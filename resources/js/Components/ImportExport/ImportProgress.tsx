import React, { useEffect, useState } from 'react';

interface ImportProgressProps {
  importLogId: number;
  /** Dipanggil SATU KALI saat import mencapai status akhir (completed/failed).
   *  JANGAN dipakai untuk reload halaman otomatis — hasil import harus
   *  ditampilkan dulu, reload hanya saat user menutup modal. */
  onFinished?: () => void;
}

interface ImportError {
  row: number;
  field: string;
  message: string;
}

interface ImportStatus {
  status: string;
  total_rows: number;
  processed_rows: number;
  skipped_rows: number;
  errors: ImportError[] | null;
}

const STATUS_LABELS: Record<string, string> = {
  pending: 'Menunggu antrian (pending)...',
  processing: 'Importing...',
  completed: 'Import selesai',
  failed: 'Import gagal',
};

export default function ImportProgress({ importLogId, onFinished }: ImportProgressProps) {
  const [status, setStatus] = useState<ImportStatus | null>(null);
  const [finished, setFinished] = useState(false);

  useEffect(() => {
    const poll = setInterval(async () => {
      try {
        const res = await fetch(route('import.status', importLogId));
        const data = await res.json();
        setStatus(data);

        if (data.status === 'completed' || data.status === 'failed') {
          clearInterval(poll);
          setFinished(true);
          onFinished?.();
        }
      } catch {
        // retry on next poll
      }
    }, 3000);

    return () => clearInterval(poll);
  }, [importLogId]);

  if (!status) return null;

  const errors = status.errors ?? [];
  const pct = status.total_rows > 0
    ? Math.round((status.processed_rows / status.total_rows) * 100)
    : 0;

  const statusLabel = STATUS_LABELS[status.status] ?? status.status;
  const isCompleted = status.status === 'completed';
  const isFailed = status.status === 'failed';

  return (
    <div className="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
      <div className="flex justify-between items-center mb-2">
        <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
          {statusLabel}
        </span>
        <span className="text-xs text-gray-500">
          {status.processed_rows} / {status.total_rows} rows
        </span>
      </div>
      <div className="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
        <div
          className={`h-full rounded-full transition-all duration-500 ${
            isFailed ? 'bg-red-500' : isCompleted ? 'bg-green-500' : 'bg-brand-500'
          }`}
          style={{ width: `${isCompleted ? 100 : pct}%` }}
        />
      </div>

      {status.status === 'pending' && (
        <p className="mt-2 text-xs text-amber-600 dark:text-amber-400">
          Import belum diproses — pastikan queue worker berjalan (php artisan queue:work).
        </p>
      )}

      {/* ── Hasil akhir: hanya muncul SETELAH import selesai ─────────────────── */}
      {finished && isCompleted && (
        <div className="mt-3">
          <p className="text-sm text-gray-700 dark:text-gray-300">
            {status.processed_rows} baris berhasil diimport
            {status.skipped_rows > 0 && `, ${status.skipped_rows} duplikat dilewati`}
            {errors.length > 0 && `, ${errors.length} error`}.
          </p>

          {isCompleted && status.processed_rows === 0 && status.skipped_rows > 0 && (
            <p className="mt-2 text-xs text-amber-600 dark:text-amber-400">
              Semua baris sudah pernah diimport sebelumnya (duplikat) — data sudah ada di sistem. Gunakan file/frame baru jika ingin menambah data.
            </p>
          )}

          {errors.length > 0 && (
            <div className="mt-3">
              <p className="text-xs font-medium text-red-500 mb-1">Daftar error:</p>
              <ul className="max-h-40 overflow-y-auto space-y-1 text-xs text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 rounded-lg p-2 bg-white dark:bg-gray-900">
                {errors.map((e, i) => (
                  <li key={i} className="flex gap-2">
                    <span className="text-gray-400 shrink-0">
                      {e.row > 0 ? `Baris ${e.row}` : 'Sistem'}
                      {e.field && e.field !== 'system' ? ` (${e.field})` : ''}:
                    </span>
                    <span>{e.message}</span>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}

      {finished && isFailed && (
        <div className="mt-3">
          <p className="text-sm font-medium text-red-600">Import gagal diproses.</p>
          {errors.length > 0 ? (
            <ul className="mt-2 max-h-40 overflow-y-auto space-y-1 text-xs text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 rounded-lg p-2 bg-white dark:bg-gray-900">
              {errors.map((e, i) => (
                <li key={i} className="flex gap-2">
                  <span className="text-gray-400 shrink-0">
                    {e.row > 0 ? `Baris ${e.row}` : 'Sistem'}
                    {e.field && e.field !== 'system' ? ` (${e.field})` : ''}:
                  </span>
                  <span>{e.message}</span>
                </li>
              ))}
            </ul>
          ) : (
            <p className="mt-2 text-xs text-gray-500">
              Job gagal diproses oleh worker tanpa detail tambahan. Pastikan queue worker
              berjalan, lalu cek <code>./deploy-production.sh --check-queue</code> atau{' '}
              <code>storage/logs/laravel.log</code>.
            </p>
          )}
        </div>
      )}

      {finished && (
        <p className="mt-3 text-xs text-gray-400 dark:text-gray-500">
          Klik &quot;Close&quot; untuk menutup dan memuat ulang data terbaru.
        </p>
      )}
    </div>
  );
}
