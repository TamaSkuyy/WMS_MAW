import React, { useState } from 'react';
import AppLayout from '../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import PageBreadcrumb from '../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../Tailadmin/components/common/ComponentCard';
import Button from '../../Tailadmin/components/ui/button/Button';
import Input from '../../Tailadmin/components/form/input/InputField';
import Label from '../../Tailadmin/components/form/Label';

export default function Index({ users, roles }: any) {
    const [isEditing, setIsEditing] = useState(false);
    const [editId, setEditId] = useState<number | null>(null);

    const { data, setData, post, put, delete: destroy, reset, errors } = useForm({
        name: '',
        email: '',
        password: '',
        role: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEditing && editId) {
            put(route('users.update', editId), {
                onSuccess: () => {
                    reset();
                    setIsEditing(false);
                    setEditId(null);
                },
            });
        } else {
            post(route('users.store'), {
                onSuccess: () => reset(),
            });
        }
    };

    const handleEdit = (user: any) => {
        setIsEditing(true);
        setEditId(user.id);
        setData({
            name: user.name,
            email: user.email,
            password: '',
            role: user.roles?.length > 0 ? user.roles[0].name : '',
        });
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this user?')) {
            destroy(route('users.destroy', id));
        }
    };

    const cancelEdit = () => {
        setIsEditing(false);
        setEditId(null);
        reset();
    };

    return (
        <AppLayout>
            <Head title="User Management" />
            <PageBreadcrumb pageTitle="User Management" />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div className="xl:col-span-1">
                    <ComponentCard title={isEditing ? 'Edit User' : 'Add New User'}>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <Label>Name</Label>
                                <Input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Full Name"
                                />
                                {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                            </div>

                            <div>
                                <Label>Email</Label>
                                <Input
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="Email Address"
                                />
                                {errors.email && <p className="mt-1 text-sm text-red-500">{errors.email}</p>}
                            </div>

                            <div>
                                <Label>{isEditing ? 'Password (leave blank to keep current)' : 'Password'}</Label>
                                <Input
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder="Password"
                                />
                                {errors.password && <p className="mt-1 text-sm text-red-500">{errors.password}</p>}
                            </div>

                            <div>
                                <Label>Role</Label>
                                <select
                                    value={data.role}
                                    onChange={(e) => setData('role', e.target.value)}
                                    className="w-full px-4 py-2 bg-transparent border rounded-lg outline-none border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                                >
                                    <option value="">-- Select Role --</option>
                                    {roles.map((role: any) => (
                                        <option key={role.id} value={role.name}>{role.name}</option>
                                    ))}
                                </select>
                                {errors.role && <p className="mt-1 text-sm text-red-500">{errors.role}</p>}
                            </div>

                            <div className="flex gap-2">
                                <Button type="submit" className="w-full">
                                    {isEditing ? 'Update User' : 'Save User'}
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
                    <ComponentCard title="User List">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                    {users.map((user: any) => (
                                        <tr key={user.id}>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm">{user.name}</td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{user.email}</td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                {user.roles?.map((r: any) => (
                                                    <span key={r.id} className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-brand-100 text-brand-800 dark:bg-brand-900 dark:text-brand-300">
                                                        {r.name}
                                                    </span>
                                                ))}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                                <button onClick={() => handleEdit(user)} className="text-brand-500 hover:text-brand-700 mr-3">Edit</button>
                                                <button onClick={() => handleDelete(user.id)} className="text-red-500 hover:text-red-700">Delete</button>
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
