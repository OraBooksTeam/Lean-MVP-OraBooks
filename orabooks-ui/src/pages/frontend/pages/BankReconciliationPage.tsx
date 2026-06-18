import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { Landmark, RefreshCw, Wallet } from 'lucide-react';

export default function BankReconciliationPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = async () => {
    setLoading(true);
    setError('');
    const res = await api.bankDashboard();
    if (res.error) setError(res.error || 'Unable to load bank data.');
    else setData((res as any).data);
    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const accounts = data?.accounts || [];
  const transactions = data?.recent_transactions || [];
  const reconciliations = data?.recent_reconciliations || [];
  const unmatched = data?.stats?.unmatched_count ?? 0;

  return (
    <ClientShell title="Bank Reconciliation" eyebrow="Feeds & matching" organization={data?.context?.organization}>
      <div className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Metric label="Bank Accounts" value={data?.stats?.total_accounts ?? 0} />
          <Metric label="Total Balance" value={money(data?.stats?.total_balance)} />
          <Metric label="Unmatched" value={unmatched} tone={unmatched > 0 ? 'warning' : 'default'} />
          <Metric label="Reconciled" value={data?.stats?.reconciled_count ?? 0} />
        </div>

        {unmatched > 0 && (
          <div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <Wallet className="mt-0.5 h-5 w-5 shrink-0" />
            <p>
              <span className="font-semibold">{unmatched}</span> bank transaction{unmatched === 1 ? '' : 's'} still need matching or review.
            </p>
          </div>
        )}

        <div className="flex justify-end">
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {error && <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>}

        <div className="glass-panel overflow-hidden">
          <div className="border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Bank Accounts</h2>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Account</th>
                <th className="px-5 py-3 font-semibold">Number</th>
                <th className="px-5 py-3 font-semibold">Currency</th>
                <th className="px-5 py-3 text-right font-semibold">Balance</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={4} className="px-5 py-8 text-center text-slate-500">Loading accounts...</td></tr>
              ) : accounts.length === 0 ? (
                <tr>
                  <td colSpan={4} className="px-5 py-10 text-center">
                    <Landmark className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No bank accounts configured yet.</p>
                  </td>
                </tr>
              ) : accounts.map((account: any) => (
                <tr key={account.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3 font-semibold text-ink">{account.account_name}</td>
                  <td className="px-5 py-3 text-slate-600">{account.account_number || '—'}</td>
                  <td className="px-5 py-3 text-slate-600">{account.currency || 'USD'}</td>
                  <td className="px-5 py-3 text-right font-bold text-ink">{money(account.current_balance, account.currency)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="glass-panel overflow-hidden">
          <div className="border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Recent Transactions</h2>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Date</th>
                <th className="px-5 py-3 font-semibold">Account</th>
                <th className="px-5 py-3 font-semibold">Description</th>
                <th className="px-5 py-3 font-semibold">Reference</th>
                <th className="px-5 py-3 font-semibold">Status</th>
                <th className="px-5 py-3 text-right font-semibold">Amount</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={6} className="px-5 py-8 text-center text-slate-500">Loading transactions...</td></tr>
              ) : transactions.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-5 py-10 text-center text-sm text-slate-500">No bank transactions imported yet.</td>
                </tr>
              ) : transactions.map((txn: any) => (
                <tr key={txn.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3 text-slate-600">{txn.transaction_date || '—'}</td>
                  <td className="px-5 py-3 text-slate-600">{txn.account_name || '—'}</td>
                  <td className="px-5 py-3 text-ink">{txn.description || '—'}</td>
                  <td className="px-5 py-3 font-mono text-xs text-slate-500">{txn.reference || '—'}</td>
                  <td className="px-5 py-3"><StatusBadge status={txn.status} /></td>
                  <td className={`px-5 py-3 text-right font-bold ${Number(txn.amount) >= 0 ? 'text-success' : 'text-red-600'}`}>
                    {money(txn.amount)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="glass-panel overflow-hidden">
          <div className="border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Recent Reconciliations</h2>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Statement Date</th>
                <th className="px-5 py-3 font-semibold">Account</th>
                <th className="px-5 py-3 text-right font-semibold">Ending Balance</th>
                <th className="px-5 py-3 text-right font-semibold">System Balance</th>
                <th className="px-5 py-3 text-right font-semibold">Difference</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={5} className="px-5 py-8 text-center text-slate-500">Loading reconciliation history...</td></tr>
              ) : reconciliations.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-5 py-10 text-center text-sm text-slate-500">No reconciliations finalized yet.</td>
                </tr>
              ) : reconciliations.map((entry: any) => (
                <tr key={entry.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3 text-slate-600">{entry.statement_date || '—'}</td>
                  <td className="px-5 py-3 text-slate-600">{entry.account_name || '—'}</td>
                  <td className="px-5 py-3 text-right font-bold text-ink">{money(entry.ending_balance)}</td>
                  <td className="px-5 py-3 text-right text-slate-600">{money(entry.system_balance)}</td>
                  <td className={`px-5 py-3 text-right font-bold ${Math.abs(Number(entry.difference)) < 0.01 ? 'text-success' : 'text-amber-700'}`}>
                    {money(entry.difference)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </ClientShell>
  );
}

function Metric({ label, value, tone = 'default' }: { label: string; value: string | number; tone?: 'default' | 'warning' }) {
  return (
    <div className="stat-card">
      <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</p>
      <p className={`mt-2 text-3xl font-black ${tone === 'warning' ? 'text-amber-700' : 'text-ink'}`}>{value}</p>
    </div>
  );
}

function StatusBadge({ status }: { status: string }) {
  const colors: Record<string, string> = {
    unmatched: 'border-amber-200 bg-amber-50 text-amber-800',
    matched: 'border-primary/20 bg-primary/10 text-primary-dark',
    reconciled: 'border-success/20 bg-success/10 text-success',
    skipped: 'border-slate-200 bg-slate-100 text-slate-600',
  };

  return (
    <span className={`badge border ${colors[status] || 'border-border bg-slate-50 text-slate-700'}`}>
      {status || 'unknown'}
    </span>
  );
}

function money(value?: string | number, currency = 'USD') {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(Number(value || 0));
}
