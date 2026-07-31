import React, { useEffect, useRef } from 'react';
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';

interface ScanFeedback {
    message: string;
    type: 'ok' | 'warning' | 'error';
}

interface QrScannerProps {
    isOpen: boolean;
    onClose: () => void;
    onScan: (decoded: string) => void;
    autoClose?: boolean;
    feedback?: ScanFeedback | null;
    /** 'barcode' = wide scan box for 1D barcodes, 'qr' = square box for QR codes */
    mode?: 'qr' | 'barcode';
}

const feedbackStyle: Record<ScanFeedback['type'], string> = {
    ok:      'bg-green-500 text-white',
    warning: 'bg-yellow-400 text-yellow-900',
    error:   'bg-orange-500 text-white',
};

// QR Code + common 1D barcodes
const SCAN_FORMATS = [
    Html5QrcodeSupportedFormats.QR_CODE,
    Html5QrcodeSupportedFormats.CODE_128,
    Html5QrcodeSupportedFormats.CODE_39,
    Html5QrcodeSupportedFormats.EAN_13,
    Html5QrcodeSupportedFormats.EAN_8,
];

export default function QrScanner({ isOpen, onClose, onScan, autoClose = true, feedback, mode = 'qr' }: QrScannerProps) {
    const scannerRef = useRef<Html5Qrcode | null>(null);
    const lastScanRef = useRef<{ code: string; time: number }>({ code: '', time: 0 });
    const DEBOUNCE_MS = 800;

    const isBarcode = mode === 'barcode';

    useEffect(() => {
        if (!isOpen) return;
        let active = true;

        const scanner = new Html5Qrcode('qr-reader', { verbose: false });
        scannerRef.current = scanner;

        scanner
            .start(
                { facingMode: 'environment' },
                {
                    fps: 10,
                    qrbox: isBarcode
                        ? (w: number, h: number) => ({
                            width: Math.floor(w * 0.85),
                            height: Math.floor(h * 0.45),
                          })
                        : { width: 250, height: 250 },
                    formatsToSupport: SCAN_FORMATS,
                    experimentalFeatures: { useBarCodeDetectorIfSupported: true },
                    aspectRatio: isBarcode ? 1.333 : 1.0,
                },
                (decodedText) => {
                    const code = decodedText.trim();
                    const now = Date.now();
                    if (code === lastScanRef.current.code && now - lastScanRef.current.time < DEBOUNCE_MS) return;
                    lastScanRef.current = { code, time: now };
                    onScan(code);
                    if (autoClose) onClose();
                },
                undefined
            )
            .then(() => {
                if (!active) scanner.stop().then(() => scanner.clear()).catch(() => {});
            })
            .catch((err) => {
                console.error('Scanner failed to start:', err);
            });

        return () => {
            active = false;
            const s = scannerRef.current;
            if (s) {
                s.stop().then(() => s.clear()).catch(() => {});
            }
        };
    }, [isOpen, onScan, onClose, autoClose, isBarcode]);

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
            <div className="bg-white dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-sm mx-4 p-4">
                <div className="flex justify-between items-center mb-3">
                    <h3 className="font-semibold text-gray-800 dark:text-white">
                        Scan {isBarcode ? 'Barcode' : 'QR Code'}
                    </h3>
                    <button
                        onClick={onClose}
                        className="text-gray-400 hover:text-gray-600 text-2xl leading-none"
                    >
                        &times;
                    </button>
                </div>
                <div className="relative">
                    <div id="qr-reader" className="w-full rounded-lg overflow-hidden" />
                    {feedback && (
                        <div className={`absolute bottom-0 left-0 right-0 px-3 py-2 text-sm font-medium text-center rounded-b-lg ${feedbackStyle[feedback.type]}`}>
                            {feedback.message}
                        </div>
                    )}
                </div>
                <p className="mt-3 text-xs text-center text-gray-500">
                    {isBarcode
                        ? 'Arahkan kamera ke Barcode (kode batang)'
                        : 'Arahkan kamera ke QR Code pada barang'}
                </p>
            </div>
        </div>
    );
}
