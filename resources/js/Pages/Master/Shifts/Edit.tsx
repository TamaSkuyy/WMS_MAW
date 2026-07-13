import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeftIcon, CheckIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';
import Select from '../../../Tailadmin/components/form/Select';
import { Link } from '@inertiajs/react';

export default function Edit({ shift }: any) {
    const { data, setData, put, errors } = useForm({
        name: shift.name,
        code: shift.code,
        start_time: shift.start_time?.slice(0, 5) || '',
        end_time: shift.end_time?.slice(0, 5) || '',
        status: shift.status,
    });

    const statusOptions = [
        { value: 'Aktif', label: 'Aktif' },
        { value: 'Nonaktif', label: 'Nonaktif' },
    ];

    return (
        <AppLayout>
            <Head title={`Edit Shift - ${shift.name}`} />
            <PageBreadcrumb pageTitle={`Edit: ${shift.name}`} />

            <div className="max-w-2xl">
                <ComponentCard
                    title="Edit Shift"
                    desc="Perbarui data shift"
                    action={
                        <Link href={route('shifts.index')}>
                            <Button variant="outline" size="sm" icon={<ArrowLeftIcon className="w-4 h-4" />}>Kembali</Button>
                        </Link>
                    }
                >
                    <form onSubmit={(e) => { e.preventDefault(); put(route('shifts.update', shift.id)); }} className="space-y-5">
                        <div>
                            <Label>Nama *</Label>
                            <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                            {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                        </div>
                        <div>
                            <Label>Kode *</Label>
                            <Input type="text" value={data.code} onChange={(e) => setData('code', e.target.value)} />
                            {errors.code && <p className="mt-1 text-sm text-red-500">{errors.code}</p>}
                        </div>
                        <div>
                            <Label>Jam Mulai *</Label>
                            <Input type="time" value={data.start_time} onChange={(e) => setData('start_time', e.target.value)} />
                            {errors.start_time && <p className="mt-1 text-sm text-red-500">{errors.start_time}</p>}
                        </div>
                        <div>
                            <Label>Jam Selesai *</Label>
                            <Input type="time" value={data.end_time} onChange={(e) => setData('end_time', e.target.value)} />
                            {errors.end_time && <p className="mt-1 text-sm text-red-500">{errors.end_time}</p>}
                            <p className="mt-1 text-xs text-[#6C757D]">Untuk shift malam, jam selesai boleh lebih kecil dari jam mulai (contoh: 22:00 - 06:00).</p>
                        </div>
                        <div>
                            <Label>Status</Label>
                            <Select options={statusOptions} placeholder="Pilih Status" defaultValue={data.status} onChange={(val) => setData('status', val)} />
                            {errors.status && <p className="mt-1 text-sm text-red-500">{errors.status}</p>}
                        </div>
                        <div className="flex gap-3 pt-4 border-t border-[#F1F3F5]">
                            <Button type="submit" icon={<CheckIcon className="w-4 h-4" />}>Simpan</Button>
                            <Button type="button" variant="outline" onClick={() => window.history.back()}>Batal</Button>
                        </div>
                    </form>
                </ComponentCard>
            </div>
        </AppLayout>
    );
}
