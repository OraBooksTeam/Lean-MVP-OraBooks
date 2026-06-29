import { useEffect, useMemo, useState } from 'react';
import WpLink from '../components/WpLink';

import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { BarChart3, RefreshCw } from 'lucide-react';

type AgingSummary = {
  current?: number;
  '30'?: number;
  '60'?: number;
  '90_plus'?: number;
};

type AgingBillRow = {
  bill_id: number;
  bill_number?: string;
  vendor_id: number;
  vendor_name?: string;
  bill_date?: string;
  due_date?: string;
  total_amount?: number;
  paid_amount?: number;
  outstanding?: number;
  days_overdue?: number;
  bucket?: string;
  currency?: string;
  payment_status?: string;
};

const BUCKET_LABELS: Record<string, string> = {
  all: 'All buckets',
  current: 'Current',
  '30': '1–30 days',
  '60': '31–60 days',
  '90_plus': '90+ days',
};

export default function ApAgingPage() {
  const [context, setContext] = useState<any>(null);
  const [asOfDate, setAsOfDate] = useState(new Date().toISOString().slice(0, 10));
  const [summary, setSummary] = useState<AgingSummary>({});
  const [bills, setBills] = useState<AgingBillRow[]>([]);
  const [bucketFilter, setBucketFilter] = useState('all');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const orgId = context?.organization?.id;

  const load = async (date = asOfDate) => {
    if (!orgId) return;
    setLoading(true);
    setError('');
    const res = await api.apAging(orgId, date, true);
    if (res.error) {
      setError(res.error || 'Unable to load AP aging.');
      setSummary({});
      setBills([]);
    } else {
      const payload = (res as any).data || {};
      setSummary(payload.summary || {});
      setBills(payload.bills || []);
      if (payload.as_of_date) setAsOfDate(payload.as_of_date);
    }
    setLoading(false);
  };

  useEffect(() => {
    void (async () => {
      setLoading(true);
      setError('');
      const ctx = await api.frontendContext();
      if (ctx.error) {
        setError(ctx.error || 'Unable to load organization context.');
        setLoading(false);
        return;
      }
      setContext((ctx as any).data);
    })();
  }, []);

  useEffect(() => {
    if (orgId) void load(asOfDate);
  }, [orgId]);

  const filteredBills = useMemo(() => {
    if (bucketFilter === 'all') return bills;
    return bills.filter((bill) => bill.bucket === bucketFilter);
  }, [bills, bucketFilter]);

  const totalOutstanding = useMemo(
    () => bills.reduce((sum, bill) => sum + Number(bill.outstanding || 0), 0),
    [bills]
  );

  return (
    <ClientShell
      title="AP Aging"
      eyebrow="Accounts payable"
      organization={context?.organization}
      role={context?.role}
      isPartner={context?.is_partner}
    >
      <div className="space-y-6">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <p className="text-sm text-slate-600">
              Outstanding vendor bills grouped by days past due. Only posted bills with unpaid or partial balances are included.
            </p>
          </div>
          <div className="flex flex-wrap items-end gap-3">
            <label className="block space-y-1 text-sm">
              <span className="font-medium text-slate-700">As of date</span>
              <Input type="date" value={asOfDate} onChange={(e) => setAsOfDate(e.target.value)} />
            </label>
            <Button onClick={() => void load(asOfDate)} disabled={loading || !orgId}>
              <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
              Refresh
            </Button>
            <WpLink
              to="/vendors"
              className="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark shadow-sm shadow-primary/10"
            >
              Vendors & Bills
            </WpLink>
          </div>
        </div>

        {error && (
          <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>
        )}

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
          <AgingCard label="Current" value={summary.current} />
          <AgingCard label="1–30 days" value={summary['30']} />
          <AgingCard label="31–60 days" value={summary['60']} />
          <AgingCard label="90+ days" value={summary['90_plus']} />
          <AgingCard label="Total outstanding" value={totalOutstanding} highlight />
        </div>

        <div className="rounded-2xl border border-border bg-white shadow-sm">
          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
            <div className="flex items-center gap-2">
              <BarChart3 className="h-5 w-5 text-accent" />
              <h2 className="text-lg font-semibold text-ink">Open bills</h2>
            </div>
            <select
              value={bucketFilter}
              onChange={(e) => setBucketFilter(e.target.value)}
              className="rounded-lg border border-border px-3 py-2 text-sm"
            >
              {Object.entries(BUCKET_LABELS).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
          </div>

          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="px-5 py-3">Bill</th>
                  <th className="px-5 py-3">Vendor</th>
                  <th className="px-5 py-3">Due date</th>
                  <th className="px-5 py-3">Days overdue</th>
                  <th className="px-5 py-3">Bucket</th>
                  <th className="px-5 py-3 text-right">Outstanding</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr><td colSpan={6} className="px-5 py-8 text-center text-slate-500">Loading AP aging…</td></tr>
                ) : filteredBills.length === 0 ? (
                  <tr><td colSpan={6} className="px-5 py-8 text-center text-slate-500">No open bills in this bucket.</td></tr>
                ) : filteredBills.map((bill) => (
                  <tr key={bill.bill_id} className="border-t border-border">
                    <td className="px-5 py-3 font-medium">
                      <WpLink to="/vendors" className="text-accent hover:underline">
                        {bill.bill_number || `#${bill.bill_id}`}
                      </WpLink>
                    </td>
                    <td className="px-5 py-3">{bill.vendor_name || `Vendor #${bill.vendor_id}`}</td>
                    <td className="px-5 py-3">{bill.due_date || '—'}</td>
                    <td className="px-5 py-3">{bill.days_overdue ?? 0}</td>
                    <td className="px-5 py-3">
                      <span className="badge border border-border bg-slate-50 text-slate-700">
                        {BUCKET_LABELS[bill.bucket || 'current'] || bill.bucket}
                      </span>
                    </td>
                    <td className="px-5 py-3 text-right font-medium">{money(bill.outstanding, bill.currency)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </ClientShell>
  );
}

function AgingCard({ label, value, highlight = false }: { label: string; value?: number; highlight?: boolean }) {
  return (
    <div className={`rounded-xl border p-4 ${highlight ? 'border-accent/30 bg-accent/5' : 'border-border bg-slate-50/70'}`}>
      <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
      <p className={`mt-2 text-2xl font-bold ${highlight ? 'text-accent' : 'text-ink'}`}>{money(value)}</p>
    </div>
  );
}

function money(value?: string | number, currency = 'USD') {
  const amount = Number(value || 0);
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount);
  } catch {
    return `${currency} ${amount.toFixed(2)}`;
  }
}
