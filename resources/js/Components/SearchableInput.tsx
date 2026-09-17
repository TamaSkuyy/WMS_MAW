import React, { useEffect, useMemo, useRef, useState } from 'react';

interface SearchableInputProps {
    value: string;
    onChange: (value: string) => void;
    /** Daftar pilihan (biasanya gabungan master data + nilai yang pernah dipakai). */
    options?: string[];
    placeholder?: string;
    id?: string;
    /** Batas jumlah pilihan yang ditampilkan saat dropdown terbuka. */
    maxOptions?: number;
    className?: string;
}

/**
 * Input teks + dropdown pilihan yang bisa DICARI (combobox).
 *
 * Dipakai untuk isian yang user-nya tidak perlu hafal daftar nilainya:
 * tinggal ketik sebagian ("for") lalu pilih "Toyota Fortuner".
 * Nilai tetap boleh diketik bebas (tidak harus ada di daftar), jadi tidak
 * memblokir kalau ada data yang belum ada di master.
 */
export default function SearchableInput({
    value,
    onChange,
    options = [],
    placeholder,
    id,
    maxOptions = 50,
    className = '',
}: SearchableInputProps) {
    const [open, setOpen] = useState(false);
    const wrapRef = useRef<HTMLDivElement>(null);

    // Tutup dropdown kalau klik di luar (pola yang sama dengan ExportButton).
    useEffect(() => {
        const handler = (e: MouseEvent) => {
            if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const filtered = useMemo(() => {
        const q = value.trim().toLowerCase();
        const list = q === '' ? options : options.filter((option) => option.toLowerCase().includes(q));

        return list.slice(0, maxOptions);
    }, [options, value, maxOptions]);

    return (
        <div ref={wrapRef} className={`relative ${className}`}>
            <input
                id={id}
                type="text"
                value={value}
                autoComplete="off"
                onChange={(e) => {
                    onChange(e.target.value);
                    setOpen(true);
                }}
                onFocus={() => setOpen(true)}
                placeholder={placeholder}
                className="w-full border border-[#DEE2E6] rounded-lg px-3.5 py-2.5 text-sm text-[#1A1D23] placeholder-[#ADB5BD] bg-white focus:border-[#3B5BDB] focus:ring-2 focus:ring-[#3B5BDB]/20 transition-all duration-150 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            />

            {open && filtered.length > 0 && (
                <div className="absolute z-50 mt-1 max-h-52 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                    {filtered.map((option) => {
                        const isSelected = option.toLowerCase() === value.trim().toLowerCase();

                        return (
                            <button
                                key={option}
                                type="button"
                                // preventDefault → input tidak blur sebelum onClick jalan
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => {
                                    onChange(option);
                                    setOpen(false);
                                }}
                                className={`block w-full px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-700 ${
                                    isSelected
                                        ? 'bg-brand-50 font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300'
                                        : 'text-gray-700 dark:text-gray-200'
                                }`}
                            >
                                {option}
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
