<?php

namespace App\Http\Controllers;

use App\Support\Features;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Halaman Pengaturan (superadmin) — daftar fitur dengan switch on/off.
 *
 * Route-nya ada di grup superadmin + permission 'manage settings'
 * (lihat routes/web.php), jadi controller ini tidak perlu cek role lagi.
 */
class SettingController extends Controller
{
    public function index()
    {
        $definitions = [];

        foreach (Features::definitions() as $key => $definition) {
            $definitions[] = [
                'key' => $key,
                'label' => $definition['label'] ?? $key,
                'description' => $definition['description'] ?? null,
                'default' => (bool) ($definition['default'] ?? false),
                'enabled' => Features::enabled($key),
            ];
        }

        return Inertia::render('Settings/Index', [
            'definitions' => $definitions,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'features' => 'required|array',
            'features.*' => 'boolean',
        ]);

        $saved = 0;

        foreach ($validated['features'] as $key => $value) {
            // Key tak dikenal diabaikan (jangan simpan sampah dari request manual).
            if (! Features::isKnown($key)) {
                continue;
            }

            Features::set($key, (bool) $value);
            $saved++;
        }

        return back()->with('success', "Pengaturan disimpan ({$saved} fitur diperbarui).");
    }
}
