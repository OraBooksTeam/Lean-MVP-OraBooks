import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { ArrowLeft, RefreshCw } from 'lucide-react';

export default function CommissionsPage() {
  const [context, setContext] = useState<any>(null);
  const [stats, setStats] = useState<any>(null);
  const [earned, setEarned] = useState<any[]>([]);
  const [payouts, setPayouts] = useState<any[]>([]);
  const [escrow, setEscrow] = useState<any[]>([]);
  const [aging, setAging] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = async () => {
    setLoading(true);
    setError('');
    const ctx = await api.frontendContext();
    if (!ctx.error) setContext((ctx as any).data);

    const [statsRes, earnedRes, payoutsRes, escrowRes, agingRes] = await Promise.all([
      api.commissionStats(),
      api.commissionEarned(),
      api.commissionPayouts(),
      api.commissionEscrow(),
      api.commissionAging(),
    ]);

    if (statsRes.error) setError(statsRes.error);
    else setStats((statsRes as any).data);

    if (!earnedRes.error) setEarned((earnedRes as any).data || []);
    if (!payoutsRes.error) setPayouts((payoutsRes as any).data || []);
    if (!escrowRes.error) setEscrow((escrowRes as any).data || []);
    if (!agingRes.error) setAging((agingRes as any).data);

    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const currency = stats?.currency || 'USD';

  return (
    <ClientShell title="Commission Details" eyebrow="SL-068 read model" organization={context?.organization} isPartner>
      <div className="space-y-6">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <Link
            to="/dashboard"
            className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:text-primary-dark"
          >
            <ArrowLeft className="h-4 w-4" />
            Back to Partner Program
          </Link>
          <Button onClick={load} variant="secondary" size="sm" disabled={loading}>
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {error && (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>
        )}

        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Metric label="Total Earned" value={money(stats?.total_earned, currency)} />
          <Metric label="Pending Payout" value={money(stats?.pending_payout, currency)} />
          <Metric label="Total Paid" value={money(stats?.total_paid, currency)} />
          <Metric label="Escrow Total" value={money(stats?.escrow_total, currency)} />
        </div>

        {stats?.min_payout_threshold != null && (
          <p className="text-sm text-slate-600">
            Minimum payout threshold: <span className="font-semibold text-ink">{money(stats.min_payout_threshold, currency)}</span>
          </p>
        )}

        <DataTable
          title="Earned Commissions"
          empty="No earned commission rows yet."
          loading={loading}
          columns={['Period', 'Amount', 'Status', 'Earned At']}
          rows={earned.map((row: any) => [
            row.period_month || row.release_month || '—',
            money(row.amount, row.currency || currency),
            row.status || '—',
            row.earned_at || row.created_at || '—',
          ])}
        />

        <DataTable
          title="Payout Batches"
          empty="No payout batches yet."
          loading={loading}
          columns={['Period', 'Gross', 'Fee', 'Net', 'Status']}
          rows={payouts.map((row: any) => [
            row.payout_date ? String(row.payout_date).slice(0, 7) : '—',
            money(row.gross_amount, row.currency || currency),
            money(row.fee_amount, row.currency || currency),
            money(row.net_amount, row.currency || currency),
            row.status || '—',
          ])}
        />

        <DataTable
          title="Escrow Schedule"
          empty="No escrow schedules yet."
          loading={loading}
          columns={['Customer', 'Total', 'Released', 'Remaining', 'Status']}
          rows={escrow.map((row: any) => [
            row.customer_email_masked || row.customer_id || '—',
            money(row.total_amount, row.currency || currency),
            money(row.released_amount, row.currency || currency),
            money(row.remaining_amount, row.currency || currency),
            row.remaining_amount_status || '—',
          ])}
        />

        {aging && (
          <section className="glass-panel p-5">
            <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">Commission Aging</h2>
            <pre className="mt-4 max-h-64 overflow-auto rounded-xl border border-border bg-slate-50 p-4 text-xs text-slate-700">
              {JSON.stringify(aging, null, 2)}
            </pre>
          </section>
        )}
      </div>
    </ClientShell>
  );
}

function DataTable({
  title,
  empty,
  loading,
  columns,
  rows,
}: {
  title: string;
  empty: string;
  loading: boolean;
  columns: string[];
  rows: (string | number)[][];
}) {
  return (
    <section className="glass-panel overflow-hidden">
      <div className="border-b border-border px-5 py-4">
        <h2 className="font-bold text-ink">{title}</h2>
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

function Metric({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="stat-card">
      <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</p>
      <p className="mt-2 text-3xl font-black text-ink">{value}</p>
    </div>
  );
}

function money(value?: string | number, currency = 'USD') {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(Number(value || 0));
}
