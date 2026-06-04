<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\SupplierAddress;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Supplier::factory(10)
                ->has(SupplierAddress::factory(), 'addresses')
                ->create();
    }
}
