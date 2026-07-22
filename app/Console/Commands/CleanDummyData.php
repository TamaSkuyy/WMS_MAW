<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanDummyData extends Command
{
    protected $signature = 'db:clean
                            {--force : Skip confirmation prompt}
                            {--keep-users : Keep existing users (only clear transactional/master data)}';

    protected $description = 'Hapus semua data dummy/factory dan reset ke data sistem essential. Siap untuk data production.';

    /**
     * Tables that hold transactional or master data — will be truncated.
     * Order matters: child tables (with FKs) must come before their parents.
     */
    private const DATA_TABLES = [
        // Transactional
        'cycle_items',
        'cycles',
        'shopping_items',
        'shoppings',
        'stocks',
        'import_logs',
        'notifications',

        // Pivot / FK-heavy
        'supplier_delivery_schedules',
        'supplier_addresses',

        // Master data
        'suppliers',
        'products',
        'product_categories',
        'vehicle_models',
        'racks',
        'departments',
        'employees',
        'job_positions',
        'work_locations',
        'shifts',
    ];

    /**
     * Tables that hold system configuration — truncated then re-seeded.
     */
    private const CONFIG_TABLES = [
        'menus',
        'model_has_permissions',
        'model_has_roles',
        'role_has_permissions',
        'roles',
        'permissions',
    ];

    /**
     * Tables holding user data — only truncated when --keep-users is NOT set.
     */
    private const USER_TABLES = [
        'users',
    ];

    public function handle(): int
    {
        $this->warn('╔══════════════════════════════════════════════════════════╗');
        $this->warn('║  DATABASE CLEANUP — Hapus semua data dummy              ║');
        $this->warn('║  Pastikan database sudah di-backup sebelum lanjut!      ║');
        $this->warn('╚══════════════════════════════════════════════════════════╝');

        if (! $this->option('force')) {
            $env = app()->environment();

            if ($env === 'production') {
                $confirm = $this->ask(
                    '⚠️  Environment PRODUCTION! Ketik "YA" untuk melanjutkan'
                );
                if ($confirm !== 'YA') {
                    $this->info('Dibatalkan.');
                    return self::SUCCESS;
                }
            } elseif (! $this->confirm('Lanjutkan hapus semua data dummy?', false)) {
                $this->info('Dibatalkan.');
                return self::SUCCESS;
            }
        }

        // ── Truncate data tables ──────────────────────────────
        $this->info("\n📦 Menghapus data transaksi & master...");
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (self::DATA_TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
                $this->line("  ✓ {$table}");
            }
        }

        // ── Truncate users (unless --keep-users) ──────────────
        if (! $this->option('keep-users')) {
            $this->info("\n👤 Menghapus users...");
            foreach (self::USER_TABLES as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                    $this->line("  ✓ {$table}");
                }
            }
        } else {
            $this->info("\n👤 Users dipertahankan (--keep-users)");
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ── Truncate & re-seed config tables ──────────────────
        $this->info("\n⚙️  Me-reset konfigurasi sistem...");
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (self::CONFIG_TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ── Run essential seeders ─────────────────────────────
        $this->call('db:seed', ['--class' => 'WmsRoleSeeder']);
        $this->call('db:seed', ['--class' => 'MenuSeeder']);
        $this->call('db:seed', ['--class' => 'RoleAndMenuSeeder']);

        $this->info("\n✅ Selesai! Database bersih, siap untuk data production.");
        $this->line('   Yang tersisa:');
        $this->line('   • 6 delivery slots (C1–C6)');
        $this->line('   • Menu sidebar & role management');
        $this->line('   • 1 Super Admin user (admin@example.com / password)');
        if ($this->option('keep-users')) {
            $this->line('   • Users lama tetap dipertahankan');
        }

        return self::SUCCESS;
    }
}
