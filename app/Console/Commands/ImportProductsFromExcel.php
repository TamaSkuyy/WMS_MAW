<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\VehicleModel;
use Illuminate\Console\Command;

class ImportProductsFromExcel extends Command
{
    protected $signature = 'product:import-from-excel
                            {file : Path ke file .xlsx (e.g. storage/app/imports/data.xlsx)}
                            {--dry-run : Hanya preview, jangan insert ke database}
                            {--default-brand=Toyota : Brand default untuk model baru yang belum ada}';

    protected $description = 'Import produk dari Excel dengan format: Supplier | Part No | Part Name | Model | Pcs/Box';

    private int $created = 0;
    private int $updated = 0;
    private int $skipped = 0;
    private int $errors = 0;
    private array $errorRows = [];

    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (! file_exists($filePath)) {
            $this->error("File tidak ditemukan: {$filePath}");
            return self::FAILURE;
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray();
        $total = count($rows);
        $this->info("📄 {$total} baris ditemukan (termasuk header)");

        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY RUN — tidak ada data yang ditulis ke database');
        }

        // Pastikan kategori default ada
        $defaultCategory = ProductCategory::firstOrCreate(
            ['name' => 'General'],
            ['description' => 'Default category for imported products']
        );

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($rows as $index => $row) {
            $bar->advance();

            // Skip header & empty rows
            if ($index === 0) {
                continue; // header row
            }
            if ($index === 1 && empty($row[0]) && empty($row[1])) {
                continue; // empty separator row
            }

            $supplierCode = isset($row[0]) ? trim((string) $row[0]) : '';
            $partNumber   = isset($row[1]) ? trim((string) $row[1]) : '';
            $partName     = isset($row[2]) ? trim((string) $row[2]) : '';
            $modelName    = isset($row[3]) ? trim((string) $row[3]) : '';
            $pcsPerBox    = isset($row[4]) ? (int) $row[4] : 1;

            if (empty($partNumber) || empty($partName)) {
                $this->errors++;
                $this->errorRows[] = "Baris {$index}: Part No atau Part Name kosong — dilewati";
                continue;
            }

            try {
                // Resolve / create supplier by code
                $supplier = Supplier::where('code', $supplierCode)->first();
                if (! $supplier && ! empty($supplierCode)) {
                    if (! $this->option('dry-run')) {
                        $supplier = Supplier::create([
                            'name' => $supplierCode,
                            'code' => $supplierCode,
                            'email' => strtolower($supplierCode) . '@example.com',
                        ]);
                    }
                }

                // Resolve / create vehicle model by name
                $vehicleModel = null;
                if (! empty($modelName)) {
                    $vehicleModel = VehicleModel::where('name', $modelName)->first();
                    if (! $vehicleModel && ! $this->option('dry-run')) {
                        $vehicleModel = VehicleModel::create([
                            'brand' => $this->option('default-brand'),
                            'name' => $modelName,
                        ]);
                    }
                }

                $unit = 'pcs';

                if ($this->option('dry-run')) {
                    $status = 'would import';
                } else {
                    $product = Product::where('part_number', $partNumber)->first();

                    if ($product) {
                        $product->update([
                            'name' => $partName,
                            'supplier_id' => $supplier?->id,
                            'vehicle_model_id' => $vehicleModel?->id,
                            'category_id' => $defaultCategory->id,
                            'unit' => $unit,
                        ]);
                        $this->updated++;
                    } else {
                        Product::create([
                            'part_number' => $partNumber,
                            'name' => $partName,
                            'supplier_id' => $supplier?->id,
                            'vehicle_model_id' => $vehicleModel?->id,
                            'category_id' => $defaultCategory->id,
                            'unit' => $unit,
                            'is_active' => true,
                        ]);
                        $this->created++;
                    }
                }
            } catch (\Throwable $e) {
                $this->errors++;
                $this->errorRows[] = "Baris {$index}: {$e->getMessage()}";
            }
        }

        $bar->finish();
        $this->line('');

        // ── Summary ────────────────────────────────────────────
        $this->info('📊 Ringkasan:');
        $this->line("   ✅ Created : {$this->created}");
        $this->line("   🔄 Updated : {$this->updated}");
        $this->line("   ⏭️  Skipped : {$this->skipped}");
        $this->line("   ❌ Errors  : {$this->errors}");

        if (! empty($this->errorRows)) {
            $this->warn("\nDetail error:");
            foreach ($this->errorRows as $err) {
                $this->line("   • {$err}");
            }
        }

        return self::SUCCESS;
    }
}
