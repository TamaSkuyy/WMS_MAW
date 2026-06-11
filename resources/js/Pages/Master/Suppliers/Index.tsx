import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';

export default function Index({ suppliers }: any) {
    const handleDelete = (id: number) => {
        if (confirm('Yakin ingin menghapus supplier ini?')) {
            router.delete(route('suppliers.destroy', id));
        }
    };

    return (
        <AppLayout>
            <Head title="Supplier" />
            <PageBreadcrumb pageTitle="Supplier" />

            <ComponentCard title="Daftar Supplier">
                <div className="mb-3">
                    <Link href={route('suppliers.create')}>
                        <Button>Tambah Supplier</Button>
                    </Link>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kontak Person</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Telepon</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            {suppliers.data.map((supplier: any) => (
                                <tr key={supplier.id}>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm font-medium">{supplier.name}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{supplier.contact_person || '-'}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{supplier.email}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{supplier.phone || '-'}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                        <Link href={route('suppliers.show', supplier.id)} className="text-brand-500 hover:text-brand-700 mr-3">Lihat</Link>
                                        <Link href={route('suppliers.edit', supplier.id)} className="text-brand-500 hover:text-brand-700 mr-3">Edit</Link>
                                        <button onClick={() => handleDelete(supplier.id)} className="text-red-500 hover:text-red-700">Hapus</button>
                                    </td>
                                </tr>
                            ))}
                            {suppliers.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-4 py-8 text-center text-sm text-gray-500">Tidak ada supplier.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

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
        </AppLayout>
    );
}
