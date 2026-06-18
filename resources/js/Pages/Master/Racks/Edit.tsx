import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeftIcon, CheckIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';
import { Link } from '@inertiajs/react';

export default function Edit({ rack }: any) {
    const { data, setData, put, errors } = useForm({ code: rack.code, zone: rack.zone });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('racks.update', rack.id));
    };

    return (
        <AppLayout>
            <Head title={`Edit Rak - ${rack.code}`} />
            <PageBreadcrumb pageTitle={`Edit: ${rack.code}`} />

            <div className="max-w-2xl">
                <ComponentCard
                    title="Edit Rak"
                    desc="Perbarui data rak penyimpanan"
                    action={
                        <Link href={route('racks.index')}>
                            <Button variant="outline" size="sm" icon={<ArrowLeftIcon className="w-4 h-4" />}>Kembali</Button>
                        </Link>
                    }
                >
                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div>
                            <Label>Kode *</Label>
                            <Input type="text" value={data.code} onChange={(e) => setData('code', e.target.value)} />
                            {errors.code && <p className="mt-1 text-sm text-red-500">{errors.code}</p>}
                        </div>
                        <div>
                            <Label>Zona *</Label>
                            <Input type="text" value={data.zone} onChange={(e) => setData('zone', e.target.value)} />
                            {errors.zone && <p className="mt-1 text-sm text-red-500">{errors.zone}</p>}
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
