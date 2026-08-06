import React, { useState } from 'react';
import AppLayout from '../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import PageBreadcrumb from '../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../Tailadmin/components/common/ComponentCard';
import Button from '../../Tailadmin/components/ui/button/Button';
import Input from '../../Tailadmin/components/form/input/InputField';
import Label from '../../Tailadmin/components/form/Label';
import SearchableSelect from '../../Tailadmin/components/form/select/SearchableSelect';
import ImportExportToolbar from '../../Components/ImportExport/ImportExportToolbar';
import ImportModal from '../../Components/ImportExport/ImportModal';

export default function Index({ users, employees }: any) {
    const [isEditing, setIsEditing] = useState(false);
    const [editId, setEditId] = useState<number | null>(null);
    const [importModalOpen, setImportModalOpen] = useState(false);

    const { data, setData, post, put, delete: destroy, reset, errors } = useForm({
        name: '',
        email: '',
        password: '',
        employee_id: '',
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
            employee_id: user.employee_id || '',
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
        <>
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
                                <Label>Karyawan</Label>
                                <SearchableSelect
                                    options={employees.map((e: any) => ({
                                        value: e.id,
                                        label: e.label,
                                    }))}
                                    value={data.employee_id}
                                    onChange={(v) => setData('employee_id', v as string)}
                                    placeholder="Cari nama karyawan..."
                                />
                                {errors.employee_id && <p className="mt-1 text-sm text-red-500">{errors.employee_id}</p>}
                                {data.employee_id && (() => {
                                    const emp = employees.find((e: any) => String(e.id) === String(data.employee_id));
                                    return emp?.role_name ? (
                                        <p className="text-xs text-green-600 mt-1">
                                            ✓ Role: <strong>{emp.role_name}</strong> (dari jabatan)
                                        </p>
                                    ) : (
                                        <p className="text-xs text-yellow-600 mt-1">
                                            ⚠ Karyawan ini belum punya role di jabatannya
                                        </p>
                                    );
                                })()}
                                <p className="text-[12px] text-[#6C757D] mt-1">
                                    Role otomatis dari jabatan — tidak perlu input manual.
                                </p>
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
                                                {user.employee?.job_position?.role_name ? (
                                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#EEF2FF] text-[#3B5BDB]">
                                                        {user.employee.job_position.role_name}
                                                    </span>
                                                ) : user.roles?.map((r: any) => (
                                                    <span key={r.id} className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#FEF3C7] text-[#B45309]">
                                                        {r.name} (manual)
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
        </>
    );
}

Index.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
