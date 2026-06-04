import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';

export default function Create() {
    const { data, setData, post, errors } = useForm({
        name: '',
        contact_person: '',
        email: '',
        phone: '',
        street: '',
        city: '',
        state: '',
        postal_code: '',
        country: 'Indonesia',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('suppliers.store'));
    };

    return (
        <AppLayout>
            <Head title="Add Supplier" />
            <PageBreadcrumb pageTitle="Add Supplier" />

            <ComponentCard title="New Supplier">
                <form onSubmit={handleSubmit} className="space-y-4 max-w-2xl">
                    <div>
                        <Label>Name *</Label>
                        <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Supplier company name" />
                        {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                    </div>
                    <div>
                        <Label>Contact Person</Label>
                        <Input type="text" value={data.contact_person} onChange={(e) => setData('contact_person', e.target.value)} placeholder="Contact person name" />
                        {errors.contact_person && <p className="mt-1 text-sm text-red-500">{errors.contact_person}</p>}
                    </div>
                    <div>
                        <Label>Email *</Label>
                        <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} placeholder="email@example.com" />
                        {errors.email && <p className="mt-1 text-sm text-red-500">{errors.email}</p>}
                    </div>
                    <div>
                        <Label>Phone</Label>
                        <Input type="text" value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="+62812345678" />
                        {errors.phone && <p className="mt-1 text-sm text-red-500">{errors.phone}</p>}
                    </div>

                    <hr className="border-gray-200 dark:border-gray-700" />
                    <h3 className="text-lg font-medium">Address</h3>

                    <div>
                        <Label>Street *</Label>
                        <Input type="text" value={data.street} onChange={(e) => setData('street', e.target.value)} placeholder="Street address" />
                        {errors.street && <p className="mt-1 text-sm text-red-500">{errors.street}</p>}
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>City *</Label>
                            <Input type="text" value={data.city} onChange={(e) => setData('city', e.target.value)} placeholder="City" />
                            {errors.city && <p className="mt-1 text-sm text-red-500">{errors.city}</p>}
                        </div>
                        <div>
                            <Label>State *</Label>
                            <Input type="text" value={data.state} onChange={(e) => setData('state', e.target.value)} placeholder="State/Province" />
                            {errors.state && <p className="mt-1 text-sm text-red-500">{errors.state}</p>}
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Postal Code *</Label>
                            <Input type="text" value={data.postal_code} onChange={(e) => setData('postal_code', e.target.value)} placeholder="Postal code" />
                            {errors.postal_code && <p className="mt-1 text-sm text-red-500">{errors.postal_code}</p>}
                        </div>
                        <div>
                            <Label>Country *</Label>
                            <Input type="text" value={data.country} onChange={(e) => setData('country', e.target.value)} placeholder="Country" />
                            {errors.country && <p className="mt-1 text-sm text-red-500">{errors.country}</p>}
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit">Save Supplier</Button>
                        <Button type="button" variant="outline" onClick={() => window.history.back()}>Cancel</Button>
                    </div>
                </form>
            </ComponentCard>
        </AppLayout>
    );
}
