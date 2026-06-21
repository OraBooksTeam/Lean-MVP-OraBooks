import { useEffect, useState } from 'react';
import AdminPageShell from '@/components/AdminPageShell';
import { api } from '../api';
import { Calendar, Download, Filter, User } from 'lucide-react';

interface AuditRow {
  org_id?: number;
  created_at: string;
  user_id: number;
  event_type: string;
  severity: string;
  description: string;
  ip_address: string;
  correlation_id: string;
}

interface AuditFilters {
  user_id: string;
  event_type: string;
  severity: string;
  from_date: string;
  to_date: string;
  org_id: string;
  correlation_id: string;
}

const emptyFilters: AuditFilters = {
  user_id: '',
  event_type: '',
  severity: '',
  from_date: '',
  to_date: '',
  org_id: '',
  correlation_id: '',
};

export default function AdminAudit() {
  const isAdmin = Boolean((window as any).orabooks_ajax?.is_admin);
  const [filters, setFilters] = useState<AuditFilters>(emptyFilters);
  const [applied, setApplied] = useState<AuditFilters>(emptyFilters);
  const [rows, setRows] = useState<AuditRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const buildParams = (f: AuditFilters) => {
    const params: Record<string, string | number> = { limit: 100 };
    if (f.user_id) params.user_id = Number(f.user_id);
    if (f.event_type) params.event_type = f.event_type;
    if (f.severity) params.severity = f.severity;
    if (f.from_date) params.from_date = f.from_date;
    if (f.to_date) params.to_date = `${f.to_date} 23:59:59`;
    if (f.correlation_id) params.correlation_id = f.correlation_id;
    if (isAdmin && f.org_id) params.org_id = Number(f.org_id);
    return params;
  };

  const load = (f = applied) => {
    setLoading(true);
    setError('');
    api.auditLogs(buildParams(f)).then((res: any) => {
      if (res.error) setError(res.error);
      else setRows((res as any).data || []);
      setLoading(false);
    });
  };

  useEffect(() => {
    load();
  }, []);

  const applyFilters = () => {
    setApplied(filters);
    load(filters);
  };

  const exportCsv = () => {
    api.exportAuditLogs(buildParams(applied));
  };

  return (
    <AdminPageShell
      title="Audit Log"
      description="Security and compliance events across the platform."
      actions={
        <div className="flex gap-2">
          <button
            onClick={exportCsv}
            title="Export audit log as CSV. Includes all events for compliance."
            className="flex items-center gap-1.5 rounded-lg border border-border bg-white px-3 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50"
          >
            <Download className="h-4 w-4" />
            Export CSV
          </button>
          <button
            onClick={() => load()}
            className="flex items-center gap-1.5 rounded-lg border border-border bg-white px-3 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50"
          >
            <Filter className="h-4 w-4" />
            Refresh
          </button>
        </div>
      }
    >
      <div className="flex flex-wrap items-center gap-2">
        {isAdmin && (
          <div className="flex items-center gap-1.5 rounded-lg border border-border bg-white px-3 py-2 text-sm">
            <span className="text-slate-500">Org</span>
            <input
              className="w-20 bg-transparent text-sm outline-none"
              placeholder="ID"
              value={filters.org_id}
              onChange={(e) => setFilters((prev) => ({ ...prev, org_id: e.target.value }))}
            />
          </div>
        )}
        <div className="flex items-center gap-1.5 rounded-lg border border-border bg-white px-3 py-2 text-sm">
          <User className="h-4 w-4 text-slate-500" />
          <input
            className="w-24 bg-transparent text-sm outline-none"
            placeholder="User ID"
            value={filters.user_id}
            onChange={(e) => setFilters((prev) => ({ ...prev, user_id: e.target.value }))}
          />
        </div>
        <input
          className="rounded-lg border border-border bg-white px-3 py-2 text-sm outline-none"
          placeholder="Event type"
          value={filters.event_type}
          onChange={(e) => setFilters((prev) => ({ ...prev, event_type: e.target.value }))}
        />
        <select
          className="rounded-lg border border-border bg-white px-3 py-2 text-sm outline-none"
          value={filters.severity}
          onChange={(e) => setFilters((prev) => ({ ...prev, severity: e.target.value }))}
        >
          <option value="">All severities</option>
          <option value="info">Info</option>
          <option value="warning">Warning</option>
          <option value="error">Error</option>
        </select>
        <div className="flex items-center gap-1.5 rounded-lg border border-border bg-white px-3 py-2 text-sm">
          <Calendar className="h-4 w-4 text-slate-500" />
          <input
            type="date"
            className="bg-transparent text-sm outline-none"
            value={filters.from_date}
            onChange={(e) => setFilters((prev) => ({ ...prev, from_date: e.target.value }))}
          />
          <span className="text-slate-400">–</span>
          <input
            type="date"
            className="bg-transparent text-sm outline-none"
            value={filters.to_date}
            onChange={(e) => setFilters((prev) => ({ ...prev, to_date: e.target.value }))}
          />
        </div>
        <button
          onClick={applyFilters}
          className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-primary-dark"
        >
          Filter
        </button>
      </div>

      {error && <p className="text-sm text-danger">{error}</p>}

      <div className="glass-panel overflow-hidden">
        <table className="min-w-full text-left text-sm">
          <thead>
            <tr className="border-b border-border bg-slate-50/60 text-xs uppercase text-slate-500">
              <th className="px-5 py-3 font-semibold">Timestamp</th>
              {isAdmin && <th className="px-5 py-3 font-semibold">Org</th>}
              <th className="px-5 py-3 font-semibold">User</th>
              <th className="px-5 py-3 font-semibold">Event</th>
              <th className="px-5 py-3 font-semibold">Severity</th>
              <th className="px-5 py-3 font-semibold">Description</th>
              <th className="px-5 py-3 font-semibold">IP</th>
              <th className="px-5 py-3 font-semibold">Correlation ID</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {loading ? (
              <tr>
                <td colSpan={isAdmin ? 8 : 7} className="px-5 py-6 text-center text-slate-500">Loading…</td>
              </tr>
            ) : rows.length === 0 ? (
              <tr>
                <td colSpan={isAdmin ? 8 : 7} className="px-5 py-6 text-center text-slate-500">No logs found.</td>
              </tr>
            ) : (
              rows.map((r, i) => (
                <tr key={i} className="transition hover:bg-slate-50/60">
                  <td className="px-5 py-3 text-slate-600">{r.created_at}</td>
                  {isAdmin && <td className="px-5 py-3 font-mono text-xs text-slate-600">{r.org_id ?? 0}</td>}
                  <td className="px-5 py-3 font-mono text-xs text-slate-600">{r.user_id}</td>
                  <td className="px-5 py-3 font-medium text-ink">{r.event_type}</td>
                  <td className="px-5 py-3"><SeverityBadge severity={r.severity} /></td>
                  <td className="px-5 py-3 text-slate-600">{r.description}</td>
                  <td className="px-5 py-3 font-mono text-xs text-slate-600">{r.ip_address}</td>
                  <td className="px-5 py-3 font-mono text-xs text-slate-400">#{r.correlation_id?.slice(0, 12)}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </AdminPageShell>
  );
}

function SeverityBadge({ severity }: { severity?: string }) {
  const map: Record<string, string> = {
    info: 'bg-sky-50 text-sky-700 border-sky-200',
    warning: 'bg-amber-50 text-amber-700 border-amber-200',
    error: 'bg-red-50 text-red-700 border-red-200',
  };
  const cls = map[severity || ''] || 'bg-slate-100 text-slate-600 border-slate-200';
  return <span className={`badge border ${cls}`}>{severity || '—'}</span>;
}
