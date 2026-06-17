import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { BookOpen, RefreshCw } from 'lucide-react';

export default function ChartOfAccountsPage() {
  const [context, setContext] = useState<any>(null);
  const [accounts, setAccounts] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = async () => {
    setLoading(true);
    setError('');
    const ctx = await api.frontendContext();
    if (ctx.error) {
      setError((ctx as any).message || 'Unable to load account context.');
      setLoading(false);
      return;
    }

    const nextContext = (ctx as any).data;
    setContext(nextContext);
    const orgId = nextContext?.organization?.id;
    if (!orgId) {
      setError('Organization is not set up yet.');
      setLoading(false);
      return;
    }

    const res = await api.coaGet(orgId);
    if (res.error) setError((res as any).message || 'Unable to load chart of accounts.');
    else setAccounts((res as any).data || []);
    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  return (
    <ClientShell title="Chart of Accounts" eyebrow="Accounting setup" organization={context?.organization}>
      <div className="space-y-5">
        <div className="flex justify-end">
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>
        {error && <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>}
        <div className="glass-panel overflow-hidden">
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Code</th>
                <th className="px-5 py-3 font-semibold">Account</th>
                <th className="px-5 py-3 font-semibold">Type</th>
                <th className="px-5 py-3 font-semibold">Normal Balance</th>
                <th className="px-5 py-3 font-semibold">Source</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={5} className="px-5 py-8 text-center text-slate-500">Loading accounts...</td></tr>
              ) : accounts.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-5 py-10 text-center">
                    <BookOpen className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No active accounts found.</p>
                  </td>
                </tr>
              ) : accounts.map((account) => (
                <tr key={account.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3 font-mono text-sm font-semibold text-ink">{account.code}</td>
                  <td className="px-5 py-3 font-semibold text-ink">{account.name}</td>
                  <td className="px-5 py-3 text-slate-600">{titleCase(account.type)}</td>
                  <td className="px-5 py-3 text-slate-600">{account.normal_balance}</td>
                  <td className="px-5 py-3">
                    <span className="badge border border-border bg-slate-50 text-slate-700">
                      {Number(account.system_generated) === 1 ? 'System' : 'Custom'}
                    </span>
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

function titleCase(value?: string) {
  return (value || 'Other').replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}
