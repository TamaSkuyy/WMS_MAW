import React, { useMemo, useRef, useState } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';
import Checkbox from '../../../Tailadmin/components/form/input/Checkbox';
import Alert from '../../../Tailadmin/components/ui/alert/Alert';
import ImportModal from '../../../Components/ImportExport/ImportModal';
import ScanButton from '../../../Components/ScanButton';

/**
 * Alur 2 LANGKAH (permintaan pusat/TAM):
 *   1. Input HEADER dulu — line tujuan + frame number (halaman ini, tanpa file).
 *   2. Import BARANG — part number + qty untuk frame yang sudah terdaftar
 *      (tombol "Import Barang" di halaman daftar Shopping).
 * Alur import gabungan lama TIDAK dihapus — kalau pusat/TAM mengubah urutan
 * kerja lagi, salah satu jalur tetap bisa dipakai.
 *
 * UI: desktop = tabel, HP (<768px) = kartu per frame (tanpa scroll horizontal),
 * dengan footer aksi sticky. Semua logika (scan, paste, Enter, submit) tidak diubah.
 */
const SHOW_HEADER_IMPORT = false; // Import file header (Line + Frame) — disiapkan, tombol disembunyikan.

interface HeaderRow {
    key: string;
    frame_number: string;
    shopping_location_id: string;
    is_cripple: boolean;
}

let rowSeq = 0;
const newRow = (): HeaderRow => ({
    key: `row-${++rowSeq}-${Date.now()}`,
    frame_number: '',
    shopping_location_id: '',
    is_cripple: false,
});

/** Format waktu lokal untuk <input type="datetime-local"> (hindari geser UTC). */
function toLocalDateTimeInput(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** Pisahkan tempelan multi-baris menjadi daftar frame number. */
function splitFrames(text: string): string[] {
    return text
        .split(/[\r\n\t]+/)
        .map((s) => s.trim())
        .filter(Boolean);
}

/** True kalau viewport saat ini layout HP (Tailwind `md` = 768px). */
function isMobileViewport(): boolean {
    return typeof window !== 'undefined' && window.matchMedia('(max-width: 767px)').matches;
}

export default function Headers({ shoppingLocations = [], draftFrameCount = 0 }: any) {
    const { flash = {}, errors = {} } = usePage().props as any;
    // Import Barang (langkah 2) — HANYA Leader ke atas (`import shoppings`).
    const permissions = (usePage().props.auth as any)?.user?.permissions || [];
    const canImport = permissions.includes('import shoppings');

    const [shoppingDate, setShoppingDate] = useState(() => toLocalDateTimeInput(new Date()));
    // Default 1 baris saja (permintaan operator): tambah baris hanya kalau perlu,
    // atau scan frame berurutan — baris baru dibuat otomatis setelah tiap scan.
    const [rows, setRows] = useState<HeaderRow[]>(() => [newRow()]);
    const [submitting, setSubmitting] = useState(false);
    const [headerImportOpen, setHeaderImportOpen] = useState(false);
    const [scanMsg, setScanMsg] = useState<{ type: 'ok' | 'error'; text: string } | null>(null);
    const [formError, setFormError] = useState<string | null>(null);

    // Dua kontainer (tabel desktop & kartu HP) sama-sama ada di DOM — fokus harus
    // diarahkan ke kontainer yang sedang tampil supaya tidak salah baris.
    const tableRef = useRef<HTMLDivElement>(null);
    const cardsRef = useRef<HTMLDivElement>(null);

    const locationOptions = useMemo(
        () => shoppingLocations.map((l: any) => ({
            value: String(l.id),
            label: l.barcode ? `${l.name} (${l.barcode})` : l.name,
        })),
        [shoppingLocations]
    );

    const filledRows = rows.filter((r) => r.frame_number.trim() !== '');

    const updateRow = (key: string, patch: Partial<HeaderRow>) =>
        setRows((prev) => prev.map((r) => (r.key === key ? { ...r, ...patch } : r)));

    /** Fokuskan input frame number pada index baris tertentu (kontainer yang tampil). */
    const focusFrameInput = (index: number, scroll = false) => {
        const container = isMobileViewport() ? cardsRef.current : tableRef.current;
        const inputs = container?.querySelectorAll<HTMLInputElement>('[data-frame-input] input');
        const el = inputs?.[index];
        if (!el) return;
        el.focus();
        if (scroll) el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    };

    const addRows = (count: number) => {
        const startIndex = rows.length;
        setRows((prev) => [...prev, ...Array.from({ length: count }, () => newRow())]);
        setFormError(null);

        // Fokuskan frame number baris pertama yang baru ditambahkan.
        setTimeout(() => focusFrameInput(startIndex, true), 0);
    };

    const removeRow = (key: string) =>
        setRows((prev) => (prev.length <= 1 ? [newRow()] : prev.filter((r) => r.key !== key)));

    /**
     * Hasil scan barcode frame pada satu baris:
     * isi baris itu, lalu otomatis siapkan baris berikutnya supaya operator bisa
     * scan terus tanpa klik. Frame yang sudah ada di baris lain ditolak (dobel).
     */
    const handleScan = (rowKey: string, rawCode: string) => {
        const code = (rawCode || '').trim();
        if (code === '') return;

        setFormError(null);
        const normalized = code.toLowerCase();
        const duplicate = rows.find((r) => r.key !== rowKey && r.frame_number.trim().toLowerCase() === normalized);

        if (duplicate) {
            const line = rows.findIndex((r) => r.key === duplicate.key) + 1;
            setScanMsg({ type: 'error', text: `Frame ${code} sudah ada di baris ${line} — tidak perlu discan ulang.` });
            return;
        }

        const index = rows.findIndex((r) => r.key === rowKey);
        const isLast = index === rows.length - 1;

        setRows((prev) => {
            const next = prev.map((r) => (r.key === rowKey ? { ...r, frame_number: code } : r));
            if (isLast) next.push(newRow());

            return next;
        });
        setScanMsg({
            type: 'ok',
            text: `✓ ${code} masuk baris ${index + 1}${isLast ? ' — baris baru disiapkan, langsung scan lagi' : ''}`,
        });

        setTimeout(() => focusFrameInput(index + 1), 0);
    };

    /** Enter di kolom frame = pindah ke baris berikutnya (scanner USB kirim Enter),
     *  dan baris baru dibuat kalau sudah di baris terakhir. */
    const handleEnterKey = (index: number, e: React.KeyboardEvent) => {
        if (e.key !== 'Enter') return;

        e.preventDefault();
        setScanMsg(null);

        if (index === rows.length - 1) {
            addRows(1);
        } else {
            focusFrameInput(index + 1);
        }
    };

    /** Tempel (paste) banyak frame sekaligus mulai dari baris tertentu. */
    const handlePaste = (key: string, e: React.ClipboardEvent<HTMLDivElement>) => {
        const text = e.clipboardData.getData('text');
        if (!text || !/[\r\n\t]/.test(text)) return;

        const frames = splitFrames(text);
        if (frames.length === 0) return;

        e.preventDefault();
        setFormError(null);
        setRows((prev) => {
            const index = prev.findIndex((r) => r.key === key);
            if (index === -1) return prev;
            const next = [...prev];
            frames.forEach((frame, i) => {
                const target = index + i;
                if (target < next.length) {
                    next[target] = { ...next[target], frame_number: frame };
                } else {
                    next.push({ ...newRow(), frame_number: frame });
                }
            });
            return next;
        });
    };

    /** Tempel dari clipboard (tombol) — praktis di HP yang tidak punya Ctrl+V. */
    const handleClipboardPaste = async () => {
        setFormError(null);

        const clipboard = typeof navigator !== 'undefined' ? navigator.clipboard : undefined;
        if (!clipboard?.readText) {
            setFormError('Browser ini tidak bisa membaca clipboard otomatis. Tekan lama di kolom Frame Number lalu pilih "Tempel".');
            return;
        }

        try {
            const text = await clipboard.readText();
            const frames = splitFrames(text || '');
            if (frames.length === 0) {
                setFormError('Clipboard kosong — salin dulu daftar frame number (satu frame per baris).');
                return;
            }

            setRows((prev) => {
                const next = [...prev];
                let cursor = next.findIndex((r) => r.frame_number.trim() === '');
                if (cursor === -1) {
                    next.push(newRow());
                    cursor = next.length - 1;
                }
                frames.forEach((frame, i) => {
                    const target = cursor + i;
                    if (target < next.length) {
                        next[target] = { ...next[target], frame_number: frame };
                    } else {
                        next.push({ ...newRow(), frame_number: frame });
                    }
                });
                return next;
            });
            setScanMsg({ type: 'ok', text: `📋 ${frames.length} frame ditempel dari clipboard.` });
        } catch {
            setFormError('Izin clipboard ditolak. Tekan lama di kolom Frame Number lalu pilih "Tempel".');
        }
    };

    const handleSubmit = () => {
        if (submitting) return;
        if (filledRows.length === 0) {
            setFormError('Isi minimal satu Frame Number sebelum menyimpan.');
            return;
        }

        setFormError(null);
        setSubmitting(true);
        router.post(
            route('shoppings.headers.store'),
            {
                shopping_date: shoppingDate ? shoppingDate.replace('T', ' ') : null,
                rows: filledRows.map((r) => ({
                    frame_number: r.frame_number.trim(),
                    shopping_location_id: r.shopping_location_id || null,
                    is_cripple: r.is_cripple,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    // Kembali ke 1 baris kosong (default isian).
                    setRows([newRow()]);
                    setScanMsg(null);
                    setFormError(null);
                },
                onFinish: () => setSubmitting(false),
            }
        );
    };

    /** Reset seluruh isian. */
    const resetRows = () => {
        setRows([newRow()]);
        setScanMsg(null);
        setFormError(null);
    };

    return (
        <>
            <Head title="Input Header Frame" />
            <PageBreadcrumb pageTitle="Input Header Frame (Langkah 1)" />

            {flash?.success && (
                <div className="mb-4"><Alert variant="success" title="Berhasil" message={flash.success} /></div>
            )}
            {flash?.warning && (
                <div className="mb-4"><Alert variant="warning" title="Sebagian dilewati" message={flash.warning} /></div>
            )}
            {flash?.error && (
                <div className="mb-4"><Alert variant="error" title="Gagal" message={flash.error} /></div>
            )}

            <ComponentCard
                title="Input Line & Frame Number"
                desc="Langkah 1: daftarkan line tujuan dan frame number lebih dulu. Barang & qty diisi pada langkah 2 (Import Barang)."
            >
                {/* ── Tanggal + info draft ───────────────────────────────── */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <Label htmlFor="shopping-date">Tanggal Shopping (opsional)</Label>
                        <Input
                            id="shopping-date"
                            type="datetime-local"
                            value={shoppingDate}
                            onChange={(e) => setShoppingDate(e.target.value)}
                        />
                        <p className="mt-1 text-xs text-gray-400">Berlaku untuk semua baris di bawah.</p>
                    </div>
                    <div className="flex items-center sm:col-span-2">
                        <div className="w-full rounded-lg border border-[#E9ECEF] bg-[#F8F9FC] px-4 py-3 text-sm text-[#6C757D] dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-300">
                            Total frame draft di sistem saat ini: <strong className="text-[#1A1D23] dark:text-white">{draftFrameCount}</strong>.
                            Frame yang nomornya sudah ada akan dilewati (tidak dobel).
                        </div>
                    </div>
                </div>

                {/* ── Aksi cepat sebelum daftar ──────────────────────────── */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={handleClipboardPaste}
                        className="min-h-11 sm:min-h-0"
                        title="Tempel daftar frame number dari clipboard (satu frame per baris)"
                    >
                        📋 Tempel dari Clipboard
                    </Button>
                </div>

                {errors?.rows && (
                    <p className="text-sm text-red-500">{errors.rows}</p>
                )}

                {/* ── Desktop (≥ md): tabel ──────────────────────────────── */}
                <div className="hidden overflow-x-auto md:block" ref={tableRef}>
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-gray-200 text-left text-xs uppercase text-gray-500 dark:border-gray-700">
                                <th className="w-10 py-2 pr-3">#</th>
                                <th className="min-w-[220px] py-2 pr-3">Line / Lokasi Tujuan</th>
                                <th className="min-w-[220px] py-2 pr-3">Frame Number</th>
                                <th className="w-24 py-2 pr-3">Cripple</th>
                                <th className="w-16 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr
                                    key={row.key}
                                    className="border-b border-gray-100 dark:border-gray-800"
                                    onPaste={(e) => handlePaste(row.key, e)}
                                >
                                    <td className="py-2 pr-3 text-gray-400">{index + 1}</td>
                                    <td className="py-2 pr-3">
                                        <SearchableSelect
                                            options={locationOptions}
                                            value={row.shopping_location_id}
                                            onChange={(v) => updateRow(row.key, { shopping_location_id: String(v || '') })}
                                            placeholder="Pilih line / lokasi..."
                                        />
                                    </td>
                                    <td className="py-2 pr-3">
                                        <div className="flex items-center gap-2">
                                            <div className="min-w-0 flex-1" data-frame-input>
                                                <Input
                                                    type="text"
                                                    id={`frame-d-${row.key}`}
                                                    value={row.frame_number}
                                                    placeholder="Scan / ketik frame number"
                                                    className="font-mono"
                                                    autoComplete="off"
                                                    spellCheck={false}
                                                    onChange={(e) => updateRow(row.key, { frame_number: e.target.value })}
                                                    onKeyDown={(e) => handleEnterKey(index, e)}
                                                />
                                            </div>
                                            <ScanButton
                                                title="Scan barcode frame number"
                                                onScan={(code) => handleScan(row.key, code)}
                                            />
                                        </div>
                                    </td>
                                    <td className="py-2 pr-3">
                                        <Checkbox
                                            checked={row.is_cripple}
                                            onChange={(checked) => updateRow(row.key, { is_cripple: checked })}
                                        />
                                    </td>
                                    <td className="py-2">
                                        <button
                                            type="button"
                                            onClick={() => removeRow(row.key)}
                                            className="inline-flex h-11 w-11 items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/20"
                                            title="Hapus baris"
                                            aria-label={`Hapus baris ${index + 1}`}
                                        >
                                            ✕
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* ── HP (< md): kartu per frame, tanpa scroll horizontal ── */}
                <div className="space-y-3 md:hidden" ref={cardsRef}>
                    {rows.map((row, index) => {
                        const filled = row.frame_number.trim() !== '';

                        return (
                            <div
                                key={row.key}
                                className={`rounded-xl border p-4 ${
                                    filled
                                        ? 'border-brand-200 bg-brand-50/60 dark:border-brand-500/30 dark:bg-brand-500/10'
                                        : 'border-[#E9ECEF] bg-white dark:border-gray-700 dark:bg-gray-900'
                                }`}
                                onPaste={(e) => handlePaste(row.key, e)}
                            >
                                <div className="mb-3 flex items-center justify-between">
                                    <span className="inline-flex items-center gap-2 text-xs font-semibold text-gray-500 dark:text-gray-400">
                                        <span className="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-gray-100 px-2 dark:bg-gray-800">
                                            {index + 1}
                                        </span>
                                        {filled ? 'Terisi' : 'Baris baru'}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => removeRow(row.key)}
                                        className="inline-flex h-11 w-11 items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/20"
                                        title="Hapus baris"
                                        aria-label={`Hapus baris ${index + 1}`}
                                    >
                                        ✕
                                    </button>
                                </div>

                                <div className="space-y-3">
                                    <div>
                                        <Label htmlFor={`frame-m-${row.key}`}>
                                            Frame Number <span className="text-red-500">*</span>
                                        </Label>
                                        <div className="flex items-start gap-2">
                                            <div className="min-w-0 flex-1" data-frame-input>
                                                <Input
                                                    type="text"
                                                    id={`frame-m-${row.key}`}
                                                    value={row.frame_number}
                                                    placeholder="Scan / ketik frame number"
                                                    className="font-mono text-base"
                                                    inputMode="text"
                                                    autoComplete="off"
                                                    autoCapitalize="characters"
                                                    autoCorrect="off"
                                                    spellCheck={false}
                                                    enterKeyHint="next"
                                                    onChange={(e) => updateRow(row.key, { frame_number: e.target.value })}
                                                    onKeyDown={(e) => handleEnterKey(index, e)}
                                                />
                                            </div>
                                            <ScanButton
                                                title="Scan barcode frame number"
                                                size="lg"
                                                onScan={(code) => handleScan(row.key, code)}
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <Label>Line / Lokasi Tujuan</Label>
                                        <SearchableSelect
                                            options={locationOptions}
                                            value={row.shopping_location_id}
                                            onChange={(v) => updateRow(row.key, { shopping_location_id: String(v || '') })}
                                            placeholder="Pilih line / lokasi..."
                                        />
                                    </div>

                                    <div className="flex min-h-11 items-center justify-between gap-3 rounded-lg border border-[#E9ECEF] px-3 py-2 dark:border-gray-700">
                                        <span className="text-sm text-gray-600 dark:text-gray-300">
                                            Cripple <span className="text-xs text-gray-400">(part tidak lengkap)</span>
                                        </span>
                                        <Checkbox
                                            checked={row.is_cripple}
                                            onChange={(checked) => updateRow(row.key, { is_cripple: checked })}
                                        />
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* ── Feedback scan / tempel ─────────────────────────────── */}
                {scanMsg && (
                    <div
                        role="status"
                        className={`rounded-lg px-3 py-2 text-sm ${
                            scanMsg.type === 'ok'
                                ? 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-300'
                                : 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300'
                        }`}
                    >
                        {scanMsg.text}
                    </div>
                )}

                {formError && <Alert variant="error" title="Perhatian" message={formError} />}

                {/* ── Tips ───────────────────────────────────────────────── */}
                <div className="rounded-lg border border-[#E9ECEF] bg-[#F8F9FC] px-4 py-3 text-xs text-[#6C757D] dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-400">
                    <p className="font-medium text-[#1A1D23] dark:text-white/90">Tips cepat</p>
                    <ul className="mt-1 list-disc space-y-0.5 pl-4">
                        <li>Klik <strong>📷</strong> di kolom Frame Number untuk scan barcode — setelah satu scan, baris baru siap otomatis.</li>
                        <li>Tempel banyak frame sekaligus lewat <strong>📋 Tempel dari Clipboard</strong> (satu frame per baris).</li>
                        <li>Tekan <strong>Enter</strong> di baris terakhir untuk menambah baris baru.</li>
                    </ul>
                </div>
            </ComponentCard>

            {/* ── Footer aksi sticky (selalu terjangkau di HP) ───────────── */}
            <div className="sticky bottom-4 z-20 mt-4 flex flex-col gap-3 rounded-xl border border-[#E9ECEF] bg-white px-4 py-4 shadow-lg dark:border-gray-700 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <Button type="button" variant="outline" onClick={() => addRows(1)} className="min-h-11 flex-1 sm:min-h-0 sm:flex-none">
                        + 1 Baris
                    </Button>
                    <Button type="button" variant="outline" onClick={() => addRows(5)} className="min-h-11 flex-1 sm:min-h-0 sm:flex-none">
                        + 5 Baris
                    </Button>
                    <Button type="button" variant="outline" onClick={resetRows} className="min-h-11 sm:min-h-0" title="Kosongkan semua baris">
                        Reset
                    </Button>
                </div>
                <div className="flex items-center justify-between gap-3 sm:justify-end">
                    <span className="text-sm text-gray-500 dark:text-gray-400">
                        <strong className="text-[#1A1D23] dark:text-white">{filledRows.length}</strong> frame siap
                    </span>
                    <Button
                        onClick={handleSubmit}
                        disabled={submitting || filledRows.length === 0}
                        className="min-h-11 flex-1 sm:min-h-0 sm:flex-none"
                    >
                        {submitting ? 'Menyimpan...' : 'Simpan Header'}
                    </Button>
                </div>
            </div>

            <div className="mt-6 flex flex-wrap gap-2">
                {canImport && (
                    <Link href={route('shoppings.index', { import: 'items' })}>
                        <Button>Lanjut: Import Barang (Langkah 2) →</Button>
                    </Link>
                )}
                <Link href={route('shoppings.index')}>
                    <Button variant="outline">Kembali ke Daftar Shopping</Button>
                </Link>
                {SHOW_HEADER_IMPORT && canImport && (
                    <Button variant="outline" onClick={() => setHeaderImportOpen(true)}>Import File Header</Button>
                )}
            </div>

            {/* Import FILE HEADER (Line + Frame Number) — disiapkan untuk dipakai
                kalau pusat/TAM memutuskan kirim file, bukan input manual.
                Aktifkan dengan SHOW_HEADER_IMPORT = true di atas. */}
            {SHOW_HEADER_IMPORT && canImport && (
                <ImportModal
                    isOpen={headerImportOpen}
                    onClose={() => setHeaderImportOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('shoppings.import-headers')}
                    previewUrl={route('shoppings.import-headers.preview')}
                    templateUrl={route('shoppings.import-headers-template')}
                    title="Header Frame"
                    fields={[
                        { key: 'line', label: 'Line / Lokasi', required: false },
                        { key: 'frame_number', label: 'Frame Number', required: true },
                        { key: 'is_cripple', label: 'Cripple', required: false },
                        { key: 'modify_date', label: 'Modify Date', required: false },
                    ]}
                />
            )}
        </>
    );
}

Headers.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
