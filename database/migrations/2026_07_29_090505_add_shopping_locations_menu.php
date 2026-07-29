<?php

use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $masterData = Menu::where('name', 'Master Data')->first();
        if ($masterData) {
            Menu::create([
                'name' => 'Lokasi Tujuan',
                'icon' => 'PinIcon',
                'path' => '/shopping-locations',
                'parent_id' => $masterData->id,
                'sort_order' => 81, // between Departemen (8) and Karyawan (9)
                'group' => 'main',
            ]);
        }
    }

    public function down(): void
    {
        Menu::where('name', 'Lokasi Tujuan')->delete();
    }
};
