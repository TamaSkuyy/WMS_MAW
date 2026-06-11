import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';

export default function Index({ vehicleModels }: any) {
    return (
        <AppLayout>
            <Head title="Model Kendaraan" />
            <PageBreadcrumb pageTitle="Model Kendaraan" />
            <ComponentCard title="Daftar Model Kendaraan">
                <div className="mb-3">
                    <Link href={route('vehicle-models.create')}><Button>Tambah Model</Button></Link>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Merek</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Model</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            {vehicleModels.data.map((m: any) => (
                                <tr key={m.id}>
                                    <td className="px-4 py-3 text-sm">{m.brand}</td>
                                    <td className="px-4 py-3 text-sm">{m.name}</td>
                                    <td className="px-4 py-3 text-sm font-medium">
                                        <Link href={route('vehicle-models.edit', m.id)} className="text-brand-500 hover:text-brand-700 mr-2">Edit</Link>
                                        <Link href={route('vehicle-models.destroy', m.id)} as="button" method="delete" onClick={(e: any) => { if (!confirm('Hapus model ini?')) e.preventDefault(); }} className="text-red-500 hover:text-red-700">Hapus</Link>
                                    </td>
                                </tr>
                            ))}
                            {vehicleModels.data.length === 0 && <tr><td colSpan={3} className="px-4 py-8 text-center text-sm text-gray-500">Tidak ada model.</td></tr>}
                        </tbody>
                    </table>
                </div>
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
