import React, { useState } from 'react';
import Button from '../Tailadmin/components/ui/button/Button';

interface BulkDeleteBarProps {
    count: number;
    busy?: boolean;
    entityLabel: string;
    /** true = stok akan dikoreksi otomatis (cycle/shopping yang sudah diproses) */
    correctsStock?: boolean;
    onClear: () => void;
    onConfirm: () => void;
}

/**
 * Bar aksi hapus massal + dialog konfirmasi (superadmin).
 * Mengharuskan admin mencentang pernyataan sebelum tombol hapus aktif.
 */
export default function BulkDeleteBar({
    count,
    busy = false,
    entityLabel,
    correctsStock = true,
    onClear,
    onConfirm,
}: BulkDeleteBarProps) {
    const [open, setOpen] = useState(false);
    const [ack, setAck] = useState(false);

    if (count <= 0) return null;

    return (
        <>
            <div className="mb-3 flex flex-col gap-2 rounded-lg border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-900/20 px-3 py-3 text-sm text-red-700 dark:text-red-300 sm:flex-row sm:items-center sm:gap-3 sm:py-2">
                <span className="flex flex-wrap items-center gap-2">
                    <b>{count}</b> {entityLabel} dipilih
                    <button
                        type="button"
                        onClick={onClear}
                        className="inline-flex min-h-11 items-center underline hover:no-underline sm:min-h-0"
                    >
                        Bersihkan pilihan
                    </button>
                </span>
                <button
                    type="button"
                    onClick={() => { setAck(false); setOpen(true); }}
                    disabled={busy}
                    className="inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-60 sm:ml-auto sm:w-auto"
                >
                    🗑️ Hapus {count} terpilih
                </button>
            </div>

            {open && (
                <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 p-4">
                    <div className="bg-white dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-md p-5">
                        <h3 className="text-base font-semibold text-gray-800 dark:text-white/90 mb-2">
                            Hapus {count} {entityLabel}?
                        </h3>
                        <p className="text-sm text-gray-600 dark:text-gray-300 mb-3">
                            Tindakan ini permanen (superadmin). <b>Semua status</b> akan dihapus,
                            termasuk yang sudah diproses.
                        </p>
                        {correctsStock && (
                            <p className="text-xs text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 rounded-lg px-3 py-2 mb-3">
                                ⚠️ Stok akan <b>dikoreksi otomatis</b>: penerimaan dikurangi kembali,
                                pengiriman ditambahkan kembali. Jika stok tidak cukup, nilainya dijepit 0
                                dan dicatat sebagai peringatan.
                            </p>
                        )}
                        <label className="flex min-h-11 items-start gap-3 text-sm text-gray-700 dark:text-gray-300 mb-4">
                            <input
                                type="checkbox"
                                checked={ack}
                                onChange={(e) => setAck(e.target.checked)}
                                className="mt-0.5 h-5 w-5 shrink-0"
                            />
                            <span>Saya paham dan setuju menghapus data ini secara permanen.</span>
                        </label>
                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button type="button" variant="outline" onClick={() => setOpen(false)} disabled={busy}>
                                Batal
                            </Button>
                            <button
                                type="button"
                                onClick={() => { setOpen(false); onConfirm(); }}
                                disabled={!ack || busy}
                                className="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                            >
                                {busy ? 'Menghapus...' : 'Hapus Permanen'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
