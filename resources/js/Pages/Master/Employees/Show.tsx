import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { PencilIcon, ArrowLeftIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';

export default function Show({ employee }: any) {
    return (
        <>
            <Head title={`Detail Karyawan - ${employee.name}`} />
            <PageBreadcrumb pageTitle={`Detail: ${employee.name}`} />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div className="xl:col-span-1">
                    <ComponentCard title="Informasi Karyawan" desc="Detail data karyawan">
                        <dl className="space-y-4">
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Nama</dt>
                                <dd className="text-sm text-[#1A1D23]">{employee.name}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">NIK</dt>
                                <dd className="text-sm text-[#1A1D23]">{employee.nik || '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Jabatan</dt>
                                <dd className="text-sm text-[#1A1D23]">{employee.job_position?.name || '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Level Jabatan</dt>
                                <dd className="text-sm text-[#1A1D23]">{employee.job_position?.level || '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Lokasi Kerja</dt>
                                <dd className="text-sm text-[#1A1D23]">{employee.work_location?.name || '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Departemen</dt>
                                <dd className="text-sm text-[#1A1D23]">{employee.department?.name || '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Shift</dt>
                                <dd className="text-sm text-[#1A1D23]">{employee.shift?.name || '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Telepon</dt>
                                <dd className="text-sm text-[#1A1D23]">{employee.phone || '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Email</dt>
                                <dd className="text-sm text-[#1A1D23]">{employee.email || '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Status</dt>
                                <dd className="text-sm">
                                    <span className={`inline-flex px-2 py-1 text-xs font-medium rounded-full ${employee.status === 'Aktif' ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400'}`}>
                                        {employee.status}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">User / Login</dt>
                                <dd className="text-sm text-[#1A1D23]">{employee.user?.name ? `${employee.user.name} (${employee.user.email})` : '-'}</dd>
                            </div>
                        </dl>

                        <div className="mt-6 flex gap-2 pt-4 border-t border-[#F1F3F5]">
                            <Link href={route('employees.edit', employee.id)}>
                                <Button icon={<PencilIcon className="w-4 h-4" />} size="sm">Edit</Button>
                            </Link>
                            <Link href={route('employees.index')}>
                                <Button variant="outline" size="sm">Kembali</Button>
                            </Link>
                        </div>
                    </ComponentCard>
                </div>

                <div className="xl:col-span-2">
                    <ComponentCard title="Audit Trail" desc="Riwayat perubahan data">
                        <div className="space-y-4">
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Dibuat oleh</dt>
                                <dd className="text-sm text-[#1A1D23]">{employee.creator?.name || '-'} <span className="text-[#6C757D]">({employee.created_at ? new Date(employee.created_at).toLocaleString('id-ID') : '-'})</span></dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Diupdate oleh</dt>
                                <dd className="text-sm text-[#1A1D23]">{employee.updater?.name || '-'} <span className="text-[#6C757D]">({employee.updated_at ? new Date(employee.updated_at).toLocaleString('id-ID') : '-'})</span></dd>
                            </div>
                        </div>
                    </ComponentCard>
                </div>
            </div>
        </>
    );
}

Show.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
