import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { FileText, RefreshCw } from 'lucide-react';

export default function InvoicesPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = async () => {
    setLoading(true);
    setError('');
    const res = await api.customerDashboard();
    if (res.error) setError(res.error || 'Unable to load invoices.');
    else setData((res as any).data);
    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  return (
    <ClientShell title="Invoices" eyebrow="Accounts receivable" organization={data?.context?.organization}>
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
                <th className="px-5 py-3 font-semibold">Invoice</th>
                <th className="px-5 py-3 font-semibold">Due Date</th>
                <th className="px-5 py-3 font-semibold">Workflow</th>
                <th className="px-5 py-3 font-semibold">Payment</th>
                <th className="px-5 py-3 text-right font-semibold">Total</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={5} className="px-5 py-8 text-center text-slate-500">Loading invoices...</td></tr>
              ) : (data?.recent_invoices || []).length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-5 py-10 text-center">
                    <FileText className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No invoices found for this workspace.</p>
                  </td>
                </tr>
              ) : (
                data.recent_invoices.map((invoice: any) => (
                  <tr key={invoice.id} className="hover:bg-slate-50/70">
                    <td className="px-5 py-3 font-semibold text-ink">{invoice.invoice_number || `Invoice #${invoice.id}`}</td>
                    <td className="px-5 py-3 text-slate-600">{invoice.due_date || 'Not set'}</td>
                    <td className="px-5 py-3"><Badge value={invoice.workflow_status || 'draft'} /></td>
                    <td className="px-5 py-3"><Badge value={invoice.payment_status || 'unpaid'} /></td>
                    <td className="px-5 py-3 text-right font-bold text-ink">{money(invoice.total_amount, invoice.currency)}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </ClientShell>
  );
}

function Badge({ value }: { value: string }) {
  return <span className="badge border border-border bg-slate-50 text-slate-700">{value}</span>;
}

function money(value?: string | number, currency = 'USD') {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(Number(value || 0));
}
