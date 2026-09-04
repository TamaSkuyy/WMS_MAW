<?php

namespace App\Http\Controllers;

use App\Models\DataReset;
use App\Services\DataReset\DataResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Pemutihan Data via web — HANYA superadmin (route group superadmin + perm).
 * Keamanan: frasa konfirmasi + password ulang + backup wajib + throttle +
 * log audit (data_resets: user/IP/UA/jumlah per tabel/file backup).
 */
class DataResetController extends Controller
{
    private const CONFIRM_PHRASE = 'PEMUTIHAN';

    public function index()
    {
        $counts = (new DataResetService())->counts(includeSessions: false);

        return Inertia::render('DataReset/Index', [
            'counts' => $counts,
            'history' => DataReset::with('user:id,name')->latest('id')->limit(20)->get(),
        ]);
    }

    /** Pra-tinjau — jumlah record yang akan dihapus (tanpa mengubah apa pun). */
    public function preview(Request $request)
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);
        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;

        if ($from !== null || $to !== null) {
            return response()->json([
                'mode' => 'range',
                'from' => $from,
                'to' => $to,
                'counts' => (new DataResetService())->purgeCounts($from, $to),
            ]);
        }

        return response()->json([
            'mode' => 'full',
            'from' => null,
            'to' => null,
            'counts' => (new DataResetService())->counts(includeSessions: false),
        ]);
    }

    public function execute(Request $request)
    {
        $validated = $request->validate([
            'confirm_phrase' => 'required|string',
            'password' => 'required|string',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;

        // L1: frasa konfirmasi
        if (strtoupper(trim($validated['confirm_phrase'])) !== self::CONFIRM_PHRASE) {
            return response()->json(['message' => 'Frasa konfirmasi salah — pemutihan dibatalkan.'], 422);
        }

        // L2: password ulang (re-autentikasi)
        $user = $request->user();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Password salah — pemutihan dibatalkan.'], 422);
        }

        // L3: backup WAJIB sebelum eksekusi
        try {
            $exitCode = Artisan::call('backup:run', [
                '--only-db' => true,
                '--disable-notifications' => true,
            ]);
            if ($exitCode !== 0) {
                return response()->json(['message' => 'Backup database gagal — pemutihan dibatalkan.'], 500);
            }
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Backup database gagal — pemutihan dibatalkan.'], 500);
        }

        $backupFile = $this->latestBackupFile();
        $service = new DataResetService();

        if ($from !== null || $to !== null) {
            // Mode riwayat rentang tanggal — stok TIDAK disentuh
            $stats = $service->purge($from, $to);
            $range = trim(($from ?? '').' — '.($to ?? ''), ' —');
            $message = "Pemutihan riwayat {$range} selesai — stok tidak diubah.";
        } else {
            // Mode reset total — transaksi + antrian/cache + stok 0
            $countsBefore = $service->counts(includeSessions: false);
            $result = $service->execute(includeSessions: false, zeroStock: true);
            $stats = ['tables_cleared' => count($result['tables']), 'stock_rows' => $result['stock_rows']];

            $message = 'Pemutihan total selesai — stok direset 0.';
        }

        DataReset::create([
            'user_id' => $user->id,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'counts' => $from !== null || $to !== null ? $stats : $countsBefore,
            'backup_file' => $backupFile,
            'created_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'mode' => $from !== null || $to !== null ? 'range' : 'full',
            'from' => $from,
            'to' => $to,
            'backup_file' => $backupFile,
            'detail' => $stats,
            'message' => $message,
        ]);
    }

    /** Cari file backup terbaru di disk tujuan (folder app-name/tanggal). */
    private function latestBackupFile(): ?string
    {
        try {
            $diskName = (string) (config('backup.backup.destination.disks')[0] ?? 'local');
            $disk = Storage::disk($diskName);
            $base = (string) config('backup.backup.name');

            $dirs = $disk->directories($base);
            rsort($dirs); // folder tanggal terbaru dulu

            foreach ($dirs as $dir) {
                $files = $disk->files($dir);
                if (empty($files)) {
                    continue;
                }
                sort($files);

                return (string) end($files);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }
}
