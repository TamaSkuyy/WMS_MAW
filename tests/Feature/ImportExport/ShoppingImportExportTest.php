<?php

namespace Tests\Feature\ImportExport;

use App\Models\Product;
use App\Models\Shopping;
use App\Models\ShoppingLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ShoppingImportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ShoppingLocation $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::findOrCreate('create shoppings'));
        $this->user->givePermissionTo(Permission::findOrCreate('edit shoppings'));
        $this->location = ShoppingLocation::create(['name' => 'Line A']);
    }

    private function sampleCsv(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'shopping.csv',
            "Frame Number,Part Number,Quantity,Confirmed,Modify Date\n"
            . "MHKAA1BY4TJ021240,P5022-BYA03,1,TRUE,10/08/2026 21:18:09\n"
            . "MHKAA1BY4TJ021240,60118-TAD26,1,TRUE,10/08/2026 21:18:09\n"
            . "MHKAA1BY4TJ021240,21004-TAD26,1,FALSE,10/08/2026 21:18:08\n"
            . "MHK6GK6JTJ093724,P5634-BYA18,1,TRUE,10/08/2026 21:19:10"
        );
    }

    private function mapping(): array
    {
        return [
            'frame_number' => 'Frame Number',
            'part_number' => 'Part Number',
            'quantity' => 'Quantity',
            'confirmed' => 'Confirmed',
            'modify_date' => 'Modify Date',
        ];
    }

    public function test_import_template_downloads_successfully(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('shoppings.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_preview_returns_headers(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('shoppings.import.preview'), [
            'file' => $this->sampleCsv(),
            'shopping_location_id' => $this->location->id,
        ]);

        $response->assertOk();
        $this->assertContains('Frame Number', $response->json('headers'));
    }

    public function test_import_creates_shoppings_grouped_by_frame(): void
    {
        Product::factory()->create(['part_number' => 'P5022-BYA03']);
        Product::factory()->create(['part_number' => '60118-TAD26']);
        Product::factory()->create(['part_number' => '21004-TAD26']);
        Product::factory()->create(['part_number' => 'P5634-BYA18']);
        $this->actingAs($this->user);

        $this->post(route('shoppings.import'), [
            'file' => $this->sampleCsv(),
            'shopping_location_id' => $this->location->id,
            'column_mapping' => $this->mapping(),
        ])->assertOk();

        $frame1 = Shopping::where('frame_number', 'MHKAA1BY4TJ021240')->first();
        $frame2 = Shopping::where('frame_number', 'MHK6GK6JTJ093724')->first();

        $this->assertNotNull($frame1);
        $this->assertNotNull($frame2);
        $this->assertSame('draft', $frame1->status);
        $this->assertSame($this->location->id, $frame1->shopping_location_id);
        $this->assertSame('2026-08-10', $frame1->shopping_date->format('Y-m-d'));
        $this->assertCount(2, $frame1->items); // baris FALSE di-skip
        $this->assertCount(1, $frame2->items);
    }

    public function test_import_skips_unknown_part_and_continues(): void
    {
        Product::factory()->create(['part_number' => 'P5022-BYA03']);
        $this->actingAs($this->user);

        $file = UploadedFile::fake()->createWithContent(
            'shopping.csv',
            "Frame Number,Part Number,Quantity,Confirmed,Modify Date\n"
            . "MHKAA1BY4TJ021240,P5022-BYA03,1,TRUE,10/08/2026 21:18:09\n"
            . "MHKAA1BY4TJ021240,UNKNOWN-PART,1,TRUE,10/08/2026 21:18:09"
        );

        $this->post(route('shoppings.import'), [
            'file' => $file,
            'shopping_location_id' => $this->location->id,
            'column_mapping' => $this->mapping(),
        ])->assertOk();

        $shopping = Shopping::where('frame_number', 'MHKAA1BY4TJ021240')->first();
        $this->assertNotNull($shopping);
        $this->assertCount(1, $shopping->items);
    }

    public function test_import_merges_into_existing_draft_frame(): void
    {
        $existing = Product::factory()->create(['part_number' => 'P5022-BYA03']);
        $newPart = Product::factory()->create(['part_number' => '60118-TAD26']);
        Product::factory()->create(['part_number' => '21004-TAD26']);
        Product::factory()->create(['part_number' => 'P5634-BYA18']);

        $shopping = Shopping::create([
            'shopping_location_id' => $this->location->id,
            'shopping_date' => now(),
            'frame_number' => 'MHKAA1BY4TJ021240',
            'status' => 'draft',
        ]);
        $shopping->items()->create(['product_id' => $existing->id, 'quantity' => 1]);
        $this->actingAs($this->user);

        $this->post(route('shoppings.import'), [
            'file' => $this->sampleCsv(),
            'shopping_location_id' => $this->location->id,
            'column_mapping' => $this->mapping(),
        ])->assertOk();

        $shopping->refresh();
        $this->assertCount(2, $shopping->items); // part lama tidak diduplikasi, part baru ditambah
        $this->assertTrue($shopping->items()->where('product_id', $newPart->id)->exists());
    }

    public function test_import_rejects_merge_into_shipped_frame(): void
    {
        $part = Product::factory()->create(['part_number' => 'P5022-BYA03']);
        Product::factory()->create(['part_number' => '60118-TAD26']);
        Product::factory()->create(['part_number' => '21004-TAD26']);
        Product::factory()->create(['part_number' => 'P5634-BYA18']);

        $shopping = Shopping::create([
            'shopping_location_id' => $this->location->id,
            'shopping_date' => now(),
            'frame_number' => 'MHKAA1BY4TJ021240',
            'status' => 'shipped',
        ]);
        $shopping->items()->create(['product_id' => $part->id, 'quantity' => 1]);
        $this->actingAs($this->user);

        $this->post(route('shoppings.import'), [
            'file' => $this->sampleCsv(),
            'shopping_location_id' => $this->location->id,
            'column_mapping' => $this->mapping(),
        ])->assertOk();

        $shopping->refresh();
        $this->assertCount(1, $shopping->items); // tidak ada item baru
        $this->assertSame(2, Shopping::count()); // frame shipped tidak dibuat baru; frame kedua tetap dibuat
    }

    public function test_import_denies_merge_without_edit_permission(): void
    {
        $part = Product::factory()->create(['part_number' => 'P5022-BYA03']);
        $newPart = Product::factory()->create(['part_number' => '60118-TAD26']);

        $shopping = Shopping::create([
            'shopping_location_id' => $this->location->id,
            'shopping_date' => now(),
            'frame_number' => 'MHKAA1BY4TJ021240',
            'status' => 'draft',
        ]);
        $shopping->items()->create(['product_id' => $part->id, 'quantity' => 1]);

        // User HANYA punya izin create (tanpa edit)
        $createOnly = User::factory()->create();
        $createOnly->givePermissionTo(Permission::findOrCreate('create shoppings'));
        $this->actingAs($createOnly);

        $file = UploadedFile::fake()->createWithContent(
            'shopping.csv',
            "Frame Number,Part Number,Quantity,Confirmed,Modify Date\n"
            . "MHKAA1BY4TJ021240,{$newPart->part_number},1,TRUE,10/08/2026 21:18:09"
        );

        $this->post(route('shoppings.import'), [
            'file' => $file,
            'shopping_location_id' => $this->location->id,
            'column_mapping' => $this->mapping(),
        ])->assertOk();

        $shopping->refresh();
        $this->assertCount(1, $shopping->items); // tidak ada item yang ditambahkan
        $this->assertSame(1, Shopping::count()); // tidak ada shopping baru untuk frame itu
    }

    public function test_import_requires_location(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('shoppings.import'), [
            'file' => $this->sampleCsv(),
            'column_mapping' => $this->mapping(),
        ]);

        $response->assertSessionHasErrors('shopping_location_id');
    }
}
