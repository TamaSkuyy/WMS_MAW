import React, { useMemo, useRef, useState } from 'react';
import { useVirtualizer } from '@tanstack/react-virtual';
import { Part, PartCycleReceipt, Supplier } from '../types';
import { SlotWindow } from '../utils/scheduling';
import { getStatusColor } from '../utils/statusColor';
import { useAutoScroll } from '../utils/useAutoScroll';

interface PartsTableProps {
    parts: Part[];
    receipts: PartCycleReceipt[];
    suppliers: Supplier[];
    slots: SlotWindow[];
    onResetReceipts: () => void;
}

type StatusFilter = 'all' | 'active' | 'shortage' | 'matched' | 'over';

const STATUS_TABS: { value: StatusFilter; label: string }[] = [
    { value: 'all', label: 'All Items' },
    { value: 'active', label: 'Active Planned Only' },
    { value: 'shortage', label: 'Shortage/Delayed ⚠️' },
    { value: 'matched', label: 'Matched OK ✅' },
    { value: 'over', label: 'Over-Deliveries 📦' },
];

// NO | PART NUMBER | SUPPLIER | PART NAME | CATEGORY | C1..C6 | TOTAL STATUS
const GRID_TEMPLATE = '56px 130px 190px minmax(160px,1fr) 130px repeat(6, 96px) 140px';
const ROW_HEIGHT = 56;

// Mirrors the backend's computeReceiptStatus() — used to re-derive a cell's
// status after summing multiple same-slot receipts together.
function deriveReceiptStatus(planQty: number, receivedQty: number): PartCycleReceipt['status'] {
    if (receivedQty === 0 && planQty > 0) return 'pending';
    if (receivedQty < planQty) return 'shortage';
    if (receivedQty > planQty) return 'over';
    return 'matched';
}

export default function PartsTable({ parts, receipts, suppliers, slots, onResetReceipts }: PartsTableProps) {
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('');
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');

    const supplierById = useMemo(() => new Map(suppliers.map((s) => [s.id, s])), [suppliers]);
    const categories = useMemo(() => [...new Set(parts.map((p) => p.category))].sort(), [parts]);

    const receiptsByPart = useMemo(() => {
        const map = new Map<number, PartCycleReceipt[]>();
        for (const r of receipts) {
            const list = map.get(r.partId) ?? [];
            list.push(r);
            map.set(r.partId, list);
        }
        return map;
    }, [receipts]);

    // One aggregate pass per part: per-cycle receipt lookup + total actual/plan + derived status.
    // A supplier can have more than one real cycle in the same slot on the
    // same day, so multiple receipts can share a cycleNumber for one part —
    // those are summed per cell rather than letting one silently overwrite
    // the other.
    const rows = useMemo(() => {
        return parts.map((part) => {
            const partReceipts = receiptsByPart.get(part.id) ?? [];

            const byCycle = new Map<number, { planQty: number; receivedQty: number; status: PartCycleReceipt['status'] }>();
            for (const r of partReceipts) {
                const existing = byCycle.get(r.cycleNumber);
                if (existing) {
                    existing.planQty += r.planQty;
                    existing.receivedQty += r.receivedQty;
                    existing.status = deriveReceiptStatus(existing.planQty, existing.receivedQty);
                } else {
                    byCycle.set(r.cycleNumber, { planQty: r.planQty, receivedQty: r.receivedQty, status: r.status });
                }
            }

            const totalPlan = partReceipts.reduce((sum, r) => sum + r.planQty, 0);
            const totalActual = partReceipts.reduce((sum, r) => sum + r.receivedQty, 0);
            const hasSchedule = partReceipts.length > 0;
            const isOk = hasSchedule && totalActual >= totalPlan;
            const isOver = hasSchedule && totalActual > totalPlan;
            const isShortage = hasSchedule && totalActual < totalPlan;

            return { part, byCycle, totalPlan, totalActual, hasSchedule, isOk, isOver, isShortage };
        });
    }, [parts, receiptsByPart]);

    const filteredRows = useMemo(() => {
        const query = search.trim().toLowerCase();
        return rows.filter((row) => {
            const matchesSearch =
                query === '' ||
                row.part.partNumber.toLowerCase().includes(query) ||
                row.part.partName.toLowerCase().includes(query);
            const matchesCategory = category === '' || row.part.category === category;
            const matchesStatus =
                statusFilter === 'all' ||
                (statusFilter === 'active' && row.hasSchedule) ||
                (statusFilter === 'shortage' && row.isShortage) ||
                (statusFilter === 'matched' && row.isOk) ||
                (statusFilter === 'over' && row.isOver);

            return matchesSearch && matchesCategory && matchesStatus;
        });
    }, [rows, search, category, statusFilter]);

    const parentRef = useRef<HTMLDivElement>(null);
    const rowVirtualizer = useVirtualizer({
        count: filteredRows.length,
        getScrollElement: () => parentRef.current,
        estimateSize: () => ROW_HEIGHT,
        overscan: 12,
    });

    // Slowly auto-scroll through the (potentially huge) part list so
    // everything cycles into view on an unattended TV, without needing
    // anyone to scroll manually.
    useAutoScroll(parentRef);

    return (
        <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
            {/* Toolbar */}
            <div className="flex flex-wrap items-end justify-between gap-3 mb-3">
                <div className="flex flex-wrap items-end gap-3">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search Part Number or Name..."
                        className="h-9 w-64 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 focus:border-brand-300"
                    />
                    <select
                        value={category}
                        onChange={(e) => setCategory(e.target.value)}
                        className="h-9 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 text-sm text-gray-800 dark:text-white/90 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 focus:border-brand-300"
                    >
                        <option value="">All Categories</option>
                        {categories.map((c) => (
                            <option key={c} value={c}>
                                {c}
                            </option>
                        ))}
                    </select>
                </div>
                <button
                    type="button"
                    onClick={onResetReceipts}
                    className="h-9 rounded-lg bg-gray-100 dark:bg-gray-800 px-3 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"
                >
                    Reset Receipts
                </button>
            </div>

            <div className="flex flex-wrap gap-1.5 mb-3">
                {STATUS_TABS.map((tab) => (
                    <button
                        key={tab.value}
                        type="button"
                        onClick={() => setStatusFilter(tab.value)}
                        className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors ${
                            statusFilter === tab.value
                                ? 'bg-brand-500 text-white'
                                : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'
                        }`}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>

            {/* Table */}
            <div className="overflow-x-auto border border-gray-100 dark:border-gray-800 rounded-lg">
                <div style={{ minWidth: 1400 }}>
                    {/* Header */}
                    <div
                        className="grid bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        style={{ gridTemplateColumns: GRID_TEMPLATE }}
                    >
                        <div className="px-2 py-2">No</div>
                        <div className="px-2 py-2">Part Number</div>
                        <div className="px-2 py-2">Supplier</div>
                        <div className="px-2 py-2">Part Name</div>
                        <div className="px-2 py-2">Category</div>
                        {slots.map((w) => (
                            <div key={w.cycleNumber} className="px-2 py-2 text-center">
                                <div>C{w.cycleNumber}</div>
                                <div className="text-[9px] font-normal normal-case text-gray-400">
                                    {w.timeStart} - {w.timeEnd}
                                </div>
                            </div>
                        ))}
                        <div className="px-2 py-2 text-center">Total Status</div>
                    </div>

                    {/* Virtualized rows */}
                    <div ref={parentRef} className="overflow-y-auto" style={{ height: 560 }}>
                        {filteredRows.length === 0 ? (
                            <div className="py-12 text-center text-sm text-gray-400">Tidak ada part yang cocok.</div>
                        ) : (
                            <div style={{ height: rowVirtualizer.getTotalSize(), position: 'relative' }}>
                                {rowVirtualizer.getVirtualItems().map((virtualRow) => {
                                    const row = filteredRows[virtualRow.index];
                                    const supplier = supplierById.get(row.part.supplierId);
                                    const shortfall = row.totalPlan - row.totalActual;

                                    return (
                                        <div
                                            key={row.part.id}
                                            className="grid items-center border-b border-gray-100 dark:border-gray-800 text-xs hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                            style={{
                                                gridTemplateColumns: GRID_TEMPLATE,
                                                position: 'absolute',
                                                top: 0,
                                                left: 0,
                                                width: '100%',
                                                height: virtualRow.size,
                                                transform: `translateY(${virtualRow.start}px)`,
                                            }}
                                        >
                                            <div className="px-2 text-gray-400 tabular-nums">{virtualRow.index + 1}</div>
                                            <div className="px-2 font-mono text-gray-700 dark:text-gray-300 truncate">
                                                {row.part.partNumber}
                                            </div>
                                            <div className="px-2 truncate">
                                                <span className="inline-flex items-center gap-1">
                                                    <span className="rounded bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 dark:text-gray-300">
                                                        {supplier?.code ?? '—'}
                                                    </span>
                                                    <span className="text-gray-500 dark:text-gray-400 truncate">
                                                        {supplier?.name ?? '—'}
                                                    </span>
                                                </span>
                                            </div>
                                            <div className="px-2 text-gray-800 dark:text-white/90 truncate">
                                                {row.part.partName}
                                            </div>
                                            <div className="px-2 truncate">
                                                <span className="rounded-full bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400 px-2 py-0.5 text-[10px] font-semibold">
                                                    {row.part.category}
                                                </span>
                                            </div>

                                            {slots.map((w) => {
                                                const receipt = row.byCycle.get(w.cycleNumber);
                                                if (!receipt) {
                                                    return (
                                                        <div key={w.cycleNumber} className="px-2 text-center text-gray-300 dark:text-gray-700">
                                                            —
                                                        </div>
                                                    );
                                                }
                                                const colors = getStatusColor(receipt.status);
                                                return (
                                                    <div key={w.cycleNumber} className="px-2 text-center leading-tight">
                                                        <div className="text-[10px] text-gray-400 tabular-nums">{receipt.planQty}</div>
                                                        <div className={`flex items-center justify-center gap-1 font-bold tabular-nums ${colors.text}`}>
                                                            <span className={`h-1.5 w-1.5 rounded-full ${colors.dot}`} />
                                                            {receipt.receivedQty}
                                                        </div>
                                                    </div>
                                                );
                                            })}

                                            <div className="px-2 text-center">
                                                <div className="text-[11px] tabular-nums text-gray-500 dark:text-gray-400 mb-0.5">
                                                    {row.totalActual}/{row.totalPlan}
                                                </div>
                                                {row.hasSchedule ? (
                                                    row.isOk ? (
                                                        <span className="inline-flex items-center gap-0.5 rounded-full bg-success-50 dark:bg-success-500/10 text-success-600 dark:text-success-400 px-2 py-0.5 text-[10px] font-bold">
                                                            ✔ OK
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-0.5 rounded-full bg-error-50 dark:bg-error-500/10 text-error-600 dark:text-error-400 px-2 py-0.5 text-[10px] font-bold">
                                                            ⚠ -{shortfall} Short
                                                        </span>
                                                    )
                                                ) : (
                                                    <span className="text-[10px] text-gray-300 dark:text-gray-700">—</span>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Footer */}
            <div className="flex flex-wrap items-center justify-between gap-3 mt-3 text-xs">
                <span className="text-gray-500 dark:text-gray-400">
                    Showing {filteredRows.length} of {parts.length} registered parts.
                </span>
                <div className="flex items-center gap-4 text-[11px] text-gray-500 dark:text-gray-400">
                    <span>🟢 Matched/Complete</span>
                    <span>🔴 Shortage/Outstanding</span>
                    <span>🟠 Pending (No Receipt)</span>
                </div>
            </div>
        </div>
    );
}
