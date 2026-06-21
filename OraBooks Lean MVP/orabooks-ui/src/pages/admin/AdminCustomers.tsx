import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import AdminPageShell from '@/components/AdminPageShell';
import { api } from '../api';
import { RefreshCw, Users } from 'lucide-react';

export default function AdminCustomers() {
  const [stats, setStats] = useState<any>(null);
  const [customers, setCustomers] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  const load = () => {
    setLoading(true);
    Promise.all([
      api.customerStats(0),
      api.customersList(0, { limit: 100 }),
    ]).then(([statsRes, listRes]) => {
      if (!statsRes.error) setStats((statsRes as any).data);
      if (!listRes.error) setCustomers((listRes as any).data?.customers || []);
      setLoading(false);
    });
  };

  useEffect(() => {
    load();
  }, []);

  return (
    <AdminPageShell
      title="Customers & Invoices"
      description="Cross-organization customer records and receivables overview."
      actions={
        <Button onClick={load} variant="secondary" size="sm">
          <RefreshCw className="h-4 w-4" />
          Refresh
        </Button>
      }
    >
      {loading ? (
        <div className="h-48 animate-pulse rounded-2xl border border-border bg-white" />
      ) : (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div className="stat-card">
              <Users className="h-5 w-5 text-primary" />
              <p className="mt-3 text-xs font-bold uppercase tracking-wide text-slate-500">Total Customers</p>
              <p className="mt-1 text-3xl font-black text-ink">{stats?.total_customers ?? 0}</p>
            </div>
            <div className="stat-card">
              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Active</p>
              <p className="mt-2 text-3xl font-black text-ink">{stats?.active_customers ?? 0}</p>
            </div>
            <div className="stat-card">
              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Inactive</p>
              <p className="mt-2 text-3xl font-black text-ink">{stats?.inactive_customers ?? 0}</p>
            </div>
            <div className="stat-card">
              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Revenue</p>
              <p className="mt-2 text-3xl font-black text-ink">
                ${Number(stats?.total_revenue || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}
              </p>
            </div>
          </div>
          <div className="glass-panel overflow-hidden">
            <table className="min-w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border bg-slate-50/60 text-xs uppercase text-slate-500">
                  <th className="px-5 py-3 font-semibold">ID</th>
                  <th className="px-5 py-3 font-semibold">Email</th>
                  <th className="px-5 py-3 font-semibold">Status</th>
                  <th className="px-5 py-3 font-semibold">Invoices</th>
                  <th className="px-5 py-3 font-semibold">Total Paid</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {customers.length === 0 ? (
                  <tr><td colSpan={5} className="px-5 py-6 text-center text-slate-500">No customers found.</td></tr>
                ) : (
                  customers.map((c) => (
                    <tr key={c.id} className="hover:bg-slate-50/60">
                      <td className="px-5 py-3 font-mono text-slate-600">{c.id}</td>
                      <td className="px-5 py-3 font-medium text-ink">{c.email}</td>
                      <td className="px-5 py-3">
                        <span className={`badge border ${c.is_active ? 'bg-success/10 text-success border-success/20' : 'bg-slate-100 text-slate-600 border-slate-200'}`}>
                          {c.is_active ? 'Active' : 'Inactive'}
                        </span>
                      </td>
                      <td className="px-5 py-3">{c.invoice_count ?? 0}</td>
                      <td className="px-5 py-3">${Number(c.total_paid || 0).toFixed(2)}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </AdminPageShell>
  );
}
