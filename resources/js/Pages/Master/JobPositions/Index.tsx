import React, { useState } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import SearchInput from '../../../Tailadmin/components/form/input/SearchInput';
import TableActions from '../../../Tailadmin/components/common/TableActions';
import EmptyState from '../../../Tailadmin/components/common/EmptyState';
import Pagination from '../../../Tailadmin/components/common/Pagination';
import ImportExportToolbar from '../../../Components/ImportExport/ImportExportToolbar';
import ImportModal from '../../../Components/ImportExport/ImportModal';

export default function Index({ positions, filters, roles }: any) {
    const permissions = (usePage().props.auth as any)?.user?.permissions || [];
    const canCreate = permissions.includes('create job positions');
    const canEdit = permissions.includes('edit job positions');
    const canDelete = permissions.includes('delete job positions');
    const [importModalOpen, setImportModalOpen] = useState(false);

    const handleDelete = (id: number) => {
        if (confirm('Hapus jabatan ini?')) {
            router.delete(route('job-positions.destroy', id));
        }
    };

    return (
        <>
            <Head title="Jabatan" />
            <PageBreadcrumb pageTitle="Jabatan" />
            <ComponentCard title="Daftar Jabatan">
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    {canCreate && (
                        <Link href={route('job-positions.create')}><Button>Tambah Jabatan</Button></Link>
                    )}
                    <SearchInput
                        placeholder="Cari nama jabatan..."
                        routeName="job-positions.index"
                        filters={filters}
                    />
                    {canCreate && (<ImportExportToolbar
                        importUrl={route('job-positions.import')}
                        previewUrl={route('job-positions.import.preview')}
                        exportUrl={route('job-positions.export')}
                        onImportClick={() => setImportModalOpen(true)}/>
                    )}
                </div>
                {canCreate && (
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('job-positions.import')}
                    previewUrl={route('job-positions.import.preview')}
                    templateUrl={route('job-positions.import-template')}
                    title="Jabatan"
                    fields={[
                        { key: 'name', label: 'Nama', required: true },
                        { key: 'level', label: 'Level', required: false },
                        { key: 'role_name', label: 'Role', required: false },
                    ]}
                />
                )}
                {positions.data.length === 0 ? (
                    <EmptyState
                        icon="🪪"
                        title="Belum ada jabatan"
                        message="Tambahkan jabatan seperti Staff, Leader, Manager."
                        actionLabel={canCreate ? "Tambah Jabatan" : undefined}
                        actionRoute={canCreate ? route('job-positions.create') : undefined}
                    />
                ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full">
                        <thead className="bg-[#F8F9FC] border-b border-[#E9ECEF]">
                            <tr>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Nama</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Level</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Role</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {positions.data.map((p: any) => (
                                <tr key={p.id} className="border-b border-[#F1F3F5] hover:bg-[#F8F9FC] transition-all duration-150">
                                    <td className="px-4 py-3 text-sm text-[#1A1D23] font-medium">{p.name}</td>
                                    <td className="px-4 py-3 text-[13px] text-[#6C757D]">{p.level || '-'}</td>
                                    <td className="px-4 py-3 text-[13px] text-[#6C757D]">
                                        {p.role_name ? (
                                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#EEF2FF] text-[#3B5BDB]">
                                                {p.role_name}
                                            </span>
                                        ) : '-'}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-[#1A1D23]">
                                        <TableActions
                                            editRoute={canEdit ? route('job-positions.edit', p.id) : undefined}
                                            onDelete={canDelete ? () => handleDelete(p.id) : undefined}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                )}
                {positions.total > positions.per_page && (
                    <Pagination
                        prevUrl={positions.prev_page_url}
                        nextUrl={positions.next_page_url}
                        currentPage={positions.current_page}
                        lastPage={positions.last_page}
                        from={positions.from}
                        to={positions.to}
                        total={positions.total}
                    />
                )}
            </ComponentCard>
        </>
    );
}

Index.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
