import { useEffect, useMemo, useState } from 'react';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { BookOpen, Download, Info, Link2, RefreshCw } from 'lucide-react';

const ACCOUNT_TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'] as const;

export default function ChartOfAccountsPage() {
  const [context, setContext] = useState<any>(null);
  const [accounts, setAccounts] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState('all');

  const load = async () => {
    setLoading(true);
    setError('');
    const ctx = await api.frontendContext();
    if (ctx.error) {
      setError(ctx.error || 'Unable to load account context.');
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
    if (res.error) setError(res.error || 'Unable to load chart of accounts.');
    else setAccounts((res as any).data || []);
    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const filtered = useMemo(() => {
    const query = search.trim().toLowerCase();
    return accounts.filter((account) => {
      const matchesType = typeFilter === 'all' || account.type === typeFilter;
      const matchesSearch =
        !query ||
        String(account.code || '').toLowerCase().includes(query) ||
        String(account.name || '').toLowerCase().includes(query);
      return matchesType && matchesSearch;
    });
  }, [accounts, search, typeFilter]);

  const orgId = context?.organization?.id;

  return (
    <ClientShell
      title="Chart of Accounts"
      eyebrow="Accounting setup"
      organization={context?.organization}
      isPartner={context?.organization?.organization_type === 'partner'}
    >
      <div className="space-y-5">
        <div className="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50/80 p-4 text-sm text-sky-900">
          <Info className="mt-0.5 h-4 w-4 shrink-0" />
          <p>
            This list is pre-configured for your tier. Accounts are read-only in MVP — contact support if you need changes.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          <div className="min-w-[200px] flex-1">
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search accounts by code or name…"
            />
          </div>
          <select
            value={typeFilter}
            onChange={(e) => setTypeFilter(e.target.value)}
            className="rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink shadow-sm"
          >
            <option value="all">All types</option>
            {ACCOUNT_TYPES.map((type) => (
              <option key={type} value={type}>{titleCase(type)}</option>
            ))}
          </select>
          {orgId ? (
            <Button variant="secondary" size="sm" onClick={() => api.coaExport(orgId)}>
              <Download className="h-4 w-4" />
              Export CSV
            </Button>
          ) : null}
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {error && (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">
            {error}
          </div>
        )}

        <div className="glass-panel overflow-hidden">
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Code</th>
                <th className="px-5 py-3 font-semibold">Account</th>
                <th className="px-5 py-3 font-semibold">Type</th>
                <th className="px-5 py-3 font-semibold">Normal Balance</th>
                <th className="px-5 py-3 font-semibold">Source</th>
                <th className="px-5 py-3 font-semibold">Usage</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={6} className="px-5 py-8 text-center text-slate-500">Loading accounts...</td></tr>
              ) : filtered.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-5 py-10 text-center">
                    <BookOpen className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No accounts match your filters.</p>
                  </td>
                </tr>
              ) : filtered.map((account) => (
                <tr key={account.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3 font-mono text-sm font-semibold text-ink">{account.code}</td>
                  <td className="px-5 py-3 font-semibold text-ink">{account.name}</td>
                  <td className="px-5 py-3 text-slate-600">{titleCase(account.type)}</td>
                  <td className="px-5 py-3 capitalize text-slate-600">{account.normal_balance}</td>
                  <td className="px-5 py-3">
                    <span className="badge border border-border bg-slate-50 text-slate-700">
                      {Number(account.system_generated) === 1 ? 'System' : 'Custom'}
                    </span>
                  </td>
                  <td className="px-5 py-3 text-slate-600">
                    {Number(account.has_journal_entries) === 1 ? (
                      <span className="inline-flex items-center gap-1 text-xs font-medium text-amber-700" title="This account has journal entries. Cannot be deleted.">
                        <Link2 className="h-3.5 w-3.5" />
                        In journals
                      </span>
                    ) : (
                      <span className="text-xs text-slate-400">—</span>
                    )}
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
