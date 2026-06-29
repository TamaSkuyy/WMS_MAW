import React, { useState, useCallback } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Label from '../../../Tailadmin/components/form/Label';
import Input from '../../../Tailadmin/components/form/input/InputField';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';
import QrScanner from '../../../Components/QrScanner';

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

    const handleScan = useCallback(
        (code: string) => {
            const product = products.find(
                (p: any) => p.part_number.toLowerCase() === code.toLowerCase()
            );

            if (!product) {
                setLastScan(code);
                setLastScanStatus('unknown');
                return;
            }

            beep();
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
        [products]
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
    const canSubmit =
        !!supplierId && items.length > 0 && items.every((i) => !!i.rack_id);

    const handleSubmit = () => {
        if (!canSubmit || submitting) return;
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

    return (
        <AppLayout>
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
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead className="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Part #
                                            </th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Produk
                                            </th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Rack
                                            </th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Qty
                                            </th>
                                            <th className="px-3 py-2" />
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
                                                <td className="px-3 py-2 text-xs font-mono whitespace-nowrap">
                                                    {item.part_number}
                                                </td>
                                                <td className="px-3 py-2 text-sm">
                                                    {item.name}
                                                </td>
                                                <td className="px-3 py-2 w-36">
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
                                                        <p className="text-xs text-red-500 mt-0.5">
                                                            Pilih rack
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 w-20">
                                                    <Input
                                                        type="number"
                                                        value={item.quantity}
                                                        onChange={(e) =>
                                                            updateItem(
                                                                item.product_id,
                                                                'quantity',
                                                                parseInt(e.target.value) || 1
                                                            )
                                                        }
                                                        min={1}
                                                    />
                                                </td>
                                                <td className="px-3 py-2">
                                                    <button
                                                        onClick={() =>
                                                            removeItem(item.product_id)
                                                        }
                                                        className="text-red-500 text-sm hover:text-red-700"
                                                    >
                                                        &#x2715;
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {items.length > 0 && (
                            <div className="mt-4 pt-4 border-t border-gray-100 flex gap-3">
                                <Button
                                    onClick={handleSubmit}
                                    disabled={!canSubmit || submitting}
                                >
                                    {submitting
                                        ? 'Menyimpan...'
                                        : 'Selesaikan Penerimaan'}
                                </Button>
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
        </AppLayout>
    );
}
