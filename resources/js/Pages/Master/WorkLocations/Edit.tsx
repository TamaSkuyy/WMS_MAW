import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';

export default function Edit({ location }: any) {
    const { data, setData, put, errors } = useForm({ name: location.name });
    return (
        <AppLayout>
            <Head title={`Edit Lokasi Kerja - ${location.name}`} />
            <PageBreadcrumb pageTitle={`Edit: ${location.name}`} />
            <ComponentCard title="Edit Lokasi Kerja">
                <form onSubmit={(e) => { e.preventDefault(); put(route('work-locations.update', location.id)); }} className="space-y-4 max-w-md">
                    <div>
                        <Label>Nama *</Label>
                        <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit">Update</Button>
                        <Button type="button" variant="outline" onClick={() => window.history.back()}>Batal</Button>
                    </div>
                </form>
            </ComponentCard>
        </AppLayout>
    );
}
