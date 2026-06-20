import { useEffect, useRef, useState } from 'react';
import WpLink from '../components/WpLink';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { AlertTriangle, FileSpreadsheet, RefreshCw, Upload } from 'lucide-react';
import { getSearchParam } from '../lib/wp-routing';

const fieldClass =
  'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';

export default function CsvImportsPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [resourceType, setResourceType] = useState('inventory_item');
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [uploading, setUploading] = useState(false);
  const [selectedImportId, setSelectedImportId] = useState<number | null>(null);
  const [preview, setPreview] = useState<any>(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [confirming, setConfirming] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const orgId = data?.context?.organization?.id;

  const load = async () => {
    setLoading(true);
    setError('');
    setSuccess('');
    const res = await api.csvImportsDashboard();
    if (res.error) setError(res.error || 'Unable to load CSV imports.');
    else {
      const payload = (res as any).data;
      setData(payload);
      if (payload?.resource_types?.length && !payload.resource_types.some((t: any) => t.id === resourceType)) {
        setResourceType(payload.resource_types[0].id);
      }
    }
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  useEffect(() => {
    const importId = Number(getSearchParam('import_id') || 0);
    if (importId > 0 && orgId) {
      void loadPreview(importId);
    }
  }, [orgId]);

  const loadPreview = async (importId: number) => {
    if (!orgId) return;
    setSelectedImportId(importId);
    setPreviewLoading(true);
    setPreview(null);
    const res = await api.csvImportGet(orgId, importId);
    if (res.error) setError(res.error);
    else setPreview((res as any).data);
    setPreviewLoading(false);
  };

  const handleUpload = async () => {
    if (!orgId || !selectedFile) {
      setError('Select a CSV file to upload.');
      return;
    }

    setUploading(true);
    setError('');
    const idempotencyKey =
      typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `csv-${Date.now()}-${Math.random().toString(36).slice(2)}`;

    const res = await api.uploadCsv(orgId, resourceType, selectedFile, idempotencyKey);
    if (res.error) {
      setError(res.error);
    } else {
      setSelectedFile(null);
      if (fileInputRef.current) fileInputRef.current.value = '';
      await load();
      const importId = (res as any).data?.id;
      if (importId) void loadPreview(importId);
    }
    setUploading(false);
  };

  const handleConfirm = async () => {
    if (!orgId || !selectedImportId || preview?.import?.status !== 'pending_confirm') return;

    setConfirming(true);
    setError('');
    const idempotencyKey =
      typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `confirm-${Date.now()}-${Math.random().toString(36).slice(2)}`;

    const res = await api.csvImportConfirm(orgId, selectedImportId, idempotencyKey);
    if (res.error) {
      setError(res.error);
    } else {
      const result = (res as any).data;
      const processed = result?.processed_rows ?? result?.row_counts?.processed;
      setSuccess(
        processed != null
          ? `Import confirmed. ${processed} row(s) processed.`
          : 'Import confirmed successfully.',
      );
      await load();
      await loadPreview(selectedImportId);
    }
    setConfirming(false);
  };

  const imports = data?.recent_imports || [];
  const stats = data?.stats || {};
  const maxMb = Math.round((data?.limits?.max_file_size || 10485760) / 1048576);

  return (
    <ClientShell title="CSV Imports" eyebrow="Bulk data ingestion" organization={data?.context?.organization}>
      <div className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Metric label="Total Imports" value={stats.total ?? 0} />
          <Metric label="Awaiting Confirm" value={stats.pending_confirm ?? 0} tone={stats.pending_confirm > 0 ? 'warning' : 'default'} />
          <Metric label="Confirmed" value={stats.confirmed ?? 0} tone="success" />
          <Metric label="Failed" value={stats.failed ?? 0} tone={stats.failed > 0 ? 'danger' : 'default'} />
        </div>

        <div className="glass-panel p-5">
          <div className="flex items-center gap-2 border-b border-border pb-4">
            <Upload className="h-5 w-5 text-primary" />
            <h2 className="font-bold text-ink">Upload CSV</h2>
          </div>
          <p className="mt-3 text-sm text-slate-600">
            Max file size {maxMb} MB, up to {(data?.limits?.max_rows ?? 10000).toLocaleString()} rows per import.
          </p>
          <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <label className="block">
              <span className="mb-1.5 block text-xs font-semibold uppercase text-slate-500">Resource type</span>
              <select
                className={fieldClass}
                value={resourceType}
                onChange={(e) => setResourceType(e.target.value)}
              >
                {(data?.resource_types || []).map((type: any) => (
                  <option key={type.id} value={type.id}>
                    {type.label}
                  </option>
                ))}
              </select>
            </label>
            <label className="block sm:col-span-2">
              <span className="mb-1.5 block text-xs font-semibold uppercase text-slate-500">CSV file</span>
              <input
                ref={fileInputRef}
                type="file"
                accept=".csv,text/csv"
                className={fieldClass}
                onChange={(e) => setSelectedFile(e.target.files?.[0] || null)}
              />
            </label>
            <div className="flex items-end">
              <Button onClick={handleUpload} disabled={uploading || !selectedFile} className="w-full">
                <Upload className="h-4 w-4" />
                {uploading ? 'Uploading...' : 'Upload'}
              </Button>
            </div>
          </div>
        </div>

        <div className="flex justify-end">
          <Button onClick={load} variant="secondary" size="sm">
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
          <div className="border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Recent Imports</h2>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">File</th>
                <th className="px-5 py-3 font-semibold">Type</th>
                <th className="px-5 py-3 font-semibold">Status</th>
                <th className="px-5 py-3 text-right font-semibold">Rows</th>
                <th className="px-5 py-3 font-semibold">Created</th>
                <th className="px-5 py-3 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr>
                  <td colSpan={6} className="px-5 py-8 text-center text-slate-500">
                    Loading imports...
                  </td>
                </tr>
              ) : imports.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-5 py-10 text-center">
                    <FileSpreadsheet className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No CSV imports yet. Upload a file to get started.</p>
                  </td>
                </tr>
              ) : (
                imports.map((item: any) => (
                  <tr key={item.id} className="hover:bg-slate-50/70">
                    <td className="px-5 py-3 font-semibold text-ink">{item.original_filename}</td>
                    <td className="px-5 py-3 text-slate-600">{formatResourceType(item.resource_type, data?.resource_types)}</td>
                    <td className="px-5 py-3">
                      <StatusBadge status={item.status} />
                    </td>
                    <td className="px-5 py-3 text-right font-mono text-xs">
                      {item.processed_rows}/{item.total_rows}
                    </td>
                    <td className="px-5 py-3 text-slate-600">{formatDate(item.created_at)}</td>
                    <td className="px-5 py-3">
                      <Button variant="secondary" size="sm" onClick={() => void loadPreview(item.id)}>
                        Preview
                      </Button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {selectedImportId && (
          <div className="glass-panel overflow-hidden">
            <div className="flex flex-col gap-3 border-b border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 className="font-bold text-ink">Import Preview #{selectedImportId}</h2>
                {preview?.import && (
                  <p className="mt-1 text-sm text-slate-600">
                    {preview.import.original_filename} · <StatusBadge status={preview.import.status} />
                  </p>
                )}
                {preview?.row_counts && (
                  <div className="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-600">
                    <span>Processed: {preview.row_counts.processed ?? 0}</span>
                    <span>Escalated: {preview.row_counts.escalated ?? 0}</span>
                    <span>Failed: {preview.row_counts.failed ?? 0}</span>
                    {(preview.row_counts.escalated ?? 0) > 0 && (
                      <WpLink to="/ai-review" className="inline-flex items-center gap-1 font-semibold text-amber-700 hover:underline">
                        <AlertTriangle className="h-3.5 w-3.5" />
                        Review in AI Queue
                      </Link>
                    )}
                  </div>
                )}
              </div>
              {preview?.import?.status === 'pending_confirm' && (
                <Button onClick={handleConfirm} disabled={confirming}>
                  {confirming ? 'Confirming...' : 'Confirm Import'}
                </Button>
              )}
            </div>

            {previewLoading ? (
              <p className="px-5 py-8 text-center text-sm text-slate-500">Loading preview...</p>
            ) : !preview?.rows?.length ? (
              <p className="px-5 py-8 text-center text-sm text-slate-500">
                {preview?.import?.status === 'uploaded' || preview?.import?.status === 'parsing'
                  ? 'Import is still being parsed. Refresh in a moment.'
                  : 'No preview rows available.'}
              </p>
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full text-left text-sm">
                  <thead>
                    <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                      <th className="px-5 py-3 font-semibold">Row</th>
                      <th className="px-5 py-3 font-semibold">Confidence</th>
                      <th className="px-5 py-3 font-semibold">Status</th>
                      <th className="px-5 py-3 font-semibold">Parsed Data</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {preview.rows.map((row: any) => (
                      <tr
                        key={row.id}
                        className={`hover:bg-slate-50/70 ${row.status === 'escalated' ? 'bg-amber-50/80' : ''}`}
                      >
                        <td className="px-5 py-3 font-mono text-xs">{row.row_index + 1}</td>
                        <td className="px-5 py-3">
                          <ConfidenceBadge value={row.confidence_avg} threshold={data?.limits?.confidence_threshold ?? 70} />
                        </td>
                        <td className="px-5 py-3">
                          <span
                            className={`badge border capitalize ${
                              row.status === 'escalated'
                                ? 'border-amber-200 bg-amber-50 text-amber-800'
                                : row.status === 'failed'
                                  ? 'border-red-200 bg-red-50 text-red-800'
                                  : 'border-slate-200 bg-slate-50 text-slate-700'
                            }`}
                          >
                            {row.status}
                          </span>
                        </td>
                        <td className="px-5 py-3 font-mono text-xs text-slate-600">
                          {JSON.stringify(row.parsed_data || {})}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
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

function StatusBadge({ status }: { status: string }) {
  const styles: Record<string, string> = {
    uploaded: 'border-slate-200 bg-slate-50 text-slate-700',
    parsing: 'border-blue-200 bg-blue-50 text-blue-800',
    mapping: 'border-indigo-200 bg-indigo-50 text-indigo-800',
    pending_confirm: 'border-amber-200 bg-amber-50 text-amber-800',
    confirmed: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    failed: 'border-red-200 bg-red-50 text-red-800',
  };

  return (
    <span className={`badge border ${styles[status] || styles.uploaded}`}>
      {status.replace(/_/g, ' ')}
    </span>
  );
}

function ConfidenceBadge({ value, threshold }: { value: number | null; threshold: number }) {
  if (value == null) return <span className="text-slate-400">—</span>;
  const low = value < threshold;
  return (
    <span className={`font-semibold ${low ? 'text-amber-700' : 'text-emerald-700'}`}>
      {value.toFixed(1)}%
    </span>
  );
}

function formatResourceType(id: string, types: Array<{ id: string; label: string }> = []) {
  return types.find((t) => t.id === id)?.label || id.replace(/_/g, ' ');
}

function formatDate(value: string) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString();
}
