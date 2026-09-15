import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface PaginationProps {
    prevUrl?: string | null;
    nextUrl?: string | null;
    currentPage: number;
    lastPage: number;
    from?: number | null;
    to?: number | null;
    total?: number;
    /** Jumlah baris per halaman saat ini (dari paginator.per_page). */
    perPage?: number;
}

const PER_PAGE_OPTIONS = [10, 25, 50, 100];

/**
 * Footer pagination mobile-friendly:
 * - baris 1: info jumlah + pilih baris per halaman,
 * - baris 2: navigasi (Pertama/Sebelumnya/Berikutnya/Terakhir) + lompat halaman.
 * Tombol/tinggi kontrol 44px di HP (touch target), mengecil di desktop.
 * URL dibangun dari query string saat ini (usePage().url) supaya filter aktif
 * ikut terbawa.
 */
export default function Pagination({
    currentPage,
    lastPage,
    from,
    to,
    total,
    perPage,
}: PaginationProps) {
    const { url } = usePage();
    const [jumpTo, setJumpTo] = useState('');

    const [path, query = ''] = url.split('?');
    const currentPerPage = perPage ?? (Number(new URLSearchParams(query).get('per_page')) || 10);

    const navigate = (opts: { page?: number; perPage?: number }) => {
        const params = new URLSearchParams(query);

        if (opts.perPage !== undefined) {
            params.set('per_page', String(opts.perPage));
            params.set('page', '1');
        }
        if (opts.page !== undefined) {
            params.set('page', String(opts.page));
        }

        router.get(path, Object.fromEntries(params.entries()), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const goToPage = () => {
        const n = parseInt(jumpTo, 10);
        if (!Number.isFinite(n) || n < 1) return;
        navigate({ page: Math.min(n, Math.max(lastPage, 1)) });
        setJumpTo('');
    };

    const btnClass =
        'inline-flex items-center justify-center min-h-11 sm:min-h-10 px-3 text-sm border rounded-lg transition-colors whitespace-nowrap';
    const controlClass =
        'h-11 sm:h-10 px-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-brand-500/40';
    const disabledClass = 'text-gray-400 cursor-not-allowed opacity-60';
    const activeClass = 'hover:bg-gray-100 dark:hover:bg-gray-800';

    return (
        <div className="mt-4 space-y-3">
            {/* Baris 1 — info + jumlah baris per halaman */}
            <div className="flex flex-wrap items-center justify-between gap-2">
                {total !== undefined && (
                    <span className="text-xs sm:text-sm text-gray-500">
                        Menampilkan {from ?? 0}–{to ?? 0} dari {total}
                    </span>
                )}
                <label className="flex items-center gap-2 text-xs sm:text-sm text-gray-500">
                    Baris:
                    <select
                        value={currentPerPage}
                        onChange={(e) => navigate({ perPage: Number(e.target.value) })}
                        className={controlClass}
                        aria-label="Jumlah baris per halaman"
                    >
                        {PER_PAGE_OPTIONS.map((n) => (
                            <option key={n} value={n}>{n}</option>
                        ))}
                    </select>
                </label>
            </div>

            {/* Baris 2 — navigasi + lompat halaman */}
            <div className="flex flex-wrap items-center justify-center gap-2">
                <div className="flex items-center gap-1.5">
                    <button
                        type="button"
                        onClick={() => navigate({ page: 1 })}
                        disabled={currentPage <= 1}
                        className={`${btnClass} ${currentPage <= 1 ? disabledClass : activeClass}`}
                        title="Halaman pertama"
                        aria-label="Halaman pertama"
                    >
                        «<span className="hidden sm:inline">&nbsp;Pertama</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => navigate({ page: currentPage - 1 })}
                        disabled={currentPage <= 1}
                        className={`${btnClass} ${currentPage <= 1 ? disabledClass : activeClass}`}
                    >
                        Sebelumnya
                    </button>

                    <span className="px-1 text-sm text-gray-500 whitespace-nowrap">
                        Hal. {currentPage}/{Math.max(lastPage, 1)}
                    </span>

                    <button
                        type="button"
                        onClick={() => navigate({ page: currentPage + 1 })}
                        disabled={currentPage >= lastPage}
                        className={`${btnClass} ${currentPage >= lastPage ? disabledClass : activeClass}`}
                    >
                        Berikutnya
                    </button>
                    <button
                        type="button"
                        onClick={() => navigate({ page: lastPage })}
                        disabled={currentPage >= lastPage}
                        className={`${btnClass} ${currentPage >= lastPage ? disabledClass : activeClass}`}
                        title="Halaman terakhir"
                        aria-label="Halaman terakhir"
                    >
                        <span className="hidden sm:inline">Terakhir&nbsp;</span>»
                    </button>
                </div>

                <div className="flex items-center gap-1.5">
                    <input
                        type="number"
                        min={1}
                        max={Math.max(lastPage, 1)}
                        value={jumpTo}
                        onChange={(e) => setJumpTo(e.target.value)}
                        onKeyDown={(e) => { if (e.key === 'Enter') goToPage(); }}
                        placeholder="Ke hal."
                        className={`${controlClass} w-24`}
                        aria-label="Nomor halaman tujuan"
                    />
                    <button
                        type="button"
                        onClick={goToPage}
                        disabled={!jumpTo}
                        className={`${btnClass} ${!jumpTo ? disabledClass : activeClass}`}
                    >
                        Ke
                    </button>
                </div>
            </div>
        </div>
    );
}
