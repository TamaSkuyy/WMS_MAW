import React from 'react';
import Button from '../../Tailadmin/components/ui/button/Button';

interface ImportButtonProps {
  onClick: () => void;
  disabled?: boolean;
}

export default function ImportButton({ onClick, disabled }: ImportButtonProps) {
  return (
    <Button variant="outline" onClick={onClick} disabled={disabled}>
      Import
    </Button>
  );
}
