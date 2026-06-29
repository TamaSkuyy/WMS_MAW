import React, { useEffect, useRef } from 'react';
import { Html5Qrcode } from 'html5-qrcode';

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
}

const feedbackStyle: Record<ScanFeedback['type'], string> = {
    ok:      'bg-green-500 text-white',
    warning: 'bg-yellow-400 text-yellow-900',
    error:   'bg-orange-500 text-white',
};

export default function QrScanner({ isOpen, onClose, onScan, autoClose = true, feedback }: QrScannerProps) {
    const scannerRef = useRef<Html5Qrcode | null>(null);
    const lastScanRef = useRef<{ code: string; time: number }>({ code: '', time: 0 });
    const DEBOUNCE_MS = 800;

    useEffect(() => {
        if (!isOpen) return;

        const scanner = new Html5Qrcode('qr-reader');
        scannerRef.current = scanner;

        scanner
            .start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                (decodedText) => {
                    const code = decodedText.trim();
                    const now = Date.now();
                    if (
                        code === lastScanRef.current.code &&
                        now - lastScanRef.current.time < DEBOUNCE_MS
                    ) {
                        return;
                    }
                    lastScanRef.current = { code, time: now };
                    onScan(code);
                    if (autoClose) onClose();
                },
                undefined
            )
            .catch(() => {});

        return () => {
            scanner.stop().catch(() => {});
        };
    }, [isOpen]);

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
            <div className="bg-white dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-sm mx-4 p-4">
                <div className="flex justify-between items-center mb-3">
                    <h3 className="font-semibold text-gray-800 dark:text-white">Scan QR Code</h3>
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
                    Arahkan kamera ke QR Code pada barang
                </p>
            </div>
        </div>
    );
}
