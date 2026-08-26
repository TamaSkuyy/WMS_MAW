<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seed akun karyawan produksi (User + Employee + role) dari daftar tim warehouse.
 *
 * Cara pakai (dev atau production):
 *   php artisan db:seed --class=ProductionEmployeeSeeder
 *
 * Prasyarat: role & permission sudah ada (WmsRoleSeeder) — seeder ini
 * otomatis menjalankannya jika role superadmin belum ada.
 *
 * - Email dibuat otomatis dari nama: "Mega Saputra" → mega.saputra@maw.com
 * - Password default: maw12345 (disarankan diganti user setelah login pertama)
 * - Idempotent: aman dijalankan berulang — akun yang sudah ada tetap
 *   di-sync (role + password default), jadi bisa dipakai juga untuk
 *   mereset password user yang lupa.
 */
class ProductionEmployeeSeeder extends Seeder
{
    public const DEFAULT_PASSWORD = 'maw12345';

    private const EMAIL_DOMAIN = 'maw.com';

    /** [nama, jabatan (job position), tipe akun (role)] */
    private const PEOPLE = [
        ['Mega Saputra', 'Kordinator Warehouse', 'superadmin'],
        ['Wahyu Kurniawan', 'Team Leader', 'leader'],
        ['Ahmad R Sonjaya', 'Team Leader', 'leader'],
        ['M. Iqbal Ramadhan', 'Operator', 'operator'],
        ['Apsaka D Perkasa', 'Operator', 'operator'],
        ['M. Arifin A Laksono', 'Operator', 'operator'],
        ['Lukman Charlie M', 'Operator', 'operator'],
        ['Raka Dwi M', 'Operator', 'operator'],
        ['Nanda Hermawan', 'Operator', 'operator'],
        ['Eka Agus G', 'Operator', 'operator'],
        ['Reno Aprizal', 'Operator', 'operator'],
        ['Azhar Fauzan', 'Operator', 'operator'],
        ['Cahaya Muhamad', 'Operator', 'operator'],
        ['Achmad Jaelani', 'Operator', 'operator'],
        ['Dede Yana', 'Operator', 'operator'],
        ['Agung Febriansyah', 'Operator', 'operator'],
        ['Rido M Darda', 'Operator', 'operator'],
        ['Syaepul Rohman', 'Operator', 'operator'],
        ['Maulana Firdaus', 'Operator', 'operator'],
        ['Asep M Gufron', 'Operator', 'operator'],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        if (! Role::where('name', 'superadmin')->exists()) {
            $this->call(WmsRoleSeeder::class);
        }

        $superadminId = User::role('superadmin')->value('id') ?? User::min('id');
        $jobPositions = [];

        $this->command?->info('Membuat akun karyawan produksi...');
        $this->command?->info('Password default: '.self::DEFAULT_PASSWORD);

        foreach (self::PEOPLE as [$name, $jobTitle, $role]) {
            $jobPosition = $jobPositions[$jobTitle] ??= JobPosition::firstOrCreate(
                ['name' => $jobTitle],
                ['level' => $this->levelForRole($role), 'role_name' => $role],
            );

            $email = $this->buildEmail($name);

            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => self::DEFAULT_PASSWORD],
            );
            $user->syncRoles([$role]);

            // Force re-login agar permission role terbaru langsung berlaku
            DB::table('sessions')->where('user_id', $user->id)->delete();

            Employee::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $name,
                    'job_position_id' => $jobPosition->id,
                    'email' => $email,
                    'status' => 'Aktif',
                    'created_by' => $superadminId,
                    'updated_by' => $superadminId,
                ],
            );

            // Link dua arah: employees.user_id (link utama) + users.employee_id
            if (! $user->employee_id) {
                $user->employee_id = Employee::where('user_id', $user->id)->value('id');
                $user->save();
            }

            $this->command?->info(sprintf(
                '  ✔ %-22s %-28s %-11s %s',
                $name,
                $email,
                self::DEFAULT_PASSWORD,
                $role,
            ));
        }

        $this->command?->info('Selesai: '.count(self::PEOPLE).' akun dibuat/diperbarui.');
    }

    private function buildEmail(string $name): string
    {
        $slug = Str::lower(Str::ascii($name));
        $slug = (string) preg_replace('/[^a-z0-9]+/', '.', $slug);
        $slug = trim($slug, '.');
        $slug = (string) preg_replace('/\.{2,}/', '.', $slug);

        return $slug.'@'.self::EMAIL_DOMAIN;
    }

    private function levelForRole(string $role): string
    {
        return match ($role) {
            'superadmin' => 'Supervisor',
            'leader' => 'Leader',
            default => 'Staff',
        };
    }
}
