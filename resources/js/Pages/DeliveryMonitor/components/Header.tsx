import React from 'react';
import { TvIcon, ArrowPathIcon } from '@heroicons/react/24/outline';
import DatePicker from '../../../Tailadmin/components/form/date-picker';
import { ThemeToggleButton } from '../../../Tailadmin/components/common/ThemeToggleButton';
import { Supplier } from '../types';
import { useClock } from '../utils/useClock';

interface HeaderProps {
    suppliers: Supplier[];
    selectedSupplierId: number | null;
    onSelectSupplier: (id: number) => void;
    selectedDate: string;
    onSelectDate: (date: string) => void;
    tvMode: boolean;
    onToggleTvMode: () => void;
    rotate: boolean;
    onToggleRotate: () => void;
    otifPercent: number;
}

function otifColorClass(percent: number): string {
    if (percent >= 95) return 'text-success-600 dark:text-success-400';
    if (percent >= 80) return 'text-warning-600 dark:text-warning-400';
    return 'text-error-600 dark:text-error-400';
}

export default function Header({
    suppliers,
    selectedSupplierId,
    onSelectSupplier,
    selectedDate,
    onSelectDate,
    tvMode,
    onToggleTvMode,
    rotate,
    onToggleRotate,
    otifPercent,
}: HeaderProps) {
    const clock = useClock();

    return (
        <header className="sticky top-0 z-30 border-b border-gray-200 dark:border-gray-800 bg-white/90 dark:bg-gray-950/90 backdrop-blur px-4 py-3">
            <div className="flex flex-wrap items-center justify-between gap-4">
                {/* Branding */}
                <div className="flex items-center gap-3">
                    <img
                        src="/images/maw/logo-icon.png"
                        alt="Mitra Adhi Wasana"
                        className="h-11 w-11 rounded-xl shrink-0"
                    />
                    <div>
                        <p className="text-[10px] font-semibold uppercase tracking-widest text-brand-600 dark:text-brand-400 mb-0.5">
                            Mitra Adhi Wasana
                        </p>
                        <h1 className="text-lg font-bold leading-tight text-gray-900 dark:text-white">
                            Warehouse Part Delivery Monitor
                        </h1>
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            Real-Time Supplier Cycle Tracking &amp; Fulfillment Dashboard
                        </p>
                    </div>
                </div>

                {/* Controls */}
                <div className="flex flex-wrap items-center gap-3">
                    <div>
                        <label className="block text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-1">
                            Supplier
                        </label>
                        <select
                            value={selectedSupplierId ?? ''}
                            onChange={(e) => onSelectSupplier(Number(e.target.value))}
                            className="h-9 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 text-sm text-gray-800 dark:text-white/90 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 focus:border-brand-300"
                        >
                            {suppliers.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.code} — {s.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="block text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-1">
                            Monitoring Date
                        </label>
                        <DatePicker
                            id="monitoring-date"
                            defaultDate={selectedDate}
                            onChange={(dates, dateStr) => onSelectDate(dateStr)}
                        />
                    </div>

                    <button
                        type="button"
                        onClick={onToggleTvMode}
                        className={`flex items-center gap-1.5 h-9 rounded-lg px-3 text-sm font-medium transition-colors ${
                            tvMode
                                ? 'bg-brand-500 text-white'
                                : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'
                        }`}
                    >
                        <TvIcon className="h-4 w-4" />
                        TV Mode {tvMode ? 'Active' : 'Off'}
                    </button>

                    <button
                        type="button"
                        onClick={onToggleRotate}
                        className={`flex items-center gap-1.5 h-9 rounded-lg px-3 text-sm font-medium transition-colors ${
                            rotate
                                ? 'bg-brand-500 text-white'
                                : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'
                        }`}
                    >
                        <ArrowPathIcon className={`h-4 w-4 ${rotate ? 'animate-spin' : ''}`} />
                        Rotate {rotate ? 'ON' : 'OFF'}
                    </button>

                    <ThemeToggleButton />

                    <div className="text-right">
                        <div className="flex items-center gap-1.5 justify-end text-[10px] font-semibold uppercase tracking-wider text-gray-400">
                            <span className="relative flex h-1.5 w-1.5">
                                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-success-400 opacity-75"></span>
                                <span className="relative inline-flex rounded-full h-1.5 w-1.5 bg-success-500"></span>
                            </span>
                            Live Sync
                        </div>
                        <div className="text-sm font-mono font-semibold tabular-nums text-gray-800 dark:text-white/90">
                            {clock.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                        </div>
                    </div>

                    <div className="rounded-xl border border-gray-200 dark:border-gray-800 px-4 py-1.5 text-right">
                        <div className="text-[10px] font-semibold uppercase tracking-wider text-gray-400">
                            Today&apos;s OTIF
                        </div>
                        <div className={`text-2xl font-bold leading-tight tabular-nums ${otifColorClass(otifPercent)}`}>
                            {otifPercent.toFixed(1)}%
                        </div>
                    </div>
                </div>
            </div>
        </header>
    );
}
