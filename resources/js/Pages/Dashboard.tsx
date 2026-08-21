import { useEffect } from 'react';
import AppLayout from '../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Package, Layers, AlertTriangle, TrendingUp, ArrowDownToLine, CheckCircle, Inbox, ShoppingCart, Clock, Warehouse } from 'lucide-react';
import MetricCard from './Dashboard/MetricCard';
import OperatorPerformance from './Dashboard/OperatorPerformance';

export default function Dashboard({ metrics, lowStockItems, overStockItems, pendingCycles, todayShoppings, recentCycles, avgDurationToday, rackAlerts, rackFullCount, rackNearFullCount, operatorPerformance }: any) {

    // Refresh performa operator tiap 15 detik (pola polling Delivery Monitor).
    // Skip saat tab tidak terlihat supaya tidak membebani server.
    useEffect(() => {
        const interval = setInterval(() => {
            if (document.hidden) return;
            router.reload({ only: ['operatorPerformance'], preserveState: true, preserveScroll: true });
        }, 15000);

        return () => clearInterval(interval);
    }, []);

    const fmtDuration = (s: number) => {
        if (!s || s < 0) return '-';
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        const sec = s % 60;
        if (h > 0) return `${h}j ${m}m`;
        if (m > 0) return `${m}m ${sec}d`;
        return `${sec}d`;
    };

    const EmptyState = ({ icon: Icon, text }: { icon: any; text: string }) => (
        <div className="flex items-center justify-center gap-2 py-6 text-sm text-gray-400 dark:text-gray-500">
            <Icon className="w-4 h-4 shrink-0" />
            <span>{text}</span>
        </div>
    );

    return (
        <div className="-m-4 md:-m-6 p-4 md:p-6 bg-slate-50 dark:bg-gray-950 min-h-screen">
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
                    className="group flex items-center gap-4 rounded-xl border border-brand-100 dark:border-brand-500/20 bg-white dark:bg-gray-900 shadow-sm p-5 hover:shadow-md hover:border-brand-300 dark:hover:border-brand-500/40 transition-all"
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
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5 md:gap-5 mb-6">
                <MetricCard
                    title="Total Produk"
                    value={metrics?.total_products?.toLocaleString() || '0'}
                    subtitle="Produk aktif"
                    icon={<Package className="w-5 h-5 text-brand-500" />}
                    accentBar="bg-brand-500"
                    iconBg="bg-brand-50 dark:bg-brand-500/20"
                />
                <MetricCard
                    title="Total Stok"
                    value={metrics?.total_stock?.toLocaleString() || '0'}
                    subtitle="Semua rak"
                    icon={<Layers className="w-5 h-5 text-blue-500" />}
                    accentBar="bg-blue-500"
                    iconBg="bg-blue-50 dark:bg-blue-500/20"
                />
                <MetricCard
                    title="Stok Menipis"
                    value={metrics?.low_stock_count?.toString() || '0'}
                    subtitle={metrics?.low_stock_count > 0 ? 'Butuh restock!' : 'Aman'}
                    alert={metrics?.low_stock_count > 0}
                    icon={<AlertTriangle className="w-5 h-5 text-error-500" />}
                    accentBar="bg-error-500"
                    iconBg="bg-error-50 dark:bg-error-500/20"
                />
                <MetricCard
                    title="Stok Berlebih"
                    value={metrics?.over_stock_count?.toString() || '0'}
                    subtitle={metrics?.over_stock_count > 0 ? 'Melebihi max stock' : 'Normal'}
                    alert={metrics?.over_stock_count > 0}
                    icon={<TrendingUp className="w-5 h-5 text-error-500" />}
                    accentBar="bg-error-500"
                    iconBg="bg-error-50 dark:bg-error-500/20"
                />
                <MetricCard
                    title="Cycle Pending"
                    value={metrics?.pending_cycles?.toString() || '0'}
                    subtitle={metrics?.completed_cycles_today > 0
                        ? `${metrics.completed_cycles_today} selesai hari ini${avgDurationToday ? ` · ⏱ ${fmtDuration(avgDurationToday)}` : ''}`
                        : 'Belum ada'}
                    icon={<ArrowDownToLine className="w-5 h-5 text-success-500" />}
                    accentBar="bg-success-500"
                    iconBg="bg-success-50 dark:bg-success-500/20"
                />
            </div>

            <div className="grid grid-cols-12 gap-4 md:gap-5">

                {/* Low Stock Alerts */}
                <div className="col-span-12 xl:col-span-6">
                    <div className="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 shadow-sm">
                        <div className="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                            <h3 className="text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
                                <AlertTriangle className="w-4 h-4 text-red-500" />
                                Stok Menipis
                            </h3>
                        </div>
                        {lowStockItems?.length > 0 ? (
                            <div className="p-4 space-y-2.5">
                                {lowStockItems.map((item: any, i: number) => (
                                    <div key={i} className="flex items-center justify-between px-3 py-2.5 bg-red-50 dark:bg-red-500/10 rounded-lg">
                                        <div>
                                            <p className="text-sm font-medium text-gray-800 dark:text-white/90">{item.name}</p>
                                            <p className="text-[11px] text-gray-500">{item.part_number} — Rak {item.rack} | Min: {item.min_stock}</p>
                                        </div>
                                        <span className="inline-flex items-center px-2.5 py-0.5 text-sm font-bold rounded-full bg-red-100 dark:bg-red-500/20 text-red-700 dark:text-red-400 tabular-nums">
                                            {item.quantity}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <EmptyState icon={CheckCircle} text="Semua stok aman" />
                        )}
                    </div>
                </div>

                {/* Overstock */}
                <div className="col-span-12 xl:col-span-6">
                    <div className="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 shadow-sm">
                        <div className="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                            <h3 className="text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
                                <TrendingUp className="w-4 h-4 text-orange-500" />
                                Stok Berlebih
                            </h3>
                        </div>
                        {overStockItems?.length > 0 ? (
                            <div className="p-4 space-y-2.5">
                                {overStockItems.map((item: any, i: number) => (
                                    <div key={i} className="flex items-center justify-between px-3 py-2.5 bg-orange-50 dark:bg-orange-500/10 rounded-lg">
                                        <div>
                                            <p className="text-sm font-medium text-gray-800 dark:text-white/90">{item.name}</p>
                                            <p className="text-[11px] text-gray-500">{item.part_number} — Rak {item.rack} | Max: {item.max_stock}</p>
                                        </div>
                                        <span className="inline-flex items-center px-2.5 py-0.5 text-sm font-bold rounded-full bg-orange-100 dark:bg-orange-500/20 text-orange-700 dark:text-orange-400 tabular-nums">
                                            {item.quantity}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <EmptyState icon={CheckCircle} text="Semua stok dalam batas" />
                        )}
                    </div>
                </div>

                {/* Pending Cycles */}
                <div className="col-span-12 xl:col-span-6">
                    <div className="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 shadow-sm">
                        <div className="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center">
                            <h3 className="text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
                                <Clock className="w-4 h-4 text-yellow-500" />
                                Cycle Menunggu
                            </h3>
                            <Link href={route('cycles.index')} className="text-xs font-medium text-brand-500 hover:text-brand-600">
                                Lihat Semua →
                            </Link>
                        </div>
                        {pendingCycles?.length > 0 ? (
                            <div className="p-4 space-y-2.5">
                                {pendingCycles.map((cycle: any, i: number) => (
                                    <Link
                                        key={i}
                                        href={route('cycles.show', cycle.id)}
                                        className="block px-3 py-2.5 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
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
                                            <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-yellow-100 dark:bg-yellow-500/20 text-yellow-700 dark:text-yellow-400">
                                                Draft
                                            </span>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <EmptyState icon={Inbox} text="Tidak ada cycle pending" />
                        )}
                    </div>
                </div>

                {/* Recent Completed Cycles */}
                <div className="col-span-12 xl:col-span-6">
                    <div className="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 shadow-sm">
                        <div className="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center">
                            <h3 className="text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
                                <CheckCircle className="w-4 h-4 text-green-500" />
                                Cycle Selesai
                            </h3>
                            <Link href={route('reports.supplier-performance')} className="text-xs font-medium text-brand-500 hover:text-brand-600">
                                Performa →
                            </Link>
                        </div>
                        {recentCycles?.length > 0 ? (
                            <div className="p-4 space-y-2.5">
                                {recentCycles.map((cycle: any, i: number) => (
                                    <Link
                                        key={i}
                                        href={route('cycles.show', cycle.id)}
                                        className="block px-3 py-2.5 bg-green-50 dark:bg-green-500/10 rounded-lg hover:bg-green-100 dark:hover:bg-green-500/20 transition-colors"
                                    >
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                                                    {cycle.supplier} — Cycle #{cycle.cycle_number}
                                                </p>
                                                <p className="text-xs text-gray-500">
                                                    {cycle.items_count} item · {cycle.created_at} → {cycle.received_at}
                                                </p>
                                            </div>
                                            <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 dark:bg-green-500/20 text-green-700 dark:text-green-400 tabular-nums">
                                                ⏱ {fmtDuration(cycle.duration_minutes * 60)}
                                            </span>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <EmptyState icon={Inbox} text="Belum ada cycle selesai" />
                        )}
                    </div>
                </div>

                {/* Rack Capacity */}
                <div className="col-span-12">
                    <div className="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 shadow-sm">
                        <div className="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center">
                            <h3 className="text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
                                <Warehouse className="w-4 h-4 text-brand-500" />
                                Kapasitas Rak
                            </h3>
                            <div className="flex items-center gap-3 text-xs">
                                {rackFullCount > 0 && (
                                    <span className="text-red-600 font-medium">⚠ {rackFullCount} penuh</span>
                                )}
                                {rackNearFullCount > 0 && (
                                    <span className="text-orange-500 font-medium">{rackNearFullCount} hampir penuh</span>
                                )}
                                <Link href={route('racks.index')} className="text-brand-500 hover:text-brand-600 font-medium">
                                    Semua Rak →
                                </Link>
                            </div>
                        </div>
                        {rackAlerts?.length > 0 ? (
                            <div className="p-4">
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                                    {rackAlerts.map((rack: any, i: number) => {
                                        const hasCap = rack.capacity !== null;
                                        const isOver = hasCap && rack.pct >= 100;
                                        const isNear = hasCap && rack.pct >= 80 && rack.pct < 100;
                                        const isSafe = hasCap && rack.pct < 80;
                                        const sisa = hasCap ? rack.capacity - rack.usage : null;
                                        const color = isOver
                                            ? { bg: 'bg-red-50 dark:bg-red-500/10', border: 'border-red-200 dark:border-red-800', badge: 'bg-red-100 text-red-700', bar: 'bg-red-500', text: 'text-red-600' }
                                            : isNear
                                            ? { bg: 'bg-orange-50 dark:bg-orange-500/10', border: 'border-orange-200 dark:border-orange-800', badge: 'bg-orange-100 text-orange-700', bar: 'bg-orange-400', text: 'text-orange-600' }
                                            : isSafe
                                            ? { bg: 'bg-green-50 dark:bg-green-500/10', border: 'border-green-200 dark:border-green-800', badge: 'bg-green-100 text-green-700', bar: 'bg-green-500', text: 'text-green-600' }
                                            : { bg: 'bg-gray-50 dark:bg-gray-800', border: 'border-gray-200 dark:border-gray-700', badge: 'bg-gray-100 text-gray-600', bar: 'bg-gray-300', text: 'text-gray-500' };
                                        return (
                                        <Link
                                            key={i}
                                            href={route('racks.show', rack.id)}
                                            className={`block p-4 rounded-lg hover:shadow-md transition-all ${color.bg} border ${color.border}`}
                                        >
                                            <div className="flex items-center justify-between mb-2">
                                                <div>
                                                    <p className="text-sm font-semibold text-gray-800 dark:text-white/90 font-mono">
                                                        {rack.code}
                                                    </p>
                                                    <p className="text-[11px] text-gray-500">{rack.zone}</p>
                                                </div>
                                                {hasCap ? (
                                                    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold ${color.badge}`}>
                                                        {rack.pct}%
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs text-gray-400 bg-gray-100 dark:bg-gray-700">
                                                        ∞
                                                    </span>
                                                )}
                                            </div>
                                            {hasCap ? (
                                                <>
                                                    <div className="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2.5 overflow-hidden mb-1">
                                                        <div
                                                            className={`h-full rounded-full transition-all ${color.bar}`}
                                                            style={{ width: `${Math.min(100, rack.pct)}%` }}
                                                        />
                                                    </div>
                                                    <div className="flex justify-between text-[11px]">
                                                        <span className="text-gray-500 tabular-nums">
                                                            {rack.usage.toLocaleString()} / {rack.capacity.toLocaleString()} unit
                                                        </span>
                                                        {!isOver && sisa !== null && (
                                                            <span className={`font-medium tabular-nums ${color.text}`}>
                                                                Sisa {sisa.toLocaleString()}
                                                            </span>
                                                        )}
                                                        {isOver && (
                                                            <span className="text-red-600 font-medium">⚠ Over!</span>
                                                        )}
                                                    </div>
                                                </>
                                            ) : (
                                                <p className="text-[11px] text-gray-400">
                                                    {rack.usage.toLocaleString()} unit — <span className="italic">tanpa batas</span>
                                                </p>
                                            )}
                                        </Link>
                                        );
                                    })}
                                </div>
                            </div>
                        ) : (
                            <EmptyState icon={Inbox} text="Belum ada data rak" />
                        )}
                    </div>
                </div>

                {/* Operator Performance */}
                <div className="col-span-12">
                    <OperatorPerformance operatorPerformance={operatorPerformance} />
                </div>

                {/* Shopping Today */}
                <div className="col-span-12">
                    <div className="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 shadow-sm">
                        <div className="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center">
                            <h3 className="text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
                                <ShoppingCart className="w-4 h-4 text-blue-500" />
                                Shopping Siap Kirim
                            </h3>
                            <Link href={route('shoppings.index')} className="text-xs font-medium text-brand-500 hover:text-brand-600">
                                Lihat Semua →
                            </Link>
                        </div>
                        {todayShoppings?.length > 0 ? (
                            <div className="p-4">
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                    {todayShoppings.map((shopping: any, i: number) => (
                                        <Link
                                            key={i}
                                            href={route('shoppings.show', shopping.id)}
                                            className="block p-3.5 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                        >
                                            <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                                                {shopping.shopping_location || '-'}
                                            </p>
                                            <p className="text-xs text-gray-500 mt-1">
                                                {shopping.items_count} item · {shopping.shopping_date}
                                            </p>
                                            <span className="inline-flex mt-2 items-center px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-400">
                                                {shopping.status}
                                            </span>
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            <EmptyState icon={Inbox} text="Tidak ada shopping pending" />
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

Dashboard.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
