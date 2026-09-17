<?php

namespace App\Services\ImportExport\Imports;

use App\Models\Product;
use App\Models\Shopping;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;
use App\Services\ImportExport\Exceptions\RowTransformException;
use Illuminate\Support\Carbon;

class ShoppingImporter extends BaseImporter implements Importable
{
    /** Batas entri cache lookup frame (lihat lookupFrame()). */
    private const FRAME_CACHE_LIMIT = 5000;

    private ?string $currentFrameNumber = null;
    private ?Shopping $currentShopping = null;
    private ?int $shoppingLocationId = null;
    private bool $canMerge = false;

    /**
     * Alur 2 langkah (header dulu, barang kemudian):
     * - requireExistingFrame = true  → frame harus sudah ada (hasil input header).
     * - autoCreateFrame      = false → frame tak dikenal dijadikan error baris,
     *   bukan otomatis dibuat. `true` = perilaku import gabungan lama.
     */
    private bool $requireExistingFrame = false;
    private bool $autoCreateFrame = true;

    /** Cache lookup frame per import — file ribuan baris tidak menembak DB berulang. */
    private array $frameCache = [];

    /** Job ProcessImport menginstansiasi ulang importer tanpa argumen — lokasi & izin diinjeksi via setContext(). */
    public function __construct(
        ?int $shoppingLocationId = null,
        bool $requireExistingFrame = false,
        bool $autoCreateFrame = true,
    ) {
        $this->shoppingLocationId = $shoppingLocationId;
        $this->requireExistingFrame = $requireExistingFrame;
        $this->autoCreateFrame = $autoCreateFrame;
    }

    public function setContext(array $params): void
    {
        $this->shoppingLocationId = (int) ($params['shopping_location_id'] ?? 0) ?: null;
        $this->canMerge = (bool) ($params['can_merge'] ?? false);
        $this->requireExistingFrame = (bool) ($params['require_existing_frame'] ?? false);
        $this->autoCreateFrame = (bool) ($params['auto_create_frame'] ?? true);
    }

    public function contextParams(): array
    {
        return [
            'shopping_location_id' => $this->shoppingLocationId ?? 0,
            // Merge ke draft yang sudah ada = aksi edit — hanya untuk user dengan izin edit shoppings.
            'can_merge' => auth()->check() && auth()->user()->can('edit shoppings') ? 1 : 0,
            'require_existing_frame' => $this->requireExistingFrame ? 1 : 0,
            'auto_create_frame' => $this->autoCreateFrame ? 1 : 0,
        ];
    }

    /**
     * Cari shopping berdasarkan frame number — di-cache selama job berjalan.
     * Data import TAM sering mengulang frame yang sama di banyak baris.
     *
     * Cache dibatasi FRAME_CACHE_LIMIT: file dengan puluhan ribu frame unik tidak
     * boleh menahan satu model Shopping per frame sampai job selesai (memori job
     * ikut habis). Setelah penuh, lookup jatuh ke query DB lagi — hasilnya sama.
     */
    protected function lookupFrame(string $frame): ?Shopping
    {
        if (! array_key_exists($frame, $this->frameCache)) {
            $shopping = Shopping::where('frame_number', $frame)
                ->orderByDesc('id')
                ->first();

            $this->cacheFrame($frame, $shopping);

            return $shopping;
        }

        return $this->frameCache[$frame];
    }

    private function cacheFrame(string $frame, ?Shopping $shopping): void
    {
        if (count($this->frameCache) < self::FRAME_CACHE_LIMIT) {
            $this->frameCache[$frame] = $shopping;
        }
    }

    public function modelType(): string
    {
        return Shopping::class;
    }

    public function uniqueKey(): string|array
    {
        return 'frame_number';
    }

    public function rules(): array
    {
        return [
            'frame_number' => ['required', 'string', 'max:100'],
            'part_number' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:1'],
            'confirmed' => ['nullable'],
            'cripple' => ['nullable'],
            'modify_date' => ['nullable'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Frame Number', 'Part Number', 'Quantity', 'Confirmed', 'Cripple', 'Modify Date'];
    }

    public function fixedFields(int $userId): array
    {
        return [
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    public function transformRow(array $mapped): array
    {
        // Lokasi tujuan OPSIONAL — data import dari TAM tidak punya lokasi/line;
        // shopping dibuat dengan shopping_location_id null dan bisa diisi via Edit.

        // Hanya baris terkonfirmasi (kosong dianggap TRUE; Excel memparse TRUE/FALSE jadi boolean)
        $rawConfirmed = $mapped['confirmed'] ?? '';
        $confirmed = is_bool($rawConfirmed)
            ? ($rawConfirmed ? 'true' : 'false')
            : strtolower(trim((string) $rawConfirmed));
        if (in_array($confirmed, ['false', '0', 'no'], true)) {
            throw new RowTransformException("Baris tidak terkonfirmasi (Confirmed = {$confirmed}).");
        }

        // Cripple (part tidak lengkap) — kosong/No/FALSE/0 = tidak; Ya/TRUE/1 = ya.
        // Nilai diambil dari baris pertama frame (aturan: baris pertama frame menang).
        $rawCripple = $mapped['cripple'] ?? '';
        $mapped['is_cripple'] = is_bool($rawCripple)
            ? $rawCripple
            : in_array(strtolower(trim((string) $rawCripple)), ['yes', 'ya', 'y', 'true', '1'], true);

        // Frame sudah ada di sistem
        $frame = trim((string) ($mapped['frame_number'] ?? ''));
        $mapped['frame_number'] = $frame;
        if ($frame !== '') {
            $existing = $this->lookupFrame($frame);

            // Draft yang sudah ada → merge, butuh izin edit.
            if ($existing && $existing->status === 'draft' && ! $this->canMerge) {
                throw new RowTransformException("Frame {$frame} sudah ada (draft) — tidak punya izin edit untuk menggabung.");
            }

            // Alur "import barang": header wajib sudah diinput lebih dulu.
            // Kalau frame belum terdaftar & auto-create dimatikan → baris error.
            if (! $existing && $this->requireExistingFrame && ! $this->autoCreateFrame) {
                throw new RowTransformException(
                    "Frame \"{$frame}\" belum terdaftar di WMS. Input header (line & frame number) dulu, "
                    . 'atau aktifkan opsi "Buat frame otomatis".'
                );
            }

            // Frame non-draft (shipped/cripple) dianggap pesanan baru:
            // dibuatkan shopping BARU (lokasi di-lookup otomatis dari data lama).
        }

        // Part number → product
        $productId = $this->resolveForeignKey(Product::class, 'part_number', $mapped['part_number'] ?? null, true);
        $mapped['product_id'] = $productId;

        // Modify Date → shopping_date; kosong/gagal → hari ini
        // Nilai bisa berupa: objek Carbon, angka serial Excel (mis. 46265.31),
        // atau string dengan berbagai format tanggal. Parsing harus TAHAN
        // terhadap format tak terduga — Carbon::createFromFormat() melempar
        // InvalidFormatException (tidak return false), jangan sampai crash job.
        $mapped['shopping_date'] = now();
        $rawDate = $mapped['modify_date'] ?? null;

        try {
            $parsedDate = null;

            if ($rawDate instanceof \Carbon\CarbonInterface) {
                $parsedDate = $rawDate;
            } elseif (is_numeric($rawDate) && (float) $rawDate > 0) {
                // Excel serial date number (hari sejak 1900-01-01)
                $parsedDate = Carbon::instance(
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $rawDate)
                );
            } elseif (is_string($rawDate) && trim($rawDate) !== '') {
                $text = trim($rawDate);
                foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'd-m-Y H:i:s', 'd/m/Y', 'Y-m-d'] as $format) {
                    try {
                        $candidate = Carbon::createFromFormat($format, $text);
                        if ($candidate !== false) {
                            $parsedDate = $candidate;
                            break;
                        }
                    } catch (\Throwable) {
                        // coba format berikutnya
                    }
                }
            }

            if ($parsedDate !== null) {
                $mapped['shopping_date'] = $parsedDate;
            }
        } catch (\Throwable) {
            // Tanggal tak bisa diparsing → biarkan shopping_date = hari ini
        }

        return $mapped;
    }

    public function isDuplicate(array $data): bool
    {
        $frame = (string) $data['frame_number'];

        // Masih grup frame yang sama — lanjut tambah item
        if ($frame === $this->currentFrameNumber) {
            return false;
        }

        // Merge ke frame draft yang sudah ada: part duplikat di-skip
        $existing = $this->lookupFrame($frame);
        if ($existing && $existing->status === 'draft'
            && $existing->items()->where('product_id', $data['product_id'])->exists()) {
            return true;
        }

        return false;
    }

    public function insertRow(array $data): void
    {
        $frame = (string) $data['frame_number'];

        if ($frame !== $this->currentFrameNumber) {
            $existing = $this->lookupFrame($frame);

            // Penjaga tambahan: mode "header wajib ada" tanpa auto-create tidak
            // boleh membuat shopping baru (transformRow sudah memberi error).
            if (! $existing && $this->requireExistingFrame && ! $this->autoCreateFrame) {
                return;
            }

            if ($existing && $existing->status === 'draft') {
                $this->currentShopping = $existing; // merge ke draft yang sudah ada
            } else {
                // Frame baru ATAU frame yang sudah dikirim (pesanan baru):
                // lookup otomatis Lokasi Tujuan dari frame yang pernah diinput
                // manual (data TAM tidak punya lokasi) — pakai lokasi shopping
                // terbaru dengan frame yang sama.
                $locationId = $this->shoppingLocationId;
                if (! $locationId) {
                    $locationId = Shopping::where('frame_number', $frame)
                        ->whereNotNull('shopping_location_id')
                        ->orderByDesc('id')
                        ->value('shopping_location_id');
                }

                $this->currentShopping = Shopping::create([
                    'shopping_location_id' => $locationId,
                    'shopping_date' => $data['shopping_date'],
                    'frame_number' => $frame,
                    'status' => 'draft',
                    // Cripple dari baris pertama frame — saat merge ke draft existing, flag tidak diubah.
                    'is_cripple' => (bool) ($data['is_cripple'] ?? false),
                    'created_by' => $data['created_by'] ?? null,
                    'updated_by' => $data['updated_by'] ?? null,
                ]);
            }

            // Simpan ke cache supaya baris berikutnya memakai shopping yang sama
            // (kalau tidak, frame baru akan dibuat berkali-kali).
            $this->cacheFrame($frame, $this->currentShopping);
            $this->currentFrameNumber = $frame;
        }

        $product = Product::find($data['product_id']);

        $this->currentShopping->items()->create([
            'product_id' => $data['product_id'],
            'rack_id' => $product?->default_rack_id,
            'quantity' => $data['quantity'],
            'created_by' => $data['created_by'] ?? null,
            'updated_by' => $data['updated_by'] ?? null,
        ]);
    }
}
