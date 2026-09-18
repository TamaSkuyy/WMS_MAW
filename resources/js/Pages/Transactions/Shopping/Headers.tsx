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
 * kerja lagi, salah satu alur tetap bisa dipakai.
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

export default function Headers({ shoppingLocations = [], draftFrameCount = 0 }: any) {
    const { flash = {}, errors = {} } = usePage().props as any;

    const [shoppingDate, setShoppingDate] = useState(() => toLocalDateTimeInput(new Date()));
    // Default 1 baris saja (permintaan operator): tambah baris hanya kalau perlu,
    // atau scan frame berurutan — baris baru dibuat otomatis setelah tiap scan.
    const [rows, setRows] = useState<HeaderRow[]>(() => [newRow()]);
    const [submitting, setSubmitting] = useState(false);
    const [headerImportOpen, setHeaderImportOpen] = useState(false);
    const [scanMsg, setScanMsg] = useState<{ type: 'ok' | 'error'; text: string } | null>(null);
    const rowsRef = useRef<HTMLDivElement>(null);

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

    const addRows = (count: number) => {
        setRows((prev) => [...prev, ...Array.from({ length: count }, () => newRow())]);

        // Fokuskan frame number baris pertama yang baru ditambahkan.
        setTimeout(() => {
            const inputs = rowsRef.current?.querySelectorAll<HTMLInputElement>('input[id^="frame-"]');
            inputs?.[inputs.length - count]?.focus();
        }, 0);
    };

    const removeRow = (key: string) =>
        setRows((prev) => (prev.length <= 1 ? [newRow()] : prev.filter((r) => r.key !== key)));

    /** Fokuskan input frame number pada index baris tertentu. */
    const focusFrameInput = (index: number) => {
        const inputs = rowsRef.current?.querySelectorAll<HTMLInputElement>('input[id^="frame-"]');
        inputs?.[index]?.focus();
    };

    /**
     * Hasil scan barcode frame pada satu baris:
     * isi baris itu, lalu otomatis siapkan baris berikutnya supaya operator bisa
     * scan terus tanpa klik. Frame yang sudah ada di baris lain ditolak (dobel).
     */
    const handleScan = (rowKey: string, rawCode: string) => {
        const code = (rawCode || '').trim();
        if (code === '') return;

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

    /** Tempel (paste) banyak frame sekaligus: satu frame per baris. */
    const handlePaste = (key: string, e: React.ClipboardEvent<HTMLDivElement>) => {
        const text = e.clipboardData.getData('text');
        if (!text || !/[\r\n\t]/.test(text)) return;

        const frames = text
            .split(/[\r\n\t]+/)
            .map((s) => s.trim())
            .filter(Boolean);
        if (frames.length === 0) return;

        e.preventDefault();
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

    const handleSubmit = () => {
        if (submitting) return;
        if (filledRows.length === 0) {
            alert('Isi minimal satu Frame Number.');
            return;
        }

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
                },
                onFinish: () => setSubmitting(false),
            }
        );
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
                <div className="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <Label>Tanggal Shopping (opsional)</Label>
                        <Input
                            type="datetime-local"
                            value={shoppingDate}
                            onChange={(e) => setShoppingDate(e.target.value)}
                        />
                        <p className="mt-1 text-xs text-gray-400">Berlaku untuk semua baris di bawah.</p>
                    </div>
                    <div className="sm:col-span-2 flex items-end">
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Total frame draft di sistem saat ini: <strong>{draftFrameCount}</strong>.
                            Frame yang nomornya sudah ada akan dilewati (tidak dobel).
                        </p>
                    </div>
                </div>

                {errors?.rows && (
                    <p className="mb-3 text-sm text-red-500">{errors.rows}</p>
                )}

                <div className="overflow-x-auto" ref={rowsRef}>
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-gray-200 dark:border-gray-700 text-left text-xs uppercase text-gray-500">
                                <th className="py-2 pr-3 w-10">#</th>
                                <th className="py-2 pr-3 min-w-[220px]">Line / Lokasi Tujuan</th>
                                <th className="py-2 pr-3 min-w-[200px]">Frame Number</th>
                                <th className="py-2 pr-3 w-24">Cripple</th>
                                <th className="py-2 w-16"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr
                                    key={row.key}
                                    className="border-b border-gray-100 dark:border-gray-800"
                                    onPaste={(e) => handlePaste(row.key, e)}
                                    onKeyDown={(e) => {
                                        if (e.key !== 'Enter') return;

                                        // Enter di kolom frame = pindah ke baris berikutnya
                                        // (scanner barcode USB mengirim Enter setelah scan),
                                        // dan baris baru dibuat kalau sudah di baris terakhir.
                                        e.preventDefault();
                                        setScanMsg(null);

                                        if (index === rows.length - 1) {
                                            addRows(1);
                                        } else {
                                            focusFrameInput(index + 1);
                                        }
                                    }}
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
                                            <div className="min-w-0 flex-1">
                                                <Input
                                                    type="text"
                                                    id={`frame-${row.key}`}
                                                    value={row.frame_number}
                                                    placeholder="Scan / ketik frame number"
                                                    onChange={(e) => updateRow(row.key, { frame_number: e.target.value })}
                                                    className="font-mono"
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
                                        >
                                            ✕
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <p className="mt-2 text-xs text-gray-400">
                    Tips: klik <strong>📷</strong> di kolom Frame Number untuk scan barcode — setelah satu scan,
                    baris baru otomatis disiapkan supaya bisa scan terus tanpa klik. Bisa juga tempel (Ctrl+V)
                    banyak frame sekaligus (satu frame per baris) atau ketik manual; tekan Enter di baris
                    terakhir untuk menambah baris.
                </p>

                {scanMsg && (
                    <p className={`mt-2 text-sm ${scanMsg.type === 'ok' ? 'text-green-600' : 'text-red-500'}`}>
                        {scanMsg.text}
                    </p>
                )}

                <div className="mt-5 flex flex-wrap items-center gap-2">
                    <Button type="button" variant="outline" onClick={() => addRows(1)}>+ 1 Baris</Button>
                    <Button type="button" variant="outline" onClick={() => addRows(5)}>+ 5 Baris</Button>
                    <div className="flex-1" />
                    <span className="text-sm text-gray-500">{filledRows.length} frame siap disimpan</span>
                    <Button onClick={handleSubmit} disabled={submitting || filledRows.length === 0}>
                        {submitting ? 'Menyimpan...' : 'Simpan Header'}
                    </Button>
                </div>
            </ComponentCard>

            <div className="mt-6 flex flex-wrap gap-2">
                <Link href={route('shoppings.index', { import: 'items' })}>
                    <Button>Lanjut: Import Barang (Langkah 2) →</Button>
                </Link>
                <Link href={route('shoppings.index')}>
                    <Button variant="outline">Kembali ke Daftar Shopping</Button>
                </Link>
                {SHOW_HEADER_IMPORT && (
                    <Button variant="outline" onClick={() => setHeaderImportOpen(true)}>Import File Header</Button>
                )}
            </div>

            {/* Import FILE HEADER (Line + Frame Number) — disiapkan untuk dipakai
                kalau pusat/TAM memutuskan kirim file, bukan input manual.
                Aktifkan dengan SHOW_HEADER_IMPORT = true di atas. */}
            {SHOW_HEADER_IMPORT && (
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
