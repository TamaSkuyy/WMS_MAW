<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionProperty;
use Spatie\Health\Checks\Checks\BackupsCheck;
use Spatie\Health\Health as HealthRegistry;
use Tests\TestCase;

/**
 * Regresi health check `Backups`.
 *
 * Dulu `BackupsCheck::new()` didaftarkan TANPA `locatedAt()`/`onDisk()`,
 * sehingga check membaca `File::glob('')`. Di container hasilnya bisa `false`
 * → `new BackupFile('')` → `Symfony\...\FileNotFoundException: The file ""
 * does not exist` → `CheckDidNotComplete` masuk log setiap 10 menit
 * (`health:check` dijalankan scheduler).
 */
class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    private function backupsCheck(): BackupsCheck
    {
        $check = collect(app(HealthRegistry::class)->registeredChecks())
            ->first(fn ($check) => $check instanceof BackupsCheck);

        $this->assertInstanceOf(BackupsCheck::class, $check, 'BackupsCheck harus terdaftar.');

        return $check;
    }

    public function test_backups_check_is_configured_with_a_disk_and_folder(): void
    {
        $check = $this->backupsCheck();

        $disk = (new ReflectionProperty($check, 'disk'))->getValue($check);
        $locatedAt = (new ReflectionProperty($check, 'locatedAt'))->getValue($check);

        // Tanpa disk + folder, check memanggil File::glob('') dan membuat
        // Symfony File dari path kosong → exception "The file "" does not exist".
        $this->assertNotNull($disk, 'BackupsCheck wajib memakai onDisk() supaya tidak membuat Symfony File dari path kosong.');
        $this->assertNotEmpty($locatedAt, 'BackupsCheck wajib punya locatedAt() berisi folder backup.');
    }

    public function test_backups_check_returns_a_result_without_crashing(): void
    {
        $result = $this->backupsCheck()->run();

        // Tidak peduli ok/failed — yang penting tidak melempar exception
        // (status crashed) walau folder backup belum ada.
        $this->assertContains($result->status->value, ['ok', 'failed', 'warning']);
    }

    /**
     * Notifikasi health default `true` dengan alamat email kosong. Kalau check
     * ada yang gagal, pengiriman notifikasi ke alamat kosong tidak boleh
     * menggagalkan `health:check` (dulu berpotensi melempar exception).
     */
    public function test_health_check_command_completes_even_with_failing_checks(): void
    {
        $this->artisan('health:check')->assertSuccessful();
    }
}
