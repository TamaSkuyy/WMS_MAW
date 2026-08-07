import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { PencilIcon, ArrowLeftIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';

export default function Show({ rack }: any) {
    const permissions = (usePage().props.auth as any)?.user?.permissions || [];
    const canEdit = permissions.includes('edit racks');
    const totalQty = rack.stocks?.reduce((sum: number, s: any) => sum + (s.quantity || 0), 0) || 0;
    const cap = rack.capacity;
    const pct = cap ? Math.min(100, Math.round((totalQty / cap) * 100)) : null;
    const isOver = cap && totalQty > cap;
    const isNearFull = cap && pct !== null && pct >= 80;

    return (
        <>
            <Head title={`Rak ${rack.code}`} />
            <PageBreadcrumb pageTitle={`Detail: ${rack.code}`} />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div className="xl:col-span-1">
                    <ComponentCard title="Info Rak" desc="Detail data rak">
                        <dl className="space-y-4">
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Kode</dt>
                                <dd className="text-sm text-[#1A1D23] font-mono">{rack.code}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Zona</dt>
                                <dd className="text-sm text-[#1A1D23]">{rack.zone}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Kapasitas</dt>
                                <dd className="text-sm text-[#1A1D23]">
                                    {cap ? (
                                        <span>{totalQty} / {cap} unit {isOver ? '⚠️' : ''}</span>
                                    ) : (
                                        <span>{totalQty} unit (tanpa batas)</span>
                                    )}
                                </dd>
                            </div>
                            {cap && (
                                <div>
                                    <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Terpakai</dt>
                                    <dd>
                                        <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                                            <div className={`h-full rounded-full transition-all ${isOver ? 'bg-red-500' : isNearFull ? 'bg-orange-400' : 'bg-green-500'}`} style={{ width: `${pct}%` }} />
                                        </div>
                                        <p className="text-xs text-gray-500 mt-1">{pct}% terpakai</p>
                                    </dd>
                                </div>
                            )}
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Total Produk</dt>
                                <dd className="text-sm text-[#1A1D23]">{rack.stocks?.length || 0} jenis</dd>
                            </div>
                        </dl>
                        <div className="mt-6 flex gap-2 pt-4 border-t border-[#F1F3F5]">
                            {canEdit && (
                                <Link href={route('racks.edit', rack.id)}>
                                    <Button icon={<PencilIcon className="w-4 h-4" />} size="sm">Edit</Button>
                                </Link>
                            )}
                            <Link href={route('racks.index')}>
                                <Button variant="outline" size="sm">Kembali</Button>
                            </Link>
                        </div>
                    </ComponentCard>
                </div>

                <div className="xl:col-span-2">
                    <ComponentCard title="Stok di Rak Ini" desc="Daftar produk yang disimpan di rak ini">
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
        </>
    );
}

Show.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
