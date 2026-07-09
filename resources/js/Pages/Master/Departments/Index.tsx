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

export default function Index({ departments, filters }: any) {
    const [importModalOpen, setImportModalOpen] = useState(false);

    const handleDelete = (id: number) => {
        if (confirm('Hapus departemen ini?')) {
            router.delete(route('departments.destroy', id));
        }
    };

    return (
        <AppLayout>
            <Head title="Departemen" />
            <PageBreadcrumb pageTitle="Departemen" />
            <ComponentCard title="Daftar Departemen">
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    <Link href={route('departments.create')}><Button>Tambah Departemen</Button></Link>
                    <SearchInput
                        placeholder="Cari nama departemen..."
                        routeName="departments.index"
                        filters={filters}
                    />
                    <ImportExportToolbar
                        importUrl={route('departments.import')}
                        previewUrl={route('departments.import.preview')}
                        exportUrl={route('departments.export')}
                        onImportClick={() => setImportModalOpen(true)}
                    />
                </div>
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('departments.import')}
                    previewUrl={route('departments.import.preview')}
                    templateUrl={route('departments.import-template')}
                    title="Departemen"
                    fields={[
                        { key: 'name', label: 'Nama', required: true },
                    ]}
                />
                {departments.data.length === 0 ? (
                    <EmptyState
                        icon="🏢"
                        title="Belum ada departemen"
                        message="Tambahkan departemen untuk struktur organisasi."
                        actionLabel="Tambah Departemen"
                        actionRoute={route('departments.create')}
                    />
                ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            {departments.data.map((d: any) => (
                                <tr key={d.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td className="px-4 py-3 text-sm font-medium">{d.name}</td>
                                    <td className="px-4 py-3 text-sm font-medium">
                                        <TableActions
                                            editRoute={route('departments.edit', d.id)}
                                            onDelete={() => handleDelete(d.id)}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                )}
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
