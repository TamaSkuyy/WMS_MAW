import React, { useState, useCallback, useMemo, useRef, useEffect } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Label from '../../../Tailadmin/components/form/Label';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';
import QrScanner from '../../../Components/QrScanner';
import QtyStepper from '../../../Components/QtyStepper';

interface ScannedItem {
    product_id: number;
    part_number: string;
    name: string;
    quantity: number;
    rack_id: string;
}

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

export default function QuickReceive({ suppliers, products, racks }: any) {
    const [supplierId, setSupplierId] = useState('');
    const [scannerOpen, setScannerOpen] = useState(false);
    const [items, setItems] = useState<ScannedItem[]>([]);
    const [lastScan, setLastScan] = useState('');
    const [lastScanStatus, setLastScanStatus] = useState<'ok' | 'unknown' | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const scanSuccessRef = useRef(false);

    // Filter produk berdasarkan supplier yang dipilih
    const filteredProducts = useMemo(() => {
        if (!supplierId) return [];
        return products.filter((p: any) => String(p.supplier_id) === String(supplierId));
    }, [products, supplierId]);

    const handleScan = useCallback(
        (code: string) => {
            if (!supplierId) return;

            const product = filteredProducts.find(
                (p: any) => p.part_number.toLowerCase() === code.toLowerCase()
            );

            if (!product) {
                setLastScan(code);
                setLastScanStatus('unknown');
                return;
            }

            beep();
            scanSuccessRef.current = true;
            setLastScan(code);
            setLastScanStatus('ok');

            setItems((prev) => {
                const existing = prev.find((i) => i.product_id === product.id);
                if (existing) {
                    return prev.map((i) =>
                        i.product_id === product.id
                            ? { ...i, quantity: i.quantity + 1 }
                            : i
                    );
                }
                return [
                    ...prev,
                    {
                        product_id: product.id,
                        part_number: product.part_number,
                        name: product.name,
                        quantity: 1,
                        rack_id: product.default_rack_id
                            ? String(product.default_rack_id)
                            : '',
                    },
                ];
            });
        },
        [filteredProducts, supplierId]
    );

    const updateItem = (productId: number, field: string, value: any) => {
        setItems((prev) =>
            prev.map((i) =>
                i.product_id === productId ? { ...i, [field]: value } : i
            )
        );
    };

    const removeItem = (productId: number) => {
        setItems((prev) => prev.filter((i) => i.product_id !== productId));
    };

    const totalQty = items.reduce((sum, i) => sum + i.quantity, 0);
    const canSubmit = !!supplierId && items.length > 0;
    const missingRack = items.some((i) => !i.rack_id);

    const handleSubmit = () => {
        if (!canSubmit || submitting) return;
        if (!confirm('Konfirmasi penerimaan? Stok akan bertambah.')) return;
        setSubmitting(true);
        router.post(
            route('cycles.quick-receive.store'),
            {
                supplier_id: supplierId,
                items: items.map((i) => ({
                    product_id: i.product_id,
                    rack_id: i.rack_id,
                    quantity: i.quantity,
                })),
            },
            { onFinish: () => setSubmitting(false) }
        );
    };

    // Auto-reopen scanner after successful scan
    useEffect(() => {
        if (scanSuccessRef.current && !scannerOpen) {
            const t = setTimeout(() => {
                scanSuccessRef.current = false;
                setScannerOpen(true);
            }, 800);
            return () => clearTimeout(t);
        }
    }, [scannerOpen]);

    return (
        <>
            <Head title="Terima Cepat" />
            <PageBreadcrumb pageTitle="Terima Cepat (Scan QR)" />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div className="xl:col-span-1">
                    <ComponentCard
                        title="Setup Penerimaan"
                        action={
                            <Link href={route('cycles.index')}>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    icon={<ArrowLeftIcon className="w-4 h-4" />}
                                >
                                    Kembali
                                </Button>
                            </Link>
                        }
                    >
                        <div className="space-y-4">
                            <div>
                                <Label>Supplier *</Label>
                                <SearchableSelect
                                    options={suppliers.map((s: any) => ({
                                        value: s.id,
                                        label: s.name,
                                    }))}
                                    value={supplierId}
                                    onChange={(v) => setSupplierId(v as string)}
                                />
                            </div>

                            <Button
                                onClick={() => setScannerOpen(true)}
                                disabled={!supplierId}
                                className="w-full"
                            >
                                📷 Scan QR Code
                            </Button>

                            {lastScan && (
                                <div
                                    className={`text-xs px-3 py-2 rounded ${
                                        lastScanStatus === 'ok'
                                            ? 'bg-green-50 text-green-700'
                                            : 'bg-orange-50 text-orange-700'
                                    }`}
                                >
                                    {lastScanStatus === 'ok' ? '✓ ' : '✗ '}
                                    {lastScan}
                                    {lastScanStatus === 'unknown' &&
                                        ' — Part tidak dikenal'}
                                </div>
                            )}

                            <div className="pt-2 border-t border-gray-100 text-sm text-gray-500 space-y-1">
                                <div>
                                    Jenis produk:{' '}
                                    <span className="font-medium text-gray-800">
                                        {items.length}
                                    </span>
                                </div>
                                <div>
                                    Total qty:{' '}
                                    <span className="font-medium text-gray-800">
                                        {totalQty}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </ComponentCard>
                </div>

                <div className="xl:col-span-2">
                    <ComponentCard
                        title="Daftar Barang Diterima"
                        desc="Hasil scan — review sebelum submit"
                    >
                        {items.length === 0 ? (
                            <p className="text-sm text-gray-400 py-8 text-center">
                                Belum ada barang. Pilih supplier lalu scan QR.
                            </p>
                        ) : (
                            <>
                            {/* Desktop (≥ md): tabel */}
                            <div className="hidden md:block overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead className="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Part #</th>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider w-48">Rack</th>
                                            <th className="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                            <th className="px-4 py-2.5"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                        {items.map((item) => (
                                            <tr
                                                key={item.product_id}
                                                className={
                                                    item.rack_id
                                                        ? ''
                                                        : 'bg-red-50 dark:bg-red-900/10'
                                                }
                                            >
                                                <td className="px-4 py-2.5 text-xs font-mono whitespace-nowrap">
                                                    {item.part_number}
                                                </td>
                                                <td className="px-4 py-2.5 text-sm">
                                                    {item.name}
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <div className="w-40 sm:w-48">
                                                        <SearchableSelect
                                                            options={racks.map((r: any) => ({
                                                                value: r.id,
                                                                label: r.code,
                                                            }))}
                                                            value={item.rack_id}
                                                            onChange={(v) =>
                                                                updateItem(
                                                                    item.product_id,
                                                                    'rack_id',
                                                                    v as string
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                    {!item.rack_id && (
                                                        <span className="text-xs text-amber-600 font-medium">⚠ Relay / Tanpa Rak</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <QtyStepper
                                                        value={item.quantity}
                                                        onChange={(n) => updateItem(item.product_id, 'quantity', n)}
                                                        min={1}
                                                    />
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <button
                                                        onClick={() => removeItem(item.product_id)}
                                                        className="inline-flex items-center justify-center h-11 w-11 rounded-lg text-red-500 text-lg hover:bg-red-50 dark:hover:bg-red-500/20"
                                                        title="Hapus"
                                                    >
                                                        ✕
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {/* Mobile (< md): kartu per item */}
                            <div className="md:hidden space-y-3">
                                {items.map((item) => (
                                    <div
                                        key={item.product_id}
                                        className={`rounded-xl border p-4 ${
                                            item.rack_id
                                                ? 'border-[#E9ECEF] dark:border-gray-700 bg-white dark:bg-gray-900'
                                                : 'border-red-300 bg-red-50 dark:bg-red-900/10'
                                        }`}
                                    >
                                        <div className="flex justify-between items-start gap-2 mb-3">
                                            <div>
                                                <div className="font-mono text-xs text-gray-500 dark:text-gray-400">{item.part_number}</div>
                                                <div className="text-sm font-medium text-gray-800 dark:text-gray-200">{item.name}</div>
                                            </div>
                                            <button
                                                onClick={() => removeItem(item.product_id)}
                                                className="inline-flex items-center justify-center h-11 w-11 rounded-lg text-red-500 text-lg hover:bg-red-50 dark:hover:bg-red-500/20"
                                                title="Hapus"
                                            >
                                                ✕
                                            </button>
                                        </div>
                                        <div className="mb-2">
                                            <QtyStepper
                                                value={item.quantity}
                                                onChange={(n) => updateItem(item.product_id, 'quantity', n)}
                                                min={1}
                                            />
                                        </div>
                                        <SearchableSelect
                                            options={racks.map((r: any) => ({
                                                value: r.id,
                                                label: r.code,
                                            }))}
                                            value={item.rack_id}
                                            onChange={(v) =>
                                                updateItem(
                                                    item.product_id,
                                                    'rack_id',
                                                    v as string
                                                )
                                            }
                                        />
                                        {!item.rack_id && (
                                            <span className="text-xs text-amber-600 font-medium">⚠ Relay / Tanpa Rak</span>
                                        )}
                                    </div>
                                ))}
                            </div>
                            </>
                        )}

                        {missingRack && (
                            <div className="mt-3 flex items-center gap-2 px-3 py-2 text-sm text-amber-700 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
                                <span>⚠️</span> <span>Beberapa item belum pilih rak — stok dicatat tanpa lokasi (Overflow).</span>
                            </div>
                        )}
                        {items.length > 0 && (
                            <div className="sticky bottom-0 z-10 mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#E9ECEF] bg-white px-4 py-3 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                                <div className="text-sm font-medium text-[#1A1D23] dark:text-white">
                                    {items.length} jenis
                                    <span className="text-gray-500 dark:text-gray-400 font-normal"> · {totalQty} qty</span>
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        onClick={() => {
                                            setItems([]);
                                            setLastScan('');
                                            setLastScanStatus(null);
                                        }}
                                    >
                                        Reset
                                    </Button>
                                    <Button
                                        onClick={handleSubmit}
                                        disabled={!canSubmit || submitting}
                                    >
                                        {submitting
                                            ? 'Menyimpan...'
                                            : 'Selesaikan Penerimaan'}
                                    </Button>
                                </div>
                            </div>
                        )}
                    </ComponentCard>
                </div>
            </div>

            <QrScanner
                isOpen={scannerOpen}
                onClose={() => setScannerOpen(false)}
                onScan={handleScan}
            />
        </>
    );
}

QuickReceive.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
