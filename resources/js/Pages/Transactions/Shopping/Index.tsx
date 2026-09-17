import React, { useCallback, useEffect, useState } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import ImportModal from '../../../Components/ImportExport/ImportModal';
import QrScanner from '../../../Components/QrScanner';
import ScanButton from '../../../Components/ScanButton';
import BulkDeleteBar from '../../../Components/BulkDeleteBar';
import { Head, Link, router, usePage } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';
import TableActions from '../../../Tailadmin/components/common/TableActions';
import EmptyState from '../../../Tailadmin/components/common/EmptyState';
import Label from '../../../Tailadmin/components/form/Label';
import Pagination from '../../../Tailadmin/components/common/Pagination';
import Alert from '../../../Tailadmin/components/ui/alert/Alert';
import Checkbox from '../../../Tailadmin/components/form/input/Checkbox';

interface DraftShopping {
    id: number;
    frame_number: string;
    shopping_location_id: number | null;
    is_cripple: boolean;
    shopping_location?: { id: number; name: string } | null;
}

export default function Index({ shoppings, filters, shoppingLocations = [], draftFrameCount = 0, openItemImport = false }: any) {
    const permissions = (usePage().props.auth as any)?.user?.permissions || [];
    const canCreate = permissions.includes('create shoppings');
    const canEdit = permissions.includes('edit shoppings');
    const canDelete = permissions.includes('delete shoppings');
    const canShip = permissions.includes('ship shoppings');
    const { flash = {} } = usePage().props as any;

    const [importModalOpen, setImportModalOpen] = useState(false);
    // Import BARANG (langkah 2): frame harus sudah terdaftar di WMS.
    const [itemImportOpen, setItemImportOpen] = useState(!!openItemImport);
    const [itemAutoCreate, setItemAutoCreate] = useState(false);
    const [itemLocationId, setItemLocationId] = useState('');

    // ── Bulk Ship state ─────────────────────────────────────────────
    const [bulkShipOpen, setBulkShipOpen] = useState(false);
    const [bulkLocationId, setBulkLocationId] = useState('');
    const [selected, setSelected] = useState<DraftShopping[]>([]);
    const [frameSearch, setFrameSearch] = useState('');
    const [frames, setFrames] = useState<DraftShopping[]>([]);
    const [framesTotal, setFramesTotal] = useState<number>(draftFrameCount || 0);
    const [framesHasMore, setFramesHasMore] = useState(false);
    const [framesLoading, setFramesLoading] = useState(false);
    const [scannerOpen, setScannerOpen] = useState(false);
    const [scanMsg, setScanMsg] = useState<{ type: 'ok' | 'error'; text: string } | null>(null);
    // Frame yang baru saja dikirim di sesi ini — dicegah discan ulang sebelum
    // daftar draft ter-refresh dari server.
    const [shippedFrames, setShippedFrames] = useState<string[]>([]);
    const [submitting, setSubmitting] = useState(false);
    const selectedIds = selected.map((d) => d.id);

    // Jumlah frame draft bisa berubah setelah kirim massal (reload partial).
    useEffect(() => {
        setFramesTotal(draftFrameCount || 0);
    }, [draftFrameCount]);

    // Mode "Kirim Semua": satu klik untuk seluruh frame draft (setelah import).
    const [shipMode, setShipMode] = useState<'all' | 'pick'>('all');
    const [preview, setPreview] = useState<any>(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [shipResult, setShipResult] = useState<any>(null);
    const [showBlocked, setShowBlocked] = useState(false);

    // Tutup/refresh: buang ?import=items supaya modal tidak terbuka lagi
    // saat halaman di-refresh setelah import selesai.
    const closeItemImport = () => {
        setItemImportOpen(false);
        if (openItemImport) {
            router.get(route('shoppings.index'), {}, { preserveState: true, preserveScroll: true, replace: true });
        }
    };
    const refreshAfterItemImport = () => {
        if (openItemImport) {
            router.get(route('shoppings.index'), {}, { preserveScroll: true });
        } else {
            window.location.reload();
        }
    };

    // Hapus massal (superadmin)
    const isSuperadmin = ((usePage().props.auth as any)?.user?.roles || []).includes('superadmin');
    const [deleteIds, setDeleteIds] = useState<number[]>([]);
    const [deleting, setDeleting] = useState(false);
    const pageIds: number[] = (shoppings?.data || []).map((s: any) => s.id);
    const allPageSelected = pageIds.length > 0 && pageIds.every((id) => deleteIds.includes(id));
    const toggleAllPage = () => {
        setDeleteIds(allPageSelected ? [] : Array.from(new Set([...deleteIds, ...pageIds])));
    };
    const toggleOne = (id: number) => {
        setDeleteIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    };
    const getCsrfToken = (): string =>
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
    const handleBulkDelete = async () => {
        if (deleteIds.length === 0 || deleting) return;
        setDeleting(true);
        try {
            const res = await fetch(route('shoppings.bulk-delete'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ ids: deleteIds }),
            });
            const text = await res.text();
            const data = text ? JSON.parse(text) : {};
            if (!res.ok) throw new Error(data?.message || `Gagal menghapus (HTTP ${res.status})`);
            alert(data.message || 'Data terhapus.');
            setDeleteIds([]);
            router.reload({ only: ['shoppings'] });
        } catch (err: any) {
            alert(err.message || 'Gagal menghapus data.');
        } finally {
            setDeleting(false);
        }
    };

    // ── Pencarian frame draft (server-side) ────────────────────────────
    // Daftar draft TIDAK lagi dimuat seluruhnya ke browser: setelah import
    // ribuan frame, payload raksasa membuat worker Octane kehabisan memori →
    // 502 tepat setelah import selesai & halaman di-refresh.
    const fetchFrames = useCallback(async (search: string, exact = false): Promise<DraftShopping[]> => {
        setFramesLoading(true);
        try {
            const params = new URLSearchParams(
                exact ? { frame: search, limit: '5' } : { limit: '30' }
            );
            if (!exact && search.trim() !== '') params.set('search', search.trim());

            const res = await fetch(`${route('shoppings.draft-frames')}?${params.toString()}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const data = await res.json();
            const rows: DraftShopping[] = data.data || [];
            setFrames(rows);
            setFramesHasMore(!!data.has_more);
            if (typeof data.total === 'number') setFramesTotal(data.total);
            return rows;
        } catch {
            setFrames([]);
            setFramesHasMore(false);
            return [];
        } finally {
            setFramesLoading(false);
        }
    }, []);

    useEffect(() => {
        if (!bulkShipOpen) return;
        const timer = setTimeout(() => { void fetchFrames(frameSearch); }, 300);
        return () => clearTimeout(timer);
    }, [bulkShipOpen, frameSearch, fetchFrames]);

    const addSelected = (frame: DraftShopping) => {
        setSelected((prev) => (prev.some((d) => d.id === frame.id) ? prev : [...prev, frame]));
    };

    const removeSelected = (id: number) => {
        setSelected((prev) => prev.filter((x) => x.id !== id));
    };

    const handleScan = async (code: string) => {
        const scanned = code.trim().toLowerCase();
        let match = frames.find((d) => d.frame_number.toLowerCase() === scanned);

        if (!match) {
            // Frame di luar 30 hasil yang tampil → tanya server langsung.
            const found = await fetchFrames(code.trim(), true);
            match = found.find((d) => d.frame_number.toLowerCase() === scanned) ?? found[0];
        }

        if (!match) {
            setScanMsg({ type: 'error', text: `"${code}" tidak ditemukan di shopping draft` });
            return;
        }

        // Frame yang sudah discan/dipilih tidak boleh discan ulang.
        if (selected.some((d) => d.id === match!.id)) {
            setScanMsg({ type: 'error', text: `Frame ${match.frame_number} sudah discan — tidak perlu diulang` });
            return;
        }

        // Frame yang sudah dikirim (sesi ini) juga tidak boleh discan lagi.
        if (shippedFrames.includes(scanned)) {
            setScanMsg({ type: 'error', text: `Frame ${match.frame_number} sudah dikirim` });
            return;
        }

        addSelected(match);
        setScanMsg({ type: 'ok', text: `✓ ${match.frame_number} ditambahkan` });
    };

    const handleBulkShip = async () => {
        if (submitting) return;
        if (shipMode === 'pick' && selectedIds.length === 0) return;

        const label = shipMode === 'all'
            ? `Kirim SEMUA frame draft yang stoknya cukup?`
            : `Kirim ${selectedIds.length} shopping terpilih?`;
        if (!confirm(`${label}\n\nLokasi kosong akan diisi "${bulkLocationId ? 'lokasi terpilih' : 'tetap kosong'}".`)) return;

        setSubmitting(true);
        setShipResult(null);

        try {
            const res = await fetch(route('shoppings.bulk-ship'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(
                    shipMode === 'all'
                        ? { all: true, only_ready: true, shopping_location_id: bulkLocationId || null }
                        : { ids: selectedIds, shopping_location_id: bulkLocationId || null }
                ),
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                setShipResult({ ok: false, message: data?.message || `Gagal mengirim (HTTP ${res.status})` });
                return;
            }

            // Tandai frame yang baru dikirim supaya tidak bisa discan ulang.
            setShippedFrames((prev) => [
                ...prev,
                ...selected.map((d) => d.frame_number.toLowerCase()),
                ...(data.ready || []).map((r: any) => String(r.frame_number).toLowerCase()),
            ]);
            setShipResult(data);
            setSelected([]);
            router.reload({ only: ['shoppings', 'draftFrameCount'] });
        } catch (err: any) {
            setShipResult({ ok: false, message: err?.message || 'Gagal mengirim.' });
        } finally {
            setSubmitting(false);
        }
    };

    /** Pratinjau: lookup & matching stok untuk semua frame (atau yang terpilih). */
    const fetchPreview = useCallback(async (mode: 'all' | 'pick', ids: number[]) => {
        setPreviewLoading(true);
        try {
            const res = await fetch(route('shoppings.bulk-ship.preview'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(mode === 'all' ? { all: true } : { ids }),
            });
            const data = await res.json().catch(() => ({}));
            setPreview(res.ok ? data : { ok: false, message: data?.message || `HTTP ${res.status}` });
        } catch {
            setPreview({ ok: false, message: 'Gagal memuat pratinjau stok.' });
        } finally {
            setPreviewLoading(false);
        }
    }, []);

    // Saat modal dibuka: default mode "Kirim Semua" + langsung minta pratinjau.
    useEffect(() => {
        if (!bulkShipOpen) return;
        setShipResult(null);
        setShowBlocked(false);
        setShipMode('all');
        void fetchPreview('all', []);
    }, [bulkShipOpen, fetchPreview]);

    // Mode "pilih/scan": pratinjau mengikuti frame yang dipilih.
    useEffect(() => {
        if (!bulkShipOpen || shipMode !== 'pick') return;
        const timer = setTimeout(() => void fetchPreview('pick', selectedIds), 400);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [bulkShipOpen, shipMode, selectedIds.join(',')]);

    const handleDelete = (id: number) => {
        if (confirm('Hapus shopping ini?')) {
            router.delete(route('shoppings.destroy', id));
        }
    };

    const statusColors: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800',
        shipped: 'bg-blue-100 text-blue-800',
        cripple: 'bg-red-100 text-red-800',
        completed: 'bg-green-100 text-green-800',
    };

    return (
        <>
            <Head title="Shopping" />
            <PageBreadcrumb pageTitle="Shopping" />

            {flash?.success && (
                <div className="mb-4"><Alert variant="success" title="Berhasil" message={flash.success} /></div>
            )}
            {flash?.warning && (
                <div className="mb-4"><Alert variant="warning" title="Perhatian" message={flash.warning} /></div>
            )}
            {flash?.error && (
                <div className="mb-4"><Alert variant="error" title="Gagal" message={flash.error} /></div>
            )}

            <div className="mb-4 rounded-xl border border-brand-200 bg-brand-50 p-4 text-sm text-brand-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-300">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        Alur 2 langkah: <strong>1. Input Header</strong> (line &amp; frame number) →{' '}
                        <strong>2. Import Barang</strong> (part number &amp; qty untuk frame yang sudah terdaftar).{' '}
                        Frame draft saat ini: <strong>{framesTotal}</strong>.
                    </div>
                    {canShip && framesTotal > 0 && (
                        <Button onClick={() => setBulkShipOpen(true)}>
                            🚀 Kirim Semua ({framesTotal} frame)
                        </Button>
                    )}
                </div>
            </div>
            <ComponentCard title="Daftar Shopping">
                <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="w-full sm:min-w-[200px]">
                            <Label>Cari Lokasi (bisa scan barcode)</Label>
                            <div className="flex gap-2">
                                <div className="flex-1 min-w-0">
                                    <Input
                                        type="text"
                                        defaultValue={filters?.search || ''}
                                        placeholder="Nama lokasi tujuan..."
                                        onChange={(e) => router.get(route('shoppings.index'), { ...filters, search: e.target.value }, { preserveState: true, replace: true })}
                                    />
                                </div>
                                <ScanButton
                                    title="Scan barcode lokasi"
                                    onScan={(code) => {
                                        const found = (shoppingLocations || []).find((l: any) =>
                                            String(l.barcode || '').toUpperCase() === code.toUpperCase() ||
                                            String(l.name || '').toUpperCase() === code.toUpperCase()
                                        );
                                        router.get(route('shoppings.index'), { ...filters, search: found ? found.name : code }, { preserveState: true, replace: true });
                                    }}
                                />
                            </div>
                        </div>
                        <div className="w-full sm:min-w-[160px]">
                            <Label>Status</Label>
                            <SearchableSelect
                                options={[
                                    { value: '', label: 'Semua' },
                                    { value: 'draft', label: 'Draft' },
                                    { value: 'shipped', label: 'Dikirim' },
                                    { value: 'cripple', label: 'Cripple' },
                                    { value: 'completed', label: 'Completed' },
                                ]}
                                value={filters?.status || ''}
                                onChange={(v) => router.get(route('shoppings.index'), { ...filters, status: v as string }, { preserveState: true, replace: true })}
                            />
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {canCreate && (
                            <Link href={route('shoppings.headers.create')}>
                                <Button variant="outline">1. Input Header</Button>
                            </Link>
                        )}
                        {canCreate && (
                            <Button onClick={() => setItemImportOpen(true)}>2. Import Barang</Button>
                        )}
                        {canShip && (
                            <Button variant="outline" onClick={() => setBulkShipOpen(true)}>
                                🚚 Kirim Massal
                            </Button>
                        )}
                        {canCreate && (
                            <Link href={route('shoppings.create')}><Button variant="outline">Tambah Shopping</Button></Link>
                        )}
                        {canCreate && (
                            <Button variant="outline" title="Alur lama: satu file berisi frame + barang + qty" onClick={() => setImportModalOpen(true)}>
                                Import Gabungan
                            </Button>
                        )}
                    </div>
                </div>
                {shoppings.data.length === 0 ? (
                    <EmptyState
                        icon="📤"
                        title="Belum ada shopping"
                        message="Buat shopping pengiriman barang ke lokasi tujuan."
                        actionLabel={canCreate ? "Tambah Shopping" : undefined}
                        actionRoute={canCreate ? route('shoppings.create') : undefined}
                    />
                ) : (
                <div className="overflow-x-auto">
                    {isSuperadmin && (
                        <BulkDeleteBar
                            count={deleteIds.length}
                            busy={deleting}
                            entityLabel="shopping"
                            onClear={() => setDeleteIds([])}
                            onConfirm={handleBulkDelete}
                        />
                    )}
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                {isSuperadmin && (
                                    <th className="px-2 py-2 w-12">
                                        <label className="flex min-h-11 min-w-11 items-center justify-center cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={allPageSelected}
                                                onChange={toggleAllPage}
                                                title="Pilih semua di halaman ini"
                                                aria-label="Pilih semua di halaman ini"
                                                className="h-5 w-5"
                                            />
                                        </label>
                                    </th>
                                )}
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lokasi Tujuan</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal Kirim</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dikirim Oleh</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            {shoppings.data.map((s: any) => (
                                <tr key={s.id} className={`transition-colors ${deleteIds.includes(s.id) ? 'bg-red-50 dark:bg-red-900/10' : 'hover:bg-gray-50 dark:hover:bg-gray-800/50'}`}>
                                    {isSuperadmin && (
                                        <td className="px-2 py-2">
                                            <label className="flex min-h-11 min-w-11 items-center justify-center cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    checked={deleteIds.includes(s.id)}
                                                    onChange={() => toggleOne(s.id)}
                                                    aria-label={`Pilih shopping ${s.id}`}
                                                    className="h-5 w-5"
                                                />
                                            </label>
                                        </td>
                                    )}
                                    <td className="px-4 py-3 whitespace-nowrap text-sm">{s.shopping_location?.name || '-'}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm">
                                        {s.shopping_date ? new Date(s.shopping_date).toLocaleString('id-ID', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '-'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm">{s.shipped_by?.name || '-'}</td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <span className={`inline-block px-2 py-1 text-xs font-medium rounded-full ${statusColors[s.status]}`}>{s.status}</span>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm">{s.items_count}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                        <div className="flex items-center gap-0.5">
                                            <TableActions
                                                viewRoute={route('shoppings.show', s.id)}
                                            />
                                            {s.status === 'draft' && (
                                                <>
                                                    {canEdit && (
                                                    <Link
                                                        href={route('shoppings.edit', s.id)}
                                                        className="group relative inline-flex items-center justify-center w-11 h-11 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-500/20 transition-colors"
                                                        title="Edit"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                            <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                        <span className="absolute -top-8 left-1/2 -translate-x-1/2 px-2 py-1 text-xs font-medium text-white bg-gray-800 dark:bg-gray-200 dark:text-gray-800 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-50">
                                                            Edit
                                                        </span>
                                                    </Link>
                                                    )}
                                                    {canDelete && (
                                                    <button
                                                        onClick={() => handleDelete(s.id)}
                                                        className="group relative inline-flex items-center justify-center w-11 h-11 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/20 transition-colors"
                                                        title="Hapus"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                            <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                        <span className="absolute -top-8 left-1/2 -translate-x-1/2 px-2 py-1 text-xs font-medium text-white bg-gray-800 dark:bg-gray-200 dark:text-gray-800 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-50">
                                                            Hapus
                                                        </span>
                                                    </button>
                                                    )}
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                )}
                {shoppings.total > shoppings.per_page && (
                    <Pagination
                        prevUrl={shoppings.prev_page_url}
                        perPage={shoppings.per_page}
                        nextUrl={shoppings.next_page_url}
                        currentPage={shoppings.current_page}
                        lastPage={shoppings.last_page}
                        from={shoppings.from}
                        to={shoppings.to}
                        total={shoppings.total}
                    />
                )}
            </ComponentCard>

            {/* Import BARANG (Langkah 2) — frame harus sudah diinput header-nya. */}
            {canCreate && (
                <ImportModal
                    isOpen={itemImportOpen}
                    onClose={closeItemImport}
                    onComplete={refreshAfterItemImport}
                    importUrl={route('shoppings.import-items')}
                    previewUrl={route('shoppings.import-items.preview')}
                    templateUrl={route('shoppings.import-items-template')}
                    title="Barang Shopping"
                    extraParams={() => ({
                        auto_create_frame: itemAutoCreate ? '1' : '0',
                        ...(itemLocationId ? { shopping_location_id: itemLocationId } : {}),
                    })}
                    extraNode={(
                        <div className="space-y-3 rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                            <div>
                                <Label>Line / Lokasi Tujuan (opsional)</Label>
                                <SearchableSelect
                                    options={[
                                        { value: '', label: '— Tidak diisi —' },
                                        ...shoppingLocations.map((l: any) => ({ value: String(l.id), label: l.name })),
                                    ]}
                                    value={itemLocationId}
                                    onChange={(v) => setItemLocationId(String(v || ''))}
                                    placeholder="Dipakai hanya untuk frame baru..."
                                />
                                <p className="mt-1 text-xs text-gray-400">
                                    Diisi otomatis hanya kalau frame baru dibuat di bawah ini.
                                </p>
                            </div>
                            <Checkbox
                                checked={itemAutoCreate}
                                onChange={setItemAutoCreate}
                                label="Buat frame otomatis kalau belum terdaftar (abaikan alur header dulu)"
                            />
                        </div>
                    )}
                    fields={[
                        { key: 'frame_number', label: 'Frame Number', required: true },
                        { key: 'part_number', label: 'Part Number', required: true },
                        { key: 'quantity', label: 'Quantity', required: true },
                        { key: 'confirmed', label: 'Confirmed', required: false },
                        { key: 'cripple', label: 'Cripple', required: false },
                        { key: 'modify_date', label: 'Modify Date', required: false },
                    ]}
                />
            )}

            {/* Import GABUNGAN (alur lama) — sengaja dipertahankan. */}
            {canCreate && (
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('shoppings.import')}
                    previewUrl={route('shoppings.import.preview')}
                    templateUrl={route('shoppings.import-template')}
                    title="Shopping (Gabungan)"
                    fields={[
                        { key: 'frame_number', label: 'Frame Number', required: true },
                        { key: 'part_number', label: 'Part Number', required: true },
                        { key: 'quantity', label: 'Quantity', required: true },
                        { key: 'confirmed', label: 'Confirmed', required: false },
                        { key: 'cripple', label: 'Cripple', required: false },
                        { key: 'modify_date', label: 'Modify Date', required: false },
                    ]}
                />
            )}

            {/* ── Modal Kirim Massal ─────────────────────────────────────── */}
            {bulkShipOpen && (
                <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-xl bg-white shadow-xl dark:bg-gray-900">
                        <div className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">🚚 Kirim Massal</h2>
                                <button onClick={() => setBulkShipOpen(false)} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    ✕
                                </button>
                            </div>

                            {shipResult ? (
                                /* ── Hasil pengiriman ── */
                                <div>
                                    <div className={`mb-4 rounded-lg p-4 ${shipResult.ok ? 'bg-green-50 text-green-800 dark:bg-green-900/20 dark:text-green-300' : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300'}`}>
                                        <p className="font-semibold">{shipResult.ok ? '✅ Pengiriman diproses' : 'Gagal mengirim'}</p>
                                        <p className="mt-1 text-sm">{shipResult.message}</p>
                                    </div>

                                    {shipResult.ok && (
                                        <div className="mb-4 grid grid-cols-3 gap-3 text-center">
                                            <div className="rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-900/40 dark:bg-green-900/10">
                                                <div className="text-2xl font-bold text-green-700 dark:text-green-400">{shipResult.shipped ?? 0}</div>
                                                <div className="text-[11px] font-medium uppercase text-green-700 dark:text-green-400">Terkirim</div>
                                            </div>
                                            <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/40 dark:bg-amber-900/10">
                                                <div className="text-2xl font-bold text-amber-700 dark:text-amber-400">{shipResult.skipped ?? 0}</div>
                                                <div className="text-[11px] font-medium uppercase text-amber-700 dark:text-amber-400">Dilewati</div>
                                            </div>
                                            <div className="rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-900/40 dark:bg-red-900/10">
                                                <div className="text-2xl font-bold text-red-700 dark:text-red-400">{shipResult.failed ?? 0}</div>
                                                <div className="text-[11px] font-medium uppercase text-red-700 dark:text-red-400">Gagal</div>
                                            </div>
                                        </div>
                                    )}

                                    {(shipResult.remaining ?? 0) > 0 && (
                                        <p className="mb-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                                            Masih ada <strong>{shipResult.remaining}</strong> frame yang belum diproses (batas per sekali kirim).
                                            Klik <strong>Kirim Lagi</strong> untuk melanjutkan sisanya.
                                        </p>
                                    )}

                                    {(shipResult.blocked || []).length > 0 && (
                                        <div className="mb-4 rounded-lg border border-amber-200 dark:border-amber-900/40">
                                            <div className="border-b border-amber-200 px-3 py-2 text-xs font-semibold text-amber-700 dark:border-amber-900/40 dark:text-amber-300">
                                                Frame dilewati — barangnya masih DRAFT, bukan hilang:
                                            </div>
                                            <div className="max-h-44 overflow-y-auto divide-y divide-amber-100 dark:divide-amber-900/30">
                                                {(shipResult.blocked || []).map((b: any) => (
                                                    <div key={b.id} className="px-3 py-2 text-xs">
                                                        <span className="font-mono font-medium text-gray-700 dark:text-gray-200">{b.frame_number}</span>
                                                        <span className="text-gray-400"> · {b.items} item / {b.quantity} pcs</span>
                                                        <ul className="mt-1 list-disc pl-4 text-red-500">
                                                            {(b.issues || []).map((issue: string, i: number) => <li key={i}>{issue}</li>)}
                                                        </ul>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {(shipResult.failures || []).length > 0 && (
                                        <div className="mb-4 rounded-lg border border-red-200 dark:border-red-900/40">
                                            <div className="border-b border-red-200 px-3 py-2 text-xs font-semibold text-red-700 dark:border-red-900/40 dark:text-red-300">
                                                Gagal diproses:
                                            </div>
                                            <div className="max-h-44 overflow-y-auto divide-y divide-red-100 dark:divide-red-900/30">
                                                {(shipResult.failures || []).map((f: any, i: number) => (
                                                    <div key={i} className="px-3 py-2 text-xs">
                                                        <span className="font-mono font-medium text-gray-700 dark:text-gray-200">{f.frame || '#' + f.id}</span>
                                                        <span className="text-red-500"> — {f.reason}</span>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    <div className="flex justify-end gap-3">
                                        <Button variant="outline" onClick={() => setShipResult(null)}>Kirim Lagi</Button>
                                        <Button onClick={() => { setBulkShipOpen(false); setFrameSearch(''); }}>
                                            Tutup &amp; Lihat Daftar
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                /* ── Persiapan pengiriman ── */
                                <div>
                                    {/* Mode: semua draft (1 klik) atau pilih/scan frame */}
                                    <div className="mb-4 grid grid-cols-1 gap-2 rounded-lg bg-gray-100 p-1 sm:grid-cols-2 dark:bg-gray-800">
                                        <button
                                            type="button"
                                            onClick={() => setShipMode('all')}
                                            className={`rounded-md px-3 py-2 text-sm font-medium transition ${shipMode === 'all' ? 'bg-white text-brand-700 shadow-sm dark:bg-gray-900 dark:text-brand-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'}`}
                                        >
                                            🚀 Kirim Semua Draft ({framesTotal})
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setShipMode('pick')}
                                            className={`rounded-md px-3 py-2 text-sm font-medium transition ${shipMode === 'pick' ? 'bg-white text-brand-700 shadow-sm dark:bg-gray-900 dark:text-brand-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'}`}
                                        >
                                            🎯 Pilih / Scan Frame
                                        </button>
                                    </div>

                                    {shipMode === 'all' && (
                                        <p className="mb-3 rounded-lg bg-brand-50 p-3 text-xs text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                                            Sistem mengecek dulu ketersediaan stok untuk <strong>seluruh frame</strong> (lookup &amp; matching),
                                            lalu mengirim semuanya sekaligus. Frame yang stoknya kurang <strong>dilewati</strong> dan dilaporkan — tidak menggagalkan yang lain.
                                        </p>
                                    )}

                                    {/* Lokasi tujuan (opsional — mengisi lokasi kosong) */}
                                    <div className="mb-4">
                                        <Label>Lokasi Tujuan (opsional)</Label>
                                        <SearchableSelect
                                            options={shoppingLocations.map((l: any) => ({ value: l.id, label: l.name }))}
                                            value={bulkLocationId}
                                            onChange={(v) => setBulkLocationId(v as string)}
                                            placeholder="Isi otomatis lokasi kosong..."
                                        />
                                        <p className="mt-1 text-xs text-gray-400">
                                            Shopping yang lokasinya kosong (dari import TAM) akan diisi lokasi ini sebelum dikirim.
                                        </p>
                                    </div>

                                    {/* Pratinjau kecocokan stok */}
                                    <div className="mb-4 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                        <div className="mb-2 flex items-center justify-between">
                                            <span className="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                Pratinjau stok {shipMode === 'all' ? '(semua frame draft)' : '(frame terpilih)'}
                                            </span>
                                            <button
                                                type="button"
                                                className="text-xs text-brand-600 hover:underline"
                                                onClick={() => void fetchPreview(shipMode, selectedIds)}
                                            >
                                                ⟳ hitung ulang
                                            </button>
                                        </div>

                                        {previewLoading ? (
                                            <p className="text-xs text-gray-400">Menghitung kecocokan stok...</p>
                                        ) : !preview?.ok ? (
                                            <p className="text-xs text-red-500">{preview?.message || 'Belum ada data.'}</p>
                                        ) : (
                                            <>
                                                <div className="grid grid-cols-3 gap-2 text-center">
                                                    <div className="rounded-lg bg-green-50 p-2 dark:bg-green-900/10">
                                                        <div className="text-lg font-bold text-green-700 dark:text-green-400">{preview.summary.ready}</div>
                                                        <div className="text-[10px] uppercase text-green-700 dark:text-green-400">Siap kirim</div>
                                                    </div>
                                                    <div className="rounded-lg bg-amber-50 p-2 dark:bg-amber-900/10">
                                                        <div className="text-lg font-bold text-amber-700 dark:text-amber-400">{preview.summary.blocked}</div>
                                                        <div className="text-[10px] uppercase text-amber-700 dark:text-amber-400">Dilewati</div>
                                                    </div>
                                                    <div className="rounded-lg bg-gray-50 p-2 dark:bg-gray-800">
                                                        <div className="text-lg font-bold text-gray-700 dark:text-gray-200">{preview.summary.quantity}</div>
                                                        <div className="text-[10px] uppercase text-gray-500">Total pcs</div>
                                                    </div>
                                                </div>

                                                <p className="mt-2 text-[11px] text-gray-500">
                                                    {preview.summary.items} item di {preview.summary.total} frame
                                                    {preview.summary.blocked > 0 ? ` · ${preview.summary.blocked_quantity} pcs ada di frame yang dilewati` : ''}
                                                </p>

                                                {preview.summary.blocked > 0 && (
                                                    <div className="mt-2">
                                                        <button
                                                            type="button"
                                                            className="text-xs font-medium text-amber-700 hover:underline dark:text-amber-400"
                                                            onClick={() => setShowBlocked((v) => !v)}
                                                        >
                                                            {showBlocked ? '▾' : '▸'} Lihat {preview.summary.blocked} frame yang dilewati &amp; alasannya
                                                        </button>
                                                        {showBlocked && (
                                                            <div className="mt-2 max-h-44 overflow-y-auto divide-y divide-gray-100 rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-700">
                                                                {(preview.blocked || []).map((b: any) => (
                                                                    <div key={b.id} className="px-3 py-2 text-xs">
                                                                        <span className="font-mono font-medium text-gray-700 dark:text-gray-200">{b.frame_number}</span>
                                                                        <span className="text-gray-400"> · {b.items} item / {b.quantity} pcs</span>
                                                                        <ul className="mt-1 list-disc pl-4 text-red-500">
                                                                            {(b.issues || []).map((issue: string, i: number) => <li key={i}>{issue}</li>)}
                                                                        </ul>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        )}
                                                    </div>
                                                )}
                                            </>
                                        )}
                                    </div>

                                    {/* Mode pilih/scan frame */}
                                    {shipMode === 'pick' && (
                                        <>
                                            <div className="mb-3">
                                                <Label>Pilih Frame (search / scan)</Label>
                                                <div className="flex gap-2">
                                                    <div className="flex-1">
                                                        <Input
                                                            type="text"
                                                            value={frameSearch}
                                                            onChange={(e) => setFrameSearch(e.target.value)}
                                                            placeholder="Cari frame number..."
                                                        />
                                                    </div>
                                                    <Button type="button" variant="outline" size="sm" onClick={() => setScannerOpen(true)} title="Scan barcode frame">
                                                        📷
                                                    </Button>
                                                </div>
                                            </div>

                                            {scanMsg && (
                                                <p className={`mb-2 text-xs ${scanMsg.type === 'ok' ? 'text-green-600' : 'text-red-500'}`}>
                                                    {scanMsg.text}
                                                </p>
                                            )}

                                            <div className="mb-3 max-h-40 divide-y divide-gray-100 overflow-y-auto rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-700">
                                                {framesLoading ? (
                                                    <p className="px-3 py-2 text-xs text-gray-400">Memuat frame...</p>
                                                ) : frames.length === 0 ? (
                                                    <p className="px-3 py-2 text-xs text-gray-400">
                                                        {frameSearch.trim() !== '' ? 'Tidak ada frame ditemukan' : 'Belum ada frame draft'}
                                                    </p>
                                                ) : (
                                                    <>
                                                        {frames.map((d) => (
                                                            <button
                                                                key={d.id}
                                                                type="button"
                                                                onClick={() => addSelected(d)}
                                                                disabled={selectedIds.includes(d.id)}
                                                                className={`flex w-full justify-between gap-2 px-3 py-2 text-left text-sm ${
                                                                    selectedIds.includes(d.id)
                                                                        ? 'cursor-not-allowed bg-gray-50 opacity-50 dark:bg-gray-800/60'
                                                                        : 'hover:bg-gray-50 dark:hover:bg-gray-800'
                                                                }`}
                                                            >
                                                                <span className="font-mono">{d.frame_number}</span>
                                                                <span className="text-xs text-gray-400">
                                                                    {selectedIds.includes(d.id)
                                                                        ? '✓ sudah discan'
                                                                        : (d.shopping_location?.name || '—')}
                                                                    {d.is_cripple ? ' ⚠️' : ''}
                                                                </span>
                                                            </button>
                                                        ))}
                                                        {framesHasMore && (
                                                            <p className="px-3 py-2 text-xs text-gray-400">
                                                                Menampilkan 30 frame pertama — ketik / scan untuk mempersempit.
                                                            </p>
                                                        )}
                                                    </>
                                                )}
                                            </div>

                                            <div className="mb-4">
                                                <Label>Terpilih ({selected.length})</Label>
                                                <div className="max-h-44 divide-y divide-gray-100 overflow-y-auto rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-700">
                                                    {selected.length === 0 ? (
                                                        <p className="px-3 py-3 text-xs text-gray-400">
                                                            Belum ada frame dipilih — cari di atas atau scan barcode frame.
                                                        </p>
                                                    ) : (
                                                        selected.map((d) => (
                                                            <div key={d.id} className="flex items-center justify-between gap-2 px-3 py-2">
                                                                <div className="min-w-0">
                                                                    <div className="truncate font-mono text-sm">{d.frame_number}</div>
                                                                    <div className="text-xs text-gray-400">
                                                                        {d.shopping_location?.name || 'lokasi kosong'}
                                                                        {d.is_cripple ? ' · ⚠️ cripple' : ''}
                                                                    </div>
                                                                </div>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => removeSelected(d.id)}
                                                                    className="shrink-0 text-sm text-red-400 hover:text-red-600"
                                                                >
                                                                    ✕
                                                                </button>
                                                            </div>
                                                        ))
                                                    )}
                                                </div>
                                            </div>
                                        </>
                                    )}

                                    <div className="flex flex-wrap justify-end gap-3">
                                        <Button variant="outline" onClick={() => setBulkShipOpen(false)}>Batal</Button>
                                        <Button
                                            onClick={handleBulkShip}
                                            disabled={
                                                submitting ||
                                                (shipMode === 'all'
                                                    ? !preview?.ok || (preview?.summary?.ready ?? 0) === 0
                                                    : selectedIds.length === 0)
                                            }
                                        >
                                            {submitting
                                                ? 'Mengirim...'
                                                : shipMode === 'all'
                                                    ? `🚀 Kirim Semua (${preview?.summary?.ready ?? 0} frame)`
                                                    : `Kirim ${selected.length} Shopping`}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}

            <QrScanner
                isOpen={scannerOpen}
                onClose={() => setScannerOpen(false)}
                onScan={handleScan}
                mode="barcode"
                feedback={scanMsg ? { message: scanMsg.text, type: scanMsg.type } : undefined}
            />
        </>
    );
}

Index.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
