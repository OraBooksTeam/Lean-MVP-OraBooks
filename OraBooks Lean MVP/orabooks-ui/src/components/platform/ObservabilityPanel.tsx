import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import { api } from '@/pages/frontend/api';
import { AlertTriangle, CheckCircle2, RefreshCw, ShieldAlert } from 'lucide-react';

export default function ObservabilityPanel() {
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
  const slos = data?.slos?.objectives || {};
  const sloSummary = data?.slos?.summary || {};

  return (
    <div className="space-y-6">
      <Button onClick={load} variant="secondary" size="sm">
        <RefreshCw className="h-4 w-4" />
        Refresh
      </Button>

      {error && <p className="text-sm text-danger">{error}</p>}
      {loading ? (
        <div className="h-48 animate-pulse rounded-2xl border border-border bg-white" />
      ) : (
        <>
          <div className="glass-panel p-5">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
              <div>
                <h2 className="font-bold text-ink">SLO &amp; Error Budget</h2>
                <p className="text-sm text-slate-600">
                  Service level objectives and remaining error budget ({data?.slos?.window_days ?? 30}-day window).
                </p>
              </div>
              <div className="flex flex-wrap gap-2 text-xs">
                <SloSummaryPill label="Healthy" value={sloSummary.healthy ?? 0} tone="success" />
                <SloSummaryPill label="At risk" value={sloSummary.at_risk ?? 0} tone="warning" />
                <SloSummaryPill label="Breached" value={sloSummary.breached ?? 0} tone="danger" />
              </div>
            </div>

            <div className="mt-4 grid gap-4 lg:grid-cols-2">
              {Object.keys(slos).length === 0 ? (
                <p className="text-sm text-slate-500">No SLO data available yet.</p>
              ) : (
                Object.values(slos).map((slo: any) => (
                  <SloCard key={slo.id} slo={slo} />
                ))
              )}
            </div>
          </div>

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
                    <td colSpan={3} className="px-5 py-6 text-center text-slate-500">
                      No observability data
                    </td>
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
                      <td className="px-5 py-3 font-mono text-xs text-slate-600">{JSON.stringify(val)}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}

function SloSummaryPill({
  label,
  value,
  tone,
}: {
  label: string;
  value: number;
  tone: 'success' | 'warning' | 'danger';
}) {
  const styles = {
    success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    warning: 'border-amber-200 bg-amber-50 text-amber-800',
    danger: 'border-red-200 bg-red-50 text-red-800',
  }[tone];

  return (
    <span className={`rounded-full border px-3 py-1 font-medium ${styles}`}>
      {label}: {value}
    </span>
  );
}

function SloCard({ slo }: { slo: any }) {
  const status = slo.status || 'healthy';
  const statusStyles = {
    healthy: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    at_risk: 'border-amber-200 bg-amber-50 text-amber-800',
    breached: 'border-red-200 bg-red-50 text-red-800',
  }[status as 'healthy' | 'at_risk' | 'breached'] || 'border-slate-200 bg-slate-50 text-slate-700';

  const Icon = status === 'healthy' ? CheckCircle2 : status === 'at_risk' ? AlertTriangle : ShieldAlert;
  const budget = Number(slo.error_budget_remaining_percent ?? 0);

  return (
    <div className="rounded-2xl border border-border bg-white p-4 shadow-sm">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h3 className="font-semibold text-ink">{slo.name}</h3>
          <p className="mt-1 text-xs text-slate-500">{slo.description}</p>
        </div>
        <span className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium ${statusStyles}`}>
          <Icon className="h-3.5 w-3.5" />
          {status.replace('_', ' ')}
        </span>
      </div>

      <div className="mt-4 grid grid-cols-2 gap-3 text-sm">
        <Metric label="Current" value={`${Number(slo.current_percent ?? 0).toFixed(2)}%`} />
        <Metric label="Target" value={`${Number(slo.target_percent ?? 0).toFixed(2)}%`} />
        <Metric label="Samples" value={String(slo.sample_total ?? 0)} />
        <Metric label="Burn rate/day" value={`${Number(slo.error_budget_burn_rate_per_day ?? 0).toFixed(2)}%`} />
      </div>

      <div className="mt-4">
        <div className="mb-1 flex items-center justify-between text-xs text-slate-600">
          <span>Error budget remaining</span>
          <span className="font-semibold">{budget.toFixed(1)}%</span>
        </div>
        <div className="h-2 overflow-hidden rounded-full bg-slate-100">
          <div
            className={`h-full rounded-full ${budget <= 10 ? 'bg-red-500' : budget <= 25 ? 'bg-amber-500' : 'bg-emerald-500'}`}
            style={{ width: `${Math.max(0, Math.min(100, budget))}%` }}
          />
        </div>
        <p className="mt-2 text-xs text-slate-500">
          Used {Number(slo.error_budget_used_failures ?? 0).toFixed(0)} of{' '}
          {Number(slo.error_budget_allowed_failures ?? 0).toFixed(1)} allowed failures.
        </p>
      </div>
    </div>
  );
}

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-xl border border-border bg-slate-50/70 px-3 py-2">
      <p className="text-xs uppercase tracking-wide text-slate-500">{label}</p>
      <p className="mt-0.5 font-semibold text-ink">{value}</p>
    </div>
  );
}
