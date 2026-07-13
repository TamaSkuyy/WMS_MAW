import AppLayout from '../Tailadmin/layout/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { BoxCubeIcon, ArrowUpIcon, AlertIcon, ArrowDownIcon } from '../Tailadmin/icons';
import MetricCard from './Dashboard/MetricCard';

export default function Dashboard({ metrics, lowStockItems, pendingCycles, todayShoppings }: any) {
    return (
        <AppLayout>
            <Head title="Dashboard - MAW Warehouse System" />

            <div className="flex items-center gap-3 mb-6">
                <div className="w-1 h-8 rounded-full bg-brand-500"></div>
                <div>
                    <h1 className="text-xl font-semibold text-gray-800 dark:text-white/90">Dashboard</h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Ringkasan operasional gudang real-time
                    </p>
                </div>
            </div>

            {/* Metric Cards */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 md:gap-6 mb-6">
                <MetricCard
                    title="Total Produk"
                    value={metrics?.total_products?.toLocaleString() || '0'}
                    subtitle="Produk aktif"
                    icon={<BoxCubeIcon className="text-brand-500" />}
                    accentBar="bg-brand-500"
                    iconBg="bg-brand-50 dark:bg-brand-500/20"
                />
                <MetricCard
                    title="Total Stok"
                    value={metrics?.total_stock?.toLocaleString() || '0'}
                    subtitle="Semua rak"
                    icon={<BoxCubeIcon className="text-blue-500" />}
                    accentBar="bg-blue-500"
                    iconBg="bg-blue-50 dark:bg-blue-500/20"
                />
                <MetricCard
                    title="Stok Menipis"
                    value={metrics?.low_stock_count?.toString() || '0'}
                    subtitle={metrics?.low_stock_count > 0 ? 'Butuh restock!' : 'Aman'}
                    alert={metrics?.low_stock_count > 0}
                    icon={<AlertIcon className="text-error-500" />}
                    accentBar="bg-error-500"
                    iconBg="bg-error-50 dark:bg-error-500/20"
                />
                <MetricCard
                    title="Cycle Pending"
                    value={metrics?.pending_cycles?.toString() || '0'}
                    subtitle={metrics?.completed_cycles_today > 0 ? `${metrics.completed_cycles_today} selesai hari ini` : 'Belum ada'}
                    icon={<ArrowDownIcon className="text-success-500" />}
                    accentBar="bg-success-500"
                    iconBg="bg-success-50 dark:bg-success-500/20"
                />
            </div>

            <div className="grid grid-cols-12 gap-4 md:gap-6">
                {/* Low Stock Alerts */}
                <div className="col-span-12 xl:col-span-6">
                    <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                        <div className="px-6 py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 className="text-base font-medium text-gray-800 dark:text-white/90">
                                ⚠️ Stok Menipis
                            </h3>
                        </div>
                        <div className="p-6">
                            {lowStockItems?.length > 0 ? (
                                <div className="space-y-3">
                                    {lowStockItems.map((item: any, i: number) => (
                                        <div key={i} className="flex items-center justify-between p-3 bg-red-50 dark:bg-red-500/10 rounded-lg">
                                            <div>
                                                <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                                                    {item.name}
                                                </p>
                                                <p className="text-xs text-gray-500">
                                                    {item.part_number} — Rak {item.rack}
                                                </p>
                                            </div>
                                            <span className="inline-flex items-center px-3 py-1 text-sm font-bold rounded-full bg-red-100 dark:bg-red-500/20 text-red-700 dark:text-red-400">
                                                {item.quantity}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-8">
                                    <div className="text-3xl mb-2">✅</div>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Semua stok aman</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Pending Cycles */}
                <div className="col-span-12 xl:col-span-6">
                    <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                        <div className="px-6 py-5 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center">
                            <h3 className="text-base font-medium text-gray-800 dark:text-white/90">
                                📥 Cycle Menunggu
                            </h3>
                            <Link href={route('cycles.index')} className="text-sm text-brand-500 hover:text-brand-600">
                                Lihat Semua →
                            </Link>
                        </div>
                        <div className="p-6">
                            {pendingCycles?.length > 0 ? (
                                <div className="space-y-3">
                                    {pendingCycles.map((cycle: any, i: number) => (
                                        <Link
                                            key={i}
                                            href={route('cycles.show', cycle.id)}
                                            className="block p-3 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                        >
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                                                        {cycle.supplier} — Cycle #{cycle.cycle_number}
                                                    </p>
                                                    <p className="text-xs text-gray-500">
                                                        {cycle.items_count} item · {cycle.created_at}
                                                    </p>
                                                </div>
                                                <span className="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 dark:bg-yellow-500/20 text-yellow-700 dark:text-yellow-400">
                                                    Draft
                                                </span>
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-8">
                                    <div className="text-3xl mb-2">📭</div>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Tidak ada cycle pending</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Shopping Today */}
                <div className="col-span-12">
                    <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                        <div className="px-6 py-5 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center">
                            <h3 className="text-base font-medium text-gray-800 dark:text-white/90">
                                📤 Shopping Siap Kirim
                            </h3>
                            <Link href={route('shoppings.index')} className="text-sm text-brand-500 hover:text-brand-600">
                                Lihat Semua →
                            </Link>
                        </div>
                        <div className="p-6">
                            {todayShoppings?.length > 0 ? (
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    {todayShoppings.map((shopping: any, i: number) => (
                                        <Link
                                            key={i}
                                            href={route('shoppings.show', shopping.id)}
                                            className="block p-4 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                        >
                                            <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                                                {shopping.partner_name}
                                            </p>
                                            <p className="text-xs text-gray-500 mt-1">
                                                {shopping.items_count} item · {shopping.shopping_date}
                                            </p>
                                            <span className="inline-flex mt-2 items-center px-2 py-1 text-xs font-medium rounded-full bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-400">
                                                {shopping.status}
                                            </span>
                                        </Link>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-8">
                                    <div className="text-3xl mb-2">📭</div>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Tidak ada shopping pending</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
