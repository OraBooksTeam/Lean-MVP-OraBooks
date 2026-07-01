import { useEffect, useState } from 'react';
import WpLink from '../components/WpLink';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { Download, FileDown, RefreshCw, XCircle } from 'lucide-react';

const EXPORT_TYPES = [
  { id: 'coa', label: 'Chart of Accounts' },
  { id: 'audit_log', label: 'Audit Log' },
  { id: 'notification_log', label: 'Notification Log' },
];

export default function ExportStatusPage() {
  const [loading, setLoading] = useState(true);
  const [context, setContext] = useState<any>(null);
  const [exports, setExports] = useState<any[]>([]);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [actionId, setActionId] = useState<number | null>(null);
  const [requesting, setRequesting] = useState(false);
  const [exportType, setExportType] = useState('coa');
  const [exportFormat, setExportFormat] = useState<'csv' | 'pdf'>('csv');

  const orgId = context?.organization?.id;
  const canExport = context?.capabilities?.export_reports !== false;

  const load = async (targetPage = page) => {
    setLoading(true);
    setError('');

    const ctxRes = await api.frontendContext();
    if (!ctxRes.error) setContext((ctxRes as any).data);

    const res = await api.exportsList(targetPage);
    if (res.error) {
      setError(res.error || 'Unable to load exports.');
    } else {
      const payload = (res as any).data;
      setExports(payload?.exports || []);
      setTotal(payload?.total ?? 0);
      setTotalPages(payload?.total_pages ?? 1);
      setPage(payload?.page ?? targetPage);
    }
    setLoading(false);
  };

  useEffect(() => {
    void load(1);
  }, []);

  const stats = {
    total,
    pending: exports.filter((e) => e.status === 'pending' || e.status === 'generating').length,
    ready: exports.filter((e) => e.status === 'ready').length,
    failed: exports.filter((e) => e.status === 'failed').length,
  };

  const handleRequest = async () => {
    setRequesting(true);
    setError('');
    setSuccess('');

    const parameters = orgId ? { org_id: orgId } : undefined;
    const res = await api.exportRequest(exportType, exportFormat, parameters);
    if (res.error) {
      setError(res.error);
    } else {
      const exportId = (res as any).data?.id;
      setSuccess(
        exportId
          ? `Export #${exportId} queued. Refresh when ready to download.`
          : 'Export queued. Refresh when ready to download.',
      );
      await load(1);
    }
    setRequesting(false);
  };

  const handleDownload = async (ex: any) => {
    if (ex.file_url) {
      window.open(ex.file_url, '_blank', 'noopener,noreferrer');
      return;
    }

    setActionId(ex.id);
    setError('');
    const res = await api.exportDownload(ex.id);
    if (res.error) {
      setError(res.error);
    } else {
      const url = (res as any).data?.file_url;
      if (url) window.open(url, '_blank', 'noopener,noreferrer');
      await load(page);
    }
    setActionId(null);
  };

  const handleCancel = async (exportId: number) => {
    if (!window.confirm('Cancel this pending export?')) return;
    setActionId(exportId);
    setError('');
    setSuccess('');
    const res = await api.exportCancel(exportId);
    if (res.error) {
      setError(res.error);
    } else {
      setSuccess('Export cancelled.');
      await load(page);
    }
    setActionId(null);
  };

  return (
    <ClientShell
      title="My Exports"
      eyebrow="Generated files"
      organization={context?.organization}
      isPartner={context?.organization?.organization_type === 'partner' || context?.user?.is_partner}
    >
      <div className="space-y-5">
        <div className="rounded-2xl border border-primary/15 bg-primary/5 p-4 text-sm text-ink">
          Exports are generated asynchronously and expire after 7 days. Financial and operational report exports from{' '}
          <WpLink to="/reports" className="font-semibold text-primary hover:underline">
            Reports
          </WpLink>{' '}
          also appear here when ready.
        </div>

        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Metric label="Total" value={stats.total} />
          <Metric label="Pending" value={stats.pending} tone="warning" />
          <Metric label="Ready" value={stats.ready} tone="success" />
          <Metric label="Failed (page)" value={stats.failed} tone={stats.failed > 0 ? 'danger' : 'default'} />
        </div>

        {canExport && (
          <div className="glass-panel p-5">
            <div className="flex items-center gap-2 border-b border-border pb-4">
              <FileDown className="h-5 w-5 text-primary" />
              <h2 className="font-bold text-ink">Request Export</h2>
            </div>
            <p className="mt-3 text-sm text-slate-600">
              Queue a CSV or PDF export. Rate limit: 10 exports per hour.
            </p>
            <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <label className="block">
                <span className="mb-1.5 block text-xs font-semibold uppercase text-slate-500">Report</span>
                <select
                  className="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm"
                  value={exportType}
                  onChange={(e) => setExportType(e.target.value)}
                >
                  {EXPORT_TYPES.map((type) => (
                    <option key={type.id} value={type.id}>
                      {type.label}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block">
                <span className="mb-1.5 block text-xs font-semibold uppercase text-slate-500">Format</span>
                <select
                  className="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm"
                  value={exportFormat}
                  onChange={(e) => setExportFormat(e.target.value as 'csv' | 'pdf')}
                >
                  <option value="csv">CSV</option>
                  <option value="pdf">PDF</option>
                </select>
              </label>
              <div className="flex items-end sm:col-span-2">
                <Button onClick={() => void handleRequest()} disabled={requesting} className="w-full sm:w-auto">
                  <FileDown className="h-4 w-4" />
                  {requesting ? 'Requesting...' : 'Request Export'}
                </Button>
              </div>
            </div>
          </div>
        )}

        <div className="flex justify-end">
          <Button onClick={() => void load(page)} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {error && (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>
        )}
        {success && (
          <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
            {success}
          </div>
        )}

        <div className="glass-panel overflow-hidden">
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/60 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Type</th>
                <th className="px-5 py-3 font-semibold">Format</th>
                <th className="px-5 py-3 font-semibold">Status</th>
                <th className="px-5 py-3 font-semibold">Size</th>
                <th className="px-5 py-3 font-semibold">Expires</th>
                <th className="px-5 py-3 font-semibold">Downloads</th>
                <th className="px-5 py-3 font-semibold">Created</th>
                <th className="px-5 py-3 font-semibold">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr>
                  <td colSpan={8} className="px-5 py-8 text-center text-slate-500">
                    Loading exports...
                  </td>
                </tr>
              ) : exports.length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-5 py-8 text-center text-slate-500">
                    No exports found. Request one above or export from Reports.
                  </td>
                </tr>
              ) : (
                exports.map((ex) => (
                  <tr key={ex.id} className="transition hover:bg-slate-50/60">
                    <td className="px-5 py-3 font-medium text-ink">{formatExportType(ex.export_type)}</td>
                    <td className="px-5 py-3 uppercase text-slate-600">{ex.format || '—'}</td>
                    <td className="px-5 py-3">
                      <StatusBadge status={ex.status} />
                      {ex.status === 'failed' && ex.error_message && (
                        <p className="mt-1 max-w-xs text-xs text-red-600" title={ex.error_message}>
                          {ex.error_message}
                        </p>
                      )}
                    </td>
                    <td className="px-5 py-3 text-slate-600">{ex.file_size || '—'}</td>
                    <td className="px-5 py-3 text-slate-600">
                      {ex.time_remaining || formatDate(ex.expires_at)}
                    </td>
                    <td className="px-5 py-3 text-slate-600">{ex.download_count ?? 0}</td>
                    <td className="px-5 py-3 text-slate-600">{formatDate(ex.created_at)}</td>
                    <td className="px-5 py-3">
                      <div className="flex flex-wrap gap-2">
                        {ex.can_download && (
                          <Button
                            size="sm"
                            disabled={actionId === ex.id}
                            onClick={() => void handleDownload(ex)}
                          >
                            <Download className="h-3.5 w-3.5" />
                            Download
                          </Button>
                        )}
                        {ex.can_cancel && (
                          <Button
                            variant="secondary"
                            size="sm"
                            disabled={actionId === ex.id}
                            onClick={() => void handleCancel(ex.id)}
                          >
                            <XCircle className="h-3.5 w-3.5" />
                            Cancel
                          </Button>
                        )}
                        {!ex.can_download && !ex.can_cancel && (
                          <span className="text-xs text-slate-400">
                            {ex.status === 'generating' ? 'Generating...' : 'Not available'}
                          </span>
                        )}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {totalPages > 1 && (
          <div className="flex items-center justify-between">
            <p className="text-sm text-slate-600">
              Page {page} of {totalPages} ({total} total)
            </p>
            <div className="flex gap-2">
              <Button
                variant="secondary"
                size="sm"
                disabled={page <= 1 || loading}
                onClick={() => void load(page - 1)}
              >
                Previous
              </Button>
              <Button
                variant="secondary"
                size="sm"
                disabled={page >= totalPages || loading}
                onClick={() => void load(page + 1)}
              >
                Next
              </Button>
            </div>
          </div>
        )}
      </div>
    </ClientShell>
  );
}

function Metric({
  label,
  value,
  tone = 'default',
}: {
  label: string;
  value: string | number;
  tone?: 'default' | 'warning' | 'success' | 'danger';
}) {
  const toneClass =
    tone === 'warning'
      ? 'border-amber-200 bg-amber-50'
      : tone === 'success'
        ? 'border-emerald-200 bg-emerald-50'
        : tone === 'danger'
          ? 'border-red-200 bg-red-50'
          : 'border-border bg-white';

  return (
    <div className={`rounded-2xl border p-4 shadow-sm ${toneClass}`}>
      <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</p>
      <p className="mt-2 text-2xl font-black text-ink">{value}</p>
    </div>
  );
}

function StatusBadge({ status }: { status?: string }) {
  const map: Record<string, string> = {
    pending: 'border-amber-200 bg-amber-50 text-amber-800',
    generating: 'border-primary/20 bg-primary/10 text-primary',
    ready: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    failed: 'border-red-200 bg-red-50 text-red-800',
    expired: 'border-slate-200 bg-slate-50 text-slate-600',
    cancelled: 'border-slate-200 bg-slate-50 text-slate-600',
  };
  const cls = map[status || ''] || 'border-slate-200 bg-slate-50 text-slate-600';
  return <span className={`badge border capitalize ${cls}`}>{status || 'unknown'}</span>;
}

function formatExportType(type?: string) {
  if (!type) return '—';
  const labels: Record<string, string> = {
    coa: 'Chart of Accounts',
    audit_log: 'Audit Log',
    notification_log: 'Notification Log',
    pnl: 'Profit & Loss',
    balance_sheet: 'Balance Sheet',
    ar_aging: 'AR Aging',
    ap_aging: 'AP Aging',
    financial_report: 'Financial Report',
    operational_report: 'Operational Report',
  };
  return labels[type] || type.replace(/_/g, ' ');
}

function formatDate(value?: string) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString();
}
