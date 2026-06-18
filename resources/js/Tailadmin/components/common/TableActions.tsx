import { Link } from '@inertiajs/react';
import { ReactNode } from 'react';

interface ActionItem {
    icon: ReactNode;
    label: string;
    route: string;
    method?: 'get' | 'delete';
    color?: 'brand' | 'red';
    onClick?: () => void;
}

/**
 * Reusable icon action buttons with tooltip labels.
 * Eye icon = View, Pencil = Edit, Trash = Delete
 */
export function EyeIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        </svg>
    );
}

export function PencilIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
        </svg>
    );
}

export function TrashIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
    );
}

interface TableActionsProps {
    viewRoute?: string;
    editRoute?: string;
    onDelete?: () => void;
}

/**
 * Standard 3-button action row: Lihat (Eye) | Edit (Pencil) | Hapus (Trash)
 * Each button has a tooltip label on hover.
 */
export default function TableActions({ viewRoute, editRoute, onDelete }: TableActionsProps) {
    return (
        <div className="flex items-center gap-0.5">
            {viewRoute && (
                <Link
                    href={viewRoute}
                    className="group relative inline-flex items-center justify-center w-8 h-8 rounded-lg p-2 text-[#3B5BDB] bg-[#EEF2FF] hover:bg-[#DBE4FF] transition-all duration-150"
                    title="Lihat"
                >
                    <EyeIcon />
                    <span className="absolute -top-8 left-1/2 -translate-x-1/2 px-2 py-1 text-xs font-medium text-white bg-gray-800 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-50">
                        Lihat
                    </span>
                </Link>
            )}
            {editRoute && (
                <Link
                    href={editRoute}
                    className="group relative inline-flex items-center justify-center w-8 h-8 rounded-lg p-2 text-[#F59F00] bg-[#FFF9DB] hover:bg-[#FFF3BF] transition-all duration-150"
                    title="Edit"
                >
                    <PencilIcon />
                    <span className="absolute -top-8 left-1/2 -translate-x-1/2 px-2 py-1 text-xs font-medium text-white bg-gray-800 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-50">
                        Edit
                    </span>
                </Link>
            )}
            {onDelete && (
                <button
                    onClick={onDelete}
                    className="group relative inline-flex items-center justify-center w-8 h-8 rounded-lg p-2 text-[#FA5252] bg-[#FFF5F5] hover:bg-[#FFE3E3] transition-all duration-150"
                    title="Hapus"
                >
                    <TrashIcon />
                    <span className="absolute -top-8 left-1/2 -translate-x-1/2 px-2 py-1 text-xs font-medium text-white bg-gray-800 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-50">
                        Hapus
                    </span>
                </button>
            )}
        </div>
    );
}
