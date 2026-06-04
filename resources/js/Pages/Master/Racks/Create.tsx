import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';

export default function Create() {
    const { data, setData, post, errors } = useForm({ code: '', zone: '' });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('racks.store'));
    };

    return (
        <AppLayout>
            <Head title="Add Rack" />
            <PageBreadcrumb pageTitle="Add Rack" />
            <ComponentCard title="New Rack">
                <form onSubmit={handleSubmit} className="space-y-4 max-w-md">
                    <div>
                        <Label>Code *</Label>
                        <Input type="text" value={data.code} onChange={(e) => setData('code', e.target.value)} placeholder="e.g. A-01" />
                        {errors.code && <p className="mt-1 text-sm text-red-500">{errors.code}</p>}
                    </div>
                    <div>
                        <Label>Zone *</Label>
                        <Input type="text" value={data.zone} onChange={(e) => setData('zone', e.target.value)} placeholder="e.g. Zona A" />
                        {errors.zone && <p className="mt-1 text-sm text-red-500">{errors.zone}</p>}
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit">Save</Button>
                        <Button type="button" variant="outline" onClick={() => window.history.back()}>Cancel</Button>
                    </div>
                </form>
            </ComponentCard>
        </AppLayout>
    );
}
