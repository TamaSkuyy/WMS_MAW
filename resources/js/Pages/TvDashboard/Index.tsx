import React, { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';

interface VehicleModelInfo {
    brand: string;
    name: string;
    suffix: string | null;
}

interface MovementInfo {
    quantity: number;
    date: string;
}

interface TvItem {
    id: number;
    part_number: string;
    name: string;
    vehicle_model: VehicleModelInfo | null;
    total_stock: number;
    last_received: MovementInfo | null;
    last_shipped: MovementInfo | null;
}

interface Props {
    slides: TvItem[][];
}

const COLOR_HEALTHY = '#3B5BDB';
const COLOR_LOW = '#F2A93B';
const COLOR_OUT = '#B33F2E';

function stockColor(quantity: number): string {
    if (quantity <= 0) return COLOR_OUT;
    if (quantity < 5) return COLOR_LOW;
    return COLOR_HEALTHY;
}

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

function vehicleModelLabel(vm: VehicleModelInfo | null): string {
    if (!vm) return '—';
    return `${vm.brand} ${vm.name}${vm.suffix ? ' ' + vm.suffix : ''}`;
}

function useClock() {
    const [now, setNow] = useState(new Date());
    useEffect(() => {
        const interval = setInterval(() => setNow(new Date()), 1000);
        return () => clearInterval(interval);
    }, []);
    return now;
}

export default function Index({ slides }: Props) {
    const [currentSlideIndex, setCurrentSlideIndex] = useState(0);
    const clock = useClock();

    useEffect(() => {
        const interval = setInterval(() => {
            setCurrentSlideIndex((prev) => (slides.length === 0 ? 0 : (prev + 1) % slides.length));
        }, 8000);
        return () => clearInterval(interval);
    }, [slides.length]);

    useEffect(() => {
        setCurrentSlideIndex((prev) => Math.min(prev, Math.max(slides.length - 1, 0)));
    }, [slides.length]);

    useEffect(() => {
        const Echo = (window as any).Echo;
        if (!Echo) return;

        const channel = Echo.channel('warehouse.stock');
        channel.listen('.StockChanged', () => {
            router.reload({ only: ['slides'], preserveState: true, preserveScroll: true });
        });

        return () => {
            Echo.leave('warehouse.stock');
        };
    }, []);

    const currentItems = slides[currentSlideIndex] ?? [];

    return (
        <>
            <Head title="TV Dashboard" />
            <div className="min-h-screen bg-[#12151A] text-white p-10 flex flex-col font-outfit">
                <header className="flex items-start justify-between mb-10">
                    <div>
                        <div className="font-mono-tv text-xs tracking-[0.3em] text-white/40 uppercase mb-2">
                            Gudang Part Otomotif
                        </div>
                        <h1 className="font-oswald text-5xl font-semibold uppercase tracking-wide">
                            Papan Monitoring
                        </h1>
                    </div>
                    <div className="text-right">
                        <div className="font-mono-tv text-3xl tabular-nums text-white/90">
                            {clock.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                        </div>
                        <div className="font-mono-tv text-xs text-white/40 mt-1 uppercase tracking-wider">
                            {clock.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}
                        </div>
                    </div>
                </header>

                <div
                    key={currentSlideIndex}
                    className="tv-slide-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 lg:grid-rows-2 gap-6 flex-1"
                >
                    {currentItems.map((item) => {
                        const accent = stockColor(item.total_stock);
                        return (
                            <div
                                key={item.id}
                                className="rounded-lg overflow-hidden bg-[#1C222B] border border-white/10 flex flex-col"
                                style={{ borderLeft: `4px solid ${accent}` }}
                            >
                                <div className="bg-[#EDE6D3] text-[#12151A] px-4 py-2">
                                    <span className="font-mono-tv text-sm font-medium tracking-widest">
                                        {item.part_number}
                                    </span>
                                </div>
                                <div className="border-b border-dashed border-white/15" />

                                <div className="p-5 flex flex-col flex-1 justify-between">
                                    <div>
                                        <div className="font-oswald text-2xl font-semibold uppercase tracking-wide leading-tight">
                                            {item.name}
                                        </div>
                                        <div className="font-mono-tv text-xs text-white/40 mt-1.5">
                                            {vehicleModelLabel(item.vehicle_model)}
                                        </div>
                                    </div>

                                    <div className="mt-5">
                                        <div className="font-oswald text-4xl font-bold" style={{ color: accent }}>
                                            {item.total_stock}
                                            <span className="font-outfit text-base text-white/40 ml-2">pcs</span>
                                        </div>

                                        <div className="mt-4 space-y-1.5 font-mono-tv text-xs text-white/60">
                                            <div>
                                                <span className="text-white/40">↓ MASUK</span>{' '}
                                                {item.last_received
                                                    ? `${item.last_received.quantity} pcs — ${formatDate(item.last_received.date)}`
                                                    : 'Belum ada data'}
                                            </div>
                                            <div>
                                                <span className="text-white/40">↑ KELUAR</span>{' '}
                                                {item.last_shipped
                                                    ? `${item.last_shipped.quantity} pcs — ${formatDate(item.last_shipped.date)}`
                                                    : 'Belum ada data'}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>

                <footer className="mt-8 flex items-center justify-center gap-1.5">
                    {slides.map((_, i) => (
                        <span
                            key={i}
                            className="w-[3px] rounded-full transition-all duration-300"
                            style={{
                                height: i === currentSlideIndex ? '20px' : '10px',
                                backgroundColor: i === currentSlideIndex ? COLOR_LOW : 'rgba(255,255,255,0.15)',
                            }}
                        />
                    ))}
                </footer>
            </div>
        </>
    );
}
