import { SupplierStatus, ReceiptStatus } from '../types';

export interface StatusColorClasses {
    dot: string;
    text: string;
    bg: string;
    border: string;
}

// Single source of truth for status -> color mapping, used by every component.
// Mapping follows the requested legend exactly:
//   Supplier cards: orange = shortage/in-progress (live), gray = standby, green = complete (done), red = alert
//   Parts table:    green = matched/complete, red = shortage/outstanding, orange = pending (no receipt)
const STATUS_COLOR_MAP: Record<SupplierStatus | ReceiptStatus, StatusColorClasses> = {
    // Supplier statuses
    live: {
        dot: 'bg-warning-500',
        text: 'text-warning-600 dark:text-warning-400',
        bg: 'bg-warning-50 dark:bg-warning-500/10',
        border: 'border-warning-300 dark:border-warning-500/40',
    },
    alert: {
        dot: 'bg-error-500',
        text: 'text-error-600 dark:text-error-400',
        bg: 'bg-error-50 dark:bg-error-500/10',
        border: 'border-error-300 dark:border-error-500/40',
    },
    done: {
        dot: 'bg-success-500',
        text: 'text-success-600 dark:text-success-400',
        bg: 'bg-success-50 dark:bg-success-500/10',
        border: 'border-success-300 dark:border-success-500/40',
    },
    standby: {
        dot: 'bg-gray-400',
        text: 'text-gray-500 dark:text-gray-400',
        bg: 'bg-gray-50 dark:bg-gray-800',
        border: 'border-gray-200 dark:border-gray-700',
    },

    // Part receipt statuses
    matched: {
        dot: 'bg-success-500',
        text: 'text-success-600 dark:text-success-400',
        bg: 'bg-success-50 dark:bg-success-500/10',
        border: 'border-success-300 dark:border-success-500/40',
    },
    shortage: {
        dot: 'bg-error-500',
        text: 'text-error-600 dark:text-error-400',
        bg: 'bg-error-50 dark:bg-error-500/10',
        border: 'border-error-300 dark:border-error-500/40',
    },
    pending: {
        dot: 'bg-warning-500',
        text: 'text-warning-600 dark:text-warning-400',
        bg: 'bg-warning-50 dark:bg-warning-500/10',
        border: 'border-warning-300 dark:border-warning-500/40',
    },
    over: {
        dot: 'bg-brand-500',
        text: 'text-brand-600 dark:text-brand-400',
        bg: 'bg-brand-50 dark:bg-brand-500/10',
        border: 'border-brand-300 dark:border-brand-500/40',
    },
};

export function getStatusColor(status: SupplierStatus | ReceiptStatus): StatusColorClasses {
    return STATUS_COLOR_MAP[status];
}
