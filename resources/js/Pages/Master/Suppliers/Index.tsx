import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';

export default function Index({ suppliers }: any) {
    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this supplier?')) {
            router.delete(route('suppliers.destroy', id));
        }
    };

    return (
        <AppLayout>
            <Head title="Suppliers" />
            <PageBreadcrumb pageTitle="Suppliers" />

            <ComponentCard
                title="Supplier List"
                action={
                    <Link href={route('suppliers.create')}>
                        <Button>Add Supplier</Button>
                    </Link>
                }
            >
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Person</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            {suppliers.data.map((supplier: any) => (
                                <tr key={supplier.id}>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm font-medium">{supplier.name}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{supplier.contact_person || '-'}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{supplier.email}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{supplier.phone || '-'}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                        <Link href={route('suppliers.show', supplier.id)} className="text-brand-500 hover:text-brand-700 mr-3">View</Link>
                                        <Link href={route('suppliers.edit', supplier.id)} className="text-brand-500 hover:text-brand-700 mr-3">Edit</Link>
                                        <button onClick={() => handleDelete(supplier.id)} className="text-red-500 hover:text-red-700">Delete</button>
                                    </td>
                                </tr>
                            ))}
                            {suppliers.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-4 py-8 text-center text-sm text-gray-500">No suppliers found.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {suppliers.total > suppliers.per_page && (
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">
                            Showing {suppliers.from || 0} to {suppliers.to || 0} of {suppliers.total || 0}
                        </div>
                        <div className="flex gap-2">
                            {suppliers.prev_page_url ? (
                                <Link href={suppliers.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Prev</Link>
                            ) : (
                                <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Prev</span>
                            )}
                            <span className="px-3 py-1 text-sm">
                                Page {suppliers.current_page} of {suppliers.last_page}
                            </span>
                            {suppliers.next_page_url ? (
                                <Link href={suppliers.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Next</Link>
                            ) : (
                                <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Next</span>
                            )}
                        </div>
                    </div>
                )}
            </ComponentCard>
        </AppLayout>
    );
}
