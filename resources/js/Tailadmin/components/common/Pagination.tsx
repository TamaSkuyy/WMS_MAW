import { Link } from '@inertiajs/react';

interface PaginationProps {
    prevUrl?: string | null;
    nextUrl?: string | null;
    currentPage: number;
    lastPage: number;
    from?: number | null;
    to?: number | null;
    total?: number;
}

/**
 * Pagination footer mobile-friendly: tombol 44px, menumpuk rapi di HP.
 * Memakai path relatif dari Laravel paginator (prev_page_url/next_page_url).
 */
export default function Pagination({ prevUrl, nextUrl, currentPage, lastPage, from, to, total }: PaginationProps) {
    // Normalisasi URL absolut (mis. http:// dari APP_URL yang salah konfigurasi)
    // menjadi path relatif — mencegah blokir CSP connect-src 'self' di https.
    const toRelative = (url: string | null | undefined): string | null | undefined => {
        if (!url) return url;
        if (url.startsWith('http://') || url.startsWith('https://')) {
            try {
                const u = new URL(url);
                return u.pathname + u.search;
            } catch {
                return url;
            }
        }
        return url;
    };

    const btnClass = 'inline-flex items-center justify-center min-h-11 px-4 text-sm border rounded-lg transition-colors';
    const disabledClass = 'text-gray-400 cursor-not-allowed opacity-60';

    return (
        <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            {total !== undefined && (
                <div className="text-xs sm:text-sm text-gray-500 text-center sm:text-left">
                    Menampilkan {from ?? 0} sampai {to ?? 0} dari {total}
                </div>
            )}
            <div className="flex items-center justify-center gap-2">
                {prevUrl ? (
                    <Link href={toRelative(prevUrl) as string} className={`${btnClass} hover:bg-gray-100 dark:hover:bg-gray-800`}>
                        Sebelumnya
                    </Link>
                ) : (
                    <span className={`${btnClass} ${disabledClass}`}>Sebelumnya</span>
                )}
                <span className="px-3 text-sm text-gray-500 whitespace-nowrap">
                    Halaman {currentPage} dari {lastPage}
                </span>
                {nextUrl ? (
                    <Link href={toRelative(nextUrl) as string} className={`${btnClass} hover:bg-gray-100 dark:hover:bg-gray-800`}>
                        Berikutnya
                    </Link>
                ) : (
                    <span className={`${btnClass} ${disabledClass}`}>Berikutnya</span>
                )}
            </div>
        </div>
    );
}
