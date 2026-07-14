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

export default function Index({ shifts, filters }: any) {
    const [importModalOpen, setImportModalOpen] = useState(false);

    const handleDelete = (id: number) => {
        if (confirm('Hapus shift ini?')) {
            router.delete(route('shifts.destroy', id));
        }
    };

    return (
        <>
            <Head title="Shift" />
            <PageBreadcrumb pageTitle="Shift" />
            <ComponentCard title="Daftar Shift">
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    <Link href={route('shifts.create')}><Button>Tambah Shift</Button></Link>
                    <SearchInput
                        placeholder="Cari nama atau kode shift..."
                        routeName="shifts.index"
                        filters={filters}
                    />
                    <ImportExportToolbar
                        importUrl={route('shifts.import')}
                        previewUrl={route('shifts.import.preview')}
                        exportUrl={route('shifts.export')}
                        onImportClick={() => setImportModalOpen(true)}
                    />
                </div>
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('shifts.import')}
                    previewUrl={route('shifts.import.preview')}
                    templateUrl={route('shifts.import-template')}
                    title="Shift"
                    fields={[
                        { key: 'name', label: 'Nama', required: true },
                        { key: 'code', label: 'Kode', required: true },
                        { key: 'start_time', label: 'Jam Mulai', required: true },
                        { key: 'end_time', label: 'Jam Selesai', required: true },
                        { key: 'status', label: 'Status (Aktif/Nonaktif)', required: true },
                    ]}
                />
                {shifts.data.length === 0 ? (
                    <EmptyState
                        icon="🕒"
                        title="Belum ada shift"
                        message="Tambahkan shift kerja seperti Pagi, Siang, atau Malam."
                        actionLabel="Tambah Shift"
                        actionRoute={route('shifts.create')}
                    />
                ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full">
                        <thead className="bg-[#F8F9FC] border-b border-[#E9ECEF]">
                            <tr>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Nama</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Kode</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Jam Mulai</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Jam Selesai</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Status</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {shifts.data.map((s: any) => (
                                <tr key={s.id} className="border-b border-[#F1F3F5] hover:bg-[#F8F9FC] transition-all duration-150">
                                    <td className="px-4 py-3 text-sm text-[#1A1D23] font-medium">{s.name}</td>
                                    <td className="px-4 py-3 text-[13px] text-[#6C757D]">{s.code}</td>
                                    <td className="px-4 py-3 text-[13px] text-[#6C757D]">{s.start_time}</td>
                                    <td className="px-4 py-3 text-[13px] text-[#6C757D]">{s.end_time}</td>
                                    <td className="px-4 py-3 text-sm">
                                        <span className={`inline-flex px-2 py-1 text-xs font-medium rounded-full ${s.status === 'Aktif' ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400'}`}>
                                            {s.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-[#1A1D23]">
                                        <TableActions
                                            editRoute={route('shifts.edit', s.id)}
                                            onDelete={() => handleDelete(s.id)}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                )}
                {shifts.total > shifts.per_page && (
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">Menampilkan {shifts.from} sampai {shifts.to} dari {shifts.total}</div>
                        <div className="flex gap-2">
                            {shifts.prev_page_url ? <Link href={shifts.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Sebelumnya</Link> : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Sebelumnya</span>}
                            <span className="px-3 py-1 text-sm">Halaman {shifts.current_page} dari {shifts.last_page}</span>
                            {shifts.next_page_url ? <Link href={shifts.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Berikutnya</Link> : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Berikutnya</span>}
                        </div>
                    </div>
                )}
            </ComponentCard>
        </>
    );
}

Index.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
