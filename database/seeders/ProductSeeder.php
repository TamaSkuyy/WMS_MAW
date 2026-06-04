<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\VehicleModel;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Seed vehicle models if empty
        if (VehicleModel::count() === 0) {
            $models = [
                ['name' => 'Fortuner VRZ', 'brand' => 'Toyota'],
                ['name' => 'Avanza G', 'brand' => 'Toyota'],
                ['name' => 'Innova Zenix', 'brand' => 'Toyota'],
                ['name' => 'CR-V Turbo', 'brand' => 'Honda'],
                ['name' => 'Xpander Cross', 'brand' => 'Mitsubishi'],
            ];
            foreach ($models as $model) {
                VehicleModel::create($model);
            }
        }

        // Seed categories if empty
        if (ProductCategory::count() === 0) {
            $categories = [
                ['name' => 'Body Parts', 'description' => 'Exterior body components'],
                ['name' => 'Engine Components', 'description' => 'Engine and related parts'],
                ['name' => 'Electrical', 'description' => 'Electrical and electronic parts'],
                ['name' => 'Interior', 'description' => 'Interior trim and accessories'],
                ['name' => 'Suspension & Brakes', 'description' => 'Suspension and braking systems'],
            ];
            foreach ($categories as $cat) {
                ProductCategory::create($cat);
            }
        }

        // Seed sample products
        Product::factory(20)->create();
    }
}
