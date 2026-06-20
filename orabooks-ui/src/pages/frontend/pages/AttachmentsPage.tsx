import { useEffect, useRef, useState } from 'react';
import WpLink from '../components/WpLink';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { Download, History, Paperclip, RefreshCw, Trash2, Upload, X } from 'lucide-react';
import { getSearchParam, replaceSearchParams } from '../lib/wp-routing';

const fieldClass =
  'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';

export default function AttachmentsPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [listLoading, setListLoading] = useState(false);
  const [listAttachments, setListAttachments] = useState<any[]>([]);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [resourceType, setResourceType] = useState('general');
  const [resourceId, setResourceId] = useState('1');
  const [filterType, setFilterType] = useState('');
  const [filterResourceId, setFilterResourceId] = useState('');
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [newVersionAttachmentId, setNewVersionAttachmentId] = useState('');
  const [uploading, setUploading] = useState(false);
  const [historyAttachmentId, setHistoryAttachmentId] = useState<number | null>(null);
  const [history, setHistory] = useState<any>(null);
  const [historyLoading, setHistoryLoading] = useState(false);
  const [actionId, setActionId] = useState<number | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const initializedFromUrl = useRef(false);

  const orgId = data?.context?.organization?.id;
  const caps = data?.capabilities || {};
  const hasServerFilter = filterType !== '' || filterResourceId !== '';

  const loadDashboard = async () => {
    setLoading(true);
    setError('');
    const res = await api.attachmentsDashboard();
    if (res.error) setError(res.error || 'Unable to load attachments.');
    else {
      const payload = (res as any).data;
      setData(payload);
      if (payload?.resource_types?.length && !payload.resource_types.some((t: any) => t.id === resourceType)) {
        setResourceType(payload.resource_types[0].id);
      }
    }
    setLoading(false);
  };

  const loadFilteredList = async (targetOrgId = orgId) => {
    if (!targetOrgId || !hasServerFilter) {
      setListAttachments([]);
      return;
    }

    setListLoading(true);
    const res = await api.attachmentsList(
      targetOrgId,
      filterType,
      Number(filterResourceId) || 0,
    );
    if (res.error) setError(res.error);
    else setListAttachments((res as any).data?.attachments || []);
    setListLoading(false);
  };

  const load = async () => {
    await loadDashboard();
    await loadFilteredList();
  };

  useEffect(() => {
    if (initializedFromUrl.current) return;
    initializedFromUrl.current = true;

    const urlType = getSearchParam('resource_type');
    const urlResourceId = getSearchParam('resource_id');
    if (urlType) {
      setFilterType(urlType);
      setResourceType(urlType);
    }
    if (urlResourceId) {
      setFilterResourceId(urlResourceId);
      setResourceId(urlResourceId);
    }
  }, [searchParams]);

  useEffect(() => {
    void loadDashboard();
  }, []);

  useEffect(() => {
    void loadFilteredList();
  }, [orgId, filterType, filterResourceId]);

  useEffect(() => {
    const attachmentId = Number(searchParams.get('attachment_id') || 0);
    if (orgId && attachmentId > 0) {
      void loadHistory(attachmentId);
    }
  }, [orgId, searchParams]);

  const handleUpload = async () => {
    if (!orgId || !selectedFile) {
      setError('Select a file to upload.');
      return;
    }

    const parsedResourceId = Number(resourceId);
    if (!parsedResourceId || parsedResourceId <= 0) {
      setError('Enter a valid resource ID.');
      return;
    }

    setUploading(true);
    setError('');
    setSuccess('');

    const idempotencyKey =
      typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `attach-${Date.now()}-${Math.random().toString(36).slice(2)}`;

    const res = await api.uploadAttachment(
      orgId,
      resourceType,
      parsedResourceId,
      selectedFile,
      Number(newVersionAttachmentId) || 0,
      idempotencyKey,
    );

    if (res.error) {
      setError(res.error);
    } else {
      setSuccess(Number(newVersionAttachmentId) ? 'New version uploaded.' : 'Attachment uploaded.');
      setSelectedFile(null);
      setNewVersionAttachmentId('');
      if (fileInputRef.current) fileInputRef.current.value = '';
      await loadDashboard();
      await loadFilteredList();
    }
    setUploading(false);
  };

  const handleDelete = async (attachmentId: number) => {
    if (!orgId || !window.confirm('Soft delete this attachment?')) return;
    setActionId(attachmentId);
    setError('');
    setSuccess('');
    const res = await api.attachmentDelete(orgId, attachmentId);
    if (res.error) setError(res.error);
    else {
      setSuccess('Attachment deleted.');
      if (historyAttachmentId === attachmentId) {
        setHistoryAttachmentId(null);
        setHistory(null);
      }
      await loadDashboard();
      await loadFilteredList();
    }
    setActionId(null);
  };

  const loadHistory = async (attachmentId: number) => {
    if (!orgId) return;
    setHistoryAttachmentId(attachmentId);
    setHistoryLoading(true);
    setHistory(null);
    const res = await api.attachmentGet(orgId, attachmentId);
    if (res.error) setError(res.error);
    else setHistory((res as any).data);
    setHistoryLoading(false);
  };

  const applyFilter = () => {
    const params = new URLSearchParams();
    if (filterType) params.set('resource_type', filterType);
    if (filterResourceId) params.set('resource_id', filterResourceId);
    replaceSearchParams(params);
  };

  const clearFilter = () => {
    setFilterType('');
    setFilterResourceId('');
    replaceSearchParams(new URLSearchParams());
  };

  const attachments = hasServerFilter
    ? listAttachments
    : (data?.attachments || []).filter((item: any) => (filterType ? item.resource_type === filterType : true));

  const tableLoading = loading || listLoading;
  const maxMb = Math.round((data?.limits?.max_file_size || 26214400) / 1048576);

  return (
    <ClientShell title="Attachments" eyebrow="SL-203 files & versioning" organization={data?.context?.organization}>
      <div className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Metric label="Active Files" value={data?.stats?.active_count ?? 0} />
          <Metric label="Storage Used" value={formatBytes(data?.stats?.total_bytes ?? 0)} />
          <Metric label="Soft Deleted" value={data?.stats?.deleted_count ?? 0} />
          <Metric label="Max File Size" value={`${maxMb} MB`} />
        </div>

        {hasServerFilter && (
          <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm text-ink">
            <p>
              Showing attachments for{' '}
              <strong>
                {formatResourceType(filterType, data?.resource_types) || 'all types'}
                {filterResourceId ? ` #${filterResourceId}` : ''}
              </strong>
            </p>
            <Button variant="secondary" size="sm" onClick={clearFilter}>
              <X className="h-3.5 w-3.5" />
              Clear filter
            </Button>
          </div>
        )}

        {caps.upload && (
          <div className="glass-panel p-5">
            <div className="flex items-center gap-2 border-b border-border pb-4">
              <Upload className="h-5 w-5 text-primary" />
              <h2 className="font-bold text-ink">Upload Attachment</h2>
            </div>
            <p className="mt-3 text-sm text-slate-600">
              Supported: PDF, images, CSV, audio, and common documents. Max {maxMb} MB per file.
            </p>
            <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
              <label className="block">
                <span className="mb-1.5 block text-xs font-semibold uppercase text-slate-500">Resource type</span>
                <select className={fieldClass} value={resourceType} onChange={(e) => setResourceType(e.target.value)}>
                  {(data?.resource_types || []).map((type: any) => (
                    <option key={type.id} value={type.id}>
                      {type.label}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block">
                <span className="mb-1.5 block text-xs font-semibold uppercase text-slate-500">Resource ID</span>
                <input
                  className={fieldClass}
                  value={resourceId}
                  onChange={(e) => setResourceId(e.target.value)}
                  placeholder="e.g. 42"
                />
              </label>
              <label className="block sm:col-span-2">
                <span className="mb-1.5 block text-xs font-semibold uppercase text-slate-500">File</span>
                <input
                  ref={fileInputRef}
                  type="file"
                  className={fieldClass}
                  onChange={(e) => setSelectedFile(e.target.files?.[0] || null)}
                />
              </label>
              <label className="block">
                <span className="mb-1.5 block text-xs font-semibold uppercase text-slate-500">New version of</span>
                <input
                  className={fieldClass}
                  value={newVersionAttachmentId}
                  onChange={(e) => setNewVersionAttachmentId(e.target.value)}
                  placeholder="Attachment ID"
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
        )}

        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div className="flex flex-wrap items-end gap-3">
            <label className="block text-sm">
              <span className="mb-1.5 block text-xs font-semibold uppercase text-slate-500">Type</span>
              <select
                className="rounded-lg border border-border bg-white px-3 py-2 text-sm"
                value={filterType}
                onChange={(e) => setFilterType(e.target.value)}
              >
                <option value="">All types</option>
                {(data?.resource_types || []).map((type: any) => (
                  <option key={type.id} value={type.id}>
                    {type.label}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-sm">
              <span className="mb-1.5 block text-xs font-semibold uppercase text-slate-500">Resource ID</span>
              <input
                className="w-32 rounded-lg border border-border bg-white px-3 py-2 text-sm"
                value={filterResourceId}
                onChange={(e) => setFilterResourceId(e.target.value)}
                placeholder="Any"
              />
            </label>
            <Button variant="secondary" size="sm" onClick={applyFilter}>
              Apply
            </Button>
          </div>
          <Button onClick={() => void load()} variant="secondary" size="sm">
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
            <h2 className="font-bold text-ink">Attachment Library</h2>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">File</th>
                <th className="px-5 py-3 font-semibold">Resource</th>
                <th className="px-5 py-3 font-semibold">Version</th>
                <th className="px-5 py-3 font-semibold">Scan</th>
                <th className="px-5 py-3 text-right font-semibold">Size</th>
                <th className="px-5 py-3 font-semibold">Uploaded</th>
                <th className="px-5 py-3 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {tableLoading ? (
                <tr>
                  <td colSpan={7} className="px-5 py-8 text-center text-slate-500">
                    Loading attachments...
                  </td>
                </tr>
              ) : attachments.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-5 py-10 text-center">
                    <Paperclip className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">
                      {hasServerFilter ? 'No attachments match this resource filter.' : 'No attachments found.'}
                    </p>
                  </td>
                </tr>
              ) : (
                attachments.map((item: any) => (
                  <tr
                    key={item.id}
                    className={`hover:bg-slate-50/70 ${historyAttachmentId === item.id ? 'bg-primary/10 ring-2 ring-inset ring-primary/20' : ''}`}
                  >
                    <td className="px-5 py-3">
                      <p className="font-semibold text-ink">{item.file_name || `Attachment #${item.id}`}</p>
                      <p className="text-xs text-slate-500">{item.mime_type || 'unknown type'}</p>
                    </td>
                    <td className="px-5 py-3 text-slate-600">
                      <WpLink
                        to={`/attachments?resource_type=${item.resource_type}&resource_id=${item.resource_id}`}
                        className="hover:text-primary hover:underline"
                      >
                        {formatResourceType(item.resource_type, data?.resource_types)} #{item.resource_id}
                      </WpLink>
                    </td>
                    <td className="px-5 py-3 font-mono text-xs">v{item.version_number ?? 1}</td>
                    <td className="px-5 py-3">
                      <ScanBadge status={item.virus_scan_status || 'pending'} />
                    </td>
                    <td className="px-5 py-3 text-right text-slate-600">{formatBytes(item.file_size ?? 0)}</td>
                    <td className="px-5 py-3 text-slate-600">{formatDate(item.uploaded_at || item.updated_at)}</td>
                    <td className="px-5 py-3">
                      <div className="flex flex-wrap gap-2">
                        {caps.download && item.virus_scan_status !== 'infected' && (
                          <a
                            href={api.attachmentDownloadUrl(orgId, item.id, item.version_id || 0)}
                            className="inline-flex items-center gap-1 rounded-lg border border-border bg-white px-2.5 py-1.5 text-xs font-semibold text-primary hover:bg-primary/5"
                          >
                            <Download className="h-3.5 w-3.5" />
                            Download
                          </a>
                        )}
                        <Button variant="secondary" size="sm" onClick={() => void loadHistory(item.id)}>
                          <History className="h-3.5 w-3.5" />
                          History
                        </Button>
                        {caps.delete && (
                          <Button
                            variant="secondary"
                            size="sm"
                            disabled={actionId === item.id}
                            onClick={() => void handleDelete(item.id)}
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                            Delete
                          </Button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {historyAttachmentId && (
          <div className="glass-panel overflow-hidden">
            <div className="border-b border-border px-5 py-4">
              <h2 className="font-bold text-ink">Version History #{historyAttachmentId}</h2>
            </div>
            {historyLoading ? (
              <p className="px-5 py-8 text-center text-sm text-slate-500">Loading version history...</p>
            ) : !history?.versions?.length ? (
              <p className="px-5 py-8 text-center text-sm text-slate-500">No versions found.</p>
            ) : (
              <table className="min-w-full text-left text-sm">
                <thead>
                  <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                    <th className="px-5 py-3 font-semibold">Version</th>
                    <th className="px-5 py-3 font-semibold">File</th>
                    <th className="px-5 py-3 text-right font-semibold">Size</th>
                    <th className="px-5 py-3 font-semibold">Scan</th>
                    <th className="px-5 py-3 font-semibold">Uploaded</th>
                    <th className="px-5 py-3 font-semibold">Download</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {history.versions.map((version: any) => (
                    <tr key={version.id} className="hover:bg-slate-50/70">
                      <td className="px-5 py-3 font-mono text-xs">v{version.version_number}</td>
                      <td className="px-5 py-3 font-semibold text-ink">{version.file_name}</td>
                      <td className="px-5 py-3 text-right text-slate-600">{formatBytes(version.file_size)}</td>
                      <td className="px-5 py-3">
                        <ScanBadge status={version.virus_scan_status} />
                      </td>
                      <td className="px-5 py-3 text-slate-600">{formatDate(version.uploaded_at)}</td>
                      <td className="px-5 py-3">
                        {caps.download && version.virus_scan_status !== 'infected' ? (
                          <a
                            href={api.attachmentDownloadUrl(orgId, historyAttachmentId, version.id)}
                            className="inline-flex items-center gap-1 text-primary hover:text-primary-dark"
                          >
                            <Download className="h-3.5 w-3.5" />
                            Download
                          </a>
                        ) : (
                          <span className="text-slate-400">—</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        )}
      </div>
    </ClientShell>
  );
}

function Metric({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="rounded-2xl border border-border bg-white p-4 shadow-sm">
      <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</p>
      <p className="mt-2 text-2xl font-black text-ink">{value}</p>
    </div>
  );
}

function ScanBadge({ status }: { status: string }) {
  const styles: Record<string, string> = {
    pending: 'border-amber-200 bg-amber-50 text-amber-800',
    clean: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    infected: 'border-red-200 bg-red-50 text-red-800',
    error: 'border-slate-200 bg-slate-50 text-slate-700',
  };

  return <span className={`badge border ${styles[status] || styles.pending}`}>{status}</span>;
}

function formatResourceType(id: string, types: Array<{ id: string; label: string }> = []) {
  return types.find((t) => t.id === id)?.label || id.replace(/_/g, ' ');
}

function formatBytes(bytes: number) {
  if (!bytes) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  let value = bytes;
  let unit = 0;
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024;
    unit += 1;
  }
  return `${value.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
}

function formatDate(value: string) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString();
}
