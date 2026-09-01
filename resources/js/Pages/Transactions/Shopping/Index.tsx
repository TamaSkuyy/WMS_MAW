import React, { useState, useMemo } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import ImportModal from '../../../Components/ImportExport/ImportModal';
import QrScanner from '../../../Components/QrScanner';
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

interface DraftShopping {
    id: number;
    frame_number: string;
    shopping_location_id: number | null;
    is_cripple: boolean;
    shopping_location?: { id: number; name: string } | null;
}

export default function Index({ shoppings, filters, shoppingLocations = [], draftShoppings = [] }: any) {
    const permissions = (usePage().props.auth as any)?.user?.permissions || [];
    const canCreate = permissions.includes('create shoppings');
    const canEdit = permissions.includes('edit shoppings');
    const canDelete = permissions.includes('delete shoppings');
    const canShip = permissions.includes('ship shoppings');

    const [importModalOpen, setImportModalOpen] = useState(false);

    // ── Bulk Ship state ─────────────────────────────────────────────
    const [bulkShipOpen, setBulkShipOpen] = useState(false);
    const [bulkLocationId, setBulkLocationId] = useState('');
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [frameSearch, setFrameSearch] = useState('');
    const [scannerOpen, setScannerOpen] = useState(false);
    const [scanMsg, setScanMsg] = useState<{ type: 'ok' | 'error'; text: string } | null>(null);
    const [submitting, setSubmitting] = useState(false);

    const draftMap = useMemo(() => {
        const m = new Map<number, DraftShopping>();
        (draftShoppings as DraftShopping[]).forEach((d) => m.set(d.id, d));
        return m;
    }, [draftShoppings]);

    const filteredDrafts = useMemo(() => {
        const q = frameSearch.trim().toLowerCase();
        const list = draftShoppings as DraftShopping[];
        if (!q) return list;
        return list.filter((d) => d.frame_number.toLowerCase().includes(q));
    }, [draftShoppings, frameSearch]);

    const selectedDrafts = selectedIds.map((id) => draftMap.get(id)).filter(Boolean) as DraftShopping[];

    const addSelected = (id: number) => {
        setSelectedIds((prev) => (prev.includes(id) ? prev : [...prev, id]));
    };

    const removeSelected = (id: number) => {
        setSelectedIds((prev) => prev.filter((x) => x !== id));
    };

    const handleScan = (code: string) => {
        const match = (draftShoppings as DraftShopping[]).find(
            (d) => d.frame_number.toLowerCase() === code.toLowerCase()
        );
        if (!match) {
            setScanMsg({ type: 'error', text: `"${code}" tidak ditemukan di shopping draft` });
            return;
        }
        addSelected(match.id);
        setScanMsg({ type: 'ok', text: `✓ ${match.frame_number} ditambahkan` });
    };

    const handleBulkShip = () => {
        if (submitting || selectedIds.length === 0) return;
        const msg = `Kirim ${selectedIds.length} shopping? Lokasi kosong akan diisi "${bulkLocationId ? 'lokasi terpilih' : 'tetap kosong'}" lalu semua diproses.`;
        if (!confirm(msg)) return;
        setSubmitting(true);
        router.post(
            route('shoppings.bulk-ship'),
            {
                ids: selectedIds,
                shopping_location_id: bulkLocationId || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setBulkShipOpen(false);
                    setSelectedIds([]);
                    setFrameSearch('');
                },
                onFinish: () => setSubmitting(false),
            }
        );
    };

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
            <ComponentCard title="Daftar Shopping">
                <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="w-full sm:min-w-[200px]">
                            <Label>Cari Lokasi</Label>
                            <Input
                                type="text"
                                defaultValue={filters?.search || ''}
                                placeholder="Nama lokasi tujuan..."
                                onChange={(e) => router.get(route('shoppings.index'), { ...filters, search: e.target.value }, { preserveState: true, replace: true })}
                            />
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
                        {canShip && (
                            <Button variant="outline" onClick={() => setBulkShipOpen(true)}>
                                🚚 Kirim Massal
                            </Button>
                        )}
                        {canCreate && (
                            <Link href={route('shoppings.create')}><Button>Tambah Shopping</Button></Link>
                        )}
                        {canCreate && (
                            <Button variant="outline" onClick={() => setImportModalOpen(true)}>Import</Button>
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
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lokasi Tujuan</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal Kirim</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            {shoppings.data.map((s: any) => (
                                <tr key={s.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td className="px-4 py-3 whitespace-nowrap text-sm">{s.shopping_location?.name || '-'}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm">
                                        {s.shopping_date ? new Date(s.shopping_date).toLocaleString('id-ID', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '-'}
                                    </td>
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
                        nextUrl={shoppings.next_page_url}
                        currentPage={shoppings.current_page}
                        lastPage={shoppings.last_page}
                        from={shoppings.from}
                        to={shoppings.to}
                        total={shoppings.total}
                    />
                )}
            </ComponentCard>

            {canCreate && (
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('shoppings.import')}
                    previewUrl={route('shoppings.import.preview')}
                    templateUrl={route('shoppings.import-template')}
                    title="Shopping"
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
                <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50">
                    <div className="bg-white dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
                        <div className="p-6">
                            <div className="flex justify-between items-center mb-4">
                                <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">🚚 Kirim Massal</h2>
                                <button onClick={() => setBulkShipOpen(false)} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    ✕
                                </button>
                            </div>

                            {/* Pilih lokasi tujuan (opsional — mengisi lokasi kosong) */}
                            <div className="mb-4">
                                <Label>Lokasi Tujuan (opsional)</Label>
                                <SearchableSelect
                                    options={shoppingLocations.map((l: any) => ({ value: l.id, label: l.name }))}
                                    value={bulkLocationId}
                                    onChange={(v) => setBulkLocationId(v as string)}
                                    placeholder="Isi otomatis lokasi kosong..."
                                />
                                <p className="mt-1 text-xs text-gray-400">
                                    Shopping terpilih yang lokasinya kosong (dari import TAM) akan diisi lokasi ini sebelum dikirim.
                                </p>
                            </div>

                            {/* Cari frame + scan */}
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

                            {/* Hasil pencarian frame (klik untuk pilih) */}
                            {frameSearch.trim() !== '' && (
                                <div className="mb-3 border border-gray-200 dark:border-gray-700 rounded-lg max-h-40 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                                    {filteredDrafts.length === 0 ? (
                                        <p className="px-3 py-2 text-xs text-gray-400">Tidak ada frame ditemukan</p>
                                    ) : (
                                        filteredDrafts.slice(0, 20).map((d) => (
                                            <button
                                                key={d.id}
                                                type="button"
                                                onClick={() => addSelected(d.id)}
                                                disabled={selectedIds.includes(d.id)}
                                                className={`w-full text-left px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800 flex justify-between gap-2 ${
                                                    selectedIds.includes(d.id) ? 'opacity-50' : ''
                                                }`}
                                            >
                                                <span className="font-mono">{d.frame_number}</span>
                                                <span className="text-xs text-gray-400">
                                                    {d.shopping_location?.name || '—'}
                                                    {d.is_cripple ? ' ⚠️' : ''}
                                                </span>
                                            </button>
                                        ))
                                    )}
                                </div>
                            )}

                            {/* Daftar terpilih */}
                            <div className="mb-4">
                                <Label>Terpilih ({selectedDrafts.length})</Label>
                                <div className="border border-gray-200 dark:border-gray-700 rounded-lg divide-y divide-gray-100 dark:divide-gray-800 max-h-44 overflow-y-auto">
                                    {selectedDrafts.length === 0 ? (
                                        <p className="px-3 py-3 text-xs text-gray-400">
                                            Belum ada frame dipilih — cari di atas atau scan barcode frame.
                                        </p>
                                    ) : (
                                        selectedDrafts.map((d) => (
                                            <div key={d.id} className="flex justify-between items-center gap-2 px-3 py-2">
                                                <div className="min-w-0">
                                                    <div className="text-sm font-mono truncate">{d.frame_number}</div>
                                                    <div className="text-xs text-gray-400">
                                                        {d.shopping_location?.name || 'lokasi kosong'}
                                                        {d.is_cripple ? ' · ⚠️ cripple' : ''}
                                                    </div>
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => removeSelected(d.id)}
                                                    className="text-red-400 hover:text-red-600 text-sm shrink-0"
                                                >
                                                    ✕
                                                </button>
                                            </div>
                                        ))
                                    )}
                                </div>
                            </div>

                            <div className="flex justify-end gap-3">
                                <Button variant="outline" onClick={() => setBulkShipOpen(false)}>Batal</Button>
                                <Button onClick={handleBulkShip} disabled={selectedIds.length === 0 || submitting}>
                                    {submitting ? 'Mengirim...' : `Kirim ${selectedDrafts.length} Shopping`}
                                </Button>
                            </div>
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
