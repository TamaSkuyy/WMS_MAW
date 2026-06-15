import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';

export default function Edit({ department }: any) {
    const { data, setData, put, errors } = useForm({ name: department.name });
    return (
        <AppLayout>
            <Head title={`Edit Departemen - ${department.name}`} />
            <PageBreadcrumb pageTitle={`Edit: ${department.name}`} />
            <ComponentCard title="Edit Departemen">
                <form onSubmit={(e) => { e.preventDefault(); put(route('departments.update', department.id)); }} className="space-y-4 max-w-md">
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
