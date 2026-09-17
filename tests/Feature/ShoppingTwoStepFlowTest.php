<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shopping;
use App\Models\ShoppingLocation;
use App\Models\User;
use App\Services\ImportExport\DTOs\ImportConfig;
use App\Services\ImportExport\Enums\ImportFormat;
use App\Services\ImportExport\Imports\ShoppingHeaderImporter;
use App\Services\ImportExport\Imports\ShoppingItemImporter;
use App\Services\ImportExport\Jobs\ProcessImport;
use App\Services\ImportExport\Models\ImportLog as ImportLogModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Alur 2 langkah Shopping: (1) input/import HEADER (line + frame number),
 * (2) import BARANG (part number + qty) yang dicocokkan ke header tersebut.
 */
class ShoppingTwoStepFlowTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function location(string $name, string $barcode = ''): ShoppingLocation
    {
        return ShoppingLocation::create(['name' => $name, 'barcode' => $barcode ?: null]);
    }

    private function product(string $partNumber): Product
    {
        return Product::factory()->create([
            'part_number' => $partNumber,
            'is_active' => true,
        ]);
    }

    /**
     * Jalankan importer persis seperti job queue: importer di-rekonstruksi
     * tanpa argumen lalu konteks diinjeksi dari ImportConfig.
     */
    private function runImport(string $csv, array $columnMapping, User $user, string $importerClass): ImportLogModel
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent('import.csv', $csv);
        $path = $file->store('imports/test');

        $importLog = ImportLogModel::factory()->create([
            'user_id' => $user->id,
            'model_type' => Shopping::class,
            'status' => 'pending',
        ]);

        $importer = new $importerClass();
        $this->actingAs($user);

        $config = new ImportConfig(
            format: ImportFormat::Csv,
            filePath: Storage::path($path),
            modelType: Shopping::class,
            columnMapping: $columnMapping,
            validationRules: $importer->rules(),
            uniqueKey: $importer->uniqueKey(),
            importerClass: $importerClass,
            chunkSize: 500,
            importerParams: $importer->contextParams(),
        );

        (new ProcessImport($config, $importLog->id))->handle();
        $importLog->refresh();

        return $importLog;
    }

    // ── Langkah 1: input header manual ──────────────────────────────────

    public function test_input_header_saves_multiple_draft_frames_and_skips_duplicates(): void
    {
        $user = $this->userWith(['create shoppings']);
        $line = $this->location('LINE A', 'BAR-A');
        Shopping::factory()->create(['frame_number' => 'FR-000', 'status' => 'draft']);

        $response = $this->actingAs($user)->post(route('shoppings.headers.store'), [
            'shopping_date' => '2026-09-16 08:30:00',
            'rows' => [
                ['frame_number' => 'FR-001', 'shopping_location_id' => $line->id],
                ['frame_number' => 'FR-001', 'shopping_location_id' => $line->id], // dobel di form
                ['frame_number' => 'FR-000', 'shopping_location_id' => $line->id], // sudah ada di DB
                ['frame_number' => 'FR-002', 'shopping_location_id' => null, 'is_cripple' => true],
                ['frame_number' => '   ', 'shopping_location_id' => $line->id],    // baris kosong
            ],
        ]);

        $response->assertRedirect(route('shoppings.headers.create'));

        $this->assertSame(1, Shopping::where('frame_number', 'FR-001')->count());
        $this->assertDatabaseHas('shoppings', [
            'frame_number' => 'FR-001',
            'shopping_location_id' => $line->id,
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('shoppings', [
            'frame_number' => 'FR-002',
            'is_cripple' => true,
        ]);
        // Header saja: belum ada barang.
        $this->assertSame(0, \App\Models\ShoppingItem::count());

        // Operator diberi tahu berapa yang dilewati.
        $response->assertSessionHas('success');
    }

    public function test_input_header_requires_at_least_one_frame(): void
    {
        $user = $this->userWith(['create shoppings']);

        $response = $this->actingAs($user)->post(route('shoppings.headers.store'), [
            'rows' => [['frame_number' => '  ']],
        ]);

        $response->assertSessionHasErrors('rows');
        $this->assertSame(0, Shopping::count());
    }

    // ── Langkah 2: import barang ────────────────────────────────────────

    public function test_item_import_fills_items_for_registered_frame_only(): void
    {
        $user = $this->userWith(['create shoppings', 'edit shoppings']);
        $this->product('P-001');
        $header = Shopping::factory()->create(['frame_number' => 'FR-100', 'status' => 'draft']);

        $csv = implode("\n", [
            'Frame Number,Part Number,Quantity',
            'FR-100,P-001,5',
            'FR-999,P-001,3',
        ]);

        $log = $this->runImport($csv, [
            'frame_number' => 'Frame Number',
            'part_number' => 'Part Number',
            'quantity' => 'Quantity',
        ], $user, ShoppingItemImporter::class);

        $this->assertSame('completed', $log->status);
        $this->assertSame(1, $log->processed_rows);
        $this->assertSame(1, $header->items()->count());
        $this->assertSame(5, (int) $header->items()->first()->quantity);

        // Frame yang belum terdaftar TIDAK dibuat otomatis — dilaporkan sebagai error.
        $this->assertSame(0, Shopping::where('frame_number', 'FR-999')->count());
        $this->assertCount(1, $log->errors);
        $this->assertStringContainsString('belum terdaftar', (string) $log->errors[0]['message']);
    }

    public function test_item_import_can_auto_create_unknown_frames_when_enabled(): void
    {
        $user = $this->userWith(['create shoppings', 'edit shoppings']);
        $this->product('P-002');
        $line = $this->location('LINE B');

        $csv = "Frame Number,Part Number,Quantity\nFR-888,P-002,7\n";

        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('import.csv', $csv);
        $path = $file->store('imports/test');

        $this->actingAs($user);
        $importer = new ShoppingItemImporter($line->id, true); // auto_create_frame = true

        $log = ImportLogModel::factory()->create([
            'user_id' => $user->id,
            'model_type' => Shopping::class,
            'status' => 'pending',
        ]);

        $config = new ImportConfig(
            format: ImportFormat::Csv,
            filePath: Storage::path($path),
            modelType: Shopping::class,
            columnMapping: [
                'frame_number' => 'Frame Number',
                'part_number' => 'Part Number',
                'quantity' => 'Quantity',
            ],
            validationRules: $importer->rules(),
            uniqueKey: $importer->uniqueKey(),
            importerClass: ShoppingItemImporter::class,
            chunkSize: 500,
            importerParams: $importer->contextParams(),
        );

        (new ProcessImport($config, $log->id))->handle();
        $log->refresh();

        $this->assertSame('completed', $log->status);
        $this->assertSame([], $log->errors ?? []);
        $this->assertDatabaseHas('shoppings', [
            'frame_number' => 'FR-888',
            'shopping_location_id' => $line->id,
            'status' => 'draft',
        ]);
    }

    // ── Import file header (disiapkan, tombol UI disembunyikan) ─────────

    public function test_header_file_import_creates_draft_frames(): void
    {
        $user = $this->userWith(['create shoppings', 'edit shoppings']);
        $this->location('LINE C', 'BAR-C');

        $csv = implode("\n", [
            'Line,Frame Number',
            'LINE C,FR-201',
            'BAR-C,FR-202',
            'LINE TIDAK ADA,FR-203',
        ]);

        $log = $this->runImport($csv, [
            'line' => 'Line',
            'frame_number' => 'Frame Number',
        ], $user, ShoppingHeaderImporter::class);

        $this->assertSame('completed', $log->status);
        $this->assertSame(2, $log->processed_rows);
        $this->assertDatabaseHas('shoppings', ['frame_number' => 'FR-201', 'status' => 'draft']);
        $this->assertDatabaseHas('shoppings', ['frame_number' => 'FR-202', 'status' => 'draft']);
        $this->assertSame(0, Shopping::where('frame_number', 'FR-203')->count());
        $this->assertCount(1, $log->errors);
    }

    // ── Endpoint pencarian frame + payload halaman index (anti-502) ─────

    public function test_draft_frames_endpoint_searches_and_limits(): void
    {
        $user = $this->userWith(['view shoppings']);
        $line = $this->location('LINE D');

        foreach (range(1, 40) as $i) {
            Shopping::factory()->create([
                'frame_number' => sprintf('FRM-%03d', $i),
                'status' => 'draft',
                'shopping_location_id' => $line->id,
            ]);
        }
        Shopping::factory()->create(['frame_number' => 'FRM-999', 'status' => 'shipped']);

        $response = $this->actingAs($user)->getJson(route('shoppings.draft-frames', ['limit' => 30]));
        $response->assertOk();
        $this->assertCount(30, $response->json('data'));
        $this->assertTrue($response->json('has_more'));
        $this->assertSame(40, $response->json('total'));

        // Scan = pencarian persis (frame di luar 30 hasil pertama tetap ketemu).
        $exact = $this->actingAs($user)->getJson(route('shoppings.draft-frames', ['frame' => 'FRM-039']));
        $exact->assertOk();
        $this->assertCount(1, $exact->json('data'));
        $this->assertSame('FRM-039', $exact->json('data.0.frame_number'));
    }

    public function test_index_sends_only_frame_count_not_the_whole_draft_list(): void
    {
        $user = $this->userWith(['view shoppings']);
        Shopping::factory(5)->create(['status' => 'draft', 'frame_number' => 'FRX-1']);

        $response = $this->actingAs($user)->get(route('shoppings.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Transactions/Shopping/Index')
            ->where('draftFrameCount', 5)
            // Daftar utuh inilah yang dulu membuat payload raksasa → 502 setelah
            // import besar. Sekarang harus benar-benar tidak dikirim lagi.
            ->missing('draftShoppings'));
    }
}
