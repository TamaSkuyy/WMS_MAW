import React, { useState } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { PencilIcon, ArrowLeftIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';

export default function Show({ shopping }: any) {
    const [submitting, setSubmitting] = useState(false);

    const statusColors: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800',
        shipped: 'bg-blue-100 text-blue-800',
        completed: 'bg-green-100 text-green-800',
    };

    const handleShip = () => {
        if (submitting) return;
        if (confirm('Proses pengiriman ini? Stok akan dikurangi.')) {
            setSubmitting(true);
            router.post(route('shoppings.ship', shopping.id), {}, {
                onFinish: () => setSubmitting(false),
            });
        }
    };

    return (
        <AppLayout>
            <Head title={`Pengiriman ke ${shopping.partner_name}`} />
            <PageBreadcrumb pageTitle={`Detail: ${shopping.partner_name}`} />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div className="xl:col-span-1">
                    <ComponentCard title="Info Shopping" desc="Detail pengiriman barang">
                        <dl className="space-y-4">
                            <div><dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Mitra</dt><dd className="text-sm text-[#1A1D23]">{shopping.partner_name}</dd></div>
                            <div><dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Tanggal</dt><dd className="text-sm text-[#1A1D23]">{shopping.shopping_date}</dd></div>
                            <div><dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Status</dt><dd><span className={`inline-block px-2 py-1 text-xs font-medium rounded-full ${statusColors[shopping.status]}`}>{shopping.status}</span></dd></div>
                            {shopping.notes && <div><dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Catatan</dt><dd className="text-sm text-[#1A1D23]">{shopping.notes}</dd></div>}
                        </dl>
                        <div className="mt-6 flex gap-2 pt-4 border-t border-[#F1F3F5]">
                            {shopping.status === 'draft' && (
                                <><Link href={route('shoppings.edit', shopping.id)}><Button icon={<PencilIcon className="w-4 h-4" />} size="sm">Edit</Button></Link>
                                <Button variant="outline" size="sm" onClick={handleShip} disabled={submitting}>
                                    {submitting ? 'Memproses...' : 'Kirim Sekarang'}
                                </Button></>
                            )}
                            <Link href={route('shoppings.index')}><Button variant="outline" size="sm">Kembali</Button></Link>
                        </div>
                    </ComponentCard>
                </div>
                <div className="xl:col-span-2">
                    <ComponentCard title="Items" desc="Daftar produk dalam pengiriman">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Part #</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Model</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rak</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                    {shopping.items.map((item: any) => (
                                        <tr key={item.id}>
                                            <td className="px-3 py-2 text-sm font-mono">{item.product?.part_number}</td>
                                            <td className="px-3 py-2 text-sm">{item.product?.name}</td>
                                            <td className="px-3 py-2 text-sm text-gray-500">{item.product?.vehicle_model?.name || '-'}</td>
                                            <td className="px-3 py-2 text-sm font-mono">{item.rack?.code}</td>
                                            <td className="px-3 py-2 text-sm font-medium">{item.quantity}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </ComponentCard>
                </div>
            </div>
        </AppLayout>
    );
}
