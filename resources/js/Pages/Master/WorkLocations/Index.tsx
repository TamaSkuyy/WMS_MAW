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

export default function Index({ locations, filters }: any) {
    const [importModalOpen, setImportModalOpen] = useState(false);

    const handleDelete = (id: number) => {
        if (confirm('Hapus lokasi kerja ini?')) {
            router.delete(route('work-locations.destroy', id));
        }
    };

    return (
        <AppLayout>
            <Head title="Lokasi Kerja" />
            <PageBreadcrumb pageTitle="Lokasi Kerja" />
            <ComponentCard title="Daftar Lokasi Kerja">
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    <Link href={route('work-locations.create')}><Button>Tambah Lokasi</Button></Link>
                    <SearchInput
                        placeholder="Cari nama lokasi..."
                        routeName="work-locations.index"
                        filters={filters}
                    />
                    <ImportExportToolbar
                        importUrl={route('work-locations.import')}
                        previewUrl={route('work-locations.import.preview')}
                        exportUrl={route('work-locations.export')}
                        onImportClick={() => setImportModalOpen(true)}
                    />
                </div>
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('work-locations.import')}
                    previewUrl={route('work-locations.import.preview')}
                    templateUrl={route('work-locations.import-template')}
                    title="Lokasi Kerja"
                    fields={[
                        { key: 'name', label: 'Nama', required: true },
                    ]}
                />
                {locations.data.length === 0 ? (
                    <EmptyState
                        icon="📍"
                        title="Belum ada lokasi kerja"
                        message="Tambahkan lokasi kerja cabang atau kantor."
                        actionLabel="Tambah Lokasi"
                        actionRoute={route('work-locations.create')}
                    />
                ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full">
                        <thead className="bg-[#F8F9FC] border-b border-[#E9ECEF]">
                            <tr>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Nama</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {locations.data.map((l: any) => (
                                <tr key={l.id} className="border-b border-[#F1F3F5] hover:bg-[#F8F9FC] transition-all duration-150">
                                    <td className="px-4 py-3 text-sm text-[#1A1D23] font-medium">{l.name}</td>
                                    <td className="px-4 py-3 text-sm text-[#1A1D23]">
                                        <TableActions
                                            editRoute={route('work-locations.edit', l.id)}
                                            onDelete={() => handleDelete(l.id)}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                )}
                {locations.total > locations.per_page && (
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">Menampilkan {locations.from} sampai {locations.to} dari {locations.total}</div>
                        <div className="flex gap-2">
                            {locations.prev_page_url ? <Link href={locations.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Sebelumnya</Link> : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Sebelumnya</span>}
                            <span className="px-3 py-1 text-sm">Halaman {locations.current_page} dari {locations.last_page}</span>
                            {locations.next_page_url ? <Link href={locations.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Berikutnya</Link> : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Berikutnya</span>}
                        </div>
                    </div>
                )}
            </ComponentCard>
        </AppLayout>
    );
}
