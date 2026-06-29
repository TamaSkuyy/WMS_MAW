import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';
import TableActions from '../../../Tailadmin/components/common/TableActions';
import EmptyState from '../../../Tailadmin/components/common/EmptyState';

export default function Index({ products, categories, suppliers, filters }: any) {
    const handleDelete = (id: number) => {
        if (confirm('Yakin ingin menghapus produk ini?')) {
            router.delete(route('products.destroy', id));
        }
    };

    return (
        <AppLayout>
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

                <div className="mb-3">
                    <Link href={route('products.create')}>
                        <Button>Tambah Produk</Button>
                    </Link>
                </div>

                {products.data.length === 0 ? (
                    <EmptyState
                        icon="🏷️"
                        title="Belum ada produk"
                        message="Tambahkan produk pertama dari data Excel."
                        actionLabel="Tambah Produk"
                        actionRoute={route('products.create')}
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
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Harga</th>
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
                                    <td className="px-4 py-3 whitespace-nowrap text-[13px] text-[#6C757D]">
                                        {product.base_price ? `Rp ${Number(product.base_price).toLocaleString('id-ID')}` : '-'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-[#1A1D23]">
                                        <TableActions
                                            viewRoute={route('products.show', product.id)}
                                            editRoute={route('products.edit', product.id)}
                                            onDelete={() => handleDelete(product.id)}
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
        </AppLayout>
    );
}
