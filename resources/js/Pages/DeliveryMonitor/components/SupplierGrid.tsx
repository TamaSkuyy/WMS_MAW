import React, { useMemo, useState } from 'react';
import { Supplier, DeliveryCycle, SupplierStatus } from '../types';
import SupplierCard from './SupplierCard';

interface SupplierGridProps {
    suppliers: Supplier[];
    cycles: DeliveryCycle[];
    currentCycleNumber: number;
    selectedSupplierId: number | null;
    onSelectSupplier: (id: number) => void;
}

type FilterValue = 'all' | SupplierStatus;

const FILTER_TABS: { value: FilterValue; label: string; icon: string }[] = [
    { value: 'all', label: 'All', icon: '' },
    { value: 'live', label: 'LIVE', icon: '🚨' },
    { value: 'alert', label: 'Alert', icon: '⚠️' },
    { value: 'done', label: 'Done', icon: '✅' },
    { value: 'standby', label: 'Standby', icon: '⚪' },
];

export default function SupplierGrid({
    suppliers,
    cycles,
    currentCycleNumber,
    selectedSupplierId,
    onSelectSupplier,
}: SupplierGridProps) {
    const [search, setSearch] = useState('');
    const [activeFilter, setActiveFilter] = useState<FilterValue>('all');

    const cycleBySupplier = useMemo(() => {
        const map = new Map<number, DeliveryCycle>();
        for (const cycle of cycles) {
            if (cycle.cycleNumber === currentCycleNumber) {
                map.set(cycle.supplierId, cycle);
            }
        }
        return map;
    }, [cycles, currentCycleNumber]);

    const summary = useMemo(
        () => ({
            all: suppliers.length,
            live: suppliers.filter((s) => s.status === 'live').length,
            shortage: suppliers.filter((s) => s.status === 'alert').length,
            complete: suppliers.filter((s) => s.status === 'done').length,
        }),
        [suppliers]
    );

    const filteredSuppliers = useMemo(() => {
        const query = search.trim().toLowerCase();
        return suppliers.filter((s) => {
            const matchesFilter = activeFilter === 'all' || s.status === activeFilter;
            const matchesSearch =
                query === '' || s.code.toLowerCase().includes(query) || s.name.toLowerCase().includes(query);
            return matchesFilter && matchesSearch;
        });
    }, [suppliers, activeFilter, search]);

    return (
        <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
            <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Cari Supplier (Code atau Nama)..."
                className="w-full h-10 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 focus:border-brand-300 mb-3"
            />

            <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div className="flex flex-wrap gap-1.5">
                    {FILTER_TABS.map((tab) => (
                        <button
                            key={tab.value}
                            type="button"
                            onClick={() => setActiveFilter(tab.value)}
                            className={`flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors ${
                                activeFilter === tab.value
                                    ? 'bg-brand-500 text-white'
                                    : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'
                            }`}
                        >
                            {tab.icon && <span>{tab.icon}</span>}
                            {tab.label}
                        </button>
                    ))}
                </div>

                <div className="flex flex-wrap items-center gap-3 text-[11px] font-semibold text-gray-500 dark:text-gray-400">
                    <span>ALL: <span className="text-gray-800 dark:text-white">{summary.all}</span></span>
                    <span>LIVE: <span className="text-warning-600 dark:text-warning-400">{summary.live}</span></span>
                    <span>SHORTAGE: <span className="text-error-600 dark:text-error-400">{summary.shortage}</span></span>
                    <span>COMPLETE: <span className="text-success-600 dark:text-success-400">{summary.complete}</span></span>
                </div>
            </div>

            {filteredSuppliers.length === 0 ? (
                <div className="py-12 text-center text-sm text-gray-400">Tidak ada supplier yang cocok.</div>
            ) : (
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    {filteredSuppliers.map((supplier) => (
                        <SupplierCard
                            key={supplier.id}
                            supplier={supplier}
                            cycle={cycleBySupplier.get(supplier.id)}
                            currentCycleNumber={currentCycleNumber}
                            isSelected={supplier.id === selectedSupplierId}
                            onClick={() => onSelectSupplier(supplier.id)}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
