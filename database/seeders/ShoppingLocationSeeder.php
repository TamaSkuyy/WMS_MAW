<?php

namespace Database\Seeders;

use App\Models\ShoppingLocation;
use Illuminate\Database\Seeder;

class ShoppingLocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            'Toko Cabang Jakarta Pusat',
            'Toko Cabang Bandung',
            'Toko Cabang Surabaya',
            'Gudang Pusat',
            'Workshop',
            'Customer Direct',
            'Ekspedisi JNE',
            'Ekspedisi J&T',
            'Ekspedisi SiCepat',
            'Retur Supplier',
        ];

        foreach ($locations as $name) {
            ShoppingLocation::firstOrCreate(['name' => $name]);
        }
    }
}
