import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';

export default function Show({ employee }: any) {
    return (
        <AppLayout>
            <Head title={`Detail Karyawan - ${employee.name}`} />
            <PageBreadcrumb pageTitle={`Detail: ${employee.name}`} />
            <ComponentCard title="Informasi Karyawan">
                <div className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-500 dark:text-gray-400">Nama</label>
                            <p className="mt-1 text-sm font-medium">{employee.name}</p>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-500 dark:text-gray-400">NIK</label>
                            <p className="mt-1 text-sm font-medium">{employee.nik || '-'}</p>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-500 dark:text-gray-400">Jabatan</label>
                            <p className="mt-1 text-sm font-medium">{employee.job_position?.name || '-'}</p>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-500 dark:text-gray-400">Level Jabatan</label>
                            <p className="mt-1 text-sm font-medium">{employee.job_position?.level || '-'}</p>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-500 dark:text-gray-400">Lokasi Kerja</label>
                            <p className="mt-1 text-sm font-medium">{employee.work_location?.name || '-'}</p>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-500 dark:text-gray-400">Departemen</label>
                            <p className="mt-1 text-sm font-medium">{employee.department?.name || '-'}</p>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-500 dark:text-gray-400">Telepon</label>
                            <p className="mt-1 text-sm font-medium">{employee.phone || '-'}</p>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-500 dark:text-gray-400">Email</label>
                            <p className="mt-1 text-sm font-medium">{employee.email || '-'}</p>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-500 dark:text-gray-400">Status</label>
                            <p className="mt-1">
                                <span className={`inline-flex px-2 py-1 text-xs font-medium rounded-full ${employee.status === 'Aktif' ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400'}`}>
                                    {employee.status}
                                </span>
                            </p>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-500 dark:text-gray-400">User / Login</label>
                            <p className="mt-1 text-sm font-medium">{employee.user?.name ? `${employee.user.name} (${employee.user.email})` : '-'}</p>
                        </div>
                    </div>

                    <hr className="border-gray-200 dark:border-gray-700" />

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs text-gray-400">
                        <div>
                            <span>Dibuat oleh: {employee.creator?.name || '-'}</span>
                            <span className="ml-2">({employee.created_at ? new Date(employee.created_at).toLocaleString('id-ID') : '-'})</span>
                        </div>
                        <div>
                            <span>Diupdate oleh: {employee.updater?.name || '-'}</span>
                            <span className="ml-2">({employee.updated_at ? new Date(employee.updated_at).toLocaleString('id-ID') : '-'})</span>
                        </div>
                    </div>

                    <div className="flex gap-2 pt-2">
                        <Link href={route('employees.edit', employee.id)}><Button>Edit</Button></Link>
                        <Link href={route('employees.index')}><Button type="button" variant="outline">Kembali</Button></Link>
                    </div>
                </div>
            </ComponentCard>
        </AppLayout>
    );
}
