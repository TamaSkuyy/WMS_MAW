import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import EmptyState from '../../../Tailadmin/components/common/EmptyState';

export default function Index({ stocks }: any) {
    return (
        <>
            <Head title="Inventori Stok" />
            <PageBreadcrumb pageTitle="Inventori Stok" />
            <ComponentCard title="Stok Saat Ini">
                {stocks.data.length === 0 ? (
                    <EmptyState
                        icon="📊"
                        title="Belum ada stok"
                        message="Stok akan muncul setelah cycle diterima."
                    />
                ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full">
                        <thead className="bg-[#F8F9FC] border-b border-[#E9ECEF]">
                            <tr>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Produk</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Part Number</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Rak</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Zona</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider w-24">Qty</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Supplier</th>
                            </tr>
                        </thead>
                        <tbody>
                            {stocks.data.map((stock: any) => {
                                const isRelay = !stock.rack;
                                return (
                                <tr key={stock.id} className={`border-b border-[#F1F3F5] hover:bg-[#F8F9FC] transition-all duration-150 ${isRelay ? 'bg-amber-50 dark:bg-amber-900/10' : ''}`}>
                                    <td className="px-4 py-3 text-sm text-[#1A1D23]">
                                        {stock.product?.name}
                                        {isRelay && (
                                            <span className="ml-2 inline-flex items-center px-1.5 py-0.5 text-[10px] font-bold rounded bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                                ⚠ RELAY
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-[#1A1D23] font-mono">{stock.product?.part_number}</td>
                                    <td className="px-4 py-3 text-sm font-mono">
                                        {stock.rack ? (
                                            <span className="text-[#1A1D23]">{stock.rack.code}</span>
                                        ) : (
                                            <span className="text-amber-600 dark:text-amber-400 font-medium">— Relay / Overflow</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-[13px] text-[#6C757D]">{stock.rack?.zone || '-'}</td>
                                    <td className="px-4 py-3 text-sm text-[#1A1D23] font-medium">{stock.quantity}</td>
                                    <td className="px-4 py-3 text-[13px] text-[#6C757D]">
                                        <Link href={route('suppliers.show', stock.product?.supplier_id)} className="text-brand-500 hover:text-brand-700">
                                            {stock.product?.supplier?.name || '-'}
                                        </Link>
                                    </td>
                                </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
                )}
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
        </>
    );
}

Index.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
