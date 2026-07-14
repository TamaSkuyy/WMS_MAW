<?php

namespace App\Services\ImportExport\Imports;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rack;
use App\Models\Supplier;
use App\Models\VehicleModel;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;
use App\Services\ImportExport\Exceptions\RowTransformException;

class ProductImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return Product::class;
    }

    public function uniqueKey(): string|array
    {
        return 'part_number';
    }

    public function rules(): array
    {
        return [
            'part_number' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'vehicle_model_id' => ['required', 'exists:vehicle_models,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'category_id' => ['required', 'exists:product_categories,id'],
            'unit' => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'default_rack_id' => ['nullable', 'exists:racks,id'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['PartNumber', 'Nama', 'Merek', 'Model', 'Supplier', 'Kategori', 'Satuan', 'Deskripsi', 'Aktif', 'Rak'];
    }

    public function transformRow(array $mapped): array
    {
        $brand = is_string($mapped['brand'] ?? null) ? trim($mapped['brand']) : null;
        $modelName = is_string($mapped['model_kendaraan'] ?? null) ? trim($mapped['model_kendaraan']) : null;

        $vehicleModel = VehicleModel::where('brand', $brand)->where('name', $modelName)->first();

        if (! $vehicleModel) {
            throw new RowTransformException(
                'Model kendaraan "'.$brand.' '.$modelName.'" tidak ditemukan.'
            );
        }

        $mapped['vehicle_model_id'] = $vehicleModel->id;
        $mapped['supplier_id'] = $this->resolveForeignKey(Supplier::class, 'name', $mapped['supplier'] ?? null);
        $mapped['category_id'] = $this->resolveForeignKey(ProductCategory::class, 'name', $mapped['kategori'] ?? null);
        $mapped['default_rack_id'] = $this->resolveForeignKey(Rack::class, 'code', $mapped['default_rack'] ?? null, required: false);
        $mapped['is_active'] = filter_var($mapped['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);

        unset($mapped['brand'], $mapped['model_kendaraan'], $mapped['supplier'], $mapped['kategori'], $mapped['default_rack']);

        return $mapped;
    }
}
