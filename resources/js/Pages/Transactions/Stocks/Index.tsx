import React, { useEffect, useMemo, useRef, useState } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import EmptyState from '../../../Tailadmin/components/common/EmptyState';
import Pagination from '../../../Tailadmin/components/common/Pagination';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';
import ScanButton from '../../../Components/ScanButton';

interface Filters {
    search: string;
    rack_id: number | null;
    zone: string;
    supplier_id: number | null;
    status: string;
    sort: string;
    product_id: number | null;
}

const STATUS_OPTIONS = [
    { value: '', label: 'Semua stok' },
    { value: 'rack', label: 'Ada di rak' },
    { value: 'relay', label: '⚠ Relay / tanpa rak' },
    { value: 'available', label: 'Qty > 0' },
    { value: 'zero', label: 'Qty 0 (kosong)' },
    { value: 'low', label: '⚠ Stok menipis (< min)' },
];

const SORT_OPTIONS = [
    { value: 'qty_desc', label: 'Qty terbesar' },
    { value: 'qty_asc', label: 'Qty terkecil' },
    { value: 'part_asc', label: 'Part number (A-Z)' },
    { value: 'name_asc', label: 'Nama produk (A-Z)' },
    { value: 'rack_asc', label: 'Kode rak (A-Z)' },
    { value: 'updated_desc', label: 'Terakhir diubah' },
];

const number = (value: number) => new Intl.NumberFormat('id-ID').format(value || 0);

export default function Index({ stocks, summary, racks = [], zones = [], suppliers = [], activeProduct = null, filters = {} }: any) {
    const permissions = (usePage().props.auth as any)?.user?.permissions || [];
    const canOpname = permissions.includes('stock opname');
    const canViewProduct = permissions.includes('view products');

    const current: Filters = {
        search: filters?.search || '',
        rack_id: filters?.rack_id || null,
        zone: filters?.zone || '',
        supplier_id: filters?.supplier_id || null,
        status: filters?.status || '',
        sort: filters?.sort || 'qty_desc',
        product_id: filters?.product_id || null,
    };

    const [search, setSearch] = useState(current.search);
    /** Nilai terakhir yang sudah dikirim ke server (biar tidak dobel request). */
    const lastSubmitted = useRef(current.search);

    /** Semua perubahan filter: buang `page` supaya tidak nyangkut di halaman lama. */
    const applyFilters = (next: Partial<Filters>) => {
        const params: Record<string, any> = { ...current, ...next };
        delete params.page;

        Object.keys(params).forEach((key) => {
            const value = params[key];
            if (value === '' || value === null || value === undefined) delete params[key];
        });

        router.get(route('stocks.index'), params, { preserveState: true, preserveScroll: true, replace: true });
    };

    // Pencarian diketik: tunggu 400ms baru dikirim (biar tidak spam request).
    useEffect(() => {
        if (search === lastSubmitted.current) return;

        const timer = setTimeout(() => {
            lastSubmitted.current = search;
            applyFilters({ search });
        }, 400);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const activeFilterCount = useMemo(() => {
        let count = 0;
        if (current.search) count++;
        if (current.rack_id) count++;
        if (current.zone) count++;
        if (current.supplier_id) count++;
        if (current.status) count++;
        if (current.product_id) count++;
        return count;
    }, [current.search, current.rack_id, current.zone, current.supplier_id, current.status, current.product_id]);

    const rackOptions = [
        { value: '', label: 'Semua rak' },
        ...racks.map((rack: any) => ({
            value: String(rack.id),
            label: rack.zone ? `${rack.code} — ${rack.zone}` : rack.code,
        })),
    ];

    const supplierOptions = [
        { value: '', label: 'Semua supplier' },
        ...suppliers.map((supplier: any) => ({ value: String(supplier.id), label: supplier.name })),
    ];

    const hasAnyStock = (summary?.rows ?? 0) > 0;

    return (
        <>
            <Head title="Inventori Stok" />
            <PageBreadcrumb pageTitle="Inventori Stok" />
            <ComponentCard title="Stok Saat Ini">
                {/* ── Ringkasan hasil filter (SQL agregat, bukan cuma halaman ini) ── */}
                <div className="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div className="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                        <div className="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Baris stok</div>
                        <div className="text-lg font-bold text-gray-800 dark:text-white/90">{number(summary?.rows)}</div>
                        <div className="text-[11px] text-gray-500">{number(summary?.products)} produk</div>
                    </div>
                    <div className="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                        <div className="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Total qty</div>
                        <div className="text-lg font-bold text-gray-800 dark:text-white/90">{number(summary?.quantity)}</div>
                        <div className="text-[11px] text-gray-500">pcs</div>
                    </div>
                    <div className={`rounded-xl border p-3 ${summary?.relay_quantity > 0 ? 'border-amber-300 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-900/10' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900'}`}>
                        <div className="text-[11px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400">Relay (tanpa rak)</div>
                        <div className="text-lg font-bold text-amber-700 dark:text-amber-400">{number(summary?.relay_quantity)}</div>
                        <div className="text-[11px] text-amber-600 dark:text-amber-400/80">{number(summary?.relay_rows)} baris</div>
                    </div>
                    <div className={`rounded-xl border p-3 ${summary?.low_rows > 0 ? 'border-red-300 bg-red-50 dark:border-red-500/30 dark:bg-red-900/10' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900'}`}>
                        <div className="text-[11px] font-semibold uppercase tracking-wide text-red-700 dark:text-red-400">Stok menipis</div>
                        <div className="text-lg font-bold text-red-700 dark:text-red-400">{number(summary?.low_rows)}</div>
                        <div className="text-[11px] text-red-600 dark:text-red-400/80">baris &lt; min stok</div>
                    </div>
                </div>

                {/* ── Pencarian & filter ── */}
                <div className="mb-4 rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="w-full flex-1 sm:min-w-[280px]">
                            <Label>Cari stok (bisa scan barcode)</Label>
                            <div className="flex gap-2">
                                <div className="min-w-0 flex-1">
                                    <Input
                                        type="text"
                                        value={search}
                                        placeholder="Part number, nama produk, rak, zona, supplier, model…"
                                        onChange={(e: any) => setSearch(e.target.value)}
                                        selectOnFocus
                                    />
                                </div>
                                <ScanButton
                                    title="Scan barcode part number"
                                    onScan={(code) => {
                                        lastSubmitted.current = code;
                                        setSearch(code);
                                        applyFilters({ search: code });
                                    }}
                                />
                            </div>
                            <p className="mt-1 text-[11px] text-gray-500">
                                Bisa beberapa kata (mis. <em>visor avanza</em>) — semua kata harus cocok. Ketik <em>relay</em> untuk melihat stok tanpa rak.
                            </p>
                        </div>

                        <div className="w-full sm:w-44">
                            <Label>Status</Label>
                            <SearchableSelect
                                options={STATUS_OPTIONS}
                                value={current.status}
                                onChange={(value) => applyFilters({ status: String(value) })}
                            />
                        </div>

                        <div className="w-full sm:w-48">
                            <Label>Rak</Label>
                            <SearchableSelect
                                options={rackOptions}
                                value={current.rack_id ? String(current.rack_id) : ''}
                                onChange={(value) => applyFilters({ rack_id: value ? Number(value) : null })}
                                placeholder="Semua rak"
                            />
                        </div>

                        <div className="w-full sm:w-40">
                            <Label>Zona</Label>
                            <SearchableSelect
                                options={[{ value: '', label: 'Semua zona' }, ...zones.map((zone: string) => ({ value: zone, label: zone }))]}
                                value={current.zone}
                                onChange={(value) => applyFilters({ zone: String(value) })}
                                placeholder="Semua zona"
                            />
                        </div>

                        <div className="w-full sm:w-52">
                            <Label>Supplier</Label>
                            <SearchableSelect
                                options={supplierOptions}
                                value={current.supplier_id ? String(current.supplier_id) : ''}
                                onChange={(value) => applyFilters({ supplier_id: value ? Number(value) : null })}
                                placeholder="Semua supplier"
                            />
                        </div>

                        <div className="w-full sm:w-48">
                            <Label>Urutkan</Label>
                            <SearchableSelect
                                options={SORT_OPTIONS}
                                value={current.sort}
                                onChange={(value) => applyFilters({ sort: String(value) })}
                            />
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            {activeFilterCount > 0 && (
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        lastSubmitted.current = '';
                                        setSearch('');
                                        router.get(route('stocks.index'), {}, { preserveState: false, replace: true });
                                    }}
                                >
                                    ✕ Reset ({activeFilterCount})
                                </Button>
                            )}
                            {canOpname && (
                                <Link href={route('stock-opname.index')}>
                                    <Button variant="outline">📋 Stock Opname</Button>
                                </Link>
                            )}
                        </div>
                    </div>

                    {current.product_id && activeProduct && (
                        <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                            <span className="rounded-full bg-brand-50 px-2 py-1 font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                                Produk: {activeProduct.part_number} — {activeProduct.name}
                            </span>
                            <button type="button" className="text-brand-600 hover:underline" onClick={() => applyFilters({ product_id: null })}>
                                hapus filter produk
                            </button>
                        </div>
                    )}
                </div>

                {stocks.data.length === 0 ? (
                    hasAnyStock || activeFilterCount > 0 ? (
                        <EmptyState
                            icon="🔍"
                            title="Tidak ada stok yang cocok"
                            message="Coba kata kunci lain, atau reset filter yang aktif."
                        />
                    ) : (
                        <EmptyState
                            icon="📊"
                            title="Belum ada stok"
                            message="Stok akan muncul setelah cycle diterima."
                        />
                    )
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="border-b border-[#E9ECEF] bg-[#F8F9FC]">
                                <tr>
                                    <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-[#6C757D]">Produk</th>
                                    <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-[#6C757D]">Part Number</th>
                                    <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-[#6C757D]">Rak</th>
                                    <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-[#6C757D]">Zona</th>
                                    <th className="w-24 px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-[#6C757D]">Qty</th>
                                    <th className="hidden w-24 px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-[#6C757D] md:table-cell">Total Masuk</th>
                                    <th className="hidden w-24 px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-[#6C757D] md:table-cell">Total Keluar</th>
                                    <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-[#6C757D]">Supplier</th>
                                </tr>
                            </thead>
                            <tbody>
                                {stocks.data.map((stock: any) => {
                                    const isRelay = !stock.rack;
                                    const minStock = stock.product?.min_stock;
                                    const isLow = !isRelay && stock.quantity > 0 && minStock != null && stock.quantity < minStock;
                                    const isEmpty = stock.quantity === 0;

                                    return (
                                        <tr
                                            key={stock.id}
                                            className={`border-b border-[#F1F3F5] transition-all duration-150 hover:bg-[#F8F9FC] ${isRelay ? 'bg-amber-50 dark:bg-amber-900/10' : ''}`}
                                        >
                                            <td className="px-4 py-3 text-sm text-[#1A1D23]">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <span>
                                                        {canViewProduct ? (
                                                            <Link href={route('products.show', stock.product_id)} className="hover:text-brand-600 hover:underline">
                                                                {stock.product?.name}
                                                            </Link>
                                                        ) : (
                                                            stock.product?.name
                                                        )}
                                                    </span>
                                                    {isRelay && (
                                                        <span className="inline-flex items-center rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                                            ⚠ RELAY
                                                        </span>
                                                    )}
                                                    {isLow && (
                                                        <span className="inline-flex items-center rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                                            MENIPIS
                                                        </span>
                                                    )}
                                                </div>
                                                {stock.product?.vehicleModel && (
                                                    <div className="text-[11px] text-gray-500">
                                                        {[stock.product.vehicleModel.brand, stock.product.vehicleModel.name].filter(Boolean).join(' ')}
                                                        {stock.product.vehicleModel.suffix ? ` · ${stock.product.vehicleModel.suffix}` : ''}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 font-mono text-sm text-[#1A1D23]">{stock.product?.part_number}</td>
                                            <td className="px-4 py-3 font-mono text-sm">
                                                {stock.rack ? (
                                                    <span className="text-[#1A1D23]">{stock.rack.code}</span>
                                                ) : (
                                                    <span className="font-medium text-amber-600 dark:text-amber-400">— Relay / Overflow</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-[13px] text-[#6C757D]">{stock.rack?.zone || '-'}</td>
                                            <td className={`px-4 py-3 text-sm font-semibold ${isEmpty ? 'text-gray-400' : isLow ? 'text-red-600' : 'text-[#1A1D23]'}`}>
                                                {stock.quantity}
                                            </td>
                                            <td className="hidden px-4 py-3 text-sm font-medium text-green-600 md:table-cell">{stock.total_in ?? 0}</td>
                                            <td className="hidden px-4 py-3 text-sm font-medium text-red-600 md:table-cell">{stock.total_out ?? 0}</td>
                                            <td className="px-4 py-3 text-[13px] text-[#6C757D]">
                                                <Link href={route('suppliers.show', stock.product?.supplier_id)} className="text-brand-500 hover:text-brand-700">
                                                    {stock.product?.supplier?.name || '-'}
                                                </Link>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                {stocks.total > 0 && (
                    <Pagination
                        prevUrl={stocks.prev_page_url}
                        perPage={stocks.per_page}
                        nextUrl={stocks.next_page_url}
                        currentPage={stocks.current_page}
                        lastPage={stocks.last_page}
                        from={stocks.from}
                        to={stocks.to}
                        total={stocks.total}
                    />
                )}
            </ComponentCard>
        </>
    );
}

Index.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
