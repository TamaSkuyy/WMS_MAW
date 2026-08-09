<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RoleAndMenuSeeder extends Seeder
{
    /**
     * Buat default admin user & assign ke role superadmin.
     *
     * ⚠️  Penting: WmsRoleSeeder HARUS dijalankan lebih dulu!
     *     WmsRoleSeeder yang bikin semua permission + role (superadmin, leader, operator).
     *     Seeder ini HANYA untuk bikin admin user-nya.
     */
    public function run(): void
    {
        // Pastikan role superadmin sudah ada (dibuat oleh WmsRoleSeeder)
        $superadminRole = Role::where('name', 'superadmin')->first();

        if (! $superadminRole) {
            throw new \RuntimeException(
                'Role "superadmin" tidak ditemukan. Jalankan WmsRoleSeeder dulu!'
            );
        }

        // Buat default admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@maw.com'],
            [
                'name' => 'Super Admin MAW',
                'password' => Hash::make('password'),
            ]
        );

        $admin->syncRoles(['superadmin']);
    }
}
