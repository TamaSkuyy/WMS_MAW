import React from 'react';

interface QtyStepperProps {
    value: number;
    onChange: (n: number) => void;
    min?: number;
    max?: number;
}

export default function QtyStepper({ value, onChange, min = 0, max }: QtyStepperProps) {
    const clamp = (n: number) => {
        if (n < min) n = min;
        if (max !== undefined && n > max) n = max;
        return n;
    };

    return (
        <div className="flex items-center gap-1">
            <button
                type="button"
                onClick={() => onChange(clamp(value - 1))}
                disabled={value <= min}
                className="h-11 w-11 inline-flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 text-xl font-semibold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed"
            >
                −
            </button>
            <input
                type="text"
                inputMode="numeric"
                value={value}
                onFocus={(e) => e.target.select()}
                onChange={(e) => {
                    const parsed = parseInt(e.target.value);
                    onChange(clamp(isNaN(parsed) ? min : parsed));
                }}
                className="h-11 w-16 text-center text-base font-semibold tabular-nums border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-lg"
            />
            <button
                type="button"
                onClick={() => onChange(clamp(value + 1))}
                disabled={max !== undefined && value >= max}
                className="h-11 w-11 inline-flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 text-xl font-semibold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed"
            >
                +
            </button>
        </div>
    );
}
