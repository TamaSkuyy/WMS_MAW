import React, { useRef, useState } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, router } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';
import EmptyState from '../../../Tailadmin/components/common/EmptyState';
import Pagination from '../../../Tailadmin/components/common/Pagination';

interface PreviewRow {
  part_number: string;
  product_name: string;
  rack_code: string;
  sap_qty: number;
  actual_qty: number;
  diff: number;
  status: string; // same | diff | new | not_found | invalid
  message: string;
  skip?: boolean;
}

interface PreviewData {
  rows: PreviewRow[];
  summary: { total: number; diff: number; same: number; new: number; skipped: number };
}

function extractError(text: string, status: number): string {
  try {
    const data = JSON.parse(text);
    if (data.message) return data.message;
    if (data.error) return data.error;
    if (data.errors) {
      const first = Object.values(data.errors).flat()[0];
      if (first) return String(first);
    }
  } catch {}
  const stripped = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
  if (stripped.length > 0 && stripped.length < 500) return stripped;
  return `Server error (HTTP ${status})`;
}

function getCsrfToken(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
}

export default function StockOpnameIndex({ opnames, zones, racks }: any) {
  const [zone, setZone] = useState('');
  const [rackId, setRackId] = useState('');
  const [uploading, setUploading] = useState(false);
  const [preview, setPreview] = useState<PreviewData | null>(null);
  const [previewError, setPreviewError] = useState('');
  const [applying, setApplying] = useState(false);
  const [applyResult, setApplyResult] = useState('');
  const [detail, setDetail] = useState<null | { opname: any; items: any[] }>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);

  const dlParams = () => {
    const p: Record<string, string> = {};
    if (zone) p.zone = zone;
    if (rackId) p.rack_id = rackId;
    return p;
  };
  const templateUrl = route('stock-opname.template', dlParams());

  const applicableRows = (preview?.rows || []).filter(
    (r) => ['same', 'diff', 'new'].includes(r.status) && !r.skip
  );

  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const f = e.target.files?.[0];
    if (!f) return;
    setUploading(true);
    setPreviewError('');
    setApplyResult('');
    const formData = new FormData();
    formData.append('file', f);
    try {
      const res = await fetch(route('stock-opname.preview'), {
        method: 'POST',
        body: formData,
        headers: {
          'X-CSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      const text = await res.text();
      if (!res.ok) throw new Error(extractError(text, res.status));
      setPreview(JSON.parse(text));
    } catch (err: any) {
      setPreview(null);
      setPreviewError(err.message || 'Gagal membaca file.');
    } finally {
      setUploading(false);
      if (fileRef.current) fileRef.current.value = '';
    }
  };

  const handleApply = async () => {
    if (applicableRows.length === 0 || applying) return;
    if (!confirm(`Terapkan stock opname? ${applicableRows.length} baris akan diproses, stok disesuaikan dengan hasil hitung fisik.`)) return;
    setApplying(true);
    setApplyResult('');
    try {
      const payload = {
        rows: applicableRows.map((r) => ({
          part_number: r.part_number,
          rack_code: r.rack_code === 'RELAY' ? '' : r.rack_code,
          actual_qty: r.actual_qty,
        })),
      };
      const res = await fetch(route('stock-opname.apply'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
      });
      const text = await res.text();
      const data = text ? JSON.parse(text) : {};
      if (!res.ok) throw new Error(extractError(text, res.status));
      setPreview(null);
      setApplyResult(`✅ Opname ${data.code} diterapkan — ${data.applied} baris diproses, ${data.diff_items} selisih, ${data.new_items} barang baru.`);
      router.reload({ only: ['opnames'] });
    } catch (err: any) {
      setPreviewError(err.message || 'Gagal menerapkan opname.');
    } finally {
      setApplying(false);
    }
  };

  const openDetail = async (id: number) => {
    setDetailLoading(true);
    setDetail(null);
    try {
      const res = await fetch(route('stock-opname.items', id), {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.message || 'Gagal memuat detail.');
      setDetail(data);
    } catch (err: any) {
      alert(err.message || 'Gagal memuat detail.');
    } finally {
      setDetailLoading(false);
    }
  };

  const statusBadge = (status: string, diff: number) => {
    switch (status) {
      case 'diff': return diff > 0
        ? { label: `+${diff}`, cls: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' }
        : { label: `${diff}`, cls: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' };
      case 'new': return { label: 'Baru', cls: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' };
      case 'same': return { label: 'Sama', cls: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' };
      default: return { label: 'Lewati', cls: 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' };
    }
  };

  const inputCls =
    'w-full h-11 px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500/50';

  return (
    <>
      <Head title="Stock Opname" />
      <PageBreadcrumb pageTitle="Stock Opname" />

      {/* ── Alur & Unduh Template ─────────────────────────────── */}
      <ComponentCard title="Stock Opname" desc="Cocokkan stok sistem dengan hitung fisik di gudang">
        <ol className="mb-4 list-decimal list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">
          <li>Unduh template (terisi stok sistem saat ini — kolom <b>SAP</b> = qty sistem).</li>
          <li>Hitung fisik di rak, tulis hasilnya di kolom <b className="text-amber-600">Qty Opname</b>.</li>
          <li>Upload file kembali → tinjau selisih → terapkan (stok otomatis disesuaikan & tercatat di riwayat).</li>
        </ol>

        <div className="flex flex-wrap items-end gap-3">
          <div className="w-full sm:w-44">
            <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Zona (opsional)</label>
            <SearchableSelect
              options={[{ value: '', label: 'Semua Zona' }, ...(zones || []).map((z: string) => ({ value: z, label: z }))]}
              value={zone}
              onChange={(v) => { setZone(v as string); setRackId(''); }}
            />
          </div>
          <div className="w-full sm:w-44">
            <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Rak (opsional)</label>
            <SearchableSelect
              options={[{ value: '', label: 'Semua Rak' }, ...(racks || []).map((r: any) => ({ value: r.id, label: `${r.code} (${r.zone})` }))]}
              value={rackId}
              onChange={(v) => setRackId(v as string)}
            />
          </div>
          <a href={templateUrl} download>
            <Button variant="outline">⬇️ Unduh Template</Button>
          </a>
          <Button onClick={() => fileRef.current?.click()} disabled={uploading}>
            {uploading ? 'Membaca file...' : '📤 Upload Hasil Opname'}
          </Button>
          <input ref={fileRef} type="file" accept=".xlsx,.xls,.csv" onChange={handleFileChange} className="hidden" />
        </div>

        {applyResult && (
          <div className="mt-4 px-3 py-2.5 text-sm text-green-700 bg-green-50 dark:bg-green-900/20 rounded-lg">
            {applyResult}
          </div>
        )}
        {previewError && (
          <div className="mt-4 px-3 py-2.5 text-sm text-red-700 bg-red-50 dark:bg-red-900/20 rounded-lg">
            ⚠️ {previewError}
          </div>
        )}

        {/* ── Preview hasil opname ─────────────────────────────── */}
        {preview && (
          <div className="mt-5 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
            <div className="flex flex-wrap items-center gap-2 mb-3">
              <h4 className="text-sm font-semibold text-gray-800 dark:text-white/90 mr-auto">Preview Selisih</h4>
              <span className="px-2 py-1 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">Total {preview.summary.total}</span>
              {preview.summary.diff > 0 && <span className="px-2 py-1 text-xs rounded-full bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300">{preview.summary.diff} selisih</span>}
              {preview.summary.new > 0 && <span className="px-2 py-1 text-xs rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">{preview.summary.new} barang baru</span>}
              {preview.summary.same > 0 && <span className="px-2 py-1 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{preview.summary.same} sama</span>}
              {preview.summary.skipped > 0 && <span className="px-2 py-1 text-xs rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">{preview.summary.skipped} dilewati</span>}
            </div>

            <div className="overflow-x-auto max-h-96 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg">
              <table className="min-w-full">
                <thead className="bg-[#F8F9FC] dark:bg-gray-800 sticky top-0 border-b border-[#E9ECEF] dark:border-gray-700">
                  <tr>
                    <th className="px-3 py-2 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Part No</th>
                    <th className="px-3 py-2 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Part Name</th>
                    <th className="px-3 py-2 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">RAK</th>
                    <th className="px-3 py-2 text-center text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">SAP</th>
                    <th className="px-3 py-2 text-center text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Qty Opname</th>
                    <th className="px-3 py-2 text-center text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Selisih</th>
                    <th className="px-3 py-2 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Keterangan</th>
                  </tr>
                </thead>
                <tbody>
                  {preview.rows.map((r, i) => {
                    const badge = statusBadge(r.status, r.diff);
                    const skipped = r.skip || r.status === 'not_found' || r.status === 'invalid';
                    return (
                      <tr key={i} className={`border-b border-[#F1F3F5] dark:border-gray-700 ${skipped ? 'opacity-60' : ''}`}>
                        <td className="px-3 py-2 text-xs font-mono text-[#1A1D23] dark:text-gray-200 whitespace-nowrap">{r.part_number}</td>
                        <td className="px-3 py-2 text-xs text-[#1A1D23] dark:text-gray-200">{r.product_name}</td>
                        <td className="px-3 py-2 text-xs font-mono text-[#1A1D23] dark:text-gray-200">{r.rack_code}</td>
                        <td className="px-3 py-2 text-xs text-center tabular-nums text-[#6C757D]">{r.sap_qty}</td>
                        <td className="px-3 py-2 text-xs text-center tabular-nums font-medium text-[#1A1D23] dark:text-gray-200">{r.actual_qty}</td>
                        <td className="px-3 py-2 text-center">
                          <span className={`inline-block px-1.5 py-0.5 text-xs font-semibold rounded ${badge.cls}`}>{badge.label}</span>
                        </td>
                        <td className="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">{r.message || '-'}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>

            <div className="flex justify-end gap-2 mt-4">
              <Button variant="outline" onClick={() => setPreview(null)} disabled={applying}>Batalkan</Button>
              <Button onClick={handleApply} disabled={applicableRows.length === 0 || applying}>
                {applying ? 'Menerapkan...' : `Terapkan Selisih (${applicableRows.length})`}
              </Button>
            </div>
          </div>
        )}
      </ComponentCard>

      {/* ── Riwayat ────────────────────────────────────────────── */}
      <ComponentCard title="Riwayat Stock Opname">
        {opnames.data.length === 0 ? (
          <EmptyState icon="📋" title="Belum ada opname" message="Unduh template, lakukan hitung fisik, lalu upload hasilnya." />
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full">
              <thead className="bg-[#F8F9FC] border-b border-[#E9ECEF]">
                <tr>
                  <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Kode</th>
                  <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Tanggal</th>
                  <th className="px-4 py-3 text-center text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Baris</th>
                  <th className="px-4 py-3 text-center text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Selisih</th>
                  <th className="px-4 py-3 text-center text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Baru</th>
                  <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Petugas</th>
                  <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider w-20">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {opnames.data.map((o: any) => (
                  <tr key={o.id} className="border-b border-[#F1F3F5] hover:bg-[#F8F9FC] transition-all duration-150">
                    <td className="px-4 py-3 text-sm font-mono text-[#1A1D23]">{o.code}</td>
                    <td className="px-4 py-3 text-sm text-[#6C757D]">{o.opname_date}</td>
                    <td className="px-4 py-3 text-center text-sm tabular-nums text-[#1A1D23]">{o.total_items}</td>
                    <td className="px-4 py-3 text-center">
                      {o.diff_items > 0 ? (
                        <span className="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">{o.diff_items}</span>
                      ) : (
                        <span className="inline-block px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-300">0</span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-center text-sm tabular-nums text-[#1A1D23]">{o.new_items}</td>
                    <td className="px-4 py-3 text-sm text-[#6C757D]">{o.creator?.name || '-'}</td>
                    <td className="px-4 py-3">
                      <Button variant="outline" size="sm" onClick={() => openDetail(o.id)}>Detail</Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {opnames.total > opnames.per_page && (
          <Pagination
            prevUrl={opnames.prev_page_url}
            nextUrl={opnames.next_page_url}
            currentPage={opnames.current_page}
            lastPage={opnames.last_page}
            from={opnames.from}
            to={opnames.to}
            total={opnames.total}
          />
        )}
      </ComponentCard>

      {/* ── Modal detail riwayat ──────────────────────────────── */}
      {detail && (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-3xl max-h-[85vh] flex flex-col">
            <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <div>
                <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Detail {detail.opname.code}</h2>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  {detail.opname.opname_date} · oleh {detail.opname.creator} · {detail.opname.total_items} baris, {detail.opname.diff_items} selisih, {detail.opname.new_items} barang baru
                </p>
              </div>
              <button onClick={() => setDetail(null)} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">✕</button>
            </div>
            <div className="px-6 py-4 overflow-y-auto flex-1">
              <div className="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                <table className="min-w-full">
                  <thead className="bg-[#F8F9FC] dark:bg-gray-800 border-b border-[#E9ECEF] dark:border-gray-700">
                    <tr>
                      <th className="px-3 py-2 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Part No</th>
                      <th className="px-3 py-2 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Part Name</th>
                      <th className="px-3 py-2 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">RAK</th>
                      <th className="px-3 py-2 text-center text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">SAP</th>
                      <th className="px-3 py-2 text-center text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Opname</th>
                      <th className="px-3 py-2 text-center text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Selisih</th>
                    </tr>
                  </thead>
                  <tbody>
                    {detail.items.map((it: any) => {
                      const diff = it.diff;
                      return (
                        <tr key={it.id} className="border-b border-[#F1F3F5] dark:border-gray-700">
                          <td className="px-3 py-2 text-xs font-mono text-[#1A1D23] dark:text-gray-200">{it.part_number}</td>
                          <td className="px-3 py-2 text-xs text-[#1A1D23] dark:text-gray-200">{it.product_name}</td>
                          <td className="px-3 py-2 text-xs font-mono text-[#1A1D23] dark:text-gray-200">{it.rack_code}</td>
                          <td className="px-3 py-2 text-xs text-center tabular-nums text-[#6C757D]">{it.sap_qty}</td>
                          <td className="px-3 py-2 text-xs text-center tabular-nums font-medium text-[#1A1D23] dark:text-gray-200">{it.actual_qty}</td>
                          <td className="px-3 py-2 text-center">
                            {diff === 0 ? (
                              <span className="text-xs text-gray-400">0</span>
                            ) : diff > 0 ? (
                              <span className="text-xs font-semibold text-green-600">+{diff}</span>
                            ) : (
                              <span className="text-xs font-semibold text-red-600">{diff}</span>
                            )}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
              {detail.items.length === 0 && <p className="text-sm text-gray-500 py-6 text-center">Tidak ada item.</p>}
            </div>
            <div className="flex justify-end px-6 py-3 border-t border-gray-200 dark:border-gray-700">
              <Button variant="outline" onClick={() => setDetail(null)}>Tutup</Button>
            </div>
          </div>
        </div>
      )}
      {detailLoading && (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white dark:bg-gray-900 rounded-xl px-6 py-4 text-sm text-gray-600">Memuat detail...</div>
        </div>
      )}
    </>
  );
}

StockOpnameIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
