import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import AdminPageShell from '@/components/AdminPageShell';
import { api } from '../api';
import { RefreshCw, Shield, ShieldAlert, ShieldCheck } from 'lucide-react';

interface OwaspControl {
  owasp_id: string;
  control_name: string;
  implemented_in_sl: string;
  validation_note: string;
  status: string;
  last_verified: string | null;
  mitigations?: string[];
  user_message?: string | null;
}

function statusBadge(status: string) {
  if (status === 'verified') {
    return 'border-green-200 bg-green-50 text-green-700';
  }
  if (status === 'failed') {
    return 'border-red-200 bg-red-50 text-red-700';
  }
  return 'border-slate-200 bg-slate-50 text-slate-600';
}

function scanBadge(status: string) {
  if (status === 'pass') return 'border-green-200 bg-green-50 text-green-700';
  if (status === 'fail') return 'border-red-200 bg-red-50 text-red-700';
  return 'border-amber-200 bg-amber-50 text-amber-700';
}

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
  const controls: OwaspControl[] = data?.owasp_controls || [];
  const scans = data?.latest_scans || [];
  const rateLimits = data?.rate_limits || {};
  const incidents = data?.incidents_by_type || {};
  const allowlist: string[] = data?.webhook_allowlist || [];
  const schemas: string[] = data?.input_schemas || [];
  const secretsRotation = data?.secrets_rotation;

  const verifiedCount = controls.filter((c) => c.status === 'verified').length;

  return (
    <AdminPageShell
      title="Security Dashboard"
      description="OWASP Top-10 (2021) governance — controls, incidents, scans, and header status (SL-099)."
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
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div className="stat-card">
              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">OWASP verified</p>
              <p className="mt-2 text-xl font-bold text-ink">
                {verifiedCount}/{controls.length || 10}
              </p>
            </div>
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
              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">SSRF blocks (24h)</p>
              <p className="mt-2 text-xl font-bold text-ink">{data?.ssrf_blocks ?? 0}</p>
            </div>
          </div>

          <div className="glass-panel overflow-hidden">
            <div className="border-b border-border px-5 py-3">
              <h3 className="text-sm font-semibold text-ink">OWASP Top-10 Controls</h3>
              <p className="mt-0.5 text-xs text-slate-500">
                Cross-cutting governance mapped to OraBooks SL implementations
              </p>
            </div>
            <div className="grid gap-4 p-4 lg:grid-cols-2">
              {controls.length === 0 ? (
                <p className="col-span-2 px-1 py-4 text-center text-sm text-slate-500">
                  No controls seeded — click Verify Controls
                </p>
              ) : (
                controls.map((row) => (
                  <div
                    key={row.owasp_id}
                    className="rounded-xl border border-border bg-white p-4 shadow-sm shadow-primary/5"
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div className="flex items-start gap-3">
                        {row.status === 'verified' ? (
                          <ShieldCheck className="mt-0.5 h-5 w-5 shrink-0 text-green-600" />
                        ) : row.status === 'failed' ? (
                          <ShieldAlert className="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
                        ) : (
                          <Shield className="mt-0.5 h-5 w-5 shrink-0 text-slate-400" />
                        )}
                        <div>
                          <p className="text-xs font-mono text-slate-500">{row.owasp_id}</p>
                          <h4 className="font-semibold text-ink">{row.control_name}</h4>
                          <p className="mt-1 text-xs text-slate-600">{row.validation_note}</p>
                        </div>
                      </div>
                      <span className={`badge shrink-0 border ${statusBadge(row.status)}`}>
                        {row.status || 'pending'}
                      </span>
                    </div>
                    <div className="mt-3 flex flex-wrap gap-1">
                      {row.implemented_in_sl.split(',').map((sl) => (
                        <span
                          key={sl.trim()}
                          className="rounded-md bg-primary/5 px-2 py-0.5 text-[10px] font-semibold text-primary"
                        >
                          {sl.trim()}
                        </span>
                      ))}
                    </div>
                    {row.mitigations && row.mitigations.length > 0 && (
                      <ul className="mt-3 space-y-1 border-t border-border pt-3 text-xs text-slate-600">
                        {row.mitigations.map((m) => (
                          <li key={m} className="flex gap-2">
                            <span className="text-primary">•</span>
                            {m}
                          </li>
                        ))}
                      </ul>
                    )}
                    {row.user_message && (
                      <p className="mt-2 rounded-lg bg-amber-50 px-2 py-1 text-[11px] text-amber-800">
                        User message: &ldquo;{row.user_message}&rdquo;
                      </p>
                    )}
                    {row.last_verified && (
                      <p className="mt-2 text-[10px] text-slate-400">
                        Last verified: {row.last_verified}
                      </p>
                    )}
                  </div>
                ))
              )}
            </div>
          </div>

          <div className="grid gap-6 lg:grid-cols-2">
            <div className="glass-panel overflow-hidden">
              <div className="border-b border-border px-5 py-3">
                <h3 className="text-sm font-semibold text-ink">Security Headers</h3>
              </div>
              <div className="-mx-5 overflow-x-auto overflow-y-hidden px-5">
                <div className="min-w-[400px]">
                  <table className="w-full text-left text-sm">
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
                                val
                                  ? 'border-green-200 bg-green-50 text-green-700'
                                  : 'border-amber-200 bg-amber-50 text-amber-700'
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
              </div>
            </div>

            <div className="glass-panel overflow-hidden">
              <div className="border-b border-border px-5 py-3">
                <h3 className="text-sm font-semibold text-ink">Incident Breakdown (24h)</h3>
              </div>
              <div className="-mx-5 overflow-x-auto overflow-y-hidden px-5">
                <div className="min-w-[400px]">
                  <table className="w-full text-left text-sm">
                    <tbody className="divide-y divide-border">
                      {Object.keys(incidents).length === 0 ? (
                        <tr>
                          <td className="px-5 py-6 text-center text-slate-500">No incidents in period</td>
                        </tr>
                      ) : (
                        Object.entries(incidents).map(([type, count]) => (
                          <tr key={type} className="hover:bg-slate-50/60">
                            <td className="px-5 py-3 font-medium text-ink">{type}</td>
                            <td className="px-5 py-3 font-mono text-xs text-slate-600">{count as number}</td>
                          </tr>
                        ))
                      )}
                    </tbody>
                  </table>
                </div>
              </div>
              <div className="border-t border-border px-5 py-3 text-xs text-slate-500">
                Audit volume: {data?.audit_volume ?? 0} events
              </div>
            </div>
          </div>

          <div className="grid gap-6 lg:grid-cols-2">
            <div className="glass-panel overflow-hidden">
              <div className="border-b border-border px-5 py-3">
                <h3 className="text-sm font-semibold text-ink">Scheduled Scans</h3>
              </div>
              <div className="-mx-5 overflow-x-auto overflow-y-hidden px-5">
                <div className="min-w-[450px]">
                  <table className="w-full text-left text-sm">
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
                              <span className={`badge border ${scanBadge(scan.status)}`}>{scan.status}</span>
                            </td>
                            <td className="px-5 py-3 text-xs text-slate-600">{scan.summary}</td>
                          </tr>
                        ))
                      )}
                    </tbody>
                  </table>
                </div>
              </div>
              {secretsRotation && (
                <div className="border-t border-border px-5 py-3 text-xs text-slate-600">
                  Secret rotation:{' '}
                  {secretsRotation.due ? (
                    <span className="font-semibold text-amber-700">
                      overdue ({secretsRotation.days_since} days)
                    </span>
                  ) : (
                    <span className="text-green-700">
                      OK ({secretsRotation.days_until} days until due)
                    </span>
                  )}
                </div>
              )}
            </div>

            <div className="glass-panel overflow-hidden">
              <div className="border-b border-border px-5 py-3">
                <h3 className="text-sm font-semibold text-ink">Rate Limits (centralized)</h3>
              </div>
              <div className="-mx-5 overflow-x-auto overflow-y-hidden px-5">
                <div className="min-w-[350px]">
                  <table className="w-full text-left text-sm">
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
          </div>

          <div className="grid gap-6 lg:grid-cols-2">
            <div className="glass-panel overflow-hidden">
              <div className="border-b border-border px-5 py-3">
                <h3 className="text-sm font-semibold text-ink">SSRF Webhook Allowlist (A10)</h3>
                <p className="mt-0.5 text-xs text-slate-500">Only HTTPS URLs matching these patterns are permitted</p>
              </div>
              <ul className="divide-y divide-border text-sm">
                {allowlist.length === 0 ? (
                  <li className="px-5 py-6 text-center text-slate-500">No allowlist configured</li>
                ) : (
                  allowlist.map((pattern) => (
                    <li key={pattern} className="px-5 py-3 font-mono text-xs text-slate-700">
                      {pattern}
                    </li>
                  ))
                )}
              </ul>
            </div>

            <div className="glass-panel overflow-hidden">
              <div className="border-b border-border px-5 py-3">
                <h3 className="text-sm font-semibold text-ink">Input Validation Schemas (A03)</h3>
                <p className="mt-0.5 text-xs text-slate-500">JSON schema keys enforced via orabooks_validate_schema</p>
              </div>
              <div className="flex flex-wrap gap-2 p-4">
                {schemas.map((schema) => (
                  <span
                    key={schema}
                    className="rounded-lg border border-border bg-slate-50 px-3 py-1.5 font-mono text-xs text-slate-700"
                  >
                    {schema}
                  </span>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}
    </AdminPageShell>
  );
}
