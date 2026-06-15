import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';
import Select from '../../../Tailadmin/components/form/Select';

export default function Create() {
    const { data, setData, post, errors } = useForm({ name: '', level: '' });
    const levelOptions = [
        { value: 'Staff', label: 'Staff' },
        { value: 'Leader', label: 'Leader' },
        { value: 'Supervisor', label: 'Supervisor' },
        { value: 'Manager', label: 'Manager' },
        { value: 'Kepala Bagian', label: 'Kepala Bagian' },
        { value: 'Direktur', label: 'Direktur' },
    ];

    return (
        <AppLayout>
            <Head title="Tambah Jabatan" />
            <PageBreadcrumb pageTitle="Tambah Jabatan" />
            <ComponentCard title="Jabatan Baru">
                <form onSubmit={(e) => { e.preventDefault(); post(route('job-positions.store')); }} className="space-y-4 max-w-md">
                    <div>
                        <Label>Nama *</Label>
                        <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="contoh: Accounting" />
                        {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                    </div>
                    <div>
                        <Label>Level</Label>
                        <Select options={levelOptions} placeholder="-- Pilih Level --" defaultValue={data.level} onChange={(val) => setData('level', val)} />
                        {errors.level && <p className="mt-1 text-sm text-red-500">{errors.level}</p>}
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
