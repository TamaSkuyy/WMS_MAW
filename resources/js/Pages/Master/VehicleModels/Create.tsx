import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';

export default function Create() {
    const { data, setData, post, errors } = useForm({ name: '', brand: '', suffix: '' });
    return (
        <AppLayout>
            <Head title="Tambah Model" />
            <PageBreadcrumb pageTitle="Tambah Model Kendaraan" />
            <ComponentCard title="Model Baru">
                <form onSubmit={(e) => { e.preventDefault(); post(route('vehicle-models.store')); }} className="space-y-4 max-w-md">
                    <div>
                        <Label>Merek *</Label>
                        <Input type="text" value={data.brand} onChange={(e) => setData('brand', e.target.value)} placeholder="contoh: Toyota" />
                        {errors.brand && <p className="mt-1 text-sm text-red-500">{errors.brand}</p>}
                    </div>
                    <div>
                        <Label>Model *</Label>
                        <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="contoh: Fortuner" />
                        {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                    </div>
                    <div>
                        <Label>Suffix (Varian)</Label>
                        <Input type="text" value={data.suffix} onChange={(e) => setData('suffix', e.target.value)} placeholder="contoh: VRZ, TRD, SRZ (kosongkan jika tidak ada)" />
                        {errors.suffix && <p className="mt-1 text-sm text-red-500">{errors.suffix}</p>}
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit">Simpan</Button>
                        <Button type="button" variant="outline" onClick={() => window.history.back()}>Batal</Button>
                    </div>
                </form>
            </ComponentCard>
        </AppLayout>
    );
}
