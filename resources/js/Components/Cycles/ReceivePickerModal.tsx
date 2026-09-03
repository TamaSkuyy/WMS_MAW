import React, { useCallback, useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import Button from '../../Tailadmin/components/ui/button/Button';
import SearchableSelect from '../../Tailadmin/components/form/select/SearchableSelect';

interface PickerCycle {
    id: number;
    cycle_number: string | number;
    supplier: string;
    delivery_date: string | null;
    status: string;
    items_count: number;
    plan_qty: number;
    received_qty: number;
}

interface ReceivePickerModalProps {
    isOpen: boolean;
    onClose: () => void;
    suppliers: Array<{ id: number; name: string }>;
}

const STATUS_META: Record<string, { label: string; cls: string }> = {
    draft: { label: 'Draft', cls: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200' },
    receiving: { label: 'Receiving', cls: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200' },
    completed: { label: 'Completed', cls: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200' },
};

export default function ReceivePickerModal({ isOpen, onClose, suppliers }: ReceivePickerModalProps) {
    const [supplierId, setSupplierId] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const [cycles, setCycles] = useState<PickerCycle[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [loaded, setLoaded] = useState(false);

    const load = useCallback(async (params: Record<string, string> = {}) => {
        setLoading(true);
        setError('');
        try {
            const qs = new URLSearchParams();
            Object.entries(params).forEach(([k, v]) => { if (v) qs.set(k, v); });
            const url = `${route('cycles.receive-picker')}${qs.toString() ? `?${qs.toString()}` : ''}`;
            const res = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data?.message || `Server error (HTTP ${res.status})`);
            setCycles(data.cycles || []);
            setLoaded(true);
        } catch (err: any) {
            setError(err.message || 'Gagal memuat daftar cycle.');
        } finally {
            setLoading(false);
        }
    }, []);

    // Muat ulang setiap modal dibuka (data draft terbaru hasil import)
    useEffect(() => {
        if (isOpen) {
            setCycles([]);
            setLoaded(false);
            load();
        }
    }, [isOpen, load]);

    if (!isOpen) return null;

    const applyFilters = () => load({ supplier_id: supplierId, date_from: dateFrom, date_to: dateTo });

    const pickCycle = (c: PickerCycle) => {
        // Buka halaman cycle dengan form Terima Barang langsung terbuka
        router.visit(route('cycles.show', c.id), { data: { receive: 1 }, preserveScroll: true });
    };

    const inputCls =
        'w-full h-11 px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500/50';

    return (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 p-4">
            <div className="bg-white dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-3xl mx-4 max-h-[90vh] flex flex-col">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">🚚 Terima Barang</h2>
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            Pilih cycle (Draft / Receiving) yang belum lengkap, lalu klik untuk menerima.
                        </p>
                    </div>
                    <button
                        onClick={onClose}
                        className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        aria-label="Tutup"
                    >
                        ✕
                    </button>
                </div>

                {/* Filter: mitra/supplier + rentang tanggal kedatangan */}
                <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="w-full sm:w-52">
                            <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Mitra / Supplier</label>
                            <SearchableSelect
                                options={[{ value: '', label: 'Semua Supplier' }, ...suppliers.map((s) => ({ value: s.id, label: s.name }))]}
                                value={supplierId}
                                onChange={(v) => setSupplierId(v as string)}
                            />
                        </div>
                        <div className="w-full sm:w-40">
                            <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Tanggal dari</label>
                            <input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} className={inputCls} />
                        </div>
                        <div className="w-full sm:w-40">
                            <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Tanggal sampai</label>
                            <input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} className={inputCls} />
                        </div>
                        <div className="flex gap-2">
                            <Button variant="outline" size="sm" onClick={applyFilters} disabled={loading}>
                                {loading ? 'Memuat...' : 'Cari'}
                            </Button>
                            {(supplierId || dateFrom || dateTo) && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => { setSupplierId(''); setDateFrom(''); setDateTo(''); load(); }}
                                >
                                    Reset
                                </Button>
                            )}
                        </div>
                    </div>
                </div>

                {/* Daftar cycle */}
                <div className="px-6 py-4 overflow-y-auto flex-1 min-h-[200px]">
                    {error && (
                        <div className="mb-3 px-3 py-2 text-sm text-red-700 bg-red-50 dark:bg-red-900/20 rounded-lg">
                            ⚠️ {error}
                        </div>
                    )}

                    {loading && !loaded && (
                        <div className="text-center py-10 text-sm text-gray-500">Memuat daftar cycle...</div>
                    )}

                    {loaded && cycles.length === 0 && !loading && (
                        <div className="text-center py-10">
                            <div className="text-3xl mb-2">📭</div>
                            <p className="text-sm text-gray-500">
                                Tidak ada cycle Draft / Receiving yang perlu diterima.
                            </p>
                            <p className="text-xs text-gray-400 mt-1">
                                Import data cycle dulu, atau coba ubah filter supplier/tanggal.
                            </p>
                        </div>
                    )}

                    {cycles.length > 0 && (
                        <div className="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-xl">
                            <table className="min-w-full">
                                <thead className="bg-[#F8F9FC] dark:bg-gray-800 border-b border-[#E9ECEF] dark:border-gray-700">
                                    <tr>
                                        <th className="px-4 py-2.5 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Nama Vendor</th>
                                        <th className="px-4 py-2.5 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">No Cycle</th>
                                        <th className="px-4 py-2.5 text-center text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Terima / Rencana (actual/plan)</th>
                                        <th className="px-4 py-2.5 text-center text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Status</th>
                                        <th className="px-4 py-2.5 text-center text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Item</th>
                                        <th className="px-4 py-2.5"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {cycles.map((c) => {
                                        const meta = STATUS_META[c.status] || { label: c.status, cls: 'bg-gray-100 text-gray-800' };
                                        const pct = c.plan_qty > 0 ? Math.min(100, Math.round((c.received_qty / c.plan_qty) * 100)) : 0;
                                        const done = c.received_qty >= c.plan_qty;
                                        return (
                                            <tr
                                                key={c.id}
                                                onClick={() => pickCycle(c)}
                                                className="border-b border-[#F1F3F5] dark:border-gray-700 hover:bg-[#F8F9FC] dark:hover:bg-gray-800 transition-colors cursor-pointer"
                                            >
                                                <td className="px-4 py-3 text-sm text-[#1A1D23] dark:text-gray-200 whitespace-nowrap">{c.supplier}</td>
                                                <td className="px-4 py-3 text-sm font-mono text-[#1A1D23] dark:text-gray-200 whitespace-nowrap">#{c.cycle_number}</td>
                                                <td className="px-4 py-3">
                                                    <div className="flex flex-col items-center gap-1">
                                                        <span className={`text-sm tabular-nums font-medium ${done ? 'text-green-600' : c.received_qty > 0 ? 'text-amber-600' : 'text-[#6C757D]'}`}>
                                                            {c.received_qty} / {c.plan_qty}
                                                        </span>
                                                        <div className="w-24 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                                            <div
                                                                className={`h-full rounded-full ${done ? 'bg-green-500' : 'bg-brand-500'}`}
                                                                style={{ width: `${pct}%` }}
                                                            />
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    <span className={`inline-block px-2 py-1 text-xs font-medium rounded-full ${meta.cls}`}>{meta.label}</span>
                                                </td>
                                                <td className="px-4 py-3 text-center text-sm text-[#6C757D] tabular-nums">{c.items_count}</td>
                                                <td className="px-4 py-3 text-right">
                                                    <span className="inline-flex items-center gap-1 text-xs font-medium text-brand-500">
                                                        Terima <span aria-hidden>›</span>
                                                    </span>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="flex justify-end px-6 py-3 border-t border-gray-200 dark:border-gray-700">
                    <Button variant="outline" onClick={onClose}>Tutup</Button>
                </div>
            </div>
        </div>
    );
}
