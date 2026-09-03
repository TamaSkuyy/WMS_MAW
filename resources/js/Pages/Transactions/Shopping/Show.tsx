import React, { useState } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { PencilIcon, ArrowLeftIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Alert from '../../../Tailadmin/components/ui/alert/Alert';

export default function Show({ shopping, corrections = [], canCorrect = false }: any) {
    const permissions = (usePage().props.auth as any)?.user?.permissions || [];
    const { flash = {} } = usePage().props as any;
    const canEdit = permissions.includes('edit shoppings');
    const canShip = permissions.includes('ship shoppings');
    const [submitting, setSubmitting] = useState(false);

    const statusColors: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800',
        shipped: 'bg-blue-100 text-blue-800',
        cripple: 'bg-red-100 text-red-800',
        completed: 'bg-green-100 text-green-800',
    };

    const handleShip = () => {
        if (submitting) return;
        const crippleNote = shopping.is_cripple
            ? ' Barang ditandai CRIPPLE (part tidak lengkap) — status akhir akan Cripple.'
            : '';
        if (confirm(`Proses pengiriman ini? Stok akan dikurangi.${crippleNote}`)) {
            setSubmitting(true);
            router.post(route('shoppings.ship', shopping.id), {}, {
                onFinish: () => setSubmitting(false),
            });
        }
    };

    return (
        <>
            <Head title={`Shopping ke ${shopping.shopping_location?.name || '-'}`} />
            <PageBreadcrumb pageTitle={`Detail: ${shopping.shopping_location?.name || '-'}`} />

            {flash?.success && (
                <div className="mb-4"><Alert variant="success" title="Berhasil" message={flash.success} /></div>
            )}
            {flash?.error && (
                <div className="mb-4"><Alert variant="error" title="Gagal" message={flash.error} /></div>
            )}

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div className="xl:col-span-1">
                    <ComponentCard title="Info Shopping" desc="Detail pengiriman barang">
                        <dl className="space-y-4">
                            <div><dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Mitra</dt><dd className="text-sm text-[#1A1D23]">{shopping.shopping_location?.name || '-'}</dd></div>
                            <div><dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Tanggal</dt><dd className="text-sm text-[#1A1D23]">{shopping.shopping_date ? new Date(shopping.shopping_date).toLocaleDateString('id-ID', {day:'2-digit',month:'2-digit',year:'numeric'}) : '-'}</dd></div>
                            <div><dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Status</dt><dd><span className={`inline-block px-2 py-1 text-xs font-medium rounded-full ${statusColors[shopping.status]}`}>{shopping.status}</span></dd></div>
                            {shopping.is_cripple && <div><dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Cripple</dt><dd><span className="inline-block px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">Ya — part tidak lengkap</span></dd></div>}
                            {shopping.shipped_at && <div><dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Dikirim</dt><dd className="text-sm text-[#1A1D23]">{shopping.shippedBy?.name || '—'} — {new Date(shopping.shipped_at).toLocaleString('id-ID', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit',second:'2-digit'})}</dd></div>}
                            {shopping.notes && <div><dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Catatan</dt><dd className="text-sm text-[#1A1D23]">{shopping.notes}</dd></div>}
                            <div><dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Frame #</dt><dd className="text-sm text-[#1A1D23] font-mono">{shopping.frame_number || '—'}</dd></div>
                        </dl>
                        <div className="mt-6 flex gap-2 pt-4 border-t border-[#F1F3F5] flex-wrap items-center">
                            {shopping.status === 'draft' && (
                                <>{canEdit && <Link href={route('shoppings.edit', shopping.id)}><Button icon={<PencilIcon className="w-4 h-4" />} size="sm">Edit</Button></Link>}
                                {canShip && shopping.items.length > 0 && <Button variant="outline" size="sm" onClick={handleShip} disabled={submitting}>
                                    {submitting ? 'Memproses...' : 'Kirim Sekarang'}
                                </Button>}
                                {canShip && shopping.items.length === 0 && (
                                    <span className="inline-flex items-center text-xs text-amber-600 font-medium">⚠ Part tidak lengkap — tambah item dulu sebelum kirim</span>
                                )}</>
                            )}
                            {canCorrect && (shopping.status === 'shipped' || shopping.status === 'cripple') && (
                                <Link href={route('shoppings.edit', shopping.id)}>
                                    <Button variant="outline" size="sm">✏️ Koreksi</Button>
                                </Link>
                            )}
                            <Link href={route('shoppings.index')}><Button variant="outline" size="sm">Kembali</Button></Link>
                        </div>
                    </ComponentCard>
                </div>
                <div className="xl:col-span-2">
                    <ComponentCard title="Items" desc="Daftar produk dalam pengiriman">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Part #</th>
                                        <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                        <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Model</th>
                                        <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Rak</th>
                                        <th className="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                    {shopping.items.map((item: any) => (
                                        <tr key={item.id}>
                                            <td className="px-4 py-2.5 text-xs font-mono">{item.product?.part_number}</td>
                                            <td className="px-4 py-2.5 text-sm">{item.product?.name}</td>
                                            <td className="px-4 py-2.5 text-sm text-gray-500">{item.product?.vehicle_model?.name || '-'}</td>
                                            <td className="px-4 py-2.5 text-sm font-mono">{item.rack?.code}</td>
                                            <td className="px-4 py-2.5 text-sm font-medium text-center tabular-nums">{item.quantity}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </ComponentCard>
                </div>
            </div>

            {corrections.length > 0 && (
                <div className="mt-6">
                    <ComponentCard title={`Riwayat Koreksi (${corrections.length})`} desc="Perubahan data final oleh user berpermission — stok disesuaikan otomatis">
                        <div className="space-y-4">
                            {corrections.map((c: any) => (
                                <div key={c.id} className="rounded-lg border border-amber-200 dark:border-amber-900/40 p-3">
                                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400 mb-1.5">
                                        <span>✏️ {new Date(c.created_at).toLocaleString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                                        <span className="font-medium text-gray-700 dark:text-gray-300">oleh {c.user?.name || '-'}</span>
                                    </div>
                                    <p className="text-sm text-gray-700 dark:text-gray-300 mb-2">📝 {c.reason}</p>
                                    {c.deltas && c.deltas.length > 0 && (
                                        <ul className="space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                                            {c.deltas.filter((d: any) => d.delta !== 0).map((d: any, i: number) => (
                                                <li key={i}>
                                                    {d.part_number} · rak {d.rack_code} · stok {d.current} → {d.result}{' '}
                                                    <span className={d.delta > 0 ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold'}>
                                                        ({d.delta > 0 ? `+${d.delta}` : d.delta})
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            ))}
                        </div>
                    </ComponentCard>
                </div>
            )}
        </>
    );
}

Show.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
