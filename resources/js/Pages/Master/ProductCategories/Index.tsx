import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import SearchInput from '../../../Tailadmin/components/form/input/SearchInput';
import TableActions from '../../../Tailadmin/components/common/TableActions';
import EmptyState from '../../../Tailadmin/components/common/EmptyState';

export default function Index({ categories, filters }: any) {
    const handleDelete = (id: number) => {
        if (confirm('Hapus kategori ini?')) {
            router.delete(route('product-categories.destroy', id));
        }
    };

    return (
        <AppLayout>
            <Head title="Kategori Produk" />
            <PageBreadcrumb pageTitle="Kategori Produk" />
            <ComponentCard title="Daftar Kategori">
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    <Link href={route('product-categories.create')}><Button>Tambah Kategori</Button></Link>
                    <SearchInput
                        placeholder="Cari nama kategori..."
                        routeName="product-categories.index"
                        filters={filters}
                    />
                </div>
                {categories.data.length === 0 ? (
                    <EmptyState
                        icon="📂"
                        title="Belum ada kategori"
                        message="Tambahkan kategori produk seperti Body Parts, Engine."
                        actionLabel="Tambah Kategori"
                        actionRoute={route('product-categories.create')}
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
                                            editRoute={route('product-categories.edit', c.id)}
                                            onDelete={() => handleDelete(c.id)}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                )}
                {categories.total > categories.per_page && (
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">Menampilkan {categories.from} sampai {categories.to} dari {categories.total}</div>
                        <div className="flex gap-2">
                            {categories.prev_page_url ? <Link href={categories.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Sebelumnya</Link> : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Sebelumnya</span>}
                            <span className="px-3 py-1 text-sm">Halaman {categories.current_page} dari {categories.last_page}</span>
                            {categories.next_page_url ? <Link href={categories.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Berikutnya</Link> : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Berikutnya</span>}
                        </div>
                    </div>
                )}
            </ComponentCard>
        </AppLayout>
    );
}
