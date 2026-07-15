import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import TableActions from '../../../Tailadmin/components/common/TableActions';

interface DeliverySlot {
    id: number;
    slot_number: number;
    time_start: string;
    time_end: string;
    label: string | null;
}

export default function Index({ slots }: { slots: DeliverySlot[] }) {
    return (
        <>
            <Head title="Jadwal Slot Pengiriman" />
            <PageBreadcrumb pageTitle="Jadwal Slot Pengiriman" />
            <ComponentCard
                title="6 Slot Jam Harian"
                desc="Jam mulai/selesai tiap slot pengiriman (C1-C6) yang dipakai Delivery Monitor. Slot tidak bisa ditambah/dihapus, hanya jamnya yang bisa diubah."
            >
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Slot</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Label</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jam Mulai</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jam Selesai</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            {slots.map((slot) => (
                                <tr key={slot.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td className="px-4 py-3 text-sm font-medium">C{slot.slot_number}</td>
                                    <td className="px-4 py-3 text-sm text-gray-500">{slot.label || '-'}</td>
                                    <td className="px-4 py-3 text-sm tabular-nums">{slot.time_start.slice(0, 5)}</td>
                                    <td className="px-4 py-3 text-sm tabular-nums">{slot.time_end.slice(0, 5)}</td>
                                    <td className="px-4 py-3 text-sm font-medium">
                                        <TableActions editRoute={route('delivery-slots.edit', slot.id)} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </ComponentCard>
        </>
    );
}

Index.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
