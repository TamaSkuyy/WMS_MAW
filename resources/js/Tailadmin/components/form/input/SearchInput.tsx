import { router } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';

interface SearchInputProps {
    placeholder?: string;
    routeName: string;
    filters?: Record<string, any>;
    className?: string;
    debounceMs?: number;
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
}: SearchInputProps) {
    const [value, setValue] = useState(filters?.search || '');
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
                className="w-full pl-10 pr-4 py-2.5 text-sm border border-[#DEE2E6] text-[#1A1D23] placeholder-[#ADB5BD] bg-white rounded-lg focus:border-[#3B5BDB] focus:ring-2 focus:ring-[#3B5BDB]/20 transition-all duration-150"
            />
            {value && (
                <button
                    onClick={() => setValue('')}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-[#ADB5BD] hover:text-[#6C757D] transition-all duration-150"
                >
                    ✕
                </button>
            )}
        </div>
    );
}
