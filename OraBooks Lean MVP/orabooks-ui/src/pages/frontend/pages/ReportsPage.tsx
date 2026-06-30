import { useEffect, useState, type ReactNode } from 'react';
import WpLink from '../components/WpLink';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { BarChart3, Download, FileText, RefreshCw } from 'lucide-react';

const OPERATIONAL_DEFAULT = 'ar_aging';

const fieldClass = 'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';

export default function ReportsPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [operationalType, setOperationalType] = useState(OPERATIONAL_DEFAULT);
  const [periodStart, setPeriodStart] = useState('');
  const [periodEnd, setPeriodEnd] = useState('');
  const [asOfDate, setAsOfDate] = useState('');
  const [operationalResult, setOperationalResult] = useState<any>(null);
  const [generatingOperational, setGeneratingOperational] = useState(false);
  const [exportingOperational, setExportingOperational] = useState<'csv' | 'pdf' | null>(null);

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
  const canExport = ['owner', 'admin', 'staff'].includes(data?.context?.role);

  const runOperational = async () => {
    if (!orgId) return;
    setGeneratingOperational(true);
    setOperationalResult(null);
    setError('');
    setSuccess('');
    const res = await api.generateOperationalReport(orgId, operationalType, {
      as_of_date: asOfDate,
      start_date: periodStart,
      end_date: periodEnd,
    });
    if (res.error) setError(res.error);
    else setOperationalResult((res as any).data);
    setGeneratingOperational(false);
  };

  const exportOperational = async (format: 'csv' | 'pdf') => {
    if (!orgId) return;
    setExportingOperational(format);
    setError('');
    setSuccess('');
    const res = await api.operationalReportExport(orgId, operationalType, format, {
      as_of_date: asOfDate,
      start_date: periodStart,
      end_date: periodEnd,
    });
    if (res.error) setError(res.error);
    else {
      const exportId = (res as any).data?.id;
      setSuccess(
        exportId
          ? `Operational report export queued (#${exportId}). Check My Exports when ready.`
          : 'Operational report export queued. Check My Exports when ready.',
      );
    }
    setExportingOperational(null);
  };

  const fin = data?.financial_preview;
  const op = data?.operational_preview;

  return (
    <ClientShell title="Reports" eyebrow="Operational reporting (SL-075)" organization={data?.context?.organization}>
      <div className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Metric label="Net Income (MTD)" value={money(fin?.net_income)} />
          <Metric label="Revenue (MTD)" value={money(fin?.total_revenue)} />
          <Metric label="Net Sales (MTD)" value={money(op?.net_sales_mtd)} />
          <Metric label="AR Customers" value={op?.ar_customers ?? 0} />
        </div>

        <div className="flex flex-wrap items-center justify-end gap-2">
          <WpLink
            to="/financial-reports"
            className="inline-flex items-center gap-1.5 rounded-lg border border-primary/30 bg-primary/5 px-3 py-2 text-sm font-medium text-primary shadow-sm transition hover:bg-primary/10"
          >
            <BarChart3 className="h-4 w-4" />
            Financial Reports
          </WpLink>
          <WpLink
            to="/my-exports"
            className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
          >
            <Download className="h-4 w-4" />
            My Exports
          </WpLink>
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {error && <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>}
        {success && <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">{success}</div>}

        <div className="grid gap-5 xl:grid-cols-1">
          <section className="glass-panel p-5">
            <h2 className="font-bold text-ink">Operational Reports (SL-075)</h2>
            <p className="mt-1 text-sm text-slate-600">AR/AP aging, inventory, bank reconciliation, sales and purchase summaries.</p>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              <Field label="Report">
                <select className={fieldClass} value={operationalType} onChange={(e) => setOperationalType(e.target.value)}>
                  {(data?.operational_types || []).map((item: any) => (
                    <option key={item.id} value={item.id}>{item.label}</option>
                  ))}
                </select>
              </Field>
              <Field label="As of date">
                <input type="date" className={fieldClass} value={asOfDate} onChange={(e) => setAsOfDate(e.target.value)} />
              </Field>
            </div>
            <div className="mt-4 flex flex-wrap gap-2">
              <Button onClick={runOperational} disabled={generatingOperational || !orgId}>
                {generatingOperational ? 'Generating...' : 'Generate Operational Report'}
              </Button>
              {canExport && (
                <>
                  <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => exportOperational('csv')}
                    disabled={!!exportingOperational || !orgId}
                  >
                    <FileText className="h-4 w-4" />
                    {exportingOperational === 'csv' ? 'Exporting...' : 'Export CSV'}
                  </Button>
                  <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => exportOperational('pdf')}
                    disabled={!!exportingOperational || !orgId}
                  >
                    <Download className="h-4 w-4" />
                    {exportingOperational === 'pdf' ? 'Exporting...' : 'Export PDF'}
                  </Button>
                </>
              )}
            </div>
            {operationalResult && <ReportOutput title="Operational result" payload={operationalResult} />}
          </section>
        </div>

        <div className="glass-panel p-5">
          <h2 className="font-bold text-ink">Core financial statements</h2>
          <p className="mt-1 text-sm text-slate-600">
            P&amp;L, Balance Sheet, Cash Flow, Trial Balance, General Ledger, and Changes in Equity live on the dedicated financial reports page.
          </p>
          <WpLink to="/financial-reports" className="mt-3 inline-flex">
            <Button size="sm">Open Financial Reports</Button>
          </WpLink>
        </div>
      </div>
    </ClientShell>
  );
}

function ReportOutput({ title, payload }: { title: string; payload: any }) {
  const report = payload?.data;

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

function Field({ label, children }: { label: string; children: ReactNode }) {
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

function formatCell(value: unknown) {
  if (value == null || value === '') return '—';
  if (typeof value === 'number') return value.toLocaleString(undefined, { maximumFractionDigits: 2 });
  return String(value);
}

function money(value?: string | number) {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(Number(value || 0));
}
