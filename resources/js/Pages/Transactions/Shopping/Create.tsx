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

export default function Create({ products, racks, shoppingLocations }: any) {
    const { errors = {} } = usePage().props as any;

    const [locationId, setLocationId] = useState('');
    const [shoppingDate, setShoppingDate] = useState(new Date().toISOString().split('T')[0]);
    const [notes, setNotes] = useState('');
    const [frameNumber, setFrameNumber] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [tableItems, setTableItems] = useState<TableItem[]>([]);
    const [scannerOpen, setScannerOpen] = useState(false);
    const [scanTarget, setScanTarget] = useState<'part' | 'frame'>('part');
    const [lastScan, setLastScan] = useState('');
    const [lastScanStatus, setLastScanStatus] = useState<'ok' | 'unknown' | 'no_stock' | null>(null);
    const scanSuccessRef = useRef(false);

    // Filters
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

    const rackMap = useMemo(() => {
        const map = new Map<string, { code: string; zone: string }>();
        racks.forEach((r: any) => map.set(String(r.id), { code: r.code, zone: r.zone || '-' }));
        return map;
    }, [racks]);

    useEffect(() => {
        const rows: TableItem[] = [];
        products.forEach((p: any) => {
            (p.stocks || []).forEach((s: any) => {
                const rid = s.rack_id ? String(s.rack_id) : '';
                rows.push({
                    product_id: p.id, part_number: p.part_number, name: p.name,
                    stock: s.quantity, rack_id: rid,
                    rack_label: rid ? (rackMap.get(rid)?.code ?? rid) : '⚠ Relay',
                    rack_zone: rid ? (rackMap.get(rid)?.zone ?? '-') : '—',
                    is_relay: !rid, quantity: 0,
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
        // Frame scan mode — fills frame_number on header
        if (scanTarget === 'frame') {
            setFrameNumber(code);
            setLastScan(code);
            setLastScanStatus('ok');
            return;
        }
        // Part scan mode — find and increment
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
    }, [tableItems, scanTarget]);

    // Auto-reopen scanner after successful part scan
    useEffect(() => {
        if (scanSuccessRef.current && !scannerOpen && scanTarget === 'part') {
            const t = setTimeout(() => {
                scanSuccessRef.current = false;
                setScannerOpen(true);
            }, 800);
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
    const canSubmit = !submitting && locationId !== '' && activeItems.length > 0 && !hasOverStock;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!canSubmit) return;
        const loc = shoppingLocations.find((l: any) => String(l.id) === String(locationId));
        if (!confirm(`Konfirmasi pengiriman ke "${loc?.name}"? Stok akan dikurangi.`)) return;
        setSubmitting(true);
        router.post(route('shoppings.store'), {
            shopping_location_id: locationId,
            shopping_date: shoppingDate,
            notes,
            frame_number: frameNumber || null,
            items: activeItems.map(i => ({ product_id: i.product_id, rack_id: i.rack_id || null, quantity: i.quantity })),
        }, { onFinish: () => setSubmitting(false) });
    };

    return (
        <>
            <Head title="Shopping Baru" />
            <PageBreadcrumb pageTitle="Tambah Shopping" />

            <form onSubmit={handleSubmit}>
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
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
                                <SearchableSelect
                                    options={shoppingLocations.map((l: any) => ({ value: l.id, label: l.name }))}
                                    value={locationId}
                                    onChange={(v) => setLocationId(v as string)}
                                    placeholder="Pilih lokasi tujuan..."
                                />
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

                            {lastScan && (
                                <div className={`text-xs px-3 py-2 rounded ${
                                    lastScanStatus === 'ok' ? 'bg-green-50 text-green-700' :
                                    lastScanStatus === 'no_stock' ? 'bg-yellow-50 text-yellow-700' :
                                    'bg-orange-50 text-orange-700'
                                }`}>
                                    {lastScanStatus === 'ok' ? '✓ ' : '✗ '}{lastScan}
                                    {lastScanStatus === 'no_stock' && ' — Stok habis'}
                                    {lastScanStatus === 'unknown' && ' — Part tidak dikenal'}
                                </div>
                            )}

                        </div>
                    </ComponentCard>

                    <div className="flex flex-col gap-6">
                    <ComponentCard
                        title={`Barang Dipilih (${activeItems.length} jenis, ${activeItems.reduce((s: number, i: any) => s + i.quantity, 0)} qty)`}
                        desc={activeItems.length === 0 ? 'Scan QR code atau cari produk di bawah' : 'Review sebelum simpan'}
                    >
                        {activeItems.length === 0 ? (
                            <div className="py-6 text-center border-2 border-dashed border-gray-200 rounded-lg">
                                <p className="text-3xl mb-2">📦</p>
                                <p className="text-sm text-gray-500">Belum ada barang dipilih</p>
                                <p className="text-xs text-gray-400 mt-1">Scan QR atau cari produk di bawah</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto max-h-60 overflow-y-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Part #</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rak</th>
                                            <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-24">Qty</th>
                                            <th className="px-3 py-2 w-8"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {activeItems.map((item, idx) => {
                                            const isOver = item.quantity > item.stock;
                                            return (
                                            <tr key={`active-${item.product_id}-${item.rack_id}`} className={isOver ? 'bg-red-50' : 'bg-blue-50/50'}>
                                                <td className="px-3 py-1.5 text-xs font-mono">{item.part_number}</td>
                                                <td className="px-3 py-1.5 text-xs">
                                                    {item.name}
                                                    {isOver && <span className="ml-1 text-[10px] text-red-600 font-medium">⚠ Stok hanya {item.stock}</span>}
                                                </td>
                                                <td className="px-3 py-1.5 text-xs">
                                                    {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : item.rack_label}
                                                </td>
                                                <td className="px-1 py-1.5 w-24">
                                                    <input type="text" inputMode="numeric"
                                                        value={item.quantity || ''}
                                                        onChange={(e) => updateItem(item.product_id, item.rack_id, 'quantity', parseInt(e.target.value) || 0)}
                                                        className={`w-full px-1 py-1.5 text-center text-xs border rounded ${isOver ? 'border-red-400 bg-red-50' : ''}`}
                                                    />
                                                </td>
                                                <td className="px-1 py-1.5">
                                                    <button type="button"
                                                        onClick={() => updateItem(item.product_id, item.rack_id, 'quantity', 0)}
                                                        className="text-red-400 hover:text-red-600 text-xs"
                                                    >✕</button>
                                                </td>
                                            </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </ComponentCard>

                    <ComponentCard title="Cari Produk" desc="Gunakan filter lalu isi qty">
                        <div className="mb-3 flex flex-wrap gap-3 items-end">
                            <div className="flex-1 min-w-[160px]">
                                <Input type="text" value={searchQuery} onChange={(e) => setSearchQuery(e.target.value)} placeholder="Cari part number / nama..." />
                            </div>
                            <div className="w-40">
                                <SearchableSelect
                                    options={suppliers.map((s: any) => ({ value: s.id, label: s.name }))}
                                    value={filterSupplierId} onChange={(v) => setFilterSupplierId(v as string)} placeholder="Supplier..." />
                            </div>
                            <div className="w-48">
                                <SearchableSelect
                                    options={vehicleModels.map((v: any) => ({ value: v.id, label: v.label }))}
                                    value={filterVehicleModelId} onChange={(v) => setFilterVehicleModelId(v as string)} placeholder="Model..." />
                            </div>
                            {(searchQuery || filterSupplierId || filterVehicleModelId) && (
                                <button type="button" onClick={() => { setSearchQuery(''); setFilterSupplierId(''); setFilterVehicleModelId(''); }} className="text-xs text-red-500 hover:text-red-700 px-2 py-1">✕ Reset</button>
                            )}
                        </div>
                        {(!searchQuery && !filterSupplierId && !filterVehicleModelId) ? (
                            <p className="text-sm text-gray-400 py-4 text-center">🔍 Gunakan filter di atas untuk mencari produk</p>
                        ) : filteredItems.length === 0 ? (
                            <p className="text-sm text-gray-400 py-4 text-center">Tidak ada produk ditemukan</p>
                        ) : (
                            <div className="overflow-x-auto max-h-80 overflow-y-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Part #</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rak</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Stok</th>
                                            <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-24">Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {filteredItems.map((item, idx) => (
                                            <tr key={`${item.product_id}-${item.rack_id}`} className={item.quantity > 0 ? 'bg-blue-50' : ''}>
                                                <td className="px-3 py-1.5 text-xs font-mono">{item.part_number}</td>
                                                <td className="px-3 py-1.5 text-xs">{item.name}</td>
                                                <td className="px-3 py-1.5 text-xs">
                                                    {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : <span>{item.rack_label} <span className="text-gray-400">({item.rack_zone})</span></span>}
                                                </td>
                                                <td className="px-3 py-1.5 text-xs">{item.stock}</td>
                                                <td className="px-1 py-1.5 w-24">
                                                    <input type="text" inputMode="numeric"
                                                        value={item.quantity || ''}
                                                        onChange={(e) => updateItem(item.product_id, item.rack_id, 'quantity', parseInt(e.target.value) || 0)}
                                                        className="w-full px-1 py-1.5 text-center text-xs border rounded"
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        <div className="pt-2 text-xs text-gray-400">Menampilkan {filteredItems.length} dari {tableItems.length} produk</div>
                    </ComponentCard>
                    </div>

                    {activeItems.length > 0 && (
                        <div className="flex flex-col gap-2 items-end">
                            {hasOverStock && (
                                <p className="text-sm text-red-600 font-medium">
                                    ⚠️ {overStockItems.length} barang melebihi stok tersedia — kurangi qty atau tidak bisa diproses
                                </p>
                            )}
                            <Button onClick={handleSubmit} disabled={!canSubmit || submitting} size="lg" icon={<CheckIcon className="w-4 h-4" />}>
                                {submitting ? 'Menyimpan...' : hasOverStock ? 'Tidak Bisa Diproses' : 'Proses Shopping'}
                            </Button>
                        </div>
                    )}
                </div>
            </form>

            <QrScanner
                isOpen={scannerOpen}
                onClose={() => setScannerOpen(false)}
                onScan={handleScan}
                mode={scanTarget === 'frame' ? 'barcode' : 'qr'}
                feedback={scanTarget === 'frame' ? { message: '📷 Scan barcode untuk Frame Number', type: 'ok' } : undefined}
            />
        </>
    );
}

Create.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
