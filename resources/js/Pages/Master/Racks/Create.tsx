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

export default function Create() {
    const { data, setData, post, errors } = useForm({ code: '', zone: '' });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('racks.store'));
    };

    return (
        <AppLayout>
            <Head title="Tambah Rak" />
            <PageBreadcrumb pageTitle="Tambah Rak" />

            <div className="max-w-2xl">
                <ComponentCard
                    title="Rak Baru"
                    desc="Tambahkan rak penyimpanan baru"
                    action={
                        <Link href={route('racks.index')}>
                            <Button variant="outline" size="sm" icon={<ArrowLeftIcon className="w-4 h-4" />}>Kembali</Button>
                        </Link>
                    }
                >
                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div>
                            <Label>Kode *</Label>
                            <Input type="text" value={data.code} onChange={(e) => setData('code', e.target.value)} placeholder="contoh: A-01" />
                            {errors.code && <p className="mt-1 text-sm text-red-500">{errors.code}</p>}
                        </div>
                        <div>
                            <Label>Zona *</Label>
                            <Input type="text" value={data.zone} onChange={(e) => setData('zone', e.target.value)} placeholder="contoh: Zona A" />
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
