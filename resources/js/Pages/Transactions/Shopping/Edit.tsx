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
import Badge from '../../../Tailadmin/components/ui/badge/Badge';
import Alert from '../../../Tailadmin/components/ui/alert/Alert';
import EmptyState from '../../../Tailadmin/components/common/EmptyState';
import QtyStepper from '../../../Components/QtyStepper';

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

export default function Edit({ shopping, products, racks, shoppingLocations }: any) {
    const { errors = {} } = usePage().props as any;

    const [locationId, setLocationId] = useState(String(shopping.shopping_location_id || ''));
    const [shoppingDate, setShoppingDate] = useState(() => {
        if (!shopping.shopping_date) return '';
        const d = new Date(shopping.shopping_date);
        if (isNaN(d.getTime())) return shopping.shopping_date;
        return d.toISOString().split('T')[0];
    });
    const [notes, setNotes] = useState(shopping.notes || '');
    const [frameNumber, setFrameNumber] = useState(shopping.frame_number || '');
    const [submitting, setSubmitting] = useState(false);
    const [tableItems, setTableItems] = useState<TableItem[]>([]);
    const [scannerOpen, setScannerOpen] = useState(false);
    const [scanTarget, setScanTarget] = useState<'part' | 'frame' | 'location'>('part');
    const [lastScan, setLastScan] = useState('');
    const [lastScanStatus, setLastScanStatus] = useState<'ok' | 'unknown' | 'no_stock' | null>(null);
    const scanSuccessRef = useRef(false);

    const [searchQuery, setSearchQuery] = useState('');
    const [filterSupplierId, setFilterSupplierId] = useState('');
    const [filterVehicleModelId, setFilterVehicleModelId] = useState('');

    const { suppliers, vehicleModels } = useMemo(() => {
        const sMap = new Map<number, { id: number; name: string }>();
        const vMap = new Map<number, { id: number; label: string }>();
        products.forEach((p: any) => {
            if (p.supplier && !sMap.has(p.supplier.id)) sMap.set(p.supplier.id, p.supplier);
            if (p.vehicle_model) {
                const vm = p.vehicle_model;
                const label = `${vm.brand} ${vm.name}${vm.suffix ? ' ' + vm.suffix : ''}`;
                if (!vMap.has(vm.id)) vMap.set(vm.id, { id: vm.id, label });
            }
        });
        return {
            suppliers: Array.from(sMap.values()).sort((a, b) => a.name.localeCompare(b.name)),
            vehicleModels: Array.from(vMap.values()).sort((a, b) => a.label.localeCompare(b.label)),
        };
    }, [products]);

    const savedItemsMap = useMemo<Record<number, { qty: number; rack_id: string }>>(() =>
        Object.fromEntries(
            (shopping.items ?? []).map((i: any) => [i.product_id, { qty: i.quantity, rack_id: i.rack_id ? String(i.rack_id) : '' }])
        ),
        []
    );
    const isFirstPopulate = useRef(true);

    const rackMap = useMemo(() => {
        const map = new Map<string, { code: string; zone: string }>();
        racks.forEach((r: any) => map.set(String(r.id), { code: r.code, zone: r.zone || '-' }));
        return map;
    }, [racks]);

    useEffect(() => {
        const rows: TableItem[] = [];
        const useMap: Record<number, { qty: number; rack_id: string }> = isFirstPopulate.current ? savedItemsMap : {};
        if (isFirstPopulate.current) isFirstPopulate.current = false;
        products.forEach((p: any) => {
            (p.stocks || []).forEach((s: any) => {
                const rid = s.rack_id ? String(s.rack_id) : '';
                const prefill = useMap[p.id];
                rows.push({
                    product_id: p.id, part_number: p.part_number, name: p.name,
                    stock: s.quantity, rack_id: rid,
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
    }, [rackMap]);

    const handleScan = useCallback((code: string) => {
        if (scanTarget === 'frame') {
            setFrameNumber(code);
            setLastScan(code);
            setLastScanStatus('ok');
            return;
        }
        // Location scan mode — auto-select lokasi tujuan by barcode
        if (scanTarget === 'location') {
            const loc = shoppingLocations.find(
                (l: any) => (l.barcode || '').toLowerCase() === code.toLowerCase()
            );
            if (!loc) {
                setLastScan(code);
                setLastScanStatus('unknown');
                return;
            }
            beep();
            setLocationId(String(loc.id));
            setLastScan(code);
            setLastScanStatus('ok');
            return;
        }
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
        scanSuccessRef.current = true;
        setLastScan(code);
        setLastScanStatus('ok');
        setTableItems(prev => prev.map(i =>
            i.product_id === item.product_id && i.rack_id === item.rack_id
                ? { ...i, quantity: i.quantity + 1 }
                : i
        ));
    }, [tableItems, scanTarget, shoppingLocations]);

    useEffect(() => {
        if (scanSuccessRef.current && !scannerOpen && scanTarget === 'part') {
            const t = setTimeout(() => { scanSuccessRef.current = false; setScannerOpen(true); }, 800);
            return () => clearTimeout(t);
        }
    }, [scannerOpen, scanTarget]);

    const updateItem = (productId: number, rackId: string, field: keyof TableItem, value: any) => {
        setTableItems(prev => prev.map(i =>
            i.product_id === productId && i.rack_id === rackId ? { ...i, [field]: value } : i
        ));
    };

    const filteredItems = useMemo(() => {
        let items = tableItems;
        if (searchQuery) {
            const q = searchQuery.toLowerCase();
            items = items.filter(i => i.part_number.toLowerCase().includes(q) || i.name.toLowerCase().includes(q));
        }
        if (filterSupplierId) {
            const prodIds = new Set(
                products.filter((p: any) => String(p.supplier_id) === String(filterSupplierId)).map((p: any) => p.id)
            );
            items = items.filter(i => prodIds.has(i.product_id));
        }
        if (filterVehicleModelId) {
            const prodIds = new Set(
                products.filter((p: any) => String(p.vehicle_model_id) === String(filterVehicleModelId)).map((p: any) => p.id)
            );
            items = items.filter(i => prodIds.has(i.product_id));
        }
        return items;
    }, [tableItems, searchQuery, filterSupplierId, filterVehicleModelId, products]);

    const activeItems = tableItems.filter(i => i.quantity > 0);
    const overStockItems = activeItems.filter(i => i.quantity > i.stock);
    const hasOverStock = overStockItems.length > 0;
    const canSubmit = !submitting && locationId !== '' && !hasOverStock;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!canSubmit) return;
        const loc = shoppingLocations.find((l: any) => String(l.id) === String(locationId));
        const itemCount = activeItems.length;
        const msg = itemCount > 0
            ? `Konfirmasi perubahan pengiriman ke "${loc?.name}" dengan ${itemCount} item?`
            : `Konfirmasi perubahan Shopping ke "${loc?.name}" tanpa item?`;
        if (!confirm(msg)) return;
        setSubmitting(true);
        router.put(route('shoppings.update', shopping.id), {
            shopping_location_id: locationId,
            shopping_date: shoppingDate,
            notes,
            frame_number: frameNumber || null,
            items: activeItems.map(i => ({ product_id: i.product_id, rack_id: i.rack_id || null, quantity: i.quantity })),
        }, { onFinish: () => setSubmitting(false) });
    };

    return (
        <>
            <Head title={`Edit Shopping - ${shopping.shopping_location?.name || ''}`} />
            <PageBreadcrumb pageTitle="Edit Shopping" />

            <form onSubmit={handleSubmit}>
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-12">
                    <div className="xl:col-span-5 flex flex-col gap-6">
                    <ComponentCard
                        title="Detail Shopping"
                        action={
                            <Link href={route('shoppings.index')}>
                                <Button variant="outline" size="sm" icon={<ArrowLeftIcon className="w-4 h-4" />}>Kembali</Button>
                            </Link>
                        }
                    >
                        <div className="space-y-4">
                            <div>
                                <Label>Lokasi Tujuan *</Label>
                                <div className="flex gap-2">
                                    <div className="flex-1">
                                        <SearchableSelect
                                            options={shoppingLocations.map((l: any) => ({ value: l.id, label: l.name }))}
                                            value={locationId}
                                            onChange={(v) => setLocationId(v as string)}
                                            placeholder="Pilih lokasi tujuan..."
                                        />
                                    </div>
                                    <Button type="button" variant="outline" size="sm" onClick={() => { setScanTarget('location'); setScannerOpen(true); }} title="Scan barcode lokasi">
                                        📷
                                    </Button>
                                </div>
                                {errors.shopping_location_id && <p className="mt-1 text-sm text-red-500">{errors.shopping_location_id}</p>}
                            </div>
                            <div>
                                <Label>Tanggal Kirim</Label>
                                <Input type="date" value={shoppingDate} onChange={(e) => setShoppingDate(e.target.value)} />
                                {errors.shopping_date && <p className="mt-1 text-sm text-red-500">{errors.shopping_date}</p>}
                            </div>
                            <div>
                                <Label>Catatan</Label>
                                <Input type="text" value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Opsional" />
                                {errors.notes && <p className="mt-1 text-sm text-red-500">{errors.notes}</p>}
                            </div>

                            {/* Frame Number — scan barcode */}
                            <div>
                                <Label>Frame Number</Label>
                                <div className="flex gap-2">
                                    <Input type="text" value={frameNumber} onChange={(e) => setFrameNumber(e.target.value)} placeholder="Scan atau ketik..." />
                                    <Button type="button" variant="outline" size="sm" onClick={() => { setScanTarget('frame'); setScannerOpen(true); }} title="Scan barcode frame">
                                        📷
                                    </Button>
                                </div>
                                {errors.frame_number && <p className="mt-1 text-sm text-red-500">{errors.frame_number}</p>}
                            </div>

                            <Button onClick={() => { setScanTarget('part'); setScannerOpen(true); }} className="w-full" type="button">
                                📷 Scan Part / QR Code
                            </Button>

                            {lastScan && lastScanStatus !== null && (
                                <Alert
                                    variant={lastScanStatus === 'ok' ? 'success' : lastScanStatus === 'no_stock' ? 'warning' : 'error'}
                                    title={lastScanStatus === 'ok' ? 'Scan Berhasil' : lastScanStatus === 'no_stock' ? 'Stok Habis' : (scanTarget === 'location' ? 'Barcode Tidak Dikenal' : 'Part Tidak Dikenal')}
                                    message={lastScan}
                                />
                            )}

                        </div>
                    </ComponentCard>

                    <ComponentCard
                        title={`Barang Dipilih (${activeItems.length} jenis, ${activeItems.reduce((s: number, i: any) => s + i.quantity, 0)} qty)`}
                        desc={activeItems.length === 0 ? 'Scan QR code atau cari produk di bawah' : 'Review sebelum simpan'}
                    >
                        {activeItems.length === 0 ? (
                            <EmptyState icon="📦" title="Belum ada barang dipilih" message="Scan QR atau isi qty di tabel Cari Produk." />
                        ) : (
                            <>
                            {/* Desktop (≥ md): tabel */}
                            <div className="hidden md:block overflow-x-auto max-h-60 overflow-y-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th className="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                            <th className="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Rak</th>
                                            <th className="px-4 py-2 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider w-48">Qty</th>
                                            <th className="px-4 py-2 w-8"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {activeItems.map((item) => {
                                            const isOver = item.quantity > item.stock;
                                            return (
                                            <tr key={`active-${item.product_id}-${item.rack_id}`} className={isOver ? 'bg-red-50' : 'bg-blue-50/50'}>
                                                <td className="px-4 py-2.5">
                                                    <div className="font-mono text-xs text-gray-500 dark:text-gray-400">{item.part_number}</div>
                                                    <div className="text-sm text-gray-800 dark:text-gray-200">{item.name}</div>
                                                    {isOver && <span className="text-[11px] text-red-600 font-medium">⚠ Stok hanya {item.stock}</span>}
                                                </td>
                                                <td className="px-4 py-2.5 text-sm">
                                                    {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : item.rack_label}
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <QtyStepper
                                                        value={item.quantity}
                                                        onChange={(n) => updateItem(item.product_id, item.rack_id, 'quantity', n)}
                                                        max={item.stock}
                                                    />
                                                </td>
                                                <td className="px-2 py-2.5">
                                                    <button type="button"
                                                        onClick={() => updateItem(item.product_id, item.rack_id, 'quantity', 0)}
                                                        className="inline-flex items-center justify-center h-11 w-11 rounded-lg text-red-400 hover:text-red-600 text-lg"
                                                    >✕</button>
                                                </td>
                                            </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>

                            {/* Mobile (< md): kartu */}
                            <div className="md:hidden space-y-3">
                                {activeItems.map((item) => {
                                    const isOver = item.quantity > item.stock;
                                    return (
                                        <div key={`active-m-${item.product_id}-${item.rack_id}`} className={`rounded-xl border p-4 ${isOver ? 'border-red-300 bg-red-50 dark:bg-red-900/10' : 'border-[#E9ECEF] dark:border-gray-700 bg-blue-50/50 dark:bg-gray-900'}`}>
                                            <div className="flex justify-between items-start gap-2 mb-3">
                                                <div>
                                                    <div className="font-mono text-xs text-gray-500 dark:text-gray-400">{item.part_number}</div>
                                                    <div className="text-sm font-medium text-gray-800 dark:text-gray-200">{item.name}</div>
                                                    {isOver && <span className="text-[11px] text-red-600 font-medium">⚠ Stok hanya {item.stock}</span>}
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => updateItem(item.product_id, item.rack_id, 'quantity', 0)}
                                                    className="inline-flex items-center justify-center h-11 w-11 rounded-lg text-red-400 hover:text-red-600 text-lg"
                                                    title="Hapus"
                                                >✕</button>
                                            </div>
                                            <div className="flex items-center justify-between gap-3">
                                                <div className="text-sm text-gray-600 dark:text-gray-300">
                                                    {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : item.rack_label}
                                                </div>
                                                <QtyStepper
                                                    value={item.quantity}
                                                    onChange={(n) => updateItem(item.product_id, item.rack_id, 'quantity', n)}
                                                    max={item.stock}
                                                />
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                            </>
                        )}
                    </ComponentCard>

                    </div>

                    <div className="xl:col-span-7">
                    <ComponentCard title="Cari Produk" desc="Gunakan filter lalu isi qty">
                        <div className="mb-4 space-y-3">
                            <div>
                                <Label>Cari Produk</Label>
                                <Input type="text" value={searchQuery} onChange={(e) => setSearchQuery(e.target.value)} placeholder="Cari part number / nama..." />
                            </div>
                            <div className="flex flex-wrap items-end gap-3">
                                <div className="w-full sm:w-48">
                                    <Label>Supplier</Label>
                                    <SearchableSelect
                                        options={suppliers.map((s: any) => ({ value: s.id, label: s.name }))}
                                        value={filterSupplierId} onChange={(v) => setFilterSupplierId(v as string)} placeholder="Semua supplier" />
                                </div>
                                <div className="w-full sm:w-56">
                                    <Label>Model</Label>
                                    <SearchableSelect
                                        options={vehicleModels.map((v: any) => ({ value: v.id, label: v.label }))}
                                        value={filterVehicleModelId} onChange={(v) => setFilterVehicleModelId(v as string)} placeholder="Semua model" />
                                </div>
                                {(searchQuery || filterSupplierId || filterVehicleModelId) && (
                                    <button type="button" onClick={() => { setSearchQuery(''); setFilterSupplierId(''); setFilterVehicleModelId(''); }} className="mb-1 text-sm text-red-500 hover:text-red-700">✕ Reset</button>
                                )}
                            </div>
                        </div>
                        {(!searchQuery && !filterSupplierId && !filterVehicleModelId) ? (
                            <p className="text-sm text-gray-400 py-4 text-center">🔍 Gunakan filter di atas untuk mencari produk</p>
                        ) : filteredItems.length === 0 ? (
                            <p className="text-sm text-gray-400 py-4 text-center">Tidak ada produk ditemukan</p>
                        ) : (
                            <>
                            {/* Desktop (≥ md): tabel */}
                            <div className="hidden md:block overflow-x-auto max-h-80 overflow-y-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Part #</th>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Rak</th>
                                            <th className="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Stok</th>
                                            <th className="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider w-48">Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {filteredItems.map((item) => (
                                            <tr key={`${item.product_id}-${item.rack_id}`} className={item.quantity > 0 ? 'bg-blue-50' : ''}>
                                                <td className="px-4 py-2.5 text-xs font-mono">{item.part_number}</td>
                                                <td className="px-4 py-2.5 text-sm">{item.name}</td>
                                                <td className="px-4 py-2.5 text-sm">
                                                    {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : item.rack_label}
                                                </td>
                                                <td className="px-4 py-2.5 text-sm text-center tabular-nums">{item.stock}</td>
                                                <td className="px-4 py-2.5">
                                                    <QtyStepper
                                                        value={item.quantity}
                                                        onChange={(n) => updateItem(item.product_id, item.rack_id, 'quantity', n)}
                                                        max={item.stock}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {/* Mobile (< md): kartu */}
                            <div className="md:hidden space-y-3">
                                {filteredItems.map((item) => (
                                    <div key={`m-${item.product_id}-${item.rack_id}`} className={`rounded-xl border p-4 ${item.quantity > 0 ? 'border-blue-200 bg-blue-50 dark:bg-blue-900/10' : 'border-[#E9ECEF] dark:border-gray-700 bg-white dark:bg-gray-900'}`}>
                                        <div className="flex justify-between items-start gap-2 mb-3">
                                            <div>
                                                <div className="font-mono text-xs text-gray-500 dark:text-gray-400">{item.part_number}</div>
                                                <div className="text-sm font-medium text-gray-800 dark:text-gray-200">{item.name}</div>
                                            </div>
                                            <span className="h-fit whitespace-nowrap px-2 py-1 text-xs font-medium rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                                Stok: {item.stock}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="text-sm text-gray-600 dark:text-gray-300">
                                                {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : item.rack_label}
                                            </div>
                                            <QtyStepper
                                                value={item.quantity}
                                                onChange={(n) => updateItem(item.product_id, item.rack_id, 'quantity', n)}
                                                max={item.stock}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                            </>
                        )}
                        <div className="pt-2 text-xs text-gray-400">Menampilkan {filteredItems.length} dari {tableItems.length} produk</div>
                    </ComponentCard>
                    </div>
                </div>

                {/* Sticky action footer — di luar grid, tetap di dalam <form> */}
                <div className="sticky bottom-4 z-10 mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#E9ECEF] bg-white px-5 py-4 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                    <div className="flex flex-wrap items-center gap-3 text-sm">
                        <span className="font-medium text-[#1A1D23] dark:text-white">
                            {activeItems.length} jenis &bull; {activeItems.reduce((s: number, i: any) => s + i.quantity, 0)} qty
                        </span>
                        {hasOverStock && (
                            <Badge color="error" variant="light" size="sm">
                                ⚠️ {overStockItems.length} barang melebihi stok
                            </Badge>
                        )}
                    </div>
                    <Button type="button" onClick={handleSubmit} disabled={!canSubmit || submitting} icon={<CheckIcon className="w-4 h-4" />}>
                        {submitting ? 'Menyimpan...' : hasOverStock ? 'Tidak Bisa Diproses' : 'Simpan Perubahan'}
                    </Button>
                </div>
            </form>

            <QrScanner
                isOpen={scannerOpen}
                onClose={() => setScannerOpen(false)}
                onScan={handleScan}
                mode={scanTarget === 'frame' || scanTarget === 'location' ? 'barcode' : 'qr'}
                feedback={scanTarget === 'frame' ? { message: '📷 Scan barcode untuk Frame Number', type: 'ok' } : undefined}
            />
        </>
    );
}

Edit.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
