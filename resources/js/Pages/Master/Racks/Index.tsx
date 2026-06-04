import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';

export default function Index({ racks }: any) {
    const handleDelete = (id: number) => {
        if (confirm('Delete this rack?')) {
            router.delete(route('racks.destroy', id));
        }
    };

    return (
        <AppLayout>
            <Head title="Racks" />
            <PageBreadcrumb pageTitle="Racks" />
            <ComponentCard title="Rack List" action={<Link href={route('racks.create')}><Button>Add Rack</Button></Link>}>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zone</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            {racks.data.map((rack: any) => (
                                <tr key={rack.id}>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm font-mono">{rack.code}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm">{rack.zone}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                        <Link href={route('racks.show', rack.id)} className="text-brand-500 hover:text-brand-700 mr-2">View</Link>
                                        <Link href={route('racks.edit', rack.id)} className="text-brand-500 hover:text-brand-700 mr-2">Edit</Link>
                                        <button onClick={() => handleDelete(rack.id)} className="text-red-500 hover:text-red-700">Delete</button>
                                    </td>
                                </tr>
                            ))}
                            {racks.data.length === 0 && (
                                <tr><td colSpan={3} className="px-4 py-8 text-center text-sm text-gray-500">No racks.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </ComponentCard>
        </AppLayout>
    );
}
