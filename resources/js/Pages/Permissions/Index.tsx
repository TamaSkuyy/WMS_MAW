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
                            <table className="min-w-full">
                                <thead className="bg-[#F8F9FC] border-b border-[#E9ECEF]">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">ID</th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Permission Name</th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider w-32">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {permissions.map((permission: any) => (
                                        <tr key={permission.id} className="border-b border-[#F1F3F5] hover:bg-[#F8F9FC] transition-all duration-150">
                                            <td className="px-4 py-3 whitespace-nowrap text-[13px] text-[#6C757D]">
                                                {permission.id}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-[#1A1D23] font-medium">
                                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#F8F9FC] text-[#6C757D] border border-[#E9ECEF]">
                                                    {permission.name}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-[#1A1D23] font-medium">
                                                <button onClick={() => handleEdit(permission)} className="text-[#3B5BDB] hover:text-[#4DABF7] mr-3 transition-all duration-150">Edit</button>
                                                <button onClick={() => handleDelete(permission)} className="text-[#FA5252] hover:text-[#E03131] transition-all duration-150">Delete</button>
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
