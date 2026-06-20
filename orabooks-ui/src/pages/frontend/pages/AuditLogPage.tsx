import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { Calendar, Download, Filter, RefreshCw, ShieldCheck } from 'lucide-react';

interface AuditRow {
  created_at: string;
  user_id: number;
  event_type: string;
  severity: string;
  description: string;
  ip_address: string;
  correlation_id: string;
  metadata?: string;
}

interface AuditFilters {
  user_id: string;
  event_type: string;
  severity: string;
  from_date: string;
  to_date: string;
  correlation_id: string;
}

const emptyFilters: AuditFilters = {
  user_id: '',
  event_type: '',
  severity: '',
  from_date: '',
  to_date: '',
  correlation_id: '',
};

export default function AuditLogPage() {
  const [context, setContext] = useState<any>(null);
  const [filters, setFilters] = useState<AuditFilters>(emptyFilters);
  const [applied, setApplied] = useState<AuditFilters>(emptyFilters);
  const [rows, setRows] = useState<AuditRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const orgId = context?.organization?.id;
  const canView = (context?.permissions || []).includes('view_audit_logs');

  const buildParams = (f: AuditFilters, resolvedOrgId?: number) => {
    const params: Record<string, string | number> = { limit: 100 };
    const id = resolvedOrgId ?? orgId;
    if (id) params.org_id = id;
    if (f.user_id) params.user_id = Number(f.user_id);
    if (f.event_type) params.event_type = f.event_type;
    if (f.severity) params.severity = f.severity;
    if (f.from_date) params.from_date = f.from_date;
    if (f.to_date) params.to_date = `${f.to_date} 23:59:59`;
    if (f.correlation_id) params.correlation_id = f.correlation_id;
    return params;
  };

  const load = async (f = applied) => {
    setLoading(true);
    setError('');

    const ctx = await api.frontendContext();
    if (ctx.error) {
      setError(ctx.error || 'Please log in to view audit logs.');
      setLoading(false);
      return;
    }

    const nextContext = (ctx as any).data;
    setContext(nextContext);

    const permissions: string[] = nextContext?.permissions || [];
    if (!permissions.includes('view_audit_logs')) {
      setError('You do not have permission to view audit logs. Contact Owner or Admin.');
      setLoading(false);
      return;
    }

    const res = await api.auditLogs(buildParams(f, nextContext?.organization?.id));
    if (res.error) setError(res.error);
    else setRows((res as any).data || []);
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  const applyFilters = () => {
    setApplied(filters);
    void load(filters);
  };

  const exportCsv = () => {
    api.exportAuditLogs(buildParams(applied));
  };

  const isPartner = context?.organization?.organization_type === 'partner' || context?.user?.is_partner;

  return (
    <ClientShell
      title="Audit Log"
      eyebrow="Compliance & security"
      organization={context?.organization}
      isPartner={isPartner}
    >
      {!canView && !loading ? (
        <div className="glass-panel max-w-lg p-6 text-center">
          <ShieldCheck className="mx-auto h-8 w-8 text-slate-400" />
          <p className="mt-3 font-medium text-ink">Access denied</p>
          <p className="mt-1 text-sm text-slate-600">
            You do not have permission to view audit logs. Contact Owner or Admin.
          </p>
        </div>
      ) : (
        <div className="space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-sm text-slate-600">
              Immutable record of system events for your organization. Cannot be edited.
            </p>
            <div className="flex flex-wrap gap-2">
              <Button variant="secondary" size="sm" onClick={exportCsv}>
                <Download className="h-4 w-4" />
                Export CSV
              </Button>
              <Button variant="secondary" size="sm" onClick={() => void load()}>
                <RefreshCw className="h-4 w-4" />
                Refresh
              </Button>
            </div>
          </div>

          <div className="glass-panel flex flex-wrap items-center gap-2 p-4">
            <input
              className="rounded-lg border border-border bg-white px-3 py-2 text-sm outline-none"
              placeholder="User ID"
              value={filters.user_id}
              onChange={(e) => setFilters((prev) => ({ ...prev, user_id: e.target.value }))}
            />
            <input
              className="rounded-lg border border-border bg-white px-3 py-2 text-sm outline-none"
              placeholder="Event type"
              value={filters.event_type}
              onChange={(e) => setFilters((prev) => ({ ...prev, event_type: e.target.value }))}
            />
            <input
              className="rounded-lg border border-border bg-white px-3 py-2 text-sm outline-none"
              placeholder="Correlation ID"
              value={filters.correlation_id}
              onChange={(e) => setFilters((prev) => ({ ...prev, correlation_id: e.target.value }))}
            />
            <select
              className="rounded-lg border border-border bg-white px-3 py-2 text-sm outline-none"
              value={filters.severity}
              onChange={(e) => setFilters((prev) => ({ ...prev, severity: e.target.value }))}
            >
              <option value="">All severities</option>
              <option value="info">Info</option>
              <option value="warning">Warning</option>
              <option value="critical">Critical</option>
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
            <Button size="sm" onClick={applyFilters}>
              <Filter className="h-4 w-4" />
              Filter
            </Button>
          </div>

          {error && (
            <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>
          )}

          <div className="glass-panel overflow-hidden">
            <table className="min-w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border bg-slate-50/60 text-xs uppercase text-slate-500">
                  <th className="px-5 py-3 font-semibold">Timestamp</th>
                  <th className="px-5 py-3 font-semibold">User</th>
                  <th className="px-5 py-3 font-semibold">Event</th>
                  <th className="px-5 py-3 font-semibold">Severity</th>
                  <th className="px-5 py-3 font-semibold">Description</th>
                  <th className="px-5 py-3 font-semibold">IP</th>
                  <th className="px-5 py-3 font-semibold">Correlation</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {loading ? (
                  <tr>
                    <td colSpan={7} className="px-5 py-6 text-center text-slate-500">Loading…</td>
                  </tr>
                ) : rows.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-5 py-6 text-center text-slate-500">No logs found.</td>
                  </tr>
                ) : (
                  rows.map((row, index) => (
                    <tr key={`${row.correlation_id}-${index}`} className="transition hover:bg-slate-50/60">
                      <td className="px-5 py-3 text-slate-600">{row.created_at}</td>
                      <td className="px-5 py-3 font-mono text-xs text-slate-600">{row.user_id || '—'}</td>
                      <td className="px-5 py-3 font-medium text-ink">{row.event_type}</td>
                      <td className="px-5 py-3"><SeverityBadge severity={row.severity} /></td>
                      <td className="px-5 py-3 text-slate-600">{row.description}</td>
                      <td className="px-5 py-3 font-mono text-xs text-slate-600">{row.ip_address || '—'}</td>
                      <td className="px-5 py-3 font-mono text-xs text-slate-400" title={row.correlation_id}>
                        #{row.correlation_id?.slice(0, 12)}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </ClientShell>
  );
}

function SeverityBadge({ severity }: { severity?: string }) {
  const map: Record<string, string> = {
    info: 'bg-sky-50 text-sky-700 border-sky-200',
    warning: 'bg-amber-50 text-amber-700 border-amber-200',
    critical: 'bg-red-50 text-red-700 border-red-200',
  };
  const cls = map[severity || ''] || 'bg-slate-100 text-slate-600 border-slate-200';
  return <span className={`badge border ${cls}`}>{severity || '—'}</span>;
}
