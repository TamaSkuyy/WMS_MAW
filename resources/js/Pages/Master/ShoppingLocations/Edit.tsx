import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { ArrowLeftIcon, CheckIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';

export default function Edit({ location }: any) {
    const { data, setData, put, errors } = useForm({ name: location.name, barcode: location.barcode || '' });

    return (
        <>
            <Head title={`Edit Lokasi - ${location.name}`} />
            <PageBreadcrumb pageTitle={`Edit: ${location.name}`} />
            <div className="max-w-2xl">
                <ComponentCard
                    title="Edit Lokasi Tujuan"
                    desc="Perbarui data lokasi tujuan"
                    action={
                        <Link href={route('shopping-locations.index')}>
                            <Button variant="outline" size="sm" icon={<ArrowLeftIcon className="w-4 h-4" />}>Kembali</Button>
                        </Link>
                    }
                >
                    <form onSubmit={(e) => { e.preventDefault(); put(route('shopping-locations.update', location.id)); }} className="space-y-5">
                        <div>
                            <Label>Nama Lokasi *</Label>
                            <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                            {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                        </div>
                        <div>
                            <Label>Barcode</Label>
                            <Input type="text" value={data.barcode} onChange={(e) => setData('barcode', e.target.value)} placeholder="Scan atau ketik kode barcode (opsional)" />
                            {errors.barcode && <p className="mt-1 text-sm text-red-500">{errors.barcode}</p>}
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
