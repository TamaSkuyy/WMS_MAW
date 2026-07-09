import React, { useState } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import SearchInput from '../../../Tailadmin/components/form/input/SearchInput';
import TableActions from '../../../Tailadmin/components/common/TableActions';
import EmptyState from '../../../Tailadmin/components/common/EmptyState';
import ImportExportToolbar from '../../../Components/ImportExport/ImportExportToolbar';
import ImportModal from '../../../Components/ImportExport/ImportModal';

export default function Index({ employees, filters }: any) {
    const [importModalOpen, setImportModalOpen] = useState(false);

    const handleDelete = (id: number) => {
        if (confirm('Hapus karyawan ini?')) {
            router.delete(route('employees.destroy', id));
        }
    };

    return (
        <AppLayout>
            <Head title="Karyawan" />
            <PageBreadcrumb pageTitle="Karyawan" />
            <ComponentCard title="Daftar Karyawan">
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    <Link href={route('employees.create')}><Button>Tambah Karyawan</Button></Link>
                    <SearchInput
                        placeholder="Cari nama, NIK, atau email..."
                        routeName="employees.index"
                        filters={filters}
                    />
                    <ImportExportToolbar
                        importUrl={route('employees.import')}
                        previewUrl={route('employees.import.preview')}
                        exportUrl={route('employees.export')}
                        onImportClick={() => setImportModalOpen(true)}
                    />
                </div>
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('employees.import')}
                    previewUrl={route('employees.import.preview')}
                    templateUrl={route('employees.import-template')}
                    title="Karyawan"
                    fields={[
                        { key: 'name', label: 'Nama', required: true },
                        { key: 'nik', label: 'NIK', required: false },
                        { key: 'job_position', label: 'Jabatan', required: false },
                        { key: 'work_location', label: 'Lokasi Kerja', required: false },
                        { key: 'department', label: 'Departemen', required: false },
                        { key: 'phone', label: 'Telepon', required: false },
                        { key: 'email', label: 'Email', required: false },
                        { key: 'status', label: 'Status (Aktif/Nonaktif)', required: true },
                    ]}
                />
                {employees.data.length === 0 ? (
                    <EmptyState
                        icon="👤"
                        title="Belum ada karyawan"
                        message="Tambahkan data karyawan dan hubungkan ke akun login."
                        actionLabel="Tambah Karyawan"
                        actionRoute={route('employees.create')}
                    />
                ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full">
                        <thead className="bg-[#F8F9FC] border-b border-[#E9ECEF]">
                            <tr>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Nama</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">NIK</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Jabatan</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Lokasi Kerja</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Departemen</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Status</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">User</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {employees.data.map((e: any) => (
                                <tr key={e.id} className="border-b border-[#F1F3F5] hover:bg-[#F8F9FC] transition-all duration-150">
                                    <td className="px-4 py-3 text-sm text-[#1A1D23] font-medium">
                                        <Link href={route('employees.show', e.id)} className="text-[#3B5BDB] hover:text-[#4DABF7]">
                                            {e.name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3 text-[13px] text-[#6C757D]">{e.nik || '-'}</td>
                                    <td className="px-4 py-3 text-[13px] text-[#6C757D]">{e.job_position?.name || '-'}</td>
                                    <td className="px-4 py-3 text-[13px] text-[#6C757D]">{e.work_location?.name || '-'}</td>
                                    <td className="px-4 py-3 text-[13px] text-[#6C757D]">{e.department?.name || '-'}</td>
                                    <td className="px-4 py-3 text-sm">
                                        <span className={`inline-flex px-2 py-1 text-xs font-medium rounded-full ${e.status === 'Aktif' ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400'}`}>
                                            {e.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-500">{e.user?.name || '-'}</td>
                                    <td className="px-4 py-3 text-sm font-medium">
                                        <TableActions
                                            viewRoute={route('employees.show', e.id)}
                                            editRoute={route('employees.edit', e.id)}
                                            onDelete={() => handleDelete(e.id)}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                )}
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
