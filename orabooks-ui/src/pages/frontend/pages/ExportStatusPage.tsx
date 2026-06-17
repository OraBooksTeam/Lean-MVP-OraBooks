import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import { api } from '@/pages/frontend/api';
import { Download, RefreshCw } from 'lucide-react';

export default function ExportStatusPage() {
  const [loading, setLoading] = useState(true);
  const [exports, setExports] = useState<any[]>([]);
  const [stats, setStats] = useState({ total: 0, pending: 0, ready: 0 });

  const load = () => {
    setLoading(true);
    api.get('orabooks_export_status', { user_id: 0 }).then((res: any) => {
      if (!res.error && res.data) {
        setExports(res.data.exports || []);
        setStats({
          total: res.data.total || 0,
          pending: (res.data.exports || []).filter((e: any) => e.status === 'pending').length,
          ready: (res.data.exports || []).filter((e: any) => e.status === 'completed').length,
        });
      }
      setLoading(false);
    });
  };

  useEffect(() => { load(); }, []);

  return (
    <div className="min-h-screen bg-slate-50 p-6">
      <div className="mx-auto max-w-5xl space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-bold text-ink">My Exports</h1>
          <button onClick={load} className="flex items-center gap-1.5 rounded-lg border border-border bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
            <RefreshCw className="h-4 w-4" /> Refresh
          </button>
        </div>

        <div className="grid gap-4 sm:grid-cols-3">
          <div className="stat-card">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Exports</p>
            <p className="mt-1 text-2xl font-bold text-ink">{stats.total}</p>
          </div>
          <div className="stat-card">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending</p>
            <p className="mt-1 text-2xl font-bold text-orange-600">{stats.pending}</p>
          </div>
          <div className="stat-card">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Ready</p>
            <p className="mt-1 text-2xl font-bold text-emerald-600">{stats.ready}</p>
          </div>
        </div>

        <div className="glass-panel overflow-hidden">
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/60 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Type</th>
                <th className="px-5 py-3 font-semibold">Format</th>
                <th className="px-5 py-3 font-semibold">Status</th>
                <th className="px-5 py-3 font-semibold">Expires</th>
                <th className="px-5 py-3 font-semibold">Downloads</th>
                <th className="px-5 py-3 font-semibold">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={6} className="px-5 py-6 text-center text-slate-500">Loading…</td></tr>
              ) : exports.length === 0 ? (
                <tr><td colSpan={6} className="px-5 py-6 text-center text-slate-500">No exports found.</td></tr>
              ) : exports.map((ex: any) => (
                <tr key={ex.id} className="transition hover:bg-slate-50/60">
                  <td className="px-5 py-3 font-medium text-ink">{ex.export_type || '—'}</td>
                  <td className="px-5 py-3 uppercase text-slate-600">{ex.format || '—'}</td>
                  <td className="px-5 py-3"><StatusBadge status={ex.status} /></td>
                  <td className="px-5 py-3 text-slate-600">{ex.expires_at || '—'}</td>
                  <td className="px-5 py-3 text-slate-600">{ex.download_count ?? 0}</td>
                  <td className="px-5 py-3">
                    {ex.status === 'completed' && ex.download_url ? (
                      <a href={ex.download_url} className="inline-flex items-center gap-1 text-primary hover:text-primary-dark">
                        <Download className="h-4 w-4" /> Download
                      </a>
                    ) : (
                      <span className="text-xs text-slate-400">Not ready</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

function StatusBadge({ status }: { status?: string }) {
  const map: Record<string, string> = {
    pending: 'bg-amber-50 text-amber-700 border-amber-200',
    processing: 'bg-sky-50 text-sky-700 border-sky-200',
    completed: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    failed: 'bg-red-50 text-red-700 border-red-200',
  };
  const cls = map[status || ''] || 'bg-slate-100 text-slate-600 border-slate-200';
  return <span className={`badge border ${cls}`}>{status || 'unknown'}</span>;
}
