import React, { useState } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';
import TableActions from '../../../Tailadmin/components/common/TableActions';
import EmptyState from '../../../Tailadmin/components/common/EmptyState';
import ImportExportToolbar from '../../../Components/ImportExport/ImportExportToolbar';
import ImportModal from '../../../Components/ImportExport/ImportModal';

export default function Index({ products, categories, suppliers, filters }: any) {
    const permissions = (usePage().props.auth as any)?.user?.permissions || [];
    const canCreate = permissions.includes('create products');
    const canEdit = permissions.includes('edit products');
    const canDelete = permissions.includes('delete products');
    const [importModalOpen, setImportModalOpen] = useState(false);

    const handleDelete = (id: number) => {
        if (confirm('Yakin ingin menghapus produk ini?')) {
            router.delete(route('products.destroy', id));
        }
    };

    return (
        <>
            <Head title="Produk" />
            <PageBreadcrumb pageTitle="Produk" />

            <ComponentCard title="Daftar Produk">
                <div className="mb-4 flex gap-3 flex-wrap items-end">
                    <div className="min-w-[200px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Cari</label>
                        <Input
                            type="text"
                            defaultValue={filters?.search || ''}
                            placeholder="Part number atau nama..."
                            onChange={(e) => {
                                router.get(route('products.index'), {
                                    ...filters,
                                    search: e.target.value,
                                }, { preserveState: true, replace: true });
                            }}
                        />
                    </div>
                    <div className="min-w-[180px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Kategori</label>
                        <SearchableSelect
                            options={[
                                { value: '', label: 'Semua Kategori' },
                                ...categories.map((c: any) => ({ value: c.id, label: c.name }))
                            ]}
                            value={filters?.category_id || ''}
                            onChange={(value) => {
                                router.get(route('products.index'), {
                                    ...filters,
                                    category_id: value as string,
                                }, { preserveState: true, replace: true });
                            }}
                        />
                    </div>
                    <div className="min-w-[180px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Supplier</label>
                        <SearchableSelect
                            options={[
                                { value: '', label: 'Semua Supplier' },
                                ...suppliers.map((s: any) => ({ value: s.id, label: s.name }))
                            ]}
                            value={filters?.supplier_id || ''}
                            onChange={(value) => {
                                router.get(route('products.index'), {
                                    ...filters,
                                    supplier_id: value as string,
                                }, { preserveState: true, replace: true });
                            }}
                        />
                    </div>
                </div>

                <div className="mb-3 flex flex-wrap items-center gap-3">
                    {canCreate && (
                        <Link href={route('products.create')}><Button>Tambah Produk</Button></Link>
                    )}
                    {canCreate && (<ImportExportToolbar
                        importUrl={route('products.import')}
                        previewUrl={route('products.import.preview')}
                        exportUrl={route('products.export')}
                        onImportClick={() => setImportModalOpen(true)}/>
                    )}
                </div>
                {canCreate && (
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('products.import')}
                    previewUrl={route('products.import.preview')}
                    templateUrl={route('products.import-template')}
                    title="Produk"
                    fields={[
                        { key: 'part_number', label: 'Part Number', required: true },
                        { key: 'name', label: 'Nama', required: true },
                        { key: 'brand', label: 'Merek', required: true },
                        { key: 'model_kendaraan', label: 'Model Kendaraan', required: true },
                        { key: 'supplier', label: 'Supplier', required: true },
                        { key: 'kategori', label: 'Kategori', required: true },
                        { key: 'unit', label: 'Satuan', required: true },
                        { key: 'description', label: 'Deskripsi', required: false },
                        { key: 'is_active', label: 'Aktif', required: false },
                        { key: 'default_rack', label: 'Rak Default', required: false },
                    ]}
                />

                )}
                {products.data.length === 0 ? (
                    <EmptyState
                        icon="🏷️"
                        title="Belum ada produk"
                        message="Tambahkan produk pertama dari data Excel."
                        actionLabel={canCreate ? "Tambah Produk" : undefined}
                        actionRoute={canCreate ? route('products.create') : undefined}
                    />
                ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full">
                        <thead className="bg-[#F8F9FC] border-b border-[#E9ECEF]">
                            <tr>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Part Number</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Nama</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Model Kendaraan</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Supplier</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Kategori</th>
                                <th className="px-4 py-3 text-center text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider w-16">Stok</th>
                                <th className="px-4 py-3 text-center text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider w-20">Min / Max</th>
                                <th className="px-4 py-3 text-center text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider w-20">Status</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {products.data.map((product: any) => (
                                <tr key={product.id} className="border-b border-[#F1F3F5] hover:bg-[#F8F9FC] transition-all duration-150">
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-[#1A1D23] font-mono">{product.part_number}</td>
                                    <td className="px-4 py-3 text-sm text-[#1A1D23]">{product.name}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-[13px] text-[#6C757D]">
                                        {product.vehicle_model
                                            ? `${product.vehicle_model.brand} ${product.vehicle_model.name}${product.vehicle_model.suffix ? ' ' + product.vehicle_model.suffix : ''}`
                                            : '-'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-[13px] text-[#6C757D]">{product.supplier?.name || '-'}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-[13px] text-[#6C757D]">{product.category?.name || '-'}</td>
                                    <td className="px-4 py-3 text-center text-sm font-medium text-[#1A1D23]">{product.total_stock ?? 0}</td>
                                    <td className="px-4 py-3 text-center text-[12px] text-[#6C757D]">
                                        {product.min_stock != null || product.max_stock != null
                                            ? `${product.min_stock ?? '-'} / ${product.max_stock ?? '-'}`
                                            : '-'}
                                    </td>
                                    <td className="px-4 py-3 text-center">
                                        {product.stock_status === 'low' && (
                                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-yellow-100 text-yellow-700">⚠ Low</span>
                                        )}
                                        {product.stock_status === 'out' && (
                                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-red-100 text-red-700">✕ Out</span>
                                        )}
                                        {product.stock_status === 'over' && (
                                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-orange-100 text-orange-700">⚠ Over</span>
                                        )}
                                        {product.stock_status === 'normal' && (
                                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-green-100 text-green-700">Normal</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-[#1A1D23]">
                                        <TableActions
                                            viewRoute={route('products.show', product.id)}
                                            editRoute={canEdit ? route('products.edit', product.id) : undefined}
                                            onDelete={canDelete ? () => handleDelete(product.id) : undefined}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                )}

                {products.total > products.per_page && (
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">
                            Menampilkan {products.from || 0} sampai {products.to || 0} dari {products.total}
                        </div>
                        <div className="flex gap-2">
                            {products.prev_page_url ? (
                                <Link href={products.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Sebelumnya</Link>
                            ) : (
                                <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Sebelumnya</span>
                            )}
                            <span className="px-3 py-1 text-sm">Halaman {products.current_page} dari {products.last_page}</span>
                            {products.next_page_url ? (
                                <Link href={products.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Berikutnya</Link>
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
