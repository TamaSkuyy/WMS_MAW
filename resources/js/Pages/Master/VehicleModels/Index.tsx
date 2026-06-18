import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import SearchInput from '../../../Tailadmin/components/form/input/SearchInput';
import TableActions from '../../../Tailadmin/components/common/TableActions';
import EmptyState from '../../../Tailadmin/components/common/EmptyState';

export default function Index({ vehicleModels, filters }: any) {
    const handleDelete = (id: number) => {
        if (confirm('Hapus model ini?')) {
            router.delete(route('vehicle-models.destroy', id));
        }
    };

    return (
        <AppLayout>
            <Head title="Model Kendaraan" />
            <PageBreadcrumb pageTitle="Model Kendaraan" />
            <ComponentCard title="Daftar Model Kendaraan">
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    <Link href={route('vehicle-models.create')}><Button>Tambah Model</Button></Link>
                    <SearchInput
                        placeholder="Cari merek atau model..."
                        routeName="vehicle-models.index"
                        filters={filters}
                    />
                </div>
                {vehicleModels.data.length === 0 ? (
                    <EmptyState
                        icon="🚗"
                        title="Belum ada model kendaraan"
                        message="Tambahkan model kendaraan seperti Fortuner, Avanza."
                        actionLabel="Tambah Model"
                        actionRoute={route('vehicle-models.create')}
                    />
                ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full">
                        <thead className="bg-[#F8F9FC] border-b border-[#E9ECEF]">
                            <tr>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Merek</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Model</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {vehicleModels.data.map((m: any) => (
                                <tr key={m.id} className="border-b border-[#F1F3F5] hover:bg-[#F8F9FC] transition-all duration-150">
                                    <td className="px-4 py-3 text-sm text-[#1A1D23]">{m.brand}</td>
                                    <td className="px-4 py-3 text-sm text-[#1A1D23]">{m.name}</td>
                                    <td className="px-4 py-3 text-sm text-[#1A1D23]">
                                        <TableActions
                                            editRoute={route('vehicle-models.edit', m.id)}
                                            onDelete={() => handleDelete(m.id)}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                )}
                {vehicleModels.total > vehicleModels.per_page && (
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">Menampilkan {vehicleModels.from} sampai {vehicleModels.to} dari {vehicleModels.total}</div>
                        <div className="flex gap-2">
                            {vehicleModels.prev_page_url ? <Link href={vehicleModels.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Sebelumnya</Link> : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Sebelumnya</span>}
                            <span className="px-3 py-1 text-sm">Halaman {vehicleModels.current_page} dari {vehicleModels.last_page}</span>
                            {vehicleModels.next_page_url ? <Link href={vehicleModels.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Berikutnya</Link> : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Berikutnya</span>}
                        </div>
                    </div>
                )}
            </ComponentCard>
        </AppLayout>
    );
}
