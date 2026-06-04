import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';

export default function Show({ shipment }: any) {
    const statusColors: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800',
        shipped: 'bg-blue-100 text-blue-800',
        completed: 'bg-green-100 text-green-800',
    };

    const handleShip = () => {
        if (confirm('Process this shipment? This will deduct stock.')) {
            router.post(route('shipments.ship', shipment.id));
        }
    };

    return (
        <AppLayout>
            <Head title={`Shipment to ${shipment.partner_name}`} />
            <PageBreadcrumb pageTitle={`Shipment: ${shipment.partner_name}`} />
            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div className="xl:col-span-1">
                    <ComponentCard title="Shipment Info">
                        <dl className="space-y-3">
                            <div><dt className="text-sm font-medium text-gray-500">Partner</dt><dd className="text-sm">{shipment.partner_name}</dd></div>
                            <div><dt className="text-sm font-medium text-gray-500">Date</dt><dd className="text-sm">{shipment.shipment_date}</dd></div>
                            <div><dt className="text-sm font-medium text-gray-500">Status</dt><dd><span className={`inline-block px-2 py-1 text-xs font-medium rounded-full ${statusColors[shipment.status]}`}>{shipment.status}</span></dd></div>
                            {shipment.notes && <div><dt className="text-sm font-medium text-gray-500">Notes</dt><dd className="text-sm">{shipment.notes}</dd></div>}
                        </dl>
                        <div className="mt-4 flex gap-2">
                            {shipment.status === 'draft' && (
                                <><Link href={route('shipments.edit', shipment.id)}><Button>Edit</Button></Link>
                                <Button variant="outline" onClick={handleShip}>Ship Now</Button></>
                            )}
                            <Link href={route('shipments.index')}><Button variant="outline">Back</Button></Link>
                        </div>
                    </ComponentCard>
                </div>
                <div className="xl:col-span-2">
                    <ComponentCard title="Items">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Part #</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Model</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rack</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                    {shipment.items.map((item: any) => (
                                        <tr key={item.id}>
                                            <td className="px-3 py-2 text-sm font-mono">{item.product?.part_number}</td>
                                            <td className="px-3 py-2 text-sm">{item.product?.name}</td>
                                            <td className="px-3 py-2 text-sm text-gray-500">{item.product?.vehicle_model?.name || '-'}</td>
                                            <td className="px-3 py-2 text-sm font-mono">{item.rack?.code}</td>
                                            <td className="px-3 py-2 text-sm font-medium">{item.quantity}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </ComponentCard>
                </div>
            </div>
        </AppLayout>
    );
}
