import { router } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import QrScanner from '../../../../Components/QrScanner';

interface SearchInputProps {
    placeholder?: string;
    routeName: string;
    filters?: Record<string, any>;
    className?: string;
    debounceMs?: number;
    /** Tampilkan tombol scan kamera di dalam kolom (default true). */
    scan?: boolean;
    /** 'barcode' (default) atau 'qr'. */
    scanMode?: 'barcode' | 'qr';
}

/**
 * Search input with debounce — auto-submits on typing.
 */
export default function SearchInput({
    placeholder = 'Cari...',
    routeName,
    filters = {},
    className = '',
    debounceMs = 300,
    scan = true,
    scanMode = 'barcode',
}: SearchInputProps) {
    const [value, setValue] = useState(filters?.search || '');
    const [scannerOpen, setScannerOpen] = useState(false);
    const mounted = useRef(false);

    useEffect(() => {
        // Skip mount pertama, hanya fire saat user mengetik
        if (!mounted.current) {
            mounted.current = true;
            return;
        }

        const timer = setTimeout(() => {
            const params = { ...filters, search: value || '' };
            Object.keys(params).forEach(k => {
                if (params[k] === '' || params[k] === undefined) delete params[k];
            });
            router.get(route(routeName), params, {
                preserveState: true,
                replace: true,
            });
        }, debounceMs);

        return () => clearTimeout(timer);
    }, [value]);

    // Sync external filter reset
    useEffect(() => {
        if (!filters?.search) setValue('');
    }, [filters?.search]);

    return (
        <div className={`relative ${className}`}>
            <svg
                className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#ADB5BD]"
                fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}
            >
                <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
                type="text"
                value={value}
                onChange={(e) => setValue(e.target.value)}
                placeholder={placeholder}
                className={`w-full pl-10 ${scan ? 'pr-20' : 'pr-10'} py-2.5 text-sm border border-[#DEE2E6] text-[#1A1D23] placeholder-[#ADB5BD] bg-white rounded-lg focus:border-[#3B5BDB] focus:ring-2 focus:ring-[#3B5BDB]/20 transition-all duration-150`}
            />
            <div className="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                {value && (
                    <button
                        type="button"
                        onClick={() => setValue('')}
                        className="w-6 h-6 flex items-center justify-center text-[#ADB5BD] hover:text-[#6C757D] transition-all duration-150"
                        title="Bersihkan"
                    >
                        ✕
                    </button>
                )}
                {scan && (
                    <button
                        type="button"
                        onClick={() => setScannerOpen(true)}
                        className="w-8 h-8 flex items-center justify-center rounded-md text-[#6C757D] hover:bg-[#F1F3F5] transition-all duration-150"
                        title="Scan barcode"
                    >
                        📷
                    </button>
                )}
            </div>
            {scan && (
                <QrScanner
                    isOpen={scannerOpen}
                    onClose={() => setScannerOpen(false)}
                    onScan={(code) => setValue(code)}
                    mode={scanMode}
                />
            )}
        </div>
    );
}
