import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeftIcon, CheckIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';
import { Link } from '@inertiajs/react';

export default function Edit({ supplier }: any) {
    const primaryAddress = supplier.primary_address || {};

    const { data, setData, put, errors } = useForm({
        name: supplier.name || '',
        contact_person: supplier.contact_person || '',
        email: supplier.email || '',
        phone: supplier.phone || '',
        street: primaryAddress.street || '',
        city: primaryAddress.city || '',
        state: primaryAddress.state || '',
        postal_code: primaryAddress.postal_code || '',
        country: primaryAddress.country || 'Indonesia',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('suppliers.update', supplier.id));
    };

    return (
        <AppLayout>
            <Head title={`Edit Supplier - ${supplier.name}`} />
            <PageBreadcrumb pageTitle={`Edit: ${supplier.name}`} />

            <div className="max-w-2xl">
                <ComponentCard
                    title="Edit Supplier"
                    desc="Perbarui data supplier yang sudah ada"
                    action={
                        <Link href={route('suppliers.index')}>
                            <Button variant="outline" size="sm" icon={<ArrowLeftIcon className="w-4 h-4" />}>Kembali</Button>
                        </Link>
                    }
                >
                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div>
                            <Label>Nama *</Label>
                            <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Nama perusahaan supplier" />
                            {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                        </div>
                        <div>
                            <Label>Kontak Person</Label>
                            <Input type="text" value={data.contact_person} onChange={(e) => setData('contact_person', e.target.value)} placeholder="Nama kontak person" />
                            {errors.contact_person && <p className="mt-1 text-sm text-red-500">{errors.contact_person}</p>}
                        </div>
                        <div>
                            <Label>Email *</Label>
                            <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} placeholder="email@contoh.com" />
                            {errors.email && <p className="mt-1 text-sm text-red-500">{errors.email}</p>}
                        </div>
                        <div>
                            <Label>Telepon</Label>
                            <Input type="text" value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="+62812345678" />
                            {errors.phone && <p className="mt-1 text-sm text-red-500">{errors.phone}</p>}
                        </div>

                        <hr className="border-gray-200 dark:border-gray-700" />
                        <h3 className="text-lg font-medium">Alamat Utama</h3>

                        <div>
                            <Label>Jalan *</Label>
                            <Input type="text" value={data.street} onChange={(e) => setData('street', e.target.value)} placeholder="Alamat jalan" />
                            {errors.street && <p className="mt-1 text-sm text-red-500">{errors.street}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Kota *</Label>
                                <Input type="text" value={data.city} onChange={(e) => setData('city', e.target.value)} placeholder="Kota" />
                                {errors.city && <p className="mt-1 text-sm text-red-500">{errors.city}</p>}
                            </div>
                            <div>
                                <Label>Provinsi *</Label>
                                <Input type="text" value={data.state} onChange={(e) => setData('state', e.target.value)} placeholder="Provinsi" />
                                {errors.state && <p className="mt-1 text-sm text-red-500">{errors.state}</p>}
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Kode Pos *</Label>
                                <Input type="text" value={data.postal_code} onChange={(e) => setData('postal_code', e.target.value)} placeholder="Kode pos" />
                                {errors.postal_code && <p className="mt-1 text-sm text-red-500">{errors.postal_code}</p>}
                            </div>
                            <div>
                                <Label>Negara *</Label>
                                <Input type="text" value={data.country} onChange={(e) => setData('country', e.target.value)} placeholder="Negara" />
                                {errors.country && <p className="mt-1 text-sm text-red-500">{errors.country}</p>}
                            </div>
                        </div>

                        <div className="flex gap-3 pt-4 border-t border-[#F1F3F5]">
                            <Button type="submit" icon={<CheckIcon className="w-4 h-4" />}>Simpan</Button>
                            <Button type="button" variant="outline" onClick={() => window.history.back()}>Batal</Button>
                        </div>
                    </form>
                </ComponentCard>
            </div>
        </AppLayout>
    );
}
