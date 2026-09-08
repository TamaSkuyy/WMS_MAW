<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\CycleItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Import file "Data Order" supplier → cycle draft.
 *
 * Format fixture meniru file asli (sheet EMAIL): baris judul di atas header,
 * kolom identitas (No/Part Number/Part Name/Model/Supplier), blok CYCLE 1..N
 * pertama berisi qty, lalu kolom agregat + blok CYCLE kedua berisi huruf D/N.
 */
class DataOrderImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeSupplier(string $code): Supplier
    {
        return Supplier::create([
            'name' => "Supplier {$code}",
            'code' => $code,
            'email' => strtolower($code) . '@example.test',
        ]);
    }

    private function makeProduct(string $partNumber, Supplier $supplier): Product
    {
        return Product::factory()->create([
            'part_number' => $partNumber,
            'name' => "Part {$partNumber}",
            'supplier_id' => $supplier->id,
            'is_active' => true,
        ]);
    }

    /** Bytes xlsx mirroring struktur file Data Order asli. */
    private function dataOrderXlsxBytes(): string
    {
        $export = new class implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
            public function array(): array
            {
                return [
                    // Baris data (No, Part Number, Part Name, Model, Supplier,
                    // CYCLE 1-3, NG, PCS, Day, Night, lalu blok CYCLE 2 D/N)
                    [1, 'P-001', 'Part A', 'Model X', 'DWA', 5, 0, 0, 0, 0, 5, 0, 'D', 'N', 'N'],
                    [2, 'P-002', 'Part B', 'Model Y', 'DWA', 0, 7, 0, 0, 0, 0, 7, 'D', 'N', 'N'],
                    [3, 'P-003', 'Part C', 'Model Z', 'MMM', 3, 0, 0, 0, 0, 3, 0, 'D', 'N', 'N'],
                    [4, 'P-999', 'Part Baru', 'Model Z', 'MMM', 2, 0, 0, 0, 0, 2, 0, 'D', 'N', 'N'],
                    [5, 'P-001', 'Part A', 'Model X', 'ZZZ', 0, 2, 0, 0, 0, 0, 2, 'D', 'N', 'N'],
                    [6, 'P-004', 'Part D', 'Model W', 'DWA', 0, 0, 0, 0, 0, 0, 0, 'D', 'N', 'N'],
                    ['', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
                ];
            }

            public function headings(): array
            {
                return [
                    'No', 'Part Number', 'Part Name', 'Model', 'Supplier',
                    'CYCLE 1', 'CYCLE 2', 'CYCLE 3', 'NG', 'PCS', 'Day', 'Night',
                    'CYCLE 1', 'CYCLE 2', 'CYCLE 3',
                ];
            }
        };

        Excel::store($export, 'data-order-fixture.xlsx', 'local');

        return Storage::disk('local')->get('data-order-fixture.xlsx');
    }

    private function actingUser(): User
    {
        \Spatie\Permission\Models\Permission::findOrCreate('create cycles', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo('create cycles');

        return $user;
    }

    public function test_preview_summarizes_data_order_file(): void
    {
        $dwa = $this->makeSupplier('DWA');
        $mmm = $this->makeSupplier('MMM');
        $this->makeProduct('P-001', $dwa);
        $this->makeProduct('P-002', $dwa);
        $this->makeProduct('P-003', $mmm);
        $this->makeProduct('P-004', $dwa);

        $this->actingAs($this->actingUser());

        $file = UploadedFile::fake()->createWithContent('Data Order V2.xlsx', $this->dataOrderXlsxBytes());

        $response = $this->post(route('cycles.data-order.preview'), [
            'file' => $file,
            'delivery_date' => '2026-09-08',
        ]);

        $response->assertOk()
            ->assertJson([
                'date' => '2026-09-08',
                'summary' => [
                    'suppliers' => 2,
                    'cycles' => 3,
                    'items' => 3,
                    'qty_total' => 15,
                    'skipped' => 2,
                    'zero_qty_rows' => 1,
                ],
            ]);

        $json = $response->json();

        // DWA: 2 gelombang (C1 qty 5 = part P-001, C2 qty 7 = P-002)
        $dwa = collect($json['suppliers'])->firstWhere('supplier_code', 'DWA');
        $this->assertNotNull($dwa);
        $this->assertCount(2, $dwa['cycles']);
        $this->assertSame([1, 2], array_column($dwa['cycles'], 'file_cycle'));
        $this->assertSame('D', $dwa['cycles'][0]['shift']);
        $this->assertSame('N', $dwa['cycles'][1]['shift']);
        $this->assertSame(5, $dwa['cycles'][0]['qty']);
        $this->assertSame(7, $dwa['cycles'][1]['qty']);

        // MMM: 1 gelombang, part tak dikenal & supplier tak dikenal dilaporkan
        $mmm = collect($json['suppliers'])->firstWhere('supplier_code', 'MMM');
        $this->assertNotNull($mmm);
        $this->assertCount(1, $mmm['cycles']);
        $this->assertSame(3, $mmm['cycles'][0]['qty']);

        $this->assertCount(2, $json['skipped']);
        $reasons = array_column($json['skipped'], 'reason');
        $this->assertContains('unknown_product', $reasons);
        $this->assertContains('unknown_supplier', $reasons);

        // Payload apply: 3 baris — tidak boleh kemasukan qty blok CYCLE kedua (huruf D/N)
        $this->assertCount(3, $json['rows']);
    }

    public function test_apply_creates_draft_cycles_with_items(): void
    {
        $dwa = $this->makeSupplier('DWA');
        $mmm = $this->makeSupplier('MMM');
        $this->makeProduct('P-001', $dwa);
        $this->makeProduct('P-002', $dwa);
        $this->makeProduct('P-003', $mmm);

        $this->actingAs($this->actingUser());

        $response = $this->postJson(route('cycles.data-order.apply'), [
            'delivery_date' => '2026-09-08',
            'mode' => 'append',
            'rows' => [
                ['supplier_code' => 'DWA', 'cycle' => 1, 'part_number' => 'P-001', 'quantity' => 5],
                ['supplier_code' => 'DWA', 'cycle' => 2, 'part_number' => 'P-002', 'quantity' => 7],
                ['supplier_code' => 'MMM', 'cycle' => 1, 'part_number' => 'P-003', 'quantity' => 3],
            ],
        ]);

        $response->assertOk()->assertJson([
            'ok' => true,
            'date' => '2026-09-08',
            'suppliers' => 2,
            'cycles_created' => 3,
            'items_created' => 3,
            'qty_total' => 15,
        ]);

        $this->assertSame(3, Cycle::count());
        $this->assertSame(3, CycleItem::count());

        // Nomor cycle per supplier mulai 1..N; status draft & delivery_date sesuai
        $dwaCycles = Cycle::where('supplier_id', $dwa->id)->orderBy('cycle_number')->get();
        $this->assertSame([1, 2], $dwaCycles->pluck('cycle_number')->all());
        $this->assertSame(['draft', 'draft'], $dwaCycles->pluck('status')->all());
        $this->assertSame('2026-09-08', $dwaCycles[0]->delivery_date->toDateString());
        $this->assertNull($dwaCycles[0]->delivery_slot_id);

        $this->assertSame(5, $dwaCycles[0]->items()->where('product_id', $this->productId('P-001'))->value('quantity'));
        $this->assertSame(7, $dwaCycles[1]->items()->where('product_id', $this->productId('P-002'))->value('quantity'));

        $mmmCycle = Cycle::where('supplier_id', $mmm->id)->sole();
        $this->assertSame(1, $mmmCycle->cycle_number);
        $this->assertSame(3, $mmmCycle->items()->where('product_id', $this->productId('P-003'))->value('quantity'));
    }

    public function test_apply_continues_numbering_and_merges_duplicate_rows(): void
    {
        $dwa = $this->makeSupplier('DWA');
        $this->makeProduct('P-001', $dwa);

        $this->actingAs($this->actingUser());

        // Simulasikan sudah ada cycle #1 dari import/periode sebelumnya
        Cycle::factory()->create(['supplier_id' => $dwa->id, 'cycle_number' => 1, 'delivery_date' => '2026-09-07']);

        // Baris P-001 qty 5 + P-001 qty 2 pada gelombang yang sama → digabung jadi 7
        $response = $this->postJson(route('cycles.data-order.apply'), [
            'delivery_date' => '2026-09-08',
            'mode' => 'append',
            'rows' => [
                ['supplier_code' => 'DWA', 'cycle' => 1, 'part_number' => 'P-001', 'quantity' => 5],
                ['supplier_code' => 'DWA', 'cycle' => 1, 'part_number' => 'P-001', 'quantity' => 2],
            ],
        ]);

        $response->assertOk();
        // Dua baris P-001 pada gelombang (cycle) yang sama → satu cycle, qty digabung
        $this->assertSame(1, $response->json('cycles_created'));
        $this->assertSame(1, $response->json('items_created'));
        $this->assertSame(7, $response->json('qty_total'));

        // Nomor lanjut dari max yang ada: 2
        $this->assertSame([1, 2], Cycle::where('supplier_id', $dwa->id)->orderBy('cycle_number')->pluck('cycle_number')->all());
        $this->assertSame(
            7,
            CycleItem::whereHas('cycle', fn ($q) => $q->where('supplier_id', $dwa->id)->where('cycle_number', 2))
                ->where('product_id', $this->productId('P-001'))
                ->value('quantity')
        );
    }

    public function test_preview_requires_create_cycles_permission(): void
    {
        $this->makeSupplier('DWA');
        $this->actingAs(User::factory()->create()); // tanpa permission

        $file = UploadedFile::fake()->createWithContent('Data Order V2.xlsx', $this->dataOrderXlsxBytes());

        $this->post(route('cycles.data-order.preview'), [
            'file' => $file,
            'delivery_date' => '2026-09-08',
        ])->assertForbidden();
    }

    public function test_reimport_identical_file_is_skipped(): void
    {
        $dwa = $this->makeSupplier('DWA');
        $mmm = $this->makeSupplier('MMM');
        $this->makeProduct('P-001', $dwa);
        $this->makeProduct('P-002', $dwa);
        $this->makeProduct('P-003', $mmm);
        $this->actingAs($this->actingUser());

        // Baris valid setara dengan isi fixture (P-999/ZZZ tak dikenal & P-004 0-qty dilewati)
        $rows = [
            ['supplier_code' => 'DWA', 'cycle' => 1, 'part_number' => 'P-001', 'quantity' => 5],
            ['supplier_code' => 'DWA', 'cycle' => 2, 'part_number' => 'P-002', 'quantity' => 7],
            ['supplier_code' => 'MMM', 'cycle' => 1, 'part_number' => 'P-003', 'quantity' => 3],
        ];

        $first = $this->postJson(route('cycles.data-order.apply'), [
            'delivery_date' => '2026-09-08',
            'mode' => 'append',
            'rows' => $rows,
        ]);
        $first->assertOk();
        $this->assertSame(3, $first->json('cycles_created'));

        // Upload ulang file yang sama (mode apa pun) → dilewati, tidak ada duplikat
        $second = $this->postJson(route('cycles.data-order.apply'), [
            'delivery_date' => '2026-09-08',
            'mode' => 'replace',
            'rows' => $rows,
        ]);
        $second->assertOk();
        $this->assertTrue($second->json('unchanged'));
        $this->assertSame(0, $second->json('cycles_created'));
        $this->assertSame(3, Cycle::whereIn('supplier_id', [$dwa->id, $mmm->id])->count());

        // Preview juga menandai file identik (sudah pernah diimport utk tanggal tsb)
        $file = UploadedFile::fake()->createWithContent('Data Order V2.xlsx', $this->dataOrderXlsxBytes());
        $preview = $this->post(route('cycles.data-order.preview'), [
            'file' => $file,
            'delivery_date' => '2026-09-08',
        ]);
        $preview->assertOk();
        $this->assertTrue($preview->json('summary.identical_previous'));
        $this->assertSame(3, $preview->json('summary.previous_drafts'));
    }

    public function test_replace_updates_previous_import_without_touching_others(): void
    {
        $dwa = $this->makeSupplier('DWA');
        $this->makeProduct('P-001', $dwa);
        $this->makeProduct('P-002', $dwa);
        $this->actingAs($this->actingUser());

        // Versi lama: cycle draft import #1 (DWA, C1 = P-001 qty 5)
        $this->postJson(route('cycles.data-order.apply'), [
            'delivery_date' => '2026-09-08',
            'mode' => 'append',
            'rows' => [
                ['supplier_code' => 'DWA', 'cycle' => 1, 'part_number' => 'P-001', 'quantity' => 5],
            ],
        ])->assertOk();

        // Cycle manual (bukan hasil import) + cycle import yang sudah completed — tak boleh dihapus
        Cycle::factory()->create([
            'supplier_id' => $dwa->id, 'cycle_number' => 9, 'delivery_date' => '2026-09-08',
            'status' => 'draft', 'notes' => null,
        ]);
        Cycle::factory()->create([
            'supplier_id' => $dwa->id, 'cycle_number' => 10, 'delivery_date' => '2026-09-08',
            'status' => 'completed', 'notes' => 'Import Data Order — ref 12345678',
        ]);

        // File di-update: qty P-001 berubah 5→9 dan ada gelombang baru C2 (P-002 7)
        $updated = $this->postJson(route('cycles.data-order.apply'), [
            'delivery_date' => '2026-09-08',
            'mode' => 'replace',
            'rows' => [
                ['supplier_code' => 'DWA', 'cycle' => 1, 'part_number' => 'P-001', 'quantity' => 9],
                ['supplier_code' => 'DWA', 'cycle' => 2, 'part_number' => 'P-002', 'quantity' => 7],
            ],
        ]);
        $updated->assertOk();
        $this->assertFalse($updated->json('unchanged'));
        $this->assertSame(1, $updated->json('replaced_cycles')); // hanya draft import lama
        $this->assertSame(2, $updated->json('cycles_created'));

        $cycles = Cycle::where('supplier_id', $dwa->id)->orderBy('cycle_number')->get();
        // Draft import lama (#1) hilang; manual (#9) & completed (#10) tetap ada
        $this->assertSame([9, 10, 11, 12], $cycles->pluck('cycle_number')->all());
        $this->assertSame('completed', $cycles->firstWhere('cycle_number', 10)->status);

        $newCycle = $cycles->firstWhere('cycle_number', 11);
        $this->assertSame(
            9,
            $newCycle->items()->where('product_id', $this->productId('P-001'))->value('quantity')
        );
        $this->assertSame(
            7,
            $cycles->firstWhere('cycle_number', 12)
                ->items()->where('product_id', $this->productId('P-002'))->value('quantity')
        );
    }

    public function test_append_mode_adds_cycles_alongside_previous(): void
    {
        $dwa = $this->makeSupplier('DWA');
        $this->makeProduct('P-001', $dwa);
        $this->actingAs($this->actingUser());

        $rowsV1 = [
            ['supplier_code' => 'DWA', 'cycle' => 1, 'part_number' => 'P-001', 'quantity' => 5],
        ];
        $this->postJson(route('cycles.data-order.apply'), [
            'delivery_date' => '2026-09-08',
            'mode' => 'append',
            'rows' => $rowsV1,
        ])->assertOk();

        // Isi berbeda (update) tapi sengaja memilih mode "append"
        $v2 = $this->postJson(route('cycles.data-order.apply'), [
            'delivery_date' => '2026-09-08',
            'mode' => 'append',
            'rows' => [
                ['supplier_code' => 'DWA', 'cycle' => 1, 'part_number' => 'P-001', 'quantity' => 3],
            ],
        ]);
        $v2->assertOk();
        $this->assertSame(1, $v2->json('cycles_created'));

        // Draft lama tetap ada; versi baru menumpuk dengan nomor lanjut
        $this->assertSame(2, Cycle::where('supplier_id', $dwa->id)->count());
        $this->assertSame([1, 2], Cycle::where('supplier_id', $dwa->id)->orderBy('cycle_number')->pluck('cycle_number')->all());
    }

    private function productId(string $partNumber): int
    {
        return Product::where('part_number', $partNumber)->value('id');
    }
}
