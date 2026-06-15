import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';

export default function Index({ employees }: any) {
    return (
        <AppLayout>
            <Head title="Karyawan" />
            <PageBreadcrumb pageTitle="Karyawan" />
            <ComponentCard title="Daftar Karyawan">
                <div className="mb-3">
                    <Link href={route('employees.create')}><Button>Tambah Karyawan</Button></Link>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">NIK</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jabatan</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lokasi Kerja</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Departemen</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            {employees.data.map((e: any) => (
                                <tr key={e.id}>
                                    <td className="px-4 py-3 text-sm font-medium">
                                        <Link href={route('employees.show', e.id)} className="text-brand-500 hover:text-brand-700">
                                            {e.name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-500">{e.nik || '-'}</td>
                                    <td className="px-4 py-3 text-sm text-gray-500">{e.job_position?.name || '-'}</td>
                                    <td className="px-4 py-3 text-sm text-gray-500">{e.work_location?.name || '-'}</td>
                                    <td className="px-4 py-3 text-sm text-gray-500">{e.department?.name || '-'}</td>
                                    <td className="px-4 py-3 text-sm">
                                        <span className={`inline-flex px-2 py-1 text-xs font-medium rounded-full ${e.status === 'Aktif' ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400'}`}>
                                            {e.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-500">{e.user?.name || '-'}</td>
                                    <td className="px-4 py-3 text-sm font-medium">
                                        <Link href={route('employees.edit', e.id)} className="text-brand-500 hover:text-brand-700 mr-2">Edit</Link>
                                        <Link href={route('employees.destroy', e.id)} as="button" method="delete" onClick={(ev: any) => { if (!confirm('Hapus karyawan ini?')) ev.preventDefault(); }} className="text-red-500 hover:text-red-700">Hapus</Link>
                                    </td>
                                </tr>
                            ))}
                            {employees.data.length === 0 && <tr><td colSpan={8} className="px-4 py-8 text-center text-sm text-gray-500">Tidak ada karyawan.</td></tr>}
                        </tbody>
                    </table>
                </div>
                {employees.total > employees.per_page && (
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">Menampilkan {employees.from} sampai {employees.to} dari {employees.total}</div>
                        <div className="flex gap-2">
                            {employees.prev_page_url ? <Link href={employees.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Sebelumnya</Link> : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Sebelumnya</span>}
                            <span className="px-3 py-1 text-sm">Halaman {employees.current_page} dari {employees.last_page}</span>
                            {employees.next_page_url ? <Link href={employees.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Berikutnya</Link> : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Berikutnya</span>}
                        </div>
                    </div>
                )}
            </ComponentCard>
        </AppLayout>
    );
}
