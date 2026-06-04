import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';

export default function Index({ cycles, suppliers, filters }: any) {
    const handleDelete = (id: number) => {
        if (confirm('Delete this cycle?')) {
            router.delete(route('cycles.destroy', id));
        }
    };

    const statusColors: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800',
        receiving: 'bg-yellow-100 text-yellow-800',
        completed: 'bg-green-100 text-green-800',
    };

    return (
        <AppLayout>
            <Head title="Cycles" />
            <PageBreadcrumb pageTitle="Cycles" />
            <ComponentCard title="Cycle List">
                <div className="mb-4 flex gap-3 flex-wrap items-end">
                    <div className="min-w-[200px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Supplier</label>
                        <SearchableSelect
                            options={[{ value: '', label: 'All Suppliers' }, ...suppliers.map((s: any) => ({ value: s.id, label: s.name }))]}
                            value={filters?.supplier_id || ''}
                            onChange={(v) => router.get(route('cycles.index'), { ...filters, supplier_id: v as string }, { preserveState: true, replace: true })}
                        />
                    </div>
                    <div className="min-w-[160px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <SearchableSelect
                            options={[
                                { value: '', label: 'All Status' },
                                { value: 'draft', label: 'Draft' },
                                { value: 'receiving', label: 'Receiving' },
                                { value: 'completed', label: 'Completed' },
                            ]}
                            value={filters?.status || ''}
                            onChange={(v) => router.get(route('cycles.index'), { ...filters, status: v as string }, { preserveState: true, replace: true })}
                        />
                    </div>
                </div>
                <div className="mb-3">
                    <Link href={route('cycles.create')}><Button>New Cycle</Button></Link>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cycle #</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Items</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Received</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            {cycles.data.map((cycle: any) => (
                                <tr key={cycle.id}>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm">{cycle.supplier?.name || '-'}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm font-mono">#{cycle.cycle_number}</td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <span className={`inline-block px-2 py-1 text-xs font-medium rounded-full ${statusColors[cycle.status] || ''}`}>
                                            {cycle.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm">{cycle.items_count || '-'}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        {cycle.received_at ? new Date(cycle.received_at).toLocaleDateString('id-ID') : '-'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                        <Link href={route('cycles.show', cycle.id)} className="text-brand-500 hover:text-brand-700 mr-2">View</Link>
                                        {cycle.status === 'draft' && (
                                            <><Link href={route('cycles.edit', cycle.id)} className="text-brand-500 hover:text-brand-700 mr-2">Edit</Link>
                                            <button onClick={() => handleDelete(cycle.id)} className="text-red-500 hover:text-red-700">Delete</button></>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {cycles.data.length === 0 && (
                                <tr><td colSpan={6} className="px-4 py-8 text-center text-sm text-gray-500">No cycles.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
                {cycles.total > cycles.per_page && (
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">Showing {cycles.from || 0} to {cycles.to || 0} of {cycles.total}</div>
                        <div className="flex gap-2">
                            {cycles.prev_page_url ? <Link href={cycles.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Prev</Link>
                                : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Prev</span>}
                            <span className="px-3 py-1 text-sm">Page {cycles.current_page} of {cycles.last_page}</span>
                            {cycles.next_page_url ? <Link href={cycles.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Next</Link>
                                : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Next</span>}
                        </div>
                    </div>
                )}
            </ComponentCard>
        </AppLayout>
    );
}
