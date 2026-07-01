import { useEffect, useState } from 'react';
import WpLink from '../components/WpLink';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import {
  AlertTriangle,
  CheckCircle2,
  Copy,
  Lightbulb,
  Lock,
  RefreshCw,
  TrendingUp,
  Users,
} from 'lucide-react';

interface PartnerDashboardData {
  partner_code: string;
  partner_type?: string;
  organization_name?: string;
  code_status: string;
  org_status: string;
  org_name?: string;
  active_customer_count: number;
  is_dormant: boolean;
  is_inactive?: boolean;
  read_only: boolean;
  payout_disabled: boolean;
  can_reactivate: boolean;
  new_attribution_blocked?: boolean;
  status_banner?: { type: string; message: string } | null;
  attribution_stats: { total: number; verified: number; pending: number };
  commission_summary: {
    total_earned: number;
    pending_payout: number;
    paid: number;
    expired: number;
    currency: string;
  };
  payout_breakdown: { period: string; gross: number; fee: number; net: number; status: string }[];
  attributions: {
    id: number;
    customer_email_masked?: string;
    attribution_date?: string;
    attribution_status?: string;
    commission_status?: string;
  }[];
}

export default function PartnerProgramPage() {
  const [context, setContext] = useState<any>(null);
  const [data, setData] = useState<PartnerDashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [copied, setCopied] = useState(false);
  const [showReactivate, setShowReactivate] = useState(false);
  const [reactivateReason, setReactivateReason] = useState('');
  const [requesting, setRequesting] = useState(false);

  const load = async () => {
    setLoading(true);
    setError('');
    const ctx = await api.frontendContext();
    if (ctx.error) {
      setError(ctx.error || 'Please log in to view the partner program.');
      setLoading(false);
      return;
    }
    setContext((ctx as any).data);

    const res = await api.partnerDashboard();
    if (res.error) setError(res.error);
    else setData((res as any).data);
    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const organization = context?.organization || (data ? {
    name: data.org_name || data.organization_name,
    organization_type: 'partner',
    status: data.org_status,
    tier: 'partner',
  } : null);

  const copyCode = async () => {
    if (!data?.partner_code) return;
    await navigator.clipboard.writeText(data.partner_code);
    setCopied(true);
    void api.partnerCodeCopied('dashboard');
    window.setTimeout(() => setCopied(false), 1800);
  };

  const submitReactivation = async () => {
    const orgId = context?.organization?.id;
    if (!orgId) return;
    setRequesting(true);
    setError('');
    setSuccess('');
    const res = await api.requestReactivation(orgId, reactivateReason.trim() || 'Requested from partner dashboard.');
    if (res.error) setError(res.error);
    else {
      setSuccess('Reactivation request submitted. An admin will review your request.');
      setShowReactivate(false);
      setReactivateReason('');
    }
    setRequesting(false);
  };

  const attrStats = normalizeStats(data?.attribution_stats);
  const currency = data?.commission_summary.currency || 'USD';

  return (
    <ClientShell title="Partner Program" eyebrow="Commission workspace" organization={organization} isPartner>
      <div className="space-y-6">
        {loading && (
          <div className="glass-panel p-6 text-sm text-slate-500">Loading partner program…</div>
        )}

        {error && (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>
        )}
        {success && (
          <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">{success}</div>
        )}

        {data && (
          <>
            {data.status_banner && (
              <StatusBanner banner={data.status_banner} canReactivate={data.can_reactivate} onReactivate={() => setShowReactivate(true)} />
            )}

            {data.read_only && !data.status_banner && (
              <div className="flex gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-slate-800">
                <Lock className="mt-0.5 h-5 w-5 shrink-0" />
                <p className="text-sm font-medium">Partner program is read-only. Contact support for reactivation.</p>
              </div>
            )}

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <Metric label="Total Attributions" value={attrStats.total} />
              <Metric label="Verified" value={attrStats.verified} />
              <Metric label="Pending" value={attrStats.pending} />
              <Metric label="Active Customers" value={data.active_customer_count} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <Metric label="Total Earned" value={money(data.commission_summary.total_earned, currency)} />
              <Metric label="Pending Payout" value={money(data.commission_summary.pending_payout, currency)} />
              <Metric label="Paid" value={money(data.commission_summary.paid, currency)} />
              <Metric label="Expired" value={money(data.commission_summary.expired, currency)} />
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
              <section className="glass-panel p-5">
                <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">Your Partner Code</h2>
                <div className="mt-4 rounded-2xl border border-border bg-slate-50 p-4">
                  <p className="font-mono text-lg font-bold tracking-wide text-ink">{data.partner_code}</p>
                  <p className="mt-2 text-xs text-slate-500">
                    Customers use this code during signup. Commissions accrue only while your code is active.
                  </p>
                  <Button onClick={copyCode} variant="secondary" className="mt-4 w-full" disabled={data.new_attribution_blocked}>
                    {copied ? <CheckCircle2 className="h-4 w-4 text-success" /> : <Copy className="h-4 w-4" />}
                    {copied ? 'Copied' : 'Copy Code'}
                  </Button>
                </div>
              </section>

              <section className="glass-panel p-5">
                <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">Partner Status</h2>
                <div className="mt-4 space-y-2">
                  <InfoRow label="Code Status" value={data.code_status} />
                  <InfoRow label="Org Status" value={data.org_status} />
                  <InfoRow label="Partner Type" value={data.partner_type || 'individual'} />
                  <InfoRow label="Organization" value={data.organization_name || data.org_name || '—'} />
                  <InfoRow label="Payout Hold" value={data.payout_disabled ? 'Yes' : 'No'} />
                </div>
              </section>

              <section className="glass-panel p-5">
                <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">Commission Details</h2>
                <p className="mt-2 text-sm text-slate-600">
                  View earned commissions, escrow schedule, payout batches, and aging.
                </p>
                <WpLink
                  to="/commissions"
                  className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-border bg-white px-4 py-2.5 text-sm font-semibold text-primary shadow-sm transition hover:bg-slate-50"
                >
                  <TrendingUp className="h-4 w-4" />
                  Open Commission Details
                </WpLink>
              </section>
            </div>

            <section className="glass-panel overflow-hidden">
              <div className="border-b border-border px-5 py-4">
                <h2 className="font-bold text-ink">Commission Payout Breakdown</h2>
                <p className="mt-1 text-sm text-slate-600">Gross, transaction fee, and net payout per period.</p>
              </div>
              <table className="min-w-full text-left text-sm">
                <thead>
                  <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                    <th className="px-5 py-3 font-semibold">Period</th>
                    <th className="px-5 py-3 text-right font-semibold">Gross</th>
                    <th className="px-5 py-3 text-right font-semibold">Fee</th>
                    <th className="px-5 py-3 text-right font-semibold">Net</th>
                    <th className="px-5 py-3 font-semibold">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {data.payout_breakdown.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="px-5 py-8 text-center text-slate-500">No payout batches yet.</td>
                    </tr>
                  ) : data.payout_breakdown.map((row, idx) => (
                    <tr key={`${row.period}-${idx}`} className="hover:bg-slate-50/70">
                      <td className="px-5 py-3 font-medium text-ink">{row.period}</td>
                      <td className="px-5 py-3 text-right text-slate-700">{money(row.gross, currency)}</td>
                      <td className="px-5 py-3 text-right text-slate-700">{money(row.fee, currency)}</td>
                      <td className="px-5 py-3 text-right font-semibold text-ink">{money(row.net, currency)}</td>
                      <td className="px-5 py-3 capitalize text-slate-600">{row.status}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </section>

            <section className="glass-panel overflow-hidden">
              <div className="border-b border-border px-5 py-4">
                <h2 className="font-bold text-ink">Attributions</h2>
              </div>
              <table className="min-w-full text-left text-sm">
                <thead>
                  <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                    <th className="px-5 py-3 font-semibold">Customer</th>
                    <th className="px-5 py-3 font-semibold">Date</th>
                    <th className="px-5 py-3 font-semibold">Attribution</th>
                    <th className="px-5 py-3 font-semibold">Commission</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {data.attributions.length === 0 ? (
                    <tr>
                      <td colSpan={4} className="px-5 py-10 text-center">
                        <Users className="mx-auto h-8 w-8 text-slate-300" />
                        <p className="mt-2 text-sm text-slate-500">No attributions yet. Share your partner code to get started.</p>
                      </td>
                    </tr>
                  ) : data.attributions.map((attr) => (
                    <tr key={attr.id} className="hover:bg-slate-50/70">
                      <td className="px-5 py-3 font-medium text-ink">{attr.customer_email_masked || 'Customer'}</td>
                      <td className="px-5 py-3 text-slate-600">{attr.attribution_date || '—'}</td>
                      <td className="px-5 py-3 capitalize text-slate-600">{attr.attribution_status || '—'}</td>
                      <td className="px-5 py-3 text-slate-600">{attr.commission_status || '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </section>
          </>
        )}

        <div className="flex justify-end">
          <Button onClick={load} variant="secondary" size="sm" disabled={loading}>
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>
      </div>

      {showReactivate && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-md rounded-2xl border border-border bg-white p-6 shadow-xl">
            <h3 className="text-lg font-bold text-ink">Request Reactivation</h3>
            <p className="mt-1 text-sm text-slate-600">
              Your partner code is inactive. Submit a request for admin review (SL-140).
            </p>
            <textarea
              className="mt-4 w-full rounded-lg border border-border px-3.5 py-2.5 text-sm"
              rows={4}
              value={reactivateReason}
              onChange={(e) => setReactivateReason(e.target.value)}
              placeholder="Optional reason for reactivation…"
            />
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setShowReactivate(false)} disabled={requesting}>
                Cancel
              </Button>
              <Button onClick={submitReactivation} loading={requesting}>
                Submit Request
              </Button>
            </div>
          </div>
        </div>
      )}
    </ClientShell>
  );
}

function StatusBanner({
  banner,
  canReactivate,
  onReactivate,
}: {
  banner: { type: string; message: string };
  canReactivate: boolean;
  onReactivate: () => void;
}) {
  const styles: Record<string, string> = {
    blocked: 'border-red-200 bg-red-50 text-red-900',
    warning: 'border-amber-200 bg-amber-50 text-amber-900',
    readonly: 'border-slate-200 bg-slate-50 text-slate-800',
    inactive: 'border-amber-200 bg-amber-50 text-amber-900',
    info: 'border-primary/20 bg-primary/10 text-primary-dark',
  };
  const cls = styles[banner.type] || styles.info;

  return (
    <div className={`flex flex-col gap-3 rounded-2xl border p-4 sm:flex-row sm:items-center sm:justify-between ${cls}`}>
      <div className="flex gap-3">
        {banner.type === 'info' ? (
          <Lightbulb className="mt-0.5 h-5 w-5 shrink-0" />
        ) : (
          <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0" />
        )}
        <p className="text-sm font-medium">{banner.message}</p>
      </div>
      {canReactivate && banner.type === 'inactive' && (
        <Button onClick={onReactivate} variant="secondary" size="sm">
          Request Reactivation
        </Button>
      )}
    </div>
  );
}

function Metric({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="stat-card">
      <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</p>
      <p className="mt-2 text-3xl font-black text-ink">{value}</p>
    </div>
  );
}

function InfoRow({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="flex items-center justify-between rounded-lg border border-border bg-white px-4 py-2.5">
      <span className="text-sm text-slate-600">{label}</span>
      <span className="text-sm font-bold capitalize text-ink">{value || '—'}</span>
    </div>
  );
}

function normalizeStats(stats?: { total: number; verified: number; pending: number } | Record<string, any>) {
  if (!stats) return { total: 0, verified: 0, pending: 0 };
  return {
    total: Number(stats.total ?? 0),
    verified: Number(stats.verified ?? 0),
    pending: Number(stats.pending ?? 0),
  };
}

function money(value?: string | number, currency = 'USD') {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(Number(value || 0));
}
