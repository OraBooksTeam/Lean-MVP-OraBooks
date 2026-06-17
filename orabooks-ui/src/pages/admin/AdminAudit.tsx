import { useEffect, useState } from 'react';
import { api } from '../api';
import { Calendar, User, Filter } from 'lucide-react';

interface AuditRow {
  created_at: string;
  user_id: number;
  event_type: string;
  severity: string;
  description: string;
  ip_address: string;
  correlation_id: string;
}

export default function AdminAudit() {
  const [rows, setRows] = useState<AuditRow[]>([]);
  const [loading, setLoading] = useState(true);

  const load = () => {
    setLoading(true);
    api.auditLogs().then((res: any) => {
      if (!res.error) setRows((res as any).data || []);
      setLoading(false);
    });
  };

  useEffect(() => { load(); }, []);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-ink">Audit Log</h1>
        <button onClick={load} className="flex items-center gap-1.5 rounded-lg border border-border bg-white px-3 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50">
          <Filter className="h-4 w-4" /> Refresh
        </button>
      </div>
      <div className="flex flex-wrap items-center gap-2">
        <div className="flex items-center gap-1.5 rounded-lg border border-border bg-white px-3 py-2 text-sm"><User className="h-4 w-4 text-slate-500" /> <input className="w-24 bg-transparent text-sm outline-none" placeholder="User ID" /></div>
        <div className="flex items-center gap-1.5 rounded-lg border border-border bg-white px-3 py-2 text-sm"><Calendar className="h-4 w-4 text-slate-500" /> <input type="date" className="bg-transparent text-sm outline-none" /></div>
        <button className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-primary-dark">Filter</button>
      </div>
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
              <th className="px-5 py-3 font-semibold">Correlation ID</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {loading ? <tr><td colSpan={7} className="px-5 py-6 text-center text-slate-500">Loading…</td></tr> : rows.length === 0 ? <tr><td colSpan={7} className="px-5 py-6 text-center text-slate-500">No logs found.</td></tr> : rows.map((r, i) => (
              <tr key={i} className="transition hover:bg-slate-50/60">
                <td className="px-5 py-3 text-slate-600">{r.created_at}</td>
                <td className="px-5 py-3 font-mono text-xs text-slate-600">{r.user_id}</td>
                <td className="px-5 py-3 font-medium text-ink">{r.event_type}</td>
                <td className="px-5 py-3"><SeverityBadge severity={r.severity} /></td>
                <td className="px-5 py-3 text-slate-600">{r.description}</td>
                <td className="px-5 py-3 font-mono text-xs text-slate-600">{r.ip_address}</td>
                <td className="px-5 py-3 font-mono text-xs text-slate-400">#{r.correlation_id?.slice(0, 12)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function SeverityBadge({ severity }: { severity?: string }) {
  const map: Record<string, string> = { info: 'bg-sky-50 text-sky-700 border-sky-200', warning: 'bg-amber-50 text-amber-700 border-amber-200', error: 'bg-red-50 text-red-700 border-red-200' };
  const cls = map[severity || ''] || 'bg-slate-100 text-slate-600 border-slate-200';
  return <span className={`badge border ${cls}`}>{severity || '—'}</span>;
}
