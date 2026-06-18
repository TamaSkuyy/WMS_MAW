import React, { useState } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeftIcon, CheckIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';
import { Link } from '@inertiajs/react';

export default function Edit({ shipment, products, racks }: any) {
    const { data, setData, put, errors } = useForm({
        partner_name: shipment.partner_name || '',
        shipment_date: shipment.shipment_date || '',
        notes: shipment.notes || '',
        items: shipment.items.map((i: any) => ({ product_id: String(i.product_id), rack_id: String(i.rack_id), quantity: i.quantity })),
    });

    const [selProduct, setSelProduct] = useState('');
    const [selRack, setSelRack] = useState('');
    const [selQty, setSelQty] = useState(1);

    const addItem = () => {
        if (!selProduct || !selRack || selQty < 1) return;
        setData('items', [...data.items, { product_id: selProduct, rack_id: selRack, quantity: selQty }]);
        setSelProduct('');
        setSelRack('');
        setSelQty(1);
    };

    const removeItem = (i: number) => setData('items', data.items.filter((_: any, idx: number) => idx !== i));

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('shipments.update', shipment.id));
    };

    return (
        <AppLayout>
            <Head title="Edit Shipment" />
            <PageBreadcrumb pageTitle={`Edit: ${shipment.partner_name}`} />

            <form onSubmit={handleSubmit}>
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <ComponentCard
                        title="Info Shipment"
                        desc="Perbarui data pengiriman"
                        action={
                            <Link href={route('shipments.index')}>
                                <Button variant="outline" size="sm" icon={<ArrowLeftIcon className="w-4 h-4" />}>Kembali</Button>
                            </Link>
                        }
                    >
                        <div className="space-y-5">
                            <div>
                                <Label>Nama Mitra *</Label>
                                <Input type="text" value={data.partner_name} onChange={(e) => setData('partner_name', e.target.value)} />
                                {errors.partner_name && <p className="mt-1 text-sm text-red-500">{errors.partner_name}</p>}
                            </div>
                            <div>
                                <Label>Tanggal Kirim *</Label>
                                <Input type="date" value={data.shipment_date} onChange={(e) => setData('shipment_date', e.target.value)} />
                                {errors.shipment_date && <p className="mt-1 text-sm text-red-500">{errors.shipment_date}</p>}
                            </div>
                            <div>
                                <Label>Catatan</Label>
                                <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} rows={2} className="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-3 py-2 text-sm" />
                            </div>
                        </div>
                    </ComponentCard>
                    <ComponentCard title="Item" desc="Daftar produk dalam shipment">
                        <div className="flex gap-2 items-end mb-4 flex-wrap">
                            <div className="flex-1 min-w-[150px]">
                                <Label>Produk</Label>
                                <SearchableSelect
                                    options={products.map((p: any) => ({ value: p.id, label: `${p.part_number} - ${p.name}` }))}
                                    value={selProduct}
                                    onChange={(v) => setSelProduct(v as string)}
                                />
                            </div>
                            <div className="w-24">
                                <Label>Rak</Label>
                                <SearchableSelect
                                    options={racks.map((r: any) => ({ value: r.id, label: r.code }))}
                                    value={selRack}
                                    onChange={(v) => setSelRack(v as string)}
                                />
                            </div>
                            <div className="w-20">
                                <Label>Qty</Label>
                                <Input type="number" value={selQty} onChange={(e) => setSelQty(parseInt(e.target.value) || 0)} min={1} />
                            </div>
                            <Button type="button" onClick={addItem}>Tambah</Button>
                        </div>
                        {data.items.length > 0 && (
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 mt-3">
                                <thead className="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rak</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                    {data.items.map((item: any, i: number) => {
                                        const p = products.find((pr: any) => String(pr.id) === String(item.product_id));
                                        const r = racks.find((rk: any) => String(rk.id) === String(item.rack_id));
                                        return (
                                            <tr key={i}>
                                                <td className="px-3 py-2 text-sm">{p?.name || '-'}</td>
                                                <td className="px-3 py-2 text-sm font-mono">{r?.code || '-'}</td>
                                                <td className="px-3 py-2 text-sm">{item.quantity}</td>
                                                <td className="px-3 py-2"><button type="button" onClick={() => removeItem(i)} className="text-red-500 text-sm">&#x2715;</button></td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        )}
                    </ComponentCard>
                </div>
                <div className="mt-4 flex gap-3">
                    <Button type="submit" disabled={data.items.length === 0} icon={<CheckIcon className="w-4 h-4" />}>Update Shipment</Button>
                    <Button type="button" variant="outline" onClick={() => window.history.back()}>Batal</Button>
                </div>
            </form>
        </AppLayout>
    );
}
