import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';

export default function Index({ stocks }: any) {
    return (
        <AppLayout>
            <Head title="Inventori Stok" />
            <PageBreadcrumb pageTitle="Inventori Stok" />
            <ComponentCard title="Stok Saat Ini">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Part Number</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rak</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zona</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            {stocks.data.map((stock: any) => (
                                <tr key={stock.id}>
                                    <td className="px-4 py-3 text-sm">{stock.product?.name}</td>
                                    <td className="px-4 py-3 text-sm font-mono">{stock.product?.part_number}</td>
                                    <td className="px-4 py-3 text-sm font-mono">{stock.rack?.code}</td>
                                    <td className="px-4 py-3 text-sm text-gray-500">{stock.rack?.zone}</td>
                                    <td className="px-4 py-3 text-sm font-medium">{stock.quantity}</td>
                                    <td className="px-4 py-3 text-sm text-gray-500">
                                        <Link href={route('suppliers.show', stock.product?.supplier_id)} className="text-brand-500 hover:text-brand-700">
                                            {stock.product?.supplier?.name || '-'}
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                            {stocks.data.length === 0 && (
                                <tr><td colSpan={6} className="px-4 py-8 text-center text-sm text-gray-500">Belum ada stok.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
                {stocks.total > stocks.per_page && (
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">Menampilkan {stocks.from || 0} sampai {stocks.to || 0} dari {stocks.total}</div>
                        <div className="flex gap-2">
                            {stocks.prev_page_url ? <Link href={stocks.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Sebelumnya</Link>
                                : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Sebelumnya</span>}
                            <span className="px-3 py-1 text-sm">Halaman {stocks.current_page} dari {stocks.last_page}</span>
                            {stocks.next_page_url ? <Link href={stocks.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Berikutnya</Link>
                                : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Berikutnya</span>}
                        </div>
                    </div>
                )}
            </ComponentCard>
        </AppLayout>
    );
}
