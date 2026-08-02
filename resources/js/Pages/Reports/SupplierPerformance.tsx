import React from 'react';
import AppLayout from '../../Tailadmin/layout/AppLayout';
import { Head, Link } from '@inertiajs/react';
import PageBreadcrumb from '../../Tailadmin/components/common/PageBreadCrumb';
import MetricCard from '../Dashboard/MetricCard';

export default function SupplierPerformance({ cycles, incompleteItems, perSupplier, metrics, suppliers, filters }: any) {
    const buildUrl = (overrides: Record<string, string>) => {
        const params = new URLSearchParams({ ...filters, ...overrides });
        Array.from(params.keys()).forEach(k => { if (!params.get(k)) params.delete(k); });
        return route('reports.supplier-performance') + '?' + params.toString();
    };

    return (
        <>
            <Head title="Performa Supplier" />
            <PageBreadcrumb pageTitle="Performa Supplier" />

            {/* Filters */}
            <div className="mb-6 flex flex-wrap gap-3 items-end bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
                <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Supplier</label>
                    <select
                        value={filters?.supplier_id || ''}
                        onChange={(e) => window.location.href = buildUrl({ supplier_id: e.target.value })}
                        className="border rounded px-3 py-1.5 text-sm dark:bg-gray-900 dark:border-gray-600"
                    >
                        <option value="">Semua</option>
                        {suppliers.map((s: any) => (
                            <option key={s.id} value={s.id}>{s.name}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Dari</label>
                    <input type="date" value={filters?.date_from || ''} onChange={(e) => window.location.href = buildUrl({ date_from: e.target.value })}
                        className="border rounded px-3 py-1.5 text-sm dark:bg-gray-900 dark:border-gray-600" />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Sampai</label>
                    <input type="date" value={filters?.date_to || ''} onChange={(e) => window.location.href = buildUrl({ date_to: e.target.value })}
                        className="border rounded px-3 py-1.5 text-sm dark:bg-gray-900 dark:border-gray-600" />
                </div>
                {(filters?.supplier_id || filters?.date_from || filters?.date_to) && (
                    <a href={route('reports.supplier-performance')} className="text-xs text-red-500 hover:text-red-700 pb-1">✕ Reset</a>
                )}
            </div>

            {/* Metrics */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
                <MetricCard title="Total Cycle" value={metrics?.total_cycles || 0} subtitle="Completed" accentBar="bg-blue-500" iconBg="bg-blue-50" />
                <MetricCard title="On-Time Rate" value={`${metrics?.on_time_rate || 0}%`} subtitle="Delivery tepat waktu" accentBar="bg-green-500" iconBg="bg-green-50" alert={metrics?.on_time_rate < 80} />
                <MetricCard title="Barang Kurang" value={metrics?.incomplete_count || 0} subtitle="Received < Quantity" accentBar="bg-orange-500" iconBg="bg-orange-50" alert={metrics?.incomplete_count > 0} />
            </div>

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-6">
                {/* Per Supplier Summary */}
                <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-6">
                    <h3 className="text-base font-medium text-gray-800 dark:text-white/90 mb-4">📊 Ringkasan per Supplier</h3>
                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                    <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Total Cycle</th>
                                    <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">On-Time</th>
                                    <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Rate</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                {perSupplier.map((s: any, i: number) => (
                                    <tr key={i}>
                                        <td className="px-3 py-2 text-sm">{s.supplier}</td>
                                        <td className="px-3 py-2 text-sm text-center">{s.total}</td>
                                        <td className="px-3 py-2 text-sm text-center">{s.on_time}</td>
                                        <td className="px-3 py-2 text-center">
                                            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${s.rate >= 80 ? 'bg-green-100 text-green-700' : s.rate >= 50 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'}`}>
                                                {s.rate}%
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Incomplete Items */}
                <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-6">
                    <h3 className="text-base font-medium text-gray-800 dark:text-white/90 mb-4">⚠️ Barang Tidak Lengkap</h3>
                    {incompleteItems.length === 0 ? (
                        <div className="text-center py-8 text-gray-400">✅ Semua barang diterima lengkap</div>
                    ) : (
                        <div className="overflow-x-auto max-h-96 overflow-y-auto">
                            <table className="min-w-full">
                                <thead className="bg-gray-50 dark:bg-gray-800 sticky top-0">
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Part #</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                        <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Qty</th>
                                        <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Terima</th>
                                        <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Kurang</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {incompleteItems.map((item: any, i: number) => (
                                        <tr key={i} className="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                            <td className="px-3 py-1.5 text-xs font-mono">
                                                <Link href={route('cycles.show', item.cycle_id)} className="text-brand-500 hover:underline">
                                                    {item.part_number}
                                                </Link>
                                                <div className="text-[11px] text-gray-400">{item.name}</div>
                                            </td>
                                            <td className="px-3 py-1.5 text-xs">{item.supplier}</td>
                                            <td className="px-3 py-1.5 text-xs text-center">{item.quantity}</td>
                                            <td className="px-3 py-1.5 text-xs text-center text-green-600">{item.received_quantity}</td>
                                            <td className="px-3 py-1.5 text-center">
                                                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                                    -{item.shortfall}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* Cycle History */}
            <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-6">
                <h3 className="text-base font-medium text-gray-800 dark:text-white/90 mb-4">📋 Riwayat Cycle</h3>
                <div className="overflow-x-auto">
                    <table className="min-w-full">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cycle #</th>
                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Jadwal</th>
                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Terima</th>
                                <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Item</th>
                                <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                            {cycles.data.map((c: any) => {
                                const totalItems = c.items?.length || 0;
                                const completeItems = c.items?.filter((i: any) => i.received_quantity >= i.quantity).length || 0;
                                const isFull = completeItems === totalItems && totalItems > 0;
                                const isOnTime = c.delivery_date && c.received_at && new Date(c.received_at) <= new Date(c.delivery_date);
                                return (
                                    <tr key={c.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td className="px-3 py-2 text-sm">
                                            <Link href={route('cycles.show', c.id)} className="text-brand-500 hover:underline font-mono">
                                                {c.cycle_number}
                                            </Link>
                                        </td>
                                        <td className="px-3 py-2 text-sm">{c.supplier?.name}</td>
                                        <td className="px-3 py-2 text-xs">{c.delivery_date || '-'}</td>
                                        <td className="px-3 py-2 text-xs">{c.received_at ? new Date(c.received_at).toLocaleString('id-ID', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit',second:'2-digit'}) : '-'}</td>
                                        <td className="px-3 py-2 text-center text-xs">{completeItems}/{totalItems}</td>
                                        <td className="px-3 py-2 text-center">
                                            {isFull ? (
                                                <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${isOnTime ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}`}>
                                                    {isOnTime ? '✓ Tepat' : '⚠ Terlambat'}
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">✕ Kurang</span>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
                {cycles.total > cycles.per_page && (
                    <div className="mt-4 flex justify-between text-sm">
                        <span className="text-gray-500">{cycles.from}-{cycles.to} dari {cycles.total}</span>
                        <div className="flex gap-2">
                            {cycles.prev_page_url && <Link href={cycles.prev_page_url} className="text-brand-500">← Sebelumnya</Link>}
                            {cycles.next_page_url && <Link href={cycles.next_page_url} className="text-brand-500">Berikutnya →</Link>}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

SupplierPerformance.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
