import React, { useState } from 'react';
import AppLayout from '../../Tailadmin/layout/AppLayout';
import { Head, router } from '@inertiajs/react';
import PageBreadcrumb from '../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../Tailadmin/components/common/ComponentCard';
import Button from '../../Tailadmin/components/ui/button/Button';
import Label from '../../Tailadmin/components/form/Label';

function getCsrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
}

interface PreviewCounts { [table: string]: number }

export default function DataResetIndex({ counts, history }: any) {
  const [phrase, setPhrase] = useState('');
  const [password, setPassword] = useState('');
  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');
  const [preview, setPreview] = useState<{ mode: 'full' | 'range'; from: string | null; to: string | null; counts: PreviewCounts } | null>(null);
  const [loading, setLoading] = useState(false);
  const [executing, setExecuting] = useState(false);
  const [error, setError] = useState('');
  const [result, setResult] = useState('');

  const phraseOk = phrase.trim().toUpperCase() === 'PEMUTIHAN';
  const isDateMode = fromDate.trim() !== '' || toDate.trim() !== '';
  const rangeLabel = (fromDate || '…') + ' — ' + (toDate || '…');
  const rangeInvalid = fromDate && toDate && fromDate > toDate;

  const handlePreview = async () => {
    if (rangeInvalid) { setError('Tanggal "Dari" tidak boleh lebih besar dari "Sampai".'); return; }
    setLoading(true);
    setError('');
    try {
      const res = await fetch(route('data-reset.preview'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ from: fromDate || null, to: toDate || null }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.message || 'Gagal pra-tinjau.');
      setPreview(data);
    } catch (err: any) {
      setError(err.message || 'Gagal pra-tinjau.');
    } finally {
      setLoading(false);
    }
  };

  const handleExecute = async () => {
    if (!phraseOk || !password || executing) return;
    if (rangeInvalid) { setError('Tanggal "Dari" tidak boleh lebih besar dari "Sampai".'); return; }
    const confirmMsg = isDateMode
      ? `⚠️ Riwayat transaksi ${rangeLabel} akan DIHAPUS PERMANEN. Stok TIDAK diubah. Backup otomatis dibuat dahulu. Lanjutkan?`
      : '⚠️ PERHATIAN: Data transaksi akan DIHAPUS PERMANEN dan stok direset 0. Backup otomatis akan dibuat dahulu. Lanjutkan?';
    if (!confirm(confirmMsg)) return;
    setExecuting(true);
    setError('');
    setResult('');
    try {
      const res = await fetch(route('data-reset.execute'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ confirm_phrase: phrase, password, from: fromDate || null, to: toDate || null }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.message || `HTTP ${res.status}`);
      setResult(`✅ ${data.message} Backup: ${data.backup_file || '(tercatat)'}`);
      setPhrase('');
      setPassword('');
      setPreview(null);
      router.reload({ only: ['counts', 'history'] });
    } catch (err: any) {
      setError(err.message || 'Eksekusi gagal.');
    } finally {
      setExecuting(false);
    }
  };

  const inputCls =
    'w-full h-11 px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-400/50';

  return (
    <>
      <Head title="Pemutihan Data" />
      <PageBreadcrumb pageTitle="Pemutihan Data" />

      {/* ── Peringatan & alur ─────────────────────────────── */}
      <div className="mb-4 rounded-xl border border-red-200 dark:border-red-900/40 bg-red-50 dark:bg-red-950/30 p-4">
        <h3 className="text-sm font-semibold text-red-700 dark:text-red-300 mb-1">⚠️ Fitur berbahaya — hanya untuk superadmin</h3>
        <ul className="text-xs text-red-600 dark:text-red-300/80 space-y-0.5 list-disc list-inside">
          <li><b>Mode total (tanpa tanggal):</b> hapus semua transaksi (cycle, shopping, riwayat terima, import log, stock opname, koreksi), antrian & cache, <b>stok direset 0</b>.</li>
          <li><b>Mode tanggal (opsional, 1–2 tanggal):</b> hanya hapus riwayat transaksi tuntas dalam rentang tsb — <b>stok, antrian & cache TIDAK diubah</b>.</li>
          <li><b>Tetap:</b> master data (produk, supplier, rak, lokasi, user, role/menu), activity_log & file import.</li>
          <li>Eksekusi wajib: <b>backup otomatis</b> dahulu + ketik frasa <b>PEMUTIHAN</b> + <b>password Anda</b>.</li>
        </ul>
      </div>

      {result && (
        <div className="mb-4 px-4 py-3 text-sm text-green-700 bg-green-50 dark:bg-green-900/20 rounded-xl">{result}</div>
      )}
      {error && (
        <div className="mb-4 px-4 py-3 text-sm text-red-700 bg-red-50 dark:bg-red-900/20 rounded-xl">⚠️ {error}</div>
      )}

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
        {/* ── Pra-tinjau ─────────────────────────────────────── */}
        <ComponentCard title="1. Pra-tinjau" desc="Lihat jumlah record yang akan dihapus">
          <Button type="button" variant="outline" onClick={handlePreview} disabled={loading}>
            {loading ? 'Memuat...' : '🔍 Ambil Data Terkini'}
          </Button>

          {preview && (
            <div className="mt-4">
              <p className="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">
                {preview.mode === 'range'
                  ? `Akan dihapus (riwayat ${rangeLabel}) — stok TIDAK diubah:`
                  : 'Jumlah record yang akan dihapus (reset total):'}
              </p>
              <div className="max-h-72 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                <table className="min-w-full">
                  <tbody>
                    {Object.entries(preview.counts || {}).map(([table, n]) => (
                      <tr key={table} className="border-b border-[#F1F3F5] dark:border-gray-700">
                        <td className="px-3 py-1.5 text-xs font-mono text-gray-600 dark:text-gray-300">{table}</td>
                        <td className="px-3 py-1.5 text-right">
                          <span className={`text-xs font-semibold tabular-nums ${n > 0 ? 'text-red-600' : 'text-gray-400'}`}>{n}</span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              {Object.values(preview.counts || {}).every((n: number) => n === 0) && (
                <p className="mt-2 text-xs text-gray-400">Tidak ada data yang cocok untuk dihapus.</p>
              )}
            </div>
          )}
        </ComponentCard>

        {/* ── Eksekusi ────────────────────────────────────────── */}
        <ComponentCard title="2. Eksekusi Pemutihan" desc="Backup otomatis + frasa + password">
          <div className="space-y-4">
            <div>
              <Label>Hapus riwayat rentang tanggal (opsional)</Label>
              <div className="flex flex-wrap items-end gap-2">
                <div className="flex-1 min-w-[130px]">
                  <label className="block text-[11px] text-gray-500 dark:text-gray-400 mb-1">Dari</label>
                  <input type="date" value={fromDate} onChange={(e) => setFromDate(e.target.value)} className={inputCls} />
                </div>
                <div className="flex-1 min-w-[130px]">
                  <label className="block text-[11px] text-gray-500 dark:text-gray-400 mb-1">Sampai</label>
                  <input type="date" value={toDate} onChange={(e) => setToDate(e.target.value)} className={inputCls} />
                </div>
              </div>
              {rangeInvalid && <p className="mt-1 text-xs text-red-500">Tanggal "Dari" tidak boleh lebih besar dari "Sampai".</p>}
              <p className="mt-1 text-[11px] text-gray-400">
                <b>Kosong</b> = reset total (stok direset 0). <b>Isi 1 atau 2 tanggal</b> = hanya hapus riwayat transaksi tuntas dalam rentang tsb — <b className="text-amber-600">stok & antrian TIDAK diubah</b>.
              </p>
            </div>
            <div>
              <Label>Ketik frasa konfirmasi: PEMUTIHAN</Label>
              <input
                type="text"
                value={phrase}
                onChange={(e) => setPhrase(e.target.value)}
                placeholder="PEMUTIHAN"
                className={inputCls}
                autoComplete="off"
              />
              {phrase !== '' && !phraseOk && (
                <p className="mt-1 text-xs text-red-500">Frasa harus persis: PEMUTIHAN</p>
              )}
            </div>
            <div>
              <Label>Password Anda (re-autentikasi)</Label>
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Masukkan password login"
                className={inputCls}
                autoComplete="current-password"
              />
            </div>
            <Button
              type="button"
              onClick={handleExecute}
              disabled={!phraseOk || !password || executing}
              className={phraseOk && password ? '!bg-red-600 hover:!bg-red-700' : ''}
            >
              {executing ? 'Memproses (backup + reset)...' : isDateMode ? '🗑️ Hapus Riwayat Rentang Tanggal' : '🗑️ Eksekusi Pemutihan Total'}
            </Button>
            <p className="text-[11px] text-gray-400">
              Batas percobaan: 3× per menit. Semua tindakan tercatat (user, IP, jumlah per tabel, file backup).
            </p>
          </div>
        </ComponentCard>
      </div>

      {/* ── Riwayat ──────────────────────────────────────────── */}
      <div className="mt-6">
        <ComponentCard title={`Riwayat Pemutihan (${history.length})`} desc="Jejak audit setiap eksekusi">
          {history.length === 0 ? (
            <p className="text-sm text-gray-400 py-4 text-center">Belum ada pemutihan tercatat.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead className="bg-[#F8F9FC] dark:bg-gray-800">
                  <tr>
                    <th className="px-3 py-2 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Waktu</th>
                    <th className="px-3 py-2 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Oleh</th>
                    <th className="px-3 py-2 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">IP</th>
                    <th className="px-3 py-2 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Jumlah terhapus</th>
                    <th className="px-3 py-2 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Backup</th>
                  </tr>
                </thead>
                <tbody>
                  {history.map((h: any) => {
                    const detail = Object.entries(h.counts || {})
                      .filter(([, n]) => (n as number) > 0)
                      .map(([t, n]) => `${t}:${n}`)
                      .join(', ');
                    return (
                      <tr key={h.id} className="border-b border-[#F1F3F5] dark:border-gray-700">
                        <td className="px-3 py-2 text-xs text-gray-600 dark:text-gray-300 whitespace-nowrap">
                          {new Date(h.created_at).toLocaleString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                        </td>
                        <td className="px-3 py-2 text-xs text-gray-800 dark:text-gray-200">{h.user?.name || '-'}</td>
                        <td className="px-3 py-2 text-xs font-mono text-gray-500">{h.ip || '-'}</td>
                        <td className="px-3 py-2 text-[11px] text-gray-500 break-all">{detail || '(kosong)'}</td>
                        <td className="px-3 py-2 text-[11px] font-mono text-gray-500 break-all">{h.backup_file || '-'}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </ComponentCard>
      </div>
    </>
  );
}

DataResetIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
