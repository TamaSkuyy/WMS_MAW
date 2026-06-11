import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';

export default function Create() {
    const { data, setData, post, errors } = useForm({ name: '', description: '' });
    return (
        <AppLayout>
            <Head title="Tambah Kategori" />
            <PageBreadcrumb pageTitle="Tambah Kategori" />
            <ComponentCard title="Kategori Baru">
                <form onSubmit={(e) => { e.preventDefault(); post(route('product-categories.store')); }} className="space-y-4 max-w-md">
                    <div>
                        <Label>Nama *</Label>
                        <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="contoh: Body Parts" />
                        {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                    </div>
                    <div>
                        <Label>Deskripsi</Label>
                        <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={3} className="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-3 py-2 text-sm" placeholder="Deskripsi opsional..." />
                        {errors.description && <p className="mt-1 text-sm text-red-500">{errors.description}</p>}
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
