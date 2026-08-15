import React, { useState, useCallback, useEffect, useRef } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { PencilIcon, ArrowLeftIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';
import QrScanner from '../../../Components/QrScanner';
import Alert from '../../../Tailadmin/components/ui/alert/Alert';

function beep() {
    try {
        const ctx = new AudioContext();
        const osc = ctx.createOscillator();
        osc.connect(ctx.destination);
        osc.frequency.value = 880;
        osc.start();
        osc.stop(ctx.currentTime + 0.06);
    } catch (_) {}
}

export default function Show({ cycle, racks, lastUsedRacks }: any) {
    const { errors = {}, flash = {} } = usePage().props as any;
    const permissions = (usePage().props.auth as any)?.user?.permissions || [];
    const canEdit = permissions.includes('edit cycles');
    const [isReceiving, setIsReceiving] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [items, setItems] = useState<any[]>(
        cycle.items.map((item: any) => {
            let rackId = '';
            let rackSource: 'default' | 'history' | 'none' = 'none';

            if (item.product?.default_rack_id) {
                rackId = String(item.product.default_rack_id);
                rackSource = 'default';
            } else if (lastUsedRacks[item.product_id]) {
                rackId = String(lastUsedRacks[item.product_id]);
                rackSource = 'history';
            }

            return {
                id: item.id,
                received_quantity: item.received_quantity || 0,
                rack_id: rackId,
                rack_source: rackSource,
                notes: item.notes || '',
            };
        })
    );

    // Latest items snapshot for scan feedback — handleScan identity stays stable
    // so the QR scanner does not restart after every scan.
    const itemsRef = useRef(items);
    useEffect(() => { itemsRef.current = items; });

    const [scannerOpen, setScannerOpen] = useState(false);
    const [scanFeedback, setScanFeedback] = useState<{ message: string; type: 'ok' | 'warning' | 'error' } | null>(null);

    useEffect(() => {
        if (!scanFeedback) return;
        const t = setTimeout(() => setScanFeedback(null), 1500);
        return () => clearTimeout(t);
    }, [scanFeedback]);

    const handleScan = useCallback((code: string) => {
        const index = cycle.items.findIndex(
            (item: any) => item.product?.part_number?.toLowerCase() === code.toLowerCase()
        );
        if (index === -1) {
            setScanFeedback({ message: `"${code}" tidak ada dalam cycle ini`, type: 'error' });
            return;
        }
        const current = itemsRef.current[index]?.received_quantity ?? 0;
        const qtyPlan = cycle.items[index].quantity;
        beep();
        setScanFeedback({ message: `✓ ${code} — ${current + 1} (plan: ${qtyPlan})`, type: 'ok' });
        setItems(prev => {
            const updated = [...prev];
            updated[index] = { ...updated[index], received_quantity: prev[index].received_quantity + 1 };
            return updated;
        });
    }, [cycle.items]);

    const statusColors: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800',
        receiving: 'bg-yellow-100 text-yellow-800',
        completed: 'bg-green-100 text-green-800',
    };

    const fmtDuration = (ms: number) => {
        if (ms < 0) return '-';
        const s = Math.floor(ms / 1000);
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        const sec = s % 60;
        if (h > 0) return `${h}j ${m}m ${sec}d`;
        if (m > 0) return `${m}m ${sec}d`;
        return `${sec}d`;
    };

    const cycleStart = new Date(cycle.created_at).getTime();
    const cycleEnd = cycle.received_at ? new Date(cycle.received_at).getTime() : Date.now();
    const totalDuration = cycleEnd - cycleStart;

    const updateItem = (index: number, field: string, value: any) => {
        setItems(prev => prev.map((it, i) => (i === index ? { ...it, [field]: value } : it)));
    };

    const missingRack = items.some((it: any) => !it.rack_id);
    const canReceive = !submitting;

    const handleReceive = (e: React.FormEvent) => {
        e.preventDefault();
        if (!canReceive) return;
        if (!confirm('Konfirmasi penerimaan? Stok akan bertambah sesuai jumlah yang dimasukkan.')) return;
        setSubmitting(true);
        router.post(route('cycles.receive', cycle.id), { items }, {
            onSuccess: () => setIsReceiving(false),
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <>
            <Head title={`Cycle #${cycle.cycle_number}`} />
            <PageBreadcrumb pageTitle={`${cycle.supplier?.name} — Cycle #${cycle.cycle_number}`} />

            {flash?.success && (
                <div className="mb-4">
                    <Alert variant="success" title="Berhasil" message={flash.success} />
                </div>
            )}
            {flash?.error && (
                <div className="mb-4">
                    <Alert variant="error" title="Gagal" message={flash.error} />
                </div>
            )}
            {Object.keys(errors).length > 0 && (
                <div className="mb-4">
                    <Alert
                        variant="error"
                        title="Periksa input"
                        message={Object.values(errors as Record<string, string[]>).flat().join('; ')}
                    />
                </div>
            )}

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div className="xl:col-span-1">
                    <ComponentCard title="Info Cycle" desc="Detail cycle penerimaan">
                        <dl className="space-y-4">
                            <div><dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Supplier</dt><dd className="text-sm text-[#1A1D23]">{cycle.supplier?.name}</dd></div>
                            <div><dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Nomor Cycle</dt><dd className="text-sm text-[#1A1D23] font-mono">{cycle.cycle_number}</dd></div>
                            <div><dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Status</dt><dd><span className={`inline-block px-2 py-1 text-xs font-medium rounded-full ${statusColors[cycle.status]}`}>{cycle.status}</span></dd></div>
                            <div><dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Item</dt><dd className="text-sm text-[#1A1D23]">{cycle.items.length}</dd></div>
                            <div><dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Diterima</dt><dd className="text-sm text-[#1A1D23]">{cycle.received_at ? new Date(cycle.received_at).toLocaleString('id-ID', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit',second:'2-digit'}) : '-'}</dd></div>
                            <div><dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Durasi</dt><dd className="text-sm text-[#1A1D23] tabular-nums">{cycle.status === 'completed' ? `⏱ ${fmtDuration(totalDuration)}` : `🔄 ${fmtDuration(totalDuration)}`}</dd></div>
                            {cycle.notes && <div><dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Catatan</dt><dd className="text-sm text-[#1A1D23]">{cycle.notes}</dd></div>}
                        </dl>
                        <div className="mt-6 flex gap-2 pt-4 border-t border-[#F1F3F5]">
                            {cycle.status === 'draft' && canEdit && <Link href={route('cycles.edit', cycle.id)}><Button icon={<PencilIcon className="w-4 h-4" />} size="sm">Edit</Button></Link>}
                            {cycle.status !== 'completed' && !isReceiving && (
                                <Button variant="outline" size="sm" onClick={() => setIsReceiving(true)}>Terima Barang</Button>
                            )}
                            <Link href={route('cycles.index')}><Button variant="outline" size="sm">Kembali</Button></Link>
                        </div>
                    </ComponentCard>
                </div>

                <div className="xl:col-span-2">
                    {isReceiving ? (
                        <ComponentCard title="Terima Barang" desc="Masukkan jumlah dan rak tujuan">
                            <div className="mb-4">
                                <Button type="button" variant="outline" size="sm" onClick={() => setScannerOpen(true)}>
                                    📷 Scan QR
                                </Button>
                            </div>
                            <form onSubmit={handleReceive}>
                                <div className="overflow-x-auto">
                                <table className="min-w-[850px] sm:min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead className="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th className="px-2 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase">Part Number / Nama Produk</th>
                                            <th className="px-2 py-2 text-center text-[10px] font-semibold text-gray-500 uppercase w-14">Qty Doc</th>
                                            <th className="px-2 py-2 text-center text-[10px] font-semibold text-gray-500 uppercase w-16">Diterima</th>
                                            <th className="px-2 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase w-32">Rak</th>
                                            <th className="px-2 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase hidden sm:table-cell">Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                        {cycle.items.map((item: any, i: number) => (
                                            <tr key={item.id}>
                                                <td className="px-2 py-2 min-w-[180px]">
                                                    <span className="text-xs font-mono text-gray-700 dark:text-gray-300">{item.product?.part_number}</span>
                                                    <span className="text-xs sm:text-sm text-gray-800 dark:text-white/90 block mt-0.5">{item.product?.name}</span>
                                                </td>
                                                <td className="px-2 py-2 text-xs text-center tabular-nums">{item.quantity}</td>
                                                <td className="px-2 py-2">
                                                    <input
                                                        type="number"
                                                        value={items[i].received_quantity}
                                                        onChange={(e) => updateItem(i, 'received_quantity', parseInt(e.target.value) || 0)}
                                                        onFocus={(e) => e.target.select()}
                                                        min={0}
                                                        className="w-14 sm:w-20 text-center text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 rounded px-1 py-1.5"
                                                    />
                                                </td>
                                                <td className="px-2 py-2">
                                                    <div className="flex flex-col gap-0.5">
                                                        <div className="w-28 sm:w-36">
                                                            <SearchableSelect options={racks.map((r: any) => ({ value: r.id, label: r.code }))} value={items[i].rack_id} onChange={(v) => updateItem(i, 'rack_id', v as string)} />
                                                        </div>
                                                        {!items[i].rack_id ? (
                                                            <span className="text-[10px] font-medium text-amber-600">⚠ Relay</span>
                                                        ) : (
                                                            <>
                                                                {items[i].rack_source === 'default' && (
                                                                    <span className="text-[10px] font-medium text-blue-600">Default</span>
                                                                )}
                                                                {items[i].rack_source === 'history' && (
                                                                    <span className="text-[10px] font-medium text-gray-500">Terakhir</span>
                                                                )}
                                                            </>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-2 py-2 hidden sm:table-cell">
                                                    <input type="text" value={items[i].notes} onChange={(e) => updateItem(i, 'notes', e.target.value)} className="w-full text-xs border rounded px-2 py-1 dark:bg-gray-800 dark:border-gray-700" placeholder="cth: rusak" />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                </div>
                                {missingRack && (
                                    <div className="mt-3 flex items-center gap-2 px-3 py-2 text-sm text-amber-700 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
                                        <span>⚠️</span> <span>Beberapa item belum memilih rak — stok akan dicatat tanpa lokasi rak (Overflow).</span>
                                    </div>
                                )}
                                <div className="mt-4 flex gap-3">
                                    <Button type="submit" disabled={!canReceive}>
                                        {submitting ? 'Menyimpan...' : 'Selesaikan Penerimaan'}
                                    </Button>
                                    <Button type="button" variant="outline" onClick={() => setIsReceiving(false)} disabled={submitting}>Batal</Button>
                                </div>
                            </form>
                            {/* Receive Logs */}
                            {cycle.items?.some((i: any) => i.receive_logs?.length > 0) && (
                                <div className="mt-4 pt-4 border-t border-gray-200">
                                    <h4 className="text-xs font-medium text-gray-500 mb-2">📋 Riwayat Penerimaan</h4>
                                    <div className="text-[11px] space-y-1 max-h-40 overflow-y-auto">
                                        {cycle.items.map((item: any) =>
                                            item.receive_logs?.map((log: any, li: number) => {
                                                const logTime = new Date(log.created_at).getTime();
                                                const duration = logTime - cycleStart;
                                                return (
                                                <div key={`${item.id}-${li}`} className="flex gap-3 text-gray-500 items-center">
                                                    <span className="text-gray-300 font-mono w-14">{item.product?.part_number?.substring(0, 10)}</span>
                                                    <span className="text-green-600 font-medium w-8">+{log.quantity}</span>
                                                    <span className="w-36">{new Date(log.created_at).toLocaleString('id-ID', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit',second:'2-digit'})}</span>
                                                    <span className="text-blue-500 w-20 tabular-nums">⏱ {fmtDuration(duration)}</span>
                                                    {log.user?.name && <span className="text-gray-400">— {log.user.name}</span>}
                                                </div>
                                                );
                                            })
                                        )}
                                    </div>
                                </div>
                            )}
                        </ComponentCard>
                    ) : (
                        <ComponentCard title="Item" desc="Daftar produk dalam cycle">
                            <div className="overflow-x-auto">
                            <table className="min-w-[600px] sm:min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th className="px-2 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase">Part Number / Nama Produk</th>
                                        <th className="px-2 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase hidden sm:table-cell">Model</th>
                                        <th className="px-2 py-2 text-center text-[10px] font-semibold text-gray-500 uppercase w-12">Qty</th>
                                        <th className="px-2 py-2 text-center text-[10px] font-semibold text-gray-500 uppercase w-14">Terima</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                    {cycle.items.map((item: any) => (
                                        <tr key={item.id}>
                                            <td className="px-2 py-2 min-w-[180px]">
                                                <span className="text-xs font-mono text-gray-700 dark:text-gray-300">{item.product?.part_number}</span>
                                                <span className="text-xs sm:text-sm text-gray-800 dark:text-white/90 block mt-0.5">{item.product?.name}</span>
                                            </td>
                                            <td className="px-2 py-2 text-xs text-gray-500 hidden sm:table-cell">{item.product?.vehicle_model?.name || '-'}</td>
                                            <td className="px-2 py-2 text-xs text-center tabular-nums">{item.quantity}</td>
                                            <td className="px-2 py-2 text-xs text-center tabular-nums">{item.received_quantity || 0}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            </div>
                        </ComponentCard>
                    )}
                </div>
            </div>
            <QrScanner
                isOpen={scannerOpen}
                onClose={() => setScannerOpen(false)}
                onScan={handleScan}
                autoClose={false}
                feedback={scanFeedback}
            />
        </>
    );
}

Show.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
