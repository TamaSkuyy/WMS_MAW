import React, { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import Header from './components/Header';
import SupplierGrid from './components/SupplierGrid';
import SupplierDetailPanel from './components/SupplierDetailPanel';
import PartsTable from './components/PartsTable';
import { generateMockData, getCurrentCycleNumber, calculateOtifPercent } from './utils/mockData';
import { useClock } from './utils/useClock';
import { PartCycleReceipt } from './types';

const ROTATE_INTERVAL_MS = 8000;

function todayIso(): string {
    return new Date().toISOString().split('T')[0];
}

export default function Index() {
    const mockData = useMemo(() => generateMockData(), []);
    const { suppliers, cycles, parts } = mockData;
    const clock = useClock();
    const currentCycleNumber = getCurrentCycleNumber(clock);

    // Receipts are mutable state (not a plain const from mockData) so "Reset Receipts"
    // in PartsTable can update them and have every dependent view (OTIF badge, detail
    // panel, table) recompute live.
    const [receipts, setReceipts] = useState<PartCycleReceipt[]>(mockData.receipts);
    const todayOtifPercent = useMemo(() => calculateOtifPercent(receipts), [receipts]);

    const handleResetReceipts = () => {
        setReceipts((current) => current.map((r) => ({ ...r, receivedQty: 0, status: 'pending' })));
    };

    const [selectedSupplierId, setSelectedSupplierId] = useState<number | null>(suppliers[0]?.id ?? null);
    const focusedSupplier = suppliers.find((s) => s.id === selectedSupplierId);
    const [selectedDate, setSelectedDate] = useState<string>(todayIso());
    const [tvMode, setTvMode] = useState(false);
    const [rotate, setRotate] = useState(false);

    // Auto-rotate the focused supplier while TV mode + rotate are both on.
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
                document.documentElement.requestFullscreen().catch(() => {
                    // Fullscreen can be rejected by the browser (no user gesture, permissions policy, etc.) — non-fatal.
                });
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
                selectedDate={selectedDate}
                onSelectDate={setSelectedDate}
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
                    onResetReceipts={handleResetReceipts}
                />
            </div>
        </div>
    );
}
