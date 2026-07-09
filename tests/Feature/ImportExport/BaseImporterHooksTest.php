<?php

namespace Tests\Feature\ImportExport;

use App\Models\Rack;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exceptions\RowTransformException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StubCompositeImporter extends BaseImporter
{
    public function modelType(): string
    {
        return Rack::class;
    }

    public function uniqueKey(): string|array
    {
        return ['code', 'zone'];
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'zone' => ['required', 'string'],
        ];
    }
}

class StubForeignKeyImporter extends BaseImporter
{
    public function modelType(): string
    {
        return Rack::class;
    }

    public function uniqueKey(): string|array
    {
        return 'code';
    }

    public function rules(): array
    {
        return ['code' => ['required']];
    }

    public function transformRow(array $mapped): array
    {
        $mapped['zone_id'] = $this->resolveForeignKey(Rack::class, 'code', $mapped['zone_lookup'] ?? null);
        unset($mapped['zone_lookup']);

        return $mapped;
    }
}

class BaseImporterHooksTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_transform_row_is_passthrough(): void
    {
        $importer = new StubCompositeImporter();

        $this->assertEquals(
            ['code' => 'A1', 'zone' => 'Z1'],
            $importer->transformRow(['code' => 'A1', 'zone' => 'Z1'])
        );
    }

    public function test_default_fixed_fields_is_empty(): void
    {
        $importer = new StubCompositeImporter();

        $this->assertEquals([], $importer->fixedFields(1));
    }

    public function test_composite_unique_key_detects_duplicate_only_on_full_match(): void
    {
        Rack::create(['code' => 'A1', 'zone' => 'Zone A']);

        $importer = new StubCompositeImporter();

        $this->assertTrue($importer->isDuplicate(['code' => 'A1', 'zone' => 'Zone A']));
        $this->assertFalse($importer->isDuplicate(['code' => 'A1', 'zone' => 'Zone B']));
    }

    public function test_null_unique_key_value_is_never_a_duplicate(): void
    {
        $importer = new StubCompositeImporter();

        $this->assertFalse($importer->isDuplicate(['code' => null, 'zone' => 'Zone A']));
    }

    public function test_resolve_foreign_key_returns_matching_id(): void
    {
        $rack = Rack::create(['code' => 'B2', 'zone' => 'Zone B']);

        $importer = new StubForeignKeyImporter();
        $result = $importer->transformRow(['zone_lookup' => 'B2']);

        $this->assertEquals($rack->id, $result['zone_id']);
    }

    public function test_resolve_foreign_key_throws_when_not_found(): void
    {
        $importer = new StubForeignKeyImporter();

        $this->expectException(RowTransformException::class);
        $importer->transformRow(['zone_lookup' => 'DOES-NOT-EXIST']);
    }

    public function test_default_template_headings_derive_from_rules(): void
    {
        $importer = new StubCompositeImporter();

        $this->assertEquals(['Code', 'Zone'], $importer->templateHeadings());
    }
}
