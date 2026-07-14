import React, { useState, useMemo, useEffect, useCallback, useRef } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeftIcon, CheckIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';
import QrScanner from '../../../Components/QrScanner';

interface TableItem {
    product_id: number;
    part_number: string;
    name: string;
    stock: number;
    rack_id: string;
    quantity: number;
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

export default function Edit({ shopping, products, racks, vehicleModels }: any) {
    const pageErrors = ((usePage().props as any).errors ?? {}) as Record<string, string>;

    const [partnerName, setPartnerName] = useState(shopping.partner_name || '');
    const [shoppingDate, setShoppingDate] = useState(shopping.shopping_date || '');
    const [notes, setNotes] = useState(shopping.notes || '');
    const [submitting, setSubmitting] = useState(false);

    const [selUnit, setSelUnit] = useState('');
    const [selSuffix, setSelSuffix] = useState('');
    const [tableItems, setTableItems] = useState<TableItem[]>([]);
    const [scannerOpen, setScannerOpen] = useState(false);
    const [lastScan, setLastScan] = useState('');
    const [lastScanStatus, setLastScanStatus] = useState<'ok' | 'unknown' | 'no_stock' | null>(null);

    // Built once from initial prop value — used only on first table populate
    const savedItemsMap = useMemo<Record<number, { qty: number; rack_id: string }>>(() =>
        Object.fromEntries(
            (shopping.items ?? []).map((i: any) => [i.product_id, { qty: i.quantity, rack_id: String(i.rack_id) }])
        ),
        [] // eslint-disable-line react-hooks/exhaustive-deps
    );
    const isFirstPopulate = useRef(true);

    // Pre-select Unit + Suffix from first existing item on mount
    useEffect(() => {
        if (!shopping.items?.length) return;
        const firstItem = shopping.items[0];
        const prod = products.find((p: any) => p.id === firstItem.product_id);
        if (!prod?.vehicle_model) return;
        const vm = prod.vehicle_model;
        setSelUnit(`${vm.brand} ${vm.name}`);
        setSelSuffix(vm.suffix ?? '');
    }, []);

    const units = useMemo(() =>
        [...new Map(vehicleModels.map((m: any) => [`${m.brand} ${m.name}`, m])).values()]
            .map((m: any) => ({ value: `${m.brand} ${m.name}`, label: `${m.brand} ${m.name}` })),
        [vehicleModels]
    );

    const suffixes = useMemo(() =>
        vehicleModels
            .filter((m: any) => `${m.brand} ${m.name}` === selUnit)
            .map((m: any) => ({ value: m.suffix ?? '', label: m.suffix || 'Standar' })),
        [selUnit, vehicleModels]
    );

    const activeVehicleModelId = useMemo(() =>
        vehicleModels.find((m: any) =>
            `${m.brand} ${m.name}` === selUnit && (m.suffix ?? '') === selSuffix
        )?.id,
        [selUnit, selSuffix, vehicleModels]
    );

    useEffect(() => {
        if (!activeVehicleModelId) { setTableItems([]); return; }
        const filtered = products.filter((p: any) => p.vehicle_model_id === activeVehicleModelId);
        // Only use saved quantities on first populate (from mount pre-select)
        // Subsequent populates (user changed Unit/Suffix) start fresh at qty=0
        const useMap: Record<number, { qty: number; rack_id: string }> = isFirstPopulate.current ? savedItemsMap : {};
        if (isFirstPopulate.current) isFirstPopulate.current = false;
        setTableItems(filtered.map((p: any) => ({
            product_id: p.id,
            part_number: p.part_number,
            name: p.name,
            stock: p.default_rack_id
                ? (p.stocks.find((s: any) => s.rack_id === p.default_rack_id)?.quantity ?? 0)
                : p.stocks.reduce((sum: number, s: any) => sum + s.quantity, 0),
            rack_id: useMap[p.id]?.rack_id ?? (p.default_rack_id ? String(p.default_rack_id) : ''),
            quantity: useMap[p.id]?.qty ?? 0,
        })));
    }, [activeVehicleModelId]);

    const handleScan = useCallback((code: string) => {
        const item = tableItems.find(i => i.part_number.toLowerCase() === code.toLowerCase());
        if (!item) { setLastScan(code); setLastScanStatus('unknown'); return; }
        if (item.stock <= 0) { setLastScan(code); setLastScanStatus('no_stock'); return; }
        beep();
        setLastScan(code);
        setLastScanStatus('ok');
        setTableItems(prev => prev.map(i =>
            i.product_id === item.product_id ? { ...i, quantity: i.quantity + 1 } : i
        ));
    }, [tableItems]);

    const updateItem = (productId: number, field: keyof TableItem, value: any) => {
        setTableItems(prev => prev.map(i =>
            i.product_id === productId ? { ...i, [field]: value } : i
        ));
    };

    const hasActiveItems = tableItems.some(i => i.quantity > 0);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!hasActiveItems || submitting) return;
        setSubmitting(true);
        router.put(route('shoppings.update', shopping.id), {
            partner_name: partnerName,
            shopping_date: shoppingDate,
            notes,
            items: tableItems
                .filter(i => i.quantity > 0)
                .map(i => ({ product_id: i.product_id, rack_id: i.rack_id, quantity: i.quantity })),
        }, { onFinish: () => setSubmitting(false) });
    };

    return (
        <>
            <Head title="Edit Shopping" />
            <PageBreadcrumb pageTitle={`Edit: ${shopping.partner_name}`} />

            <form onSubmit={handleSubmit}>
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <ComponentCard
                        title="Info Shopping"
                        desc="Perbarui data pengiriman"
                        action={
                            <Link href={route('shoppings.index')}>
                                <Button variant="outline" size="sm" icon={<ArrowLeftIcon className="w-4 h-4" />}>Kembali</Button>
                            </Link>
                        }
                    >
                        <div className="space-y-5">
                            <div>
                                <Label>Nama Mitra *</Label>
                                <Input type="text" value={partnerName} onChange={(e) => setPartnerName(e.target.value)} />
                                {pageErrors.partner_name && <p className="mt-1 text-sm text-red-500">{pageErrors.partner_name}</p>}
                            </div>
                            <div>
                                <Label>Tanggal Kirim *</Label>
                                <Input type="date" value={shoppingDate} onChange={(e) => setShoppingDate(e.target.value)} />
                                {pageErrors.shopping_date && <p className="mt-1 text-sm text-red-500">{pageErrors.shopping_date}</p>}
                            </div>
                            <div>
                                <Label>Catatan</Label>
                                <textarea
                                    value={notes}
                                    onChange={(e) => setNotes(e.target.value)}
                                    rows={2}
                                    className="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-3 py-2 text-sm"
                                />
                            </div>
                            <div className="pt-3 border-t border-gray-100 space-y-4">
                                <div>
                                    <Label>Unit *</Label>
                                    <SearchableSelect
                                        options={units}
                                        value={selUnit}
                                        onChange={(v) => { setSelUnit(v as string); setSelSuffix(''); }}
                                    />
                                </div>
                                <div>
                                    <Label>Suffix *</Label>
                                    <SearchableSelect
                                        options={selUnit ? suffixes : []}
                                        value={selSuffix}
                                        onChange={(v) => setSelSuffix(v as string)}
                                    />
                                    {!selUnit && <p className="mt-1 text-xs text-gray-400">Pilih Unit terlebih dahulu</p>}
                                </div>
                            </div>
                        </div>
                    </ComponentCard>

                    <ComponentCard title="Daftar Part" desc="Muncul otomatis sesuai Unit + Suffix">
                        <div className="mb-3 flex items-center justify-between flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setScannerOpen(true)}
                                disabled={!activeVehicleModelId}
                            >
                                📷 Scan QR
                            </Button>
                            {lastScan && (
                                <span className={`text-xs px-2 py-1 rounded ${
                                    lastScanStatus === 'ok'
                                        ? 'bg-green-50 text-green-700'
                                        : lastScanStatus === 'no_stock'
                                        ? 'bg-yellow-50 text-yellow-700'
                                        : 'bg-orange-50 text-orange-700'
                                }`}>
                                    {lastScanStatus === 'ok' ? '✓ ' : '✗ '}
                                    {lastScan}
                                    {lastScanStatus === 'unknown' && ' — tidak dikenal'}
                                    {lastScanStatus === 'no_stock' && ' — stok 0'}
                                </span>
                            )}
                        </div>

                        {tableItems.length === 0 ? (
                            <p className="text-sm text-gray-400 py-8 text-center">
                                {activeVehicleModelId
                                    ? 'Tidak ada part untuk kombinasi ini.'
                                    : 'Pilih Unit & Suffix untuk memuat part.'}
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead className="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Part #</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Stok</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rack</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                        {tableItems.map((item) => (
                                            <tr
                                                key={item.product_id}
                                                className={item.stock === 0 ? 'bg-red-50 dark:bg-red-900/10 opacity-60' : ''}
                                            >
                                                <td className="px-3 py-2 text-xs font-mono whitespace-nowrap">{item.part_number}</td>
                                                <td className="px-3 py-2 text-sm">{item.name}</td>
                                                <td className="px-3 py-2">
                                                    <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${
                                                        item.stock > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                                                    }`}>
                                                        {item.stock}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2 w-32">
                                                    <SearchableSelect
                                                        options={racks.map((r: any) => ({ value: r.id, label: r.code }))}
                                                        value={item.rack_id}
                                                        onChange={(v) => updateItem(item.product_id, 'rack_id', v as string)}
                                                    />
                                                </td>
                                                <td className="px-3 py-2 w-20">
                                                    <Input
                                                        type="number"
                                                        value={item.quantity}
                                                        onChange={(e) => updateItem(item.product_id, 'quantity', parseInt(e.target.value) || 0)}
                                                        min={0}
                                                        max={item.stock}
                                                        disabled={item.stock === 0}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {pageErrors.items && <p className="mt-2 text-sm text-red-500">{pageErrors.items}</p>}
                    </ComponentCard>
                </div>

                <div className="mt-4 flex gap-3">
                    <Button
                        type="submit"
                        disabled={!hasActiveItems || submitting}
                        icon={<CheckIcon className="w-4 h-4" />}
                    >
                        {submitting ? 'Menyimpan...' : 'Update Shopping'}
                    </Button>
                    <Button type="button" variant="outline" onClick={() => window.history.back()}>Batal</Button>
                </div>
            </form>

            <QrScanner
                isOpen={scannerOpen}
                onClose={() => setScannerOpen(false)}
                onScan={handleScan}
            />
        </>
    );
}

Edit.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
