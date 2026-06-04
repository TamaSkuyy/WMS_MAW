import React, { useState, useRef, useEffect } from 'react';
import Button from '../../Tailadmin/components/ui/button/Button';

interface ExportButtonProps {
  baseUrl: string;
}

export default function ExportButton({ baseUrl }: ExportButtonProps) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  const handleExport = (format: string) => {
    const url = new URL(baseUrl, window.location.origin);
    url.searchParams.set('format', format);
    window.open(url.toString(), '_blank');
    setOpen(false);
  };

  return (
    <div ref={ref} className="relative inline-block">
      <Button variant="outline" onClick={() => setOpen(!open)}>
        Export
      </Button>
      {open && (
        <div className="absolute right-0 mt-1 w-36 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50">
          <button
            className="block w-full text-left px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 rounded-t-lg"
            onClick={() => handleExport('xlsx')}
          >
            Excel (.xlsx)
          </button>
          <button
            className="block w-full text-left px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700"
            onClick={() => handleExport('csv')}
          >
            CSV
          </button>
          <button
            className="block w-full text-left px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 rounded-b-lg"
            onClick={() => handleExport('pdf')}
          >
            PDF
          </button>
        </div>
      )}
    </div>
  );
}
