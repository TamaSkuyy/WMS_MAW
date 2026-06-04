import React, { useEffect, useState } from 'react';

interface ImportProgressProps {
  importLogId: number;
  onComplete?: () => void;
}

interface ImportStatus {
  status: string;
  total_rows: number;
  processed_rows: number;
  skipped_rows: number;
  errors: Array<{ row: number; field: string; message: string }>;
}

export default function ImportProgress({ importLogId, onComplete }: ImportProgressProps) {
  const [status, setStatus] = useState<ImportStatus | null>(null);

  useEffect(() => {
    const poll = setInterval(async () => {
      try {
        const res = await fetch(route('import.status', importLogId));
        const data = await res.json();
        setStatus(data);

        if (data.status === 'completed' || data.status === 'failed') {
          clearInterval(poll);
          onComplete?.();
        }
      } catch {
        // retry on next poll
      }
    }, 3000);

    return () => clearInterval(poll);
  }, [importLogId]);

  if (!status) return null;

  const pct = status.total_rows > 0
    ? Math.round((status.processed_rows / status.total_rows) * 100)
    : 0;

  return (
    <div className="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
      <div className="flex justify-between items-center mb-2">
        <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
          {status.status === 'processing' ? 'Importing...' : status.status === 'completed' ? 'Completed' : 'Failed'}
        </span>
        <span className="text-xs text-gray-500">
          {status.processed_rows} / {status.total_rows} rows
        </span>
      </div>
      <div className="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
        <div
          className={`h-full rounded-full transition-all duration-500 ${
            status.status === 'failed' ? 'bg-red-500' : status.status === 'completed' ? 'bg-green-500' : 'bg-brand-500'
          }`}
          style={{ width: `${status.status === 'completed' ? 100 : pct}%` }}
        />
      </div>
      {(status.skipped_rows > 0 || (status.errors && status.errors.length > 0)) && (
        <div className="mt-2 text-xs text-gray-500">
          {status.skipped_rows > 0 && <span>{status.skipped_rows} duplicates skipped</span>}
          {status.errors && status.errors.length > 0 && (
            <span className="ml-3 text-red-500">{status.errors.length} errors</span>
          )}
        </div>
      )}
    </div>
  );
}
