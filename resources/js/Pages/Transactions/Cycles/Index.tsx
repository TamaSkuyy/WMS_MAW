import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';
import TableActions from '../../../Tailadmin/components/common/TableActions';
import EmptyState from '../../../Tailadmin/components/common/EmptyState';

export default function Index({ cycles, suppliers, filters }: any) {
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
                    <div className="min-w-[200px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Supplier</label>
                        <SearchableSelect
                            options={[{ value: '', label: 'Semua Supplier' }, ...suppliers.map((s: any) => ({ value: s.id, label: s.name }))]}
                            value={filters?.supplier_id || ''}
                            onChange={(v) => router.get(route('cycles.index'), { ...filters, supplier_id: v as string }, { preserveState: true, replace: true })}
                        />
                    </div>
                    <div className="min-w-[160px]">
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
                <div className="mb-3 flex gap-2">
                    <Link href={route('cycles.create')}><Button>Cycle Baru</Button></Link>
                    <Link href={route('cycles.quick-receive.form')}>
                        <Button variant="outline">📷 Terima Cepat</Button>
                    </Link>
                </div>
                {cycles.data.length === 0 ? (
                    <EmptyState
                        icon="📥"
                        title="Belum ada cycle"
                        message="Buat cycle penerimaan barang dari supplier."
                        actionLabel="Buat Cycle"
                        actionRoute={route('cycles.create')}
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
                                        {cycle.received_at ? new Date(cycle.received_at).toLocaleDateString('id-ID') : '-'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-[#1A1D23]">
                                        <div className="flex items-center gap-0.5">
                                            <TableActions
                                                viewRoute={route('cycles.show', cycle.id)}
                                            />
                                            {cycle.status === 'draft' && (
                                                <>
                                                    <Link
                                                        href={route('cycles.edit', cycle.id)}
                                                        className="group relative inline-flex items-center justify-center w-8 h-8 rounded-lg p-2 text-[#F59F00] bg-[#FFF9DB] hover:bg-[#FFF3BF] transition-all duration-150"
                                                        title="Edit"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                            <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                        <span className="absolute -top-8 left-1/2 -translate-x-1/2 px-2 py-1 text-xs font-medium text-white bg-gray-800 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-50">
                                                            Edit
                                                        </span>
                                                    </Link>
                                                    <button
                                                        onClick={() => handleDelete(cycle.id)}
                                                        className="group relative inline-flex items-center justify-center w-8 h-8 rounded-lg p-2 text-[#FA5252] bg-[#FFF5F5] hover:bg-[#FFE3E3] transition-all duration-150"
                                                        title="Hapus"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                            <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                        <span className="absolute -top-8 left-1/2 -translate-x-1/2 px-2 py-1 text-xs font-medium text-white bg-gray-800 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-50">
                                                            Hapus
                                                        </span>
                                                    </button>
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
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">Menampilkan {cycles.from || 0} sampai {cycles.to || 0} dari {cycles.total}</div>
                        <div className="flex gap-2">
                            {cycles.prev_page_url ? <Link href={cycles.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Sebelumnya</Link>
                                : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Sebelumnya</span>}
                            <span className="px-3 py-1 text-sm">Halaman {cycles.current_page} dari {cycles.last_page}</span>
                            {cycles.next_page_url ? <Link href={cycles.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Berikutnya</Link>
                                : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Berikutnya</span>}
                        </div>
                    </div>
                )}
            </ComponentCard>
        </>
    );
}

Index.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
