import React, { useState } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';

export default function Show({ cycle, racks }: any) {
    const [isReceiving, setIsReceiving] = useState(false);
    const [items, setItems] = useState<any[]>(
        cycle.items.map((item: any) => ({
            id: item.id,
            received_quantity: item.received_quantity || 0,
            rack_id: '',
            notes: item.notes || '',
        }))
    );

    const statusColors: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800',
        receiving: 'bg-yellow-100 text-yellow-800',
        completed: 'bg-green-100 text-green-800',
    };

    const updateItem = (index: number, field: string, value: any) => {
        const newItems = [...items];
        newItems[index][field] = value;
        setItems(newItems);
    };

    const handleReceive = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(route('cycles.receive', cycle.id), { items }, {
            onSuccess: () => setIsReceiving(false),
        });
    };

    return (
        <AppLayout>
            <Head title={`Cycle #${cycle.cycle_number}`} />
            <PageBreadcrumb pageTitle={`${cycle.supplier?.name} — Cycle #${cycle.cycle_number}`} />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div className="xl:col-span-1">
                    <ComponentCard title="Cycle Info">
                        <dl className="space-y-3">
                            <div><dt className="text-sm font-medium text-gray-500">Supplier</dt><dd className="text-sm">{cycle.supplier?.name}</dd></div>
                            <div><dt className="text-sm font-medium text-gray-500">Cycle #</dt><dd className="text-sm font-mono">{cycle.cycle_number}</dd></div>
                            <div><dt className="text-sm font-medium text-gray-500">Status</dt><dd><span className={`inline-block px-2 py-1 text-xs font-medium rounded-full ${statusColors[cycle.status]}`}>{cycle.status}</span></dd></div>
                            <div><dt className="text-sm font-medium text-gray-500">Items</dt><dd className="text-sm">{cycle.items.length}</dd></div>
                            <div><dt className="text-sm font-medium text-gray-500">Received</dt><dd className="text-sm">{cycle.received_at ? new Date(cycle.received_at).toLocaleDateString('id-ID') : '-'}</dd></div>
                            {cycle.notes && <div><dt className="text-sm font-medium text-gray-500">Notes</dt><dd className="text-sm">{cycle.notes}</dd></div>}
                        </dl>
                        <div className="mt-4 flex gap-2">
                            {cycle.status === 'draft' && <Link href={route('cycles.edit', cycle.id)}><Button>Edit</Button></Link>}
                            {cycle.status !== 'completed' && !isReceiving && (
                                <Button variant="outline" onClick={() => setIsReceiving(true)}>Receive Items</Button>
                            )}
                            <Link href={route('cycles.index')}><Button variant="outline">Back</Button></Link>
                        </div>
                    </ComponentCard>
                </div>

                <div className="xl:col-span-2">
                    {isReceiving ? (
                        <ComponentCard title="Receive Items">
                            <form onSubmit={handleReceive}>
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead className="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Doc Qty</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rec Qty</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rack</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                        {cycle.items.map((item: any, i: number) => (
                                            <tr key={item.id}>
                                                <td className="px-3 py-2 text-sm">{item.product?.part_number} — {item.product?.name}</td>
                                                <td className="px-3 py-2 text-sm text-center">{item.quantity}</td>
                                                <td className="px-3 py-2">
                                                    <Input type="number" value={items[i].received_quantity} onChange={(e) => updateItem(i, 'received_quantity', parseInt(e.target.value) || 0)} min={0} max={item.quantity} />
                                                </td>
                                                <td className="px-3 py-2">
                                                    <SearchableSelect options={racks.map((r: any) => ({ value: r.id, label: r.code }))} value={items[i].rack_id} onChange={(v) => updateItem(i, 'rack_id', v as string)} />
                                                </td>
                                                <td className="px-3 py-2">
                                                    <input type="text" value={items[i].notes} onChange={(e) => updateItem(i, 'notes', e.target.value)} className="w-full text-sm border rounded px-2 py-1 dark:bg-gray-800 dark:border-gray-700" placeholder="e.g. 2 pcs rusak" />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                <div className="mt-4 flex gap-2">
                                    <Button type="submit">Complete Receiving</Button>
                                    <Button type="button" variant="outline" onClick={() => setIsReceiving(false)}>Cancel</Button>
                                </div>
                            </form>
                        </ComponentCard>
                    ) : (
                        <ComponentCard title="Items">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Part #</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Model</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rec</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                    {cycle.items.map((item: any) => (
                                        <tr key={item.id}>
                                            <td className="px-3 py-2 text-sm font-mono">{item.product?.part_number}</td>
                                            <td className="px-3 py-2 text-sm">{item.product?.name}</td>
                                            <td className="px-3 py-2 text-sm text-gray-500">{item.product?.vehicle_model?.name || '-'}</td>
                                            <td className="px-3 py-2 text-sm text-center">{item.quantity}</td>
                                            <td className="px-3 py-2 text-sm text-center">{item.received_quantity || 0}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </ComponentCard>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
