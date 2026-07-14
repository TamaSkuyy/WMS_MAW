import React from 'react';
import AppLayout from '../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageBreadcrumb from '../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../Tailadmin/components/common/ComponentCard';
import Button from '../../Tailadmin/components/ui/button/Button';
import Input from '../../Tailadmin/components/form/input/InputField';
import SearchableSelect from '../../Tailadmin/components/form/select/SearchableSelect';
import EmptyState from '../../Tailadmin/components/common/EmptyState';
import MetricCard from '../Dashboard/MetricCard';
import { ListIcon, ArrowDownIcon, BoxCubeIcon } from '../../Tailadmin/icons';

export default function Receiving({ items, summary, filters, suppliers }: any) {
    const updateFilter = (key: string, value: string) => {
        router.get(route('reports.receiving'), { ...filters, [key]: value }, { preserveState: true, replace: true });
    };

    const exportUrl = (format: 'xlsx' | 'pdf') => {
        const params = new URLSearchParams({ ...filters, format }).toString();
        return `${route('reports.receiving.export')}?${params}`;
    };

    const statusColors: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800',
        receiving: 'bg-yellow-100 text-yellow-800',
        completed: 'bg-green-100 text-green-800',
    };

    return (
        <>
            <Head title="Receiving Report" />
            <PageBreadcrumb pageTitle="Receiving Report" />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
                <MetricCard
                    title="Total Transaksi"
                    value={summary.total_transactions.toLocaleString()}
                    icon={<ListIcon className="text-brand-500" />}
                    accentBar="bg-brand-500"
                    iconBg="bg-brand-50 dark:bg-brand-500/20"
                />
                <MetricCard
                    title="Total Qty Diterima"
                    value={summary.total_quantity.toLocaleString()}
                    icon={<ArrowDownIcon className="text-success-500" />}
                    accentBar="bg-success-500"
                    iconBg="bg-success-50 dark:bg-success-500/20"
                />
                <MetricCard
                    title="Produk Unik"
                    value={summary.unique_products.toLocaleString()}
                    icon={<BoxCubeIcon className="text-warning-500" />}
                    accentBar="bg-warning-500"
                    iconBg="bg-warning-50 dark:bg-warning-500/20"
                />
            </div>

            <ComponentCard title="Detail Transaksi Receiving">
                <div className="mb-4 flex gap-3 flex-wrap items-end">
                    <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">Dari Tanggal</label>
                        <Input type="date" defaultValue={filters?.date_from || ''} onChange={(e) => updateFilter('date_from', e.target.value)} />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">Sampai Tanggal</label>
                        <Input type="date" defaultValue={filters?.date_to || ''} onChange={(e) => updateFilter('date_to', e.target.value)} />
                    </div>
                    <div className="min-w-[200px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Supplier</label>
                        <SearchableSelect
                            options={[{ value: '', label: 'Semua' }, ...suppliers.map((s: any) => ({ value: s.id, label: s.name }))]}
                            value={filters?.supplier_id || ''}
                            onChange={(v) => updateFilter('supplier_id', v as string)}
                        />
                    </div>
                    <div className="min-w-[160px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <SearchableSelect
                            options={[
                                { value: '', label: 'Semua' },
                                { value: 'draft', label: 'Draft' },
                                { value: 'receiving', label: 'Receiving' },
                                { value: 'completed', label: 'Completed' },
                            ]}
                            value={filters?.status || ''}
                            onChange={(v) => updateFilter('status', v as string)}
                        />
                    </div>
                    <div className="flex gap-2">
                        <a href={exportUrl('xlsx')}><Button variant="outline" size="sm">Export Excel</Button></a>
                        <a href={exportUrl('pdf')}><Button variant="outline" size="sm">Export PDF</Button></a>
                    </div>
                </div>

                {items.data.length === 0 ? (
                    <EmptyState icon="📄" title="Tidak ada data" message="Tidak ada transaksi receiving yang cocok dengan filter ini." />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead className="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Cycle</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rak</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty Diterima</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                {items.data.map((item: any) => (
                                    <tr key={item.id}>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap">
                                            {item.cycle?.received_at ? new Date(item.cycle.received_at).toLocaleDateString('id-ID') : '-'}
                                        </td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap font-mono">{item.cycle?.cycle_number}</td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap">{item.cycle?.supplier?.name || '-'}</td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap">{item.product?.part_number} — {item.product?.name}</td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap">{item.rack?.code || '-'}</td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap">{item.received_quantity}</td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            <span className={`inline-block px-2 py-1 text-xs font-medium rounded-full ${statusColors[item.cycle?.status] || ''}`}>
                                                {item.cycle?.status}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {items.total > items.per_page && (
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">Menampilkan {items.from || 0} sampai {items.to || 0} dari {items.total}</div>
                        <div className="flex gap-2">
                            {items.prev_page_url ? (
                                <Link href={items.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Sebelumnya</Link>
                            ) : (
                                <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Sebelumnya</span>
                            )}
                            <span className="px-3 py-1 text-sm">Halaman {items.current_page} dari {items.last_page}</span>
                            {items.next_page_url ? (
                                <Link href={items.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Berikutnya</Link>
                            ) : (
                                <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Berikutnya</span>
                            )}
                        </div>
                    </div>
                )}
            </ComponentCard>
        </>
    );
}

Receiving.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
