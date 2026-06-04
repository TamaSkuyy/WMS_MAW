<?php

namespace Database\Seeders;

use App\Models\Rack;
use Illuminate\Database\Seeder;

class RackSeeder extends Seeder
{
    public function run(): void
    {
        if (Rack::count() > 0) {
            return;
        }

        $racks = [
            ['code' => 'A-01', 'zone' => 'Zona A'],
            ['code' => 'A-02', 'zone' => 'Zona A'],
            ['code' => 'B-01', 'zone' => 'Zona B'],
            ['code' => 'B-02', 'zone' => 'Zona B'],
            ['code' => 'C-01', 'zone' => 'Zona C'],
        ];

        foreach ($racks as $rack) {
            Rack::create($rack);
        }
    }
}
