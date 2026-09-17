<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Halaman Pengaturan (superadmin) — switch on/off fitur.
 *
 * Definisi fitur: config/features.php. Nilai efektif disimpan di tabel
 * `settings` (key `feature.<nama>`) dan dibagikan ke seluruh halaman sebagai
 * shared prop Inertia `features`.
 */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private const FLAG_KEY = 'feature.shopping_vehicle_model_column';

    private function superadmin(): User
    {
        Role::findOrCreate('superadmin');
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $user->givePermissionTo(Permission::findOrCreate('manage settings'));

        return $user;
    }

    public function test_superadmin_can_open_settings_page(): void
    {
        $response = $this->actingAs($this->superadmin())->get(route('settings.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Index')
            ->has('definitions', 1)
            ->where('definitions.0.key', 'shopping_vehicle_model_column')
            ->where('definitions.0.enabled', true) // default config = ON
            ->where('definitions.0.default', true));
    }

    public function test_non_superadmin_cannot_open_settings_page(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('manage settings'));

        // Punya permission tapi bukan superadmin → route group menolak.
        $this->actingAs($user)->get(route('settings.index'))->assertForbidden();
    }

    public function test_feature_flag_defaults_to_on_and_is_shared_to_pages(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('create shoppings'));

        $this->actingAs($user)->get(route('shoppings.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('features.shopping_vehicle_model_column', true));
    }

    public function test_toggling_feature_off_persists_and_hides_it_everywhere(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)
            ->post(route('settings.update'), ['features' => ['shopping_vehicle_model_column' => false]])
            ->assertRedirect();

        $this->assertDatabaseHas('settings', ['key' => self::FLAG_KEY, 'value' => '0']);
        $this->assertFalse(\App\Support\Features::enabled('shopping_vehicle_model_column'));

        // Halaman lain menerima flag terbaru lewat shared prop.
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('create shoppings'));

        $this->actingAs($user)->get(route('shoppings.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('features.shopping_vehicle_model_column', false));
    }

    public function test_toggling_feature_on_again_uses_single_setting_row(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->post(route('settings.update'), [
            'features' => ['shopping_vehicle_model_column' => false],
        ]);
        $this->actingAs($admin)->post(route('settings.update'), [
            'features' => ['shopping_vehicle_model_column' => true],
        ]);

        $this->assertSame(1, Setting::where('key', self::FLAG_KEY)->count());
        $this->assertSame('1', Setting::where('key', self::FLAG_KEY)->value('value'));
        $this->assertTrue(\App\Support\Features::enabled('shopping_vehicle_model_column'));
    }

    public function test_unknown_feature_keys_are_ignored(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->post(route('settings.update'), [
            'features' => [
                'shopping_vehicle_model_column' => true,
                'fitur_yang_tidak_ada' => true,
            ],
        ])->assertRedirect();

        $this->assertDatabaseMissing('settings', ['key' => 'feature.fitur_yang_tidak_ada']);
    }

    public function test_settings_menu_is_registered_under_setup(): void
    {
        $menu = \Illuminate\Support\Facades\DB::table('menus')->where('name', 'Pengaturan')->first();

        $this->assertNotNull($menu, 'Menu Pengaturan harus ada di bawah Setup.');
        $this->assertSame('/settings', $menu->path);
        $this->assertSame('manage settings', $menu->permission_name);

        $parent = \Illuminate\Support\Facades\DB::table('menus')->where('id', $menu->parent_id)->first();
        $this->assertSame('Setup', $parent?->name);
    }

    public function test_update_requires_superadmin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('settings.update'), [
            'features' => ['shopping_vehicle_model_column' => false],
        ])->assertForbidden();

        $this->assertDatabaseMissing('settings', ['key' => self::FLAG_KEY]);
    }
}
