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

export default function Index({ locations, filters }: any) {
    const permissions = (usePage().props.auth as any)?.user?.permissions || [];
    const canCreate = permissions.includes('create shopping locations');
    const canEdit = permissions.includes('edit shopping locations');
    const canDelete = permissions.includes('delete shopping locations');
    const handleDelete = (id: number) => {
        if (confirm('Hapus lokasi tujuan ini?')) {
            router.delete(route('shopping-locations.destroy', id));
        }
    };

    return (
        <>
            <Head title="Lokasi Tujuan" />
            <PageBreadcrumb pageTitle="Lokasi Tujuan" />
            <ComponentCard title="Daftar Lokasi Tujuan">
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    {canCreate && (
                        <Link href={route('shopping-locations.create')}><Button>Tambah Lokasi Tujuan</Button></Link>
                    )}
                    <SearchInput
                        placeholder="Cari lokasi..."
                        routeName="shopping-locations.index"
                        filters={filters}
                    />
                </div>
                {locations.data.length === 0 ? (
                    <EmptyState
                        icon="📍"
                        title="Belum ada lokasi tujuan"
                        message="Tambahkan lokasi tujuan pengiriman."
                        actionLabel={canCreate ? "Tambah Lokasi Tujuan" : undefined}
                        actionRoute={canCreate ? route('shopping-locations.create') : undefined}
                    />
                ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full">
                        <thead className="bg-[#F8F9FC] border-b border-[#E9ECEF]">
                            <tr>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Nama Lokasi</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Barcode</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {locations.data.map((l: any) => (
                                <tr key={l.id} className="border-b border-[#F1F3F5] hover:bg-[#F8F9FC] transition-all duration-150">
                                    <td className="px-4 py-3 text-sm text-[#1A1D23] font-medium">{l.name}</td>
                                    <td className="px-4 py-3 text-sm font-mono text-[#1A1D23]">{l.barcode || '—'}</td>
                                    <td className="px-4 py-3 text-sm text-[#1A1D23]">
                                        <TableActions
                                            editRoute={canEdit ? route('shopping-locations.edit', l.id) : undefined}
                                            onDelete={canDelete ? () => handleDelete(l.id) : undefined}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                )}
                {locations.total > locations.per_page && (
                    <Pagination
                        prevUrl={locations.prev_page_url}
                        nextUrl={locations.next_page_url}
                        currentPage={locations.current_page}
                        lastPage={locations.last_page}
                        from={locations.from}
                        to={locations.to}
                        total={locations.total}
                    />
                )}
            </ComponentCard>
        </>
    );
}

Index.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
