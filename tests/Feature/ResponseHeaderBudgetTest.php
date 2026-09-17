<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Rack;
use App\Models\ShoppingLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Budget header respons.
 *
 * Laravel (Vite::prefetch + AddLinkHeadersForPreloadedAssets) menambahkan header
 * `Link:` Early Hints untuk SEMUA aset build. Kalau totalnya melebihi buffer
 * header nginx (`proxy_buffer_size`), nginx membalas:
 *
 *   upstream sent too big header while reading response header from upstream → 502
 *
 * Buffer yang dipakai produksi: 32k (docker/nginx/default.conf + nginx host,
 * lihat docs/production-server-setup.md §9.6). Test ini menjaga agar jumlah
 * header tetap jauh di bawah buffer itu — kalau aset/chunk bertambah banyak,
 * test gagal sebelum kejadian di produksi.
 */
class ResponseHeaderBudgetTest extends TestCase
{
    use RefreshDatabase;

    /** Batas aman: buffer nginx 32k, sisakan ruang untuk cookie & header lain. */
    private const MAX_HEADER_BYTES = 24 * 1024;

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    /** Ukuran (byte) semua header respons, format "Nama: nilai\r\n". */
    private function headerBytes($response): int
    {
        $total = 0;

        foreach ($response->headers->all() as $name => $values) {
            foreach ((array) $values as $value) {
                $total += strlen($name) + 2 + strlen($value) + 2;
            }
        }

        return $total;
    }

    public function test_shopping_create_response_headers_fit_nginx_buffer(): void
    {
        $user = $this->userWith(['create shoppings']);
        $location = ShoppingLocation::create(['name' => 'LINE HDR']);
        $rack = Rack::factory()->create();

        // Beberapa produk + stok supaya halaman ini benar-benar berat (kasus nyata).
        Product::factory()->count(20)->create(['is_active' => true])->each(function (Product $product) use ($rack) {
            $product->stocks()->create(['rack_id' => $rack->id, 'quantity' => 5]);
        });

        $response = $this->actingAs($user)->get(route('shoppings.create'));

        $response->assertOk();

        $linkHeaders = $response->headers->all('link');
        $linkBytes = array_sum(array_map(fn ($value) => strlen($value) + 8, $linkHeaders));
        $totalBytes = $this->headerBytes($response);

        // Info untuk diagnosa kalau test gagal.
        fwrite(STDERR, sprintf(
            "\n[header budget] Link: %d header / %d byte | total header: %d byte (%.1f KB) | batas: %d byte\n",
            count($linkHeaders),
            $linkBytes,
            $totalBytes,
            $totalBytes / 1024,
            self::MAX_HEADER_BYTES
        ));

        $this->assertLessThan(
            self::MAX_HEADER_BYTES,
            $totalBytes,
            'Total header respons melebihi budget buffer nginx (32k). Kurangi aset preload/prefetch '
            . 'atau naikkan proxy_buffer_size di nginx (docker + host).'
        );
    }
}
