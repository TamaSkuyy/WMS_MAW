import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';

export default function Show({ supplier }: any) {
    return (
        <AppLayout>
            <Head title={`Supplier - ${supplier.name}`} />
            <PageBreadcrumb pageTitle={supplier.name} />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <ComponentCard title="Informasi Supplier">
                    <dl className="space-y-3">
                        <div>
                            <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Nama</dt>
                            <dd className="text-sm">{supplier.name}</dd>
                        </div>
                        <div>
                            <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Kontak Person</dt>
                            <dd className="text-sm">{supplier.contact_person || '-'}</dd>
                        </div>
                        <div>
                            <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
                            <dd className="text-sm">{supplier.email}</dd>
                        </div>
                        <div>
                            <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Telepon</dt>
                            <dd className="text-sm">{supplier.phone || '-'}</dd>
                        </div>
                    </dl>

                    <div className="mt-6 flex gap-2">
                        <Link href={route('suppliers.edit', supplier.id)}>
                            <Button>Edit</Button>
                        </Link>
                        <Link href={route('suppliers.index')}>
                            <Button variant="outline">Kembali</Button>
                        </Link>
                    </div>
                </ComponentCard>

                <ComponentCard title="Alamat">
                    {supplier.addresses?.length > 0 ? (
                        <div className="space-y-4">
                            {supplier.addresses.map((address: any) => (
                                <div key={address.id} className="p-4 border rounded-lg border-gray-200 dark:border-gray-700">
                                    <span className="inline-block px-2 py-1 text-xs font-medium rounded-full bg-brand-100 text-brand-800 dark:bg-brand-900 dark:text-brand-200 mb-2">
                                        {address.address_type === 'primary' ? 'UTAMA' : address.address_type === 'shipping' ? 'PENGIRIMAN' : address.address_type === 'billing' ? 'BILLING' : address.address_type.toUpperCase()}
                                    </span>
                                    <p className="text-sm">{address.street}</p>
                                    <p className="text-sm text-gray-500">{address.city}, {address.state} {address.postal_code}</p>
                                    <p className="text-sm text-gray-500">{address.country}</p>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-gray-500">Belum ada alamat.</p>
                    )}
                </ComponentCard>
            </div>
        </AppLayout>
    );
}
