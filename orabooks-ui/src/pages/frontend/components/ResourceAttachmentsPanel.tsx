import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Download, Paperclip } from 'lucide-react';
import { api } from '../api';

type Props = {
  orgId: number;
  resourceType: string;
  resourceId: number;
  title?: string;
};

export default function ResourceAttachmentsPanel({
  orgId,
  resourceType,
  resourceId,
  title = 'Attachments',
}: Props) {
  const [items, setItems] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!orgId || !resourceId) {
      setItems([]);
      return;
    }

    let cancelled = false;
    setLoading(true);

    void api.attachmentsList(orgId, resourceType, resourceId).then((res) => {
      if (cancelled) return;
      if (!res.error) setItems((res as any).data?.attachments || []);
      setLoading(false);
    });

    return () => {
      cancelled = true;
    };
  }, [orgId, resourceType, resourceId]);

  const manageHref = `/attachments?resource_type=${resourceType}&resource_id=${resourceId}`;

  if (loading) {
    return <p className="text-sm text-slate-500">Loading attachments...</p>;
  }

  if (!items.length) {
    return (
      <div className="rounded-xl border border-dashed border-border bg-slate-50/50 p-4 text-sm text-slate-600">
        No files attached.{' '}
        <Link to={manageHref} className="font-semibold text-primary hover:underline">
          Upload in Attachments
        </Link>
      </div>
    );
  }

  return (
    <div className="rounded-xl border border-border bg-white p-4">
      <div className="mb-3 flex items-center justify-between gap-3">
        <h3 className="flex items-center gap-2 text-sm font-bold text-ink">
          <Paperclip className="h-4 w-4 text-primary" />
          {title} ({items.length})
        </h3>
        <Link to={manageHref} className="text-xs font-semibold text-primary hover:underline">
          Manage all
        </Link>
      </div>
      <ul className="divide-y divide-border">
        {items.map((item) => (
          <li key={item.id} className="flex items-center justify-between gap-3 py-2 text-sm">
            <div className="min-w-0">
              <p className="truncate font-medium text-ink">{item.file_name || `File #${item.id}`}</p>
              <p className="text-xs text-slate-500">
                v{item.version_number ?? 1}
                {item.virus_scan_status ? ` · ${item.virus_scan_status}` : ''}
              </p>
            </div>
            {item.virus_scan_status !== 'infected' && (
              <a
                href={api.attachmentDownloadUrl(orgId, item.id, item.version_id || 0)}
                className="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-primary hover:underline"
              >
                <Download className="h-3.5 w-3.5" />
                Download
              </a>
            )}
          </li>
        ))}
      </ul>
    </div>
  );
}
