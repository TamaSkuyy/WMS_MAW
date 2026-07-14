import React from 'react';
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

export default function Create({ vehicleModels, categories, suppliers, racks }: any) {
    const { data, setData, post, errors } = useForm({
        part_number: '',
        name: '',
        vehicle_model_id: '',
        supplier_id: '',
        category_id: '',
        unit: 'pcs',
        description: '',
        is_active: true,
        default_rack_id: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('products.store'));
    };

    return (
        <>
            <Head title="Tambah Produk" />
            <PageBreadcrumb pageTitle="Tambah Produk" />

            <div className="max-w-2xl">
                <ComponentCard
                    title="Produk Baru"
                    desc="Tambahkan part/suku cadang baru"
                    action={
                        <Link href={route('products.index')}>
                            <Button variant="outline" size="sm" icon={<ArrowLeftIcon className="w-4 h-4" />}>Kembali</Button>
                        </Link>
                    }
                >
                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div>
                            <Label>Part Number *</Label>
                            <Input type="text" value={data.part_number} onChange={(e) => setData('part_number', e.target.value)} placeholder="contoh: P5188-0KA03" />
                            {errors.part_number && <p className="mt-1 text-sm text-red-500">{errors.part_number}</p>}
                        </div>
                        <div>
                            <Label>Nama Part *</Label>
                            <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="contoh: Grade Emblem (VRZ)" />
                            {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Model Kendaraan *</Label>
                                <SearchableSelect
                                    options={vehicleModels.map((m: any) => ({ value: m.id, label: `${m.brand} ${m.name}${m.suffix ? ' ' + m.suffix : ''}` }))}
                                    value={data.vehicle_model_id}
                                    onChange={(value) => setData('vehicle_model_id', value as string)}
                                />
                                {errors.vehicle_model_id && <p className="mt-1 text-sm text-red-500">{errors.vehicle_model_id}</p>}
                            </div>
                            <div>
                                <Label>Supplier *</Label>
                                <SearchableSelect
                                    options={suppliers.map((s: any) => ({ value: s.id, label: s.name }))}
                                    value={data.supplier_id}
                                    onChange={(value) => setData('supplier_id', value as string)}
                                />
                                {errors.supplier_id && <p className="mt-1 text-sm text-red-500">{errors.supplier_id}</p>}
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Kategori *</Label>
                                <SearchableSelect
                                    options={categories.map((c: any) => ({ value: c.id, label: c.name }))}
                                    value={data.category_id}
                                    onChange={(value) => setData('category_id', value as string)}
                                />
                                {errors.category_id && <p className="mt-1 text-sm text-red-500">{errors.category_id}</p>}
                            </div>
                            <div>
                                <Label>Satuan *</Label>
                                <SearchableSelect
                                    options={[
                                        { value: 'pcs', label: 'Pcs' },
                                        { value: 'set', label: 'Set' },
                                        { value: 'box', label: 'Box' },
                                        { value: 'unit', label: 'Unit' },
                                    ]}
                                    value={data.unit}
                                    onChange={(value) => setData('unit', value as string)}
                                />
                                {errors.unit && <p className="mt-1 text-sm text-red-500">{errors.unit}</p>}
                            </div>
                        </div>

                        <div>
                            <Label>Rack Default</Label>
                            <SearchableSelect
                                options={[
                                    { value: '', label: 'Tidak ada (pilih saat receiving)' },
                                    ...racks.map((r: any) => ({ value: r.id, label: `${r.code}${r.zone ? ' — ' + r.zone : ''}` })),
                                ]}
                                value={data.default_rack_id}
                                onChange={(value) => setData('default_rack_id', value as string)}
                            />
                            {errors.default_rack_id && <p className="mt-1 text-sm text-red-500">{errors.default_rack_id}</p>}
                        </div>

                        <div>
                            <Label>Deskripsi</Label>
                            <textarea
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                rows={3}
                                className="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-3 py-2 text-sm"
                                placeholder="Catatan tambahan..."
                            />
                            {errors.description && <p className="mt-1 text-sm text-red-500">{errors.description}</p>}
                        </div>

                        <div className="flex gap-3 pt-4 border-t border-[#F1F3F5]">
                            <Button type="submit" icon={<CheckIcon className="w-4 h-4" />}>Simpan</Button>
                            <Button type="button" variant="outline" onClick={() => window.history.back()}>Batal</Button>
                        </div>
                    </form>
                </ComponentCard>
            </div>
        </>
    );
}

Create.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
