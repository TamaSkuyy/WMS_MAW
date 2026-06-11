import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';

export default function Show({ rack }: any) {
    return (
        <AppLayout>
            <Head title={`Rak ${rack.code}`} />
            <PageBreadcrumb pageTitle={`Rak: ${rack.code}`} />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div className="xl:col-span-1">
                    <ComponentCard title="Info Rak">
                        <dl className="space-y-3">
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Kode</dt>
                                <dd className="text-sm font-mono text-lg">{rack.code}</dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Zona</dt>
                                <dd className="text-sm">{rack.zone}</dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Total Produk</dt>
                                <dd className="text-sm">{rack.stocks?.length || 0}</dd>
                            </div>
                        </dl>
                        <div className="mt-4 flex gap-2">
                            <Link href={route('racks.edit', rack.id)}><Button>Edit</Button></Link>
                            <Link href={route('racks.index')}><Button variant="outline">Kembali</Button></Link>
                        </div>
                    </ComponentCard>
                </div>

                <div className="xl:col-span-2">
                    <ComponentCard title="Stok di Rak Ini">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Part Number</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                    {rack.stocks?.map((stock: any) => (
                                        <tr key={stock.id}>
                                            <td className="px-4 py-3 text-sm font-mono">{stock.product?.part_number}</td>
                                            <td className="px-4 py-3 text-sm">
                                                <Link href={route('products.show', stock.product?.id)} className="text-brand-500 hover:text-brand-700">
                                                    {stock.product?.name}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-500">{stock.product?.supplier?.name || '-'}</td>
                                            <td className="px-4 py-3 text-sm font-medium">{stock.quantity}</td>
                                        </tr>
                                    ))}
                                    {(!rack.stocks || rack.stocks.length === 0) && (
                                        <tr><td colSpan={4} className="px-4 py-8 text-center text-sm text-gray-500">Tidak ada stok di rak ini.</td></tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </ComponentCard>
                </div>
            </div>
        </AppLayout>
    );
}
