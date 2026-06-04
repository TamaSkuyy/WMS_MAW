import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';

export default function Edit({ rack }: any) {
    const { data, setData, put, errors } = useForm({ code: rack.code, zone: rack.zone });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('racks.update', rack.id));
    };

    return (
        <AppLayout>
            <Head title={`Edit Rack - ${rack.code}`} />
            <PageBreadcrumb pageTitle={`Edit: ${rack.code}`} />
            <ComponentCard title="Edit Rack">
                <form onSubmit={handleSubmit} className="space-y-4 max-w-md">
                    <div>
                        <Label>Code *</Label>
                        <Input type="text" value={data.code} onChange={(e) => setData('code', e.target.value)} />
                        {errors.code && <p className="mt-1 text-sm text-red-500">{errors.code}</p>}
                    </div>
                    <div>
                        <Label>Zone *</Label>
                        <Input type="text" value={data.zone} onChange={(e) => setData('zone', e.target.value)} />
                        {errors.zone && <p className="mt-1 text-sm text-red-500">{errors.zone}</p>}
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit">Update</Button>
                        <Button type="button" variant="outline" onClick={() => window.history.back()}>Cancel</Button>
                    </div>
                </form>
            </ComponentCard>
        </AppLayout>
    );
}
