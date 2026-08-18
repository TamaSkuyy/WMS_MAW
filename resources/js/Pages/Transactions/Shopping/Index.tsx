import React, { useState } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import ImportModal from '../../../Components/ImportExport/ImportModal';
import { Head, Link, router, usePage } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';
import TableActions from '../../../Tailadmin/components/common/TableActions';
import EmptyState from '../../../Tailadmin/components/common/EmptyState';
import Label from '../../../Tailadmin/components/form/Label';

export default function Index({ shoppings, filters, shoppingLocations = [] }: any) {
    const permissions = (usePage().props.auth as any)?.user?.permissions || [];
    const canCreate = permissions.includes('create shoppings');
    const canEdit = permissions.includes('edit shoppings');
    const canDelete = permissions.includes('delete shoppings');
    const [importModalOpen, setImportModalOpen] = useState(false);
    const [importLocationId, setImportLocationId] = useState('');

    const handleDelete = (id: number) => {
        if (confirm('Hapus shopping ini?')) {
            router.delete(route('shoppings.destroy', id));
        }
    };

    const statusColors: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800',
        shipped: 'bg-blue-100 text-blue-800',
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
                                    { value: 'completed', label: 'Completed' },
                                ]}
                                value={filters?.status || ''}
                                onChange={(v) => router.get(route('shoppings.index'), { ...filters, status: v as string }, { preserveState: true, replace: true })}
                            />
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
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
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">Menampilkan {shoppings.from || 0} sampai {shoppings.to || 0} dari {shoppings.total}</div>
                        <div className="flex gap-2">
                            {shoppings.prev_page_url ? (
                                <Link href={shoppings.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Sebelumnya</Link>
                            ) : (
                                <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Sebelumnya</span>
                            )}
                            <span className="px-3 py-1 text-sm">Halaman {shoppings.current_page} dari {shoppings.last_page}</span>
                            {shoppings.next_page_url ? (
                                <Link href={shoppings.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Berikutnya</Link>
                            ) : (
                                <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Berikutnya</span>
                            )}
                        </div>
                    </div>
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
                        { key: 'modify_date', label: 'Modify Date', required: false },
                    ]}
                    extraNode={
                        <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">Lokasi Tujuan *</label>
                            <SearchableSelect
                                options={shoppingLocations.map((l: any) => ({ value: l.id, label: l.name }))}
                                value={importLocationId}
                                onChange={(v) => setImportLocationId(v as string)}
                                placeholder="Pilih lokasi tujuan..."
                            />
                        </div>
                    }
                    extraParams={() => ({ shopping_location_id: importLocationId })}
                />
            )}
        </>
    );
}

Index.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
