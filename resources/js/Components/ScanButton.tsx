import React, { useState } from 'react';
import Button from '../Tailadmin/components/ui/button/Button';
import QrScanner from './QrScanner';

interface ScanButtonProps {
    /** Dipanggil dengan teks hasil scan (biasanya part number / kode). */
    onScan: (code: string) => void;
    title?: string;
    /** 'barcode' = kotak lebar untuk barcode 1D, 'qr' = kotak persegi. */
    mode?: 'qr' | 'barcode';
}

/**
 * Tombol scan barcode/QR berbasis kamera (html5-qrcode).
 * Drop-in di samping input pencarian / pemilih produk mana pun.
 */
export default function ScanButton({ onScan, title = 'Scan barcode', mode = 'barcode' }: ScanButtonProps) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setOpen(true)}
                title={title}
            >
                📷
            </Button>
            <QrScanner
                isOpen={open}
                onClose={() => setOpen(false)}
                onScan={(code) => onScan(code)}
                mode={mode}
            />
        </>
    );
}
