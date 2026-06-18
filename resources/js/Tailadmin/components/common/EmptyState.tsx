import { Link } from '@inertiajs/react';
import Button from '../ui/button/Button';

interface EmptyStateProps {
    icon?: string;
    title: string;
    message?: string;
    actionLabel?: string;
    actionRoute?: string;
}

export default function EmptyState({
    icon = '📭',
    title,
    message = 'Belum ada data.',
    actionLabel,
    actionRoute,
}: EmptyStateProps) {
    return (
        <div className="bg-white rounded-xl border border-[#E9ECEF] flex flex-col items-center justify-center py-16 px-4">
            <div className="text-5xl mb-4">{icon}</div>
            <h3 className="text-lg font-semibold text-[#1A1D23] mb-1">
                {title}
            </h3>
            <p className="text-sm text-[#6C757D] text-center max-w-md mb-4">
                {message}
            </p>
            {actionLabel && actionRoute && (
                <Link href={actionRoute}>
                    <Button>{actionLabel}</Button>
                </Link>
            )}
        </div>
    );
}
