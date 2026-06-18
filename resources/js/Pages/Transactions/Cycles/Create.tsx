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

export default function Create({ suppliers, products }: any) {
    const { data, setData, post, errors } = useForm({
        supplier_id: '',
        cycle_number: '',
        notes: '',
        items: [] as { product_id: string; quantity: number }[],
    });

    const [selectedProduct, setSelectedProduct] = useState('');
    const [selectedQty, setSelectedQty] = useState(1);

    const addItem = () => {
        if (!selectedProduct || selectedQty < 1) return;
        if (data.items.find((i: any) => i.product_id === selectedProduct)) {
            alert('Produk sudah ditambah.');
            return;
        }
        setData('items', [...data.items, { product_id: selectedProduct, quantity: selectedQty }]);
        setSelectedProduct('');
        setSelectedQty(1);
    };

    const removeItem = (index: number) => {
        setData('items', data.items.filter((_: any, i: number) => i !== index));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('cycles.store'));
    };

    return (
        <AppLayout>
            <Head title="Cycle Baru" />
            <PageBreadcrumb pageTitle="Tambah Cycle" />

            <form onSubmit={handleSubmit}>
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <ComponentCard
                        title="Info Cycle"
                        desc="Data cycle penerimaan barang"
                        action={
                            <Link href={route('cycles.index')}>
                                <Button variant="outline" size="sm" icon={<ArrowLeftIcon className="w-4 h-4" />}>Kembali</Button>
                            </Link>
                        }
                    >
                        <div className="space-y-5">
                            <div>
                                <Label>Supplier *</Label>
                                <SearchableSelect options={suppliers.map((s: any) => ({ value: s.id, label: s.name }))} value={data.supplier_id} onChange={(v) => setData('supplier_id', v as string)} />
                                {errors.supplier_id && <p className="mt-1 text-sm text-red-500">{errors.supplier_id}</p>}
                            </div>
                            <div>
                                <Label>Cycle Number *</Label>
                                <Input type="number" value={data.cycle_number} onChange={(e) => setData('cycle_number', e.target.value)} placeholder="contoh: 1" />
                                {errors.cycle_number && <p className="mt-1 text-sm text-red-500">{errors.cycle_number}</p>}
                            </div>
                            <div>
                                <Label>Catatan</Label>
                                <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} rows={2} className="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-3 py-2 text-sm" />
                            </div>
                        </div>
                    </ComponentCard>

                    <ComponentCard title="Tambah Item" desc="Pilih produk yang akan diterima">
                        <div className="flex gap-2 items-end mb-4">
                            <div className="flex-1">
                                <Label>Produk</Label>
                                <SearchableSelect
                                    options={products.map((p: any) => ({ value: p.id, label: `${p.part_number} - ${p.name}` }))}
                                    value={selectedProduct}
                                    onChange={(v) => setSelectedProduct(v as string)}
                                />
                            </div>
                            <div className="w-24">
                                <Label>Qty</Label>
                                <Input type="number" value={selectedQty} onChange={(e) => setSelectedQty(parseInt(e.target.value) || 0)} min={1} />
                            </div>
                            <Button type="button" onClick={addItem}>Tambah</Button>
                        </div>
                        {errors.items && <p className="mt-1 text-sm text-red-500">{errors.items}</p>}

                        {data.items.length > 0 && (
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 mt-3">
                                <thead className="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Part Number / Nama Part</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nama Part</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                        <th className="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                    {data.items.map((item: any, i: number) => {
                                        const p = products.find((pr: any) => String(pr.id) === String(item.product_id));
                                        return (
                                            <tr key={i}>
                                                <td className="px-3 py-2 text-sm font-mono">{p?.part_number || item.product_id}</td>
                                                <td className="px-3 py-2 text-sm">{p?.name || '-'}</td>
                                                <td className="px-3 py-2 text-sm">{item.quantity}</td>
                                                <td className="px-3 py-2"><button type="button" onClick={() => removeItem(i)} className="text-red-500 text-sm">✕</button></td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        )}
                    </ComponentCard>
                </div>
                <div className="mt-4 flex gap-3">
                    <Button type="submit" disabled={data.items.length === 0} icon={<CheckIcon className="w-4 h-4" />}>Simpan Cycle</Button>
                    <Button type="button" variant="outline" onClick={() => window.history.back()}>Batal</Button>
                </div>
            </form>
        </AppLayout>
    );
}
