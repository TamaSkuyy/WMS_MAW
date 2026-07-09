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

export default function Index({ racks, filters }: any) {
    const [importModalOpen, setImportModalOpen] = useState(false);

    const handleDelete = (id: number) => {
        if (confirm('Hapus rak ini?')) {
            router.delete(route('racks.destroy', id));
        }
    };

    return (
        <AppLayout>
            <Head title="Rak" />
            <PageBreadcrumb pageTitle="Rak" />
            <ComponentCard title="Daftar Rak">
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    <Link href={route('racks.create')}>
                        <Button>Tambah Rak</Button>
                    </Link>
                    <SearchInput
                        placeholder="Cari kode atau zona rak..."
                        routeName="racks.index"
                        filters={filters}
                    />
                    <ImportExportToolbar
                        importUrl={route('racks.import')}
                        previewUrl={route('racks.import.preview')}
                        exportUrl={route('racks.export')}
                        onImportClick={() => setImportModalOpen(true)}
                    />
                </div>
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('racks.import')}
                    previewUrl={route('racks.import.preview')}
                    templateUrl={route('racks.import-template')}
                    title="Rak"
                    fields={[
                        { key: 'code', label: 'Kode', required: true },
                        { key: 'zone', label: 'Zona', required: true },
                    ]}
                />
                {racks.data.length === 0 ? (
                    <EmptyState
                        icon="🗄️"
                        title="Belum ada rak"
                        message="Daftarkan lokasi rak penyimpanan."
                        actionLabel="Tambah Rak"
                        actionRoute={route('racks.create')}
                    />
                ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zona</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            {racks.data.map((rack: any) => (
                                <tr key={rack.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td className="px-4 py-3 whitespace-nowrap text-sm font-mono">{rack.code}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm">{rack.zone}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                        <TableActions
                                            viewRoute={route('racks.show', rack.id)}
                                            editRoute={route('racks.edit', rack.id)}
                                            onDelete={() => handleDelete(rack.id)}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                )}
                {racks.total > racks.per_page && (
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">Menampilkan {racks.from || 0} sampai {racks.to || 0} dari {racks.total}</div>
                        <div className="flex gap-2">
                            {racks.prev_page_url ? <Link href={racks.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Sebelumnya</Link>
                                : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Sebelumnya</span>}
                            <span className="px-3 py-1 text-sm">Halaman {racks.current_page} dari {racks.last_page}</span>
                            {racks.next_page_url ? <Link href={racks.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Berikutnya</Link>
                                : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Berikutnya</span>}
                        </div>
                    </div>
                )}
            </ComponentCard>
        </AppLayout>
    );
}
