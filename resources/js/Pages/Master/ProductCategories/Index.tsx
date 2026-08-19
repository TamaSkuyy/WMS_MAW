import React, { useState } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import SearchInput from '../../../Tailadmin/components/form/input/SearchInput';
import TableActions from '../../../Tailadmin/components/common/TableActions';
import EmptyState from '../../../Tailadmin/components/common/EmptyState';
import Pagination from '../../../Tailadmin/components/common/Pagination';
import ImportExportToolbar from '../../../Components/ImportExport/ImportExportToolbar';
import ImportModal from '../../../Components/ImportExport/ImportModal';

export default function Index({ categories, filters }: any) {
    const permissions = (usePage().props.auth as any)?.user?.permissions || [];
    const canCreate = permissions.includes('create product categories');
    const canEdit = permissions.includes('edit product categories');
    const canDelete = permissions.includes('delete product categories');
    const [importModalOpen, setImportModalOpen] = useState(false);

    const handleDelete = (id: number) => {
        if (confirm('Hapus kategori ini?')) {
            router.delete(route('product-categories.destroy', id));
        }
    };

    return (
        <>
            <Head title="Kategori Produk" />
            <PageBreadcrumb pageTitle="Kategori Produk" />
            <ComponentCard title="Daftar Kategori">
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    {canCreate && (
                        <Link href={route('product-categories.create')}><Button>Tambah Kategori</Button></Link>
                    )}
                    <SearchInput
                        placeholder="Cari nama kategori..."
                        routeName="product-categories.index"
                        filters={filters}
                    />
                    {canCreate && (<ImportExportToolbar
                        importUrl={route('product-categories.import')}
                        previewUrl={route('product-categories.import.preview')}
                        exportUrl={route('product-categories.export')}
                        onImportClick={() => setImportModalOpen(true)}/>
                    )}
                </div>
                {canCreate && (
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('product-categories.import')}
                    previewUrl={route('product-categories.import.preview')}
                    templateUrl={route('product-categories.import-template')}
                    title="Kategori Produk"
                    fields={[
                        { key: 'name', label: 'Nama', required: true },
                        { key: 'description', label: 'Deskripsi', required: false },
                    ]}
                />
                )}
                {categories.data.length === 0 ? (
                    <EmptyState
                        icon="📂"
                        title="Belum ada kategori"
                        message="Tambahkan kategori produk seperti Body Parts, Engine."
                        actionLabel={canCreate ? "Tambah Kategori" : undefined}
                        actionRoute={canCreate ? route('product-categories.create') : undefined}
                    />
                ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Deskripsi</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            {categories.data.map((c: any) => (
                                <tr key={c.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td className="px-4 py-3 text-sm font-medium">{c.name}</td>
                                    <td className="px-4 py-3 text-sm text-gray-500">{c.description || '-'}</td>
                                    <td className="px-4 py-3 text-sm font-medium">
                                        <TableActions
                                            editRoute={canEdit ? route('product-categories.edit', c.id) : undefined}
                                            onDelete={canDelete ? () => handleDelete(c.id) : undefined}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                )}
                {categories.total > categories.per_page && (
                    <Pagination
                        prevUrl={categories.prev_page_url}
                        nextUrl={categories.next_page_url}
                        currentPage={categories.current_page}
                        lastPage={categories.last_page}
                        from={categories.from}
                        to={categories.to}
                        total={categories.total}
                    />
                )}
            </ComponentCard>
        </>
    );
}

Index.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
