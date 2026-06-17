import { useEffect, useState } from 'react';
import { api } from '../api';
import Button from '@/components/Button';
import { Building2, ShieldCheck, ShieldOff } from 'lucide-react';

interface Org {
  id: number;
  name: string;
  subdomain: string;
  organization_type: string;
  tier: string;
  status: string;
  created_at: string;
}

export default function AdminOrganizations() {
  const [orgs, setOrgs] = useState<Org[]>([]);
  const [loading, setLoading] = useState(true);

  const load = () => {
    setLoading(true);
    api.listOrgs().then((res) => {
      if (!res.error) setOrgs((res as any).data || []);
      setLoading(false);
    });
  };

  useEffect(() => { load(); }, []);

  const suspend = (id: number) => {
    if (!confirm('Suspend this organization?')) return;
    api.suspendOrg(id).then(() => load());
  };

  const activate = (id: number) => {
    if (!confirm('Activate this organization?')) return;
    api.activateOrg(id).then(() => load());
  };

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-ink">Organizations</h1>
      <div className="flex items-center justify-between">
        <div className="flex gap-2">
          <select className="rounded-lg border border-border bg-white px-3 py-2 text-sm">
            <option value="">All Types</option>
            <option value="customer">Customer</option>
            <option value="partner">Partner</option>
          </select>
          <select className="rounded-lg border border-border bg-white px-3 py-2 text-sm">
            <option value="">All Statuses</option>
            <option value="active">Active</option>
            <option value="pending_setup">Pending Setup</option>
            <option value="suspended">Suspended</option>
          </select>
        </div>
        <Button variant="secondary" onClick={load}>Refresh</Button>
      </div>
      <div className="glass-panel overflow-hidden">
        <table className="min-w-full text-left text-sm">
          <thead>
            <tr className="border-b border-border bg-slate-50/60 text-xs uppercase text-slate-500">
              <th className="px-5 py-3 font-semibold">ID</th>
              <th className="px-5 py-3 font-semibold">Name</th>
              <th className="px-5 py-3 font-semibold">Subdomain</th>
              <th className="px-5 py-3 font-semibold">Type</th>
              <th className="px-5 py-3 font-semibold">Tier</th>
              <th className="px-5 py-3 font-semibold">Status</th>
              <th className="px-5 py-3 font-semibold">Created</th>
              <th className="px-5 py-3 font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {loading ? (
              <tr><td colSpan={8} className="px-5 py-6 text-center text-slate-500">Loading…</td></tr>
            ) : orgs.length === 0 ? (
              <tr><td colSpan={8} className="px-5 py-6 text-center text-slate-500">No organizations found.</td></tr>
            ) : orgs.map((org) => (
              <tr key={org.id} className="transition hover:bg-slate-50/60">
                <td className="px-5 py-3 font-mono text-slate-600">{org.id}</td>
                <td className="px-5 py-3 font-medium text-ink">{org.name}</td>
                <td className="px-5 py-3 font-mono text-xs text-slate-600">{org.subdomain}</td>
                <td className="px-5 py-3 capitalize text-slate-600">{org.organization_type}</td>
                <td className="px-5 py-3 capitalize text-slate-600">{org.tier}</td>
                <td className="px-5 py-3"><StatusBadge status={org.status} /></td>
                <td className="px-5 py-3 text-slate-600">{org.created_at}</td>
                <td className="px-5 py-3">
                  <div className="flex gap-2">
                    {org.status === 'active' ? (
                      <button onClick={() => suspend(org.id)} className="inline-flex items-center gap-1 text-xs font-medium text-amber-700 hover:text-amber-800"><ShieldOff className="h-3.5 w-3.5" /> Suspend</button>
                    ) : (
                      <button onClick={() => activate(org.id)} className="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 hover:text-emerald-800"><ShieldCheck className="h-3.5 w-3.5" /> Activate</button>
                    )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function StatusBadge({ status }: { status?: string }) {
  const map: Record<string, string> = {
    active: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    pending_setup: 'bg-amber-50 text-amber-700 border-amber-200',
    suspended: 'bg-red-50 text-red-700 border-red-200',
  };
  const cls = map[status || ''] || 'bg-slate-100 text-slate-600 border-slate-200';
  return <span className={`badge border ${cls}`}>{status || '—'}</span>;
}
