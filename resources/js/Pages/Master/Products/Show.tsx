import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { PencilIcon, ArrowLeftIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';

export default function Show({ product }: any) {
    return (
        <AppLayout>
            <Head title={`Produk - ${product.part_number}`} />
            <PageBreadcrumb pageTitle={`Detail: ${product.part_number}`} />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div className="xl:col-span-1">
                    <ComponentCard title="Informasi Produk" desc="Detail data produk">
                        <dl className="space-y-4">
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Part Number</dt>
                                <dd className="text-sm text-[#1A1D23] font-mono">{product.part_number}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Nama</dt>
                                <dd className="text-sm text-[#1A1D23]">{product.name}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Model Kendaraan</dt>
                                <dd className="text-sm text-[#1A1D23]">{product.vehicle_model ? `${product.vehicle_model.name} (${product.vehicle_model.brand})` : '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Kategori</dt>
                                <dd className="text-sm text-[#1A1D23]">{product.category?.name || '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Satuan</dt>
                                <dd className="text-sm text-[#1A1D23]">{product.unit}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Harga Dasar</dt>
                                <dd className="text-sm text-[#1A1D23]">{product.base_price ? `Rp ${Number(product.base_price).toLocaleString('id-ID')}` : '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Status</dt>
                                <dd className="text-sm">
                                    <span className={`inline-block px-2 py-1 text-xs font-medium rounded-full ${product.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'}`}>
                                        {product.is_active ? 'Aktif' : 'Nonaktif'}
                                    </span>
                                </dd>
                            </div>
                            {product.description && (
                                <div>
                                    <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Deskripsi</dt>
                                    <dd className="text-sm text-[#1A1D23]">{product.description}</dd>
                                </div>
                            )}
                        </dl>

                        <div className="mt-6 flex gap-2 pt-4 border-t border-[#F1F3F5]">
                            <Link href={route('products.edit', product.id)}>
                                <Button icon={<PencilIcon className="w-4 h-4" />} size="sm">Edit</Button>
                            </Link>
                            <Link href={route('products.index')}>
                                <Button variant="outline" size="sm">Kembali</Button>
                            </Link>
                        </div>
                    </ComponentCard>
                </div>

                <div className="xl:col-span-2">
                    <ComponentCard title="Informasi Supplier" desc="Data pemasok produk ini">
                        {product.supplier ? (
                            <dl className="space-y-4">
                                <div>
                                    <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Nama Supplier</dt>
                                    <dd className="text-sm text-[#1A1D23]">
                                        <Link href={route('suppliers.show', product.supplier.id)} className="text-brand-500 hover:text-brand-700">
                                            {product.supplier.name}
                                        </Link>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Kontak Person</dt>
                                    <dd className="text-sm text-[#1A1D23]">{product.supplier.contact_person || '-'}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Email</dt>
                                    <dd className="text-sm text-[#1A1D23]">{product.supplier.email}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Telepon</dt>
                                    <dd className="text-sm text-[#1A1D23]">{product.supplier.phone || '-'}</dd>
                                </div>
                            </dl>
                        ) : (
                            <p className="text-sm text-gray-500">Tidak ada supplier.</p>
                        )}
                    </ComponentCard>
                </div>
            </div>
        </AppLayout>
    );
}
