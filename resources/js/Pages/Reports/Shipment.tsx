import React from 'react';
import AppLayout from '../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageBreadcrumb from '../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../Tailadmin/components/common/ComponentCard';
import Button from '../../Tailadmin/components/ui/button/Button';
import Input from '../../Tailadmin/components/form/input/InputField';
import SearchableSelect from '../../Tailadmin/components/form/select/SearchableSelect';
import EmptyState from '../../Tailadmin/components/common/EmptyState';

export default function Shipment({ items, summary, filters }: any) {
    const updateFilter = (key: string, value: string) => {
        router.get(route('reports.shipment'), { ...filters, [key]: value }, { preserveState: true, replace: true });
    };

    const exportUrl = (format: 'xlsx' | 'pdf') => {
        const params = new URLSearchParams({ ...filters, format }).toString();
        return `${route('reports.shipment.export')}?${params}`;
    };

    const statusColors: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800',
        shipped: 'bg-blue-100 text-blue-800',
        completed: 'bg-green-100 text-green-800',
    };

    return (
        <AppLayout>
            <Head title="Shipment Report" />
            <PageBreadcrumb pageTitle="Shipment Report" />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
                <ComponentCard title="Total Transaksi">
                    <div className="text-3xl font-semibold">{summary.total_transactions}</div>
                </ComponentCard>
                <ComponentCard title="Total Qty Dikirim">
                    <div className="text-3xl font-semibold">{summary.total_quantity}</div>
                </ComponentCard>
                <ComponentCard title="Produk Unik">
                    <div className="text-3xl font-semibold">{summary.unique_products}</div>
                </ComponentCard>
            </div>

            <ComponentCard title="Detail Transaksi Shipment">
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
                        <label className="block text-xs font-medium text-gray-500 mb-1">Cari Partner</label>
                        <Input type="text" defaultValue={filters?.partner || ''} placeholder="Nama partner..." onChange={(e) => updateFilter('partner', e.target.value)} />
                    </div>
                    <div className="min-w-[160px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <SearchableSelect
                            options={[
                                { value: '', label: 'Semua' },
                                { value: 'draft', label: 'Draft' },
                                { value: 'shipped', label: 'Dikirim' },
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
                    <EmptyState icon="📄" title="Tidak ada data" message="Tidak ada transaksi shipment yang cocok dengan filter ini." />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead className="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Partner</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rak</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                {items.data.map((item: any) => (
                                    <tr key={item.id}>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap">
                                            {item.shipment?.shipment_date ? new Date(item.shipment.shipment_date).toLocaleDateString('id-ID') : '-'}
                                        </td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap">{item.shipment?.partner_name}</td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap">{item.product?.part_number} — {item.product?.name}</td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap">{item.rack?.code}</td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap">{item.quantity}</td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            <span className={`inline-block px-2 py-1 text-xs font-medium rounded-full ${statusColors[item.shipment?.status] || ''}`}>
                                                {item.shipment?.status}
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
        </AppLayout>
    );
}
