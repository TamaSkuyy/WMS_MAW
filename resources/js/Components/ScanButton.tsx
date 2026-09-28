import React, { useState } from 'react';
import Button from '../Tailadmin/components/ui/button/Button';
import QrScanner from './QrScanner';

interface ScanButtonProps {
    /** Dipanggil dengan teks hasil scan (biasanya part number / kode). */
    onScan: (code: string) => void;
    title?: string;
    /** 'barcode' = kotak lebar untuk barcode 1D, 'qr' = kotak persegi. */
    mode?: 'qr' | 'barcode';
    /** 'sm' = tombol kecil desktop (default), 'lg' = 44×44 px untuk area sentuh HP. */
    size?: 'sm' | 'lg';
}

/**
 * Tombol scan barcode/QR berbasis kamera (html5-qrcode).
 * Drop-in di samping input pencarian / pemilih produk mana pun.
 */
export default function ScanButton({ onScan, title = 'Scan barcode', mode = 'barcode', size = 'sm' }: ScanButtonProps) {
    const [open, setOpen] = useState(false);

    return (
        <>
            {size === 'lg' ? (
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    title={title}
                    aria-label={title}
                    className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-[#DEE2E6] text-base text-[#6C757D] transition-all duration-150 hover:bg-[#F8F9FC] dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    📷
                </button>
            ) : (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setOpen(true)}
                    title={title}
                >
                    📷
                </Button>
            )}
            <QrScanner
                isOpen={open}
                onClose={() => setOpen(false)}
                onScan={(code) => onScan(code)}
                mode={mode}
            />
        </>
    );
}
