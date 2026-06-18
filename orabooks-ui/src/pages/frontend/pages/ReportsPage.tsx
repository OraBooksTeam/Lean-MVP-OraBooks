import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { BarChart3, RefreshCw } from 'lucide-react';

const FINANCIAL_DEFAULT = 'profit_loss';
const OPERATIONAL_DEFAULT = 'ar_aging';

export default function ReportsPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [financialType, setFinancialType] = useState(FINANCIAL_DEFAULT);
  const [operationalType, setOperationalType] = useState(OPERATIONAL_DEFAULT);
  const [periodStart, setPeriodStart] = useState('');
  const [periodEnd, setPeriodEnd] = useState('');
  const [asOfDate, setAsOfDate] = useState('');
  const [financialResult, setFinancialResult] = useState<any>(null);
  const [operationalResult, setOperationalResult] = useState<any>(null);
  const [generatingFinancial, setGeneratingFinancial] = useState(false);
  const [generatingOperational, setGeneratingOperational] = useState(false);

  const load = async () => {
    setLoading(true);
    setError('');
    const res = await api.reportsDashboard();
    if (res.error) {
      setError(res.error || 'Unable to load reports.');
    } else {
      const payload = (res as any).data;
      setData(payload);
      setPeriodStart(payload?.period?.start || '');
      setPeriodEnd(payload?.period?.end || '');
      setAsOfDate(payload?.period?.as_of_date || '');
    }
    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const orgId = data?.context?.organization?.id;

  const runFinancial = async () => {
    if (!orgId) return;
    setGeneratingFinancial(true);
    setFinancialResult(null);
    const res = await api.generateFinancialReport(orgId, financialType, periodStart, periodEnd);
    if (res.error) setError(res.error);
    else setFinancialResult((res as any).data);
    setGeneratingFinancial(false);
  };

  const runOperational = async () => {
    if (!orgId) return;
    setGeneratingOperational(true);
    setOperationalResult(null);
    const res = await api.generateOperationalReport(orgId, operationalType, {
      as_of_date: asOfDate,
      start_date: periodStart,
      end_date: periodEnd,
    });
    if (res.error) setError(res.error);
    else setOperationalResult((res as any).data);
    setGeneratingOperational(false);
  };

  const fin = data?.financial_preview;
  const op = data?.operational_preview;

  return (
    <ClientShell title="Reports" eyebrow="Financial & operational" organization={data?.context?.organization}>
      <div className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Metric label="Net Income (MTD)" value={money(fin?.net_income)} />
          <Metric label="Revenue (MTD)" value={money(fin?.total_revenue)} />
          <Metric label="Net Sales (MTD)" value={money(op?.net_sales_mtd)} />
          <Metric label="AR Customers" value={op?.ar_customers ?? 0} />
        </div>

        <div className="flex justify-end">
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {error && <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>}

        <div className="grid gap-5 xl:grid-cols-2">
          <section className="glass-panel p-5">
            <h2 className="font-bold text-ink">Financial Reports (SL-074)</h2>
            <p className="mt-1 text-sm text-slate-600">P&amp;L, balance sheet, cash flow, trial balance, and equity changes.</p>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              <Field label="Report">
                <select className="input-field" value={financialType} onChange={(e) => setFinancialType(e.target.value)}>
                  {(data?.financial_types || []).map((item: any) => (
                    <option key={item.id} value={item.id}>{item.label}</option>
                  ))}
                </select>
              </Field>
              <Field label="Period start">
                <input type="date" className="input-field" value={periodStart} onChange={(e) => setPeriodStart(e.target.value)} />
              </Field>
              <Field label="Period end">
                <input type="date" className="input-field" value={periodEnd} onChange={(e) => setPeriodEnd(e.target.value)} />
              </Field>
            </div>
            <div className="mt-4">
              <Button onClick={runFinancial} disabled={generatingFinancial || !orgId}>
                {generatingFinancial ? 'Generating...' : 'Generate Financial Report'}
              </Button>
            </div>
            {financialResult && <ReportOutput title="Financial result" payload={financialResult} kind="financial" />}
          </section>

          <section className="glass-panel p-5">
            <h2 className="font-bold text-ink">Operational Reports (SL-075)</h2>
            <p className="mt-1 text-sm text-slate-600">AR/AP aging, inventory, bank reconciliation, sales and purchase summaries.</p>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              <Field label="Report">
                <select className="input-field" value={operationalType} onChange={(e) => setOperationalType(e.target.value)}>
                  {(data?.operational_types || []).map((item: any) => (
                    <option key={item.id} value={item.id}>{item.label}</option>
                  ))}
                </select>
              </Field>
              <Field label="As of date">
                <input type="date" className="input-field" value={asOfDate} onChange={(e) => setAsOfDate(e.target.value)} />
              </Field>
            </div>
            <div className="mt-4">
              <Button onClick={runOperational} disabled={generatingOperational || !orgId}>
                {generatingOperational ? 'Generating...' : 'Generate Operational Report'}
              </Button>
            </div>
            {operationalResult && <ReportOutput title="Operational result" payload={operationalResult} kind="operational" />}
          </section>
        </div>

        <div className="glass-panel overflow-hidden">
          <div className="border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Recent Financial Snapshots</h2>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Type</th>
                <th className="px-5 py-3 font-semibold">Period</th>
                <th className="px-5 py-3 font-semibold">Frozen</th>
                <th className="px-5 py-3 font-semibold">Created</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={4} className="px-5 py-8 text-center text-slate-500">Loading snapshots...</td></tr>
              ) : (data?.recent_snapshots || []).length === 0 ? (
                <tr>
                  <td colSpan={4} className="px-5 py-10 text-center">
                    <BarChart3 className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No report snapshots yet. Generate a financial report to create one.</p>
                  </td>
                </tr>
              ) : data.recent_snapshots.map((snap: any) => (
                <tr key={snap.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3 font-semibold text-ink">{snap.report_type}</td>
                  <td className="px-5 py-3 text-slate-600">{snap.period_start} → {snap.period_end}</td>
                  <td className="px-5 py-3">{Number(snap.frozen) === 1 ? 'Yes' : 'No'}</td>
                  <td className="px-5 py-3 text-slate-600">{snap.created_at || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </ClientShell>
  );
}

function ReportOutput({ title, payload, kind }: { title: string; payload: any; kind: 'financial' | 'operational' }) {
  const report = kind === 'financial' ? payload?.report : payload?.data;

  if (kind === 'financial' && payload?.report_type === 'profit_loss') {
    return (
      <div className="mt-5 rounded-xl border border-border bg-slate-50/70 p-4">
        <p className="text-sm font-bold text-ink">{title}</p>
        <div className="mt-3 grid gap-2 sm:grid-cols-3">
          <SummaryLine label="Revenue" value={money(payload.report?.total_revenue)} />
          <SummaryLine label="Expenses" value={money(payload.report?.total_expenses)} />
          <SummaryLine label="Net income" value={money(payload.report?.net_income)} bold />
        </div>
      </div>
    );
  }

  if (Array.isArray(report) && report.length > 0) {
    const sample = report[0];
    const columns = typeof sample === 'object' ? Object.keys(sample) : ['value'];

    return (
      <div className="mt-5 overflow-hidden rounded-xl border border-border">
        <div className="border-b border-border bg-slate-50/70 px-4 py-2 text-sm font-bold text-ink">{title}</div>
        <div className="max-h-72 overflow-auto">
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-white text-xs uppercase text-slate-500">
                {columns.map((col) => (
                  <th key={col} className="px-4 py-2 font-semibold">{col}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {report.slice(0, 25).map((row: any, idx: number) => (
                <tr key={idx}>
                  {columns.map((col) => (
                    <td key={col} className="px-4 py-2 text-slate-700">{formatCell(row?.[col])}</td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    );
  }

  if (report && typeof report === 'object') {
    return (
      <div className="mt-5 rounded-xl border border-border bg-slate-50/70 p-4">
        <p className="text-sm font-bold text-ink">{title}</p>
        <pre className="mt-3 max-h-64 overflow-auto text-xs text-slate-700">{JSON.stringify(report, null, 2)}</pre>
      </div>
    );
  }

  return (
    <div className="mt-5 rounded-xl border border-border bg-slate-50/70 p-4 text-sm text-slate-600">
      {title}: no rows returned for this period.
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="block">
      <span className="text-xs font-semibold uppercase text-slate-500">{label}</span>
      <div className="mt-1">{children}</div>
    </label>
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

function SummaryLine({ label, value, bold = false }: { label: string; value: string; bold?: boolean }) {
  return (
    <div>
      <p className="text-xs uppercase text-slate-500">{label}</p>
      <p className={`text-lg ${bold ? 'font-black text-ink' : 'font-semibold text-slate-700'}`}>{value}</p>
    </div>
  );
}

function formatCell(value: unknown) {
  if (value == null || value === '') return '—';
  if (typeof value === 'number') return value.toLocaleString(undefined, { maximumFractionDigits: 2 });
  return String(value);
}

function money(value?: string | number) {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(Number(value || 0));
}
