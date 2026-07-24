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

export default function Index({ suppliers, filters }: any) {
    const [importModalOpen, setImportModalOpen] = useState(false);

    const handleDelete = (id: number) => {
        if (confirm('Yakin ingin menghapus supplier ini?')) {
            router.delete(route('suppliers.destroy', id));
        }
    };

    return (
        <>
            <Head title="Supplier" />
            <PageBreadcrumb pageTitle="Supplier" />

            <ComponentCard title="Daftar Supplier">
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    <Link href={route('suppliers.create')}>
                        <Button>Tambah Supplier</Button>
                    </Link>
                    <SearchInput
                        placeholder="Cari nama supplier..."
                        routeName="suppliers.index"
                        filters={filters}
                    />
                    <ImportExportToolbar
                        importUrl={route('suppliers.import')}
                        previewUrl={route('suppliers.import.preview')}
                        exportUrl={route('suppliers.export')}
                        onImportClick={() => setImportModalOpen(true)}
                    />
                </div>
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('suppliers.import')}
                    previewUrl={route('suppliers.import.preview')}
                    templateUrl={route('suppliers.import-template')}
                    title="Supplier"
                    fields={[
                        { key: 'code', label: 'Kode / Singkatan', required: false },
                        { key: 'name', label: 'Nama', required: true },
                        { key: 'contact_person', label: 'Kontak Person', required: false },
                        { key: 'email', label: 'Email', required: true },
                        { key: 'phone', label: 'Telepon', required: false },
                        { key: 'street', label: 'Jalan', required: true },
                        { key: 'city', label: 'Kota', required: true },
                        { key: 'state', label: 'Provinsi', required: true },
                        { key: 'postal_code', label: 'Kode Pos', required: true },
                        { key: 'country', label: 'Negara', required: true },
                    ]}
                />
                {suppliers.data.length === 0 ? (
                    <EmptyState
                        icon="📦"
                        title="Belum ada supplier"
                        message="Tambahkan supplier pertama untuk mulai menerima barang."
                        actionLabel="Tambah Supplier"
                        actionRoute={route('suppliers.create')}
                    />
                ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full">
                        <thead className="bg-[#F8F9FC] border-b border-[#E9ECEF]">
                            <tr>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider w-20">Kode</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Nama</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Kontak Person</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Email</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Telepon</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {suppliers.data.map((supplier: any) => (
                                <tr key={supplier.id} className="border-b border-[#F1F3F5] hover:bg-[#F8F9FC] transition-all duration-150">
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <span className="rounded bg-brand-50 dark:bg-brand-500/10 px-2 py-0.5 text-[11px] font-bold text-brand-600 dark:text-brand-400">{supplier.code || '-'}</span>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-[#1A1D23] font-medium">{supplier.name}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-[13px] text-[#6C757D]">{supplier.contact_person || '-'}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-[13px] text-[#6C757D]">{supplier.email}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-[13px] text-[#6C757D]">{supplier.phone || '-'}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-[#1A1D23]">
                                        <TableActions
                                            viewRoute={route('suppliers.show', supplier.id)}
                                            editRoute={route('suppliers.edit', supplier.id)}
                                            onDelete={() => handleDelete(supplier.id)}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                )}

                {suppliers.total > suppliers.per_page && (
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">
                            Menampilkan {suppliers.from || 0} sampai {suppliers.to || 0} dari {suppliers.total || 0}
                        </div>
                        <div className="flex gap-2">
                            {suppliers.prev_page_url ? (
                                <Link href={suppliers.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Sebelumnya</Link>
                            ) : (
                                <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Sebelumnya</span>
                            )}
                            <span className="px-3 py-1 text-sm">
                                Halaman {suppliers.current_page} dari {suppliers.last_page}
                            </span>
                            {suppliers.next_page_url ? (
                                <Link href={suppliers.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Berikutnya</Link>
                            ) : (
                                <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Berikutnya</span>
                            )}
                        </div>
                    </div>
                )}
            </ComponentCard>
        </>
    );
}

Index.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
