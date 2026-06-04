import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';

export default function Show({ product }: any) {
    return (
        <AppLayout>
            <Head title={`Product - ${product.part_number}`} />
            <PageBreadcrumb pageTitle={product.part_number} />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <ComponentCard title="Product Information">
                    <dl className="space-y-3">
                        <div>
                            <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Part Number</dt>
                            <dd className="text-sm font-mono">{product.part_number}</dd>
                        </div>
                        <div>
                            <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                            <dd className="text-sm">{product.name}</dd>
                        </div>
                        <div>
                            <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Vehicle Model</dt>
                            <dd className="text-sm">{product.vehicle_model ? `${product.vehicle_model.name} (${product.vehicle_model.brand})` : '-'}</dd>
                        </div>
                        <div>
                            <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Category</dt>
                            <dd className="text-sm">{product.category?.name || '-'}</dd>
                        </div>
                        <div>
                            <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Unit</dt>
                            <dd className="text-sm">{product.unit}</dd>
                        </div>
                        <div>
                            <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Base Price</dt>
                            <dd className="text-sm">{product.base_price ? `Rp ${Number(product.base_price).toLocaleString('id-ID')}` : '-'}</dd>
                        </div>
                        <div>
                            <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                            <dd className="text-sm">
                                <span className={`inline-block px-2 py-1 text-xs font-medium rounded-full ${product.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'}`}>
                                    {product.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </dd>
                        </div>
                        {product.description && (
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Description</dt>
                                <dd className="text-sm">{product.description}</dd>
                            </div>
                        )}
                    </dl>

                    <div className="mt-6 flex gap-2">
                        <Link href={route('products.edit', product.id)}>
                            <Button>Edit</Button>
                        </Link>
                        <Link href={route('products.index')}>
                            <Button variant="outline">Back to List</Button>
                        </Link>
                    </div>
                </ComponentCard>

                <ComponentCard title="Supplier Information">
                    {product.supplier ? (
                        <dl className="space-y-3">
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Supplier Name</dt>
                                <dd className="text-sm">
                                    <Link href={route('suppliers.show', product.supplier.id)} className="text-brand-500 hover:text-brand-700">
                                        {product.supplier.name}
                                    </Link>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Contact Person</dt>
                                <dd className="text-sm">{product.supplier.contact_person || '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
                                <dd className="text-sm">{product.supplier.email}</dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Phone</dt>
                                <dd className="text-sm">{product.supplier.phone || '-'}</dd>
                            </div>
                        </dl>
                    ) : (
                        <p className="text-sm text-gray-500">No supplier assigned.</p>
                    )}
                </ComponentCard>
            </div>
        </AppLayout>
    );
}
