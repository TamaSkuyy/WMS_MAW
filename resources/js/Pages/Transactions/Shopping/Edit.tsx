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
    rack_label: string;
    rack_zone: string;
    is_relay: boolean;
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

    // Build rack lookup for labels/zones
    const rackMap = useMemo(() => {
        const map = new Map<string, { code: string; zone: string }>();
        racks.forEach((r: any) => map.set(String(r.id), { code: r.code, zone: r.zone || '-' }));
        return map;
    }, [racks]);

    useEffect(() => {
        if (!activeVehicleModelId) { setTableItems([]); return; }
        const filtered = products.filter((p: any) => p.vehicle_model_id === activeVehicleModelId);
        const useMap: Record<number, { qty: number; rack_id: string }> = isFirstPopulate.current ? savedItemsMap : {};
        if (isFirstPopulate.current) isFirstPopulate.current = false;
        const rows: TableItem[] = [];
        filtered.forEach((p: any) => {
            (p.stocks || []).forEach((s: any) => {
                const rid = s.rack_id ? String(s.rack_id) : '';
                const prefill = useMap[p.id];
                rows.push({
                    product_id: p.id,
                    part_number: p.part_number,
                    name: p.name,
                    stock: s.quantity,
                    rack_id: rid,
                    rack_label: rid ? (rackMap.get(rid)?.code ?? rid) : '⚠ Relay',
                    rack_zone: rid ? (rackMap.get(rid)?.zone ?? '-') : '—',
                    is_relay: !rid,
                    quantity: prefill && String(prefill.rack_id) === rid ? prefill.qty : 0,
                });
            });
            if ((p.stocks || []).length === 0) {
                rows.push({
                    product_id: p.id, part_number: p.part_number, name: p.name,
                    stock: 0, rack_id: '', rack_label: '—', rack_zone: '—',
                    is_relay: false, quantity: 0,
                });
            }
        });
        setTableItems(rows);
    }, [activeVehicleModelId, rackMap]);

    const handleScan = useCallback((code: string) => {
        const item = tableItems.find(i =>
            i.part_number.toLowerCase() === code.toLowerCase() && i.stock > 0
        );
        if (!item) {
            const existsButZero = tableItems.find(i => i.part_number.toLowerCase() === code.toLowerCase());
            setLastScan(code);
            setLastScanStatus(existsButZero ? 'no_stock' : 'unknown');
            return;
        }
        beep();
        setLastScan(code);
        setLastScanStatus('ok');
        setTableItems(prev => prev.map(i =>
            i.product_id === item.product_id && i.rack_id === item.rack_id
                ? { ...i, quantity: i.quantity + 1 }
                : i
        ));
    }, [tableItems]);

    const updateItem = (productId: number, rackId: string, field: keyof TableItem, value: any) => {
        setTableItems(prev => prev.map(i =>
            i.product_id === productId && i.rack_id === rackId ? { ...i, [field]: value } : i
        ));
    };

    const activeItems = tableItems.filter(i => i.quantity > 0);
    const hasActiveItems = activeItems.length > 0;
    const hasRelayItems = activeItems.some(i => i.is_relay);
    const canSubmit = !submitting && hasActiveItems;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!canSubmit) return;
        if (!confirm('Konfirmasi perubahan shopping? Stok akan disesuaikan.')) return;
        setSubmitting(true);
        router.put(route('shoppings.update', shopping.id), {
            partner_name: partnerName,
            shopping_date: shoppingDate,
            notes,
            items: tableItems
                .filter(i => i.quantity > 0)
                .map(i => ({ product_id: i.product_id, rack_id: i.rack_id || null, quantity: i.quantity })),
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
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rak / Zona</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Stok</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase w-24">Qty Kirim</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                        {tableItems.map((item) => {
                                            const key = `${item.product_id}-${item.rack_id}`;
                                            return (
                                            <tr
                                                key={key}
                                                className={
                                                    item.is_relay ? 'bg-amber-50 dark:bg-amber-900/10' :
                                                    item.stock === 0 ? 'bg-red-50 dark:bg-red-900/10 opacity-60' : ''
                                                }
                                            >
                                                <td className="px-3 py-2 text-xs font-mono whitespace-nowrap">{item.part_number}</td>
                                                <td className="px-3 py-2 text-sm">
                                                    {item.name}
                                                    {item.is_relay && (
                                                        <span className="ml-1.5 inline-flex items-center px-1 py-0.5 text-[10px] font-bold rounded bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                                            RELAY
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-sm">
                                                    <span className={item.is_relay ? 'text-amber-600 dark:text-amber-400 font-medium' : ''}>
                                                        {item.rack_label}
                                                    </span>
                                                    <span className="ml-1 text-xs text-gray-400">{item.rack_zone}</span>
                                                </td>
                                                <td className="px-3 py-2">
                                                    <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${
                                                        item.stock > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                                                    }`}>
                                                        {item.stock}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2 w-24">
                                                    <Input
                                                        type="number"
                                                        value={item.quantity}
                                                        onChange={(e) => updateItem(item.product_id, item.rack_id, 'quantity', parseInt(e.target.value) || 0)}
                                                        min={0}
                                                        max={item.stock}
                                                        disabled={item.stock === 0}
                                                    />
                                                </td>
                                            </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {pageErrors.items && <p className="mt-2 text-sm text-red-500">{pageErrors.items}</p>}
                    </ComponentCard>
                </div>

                {hasRelayItems && (
                    <div className="mt-3 flex items-center gap-2 px-3 py-2 text-sm text-amber-700 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
                        <span>💡</span> <span>Item dari stok Relay akan dikirim tanpa lokasi rak spesifik.</span>
                    </div>
                )}
                <div className="mt-4 flex gap-3">
                    <Button
                        type="submit"
                        disabled={!canSubmit}
                        icon={<CheckIcon className="w-4 h-4" />}
                    >
                        {submitting ? 'Menyimpan...'
                            : !hasActiveItems ? '⚠ Belum ada item'
                            : 'Update Shopping'}
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
