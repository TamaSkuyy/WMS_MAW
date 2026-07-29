import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { ArrowLeftIcon, CheckIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';

export default function Create() {
    const { data, setData, post, errors } = useForm({ name: '' });

    return (
        <>
            <Head title="Tambah Lokasi Tujuan" />
            <PageBreadcrumb pageTitle="Tambah Lokasi Tujuan" />
            <div className="max-w-2xl">
                <ComponentCard
                    title="Lokasi Tujuan Baru"
                    desc="Tambahkan lokasi tujuan pengiriman"
                    action={
                        <Link href={route('shopping-locations.index')}>
                            <Button variant="outline" size="sm" icon={<ArrowLeftIcon className="w-4 h-4" />}>Kembali</Button>
                        </Link>
                    }
                >
                    <form onSubmit={(e) => { e.preventDefault(); post(route('shopping-locations.store')); }} className="space-y-5">
                        <div>
                            <Label>Nama Lokasi *</Label>
                            <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="contoh: Gudang Jakarta" />
                            {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
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

Create.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
