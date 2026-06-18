import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import AdminPageShell from '@/components/AdminPageShell';
import { api } from '../api';
import { RefreshCw, ShieldCheck } from 'lucide-react';

export default function AdminSecurity() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [verifying, setVerifying] = useState(false);

  const load = () => {
    setLoading(true);
    setError('');
    api.securityDashboard(24).then((res: any) => {
      if (res.error) setError(res.message || res.error);
      else setData(res.data);
      setLoading(false);
    });
  };

  const verifyControls = () => {
    setVerifying(true);
    api.securityVerifyControls().then((res: any) => {
      if (res.error) setError(res.message || res.error);
      else load();
      setVerifying(false);
    });
  };

  useEffect(() => {
    load();
  }, []);

  const headers = data?.headers_status?.configured || {};
  const controls = data?.owasp_controls || [];
  const scans = data?.latest_scans || [];
  const rateLimits = data?.rate_limits || {};

  return (
    <AdminPageShell
      title="Security Dashboard"
      description="OWASP Top-10 controls, incident trends, scan results, and header status."
      actions={
        <div className="flex gap-2">
          <Button onClick={verifyControls} variant="secondary" size="sm" disabled={verifying}>
            <ShieldCheck className="h-4 w-4" />
            {verifying ? 'Verifying…' : 'Verify Controls'}
          </Button>
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>
      }
    >
      {error && <p className="text-sm text-danger">{error}</p>}
      {loading ? (
        <div className="h-48 animate-pulse rounded-2xl border border-border bg-white" />
      ) : (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div className="stat-card">
              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Failed logins (24h)</p>
              <p className="mt-2 text-xl font-bold text-ink">{data?.failed_logins ?? 0}</p>
            </div>
            <div className="stat-card">
              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Access denied (24h)</p>
              <p className="mt-2 text-xl font-bold text-ink">{data?.access_denied ?? 0}</p>
            </div>
            <div className="stat-card">
              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Rate limit hits (24h)</p>
              <p className="mt-2 text-xl font-bold text-ink">{data?.rate_limit_hits ?? 0}</p>
            </div>
            <div className="stat-card">
              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Audit volume (24h)</p>
              <p className="mt-2 text-xl font-bold text-ink">{data?.audit_volume ?? 0}</p>
            </div>
          </div>

          <div className="glass-panel overflow-hidden">
            <div className="border-b border-border px-5 py-3">
              <h3 className="text-sm font-semibold text-ink">Security Headers</h3>
            </div>
            <table className="min-w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border bg-slate-50/60 text-xs uppercase text-slate-500">
                  <th className="px-5 py-3 font-semibold">Header</th>
                  <th className="px-5 py-3 font-semibold">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {Object.entries(headers).map(([key, val]) => (
                  <tr key={key} className="hover:bg-slate-50/60">
                    <td className="px-5 py-3 font-medium text-ink">{key}</td>
                    <td className="px-5 py-3">
                      <span
                        className={`badge border ${
                          val ? 'border-green-200 bg-green-50 text-green-700' : 'border-amber-200 bg-amber-50 text-amber-700'
                        }`}
                      >
                        {val ? 'Configured' : 'Needs attention'}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="glass-panel overflow-hidden">
            <div className="border-b border-border px-5 py-3">
              <h3 className="text-sm font-semibold text-ink">OWASP Top-10 Controls</h3>
            </div>
            <table className="min-w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border bg-slate-50/60 text-xs uppercase text-slate-500">
                  <th className="px-5 py-3 font-semibold">ID</th>
                  <th className="px-5 py-3 font-semibold">Control</th>
                  <th className="px-5 py-3 font-semibold">SL</th>
                  <th className="px-5 py-3 font-semibold">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {controls.length === 0 ? (
                  <tr>
                    <td colSpan={4} className="px-5 py-6 text-center text-slate-500">
                      No controls seeded — run Verify Controls
                    </td>
                  </tr>
                ) : (
                  controls.map((row: any) => (
                    <tr key={row.owasp_id} className="hover:bg-slate-50/60">
                      <td className="px-5 py-3 font-mono text-xs">{row.owasp_id}</td>
                      <td className="px-5 py-3">{row.control_name}</td>
                      <td className="px-5 py-3 text-xs text-slate-600">{row.implemented_in_sl}</td>
                      <td className="px-5 py-3">
                        <span
                          className={`badge border ${
                            row.status === 'verified'
                              ? 'border-green-200 bg-green-50 text-green-700'
                              : row.status === 'failed'
                                ? 'border-red-200 bg-red-50 text-red-700'
                                : 'border-slate-200 bg-slate-50 text-slate-600'
                          }`}
                        >
                          {row.status || 'pending'}
                        </span>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          <div className="grid gap-6 lg:grid-cols-2">
            <div className="glass-panel overflow-hidden">
              <div className="border-b border-border px-5 py-3">
                <h3 className="text-sm font-semibold text-ink">Latest Scans</h3>
              </div>
              <table className="min-w-full text-left text-sm">
                <tbody className="divide-y divide-border">
                  {scans.length === 0 ? (
                    <tr>
                      <td className="px-5 py-6 text-center text-slate-500">No scan results yet</td>
                    </tr>
                  ) : (
                    scans.map((scan: any) => (
                      <tr key={scan.id} className="hover:bg-slate-50/60">
                        <td className="px-5 py-3 font-medium text-ink">{scan.scan_type}</td>
                        <td className="px-5 py-3">
                          <span className="badge border border-primary/20 bg-primary/10 text-primary">
                            {scan.status}
                          </span>
                        </td>
                        <td className="px-5 py-3 text-xs text-slate-600">{scan.summary}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>

            <div className="glass-panel overflow-hidden">
              <div className="border-b border-border px-5 py-3">
                <h3 className="text-sm font-semibold text-ink">Rate Limits</h3>
              </div>
              <table className="min-w-full text-left text-sm">
                <tbody className="divide-y divide-border">
                  {Object.entries(rateLimits).map(([key, cfg]: [string, any]) => (
                    <tr key={key} className="hover:bg-slate-50/60">
                      <td className="px-5 py-3">{cfg.label || key}</td>
                      <td className="px-5 py-3 font-mono text-xs text-slate-600">
                        {cfg.max} / {cfg.period}s
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}
    </AdminPageShell>
  );
}
