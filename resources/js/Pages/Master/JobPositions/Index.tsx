import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import SearchInput from '../../../Tailadmin/components/form/input/SearchInput';
import TableActions from '../../../Tailadmin/components/common/TableActions';
import EmptyState from '../../../Tailadmin/components/common/EmptyState';

export default function Index({ positions, filters }: any) {
    const handleDelete = (id: number) => {
        if (confirm('Hapus jabatan ini?')) {
            router.delete(route('job-positions.destroy', id));
        }
    };

    return (
        <AppLayout>
            <Head title="Jabatan" />
            <PageBreadcrumb pageTitle="Jabatan" />
            <ComponentCard title="Daftar Jabatan">
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    <Link href={route('job-positions.create')}><Button>Tambah Jabatan</Button></Link>
                    <SearchInput
                        placeholder="Cari nama jabatan..."
                        routeName="job-positions.index"
                        filters={filters}
                    />
                </div>
                {positions.data.length === 0 ? (
                    <EmptyState
                        icon="🪪"
                        title="Belum ada jabatan"
                        message="Tambahkan jabatan seperti Staff, Leader, Manager."
                        actionLabel="Tambah Jabatan"
                        actionRoute={route('job-positions.create')}
                    />
                ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full">
                        <thead className="bg-[#F8F9FC] border-b border-[#E9ECEF]">
                            <tr>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Nama</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Level</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {positions.data.map((p: any) => (
                                <tr key={p.id} className="border-b border-[#F1F3F5] hover:bg-[#F8F9FC] transition-all duration-150">
                                    <td className="px-4 py-3 text-sm text-[#1A1D23] font-medium">{p.name}</td>
                                    <td className="px-4 py-3 text-[13px] text-[#6C757D]">{p.level || '-'}</td>
                                    <td className="px-4 py-3 text-sm text-[#1A1D23]">
                                        <TableActions
                                            editRoute={route('job-positions.edit', p.id)}
                                            onDelete={() => handleDelete(p.id)}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                )}
                {positions.total > positions.per_page && (
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">Menampilkan {positions.from} sampai {positions.to} dari {positions.total}</div>
                        <div className="flex gap-2">
                            {positions.prev_page_url ? <Link href={positions.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Sebelumnya</Link> : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Sebelumnya</span>}
                            <span className="px-3 py-1 text-sm">Halaman {positions.current_page} dari {positions.last_page}</span>
                            {positions.next_page_url ? <Link href={positions.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Berikutnya</Link> : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Berikutnya</span>}
                        </div>
                    </div>
                )}
            </ComponentCard>
        </AppLayout>
    );
}
