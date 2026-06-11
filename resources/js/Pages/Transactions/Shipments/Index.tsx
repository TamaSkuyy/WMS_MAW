import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';

export default function Index({ shipments, filters }: any) {
    const handleDelete = (id: number) => {
        if (confirm('Hapus shipment ini?')) {
            router.delete(route('shipments.destroy', id));
        }
    };

    const statusColors: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800',
        shipped: 'bg-blue-100 text-blue-800',
        completed: 'bg-green-100 text-green-800',
    };

    return (
        <AppLayout>
            <Head title="Shipment" />
            <PageBreadcrumb pageTitle="Shipment" />
            <ComponentCard title="Daftar Shipment">
                <div className="mb-4 flex gap-3 flex-wrap items-end">
                    <div className="min-w-[200px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Cari Mitra</label>
                        <Input
                            type="text"
                            defaultValue={filters?.search || ''}
                            placeholder="Nama mitra..."
                            onChange={(e) => router.get(route('shipments.index'), { ...filters, search: e.target.value }, { preserveState: true, replace: true })}
                        />
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
                            onChange={(v) => router.get(route('shipments.index'), { ...filters, status: v as string }, { preserveState: true, replace: true })}
                        />
                    </div>
                </div>
                <div className="mb-3">
                    <Link href={route('shipments.create')}><Button>Shipment Baru</Button></Link>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mitra / Nama Mitra</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal / Tanggal Kirim</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            {shipments.data.map((s: any) => (
                                <tr key={s.id}>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm">{s.partner_name}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm">{s.shipment_date}</td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <span className={`inline-block px-2 py-1 text-xs font-medium rounded-full ${statusColors[s.status]}`}>{s.status}</span>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm">{s.items_count}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                        <Link href={route('shipments.show', s.id)} className="text-brand-500 hover:text-brand-700 mr-2">Lihat</Link>
                                        {s.status === 'draft' && (
                                            <><Link href={route('shipments.edit', s.id)} className="text-brand-500 hover:text-brand-700 mr-2">Edit</Link>
                                            <button onClick={() => handleDelete(s.id)} className="text-red-500 hover:text-red-700">Hapus</button></>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {shipments.data.length === 0 && (
                                <tr><td colSpan={5} className="px-4 py-8 text-center text-sm text-gray-500">Tidak ada shipment.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
                {shipments.total > shipments.per_page && (
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">Menampilkan {shipments.from || 0} sampai {shipments.to || 0} dari {shipments.total}</div>
                        <div className="flex gap-2">
                            {shipments.prev_page_url ? (
                                <Link href={shipments.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Sebelumnya</Link>
                            ) : (
                                <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Sebelumnya</span>
                            )}
                            <span className="px-3 py-1 text-sm">Halaman {shipments.current_page} dari {shipments.last_page}</span>
                            {shipments.next_page_url ? (
                                <Link href={shipments.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Berikutnya</Link>
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
