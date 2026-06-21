import { useState } from 'react';
import Button from '@/components/Button';
import { api } from '@/pages/frontend/api';

type Props = {
  exportType: string;
  format: 'csv' | 'pdf';
  label: string;
  className?: string;
};

export default function ExportTriggerButton({ exportType, format, label, className = '' }: Props) {
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');

  const onClick = async () => {
    setLoading(true);
    setMessage('');
    const res = await api.exportRequest(exportType, format);
    if (res.error) setMessage(res.error);
    else setMessage('Export queued — check My Exports when ready.');
    setLoading(false);
  };

  return (
    <span className={`inline-flex flex-col gap-1 ${className}`}>
      <Button type="button" size="sm" variant="secondary" loading={loading} onClick={onClick}>
        {label}
      </Button>
      {message && <span className="text-xs text-slate-600">{message}</span>}
    </span>
  );
}
