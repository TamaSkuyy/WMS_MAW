import React, { useState } from 'react';
import AppLayout from '../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import PageBreadcrumb from '../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../Tailadmin/components/common/ComponentCard';
import Button from '../../Tailadmin/components/ui/button/Button';
import Input from '../../Tailadmin/components/form/input/InputField';
import Label from '../../Tailadmin/components/form/Label';

export default function Index({ roles, permissions }: any) {
    const [isEditing, setIsEditing] = useState(false);
    const [editId, setEditId] = useState<number | null>(null);

    const { data, setData, post, put, delete: destroy, reset, errors } = useForm({
        name: '',
        permissions: [] as string[],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEditing && editId) {
            put(route('roles.update', editId), {
                onSuccess: () => {
                    reset();
                    setIsEditing(false);
                    setEditId(null);
                },
            });
        } else {
            post(route('roles.store'), {
                onSuccess: () => reset(),
            });
        }
    };

    const handleEdit = (role: any) => {
        setIsEditing(true);
        setEditId(role.id);
        setData({
            name: role.name,
            permissions: role.permissions?.map((p: any) => p.name) || [],
        });
    };

    const handleDelete = (role: any) => {
        if (role.name === 'Super Admin') {
            alert('Cannot delete Super Admin role.');
            return;
        }
        if (confirm(`Are you sure you want to delete the role "${role.name}"?`)) {
            destroy(route('roles.destroy', role.id));
        }
    };

    const cancelEdit = () => {
        setIsEditing(false);
        setEditId(null);
        reset();
    };

    const handlePermissionToggle = (permissionName: string) => {
        const currentPermissions = [...data.permissions];
        const index = currentPermissions.indexOf(permissionName);
        if (index > -1) {
            currentPermissions.splice(index, 1);
        } else {
            currentPermissions.push(permissionName);
        }
        setData('permissions', currentPermissions);
    };

    const isSuperAdmin = isEditing && data.name === 'Super Admin';

    return (
        <AppLayout>
            <Head title="Role Management" />
            <PageBreadcrumb pageTitle="Role Management" />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div className="xl:col-span-1">
                    <ComponentCard title={isEditing ? 'Edit Role' : 'Add New Role'}>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <Label>Role Name</Label>
                                <Input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Role Name"
                                    disabled={isSuperAdmin}
                                />
                                {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                            </div>

                            {!isSuperAdmin && (
                                <div>
                                    <Label className="mb-3 block">Assign Permissions</Label>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-60 overflow-y-auto p-2 border border-gray-200 dark:border-gray-800 rounded-lg custom-scrollbar">
                                        {permissions.map((p: any) => (
                                            <label key={p.id} className="flex items-center gap-2 cursor-pointer text-sm text-gray-700 dark:text-gray-300">
                                                <input
                                                    type="checkbox"
                                                    className="w-4 h-4 rounded text-brand-500 border-gray-300 focus:ring-brand-500 dark:bg-gray-800 dark:border-gray-700"
                                                    checked={data.permissions.indexOf(p.name) > -1}
                                                    onChange={() => handlePermissionToggle(p.name)}
                                                />
                                                {p.name}
                                            </label>
                                        ))}
                                    </div>
                                    {errors.permissions && <p className="mt-1 text-sm text-red-500">{errors.permissions}</p>}
                                </div>
                            )}

                            {isSuperAdmin && (
                                <p className="text-sm text-brand-500 italic">
                                    Super Admin has all permissions implicitly.
                                </p>
                            )}

                            <div className="flex gap-2 pt-2">
                                <Button type="submit" className="w-full">
                                    {isEditing ? 'Update Role' : 'Save Role'}
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
                    <ComponentCard title="Role List">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role Name</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Permissions</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                    {roles.map((role: any) => (
                                        <tr key={role.id}>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                {role.name}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-500">
                                                {role.name === 'Super Admin' ? (
                                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-brand-100 text-brand-800 dark:bg-brand-900 dark:text-brand-300">
                                                        All Permissions
                                                    </span>
                                                ) : (
                                                    <div className="flex flex-wrap gap-1">
                                                        {role.permissions?.length > 0 ? (
                                                            role.permissions.map((p: any) => (
                                                                <span key={p.id} className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                                                                    {p.name}
                                                                </span>
                                                            ))
                                                        ) : (
                                                            <span className="text-gray-400 italic text-xs">No permissions</span>
                                                        )}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                                <button onClick={() => handleEdit(role)} className="text-brand-500 hover:text-brand-700 mr-3">Edit</button>
                                                {role.name !== 'Super Admin' && (
                                                    <button onClick={() => handleDelete(role)} className="text-red-500 hover:text-red-700">Delete</button>
                                                )}
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
