import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import AdminPageShell from '@/components/AdminPageShell';
import { api } from '../api';
import { RefreshCw } from 'lucide-react';

export default function AdminObservability() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = () => {
    setLoading(true);
    setError('');
    api.observabilityDashboard(24).then((res: any) => {
      if (res.error) setError(res.error);
      else setData(res.data);
      setLoading(false);
    });
  };

  useEffect(() => {
    load();
  }, []);

  const snapshots = data?.snapshots || {};

  return (
    <AdminPageShell
      title="Platform Observability"
      description="Queue depth, subsystem health, and failure signals across the platform."
      actions={
        <Button onClick={load} variant="secondary" size="sm">
          <RefreshCw className="h-4 w-4" />
          Refresh
        </Button>
      }
    >
      {error && <p className="text-sm text-danger">{error}</p>}
      {loading ? (
        <div className="h-48 animate-pulse rounded-2xl border border-border bg-white" />
      ) : (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {['eventbus', 'async_queue', 'notifications', 'exports'].map((key) => (
              <div key={key} className="stat-card">
                <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{key.replace('_', ' ')}</p>
                <p className="mt-2 text-xl font-bold text-ink">
                  {(snapshots[key]?.status || '—').toString().toUpperCase()}
                </p>
              </div>
            ))}
          </div>
          <div className="glass-panel overflow-hidden">
            <table className="min-w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border bg-slate-50/60 text-xs uppercase text-slate-500">
                  <th className="px-5 py-3 font-semibold">Service</th>
                  <th className="px-5 py-3 font-semibold">Status</th>
                  <th className="px-5 py-3 font-semibold">Details</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {Object.keys(snapshots).length === 0 ? (
                  <tr>
                    <td colSpan={3} className="px-5 py-6 text-center text-slate-500">No observability data</td>
                  </tr>
                ) : (
                  Object.entries(snapshots).map(([key, val]: [string, any]) => (
                    <tr key={key} className="hover:bg-slate-50/60">
                      <td className="px-5 py-3 font-medium text-ink">{key}</td>
                      <td className="px-5 py-3">
                        <span className="badge border border-primary/20 bg-primary/10 text-primary">
                          {val?.status || '—'}
                        </span>
                      </td>
                      <td className="px-5 py-3 font-mono text-xs text-slate-600">
                        {JSON.stringify(val)}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </AdminPageShell>
  );
}
