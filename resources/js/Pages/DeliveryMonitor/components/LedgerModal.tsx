import React, { useEffect, useState } from 'react';

interface LedgerEntry {
    id: number;
    cycleNumber: number;
    deliveryDate: string | null;
    slotNumber: number | null;
    status: string;
    planQty: number;
    actualQty: number;
    itemCount: number;
}

interface LedgerPage {
    data: LedgerEntry[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

interface LedgerModalProps {
    supplierId: number;
    supplierName: string;
    onClose: () => void;
}

export default function LedgerModal({ supplierId, supplierName, onClose }: LedgerModalProps) {
    const [page, setPage] = useState<LedgerPage | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const loadPage = (url: string) => {
        setLoading(true);
        setError(null);
        fetch(url, { headers: { Accept: 'application/json' } })
            .then((res) => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            })
            .then((data: LedgerPage) => setPage(data))
            .catch(() => setError('Gagal memuat histori.'))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        loadPage(route('delivery-monitor.ledger', supplierId));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [supplierId]);

    return (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50" onClick={onClose}>
            <div
                className="bg-white dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-2xl mx-4 max-h-[85vh] overflow-y-auto"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                    <div>
                        <div className="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Ledger</div>
                        <div className="text-sm font-bold text-gray-900 dark:text-white">{supplierName}</div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-xl leading-none"
                    >
                        ✕
                    </button>
                </div>

                <div className="p-5">
                    {loading && <div className="py-8 text-center text-sm text-gray-400">Memuat...</div>}
                    {error && <div className="py-8 text-center text-sm text-error-500">{error}</div>}
                    {!loading && !error && page && page.data.length === 0 && (
                        <div className="py-8 text-center text-sm text-gray-400">Belum ada riwayat cycle untuk supplier ini.</div>
                    )}
                    {!loading && !error && page && page.data.length > 0 && (
                        <>
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th className="px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-gray-400">Tanggal</th>
                                        <th className="px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-gray-400">Slot</th>
                                        <th className="px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-gray-400">Cycle</th>
                                        <th className="px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-gray-400">Item</th>
                                        <th className="px-2 py-2 text-right text-[10px] font-semibold uppercase tracking-wide text-gray-400">Actual/Plan</th>
                                        <th className="px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-gray-400">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {page.data.map((entry) => (
                                        <tr key={entry.id} className="text-sm">
                                            <td className="px-2 py-2 text-gray-700 dark:text-gray-300">{entry.deliveryDate ?? '-'}</td>
                                            <td className="px-2 py-2 text-gray-700 dark:text-gray-300">{entry.slotNumber ? `C${entry.slotNumber}` : '-'}</td>
                                            <td className="px-2 py-2 text-gray-500 dark:text-gray-400">#{entry.cycleNumber}</td>
                                            <td className="px-2 py-2 text-gray-500 dark:text-gray-400">{entry.itemCount}</td>
                                            <td className="px-2 py-2 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                                {entry.actualQty}/{entry.planQty}
                                            </td>
                                            <td className="px-2 py-2 text-gray-500 dark:text-gray-400 capitalize">{entry.status}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            {(page.prev_page_url || page.next_page_url) && (
                                <div className="flex items-center justify-between mt-4 text-xs">
                                    <button
                                        type="button"
                                        disabled={!page.prev_page_url}
                                        onClick={() => page.prev_page_url && loadPage(page.prev_page_url)}
                                        className="px-3 py-1 border rounded disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-800"
                                    >
                                        Sebelumnya
                                    </button>
                                    <span className="text-gray-500 dark:text-gray-400">
                                        Halaman {page.current_page} dari {page.last_page}
                                    </span>
                                    <button
                                        type="button"
                                        disabled={!page.next_page_url}
                                        onClick={() => page.next_page_url && loadPage(page.next_page_url)}
                                        className="px-3 py-1 border rounded disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-800"
                                    >
                                        Berikutnya
                                    </button>
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}
