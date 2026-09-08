<?php

namespace Tests\Feature\ImportExport;

use App\Models\Cycle;
use App\Models\CycleItem;
use App\Services\ImportExport\Models\ImportLog as ImportLogModel;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ImportExport\DTOs\ImportConfig;
use App\Services\ImportExport\Enums\ImportFormat;
use App\Services\ImportExport\Enums\ImportStatus;
use App\Services\ImportExport\Imports\CycleImporter;
use App\Services\ImportExport\Jobs\ProcessImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Import Cycle via template flat "Cycle Number | Supplier | Delivery Date |
 * Part Number | Quantity | Notes" — meniru file yang dibuat user/supplier
 * (kolom Supplier berisi KODE, Delivery Date berupa serial Excel 46273,
 * baris qty 0 = "tidak ada pesanan", plus baris kosong di akhir sheet).
 */
class CycleImportSupportTest extends TestCase
{
    use RefreshDatabase;

    private function makeSupplier(string $code, string $name): Supplier
    {
        return Supplier::create([
            'name' => $name,
            'code' => $code,
            'email' => strtolower($code) . '-import@example.test',
        ]);
    }

    private function makeProduct(string $partNumber, Supplier $supplier): Product
    {
        return Product::factory()->create([
            'part_number' => $partNumber,
            'supplier_id' => $supplier->id,
            'is_active' => true,
        ]);
    }

    private function runImport(string $fileName, string $content, array $columnMapping, User $user, array $fileContent = []): ImportLogModel
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent($fileName, $content);
        $path = $file->store('imports/cycle-test');

        $importLog = ImportLogModel::factory()->create([
            'user_id' => $user->id,
            'model_type' => Cycle::class,
            'status' => 'pending',
        ]);

        $config = new ImportConfig(
            format: ImportFormat::Csv,
            filePath: Storage::path($path),
            modelType: Cycle::class,
            columnMapping: $columnMapping,
            validationRules: (new CycleImporter())->rules(),
            uniqueKey: (new CycleImporter())->uniqueKey(),
            importerClass: CycleImporter::class,
            chunkSize: 500,
        );

        (new ProcessImport($config, $importLog->id))->handle();
        $importLog->refresh();

        return $importLog;
    }

    /** Isi CSV meniru file user: kode supplier, serial tanggal Excel, qty 0, baris kosong. */
    private function templateStyleCsv(): string
    {
        return implode("\n", [
            'Cycle Number,Supplier,Delivery Date,Part Number,Quantity,Notes',
            '1,DWA,46273,P-001,5,',
            '1,DWA,46273,P-002,0,',
            '1,MMM,46273,P-003,3,',
            '1,MMM,46273,P-001,0,',
            ', , , , ,',
            ', , , , ,',
        ]);
    }

    private function cycleMapping(): array
    {
        return [
            'cycle_number' => 'Cycle Number',
            'supplier_name' => 'Supplier',
            'delivery_date' => 'Delivery Date',
            'part_number' => 'Part Number',
            'quantity' => 'Quantity',
            'notes' => 'Notes',
        ];
    }

    public function test_job_imports_user_template_style_file(): void
    {
        $user = User::factory()->create();
        $dwa = $this->makeSupplier('DWA', 'DWA Abadi Perkasa'); // kode ≠ nama
        $mmm = $this->makeSupplier('MMM', 'MMM Sentosa');
        $this->makeProduct('P-001', $dwa);
        $this->makeProduct('P-002', $dwa);
        $this->makeProduct('P-003', $mmm);

        $log = $this->runImport('cycle-template.csv', $this->templateStyleCsv(), $this->cycleMapping(), $user);

        $this->assertSame(ImportStatus::Completed->value, $log->status);
        $this->assertSame(4, $log->total_rows);       // baris kosong dibuang
        $this->assertSame(2, $log->processed_rows);   // P-001 & P-003
        $this->assertSame(2, $log->skipped_rows);     // dua qty 0
        $this->assertSame([], $log->errors);

        // Supplier DWA: cycle #1 dengan item P-001 qty 5, tanggal terserial dikonversi
        $dwaCycle = Cycle::where('supplier_id', $dwa->id)->sole();
        $this->assertSame(1, $dwaCycle->cycle_number);
        $this->assertSame('2026-09-08', $dwaCycle->delivery_date->toDateString());
        $this->assertSame(
            5,
            $dwaCycle->items()->where('product_id', $this->productId('P-001'))->value('quantity')
        );

        // Supplier MMM: cycle #1 TERPISAH berisi P-003 (bukan nyasar ke cycle DWA)
        $mmmCycle = Cycle::where('supplier_id', $mmm->id)->sole();
        $this->assertCount(1, $mmmCycle->items);
        $this->assertSame(
            3,
            $mmmCycle->items()->where('product_id', $this->productId('P-003'))->value('quantity')
        );
        $this->assertSame(0, $dwaCycle->items()->where('product_id', $this->productId('P-003'))->count());
    }

    public function test_job_auto_renumbers_when_file_cycle_number_taken(): void
    {
        $user = User::factory()->create();
        $dwa = $this->makeSupplier('DWA', 'DWA Abadi Perkasa');
        $mmm = $this->makeSupplier('MMM', 'MMM Sentosa');
        $this->makeProduct('P-001', $dwa);
        $this->makeProduct('P-003', $mmm);

        // DWA sudah punya cycle #1..3 (mis. dari hari-hari sebelumnya)
        Cycle::factory()->create(['supplier_id' => $dwa->id, 'cycle_number' => 1, 'delivery_date' => '2026-09-05']);
        Cycle::factory()->create(['supplier_id' => $dwa->id, 'cycle_number' => 2, 'delivery_date' => '2026-09-06']);
        Cycle::factory()->create(['supplier_id' => $dwa->id, 'cycle_number' => 3, 'delivery_date' => '2026-09-07']);

        // File user tetap berisi "Cycle Number 1" (dipakai ulang tiap hari)
        $log = $this->runImport('cycle-template.csv', $this->templateStyleCsv(), $this->cycleMapping(), $user);

        $this->assertSame(ImportStatus::Completed->value, $log->status);
        $this->assertSame(2, $log->processed_rows); // P-001 (DWA) & P-003 (MMM)
        $this->assertSame(2, $log->skipped_rows);   // dua qty 0
        $this->assertSame([], $log->errors);

        // Auto-renumber: DWA dapat #4 (max 3 + 1), MMM dapat #1 — tidak ada yang di-skip diam-diam
        $dwaNew = Cycle::where('supplier_id', $dwa->id)->where('delivery_date', '2026-09-08')->sole();
        $this->assertSame(4, $dwaNew->cycle_number);
        $this->assertSame(5, $dwaNew->items()->where('product_id', $this->productId('P-001'))->value('quantity'));

        $mmmCycle = Cycle::where('supplier_id', $mmm->id)->sole();
        $this->assertSame(1, $mmmCycle->cycle_number);
        $this->assertSame(3, $mmmCycle->items()->where('product_id', $this->productId('P-003'))->value('quantity'));
    }

    public function test_job_normalizes_text_date_d_m_y(): void
    {
        $user = User::factory()->create();
        $dwa = $this->makeSupplier('DWA', 'DWA Abadi Perkasa');
        $this->makeProduct('P-001', $dwa);

        $csv = "Cycle Number,Supplier,Delivery Date,Part Number,Quantity,Notes\n"
            . "1,DWA,09/08/2026,P-001,2,\n";

        $log = $this->runImport('cycle.csv', $csv, $this->cycleMapping(), $user);

        $this->assertSame(ImportStatus::Completed->value, $log->status);
        $this->assertSame(1, $log->processed_rows);
        $this->assertSame([], $log->errors);

        // Format Indonesia d/m/Y: 09/08/2026 = 9 Agustus 2026
        $cycle = Cycle::where('supplier_id', $dwa->id)->sole();
        $this->assertSame('2026-08-09', $cycle->delivery_date->toDateString());
    }

    public function test_job_reports_unknown_product_without_aborting(): void
    {
        $user = User::factory()->create();
        $dwa = $this->makeSupplier('DWA', 'DWA Abadi Perkasa');
        $this->makeProduct('P-001', $dwa);

        $csv = "Cycle Number,Supplier,Delivery Date,Part Number,Quantity,Notes\n"
            . "1,DWA,46273,P-001,5,\n"
            . "1,DWA,46273,XX-NOT-IN-MASTER,2,\n";

        $log = $this->runImport('cycle.csv', $csv, $this->cycleMapping(), $user);

        $this->assertSame(ImportStatus::Completed->value, $log->status);
        $this->assertSame(1, $log->processed_rows);
        $this->assertCount(1, $log->errors);
        $this->assertStringContainsString('XX-NOT-IN-MASTER', $log->errors[0]['message']);
    }

    private function productId(string $partNumber): int
    {
        return Product::where('part_number', $partNumber)->value('id');
    }
}
