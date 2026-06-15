import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';

export default function Index({ departments }: any) {
    return (
        <AppLayout>
            <Head title="Departemen" />
            <PageBreadcrumb pageTitle="Departemen" />
            <ComponentCard title="Daftar Departemen">
                <div className="mb-3">
                    <Link href={route('departments.create')}><Button>Tambah Departemen</Button></Link>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            {departments.data.map((d: any) => (
                                <tr key={d.id}>
                                    <td className="px-4 py-3 text-sm font-medium">{d.name}</td>
                                    <td className="px-4 py-3 text-sm font-medium">
                                        <Link href={route('departments.edit', d.id)} className="text-brand-500 hover:text-brand-700 mr-2">Edit</Link>
                                        <Link href={route('departments.destroy', d.id)} as="button" method="delete" onClick={(e: any) => { if (!confirm('Hapus departemen ini?')) e.preventDefault(); }} className="text-red-500 hover:text-red-700">Hapus</Link>
                                    </td>
                                </tr>
                            ))}
                            {departments.data.length === 0 && <tr><td colSpan={2} className="px-4 py-8 text-center text-sm text-gray-500">Tidak ada departemen.</td></tr>}
                        </tbody>
                    </table>
                </div>
                {departments.total > departments.per_page && (
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">Menampilkan {departments.from} sampai {departments.to} dari {departments.total}</div>
                        <div className="flex gap-2">
                            {departments.prev_page_url ? <Link href={departments.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Sebelumnya</Link> : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Sebelumnya</span>}
                            <span className="px-3 py-1 text-sm">Halaman {departments.current_page} dari {departments.last_page}</span>
                            {departments.next_page_url ? <Link href={departments.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Berikutnya</Link> : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Berikutnya</span>}
                        </div>
                    </div>
                )}
            </ComponentCard>
        </AppLayout>
    );
}
