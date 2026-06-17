import { useEffect, useState } from 'react';
import { api } from '../api';

interface Account {
  code: string;
  name: string;
  type: string;
  normal_balance: string;
  system_generated: number | string;
  is_active: number | string;
}

export default function AdminCoA() {
  const [orgs, setOrgs] = useState<any[]>([]);
  const [orgId, setOrgId] = useState<number>(0);
  const [accounts, setAccounts] = useState<Account[]>([]);
  const [loading, setLoading] = useState(false);

  const loadOrgs = () => {
    api.listOrgs().then((res) => {
      if (!res.error) setOrgs((res as any).data || []);
    });
  };

  const loadCoa = () => {
    if (!orgId) return;
    setLoading(true);
    api.coaGet(orgId).then((res) => {
      if (!res.error) setAccounts((res as any).data || []);
      setLoading(false);
    });
  };

  useEffect(() => { loadOrgs(); }, []);

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-ink">Chart of Accounts</h1>
      <div className="flex flex-wrap items-center gap-2">
        <select
          value={orgId}
          onChange={(e) => setOrgId(Number(e.target.value))}
          className="rounded-lg border border-border bg-white px-3 py-2 text-sm"
        >
          <option value={0}>Select Organization...</option>
          {orgs.map((o) => (
            <option key={o.id} value={o.id}>{o.name} ({o.subdomain})</option>
          ))}
        </select>
        <button
          onClick={loadCoa}
          className="rounded-lg border border-border bg-white px-4 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50"
        >
          Load
        </button>
      </div>
      <div className="glass-panel overflow-hidden">
        <table className="min-w-full text-left text-sm">
          <thead>
            <tr className="border-b border-border bg-slate-50/60 text-xs uppercase text-slate-500">
              <th className="px-5 py-3 font-semibold">Code</th>
              <th className="px-5 py-3 font-semibold">Name</th>
              <th className="px-5 py-3 font-semibold">Type</th>
              <th className="px-5 py-3 font-semibold">Balance</th>
              <th className="px-5 py-3 font-semibold">System</th>
              <th className="px-5 py-3 font-semibold">Active</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {loading ? (
              <tr><td colSpan={6} className="px-5 py-6 text-center text-slate-500">Loading…</td></tr>
            ) : accounts.length === 0 ? (
              <tr><td colSpan={6} className="px-5 py-6 text-center text-slate-500">Select organization and click Load.</td></tr>
            ) : accounts.map((a, i) => (
              <tr key={i} className="transition hover:bg-slate-50/60">
                <td className="px-5 py-3 font-mono font-semibold text-ink">{a.code}</td>
                <td className="px-5 py-3 text-ink">{a.name}</td>
                <td className="px-5 py-3"><span className={`badge border ${badgeClass(a.type)}`}>{a.type}</span></td>
                <td className="px-5 py-3 text-slate-600 capitalize">{a.normal_balance}</td>
                <td className="px-5 py-3 text-slate-600">{Number(a.system_generated) ? '✅' : '—'}</td>
                <td className="px-5 py-3 text-slate-600">{Number(a.is_active) ? '✅ Active' : '❌ Inactive'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function badgeClass(type: string) {
  const map: Record<string, string> = {
    asset: 'bg-sky-50 text-sky-700 border-sky-200',
    liability: 'bg-rose-50 text-rose-700 border-rose-200',
    equity: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    revenue: 'bg-amber-50 text-amber-700 border-amber-200',
    expense: 'bg-purple-50 text-purple-700 border-purple-200',
  };
  return map[type] || 'bg-slate-100 text-slate-600 border-slate-200';
}
