import React from 'react';
import ImportButton from './ImportButton';
import ExportButton from './ExportButton';

interface ImportExportToolbarProps {
  importUrl: string;
  previewUrl: string;
  exportUrl: string;
  onImportClick: () => void;
}

export default function ImportExportToolbar({ onImportClick, exportUrl }: ImportExportToolbarProps) {
  return (
    <div className="flex items-center gap-3">
      <ImportButton onClick={onImportClick} />
      <ExportButton baseUrl={exportUrl} />
    </div>
  );
}
