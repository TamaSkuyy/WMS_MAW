<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionRenames = [
            'view shipments' => 'view shoppings',
            'create shipments' => 'create shoppings',
            'edit shipments' => 'edit shoppings',
            'delete shipments' => 'delete shoppings',
            'ship shipments' => 'ship shoppings',
        ];

        foreach ($permissionRenames as $old => $new) {
            DB::table('permissions')->where('name', $old)->update(['name' => $new]);
        }

        DB::table('menus')->where('path', '/shipments')->update(['path' => '/shoppings']);

        DB::table('menus')->where('name', 'Shipment Report')->update([
            'name' => 'Shopping Report',
            'path' => '/reports/shopping',
        ]);
    }

    public function down(): void
    {
        $permissionRenames = [
            'view shoppings' => 'view shipments',
            'create shoppings' => 'create shipments',
            'edit shoppings' => 'edit shipments',
            'delete shoppings' => 'delete shipments',
            'ship shoppings' => 'ship shipments',
        ];

        foreach ($permissionRenames as $old => $new) {
            DB::table('permissions')->where('name', $old)->update(['name' => $new]);
        }

        DB::table('menus')->where('path', '/shoppings')->update(['path' => '/shipments']);

        DB::table('menus')->where('name', 'Shopping Report')->update([
            'name' => 'Shipment Report',
            'path' => '/reports/shipment',
        ]);
    }
};
