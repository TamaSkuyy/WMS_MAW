import React, { useState } from 'react';
import AppLayout from '../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import PageBreadcrumb from '../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../Tailadmin/components/common/ComponentCard';
import Button from '../../Tailadmin/components/ui/button/Button';
import Input from '../../Tailadmin/components/form/input/InputField';
import Label from '../../Tailadmin/components/form/Label';
import ImportExportToolbar from '../../Components/ImportExport/ImportExportToolbar';
import ImportModal from '../../Components/ImportExport/ImportModal';

export default function Index({ users, roles }: any) {
    const [isEditing, setIsEditing] = useState(false);
    const [editId, setEditId] = useState<number | null>(null);
    const [importModalOpen, setImportModalOpen] = useState(false);

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

            <div className="mb-4">
                <ImportExportToolbar
                    importUrl={route('users.import')}
                    previewUrl={route('users.import.preview')}
                    exportUrl={route('users.export')}
                    onImportClick={() => setImportModalOpen(true)}
                />
            </div>

            <ImportModal
                isOpen={importModalOpen}
                onClose={() => setImportModalOpen(false)}
                onComplete={() => {
                    window.location.reload();
                }}
                importUrl={route('users.import')}
                previewUrl={route('users.import.preview')}
                templateUrl={route('users.import-template')}
                title="Users"
                fields={[
                    { key: 'name', label: 'Name', required: true },
                    { key: 'email', label: 'Email', required: true },
                    { key: 'password', label: 'Password', required: true },
                ]}
            />

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
                            <table className="min-w-full">
                                <thead className="bg-[#F8F9FC] border-b border-[#E9ECEF]">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Name</th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Email</th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Role</th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.map((user: any) => (
                                        <tr key={user.id} className="border-b border-[#F1F3F5] hover:bg-[#F8F9FC] transition-all duration-150">
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-[#1A1D23]">{user.name}</td>
                                            <td className="px-4 py-3 whitespace-nowrap text-[13px] text-[#6C757D]">{user.email}</td>
                                            <td className="px-4 py-3 whitespace-nowrap text-[13px] text-[#6C757D]">
                                                {user.roles?.map((r: any) => (
                                                    <span key={r.id} className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#EEF2FF] text-[#3B5BDB]">
                                                        {r.name}
                                                    </span>
                                                ))}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-[#1A1D23] font-medium">
                                                <button onClick={() => handleEdit(user)} className="text-[#3B5BDB] hover:text-[#4DABF7] mr-3 transition-all duration-150">Edit</button>
                                                <button onClick={() => handleDelete(user.id)} className="text-[#FA5252] hover:text-[#E03131] transition-all duration-150">Delete</button>
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
