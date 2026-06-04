import React, { useState, useRef } from 'react';
import Button from '../../Tailadmin/components/ui/button/Button';
import ImportProgress from './ImportProgress';

interface ImportModalProps {
  isOpen: boolean;
  onClose: () => void;
  onComplete?: () => void;
  importUrl: string;
  previewUrl: string;
}

const SYSTEM_FIELDS = [
  { key: 'name', label: 'Name', required: true },
  { key: 'email', label: 'Email', required: true },
  { key: 'password', label: 'Password', required: true },
];

function getCsrfToken(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
}

function extractError(text: string, status: number): string {
  // Try JSON first
  try {
    const data = JSON.parse(text);
    if (data.message) return data.message;
    if (data.error) return data.error;
    // Laravel validation errors: {"errors": {"field": ["msg"]}}
    if (data.errors) {
      const first = Object.values(data.errors).flat()[0];
      if (first) return String(first);
    }
  } catch {}

  // HTML response — check if it's a redirect to login
  if (/login|Log In|Sign In/i.test(text) && /<html/i.test(text)) {
    return 'Session expired. Please refresh the page and try again.';
  }

  // Strip tags for readable text
  const stripped = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
  if (stripped.length > 0 && stripped.length < 500) return stripped;

  // Fallback
  return `Server error (HTTP ${status})`;
}

export default function ImportModal({ isOpen, onClose, onComplete, importUrl, previewUrl }: ImportModalProps) {
  const [step, setStep] = useState<'upload' | 'mapping' | 'importing'>('upload');
  const [file, setFile] = useState<File | null>(null);
  const [headers, setHeaders] = useState<string[]>([]);
  const [totalRows, setTotalRows] = useState(0);
  const [columnMapping, setColumnMapping] = useState<Record<string, string>>({});
  const [importLogId, setImportLogId] = useState<number | null>(null);
  const [loading, setLoading] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);

  if (!isOpen) return null;

  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const f = e.target.files?.[0];
    if (!f) return;
    setFile(f);
    setLoading(true);

    const formData = new FormData();
    formData.append('file', f);

    try {
      const res = await fetch(previewUrl, {
        method: 'POST',
        body: formData,
        headers: {
          'X-CSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      const text = await res.text();

      if (!res.ok) throw new Error(extractError(text, res.status));

      let data: any;
      try {
        data = JSON.parse(text);
      } catch {
        throw new Error('Server returned an invalid response');
      }

      setHeaders(data.headers);
      setTotalRows(data.total_rows);

      const autoMapping: Record<string, string> = {};
      for (const field of SYSTEM_FIELDS) {
        const match = data.headers.find(
          (h: string) => typeof h === 'string' && h.toLowerCase().replace(/[^a-z]/g, '') === field.key.toLowerCase()
        );
        if (match) autoMapping[field.key] = match;
      }
      setColumnMapping(autoMapping);
      setStep('mapping');
    } catch (err: any) {
      alert(err.message || 'Failed to preview file');
    } finally {
      setLoading(false);
    }
  };

  const handleImport = async () => {
    if (!file) return;
    setLoading(true);

    const formData = new FormData();
    formData.append('file', file);
    Object.entries(columnMapping).forEach(([key, value]) => {
      formData.append(`column_mapping[${key}]`, value);
    });

    try {
      const res = await fetch(importUrl, {
        method: 'POST',
        body: formData,
        headers: {
          'X-CSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      const text = await res.text();

      if (!res.ok) throw new Error(extractError(text, res.status));

      let data: any;
      try {
        data = JSON.parse(text);
      } catch {
        throw new Error('Server returned an invalid response');
      }

      setImportLogId(data.import_log_id);
      setStep('importing');
    } catch (err: any) {
      alert(err.message || 'Failed to start import');
    } finally {
      setLoading(false);
    }
  };

  const handleComplete = () => {
    onComplete?.();
    onClose();
    setStep('upload');
    setFile(null);
    setImportLogId(null);
  };

  return (
    <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50">
      <div className="bg-white dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <div className="p-6">
          <div className="flex justify-between items-center mb-6">
            <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">
              {step === 'upload' && 'Import Users'}
              {step === 'mapping' && 'Map Columns'}
              {step === 'importing' && 'Importing...'}
            </h2>
            <button
              onClick={onClose}
              className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
              disabled={step === 'importing'}
            >
              ✕
            </button>
          </div>

          {step === 'upload' && (
            <div>
              <div
                className="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center cursor-pointer hover:border-brand-500 transition-colors"
                onClick={() => fileRef.current?.click()}
              >
                <p className="text-sm text-gray-500 dark:text-gray-400">
                  Drop file here or click to browse
                </p>
                <p className="text-xs text-gray-400 mt-1">XLSX, CSV (max 10MB)</p>
              </div>
              <p className="text-xs text-gray-400 mt-2 text-center">
                Download template:{' '}
                <a
                  href="/examples/users-import-template.csv"
                  className="text-brand-500 hover:text-brand-600 hover:underline transition-colors"
                  download
                >
                  CSV
                </a>
                {' | '}
                <a
                  href="/examples/users-import-template.xlsx"
                  className="text-brand-500 hover:text-brand-600 hover:underline transition-colors"
                  download
                >
                  XLSX
                </a>
              </p>
              <input
                ref={fileRef}
                type="file"
                accept=".xlsx,.xls,.csv"
                onChange={handleFileChange}
                className="hidden"
              />
              {file && <p className="mt-3 text-sm text-gray-600 dark:text-gray-400">Selected: {file.name}</p>}
              <div className="flex justify-end gap-3 mt-6">
                <Button variant="outline" onClick={onClose}>Cancel</Button>
              </div>
            </div>
          )}

          {step === 'mapping' && (
            <div>
              <p className="text-sm text-gray-500 mb-4">{totalRows} rows detected.</p>
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-gray-200 dark:border-gray-700">
                    <th className="text-left py-2 text-xs font-medium text-gray-500 uppercase">System Field</th>
                    <th className="text-left py-2 text-xs font-medium text-gray-500 uppercase">File Column</th>
                  </tr>
                </thead>
                <tbody>
                  {SYSTEM_FIELDS.map((field) => (
                    <tr key={field.key} className="border-b border-gray-100 dark:border-gray-800">
                      <td className="py-2">
                        {field.label}
                        {field.required && <span className="text-red-500 ml-1">*</span>}
                      </td>
                      <td className="py-2">
                        <select
                          className="w-full px-3 py-1.5 border rounded-lg text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-800"
                          value={columnMapping[field.key] || ''}
                          onChange={(e) =>
                            setColumnMapping((prev) => ({ ...prev, [field.key]: e.target.value }))
                          }
                        >
                          <option value="">-- Select --</option>
                          {headers.map((h) => (
                            <option key={h} value={h}>{h}</option>
                          ))}
                        </select>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              <div className="flex justify-end gap-3 mt-6">
                <Button variant="outline" onClick={() => setStep('upload')} disabled={loading}>Back</Button>
                <Button
                  onClick={handleImport}
                  disabled={loading || SYSTEM_FIELDS.some((f) => f.required && !columnMapping[f.key])}
                >
                  {loading ? 'Starting...' : 'Start Import'}
                </Button>
              </div>
            </div>
          )}

          {step === 'importing' && importLogId && (
            <div>
              <ImportProgress importLogId={importLogId} onComplete={handleComplete} />
              <div className="flex justify-end mt-6">
                <Button variant="outline" onClick={handleComplete}>Close</Button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
