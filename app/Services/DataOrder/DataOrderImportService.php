<?php

namespace App\Services\DataOrder;

use App\Models\Cycle;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\ImportExport\Support\RawFileImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Import file "Data Order" supplier (contoh: lampiran email TAM, sheet
 * "EMAIL") menjadi cycle rencana (draft) di aplikasi.
 *
 * Format file yang dikenali (header otomatis, urutan kolom fleksibel):
 *   No | Part Number | Part Name | Model | Supplier | CYCLE 1..N | <kolom lain> | CYCLE 1..N
 *
 * - Baris judul/aturan di atas header dilewati.
 * - Qty pengiriman per gelombang diambil dari blok CYCLE 1..N yang PERTAMA
 *   ditemukan; blok CYCLE kedua (biasanya huruf D/N penanda shift) hanya
 *   dipakai sebagai info shift, bukan qty.
 * - Baris tanpa qty (semua cycle 0) dilewati.
 * - Supplier dicocokkan via kolom `code`, produk via `part_number` di master.
 *
 * File sering di-update supplier (data lama tetap ada). Karena itu cycle
 * hasil import diberi tanda referensi (notes "Import Data Order — ref XXXXXXXX")
 * supaya import ulang bisa dideteksi:
 *   - isi file identik utk tanggal sama  → dilewati (tidak bikin duplikat);
 *   - isi file berubah (update)          → mode "replace" menghapus cycle
 *     DRAFT import lama tanggal tsb lalu membuat ulang versi terbaru;
 *     cycle yang sudah diterima (receiving/completed) tidak pernah dihapus.
 *   - mode "append"                      → selalu tambah cycle baru.
 *
 * Nomor cycle = nomor gelombang HARI ITU per supplier (reset tiap tanggal,
 * unik per supplier+tanggal): mengikuti CYCLE di file (1..N) saat hari itu
 * masih kosong; bila nomor tsb sudah terpakai dipakai nomor bebas berikutnya.
 */
class DataOrderImportService
{
    /** Prefiks notes penanda cycle hasil import file Data Order. */
    private const IMPORT_NOTE_PREFIX = 'Import Data Order — ref ';

    /** Batas jumlah baris data yang diproses dari satu file. */
    private const MAX_DATA_ROWS = 20000;

    /**
     * Baca file & susun rencana import (preview) tanpa menyentuh database.
     *
     * @return array{date:string, summary:array, suppliers:array, skipped:array, rows:array}
     */
    public function preview(UploadedFile $file, string $date): array
    {
        $parsed = $this->parseUploadedFile($file);
        $rows = $parsed['rows'];

        // Hanya baris dengan qty > 0 di minimal satu cycle yang relevan.
        $active = array_values(array_filter($rows, fn ($r) => $this->rowTotal($r) > 0));

        $supplierCodes = array_values(array_unique(array_filter(array_column($active, 'supplier_code'))));
        $partNumbers = array_values(array_unique(array_filter(array_column($active, 'part_number'))));

        $suppliers = Supplier::whereIn('code', $supplierCodes)->get()->keyBy(
            fn (Supplier $s) => strtoupper(trim($s->code))
        );
        $products = Product::whereIn('part_number', $partNumbers)->get()->keyBy('part_number');

        $knownRows = [];
        $skipped = [];
        $zeroQtyCount = count($rows) - count($active);

        foreach ($active as $r) {
            $code = strtoupper(trim($r['supplier_code']));
            $supplier = $code !== '' ? ($suppliers[$code] ?? null) : null;

            if (! $supplier) {
                $skipped[] = $this->skipRow($r, 'unknown_supplier', 'Supplier tidak dikenal di sistem');
                continue;
            }

            $product = $products[$r['part_number']] ?? null;
            if (! $product) {
                $skipped[] = $this->skipRow($r, 'unknown_product', 'Part number tidak dikenal di master produk');
                continue;
            }

            $knownRows[] = $r;
        }

        // Gabung per (supplier × cycle file): part qty dijumlah bila dobel.
        $group = [];
        foreach ($knownRows as $r) {
            $code = strtoupper(trim($r['supplier_code']));
            foreach ($r['cycles'] as $cycleNo => $qty) {
                if ($qty <= 0) {
                    continue;
                }
                $group[$code][$cycleNo][$r['part_number']] = ($group[$code][$cycleNo][$r['part_number']] ?? 0) + $qty;
                $group[$code][$cycleNo]['_shift'][$r['shift'][$cycleNo] ?? null] = true;
            }
        }

        // Susun ringkasan per supplier; baris payload untuk apply urut deterministik.
        $supplierView = [];
        $applyRows = [];

        foreach ($group as $code => $cyclesByNo) {
            ksort($cyclesByNo);
            $supplier = $suppliers[$code];
            $cycles = [];

            foreach ($cyclesByNo as $cycleNo => $parts) {
                $shiftKeys = array_keys($parts['_shift'] ?? []);
                unset($parts['_shift']);
                ksort($parts);

                $qtySum = array_sum($parts);
                $shift = $this->shiftLabel($shiftKeys);

                $cycles[] = [
                    'file_cycle' => $cycleNo,
                    'shift' => $shift,
                    'parts' => count($parts),
                    'qty' => $qtySum,
                ];

                foreach ($parts as $part => $qty) {
                    $applyRows[] = [
                        'supplier_code' => $code,
                        'cycle' => $cycleNo,
                        'part_number' => $part,
                        'quantity' => $qty,
                    ];
                }
            }

            $supplierView[] = [
                'supplier_code' => $code,
                'supplier_name' => $supplier->name,
                'cycles' => $cycles,
            ];
        }

        usort($applyRows, fn ($a, $b) => [$a['supplier_code'], $a['cycle'], $a['part_number']] <=> [$b['supplier_code'], $b['cycle'], $b['part_number']]);

        $qtyTotal = array_sum(array_column($applyRows, 'quantity'));
        $fingerprint = $this->fingerprint($applyRows, $date);

        // Deteksi import sebelumnya utk tanggal yg sama (file sering di-update).
        $previous = $this->previousImportInfo($date, array_keys($group), $suppliers, $fingerprint);

        return [
            'date' => $date,
            'summary' => [
                'zero_qty_rows' => $zeroQtyCount,
                'suppliers' => count($supplierView),
                'cycles' => array_sum(array_map(fn ($s) => count($s['cycles']), $supplierView)),
                'items' => count($applyRows),
                'qty_total' => $qtyTotal,
                'skipped' => count($skipped),
                'previous_drafts' => $previous['drafts'],
                'previous_active' => $previous['active'],
                'suppliers_with_previous' => $previous['supplierCodes'],
                'identical_previous' => $previous['identical'],
            ],
            'suppliers' => $supplierView,
            'skipped' => array_slice($skipped, 0, 200),
            'rows' => $applyRows,
        ];
    }

    /**
     * Terapkan rencana hasil preview menjadi cycle draft.
     * Baris di-resolve ulang dari master (tidak percaya payload klien).
     *
     * @param  array<int, array{supplier_code:string, cycle:int, part_number:string, quantity:int}>  $items
     * @param  string  $date  Tanggal pengiriman (Y-m-d).
     * @param  string  $mode  'replace' = hapus cycle DRAFT import lama tanggal tsb lalu buat ulang;
     *                        'append'  = tambah cycle baru tanpa menghapus yang lama.
     * @return array{ok:true, unchanged:bool, replaced_cycles:int, date:string, suppliers:int, cycles_created:int, items_created:int, qty_total:int, message:string}
     */
    public function apply(array $items, string $date, string $mode = 'append'): array
    {
        $codes = array_values(array_unique(array_map(fn ($i) => strtoupper(trim((string) $i['supplier_code'])), $items)));
        $suppliers = Supplier::whereIn('code', $codes)->get()->keyBy(
            fn (Supplier $s) => strtoupper(trim($s->code))
        );
        $parts = array_values(array_unique(array_map(fn ($i) => trim((string) $i['part_number']), $items)));
        $products = Product::whereIn('part_number', $parts)->get()->keyBy('part_number');

        // [supplier_code][cycle_no] => [part_number => qty]
        $group = [];
        foreach ($items as $i) {
            $code = strtoupper(trim((string) $i['supplier_code']));
            $part = trim((string) $i['part_number']);
            $cycleNo = (int) $i['cycle'];
            $qty = (int) $i['quantity'];

            if ($code === '' || $part === '' || $cycleNo < 1 || $cycleNo > 99 || $qty < 1) {
                continue;
            }
            if (! isset($suppliers[$code]) || ! isset($products[$part])) {
                continue;
            }

            $group[$code][$cycleNo][$part] = ($group[$code][$cycleNo][$part] ?? 0) + $qty;
        }

        if (empty($group)) {
            throw ValidationException::withMessages(['rows' => 'Tidak ada baris valid untuk dibuat menjadi cycle.']);
        }

        ksort($group);

        // Isi file yang benar-benar sama (data lama masih ada tapi tidak ada
        // perubahan) → jangan bikin duplikat.
        $flat = $this->flattenGroup($group);
        $fingerprint = $this->fingerprint($flat, $date);
        $existing = $this->findExistingImports($date, array_keys($group));

        if ($existing->isNotEmpty() && $this->allMatchFingerprint($existing, $fingerprint)) {
            return [
                'ok' => true,
                'unchanged' => true,
                'replaced_cycles' => 0,
                'date' => $date,
                'suppliers' => count($group),
                'cycles_created' => 0,
                'items_created' => 0,
                'qty_total' => 0,
                'message' => 'File ini sudah pernah diimport untuk tanggal tersebut — tidak ada perubahan, tidak ada cycle baru dibuat.',
            ];
        }

        $result = DB::transaction(function () use ($group, $suppliers, $products, $date, $mode, $fingerprint) {
            $replaced = 0;
            $cycleCount = 0;
            $itemCount = 0;
            $qtyTotal = 0;

            if ($mode === 'replace') {
                // Hapus cycle DRAFT hasil import Data Order tanggal tsb untuk
                // supplier yang ada di file (yang sudah diterima tidak disentuh).
                $replaced = Cycle::query()
                    ->whereIn('supplier_id', $suppliers->pluck('id'))
                    ->whereDate('delivery_date', $date)
                    ->where('status', 'draft')
                    ->where('notes', 'like', self::IMPORT_NOTE_PREFIX . '%')
                    ->delete();
            }

            $notes = self::IMPORT_NOTE_PREFIX . $fingerprint;

            // Nomor cycle = nomor gelombang HARI ITU per supplier (reset tiap
            // tanggal). Kosong hari itu → nomor mengikuti CYCLE file (1..N);
            // kalau nomor tsb sudah terpakai (mis. cycle manual), dipakai nomor
            // bebas berikutnya agar tidak tabrakan.
            $usedByDate = []; // supplier_id => set nomor cycle yg terpakai tanggal tsb

            foreach ($group as $code => $cyclesByNo) {
                $supplier = $suppliers[$code];
                ksort($cyclesByNo); // urut sesuai gelombang file (CYCLE 1 sebelum CYCLE 2, dst)

                if (! isset($usedByDate[$supplier->id])) {
                    $usedByDate[$supplier->id] = Cycle::where('supplier_id', $supplier->id)
                        ->whereDate('delivery_date', $date)
                        ->pluck('cycle_number')
                        ->flip()
                        ->all();
                }

                foreach ($cyclesByNo as $cycleNo => $parts) {
                    ksort($parts);

                    $cycleNumber = $this->allocateCycleNumber($usedByDate[$supplier->id], (int) $cycleNo);

                    $cycle = Cycle::create([
                        'supplier_id' => $supplier->id,
                        'cycle_number' => $cycleNumber,
                        'delivery_date' => $date,
                        'delivery_slot_id' => null,
                        'status' => 'draft',
                        'notes' => $notes,
                    ]);
                    $cycleCount++;

                    foreach ($parts as $part => $qty) {
                        $cycle->items()->create([
                            'product_id' => $products[$part]->id,
                            'quantity' => $qty,
                        ]);
                        $itemCount++;
                        $qtyTotal += $qty;
                    }
                }
            }

            return [
                'replaced' => $replaced,
                'cycles' => $cycleCount,
                'items' => $itemCount,
                'qty_total' => $qtyTotal,
            ];
        });

        return [
            'ok' => true,
            'unchanged' => false,
            'replaced_cycles' => $result['replaced'],
            'date' => $date,
            'suppliers' => count($group),
            'cycles_created' => $result['cycles'],
            'items_created' => $result['items'],
            'qty_total' => $result['qty_total'],
            'message' => $result['replaced'] > 0
                ? "{$result['replaced']} cycle draft lama diganti dengan versi terbaru."
                : '',
        ];
    }

    // ── Deteksi import ulang / update ─────────────────────────────────────────

    /**
     * Info import Data Order sebelumnya utk tanggal & supplier yang sama.
     *
     * @param  string  $date
     * @param  array<int, string>  $codes
     * @return array{drafts:int, active:int, supplierCodes:array<int,string>, identical:bool}
     */
    private function previousImportInfo(string $date, array $codes, $suppliers, string $fingerprint): array
    {
        if (! $codes) {
            return ['drafts' => 0, 'active' => 0, 'supplierCodes' => [], 'identical' => false];
        }

        $cycles = $this->findExistingImports($date, $codes);

        $drafts = $cycles->where('status', 'draft');
        $active = $cycles->whereIn('status', ['receiving', 'completed']);
        $supplierIds = $cycles->pluck('supplier_id')->unique();

        $supplierCodes = $suppliers
            ->whereIn('id', $supplierIds)
            ->pluck('code')
            ->map(fn ($c) => strtoupper(trim((string) $c)))
            ->values()
            ->all();

        return [
            'drafts' => $drafts->count(),
            'active' => $active->count(),
            'supplierCodes' => $supplierCodes,
            'identical' => $cycles->isNotEmpty() && $this->allMatchFingerprint($cycles, $fingerprint),
        ];
    }

    /** Cycle hasil import Data Order pada tanggal tsb untuk supplier tsb. */
    private function findExistingImports(string $date, array $codes): \Illuminate\Support\Collection
    {
        return Cycle::query()
            ->whereDate('delivery_date', $date)
            ->where('notes', 'like', self::IMPORT_NOTE_PREFIX . '%')
            ->whereHas('supplier', fn ($q) => $q->whereIn('code', $codes))
            ->get(['id', 'supplier_id', 'cycle_number', 'status', 'notes']);
    }

    /** Semua cycle yang ditemukan berasal dari referensi (fingerprint) yang sama. */
    private function allMatchFingerprint($cycles, string $fingerprint): bool
    {
        $refs = $cycles
            ->pluck('notes')
            ->filter(fn ($n) => is_string($n) && str_starts_with($n, self::IMPORT_NOTE_PREFIX))
            ->map(fn ($n) => substr($n, strlen(self::IMPORT_NOTE_PREFIX)))
            ->unique();

        return $refs->count() === 1 && $refs->first() === $fingerprint;
    }

    /**
     * Sidik jari isi rencana (utk deteksi file identik).
     *
     * @param  array<int, array{supplier_code:string, cycle:int, part_number:string, quantity:int}>  $rows
     */
    private function fingerprint(array $rows, string $date): string
    {
        $map = [];
        foreach ($rows as $r) {
            $key = strtoupper(trim((string) $r['supplier_code'])) . '|' . (int) $r['cycle'] . '|' . trim((string) $r['part_number']);
            $map[$key] = ($map[$key] ?? 0) + (int) $r['quantity'];
        }

        ksort($map);

        $lines = [];
        foreach ($map as $key => $qty) {
            $lines[] = $key . '|' . $qty;
        }

        return substr(md5($date . "\n" . implode("\n", $lines)), 0, 8);
    }

    /** @return array<int, array{supplier_code:string, cycle:int, part_number:string, quantity:int}> */
    private function flattenGroup(array $group): array
    {
        $rows = [];
        foreach ($group as $code => $cyclesByNo) {
            foreach ($cyclesByNo as $cycleNo => $parts) {
                foreach ($parts as $part => $qty) {
                    $rows[] = [
                        'supplier_code' => $code,
                        'cycle' => $cycleNo,
                        'part_number' => $part,
                        'quantity' => $qty,
                    ];
                }
            }
        }

        return $rows;
    }

    // ── Parsing file ─────────────────────────────────────────────────────────

    /**
     * Baca file excel/csv → baris data dengan qty per gelombang.
     *
     * @return array{rows:array<int, array{part_number:string, part_name:string, supplier_code:string, cycles:array<int,int>, shift:array<int,string>}>}
     */
    private function parseUploadedFile(UploadedFile $file): array
    {
        $path = $file->storeAs('imports/temp', uniqid('dataorder-', true) . '.' . $file->getClientOriginalExtension());

        try {
            $sheets = Excel::toArray(new RawFileImport(), Storage::path($path));
        } finally {
            Storage::delete($path);
        }

        foreach ($sheets as $sheetRows) {
            $header = $this->locateHeader($sheetRows);
            if ($header !== null) {
                return ['rows' => $this->extractRows($sheetRows, $header)];
            }
        }

        throw ValidationException::withMessages([
            'file' => 'Format file tidak dikenali — pastikan ada kolom "Part Number", "Supplier", dan "CYCLE 1..16".',
        ]);
    }

    /**
     * Temukan baris header dalam ≤15 baris pertama sebuah sheet.
     *
     * @return array{row:int, part:int, part_name:int|null, supplier:int, cycles:array<int,int>, shift:array<int,int>}|null
     */
    private function locateHeader(array $rows): ?array
    {
        foreach (array_slice($rows, 0, 15) as $ri => $row) {
            $norm = $this->normalizeRow($row);

            $part = array_search('partnumber', $norm, true);
            $supplier = array_search('supplier', $norm, true);
            if ($part === false || $supplier === false) {
                continue;
            }

            // Blok CYCLE 1..N pertama yang berurutan & naik dari 1.
            $cycles = [];
            $count = count($norm);
            for ($i = 0; $i < $count; $i++) {
                if ($norm[$i] !== 'cycle1') {
                    continue;
                }
                $candidate = [];
                $k = 1;
                while ($i < $count && $norm[$i] === 'cycle' . $k) {
                    $candidate[$k] = $i;
                    $k++;
                    $i++;
                }
                if ($candidate) {
                    $cycles = $candidate;
                    break;
                }
            }

            if (! $cycles) {
                continue;
            }

            // Blok CYCLE kedua (jika ada) = penanda shift per gelombang.
            $shift = [];
            $after = max($cycles) + 1;
            for ($i = $after; $i < $count; $i++) {
                if ($norm[$i] !== 'cycle1') {
                    continue;
                }
                $candidate = [];
                $k = 1;
                while ($i < $count && $norm[$i] === 'cycle' . $k) {
                    $candidate[$k] = $i;
                    $k++;
                    $i++;
                }
                if ($candidate) {
                    $shift = $candidate;
                }
                break;
            }

            return [
                'row' => $ri,
                'part' => $part,
                'part_name' => array_search('partname', $norm, true) ?: null,
                'supplier' => $supplier,
                'cycles' => $cycles,
                'shift' => $shift,
            ];
        }

        return null;
    }

    /**
     * @return array<int, array{part_number:string, part_name:string, supplier_code:string, cycles:array<int,int>, shift:array<int,string>}>
     */
    private function extractRows(array $rows, array $header): array
    {
        $out = [];
        $blankStreak = 0;

        foreach (array_slice($rows, $header['row'] + 1) as $row) {
            if (count($out) >= self::MAX_DATA_ROWS) {
                break;
            }

            $part = $this->cellText($row, $header['part']);
            $supplier = $this->cellText($row, $header['supplier']);
            $nameCol = $header['part_name'];

            $cycles = [];
            $shift = [];
            $hasAny = $part !== '' || $supplier !== '';
            foreach ($header['cycles'] as $k => $col) {
                $qty = $this->parseQty($this->cellText($row, $col));
                $cycles[$k] = $qty;
                if ($qty > 0) {
                    $hasAny = true;
                }
            }
            foreach ($header['shift'] as $k => $col) {
                $letter = strtoupper(trim($this->cellText($row, $col)));
                if ($letter === 'D' || $letter === 'N') {
                    $shift[$k] = $letter;
                }
            }

            if (! $hasAny) {
                $blankStreak++;
                if ($blankStreak >= 50) {
                    break; // jejeran baris kosong di akhir sheet
                }
                continue;
            }
            $blankStreak = 0;

            $out[] = [
                'part_number' => $part,
                'part_name' => $nameCol !== null ? $this->cellText($row, $nameCol) : '',
                'supplier_code' => $supplier,
                'cycles' => $cycles,
                'shift' => $shift,
            ];
        }

        return $out;
    }

    /**
     * Ambil nomor cycle utk (supplier × tanggal): pakai nomor gelombang file
     * bila masih bebas, kalau tidak ambil nomor positif bebas terkecil.
     *
     * @param  array<int, true>  $used  (by-ref) set nomor yang sudah terpakai tanggal tsb
     */
    private function allocateCycleNumber(array &$used, int $prefer): int
    {
        if ($prefer >= 1 && ! isset($used[$prefer])) {
            $used[$prefer] = true;

            return $prefer;
        }

        $n = 1;
        while (isset($used[$n])) {
            $n++;
        }
        $used[$n] = true;

        return $n;
    }

    private function rowTotal(array $row): int
    {
        return array_sum($row['cycles']);
    }

    /** @return array{part_number:string, part_name:string, supplier_code:string, quantity:int, reason:string, message:string} */
    private function skipRow(array $r, string $reason, string $message): array
    {
        return [
            'part_number' => $r['part_number'],
            'part_name' => $r['part_name'],
            'supplier_code' => $r['supplier_code'],
            'quantity' => $this->rowTotal($r),
            'reason' => $reason,
            'message' => $message,
        ];
    }

    private function shiftLabel(array $letters): ?string
    {
        $letters = array_values(array_filter($letters, fn ($l) => is_string($l) && $l !== ''));
        if (! $letters) {
            return null;
        }
        $set = array_values(array_unique($letters));
        if (count($set) === 1) {
            return $set[0];
        }

        return 'Campur';
    }

    private function parseQty(string $raw): int
    {
        if ($raw === '' || ! is_numeric($raw)) {
            return 0;
        }

        return (int) (float) $raw;
    }

    /** Normalisasi cell header: lowercase lalu buang non-alphanumerik. */
    private function normalizeRow(array $row): array
    {
        return array_map(
            fn ($c) => preg_replace('/[^a-z0-9]/', '', strtolower((string) $c)),
            $row
        );
    }

    private function cellText(array $row, int $index): string
    {
        $value = $row[$index] ?? null;

        if ($value === null) {
            return '';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.');
        }

        return trim((string) $value);
    }
}
