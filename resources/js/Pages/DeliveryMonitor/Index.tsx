import React, { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import Header from './components/Header';
import SupplierGrid from './components/SupplierGrid';
import SupplierDetailPanel from './components/SupplierDetailPanel';
import PartsTable from './components/PartsTable';
import { getCurrentCycleNumber, SlotWindow } from './utils/scheduling';
import { calculateOtifPercent } from './utils/otif';
import { useClock } from './utils/useClock';
import { Supplier, DeliveryCycle, Part, PartCycleReceipt } from './types';

const ROTATE_INTERVAL_MS = 8000;

interface IndexProps {
    suppliers: Supplier[];
    slots: SlotWindow[];
    cycles: DeliveryCycle[];
    parts: Part[];
    receipts: PartCycleReceipt[];
    selectedDate: string;
}

export default function Index({ suppliers, slots, cycles, parts, receipts: initialReceipts, selectedDate }: IndexProps) {
    const clock = useClock();
    const currentCycleNumber = getCurrentCycleNumber(clock, slots);

    // Receipts are mutable state (not the raw prop) so "Reset Receipts" in
    // PartsTable can update them and have every dependent view (OTIF badge,
    // detail panel, table) recompute live without a server round-trip.
    const [receipts, setReceipts] = useState<PartCycleReceipt[]>(initialReceipts);
    const todayOtifPercent = useMemo(() => calculateOtifPercent(receipts), [receipts]);

    const handleResetReceipts = () => {
        setReceipts((current) => current.map((r) => ({ ...r, receivedQty: 0, status: 'pending' })));
    };

    const [selectedSupplierId, setSelectedSupplierId] = useState<number | null>(suppliers[0]?.id ?? null);
    const focusedSupplier = suppliers.find((s) => s.id === selectedSupplierId);
    const [selectedDateState, setSelectedDateState] = useState<string>(selectedDate);
    const [tvMode, setTvMode] = useState(false);
    const [rotate, setRotate] = useState(false);

    useEffect(() => {
        if (!tvMode || !rotate || suppliers.length === 0) return;

        const interval = setInterval(() => {
            setSelectedSupplierId((current) => {
                const currentIndex = suppliers.findIndex((s) => s.id === current);
                const nextIndex = (currentIndex + 1) % suppliers.length;
                return suppliers[nextIndex].id;
            });
        }, ROTATE_INTERVAL_MS);

        return () => clearInterval(interval);
    }, [tvMode, rotate, suppliers]);

    const handleToggleTvMode = () => {
        setTvMode((current) => {
            const next = !current;
            if (next && document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen().catch(() => {});
            } else if (!next && document.fullscreenElement && document.exitFullscreen) {
                document.exitFullscreen().catch(() => {});
            }
            return next;
        });
    };

    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
            <Head title="Warehouse Part Delivery Monitor" />
            <Header
                suppliers={suppliers}
                selectedSupplierId={selectedSupplierId}
                onSelectSupplier={setSelectedSupplierId}
                selectedDate={selectedDateState}
                onSelectDate={setSelectedDateState}
                tvMode={tvMode}
                onToggleTvMode={handleToggleTvMode}
                rotate={rotate}
                onToggleRotate={() => setRotate((r) => !r)}
                otifPercent={todayOtifPercent}
            />
            <div className="grid grid-cols-1 lg:grid-cols-[65%_35%] gap-4 p-4">
                <SupplierGrid
                    suppliers={suppliers}
                    cycles={cycles}
                    currentCycleNumber={currentCycleNumber}
                    selectedSupplierId={selectedSupplierId}
                    onSelectSupplier={setSelectedSupplierId}
                />
                <SupplierDetailPanel
                    supplier={focusedSupplier}
                    cycles={cycles}
                    parts={parts}
                    receipts={receipts}
                    currentCycleNumber={currentCycleNumber}
                />
            </div>
            <div className="p-4">
                <PartsTable
                    parts={parts}
                    receipts={receipts}
                    suppliers={suppliers}
                    slots={slots}
                    onResetReceipts={handleResetReceipts}
                />
            </div>
        </div>
    );
}
