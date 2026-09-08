import React, { useEffect, useRef, useState } from 'react';
import Button from '../../Tailadmin/components/ui/button/Button';

interface PlannedCycle {
    file_cycle: number;
    shift: string | null;
    parts: number;
    qty: number;
}

interface SupplierGroup {
    supplier_code: string;
    supplier_name: string;
    cycles: PlannedCycle[];
}

interface SkippedPart {
    part_number: string;
    part_name: string;
    supplier_code: string;
    quantity: number;
    reason: string;
    message: string;
}

interface ApplyRow {
    supplier_code: string;
    cycle: number;
    part_number: string;
    quantity: number;
}

interface PreviewData {
    date: string;
    summary: {
        zero_qty_rows: number;
        suppliers: number;
        cycles: number;
        items: number;
        qty_total: number;
        skipped: number;
        previous_drafts?: number;
        previous_active?: number;
        suppliers_with_previous?: string[];
        identical_previous?: boolean;
    };
    suppliers: SupplierGroup[];
    skipped: SkippedPart[];
    rows: ApplyRow[];
}

interface ApplyResult {
    ok: boolean;
    unchanged?: boolean;
    replaced_cycles?: number;
    date: string;
    suppliers: number;
    cycles_created: number;
    items_created: number;
    qty_total: number;
    message?: string;
}

interface DataOrderImportModalProps {
    isOpen: boolean;
    onClose: () => void;
    onComplete: () => void;
}

function getCsrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
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

const shiftBadge = (shift: string | null) => {
    if (!shift) return null;
    if (shift === 'D') return { label: 'Siang', cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' };
    if (shift === 'N') return { label: 'Malam', cls: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' };
    return { label: shift, cls: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' };
};

export default function DataOrderImportModal({ isOpen, onClose, onComplete }: DataOrderImportModalProps) {
    const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10));
    const fileRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [step, setStep] = useState<'upload' | 'preview' | 'done'>('upload');
    const [preview, setPreview] = useState<PreviewData | null>(null);
    const [mode, setMode] = useState<'append' | 'replace'>('append');
    const [result, setResult] = useState<ApplyResult | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        if (isOpen) {
            setStep('upload');
            setPreview(null);
            setResult(null);
            setMode('append');
            setError('');
            setFile(null);
            if (fileRef.current) fileRef.current.value = '';
        }
    }, [isOpen]);

    if (!isOpen) return null;

    const pickFile = (e: React.ChangeEvent<HTMLInputElement>) => {
        const f = e.target.files?.[0];
        if (!f) return;
        setFile(f);
        setError('');
    };

    const handlePreview = async () => {
        if (!file || !date) return;
        setBusy(true);
        setError('');
        try {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('delivery_date', date);
            const res = await fetch(route('cycles.data-order.preview'), {
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
            const data = JSON.parse(text) as PreviewData;
            setPreview(data);
            // File ter-update utk tanggal yang sama → default mode "ganti" agar tidak menumpuk.
            setMode((data.summary.previous_drafts ?? 0) > 0 ? 'replace' : 'append');
            setStep('preview');
        } catch (err: any) {
            setError(err.message || 'Gagal membaca file.');
        } finally {
            setBusy(false);
        }
    };

    const handleApply = async () => {
        if (!preview || preview.rows.length === 0 || busy) return;
        const n = preview.summary.cycles;
        const replaceDesc = mode === 'replace'
            ? ' Cycle DRAFT lama tanggal tsb akan dihapus lalu dibuat ulang (cycle yang sudah diterima tidak disentuh).'
            : '';
        if (!confirm(`Buat ${n} cycle draft untuk ${preview.summary.suppliers} supplier?${replaceDesc}`)) return;
        setBusy(true);
        setError('');
        try {
            const res = await fetch(route('cycles.data-order.apply'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    delivery_date: preview.date,
                    mode,
                    rows: preview.rows,
                }),
            });
            const text = await res.text();
            const data = text ? JSON.parse(text) : {};
            if (!res.ok) throw new Error(extractError(text, res.status));
            setResult(data);
            setStep('done');
        } catch (err: any) {
            setError(err.message || 'Gagal membuat cycle.');
        } finally {
            setBusy(false);
        }
    };

    const handleDone = () => {
        onComplete();
        onClose();
    };

    const inputCls =
        'w-full h-11 px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500/50';

    const chipCls = 'px-2.5 py-1 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300';

    return (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 p-4">
            <div className="bg-white dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-3xl max-h-[90vh] flex flex-col">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">📄 Import Data Order</h2>
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            Ubah file rencana "Data Order" supplier (Part Number + kolom CYCLE 1..N) menjadi cycle draft.
                        </p>
                    </div>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" aria-label="Tutup">
                        ✕
                    </button>
                </div>

                {/* Step 1: pilih file & tanggal */}
                {step === 'upload' && (
                    <div className="px-6 py-5 flex-1 overflow-y-auto">
                        <div className="flex flex-wrap items-end gap-3 mb-4">
                            <div className="w-full sm:w-48">
                                <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Tanggal Pengiriman</label>
                                <input type="date" value={date} onChange={(e) => setDate(e.target.value)} className={inputCls} />
                            </div>
                            <div className="w-full sm:flex-1">
                                <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">File (.xlsx / .xls / .csv)</label>
                                <div className="flex gap-2">
                                    <input ref={fileRef} type="file" accept=".xlsx,.xls,.csv" onChange={pickFile} className="hidden" />
                                    <Button variant="outline" onClick={() => fileRef.current?.click()}>
                                        {file ? `📎 ${file.name}` : 'Pilih File...'}
                                    </Button>
                                    {file && <span className="self-center text-xs text-gray-400">{(file.size / 1024).toFixed(0)} KB</span>}
                                </div>
                            </div>
                        </div>

                        <div className="text-xs text-gray-500 dark:text-gray-400 space-y-1 bg-gray-50 dark:bg-gray-800/50 rounded-lg px-3 py-2.5">
                            <div>✔️ Header dibaca otomatis — kolom <b>Part Number</b>, <b>Supplier</b> (kode), dan blok <b>CYCLE 1..N</b> pertama.</div>
                            <div>✔️ Satu cycle draft dibuat per (supplier × gelombang berisi qty). Baris tanpa qty dilewati.</div>
                            <div>✔️ Part / supplier yang tidak dikenal dilaporkan, tidak menggagalkan import.</div>
                        </div>

                        {error && (
                            <div className="mt-4 px-3 py-2.5 text-sm text-red-700 bg-red-50 dark:bg-red-900/20 rounded-lg">⚠️ {error}</div>
                        )}

                        <div className="flex justify-end gap-2 mt-5">
                            <Button variant="outline" onClick={onClose} disabled={busy}>Batal</Button>
                            <Button onClick={handlePreview} disabled={!file || !date || busy}>
                                {busy ? 'Membaca file...' : 'Tinjau Hasil'}
                            </Button>
                        </div>
                    </div>
                )}

                {/* Step 2: preview */}
                {step === 'preview' && preview && (
                    <div className="flex-1 overflow-y-auto px-6 py-5">
                        <div className="flex flex-wrap items-center gap-2 mb-1">
                            <h3 className="text-sm font-semibold text-gray-800 dark:text-white/90 mr-auto">Preview — {preview.date}</h3>
                            <span className={chipCls}>{preview.summary.suppliers} supplier</span>
                            <span className={chipCls}>{preview.summary.cycles} cycle</span>
                            <span className={chipCls}>{preview.summary.items} item</span>
                            <span className={chipCls}>{preview.summary.qty_total} pcs</span>
                            {preview.summary.skipped > 0 && (
                                <span className="px-2.5 py-1 text-xs rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">
                                    {preview.summary.skipped} dilewati
                                </span>
                            )}
                        </div>
                        {preview.summary.zero_qty_rows > 0 && (
                            <p className="text-[11px] text-gray-400 mb-2">
                                {preview.summary.zero_qty_rows} baris tanpa qty diabaikan.
                            </p>
                        )}

                        {preview.summary.identical_previous ? (
                            <div className="mb-3 px-3 py-2.5 text-sm text-blue-700 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                ℹ️ File ini <b>sudah pernah diimport</b> untuk tanggal {preview.date} dan tidak ada perubahan —
                                tidak ada cycle baru yang akan dibuat.
                            </div>
                        ) : (preview.summary.previous_drafts ?? 0) > 0 || (preview.summary.previous_active ?? 0) > 0 ? (
                            <div className="mb-3 border border-amber-200 dark:border-amber-800 rounded-lg px-3 py-2.5">
                                <p className="text-xs font-semibold text-amber-700 dark:text-amber-300 mb-1">
                                    ⚠️ Sudah ada import Data Order untuk tanggal ini
                                </p>
                                <p className="text-[11px] text-gray-500 dark:text-gray-400 mb-2">
                                    {preview.summary.previous_drafts} cycle draft lama ditemukan
                                    {preview.summary.previous_active > 0 ? ` · ${preview.summary.previous_active} cycle sudah diterima (tidak akan dihapus)` : ''}
                                    {(preview.summary.suppliers_with_previous || []).length > 0
                                        ? ` — supplier: ${(preview.summary.suppliers_with_previous || []).join(', ')}`
                                        : ''}.
                                    File ter-update? Pilih cara mengimport:
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    <button
                                        onClick={() => setMode('replace')}
                                        className={`px-3 py-1.5 text-xs rounded-lg border font-medium ${
                                            mode === 'replace'
                                                ? 'border-brand-500 bg-brand-500/10 text-brand-600 dark:text-brand-300'
                                                : 'border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400'
                                        }`}
                                    >
                                        🔄 Ganti — hapus draft lama, buat ulang versi terbaru
                                    </button>
                                    <button
                                        onClick={() => setMode('append')}
                                        className={`px-3 py-1.5 text-xs rounded-lg border font-medium ${
                                            mode === 'append'
                                                ? 'border-brand-500 bg-brand-500/10 text-brand-600 dark:text-brand-300'
                                                : 'border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400'
                                        }`}
                                    >
                                        ➕ Tambah — buat cycle baru di samping yang lama
                                    </button>
                                </div>
                            </div>
                        ) : null}

                        <div className="space-y-3">
                            {preview.suppliers.map((s) => (
                                <div key={s.supplier_code} className="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                    <div className="flex items-center justify-between px-3 py-2 bg-[#F8F9FC] dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                                        <div className="text-sm font-semibold text-gray-800 dark:text-white/90">
                                            {s.supplier_code} <span className="text-xs font-normal text-gray-400">— {s.supplier_name}</span>
                                        </div>
                                        <div className="text-[11px] text-gray-400">
                                            {s.cycles.length} gelombang
                                        </div>
                                    </div>
                                    <div className="px-3 py-2 space-y-1.5">
                                        {s.cycles.map((c) => {
                                            const shift = shiftBadge(c.shift);
                                            return (
                                                <div key={c.file_cycle} className="flex items-center gap-2 text-xs">
                                                    <span className="font-mono font-semibold text-gray-700 dark:text-gray-200 w-16">CYCLE {c.file_cycle}</span>
                                                    {shift && (
                                                        <span className={`inline-block px-1.5 py-0.5 text-[10px] font-semibold rounded ${shift.cls}`}>{shift.label}</span>
                                                    )}
                                                    <span className="text-gray-500 dark:text-gray-400">{c.parts} part</span>
                                                    <span className="text-gray-500 dark:text-gray-400">·</span>
                                                    <span className="tabular-nums text-gray-700 dark:text-gray-200">{c.qty} pcs</span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}

                            {preview.suppliers.length === 0 && (
                                <p className="text-sm text-gray-500 py-6 text-center">Tidak ada data ber-qty yang bisa dijadikan cycle.</p>
                            )}

                            {preview.skipped.length > 0 && (
                                <div className="border border-amber-200 dark:border-amber-800 rounded-lg px-3 py-2.5">
                                    <p className="text-xs font-semibold text-amber-700 dark:text-amber-300 mb-1.5">⚠️ Dilewati ({preview.summary.skipped})</p>
                                    <div className="max-h-32 overflow-y-auto space-y-1">
                                        {preview.skipped.map((k, i) => (
                                            <div key={i} className="text-[11px] text-gray-500 dark:text-gray-400 font-mono">
                                                {k.part_number} — {k.message} ({k.supplier_code}, {k.quantity} pcs)
                                            </div>
                                        ))}
                                        {preview.summary.skipped > preview.skipped.length && (
                                            <div className="text-[11px] text-gray-400">… dan {preview.summary.skipped - preview.skipped.length} lainnya</div>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>

                        {error && (
                            <div className="mt-4 px-3 py-2.5 text-sm text-red-700 bg-red-50 dark:bg-red-900/20 rounded-lg">⚠️ {error}</div>
                        )}

                        <div className="flex justify-end gap-2 mt-5 pb-1">
                            <Button variant="outline" onClick={() => setStep('upload')} disabled={busy}>← Kembali</Button>
                            <Button
                                onClick={handleApply}
                                disabled={preview.summary.cycles === 0 || !!preview.summary.identical_previous || busy}
                            >
                                {busy
                                    ? 'Membuat cycle...'
                                    : mode === 'replace'
                                        ? `🔄 Ganti & Buat ${preview.summary.cycles} Cycle Draft`
                                        : `Buat ${preview.summary.cycles} Cycle Draft`}
                            </Button>
                        </div>
                    </div>
                )}

                {/* Step 3: hasil */}
                {step === 'done' && result && (
                    <div className="px-6 py-6 flex-1 overflow-y-auto">
                        <div className="text-center py-2">
                            <div className="text-3xl mb-2">{result.unchanged ? 'ℹ️' : '✅'}</div>
                            {result.unchanged ? (
                                <>
                                    <h3 className="text-base font-semibold text-gray-800 dark:text-white/90 mb-1">Tidak ada perubahan</h3>
                                    <p className="text-sm text-gray-500 dark:text-gray-400 mb-5">{result.message}</p>
                                </>
                            ) : (
                                <>
                                    <h3 className="text-base font-semibold text-gray-800 dark:text-white/90 mb-1">
                                        {result.replaced_cycles ? `Cycle diganti (${result.replaced_cycles} draft lama) & dibuat ulang` : 'Cycle draft berhasil dibuat'}
                                    </h3>
                                    <p className="text-sm text-gray-500 dark:text-gray-400 mb-2">
                                        Tanggal {result.date} — {result.suppliers} supplier, {result.cycles_created} cycle, {result.items_created} item ({result.qty_total} pcs).
                                    </p>
                                    {result.message && <p className="text-xs text-amber-600 mb-2">{result.message}</p>}
                                    <p className="text-xs text-gray-400 mb-5">
                                        Cycle masih berstatus <b>Draft</b> — buka lewat "🚚 Terima Barang" untuk menerima kedatangannya.
                                    </p>
                                </>
                            )}
                            <Button onClick={handleDone}>Selesai</Button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
