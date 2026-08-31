<?php

namespace Tests\Unit\ImportExport;

use App\Models\Product;
use App\Services\ImportExport\Imports\ShoppingImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Parsing Modify Date pada ShoppingImporter harus tahan terhadap berbagai
 * bentuk nilai dari Excel:
 *  - angka serial Excel (mis. 46265.31 = 2026-08-31 07:39)
 *  - string 'd/m/Y H:i:s'
 *  - format lain / nilai tak dikenal → fallback ke hari ini (TIDAK crash).
 *
 * Regresi: Carbon::createFromFormat() melempar InvalidFormatException
 * ("The separation symbol could not be found") yang tidak tertangkap
 * → job import gagal.
 */
class ShoppingImporterDateParsingTest extends TestCase
{
    use RefreshDatabase;

    private function makeImporter(): ShoppingImporter
    {
        $importer = new ShoppingImporter(null);
        $importer->setContext(['shopping_location_id' => null, 'can_merge' => 0]);

        return $importer;
    }

    private function transform(array $extra): array
    {
        Product::factory()->create(['part_number' => 'P5022-BYA03']);

        return $this->makeImporter()->transformRow(array_merge([
            'frame_number' => 'FRAME-TEST-1',
            'part_number' => 'P5022-BYA03',
            'quantity' => 1,
            'confirmed' => true,
            'cripple' => '',
            'modify_date' => null,
        ], $extra));
    }

    public function test_excel_serial_date_is_converted(): void
    {
        $out = $this->transform(['modify_date' => 46265.31917824074]);

        $this->assertSame('2026-08-31 07:39:37', $out['shopping_date']->format('Y-m-d H:i:s'));
    }

    public function test_dmy_string_is_parsed(): void
    {
        $out = $this->transform(['modify_date' => '10/08/2026 21:18:09']);

        $this->assertSame('2026-08-10 21:18:09', $out['shopping_date']->format('Y-m-d H:i:s'));
    }

    public function test_iso_string_is_parsed(): void
    {
        $out = $this->transform(['modify_date' => '2026-08-10 10:00:00']);

        $this->assertSame('2026-08-10 10:00:00', $out['shopping_date']->format('Y-m-d H:i:s'));
    }

    public function test_invalid_date_falls_back_to_today_without_crash(): void
    {
        $out = $this->transform(['modify_date' => 'garbage-date']);

        $this->assertSame(now()->format('Y-m-d'), $out['shopping_date']->format('Y-m-d'));
    }

    public function test_null_date_falls_back_to_today(): void
    {
        $out = $this->transform(['modify_date' => null]);

        $this->assertSame(now()->format('Y-m-d'), $out['shopping_date']->format('Y-m-d'));
    }
}
