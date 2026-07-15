import React, { useMemo, useRef, useState } from 'react';
import { CheckCircleIcon, ClockIcon } from '@heroicons/react/24/outline';
import { Supplier, DeliveryCycle, Part, PartCycleReceipt } from '../types';
import LedgerModal from './LedgerModal';
import { useAutoScroll } from '../utils/useAutoScroll';

interface SupplierDetailPanelProps {
    supplier: Supplier | undefined;
    cycles: DeliveryCycle[];
    parts: Part[];
    receipts: PartCycleReceipt[];
    currentCycleNumber: number;
}

export default function SupplierDetailPanel({
    supplier,
    cycles,
    parts,
    receipts,
    currentCycleNumber,
}: SupplierDetailPanelProps) {
    const supplierCycles = useMemo(
        () => (supplier ? cycles.filter((c) => c.supplierId === supplier.id) : []),
        [cycles, supplier]
    );

    const currentCycle = useMemo(
        () => supplierCycles.find((c) => c.cycleNumber === currentCycleNumber),
        [supplierCycles, currentCycleNumber]
    );

    const dayFulfillment = useMemo(
        () => ({
            actual: supplierCycles.reduce((sum, c) => sum + c.actualQty, 0),
            plan: supplierCycles.reduce((sum, c) => sum + c.planQty, 0),
        }),
        [supplierCycles]
    );

    const supplierParts = useMemo(
        () => (supplier ? parts.filter((p) => p.supplierId === supplier.id) : []),
        [parts, supplier]
    );

    const partById = useMemo(() => new Map(supplierParts.map((p) => [p.id, p])), [supplierParts]);

    // Scoped to the currently active slot only — feeds the "Cycle X Progress"
    // OTIF stat, which is specifically about what's happening right now.
    const currentCycleParts = useMemo(
        () => receipts.filter((r) => r.cycleNumber === currentCycleNumber && partById.has(r.partId)),
        [receipts, partById, currentCycleNumber]
    );

    // All of today's scheduled parts across every slot (not just the active
    // one) — a part that finished in an earlier slot must stay visible here
    // once the wall clock moves into the next slot, not disappear.
    const todayScheduledParts = useMemo(() => {
        return receipts
            .filter((r) => partById.has(r.partId))
            .map((r) => ({ receipt: r, part: partById.get(r.partId)! }));
    }, [receipts, partById]);

    const cycleOtifPercent = useMemo(() => {
        const totalPlan = currentCycleParts.reduce((sum, r) => sum + r.planQty, 0);
        const totalMatched = currentCycleParts
            .filter((r) => r.status === 'matched')
            .reduce((sum, r) => sum + r.receivedQty, 0);
        return totalPlan > 0 ? Math.round((totalMatched / totalPlan) * 1000) / 10 : 0;
    }, [currentCycleParts]);

    const completedCount = todayScheduledParts.filter(({ receipt }) => receipt.receivedQty >= receipt.planQty).length;

    const [ledgerOpen, setLedgerOpen] = useState(false);

    const scheduledPartsRef = useRef<HTMLDivElement>(null);
    useAutoScroll(scheduledPartsRef);

    if (!supplier) {
        return (
            <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 text-center text-sm text-gray-400">
                Pilih supplier untuk melihat detail.
            </div>
        );
    }

    return (
        <div className="lg:sticky lg:top-[88px] rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex flex-col max-h-[calc(100vh-104px)]">
            {/* Header */}
            <div className="flex items-center justify-between p-4 border-b border-gray-100 dark:border-gray-800">
                <div className="flex items-center gap-3 min-w-0">
                    <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-500 text-white font-bold text-sm">
                        {supplier.code}
                    </div>
                    <div className="min-w-0">
                        <div className="text-[10px] font-semibold uppercase tracking-wider text-gray-400">
                            Supplier Partner
                        </div>
                        <div className="text-sm font-bold text-gray-900 dark:text-white truncate">{supplier.name}</div>
                    </div>
                </div>
                <span className="shrink-0 rounded-full bg-brand-500 text-white text-[10px] font-bold uppercase tracking-wide px-2.5 py-1">
                    Focus
                </span>
            </div>

            <div className="p-4 space-y-4 flex-1 min-h-0 flex flex-col overflow-hidden">
                {/* Stat cards */}
                <div className="grid grid-cols-2 gap-3 shrink-0">
                    <div className="rounded-lg border border-gray-100 dark:border-gray-800 p-3">
                        <div className="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-1">
                            Cycle {currentCycleNumber} Progress
                        </div>
                        <div className="text-xl font-bold tabular-nums text-gray-900 dark:text-white">
                            {currentCycle?.actualQty ?? 0} / {currentCycle?.planQty ?? 0}{' '}
                            <span className="text-xs font-normal text-gray-400">Pcs</span>
                        </div>
                        <span className="inline-block mt-1.5 rounded-full bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400 text-[10px] font-semibold px-2 py-0.5">
                            {cycleOtifPercent.toFixed(1)}% Cycle OTIF
                        </span>
                    </div>

                    <div className="rounded-lg border border-gray-100 dark:border-gray-800 p-3">
                        <div className="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-1">
                            Day Fulfillment
                        </div>
                        <div className="text-xl font-bold tabular-nums text-gray-900 dark:text-white">
                            {dayFulfillment.actual} / {dayFulfillment.plan}{' '}
                            <span className="text-xs font-normal text-gray-400">Pcs</span>
                        </div>
                        <div className="h-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden mt-2">
                            <div
                                className="h-full rounded-full bg-brand-500"
                                style={{
                                    width: `${dayFulfillment.plan > 0 ? Math.min((dayFulfillment.actual / dayFulfillment.plan) * 100, 100) : 0}%`,
                                }}
                            />
                        </div>
                    </div>
                </div>

                {/* Scheduled parts */}
                <div className="flex-1 min-h-0 flex flex-col">
                    <div className="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-2 shrink-0">
                        Today's Scheduled Parts ({todayScheduledParts.length})
                    </div>

                    {todayScheduledParts.length === 0 ? (
                        <div className="py-6 text-center text-xs text-gray-400">
                            Tidak ada part terjadwal hari ini.
                        </div>
                    ) : (
                        <div ref={scheduledPartsRef} className="space-y-2.5 overflow-y-auto flex-1 min-h-0 pr-1">
                            {todayScheduledParts.map(({ receipt, part }) => {
                                const isComplete = receipt.receivedQty >= receipt.planQty;
                                return (
                                    <div key={receipt.id} className="rounded-lg border border-gray-100 dark:border-gray-800 p-2.5">
                                        <div className="flex items-start justify-between gap-2 mb-1.5">
                                            <div className="min-w-0">
                                                <div className="flex items-center gap-1.5">
                                                    <span className="shrink-0 rounded bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400 px-1.5 py-0.5 text-[9px] font-bold">
                                                        C{receipt.cycleNumber}
                                                    </span>
                                                    <span className="text-[11px] font-mono text-gray-400 truncate">
                                                        {part.partNumber}
                                                    </span>
                                                </div>
                                                <div className="text-xs font-medium text-gray-800 dark:text-white/90 truncate">
                                                    {part.partName}
                                                </div>
                                            </div>
                                            <div className="text-right shrink-0">
                                                <div className="text-xs font-bold tabular-nums text-gray-800 dark:text-white/90">
                                                    {receipt.receivedQty} / {receipt.planQty}
                                                </div>
                                                <div className="text-[9px] font-semibold uppercase tracking-wide text-gray-400">
                                                    Units
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-1.5">
                                            {isComplete ? (
                                                <CheckCircleIcon className="h-3.5 w-3.5 text-success-500 shrink-0" />
                                            ) : (
                                                <ClockIcon className="h-3.5 w-3.5 text-warning-500 shrink-0" />
                                            )}
                                            <div className="h-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                                                <div
                                                    className={`h-full rounded-full ${isComplete ? 'bg-success-500' : 'bg-warning-500'}`}
                                                    style={{
                                                        width: `${receipt.planQty > 0 ? Math.min((receipt.receivedQty / receipt.planQty) * 100, 100) : 0}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>

            {/* Footer */}
            <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-800 text-xs">
                <span className="text-gray-500 dark:text-gray-400">
                    {completedCount} of {todayScheduledParts.length} complete
                </span>
                <button
                    type="button"
                    onClick={() => setLedgerOpen(true)}
                    className="font-semibold text-brand-500 hover:text-brand-600"
                >
                    Ledger &gt;
                </button>
            </div>

            {ledgerOpen && (
                <LedgerModal supplierId={supplier.id} supplierName={supplier.name} onClose={() => setLedgerOpen(false)} />
            )}
        </div>
    );
}
