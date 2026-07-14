import React from 'react';
import { Supplier, DeliveryCycle } from '../types';
import { getStatusColor } from '../utils/statusColor';

interface SupplierCardProps {
    supplier: Supplier;
    cycle: DeliveryCycle | undefined;
    currentCycleNumber: number;
    isSelected: boolean;
    onClick: () => void;
}

export default function SupplierCard({ supplier, cycle, currentCycleNumber, isSelected, onClick }: SupplierCardProps) {
    const colors = getStatusColor(supplier.status);
    const hasSchedule = cycle !== undefined && cycle.planQty > 0;
    const completionPercent = hasSchedule ? Math.round((cycle!.actualQty / cycle!.planQty) * 100) : 0;
    const barWidth = Math.min(completionPercent, 100);

    return (
        <button
            type="button"
            onClick={onClick}
            className={`text-left rounded-xl border p-3 transition-colors ${
                isSelected
                    ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/10 ring-1 ring-brand-500'
                    : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 hover:border-gray-300 dark:hover:border-gray-700'
            }`}
        >
            <div className="flex items-start justify-between mb-1.5">
                <div className="flex items-center gap-1.5">
                    <span className={`h-2.5 w-2.5 rounded-full shrink-0 ${colors.dot}`} />
                    <span className="font-bold text-sm text-gray-900 dark:text-white">{supplier.code}</span>
                </div>
                {hasSchedule ? (
                    <span className={`text-sm font-bold tabular-nums ${colors.text}`}>{completionPercent}%</span>
                ) : (
                    <span className="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Standby</span>
                )}
            </div>

            <p className="text-xs text-gray-600 dark:text-gray-400 leading-snug mb-2 line-clamp-2">{supplier.name}</p>

            {hasSchedule ? (
                <>
                    <div className="text-[11px] text-gray-500 dark:text-gray-400 mb-1 tabular-nums">
                        Act: {cycle!.actualQty} | Plan: {cycle!.planQty}
                    </div>
                    <div className="h-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                        <div className={`h-full rounded-full ${colors.dot}`} style={{ width: `${barWidth}%` }} />
                    </div>
                </>
            ) : (
                <div className="text-[11px] text-gray-400 dark:text-gray-500">
                    No schedule in C{currentCycleNumber}
                </div>
            )}
        </button>
    );
}
