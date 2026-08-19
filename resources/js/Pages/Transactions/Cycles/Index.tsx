import React, { useState } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';
import EmptyState from '../../../Tailadmin/components/common/EmptyState';
import Pagination from '../../../Tailadmin/components/common/Pagination';
import ImportExportToolbar from '../../../Components/ImportExport/ImportExportToolbar';
import ImportModal from '../../../Components/ImportExport/ImportModal';

export default function Index({ cycles, suppliers, filters }: any) {
    const permissions = (usePage().props.auth as any)?.user?.permissions || [];
    const canCreate = permissions.includes('create cycles');
    const canEdit = permissions.includes('edit cycles');
    const canDelete = permissions.includes('delete cycles');
    const [importModalOpen, setImportModalOpen] = useState(false);
    const handleDelete = (id: number) => {
        if (confirm('Hapus cycle ini?')) {
            router.delete(route('cycles.destroy', id));
        }
    };

    const statusColors: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800',
        receiving: 'bg-yellow-100 text-yellow-800',
        completed: 'bg-green-100 text-green-800',
    };

    return (
        <>
            <Head title="Cycle" />
            <PageBreadcrumb pageTitle="Cycle" />
            <ComponentCard title="Daftar Cycle">
                <div className="mb-4 flex gap-3 flex-wrap items-end">
                    <div className="w-full sm:min-w-[200px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Supplier</label>
                        <SearchableSelect
                            options={[{ value: '', label: 'Semua Supplier' }, ...suppliers.map((s: any) => ({ value: s.id, label: s.name }))]}
                            value={filters?.supplier_id || ''}
                            onChange={(v) => router.get(route('cycles.index'), { ...filters, supplier_id: v as string }, { preserveState: true, replace: true })}
                        />
                    </div>
                    <div className="w-full sm:min-w-[160px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <SearchableSelect
                            options={[
                                { value: '', label: 'Semua Status' },
                                { value: 'draft', label: 'Draft' },
                                { value: 'receiving', label: 'Receiving' },
                                { value: 'completed', label: 'Completed' },
                            ]}
                            value={filters?.status || ''}
                            onChange={(v) => router.get(route('cycles.index'), { ...filters, status: v as string }, { preserveState: true, replace: true })}
                        />
                    </div>
                </div>
                <div className="mb-3 flex flex-wrap gap-2 items-center">
                    {canCreate && (
                        <Link href={route('cycles.create')}><Button>Tambah Cycle</Button></Link>
                    )}
                    {canCreate && (
                    <Link href={route('cycles.quick-receive.form')}>
                        <Button variant="outline">📷 Terima Cepat</Button>
                    </Link>
                    )}
                    {canCreate && (<ImportExportToolbar
                        importUrl={route('cycles.import')}
                        previewUrl={route('cycles.import.preview')}
                        exportUrl={route('cycles.export')}
                        onImportClick={() => setImportModalOpen(true)}/>
                    )}
                </div>
                {canCreate && (
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('cycles.import')}
                    previewUrl={route('cycles.import.preview')}
                    templateUrl={route('cycles.import-template')}
                    title="Cycle"
                    fields={[
                        { key: 'cycle_number', label: 'Cycle Number', required: true },
                        { key: 'supplier_name', label: 'Supplier', required: true },
                        { key: 'delivery_date', label: 'Delivery Date', required: true },
                        { key: 'part_number', label: 'Part Number', required: true },
                        { key: 'quantity', label: 'Quantity', required: true },
                        { key: 'notes', label: 'Notes', required: false },
                    ]}
                />
                )}
                {cycles.data.length === 0 ? (
                    <EmptyState
                        icon="📥"
                        title="Belum ada cycle"
                        message="Buat cycle penerimaan barang dari supplier."
                        actionLabel={canCreate ? "Tambah Cycle" : undefined}
                        actionRoute={canCreate ? route('cycles.create') : undefined}
                    />
                ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full">
                        <thead className="bg-[#F8F9FC] border-b border-[#E9ECEF]">
                            <tr>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Supplier</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Cycle #</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Status</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Item</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Diterima</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {cycles.data.map((cycle: any) => (
                                <tr key={cycle.id} className="border-b border-[#F1F3F5] hover:bg-[#F8F9FC] transition-all duration-150">
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-[#1A1D23]">{cycle.supplier?.name || '-'}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-[#1A1D23] font-mono">#{cycle.cycle_number}</td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <span className={`inline-block px-2 py-1 text-xs font-medium rounded-full ${statusColors[cycle.status] || ''}`}>
                                            {cycle.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-[#1A1D23]">{cycle.items_count || '-'}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-[13px] text-[#6C757D]">
                                        {cycle.received_at ? new Date(cycle.received_at).toLocaleString('id-ID', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit',second:'2-digit'}) : '-'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-[#1A1D23]">
                                        <div className="flex items-center gap-0.5">
                                            <Link
                                                href={route('cycles.show', cycle.id)}
                                                className="group relative inline-flex items-center justify-center w-11 h-11 rounded-lg text-[#3B5BDB] bg-[#EEF2FF] hover:bg-[#DBE4FF] transition-all duration-150"
                                                title="Lihat"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </Link>
                                            {cycle.status === 'draft' && (
                                                <>
                                                    {canEdit && (
                                                    <Link
                                                        href={route('cycles.edit', cycle.id)}
                                                        className="group relative inline-flex items-center justify-center w-11 h-11 rounded-lg text-[#F59F00] bg-[#FFF9DB] hover:bg-[#FFF3BF] transition-all duration-150"
                                                        title="Edit"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                            <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                        <span className="absolute -top-8 left-1/2 -translate-x-1/2 px-2 py-1 text-xs font-medium text-white bg-gray-800 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-50">
                                                            Edit
                                                        </span>
                                                    </Link>
                                                    )}
                                                    {canDelete && (
                                                    <button
                                                        onClick={() => handleDelete(cycle.id)}
                                                        className="group relative inline-flex items-center justify-center w-11 h-11 rounded-lg text-[#FA5252] bg-[#FFF5F5] hover:bg-[#FFE3E3] transition-all duration-150"
                                                        title="Hapus"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                            <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                        <span className="absolute -top-8 left-1/2 -translate-x-1/2 px-2 py-1 text-xs font-medium text-white bg-gray-800 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-50">
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
                {cycles.total > cycles.per_page && (
                    <Pagination
                        prevUrl={cycles.prev_page_url}
                        nextUrl={cycles.next_page_url}
                        currentPage={cycles.current_page}
                        lastPage={cycles.last_page}
                        from={cycles.from}
                        to={cycles.to}
                        total={cycles.total}
                    />
                )}
            </ComponentCard>
        </>
    );
}

Index.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
