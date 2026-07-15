import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { ArrowLeftIcon, CheckIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';

interface DeliverySlot {
    id: number;
    slot_number: number;
    time_start: string;
    time_end: string;
    label: string | null;
}

export default function Edit({ slot }: { slot: DeliverySlot }) {
    const { data, setData, put, errors } = useForm({
        time_start: slot.time_start.slice(0, 5),
        time_end: slot.time_end.slice(0, 5),
        label: slot.label || '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('delivery-slots.update', slot.id));
    };

    return (
        <>
            <Head title={`Edit Slot C${slot.slot_number}`} />
            <PageBreadcrumb pageTitle={`Edit Slot C${slot.slot_number}`} />

            <div className="max-w-2xl">
                <ComponentCard
                    title={`Edit Slot C${slot.slot_number}`}
                    desc="Ubah jam mulai/selesai atau label slot ini"
                    action={
                        <Link href={route('delivery-slots.index')}>
                            <Button variant="outline" size="sm" icon={<ArrowLeftIcon className="w-4 h-4" />}>Kembali</Button>
                        </Link>
                    }
                >
                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div>
                            <Label>Label</Label>
                            <Input type="text" value={data.label} onChange={(e) => setData('label', e.target.value)} placeholder="mis. Pagi 1" />
                            {errors.label && <p className="mt-1 text-sm text-red-500">{errors.label}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Jam Mulai *</Label>
                                <Input type="time" value={data.time_start} onChange={(e) => setData('time_start', e.target.value)} />
                                {errors.time_start && <p className="mt-1 text-sm text-red-500">{errors.time_start}</p>}
                            </div>
                            <div>
                                <Label>Jam Selesai *</Label>
                                <Input type="time" value={data.time_end} onChange={(e) => setData('time_end', e.target.value)} />
                                {errors.time_end && <p className="mt-1 text-sm text-red-500">{errors.time_end}</p>}
                            </div>
                        </div>

                        <div className="flex gap-3 pt-4 border-t border-[#F1F3F5]">
                            <Button type="submit" icon={<CheckIcon className="w-4 h-4" />}>Simpan</Button>
                            <Button type="button" variant="outline" onClick={() => window.history.back()}>Batal</Button>
                        </div>
                    </form>
                </ComponentCard>
            </div>
        </>
    );
}

Edit.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
