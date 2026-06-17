import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { RefreshCw, Users } from 'lucide-react';

export default function CustomersPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = async () => {
    setLoading(true);
    setError('');
    const res = await api.customerDashboard();
    if (res.error) setError(res.error || 'Unable to load customers.');
    else setData((res as any).data);
    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const customers = data?.recent_customers?.customers || [];

  return (
    <ClientShell title="Customers" eyebrow="Client records" organization={data?.context?.organization}>
      <div className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-3">
          <Metric label="Total Customers" value={data?.stats?.total_customers ?? 0} />
          <Metric label="Active" value={data?.stats?.active_customers ?? 0} />
          <Metric label="Inactive" value={data?.stats?.inactive_customers ?? 0} />
        </div>

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
                <th className="px-5 py-3 font-semibold">Customer</th>
                <th className="px-5 py-3 font-semibold">Status</th>
                <th className="px-5 py-3 font-semibold">Invoices</th>
                <th className="px-5 py-3 text-right font-semibold">Paid</th>
                <th className="px-5 py-3 font-semibold">Last Paid</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={5} className="px-5 py-8 text-center text-slate-500">Loading customers...</td></tr>
              ) : customers.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-5 py-10 text-center">
                    <Users className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No customer records found.</p>
                  </td>
                </tr>
              ) : customers.map((customer: any) => (
                <tr key={customer.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3">
                    <p className="font-semibold text-ink">{customer.email || `Customer #${customer.id}`}</p>
                    {customer.notes && <p className="text-xs text-slate-500">{customer.notes}</p>}
                  </td>
                  <td className="px-5 py-3">
                    <span className={`badge border ${Number(customer.is_active) === 1 ? 'border-success/20 bg-success/10 text-success' : 'border-slate-200 bg-slate-100 text-slate-600'}`}>
                      {Number(customer.is_active) === 1 ? 'active' : 'inactive'}
                    </span>
                  </td>
                  <td className="px-5 py-3 text-slate-600">{customer.invoice_count ?? 0}</td>
                  <td className="px-5 py-3 text-right font-bold text-ink">{money(customer.total_paid)}</td>
                  <td className="px-5 py-3 text-slate-600">{customer.last_paid_invoice_date || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </ClientShell>
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

function money(value?: string | number) {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(Number(value || 0));
}
