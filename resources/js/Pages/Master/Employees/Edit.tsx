import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeftIcon, CheckIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';
import Select from '../../../Tailadmin/components/form/Select';
import { Link } from '@inertiajs/react';

export default function Edit({ employee, jobPositions, workLocations, departments, shifts, users }: any) {
    const { data, setData, put, errors } = useForm({
        name: employee.name,
        nik: employee.nik || '',
        job_position_id: employee.job_position_id || '',
        work_location_id: employee.work_location_id || '',
        department_id: employee.department_id || '',
        shift_id: employee.shift_id || '',
        user_id: employee.user_id || '',
        phone: employee.phone || '',
        email: employee.email || '',
        status: employee.status,
    });

    const statusOptions = [
        { value: 'Aktif', label: 'Aktif' },
        { value: 'Nonaktif', label: 'Nonaktif' },
    ];

    return (
        <AppLayout>
            <Head title={`Edit Karyawan - ${employee.name}`} />
            <PageBreadcrumb pageTitle={`Edit: ${employee.name}`} />

            <div className="max-w-2xl">
                <ComponentCard
                    title="Edit Karyawan"
                    desc="Perbarui data karyawan"
                    action={
                        <Link href={route('employees.index')}>
                            <Button variant="outline" size="sm" icon={<ArrowLeftIcon className="w-4 h-4" />}>Kembali</Button>
                        </Link>
                    }
                >
                    <form onSubmit={(e) => { e.preventDefault(); put(route('employees.update', employee.id)); }} className="space-y-5">
                        <div>
                            <Label>Nama *</Label>
                            <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                            {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                        </div>
                        <div>
                            <Label>NIK</Label>
                            <Input type="text" value={data.nik} onChange={(e) => setData('nik', e.target.value)} />
                            {errors.nik && <p className="mt-1 text-sm text-red-500">{errors.nik}</p>}
                        </div>
                        <div>
                            <Label>Jabatan</Label>
                            <SearchableSelect
                                options={jobPositions.map((jp: any) => ({ value: jp.id, label: jp.name }))}
                                value={data.job_position_id}
                                onChange={(val) => setData('job_position_id', val)}
                                placeholder="Cari jabatan..."
                            />
                            {errors.job_position_id && <p className="mt-1 text-sm text-red-500">{errors.job_position_id}</p>}
                        </div>
                        <div>
                            <Label>Lokasi Kerja</Label>
                            <SearchableSelect
                                options={workLocations.map((wl: any) => ({ value: wl.id, label: wl.name }))}
                                value={data.work_location_id}
                                onChange={(val) => setData('work_location_id', val)}
                                placeholder="Cari lokasi kerja..."
                            />
                            {errors.work_location_id && <p className="mt-1 text-sm text-red-500">{errors.work_location_id}</p>}
                        </div>
                        <div>
                            <Label>Departemen</Label>
                            <SearchableSelect
                                options={departments.map((d: any) => ({ value: d.id, label: d.name }))}
                                value={data.department_id}
                                onChange={(val) => setData('department_id', val)}
                                placeholder="Cari departemen..."
                            />
                            {errors.department_id && <p className="mt-1 text-sm text-red-500">{errors.department_id}</p>}
                        </div>
                        <div>
                            <Label>Shift</Label>
                            <SearchableSelect
                                options={shifts.map((s: any) => ({ value: s.id, label: s.name }))}
                                value={data.shift_id}
                                onChange={(val) => setData('shift_id', val)}
                                placeholder="Cari shift..."
                            />
                            {errors.shift_id && <p className="mt-1 text-sm text-red-500">{errors.shift_id}</p>}
                        </div>
                        <div>
                            <Label>User / Login</Label>
                            <SearchableSelect
                                options={users.map((u: any) => ({ value: u.id, label: `${u.name} (${u.email})` }))}
                                value={data.user_id}
                                onChange={(val) => setData('user_id', val)}
                                placeholder="Cari user..."
                            />
                            {errors.user_id && <p className="mt-1 text-sm text-red-500">{errors.user_id}</p>}
                        </div>
                        <div>
                            <Label>Telepon</Label>
                            <Input type="text" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                            {errors.phone && <p className="mt-1 text-sm text-red-500">{errors.phone}</p>}
                        </div>
                        <div>
                            <Label>Email</Label>
                            <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                            {errors.email && <p className="mt-1 text-sm text-red-500">{errors.email}</p>}
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
