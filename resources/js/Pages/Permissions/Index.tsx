import React, { useState } from 'react';
import AppLayout from '../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import PageBreadcrumb from '../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../Tailadmin/components/common/ComponentCard';
import Button from '../../Tailadmin/components/ui/button/Button';
import Input from '../../Tailadmin/components/form/input/InputField';
import Label from '../../Tailadmin/components/form/Label';

export default function Index({ permissions }: any) {
    const [isEditing, setIsEditing] = useState(false);
    const [editId, setEditId] = useState<number | null>(null);

    const { data, setData, post, put, delete: destroy, reset, errors } = useForm({
        name: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEditing && editId) {
            put(route('permissions.update', editId), {
                onSuccess: () => {
                    reset();
                    setIsEditing(false);
                    setEditId(null);
                },
            });
        } else {
            post(route('permissions.store'), {
                onSuccess: () => reset(),
            });
        }
    };

    const handleEdit = (permission: any) => {
        setIsEditing(true);
        setEditId(permission.id);
        setData({
            name: permission.name,
        });
    };

    const handleDelete = (permission: any) => {
        if (confirm(`Are you sure you want to delete the permission "${permission.name}"? This might break parts of the application relying on it.`)) {
            destroy(route('permissions.destroy', permission.id));
        }
    };

    const cancelEdit = () => {
        setIsEditing(false);
        setEditId(null);
        reset();
    };

    return (
        <AppLayout>
            <Head title="Permission Management" />
            <PageBreadcrumb pageTitle="Permission Management" />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div className="xl:col-span-1">
                    <ComponentCard title={isEditing ? 'Edit Permission' : 'Add New Permission'}>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <Label>Permission Name</Label>
                                <Input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g. create products"
                                />
                                {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                                <p className="mt-1 text-xs text-gray-500">
                                    Use lowercase words separated by spaces.
                                </p>
                            </div>

                            <div className="flex gap-2 pt-2">
                                <Button type="submit" className="w-full">
                                    {isEditing ? 'Update Permission' : 'Save Permission'}
                                </Button>
                                {isEditing && (
                                    <Button type="button" variant="outline" onClick={cancelEdit} className="w-full">
                                        Cancel
                                    </Button>
                                )}
                            </div>
                        </form>
                    </ComponentCard>
                </div>

                <div className="xl:col-span-2">
                    <ComponentCard title="Permission List">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Permission Name</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                    {permissions.map((permission: any) => (
                                        <tr key={permission.id}>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {permission.id}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                                                    {permission.name}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                                <button onClick={() => handleEdit(permission)} className="text-brand-500 hover:text-brand-700 mr-3">Edit</button>
                                                <button onClick={() => handleDelete(permission)} className="text-red-500 hover:text-red-700">Delete</button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </ComponentCard>
                </div>
            </div>
        </AppLayout>
    );
}
