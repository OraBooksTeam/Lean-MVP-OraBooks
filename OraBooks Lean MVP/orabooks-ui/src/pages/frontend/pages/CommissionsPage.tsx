import { Fragment, useEffect, useState } from 'react';
import WpLink from '../components/WpLink';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import {
  ArrowLeft,
  ChevronDown,
  ChevronRight,
  HelpCircle,
  RefreshCw,
} from 'lucide-react';

type TabKey = 'customers' | 'payouts' | 'aging' | 'releases';

const INFO_BANNER =
  'Commission accrued monthly. Minimum payout applies. Payout hold blocks withdrawals. Transaction fee deducted from gross (partner pays).';

export default function CommissionsPage() {
  const [context, setContext] = useState<any>(null);
  const [stats, setStats] = useState<any>(null);
  const [customers, setCustomers] = useState<any[]>([]);
  const [payouts, setPayouts] = useState<any[]>([]);
  const [aging, setAging] = useState<any>(null);
  const [releases, setReleases] = useState<any[]>([]);
  const [tab, setTab] = useState<TabKey>('customers');
  const [expanded, setExpanded] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = async () => {
    setLoading(true);
    setError('');

    const ctx = await api.frontendContext();
    if (ctx.error) {
      setError(ctx.error);
      setLoading(false);
      return;
    }

    const session = (ctx as any).data;
    setContext(session);

    if (!session?.is_partner && session?.organization?.organization_type !== 'partner') {
      setError('Commission dashboard is only available for partner organizations.');
      setLoading(false);
      return;
    }

    const [statsRes, customersRes, payoutsRes, agingRes, releasesRes] = await Promise.all([
      api.commissionStats(),
      api.commissionByCustomer(),
      api.commissionPayouts(),
      api.commissionAging(),
      api.commissionReleaseHistory(),
    ]);

    if (statsRes.error) setError(statsRes.error);
    else setStats((statsRes as any).data);

    if (!customersRes.error) setCustomers((customersRes as any).data || []);
    if (!payoutsRes.error) setPayouts((payoutsRes as any).data || []);
    if (!agingRes.error) setAging((agingRes as any).data);
    if (!releasesRes.error) setReleases((releasesRes as any).data || []);

    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  const currency = stats?.currency || 'USD';
  const minPayout = stats?.min_payout_threshold;

  return (
    <ClientShell
      title="Commissions"
      eyebrow="Partner commissions (accrual basis)"
      organization={context?.organization}
      isPartner
    >
      <div className="space-y-6">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <WpLink
            to="/partner-program"
            className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:text-primary-dark"
          >
            <ArrowLeft className="h-4 w-4" />
            Back to Partner Program
          </WpLink>
          <Button onClick={load} variant="secondary" size="sm" disabled={loading}>
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        <div
          className="rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm text-slate-700"
          title="Commission is accrued monthly when customer is active. Paid via bank transfer. Transaction fee deducted from gross."
        >
          {INFO_BANNER}
          {minPayout != null && (
            <span className="ml-1 font-semibold text-ink">
              Minimum payout: {money(minPayout, currency)}.
            </span>
          )}
        </div>

        {error && (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>
        )}

        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
          <Metric label="Total Earned (Accrued)" value={money(stats?.total_earned, currency)} tooltip="All monthly releases recognized to date." />
          <Metric label="Pending Payout" value={money(stats?.pending_payout, currency)} tooltip="Earned commissions awaiting payout batch." />
          <Metric label="Paid" value={money(stats?.total_paid, currency)} tooltip="Commissions settled via bank transfer." />
          <Metric label="Expired" value={money(stats?.total_expired, currency)} tooltip="Commissions that expired after 6 years unpaid." />
          <Metric label="Escrow Remaining" value={money(stats?.escrow_remaining, currency)} tooltip="Scheduled commission not yet released." />
        </div>

        {stats?.yearly_breakdown_template?.length > 0 && (
          <section className="glass-panel p-5">
            <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">
              Platform Yearly Breakdown
            </h2>
            <p className="mt-1 text-xs text-slate-500" title="Dynamic percentages from partner_commission_config.yearly_percentages">
              Percentages from platform config (partner type does not affect rates in MVP).
            </p>
            <div className="mt-3 flex flex-wrap gap-2">
              {stats.yearly_breakdown_template.map((row: any) => (
                <span key={row.year} className="rounded-lg border border-border bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700">
                  Year {row.year}: {row.percentage}% → {money(row.amount, currency)}
                </span>
              ))}
            </div>
          </section>
        )}

        <div className="flex flex-wrap gap-2 border-b border-border pb-2">
          {([
            ['customers', 'By Customer'],
            ['payouts', 'Payout Batches'],
            ['aging', 'Payable Aging'],
            ['releases', 'Release History'],
          ] as const).map(([key, label]) => (
            <button
              key={key}
              type="button"
              onClick={() => setTab(key)}
              className={`rounded-lg px-3 py-1.5 text-sm font-semibold transition ${
                tab === key
                  ? 'bg-primary text-white'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
              }`}
            >
              {label}
            </button>
          ))}
        </div>

        {tab === 'customers' && (
          <section className="glass-panel overflow-hidden">
            <div className="border-b border-border px-5 py-4">
              <h2 className="font-bold text-ink">Commission by Customer</h2>
              <p className="mt-1 text-xs text-slate-500" title="Yearly breakdown available per customer.">
                Customer email masked. Expand a row for yearly breakdown.
              </p>
            </div>
            <table className="min-w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                  <th className="px-5 py-3 font-semibold" />
                  <th className="px-5 py-3 font-semibold">Customer</th>
                  <th className="px-5 py-3 font-semibold">Total</th>
                  <th className="px-5 py-3 font-semibold">Earned to Date</th>
                  <th className="px-5 py-3 font-semibold">Paid</th>
                  <th className="px-5 py-3 font-semibold">
                    <span title="Each monthly release expires 6 years after release date if not paid.">Expiry</span>
                  </th>
                  <th className="px-5 py-3 font-semibold">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {loading ? (
                  <tr><td colSpan={7} className="px-5 py-8 text-center text-slate-500">Loading…</td></tr>
                ) : customers.length === 0 ? (
                  <tr><td colSpan={7} className="px-5 py-8 text-center text-slate-500">No commission schedules yet.</td></tr>
                ) : customers.map((row: any) => {
                  const isOpen = expanded === row.escrow_id;
                  return (
                    <Fragment key={row.escrow_id}>
                      <tr className="hover:bg-slate-50/70">
                        <td className="px-5 py-3">
                          <button
                            type="button"
                            onClick={() => setExpanded(isOpen ? null : row.escrow_id)}
                            className="text-slate-500 hover:text-ink"
                            aria-label="Toggle yearly breakdown"
                          >
                            {isOpen ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                          </button>
                        </td>
                        <td className="px-5 py-3 font-mono text-xs text-slate-700">{row.customer_email_masked}</td>
                        <td className="px-5 py-3">{money(row.total_amount, row.currency || currency)}</td>
                        <td className="px-5 py-3">{money(row.earned_to_date, row.currency || currency)}</td>
                        <td className="px-5 py-3">{money(row.paid_to_date, row.currency || currency)}</td>
                        <td className="px-5 py-3 text-xs text-slate-600" title="Commission expires 6 years after each monthly release.">
                          {row.next_expiry ? String(row.next_expiry).slice(0, 10) : '—'}
                        </td>
                        <td className="px-5 py-3">
                          <StatusBadge status={row.remaining_amount_status === 'expired' ? 'expired' : row.remaining_amount > 0 ? 'escrowed' : 'earned'} />
                        </td>
                      </tr>
                      {isOpen && row.yearly_breakdown?.length > 0 && (
                        <tr className="bg-slate-50/50">
                          <td colSpan={7} className="px-8 py-3">
                            <p className="mb-2 text-xs font-semibold uppercase text-slate-500">Yearly breakdown</p>
                            <div className="flex flex-wrap gap-2">
                              {row.yearly_breakdown.map((y: any) => (
                                <span key={y.year} className="rounded border border-border bg-white px-2 py-1 text-xs">
                                  Y{y.year}: {y.percentage}% = {money(y.amount, row.currency || currency)}
                                </span>
                              ))}
                            </div>
                          </td>
                        </tr>
                      )}
                    </Fragment>
                  );
                })}
              </tbody>
            </table>
          </section>
        )}

        {tab === 'payouts' && (
          <DataTable
            title="Commission Payout Breakdown"
            subtitle="Gross minus gateway fee = net payout."
            empty="No payout batches yet."
            loading={loading}
            columns={['Period', 'Gross', 'Transaction Fee', 'Net', 'Status']}
            rows={payouts.map((row: any) => [
              row.payout_date ? String(row.payout_date).slice(0, 7) : '—',
              money(row.gross_amount, currency),
              money(row.fee_amount, currency),
              money(row.net_amount, currency),
              row.status || '—',
            ])}
          />
        )}

        {tab === 'aging' && (
          <section className="glass-panel p-5">
            <h2 className="font-bold text-ink">Commission Payable Aging</h2>
            <p className="mt-1 text-xs text-slate-500" title="Commission payable aging buckets for audit.">
              Buckets: 0–30, 31–60, 61–90, 90+, Expired
            </p>
            {loading ? (
              <p className="mt-4 text-sm text-slate-500">Loading…</p>
            ) : !aging ? (
              <p className="mt-4 text-sm text-slate-500">No aging data yet.</p>
            ) : (
              <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <AgingBucket label="0–30 days" amount={aging.bucket_0_30} currency={currency} />
                <AgingBucket label="31–60 days" amount={aging.bucket_31_60} currency={currency} />
                <AgingBucket label="61–90 days" amount={aging.bucket_61_90} currency={currency} />
                <AgingBucket label="90+ days" amount={aging.bucket_90_plus} currency={currency} />
                <AgingBucket label="Expired" amount={aging.expired_total} currency={currency} />
              </div>
            )}
          </section>
        )}

        {tab === 'releases' && (
          <DataTable
            title="Monthly Release History"
            subtitle="Each month's release amount and status."
            empty="No release history yet."
            loading={loading}
            columns={['Customer', 'Release Month', 'Amount', 'Status', 'Released At']}
            rows={releases.map((row: any) => [
              row.customer_email_masked || '—',
              row.release_month ? String(row.release_month).slice(0, 7) : '—',
              money(row.amount, currency),
              row.status || '—',
              row.released_at ? String(row.released_at).slice(0, 10) : '—',
            ])}
          />
        )}
      </div>
    </ClientShell>
  );
}

function Metric({ label, value, tooltip }: { label: string; value: string; tooltip?: string }) {
  return (
    <div className="stat-card" title={tooltip}>
      <div className="flex items-center gap-1">
        <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</p>
        {tooltip && (
          <span className="text-slate-400" title={tooltip}>
            <HelpCircle className="h-3 w-3" aria-hidden />
          </span>
        )}
      </div>
      <p className="mt-2 text-2xl font-black text-ink">{value}</p>
    </div>
  );
}

function AgingBucket({ label, amount, currency }: { label: string; amount?: number; currency: string }) {
  return (
    <div className="rounded-xl border border-border bg-slate-50 p-4">
      <p className="text-xs font-semibold uppercase text-slate-500">{label}</p>
      <p className="mt-2 text-lg font-bold text-ink">{money(amount, currency)}</p>
    </div>
  );
}

function StatusBadge({ status }: { status: string }) {
  const map: Record<string, string> = {
    earned: 'bg-blue-50 text-blue-700 border-blue-200',
    paid: 'bg-success/10 text-success border-success/20',
    expired: 'bg-slate-100 text-slate-600 border-slate-200',
    escrowed: 'bg-amber-50 text-amber-700 border-amber-200',
    released: 'bg-blue-50 text-blue-700 border-blue-200',
    skipped: 'bg-slate-100 text-slate-500 border-slate-200',
  };
  const cls = map[status] || 'bg-slate-100 text-slate-600 border-slate-200';
  return <span className={`badge border text-xs capitalize ${cls}`}>{status}</span>;
}

function DataTable({
  title,
  subtitle,
  empty,
  loading,
  columns,
  rows,
}: {
  title: string;
  subtitle?: string;
  empty: string;
  loading: boolean;
  columns: string[];
  rows: (string | number)[][];
}) {
  return (
    <section className="glass-panel overflow-hidden">
      <div className="border-b border-border px-5 py-4">
        <h2 className="font-bold text-ink">{title}</h2>
        {subtitle && <p className="mt-1 text-xs text-slate-500">{subtitle}</p>}
      </div>
      <table className="min-w-full text-left text-sm">
        <thead>
          <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
            {columns.map((col) => (
              <th key={col} className="px-5 py-3 font-semibold">{col}</th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-border">
          {loading ? (
            <tr><td colSpan={columns.length} className="px-5 py-8 text-center text-slate-500">Loading…</td></tr>
          ) : rows.length === 0 ? (
            <tr><td colSpan={columns.length} className="px-5 py-8 text-center text-slate-500">{empty}</td></tr>
          ) : rows.map((row, idx) => (
            <tr key={idx} className="hover:bg-slate-50/70">
              {row.map((cell, cellIdx) => (
                <td key={cellIdx} className="px-5 py-3 text-slate-700">{cell}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  );
}

function money(value?: string | number, currency = 'USD') {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(Number(value || 0));
}
