import AppLayout from '../Tailadmin/layout/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { BoxCubeIcon, ArrowUpIcon, AlertIcon, ArrowDownIcon } from '../Tailadmin/icons';
import MetricCard from './Dashboard/MetricCard';

export default function Dashboard({ metrics, lowStockItems, pendingCycles, todayShoppings }: any) {
    return (
        <>
            <Head title="Dashboard - Mitra Adhi Wasana" />

            <div className="flex items-center gap-3 mb-6">
                <div className="w-1 h-8 rounded-full bg-brand-500"></div>
                <div>
                    <h1 className="text-xl font-semibold text-gray-800 dark:text-white/90">Dashboard</h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Ringkasan operasional gudang real-time
                    </p>
                </div>
            </div>

            {/* Quick Links */}
            <div className="mb-6">
                <Link
                    href={route('delivery-monitor')}
                    className="group flex items-center gap-4 rounded-2xl border border-brand-100 dark:border-brand-500/20 bg-gradient-to-r from-brand-50 to-white dark:from-brand-500/10 dark:to-gray-900 p-5 hover:shadow-md hover:border-brand-300 dark:hover:border-brand-500/40 transition-all"
                >
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-500 text-white shrink-0 group-hover:scale-110 transition-transform">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="h-6 w-6">
                            <path d="M3.375 4.5C2.339 4.5 1.5 5.34 1.5 6.375V13.5h12V6.375c0-1.036-.84-1.875-1.875-1.875h-8.25ZM13.5 15h-12v2.625c0 1.035.84 1.875 1.875 1.875h.375a3 3 0 1 1 6 0h3a.75.75 0 0 0 .75-.75V15Z" />
                            <path d="M8.25 19.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0ZM15.75 6.75a.75.75 0 0 0-.75.75v11.25c0 .087.015.17.042.248a3 3 0 0 1 5.958.464c.01.096.012.192 0 .288a3 3 0 0 1-5.958.464.75.75 0 0 0-.042-.247V10.5h-1.5a.75.75 0 0 1 0-1.5h1.5V7.5h-1.5a.75.75 0 0 1 0-1.5h1.5V4.5a.75.75 0 0 1 .75-.75h.75a3 3 0 0 1 5.916.498.747.747 0 0 1-.016.252 3 3 0 0 1-5.9.498.75.75 0 0 1-.75-.748V7.5h1.5a.75.75 0 0 1 0 1.5h-1.5v1.5h1.5a.75.75 0 0 1 0 1.5h-1.5v2.25c0 .414.336.75.75.75Z" />
                        </svg>
                    </div>
                    <div className="flex-1 min-w-0">
                        <p className="text-sm font-semibold text-brand-700 dark:text-brand-300">
                            Delivery Monitor
                        </p>
                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Pantau jadwal pengiriman supplier, status penerimaan, dan progress real-time
                        </p>
                    </div>
                    <span className="text-brand-400 group-hover:translate-x-1 transition-transform shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" className="h-5 w-5">
                            <path fillRule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clipRule="evenodd" />
                        </svg>
                    </span>
                </Link>
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
        </>
    );
}

Dashboard.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
