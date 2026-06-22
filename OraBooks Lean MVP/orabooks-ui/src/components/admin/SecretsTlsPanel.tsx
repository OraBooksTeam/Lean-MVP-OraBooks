import { CheckCircle2, KeyRound, ShieldAlert, XCircle } from 'lucide-react';
import type { DeployCheck } from '@/lib/security/sl008';
import { filterSl008DeployChecks, sl008DeployChecksOk } from '@/lib/security/sl008';

type SecretsRotation = {
  due?: boolean;
  days_since?: number;
  days_until?: number;
  last_rotated?: string;
};

type Props = {
  checks: DeployCheck[];
  secretsRotation?: SecretsRotation | null;
  loading?: boolean;
};

function checkTone(ok: boolean) {
  return ok ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700';
}

export default function SecretsTlsPanel({ checks, secretsRotation, loading }: Props) {
  const sl008Checks = filterSl008DeployChecks(checks);
  const allOk = sl008DeployChecksOk(checks);

  return (
    <div className="glass-panel overflow-hidden">
      <div className="flex flex-wrap items-start justify-between gap-3 border-b border-border px-5 py-4">
        <div className="flex items-start gap-3">
          {allOk ? (
            <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-green-600" />
          ) : (
            <ShieldAlert className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
          )}
          <div>
            <h3 className="text-sm font-semibold text-ink">Secrets &amp; TLS (SL-008)</h3>
            <p className="mt-0.5 text-xs text-slate-500">
              JWT/encryption keys, database TLS, and certificate provisioning — no secrets are shown in the UI.
            </p>
          </div>
        </div>
        {!loading && sl008Checks.length > 0 && (
          <span className={`badge border ${allOk ? checkTone(true) : checkTone(false)}`}>
            {allOk ? 'Healthy' : 'Needs attention'}
          </span>
        )}
      </div>

      {loading ? (
        <div className="h-28 animate-pulse bg-slate-50/80" />
      ) : sl008Checks.length === 0 ? (
        <p className="px-5 py-6 text-sm text-slate-500">
          Run post-deploy verification from Settings to load SL-008 checks.
        </p>
      ) : (
        <table className="min-w-full text-left text-sm">
          <thead>
            <tr className="border-b border-border bg-slate-50/60 text-xs uppercase text-slate-500">
              <th className="px-5 py-3 font-semibold">Check</th>
              <th className="px-5 py-3 font-semibold">Status</th>
              <th className="px-5 py-3 font-semibold">Detail</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {sl008Checks.map((check) => (
              <tr key={check.id} className="hover:bg-slate-50/60">
                <td className="px-5 py-3 font-medium text-ink">{check.label}</td>
                <td className="px-5 py-3">
                  <span className={`inline-flex items-center gap-1.5 badge border ${checkTone(check.ok)}`}>
                    {check.ok ? <CheckCircle2 className="h-3.5 w-3.5" /> : <XCircle className="h-3.5 w-3.5" />}
                    {check.ok ? 'OK' : 'FAIL'}
                  </span>
                </td>
                <td className="px-5 py-3 text-xs text-slate-600">{check.detail || '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {secretsRotation && (
        <div className="border-t border-border px-5 py-4 text-xs text-slate-600">
          <div className="flex items-start gap-2">
            <KeyRound className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
            <div>
              <p>
                Secret rotation:{' '}
                {secretsRotation.due ? (
                  <span className="font-semibold text-amber-700">
                    overdue ({secretsRotation.days_since} days since last rotation)
                  </span>
                ) : (
                  <span className="font-semibold text-green-700">
                    OK ({secretsRotation.days_until} days until due)
                  </span>
                )}
              </p>
              {secretsRotation.last_rotated && (
                <p className="mt-1 text-slate-500">Last rotated: {secretsRotation.last_rotated}</p>
              )}
              <p className="mt-1 text-slate-500">
                Runbook: docs/SL-008-secret-rotation-runbook.md (JWT 30d, encryption 90d, TLS &amp; DB SSL)
              </p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
