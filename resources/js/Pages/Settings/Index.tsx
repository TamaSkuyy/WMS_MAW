import React, { useState } from 'react';
import AppLayout from '../../Tailadmin/layout/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import PageBreadcrumb from '../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../Tailadmin/components/common/ComponentCard';
import Button from '../../Tailadmin/components/ui/button/Button';
import Switch from '../../Tailadmin/components/form/switch/Switch';
import Alert from '../../Tailadmin/components/ui/alert/Alert';

/**
 * Pengaturan aplikasi (khusus superadmin) — switch on/off fitur.
 *
 * Daftar fitur datang dari config/features.php (lewat SettingController).
 * Nilai efektifnya dibagikan ke semua halaman sebagai props `features`,
 * jadi komponen lain cukup baca `usePage().props.features.<key>`.
 */
interface FeatureDefinition {
    key: string;
    label: string;
    description: string | null;
    default: boolean;
    enabled: boolean;
}

export default function Index({ definitions = [] }: any) {
    const { flash = {} } = usePage().props as any;

    const [values, setValues] = useState<Record<string, boolean>>(() => {
        const initial: Record<string, boolean> = {};
        (definitions as FeatureDefinition[]).forEach((d) => {
            initial[d.key] = d.enabled;
        });
        return initial;
    });
    const [saving, setSaving] = useState(false);

    const dirty = (definitions as FeatureDefinition[]).some((d) => values[d.key] !== d.enabled);

    const toggle = (key: string, checked: boolean) => {
        setValues((prev) => ({ ...prev, [key]: checked }));
    };

    const save = () => {
        if (saving) return;

        setSaving(true);
        router.post(
            route('settings.update'),
            { features: values },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            }
        );
    };

    return (
        <>
            <Head title="Pengaturan" />
            <PageBreadcrumb pageTitle="Pengaturan" />

            {flash?.success && (
                <div className="mb-4"><Alert variant="success" title="Berhasil" message={flash.success} /></div>
            )}
            {flash?.error && (
                <div className="mb-4"><Alert variant="error" title="Gagal" message={flash.error} /></div>
            )}

            <ComponentCard
                title="Fitur Aplikasi"
                desc="Nyalakan atau matikan fitur untuk semua user. Perubahan langsung berlaku setelah disimpan."
            >
                {definitions.length === 0 ? (
                    <p className="py-6 text-center text-sm text-gray-400">
                        Belum ada fitur yang bisa diatur.
                    </p>
                ) : (
                    <div className="divide-y divide-gray-100 dark:divide-gray-800">
                        {(definitions as FeatureDefinition[]).map((feature) => (
                            <div key={feature.key} className="flex flex-wrap items-start justify-between gap-4 py-4">
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                                            {feature.label}
                                        </p>
                                        <span
                                            className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${
                                                values[feature.key]
                                                    ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400'
                                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
                                            }`}
                                        >
                                            {values[feature.key] ? 'Aktif' : 'Nonaktif'}
                                        </span>
                                    </div>
                                    {feature.description && (
                                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {feature.description}
                                        </p>
                                    )}
                                    <p className="mt-1 text-[11px] text-gray-400">
                                        Default: {feature.default ? 'ON' : 'OFF'}
                                    </p>
                                </div>
                                <div className="pt-1">
                                    <Switch
                                        key={`${feature.key}-${values[feature.key] ? 'on' : 'off'}`}
                                        label={values[feature.key] ? 'ON' : 'OFF'}
                                        defaultChecked={values[feature.key]}
                                        onChange={(checked) => toggle(feature.key, checked)}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {definitions.length > 0 && (
                    <div className="mt-6 flex items-center justify-end gap-3">
                        {dirty && <span className="text-xs text-amber-600">Ada perubahan belum disimpan</span>}
                        <Button onClick={save} disabled={saving || !dirty}>
                            {saving ? 'Menyimpan...' : 'Simpan Pengaturan'}
                        </Button>
                    </div>
                )}
            </ComponentCard>
        </>
    );
}

Index.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
