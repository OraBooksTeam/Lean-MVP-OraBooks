import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { Building2, FileText, RefreshCw } from 'lucide-react';

export default function VendorsPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = async () => {
    setLoading(true);
    setError('');
    const res = await api.vendorDashboard();
    if (res.error) setError(res.error || 'Unable to load vendors.');
    else setData((res as any).data);
    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const vendors = data?.recent_vendors?.vendors || [];
  const bills = data?.recent_bills?.bills || [];
  const aging = data?.ap_aging || {};

  return (
    <ClientShell title="Vendors & Bills" eyebrow="Accounts payable" organization={data?.context?.organization}>
      <div className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Metric label="Total Vendors" value={data?.stats?.total_vendors ?? 0} />
          <Metric label="Active Vendors" value={data?.stats?.active_vendors ?? 0} />
          <Metric label="Total Payable" value={money(data?.stats?.total_payable)} />
          <Metric label="Vendor Credits" value={money(data?.stats?.total_credit)} />
        </div>

        <section className="glass-panel p-5">
          <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">AP Aging</h2>
          <div className="mt-4 grid gap-3 sm:grid-cols-4">
            <AgingBucket label="Current" value={aging.current} />
            <AgingBucket label="1–30 days" value={aging['30']} />
            <AgingBucket label="31–60 days" value={aging['60']} />
            <AgingBucket label="90+ days" value={aging['90_plus']} />
          </div>
        </section>

        <div className="flex justify-end">
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {error && <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>}

        <div className="glass-panel overflow-hidden">
          <div className="border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Vendors</h2>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Vendor</th>
                <th className="px-5 py-3 font-semibold">Terms</th>
                <th className="px-5 py-3 font-semibold">Status</th>
                <th className="px-5 py-3 text-right font-semibold">Payable</th>
                <th className="px-5 py-3 text-right font-semibold">Credit</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={5} className="px-5 py-8 text-center text-slate-500">Loading vendors...</td></tr>
              ) : vendors.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-5 py-10 text-center">
                    <Building2 className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No vendor records found.</p>
                  </td>
                </tr>
              ) : vendors.map((vendor: any) => (
                <tr key={vendor.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3">
                    <p className="font-semibold text-ink">{vendor.name}</p>
                    {vendor.email && <p className="text-xs text-slate-500">{vendor.email}</p>}
                  </td>
                  <td className="px-5 py-3 text-slate-600">{vendor.payment_terms ?? 30} days</td>
                  <td className="px-5 py-3">
                    <StatusBadge active={Number(vendor.is_active) === 1} />
                  </td>
                  <td className="px-5 py-3 text-right font-bold text-ink">{money(vendor.payable_balance)}</td>
                  <td className="px-5 py-3 text-right text-slate-600">{money(vendor.credit_balance)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="glass-panel overflow-hidden">
          <div className="border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Recent Bills</h2>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Bill</th>
                <th className="px-5 py-3 font-semibold">Vendor</th>
                <th className="px-5 py-3 font-semibold">Due Date</th>
                <th className="px-5 py-3 font-semibold">Workflow</th>
                <th className="px-5 py-3 font-semibold">Payment</th>
                <th className="px-5 py-3 text-right font-semibold">Total</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={6} className="px-5 py-8 text-center text-slate-500">Loading bills...</td></tr>
              ) : bills.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-5 py-10 text-center">
                    <FileText className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No bills found for this workspace.</p>
                  </td>
                </tr>
              ) : bills.map((bill: any) => (
                <tr key={bill.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3 font-semibold text-ink">{bill.bill_number || `Bill #${bill.id}`}</td>
                  <td className="px-5 py-3 text-slate-600">{bill.vendor_name || '—'}</td>
                  <td className="px-5 py-3 text-slate-600">{bill.due_date || '—'}</td>
                  <td className="px-5 py-3"><WorkflowBadge value={bill.workflow_status || 'draft'} /></td>
                  <td className="px-5 py-3"><WorkflowBadge value={bill.payment_status || 'unpaid'} /></td>
                  <td className="px-5 py-3 text-right font-bold text-ink">{money(bill.total_amount, bill.currency)}</td>
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

function AgingBucket({ label, value }: { label: string; value?: number }) {
  return (
    <div className="rounded-xl border border-border bg-slate-50/70 p-3">
      <p className="text-xs font-semibold uppercase text-slate-500">{label}</p>
      <p className="mt-1 text-lg font-bold text-ink">{money(value)}</p>
    </div>
  );
}

function StatusBadge({ active }: { active: boolean }) {
  return (
    <span className={`badge border ${active ? 'border-success/20 bg-success/10 text-success' : 'border-slate-200 bg-slate-100 text-slate-600'}`}>
      {active ? 'active' : 'inactive'}
    </span>
  );
}

function WorkflowBadge({ value }: { value: string }) {
  return <span className="badge border border-border bg-slate-50 text-slate-700">{value}</span>;
}

function money(value?: string | number, currency = 'USD') {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(Number(value || 0));
}
