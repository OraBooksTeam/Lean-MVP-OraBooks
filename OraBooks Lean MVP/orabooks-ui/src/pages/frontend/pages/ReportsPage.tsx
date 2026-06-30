import { useEffect, useState, type ReactNode } from 'react';
import WpLink from '../components/WpLink';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { BarChart3, Download, FileText, PenLine, RefreshCw } from 'lucide-react';

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
                {canSign && <th className="px-5 py-3 font-semibold">Actions</th>}
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={canSign ? 5 : 4} className="px-5 py-8 text-center text-slate-500">Loading snapshots...</td></tr>
              ) : (data?.recent_snapshots || []).length === 0 ? (
                <tr>
                  <td colSpan={canSign ? 5 : 4} className="px-5 py-10 text-center">
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
                  {canSign && (
                    <td className="px-5 py-3">
                      <button
                        type="button"
                        onClick={() => openSignModal(snap.id, `${snap.report_type} (${snap.period_start} → ${snap.period_end})`)}
                        className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:text-primary-dark"
                      >
                        <PenLine className="h-4 w-4" />
                        Sign
                      </button>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {signTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-md rounded-2xl border border-border bg-white p-6 shadow-xl">
            <h3 className="text-lg font-bold text-ink">Sign financial report</h3>
            <p className="mt-1 text-sm text-slate-600">{signTarget.label}</p>
            <p className="mt-2 text-xs text-slate-500">Snapshot #{signTarget.id}</p>
            <div className="mt-4">
              <Field label="Board approval reference (optional)">
                <input
                  type="text"
                  className={fieldClass}
                  value={boardRef}
                  onChange={(e) => setBoardRef(e.target.value)}
                  placeholder="e.g. Board resolution 2026-04"
                />
              </Field>
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setSignTarget(null)} disabled={signing}>
                Cancel
              </Button>
              <Button onClick={submitSign} disabled={signing}>
                {signing ? 'Signing...' : 'Sign report'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </ClientShell>
  );
}

function ReportOutput({ title, payload, kind }: { title: string; payload: any; kind: 'financial' | 'operational' }) {
  const report = kind === 'financial' ? payload?.report : payload?.data;

  if (kind === 'financial' && payload?.report_type === 'profit_loss') {
    return (
      <div className="mt-5 rounded-xl border border-border bg-slate-50/70 p-4">
        <p className="text-sm font-bold text-ink">{title}</p>
        <div className="mt-3 grid gap-2 sm:grid-cols-3 lg:grid-cols-6">
          <SummaryLine label="Revenue" value={money(payload.report?.total_revenue)} />
          <SummaryLine label="COGS" value={money(payload.report?.total_cogs)} />
          <SummaryLine label="Gross profit" value={money(payload.report?.gross_profit)} />
          <SummaryLine label="Operating exp." value={money(payload.report?.total_operating_expenses)} />
          <SummaryLine label="Operating income" value={money(payload.report?.operating_income)} />
          <SummaryLine label="Net income" value={money(payload.report?.net_income)} bold />
        </div>
      </div>
    );
  }

  if (kind === 'financial' && payload?.report_type === 'trial_balance') {
    const report = payload.report || {};
    return (
      <div className="mt-5 rounded-xl border border-border bg-slate-50/70 p-4">
        <p className="text-sm font-bold text-ink">{title}</p>
        <p className="mt-1 text-xs text-slate-500">Closing balance as of {report.period_end || payload.period_end}</p>
        <div className={`mt-3 rounded-lg border px-3 py-2 text-sm ${report.balanced ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-900'}`}>
          {report.balance_message || (report.balanced ? 'Balanced' : 'Unbalanced')}
        </div>
        <div className="mt-3 grid gap-2 sm:grid-cols-3">
          <SummaryLine label="Total debits" value={money(report.total_debits)} />
          <SummaryLine label="Total credits" value={money(report.total_credits)} />
          <SummaryLine label="Difference" value={money(report.difference)} bold />
        </div>
        {(report.accounts || []).length > 0 && (
          <div className="mt-4 overflow-auto">
            <table className="min-w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border text-xs uppercase text-slate-500">
                  <th className="py-2 pr-3">Code</th>
                  <th className="py-2 pr-3">Account</th>
                  <th className="py-2 pr-3">Opening</th>
                  <th className="py-2 pr-3">Closing</th>
                  <th className="py-2 pr-3">Debit</th>
                  <th className="py-2">Credit</th>
                </tr>
              </thead>
              <tbody>
                {report.accounts.slice(0, 30).map((row: any, idx: number) => (
                  <tr key={idx} className="border-b border-border/60">
                    <td className="py-2 pr-3">{row.code}</td>
                    <td className="py-2 pr-3">{row.name}</td>
                    <td className="py-2 pr-3">{formatCell(row.opening_balance)}</td>
                    <td className="py-2 pr-3">{formatCell(row.closing_balance)}</td>
                    <td className="py-2 pr-3">{formatCell(row.debit)}</td>
                    <td className="py-2">{formatCell(row.credit)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    );
  }

  if (kind === 'financial' && payload?.report_type === 'balance_sheet') {
    const report = payload.report || {};
    return (
      <div className="mt-5 rounded-xl border border-border bg-slate-50/70 p-4">
        <p className="text-sm font-bold text-ink">{title}</p>
        <div className={`mt-3 rounded-lg border px-3 py-2 text-sm ${report.balanced ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-900'}`}>
          {report.balance_message || (report.balanced ? 'Balanced' : 'Unbalanced')}
        </div>
        <div className="mt-3 grid gap-2 sm:grid-cols-3">
          <SummaryLine label="Total assets" value={money(report.total_assets)} />
          <SummaryLine label="Total liabilities" value={money(report.total_liabilities)} />
          <SummaryLine label="Total equity" value={money(report.total_equity)} bold />
        </div>
      </div>
    );
  }

  if (kind === 'financial' && payload?.report_type === 'general_ledger') {
    const report = payload.report || {};
    return (
      <div className="mt-5 rounded-xl border border-border bg-slate-50/70 p-4">
        <p className="text-sm font-bold text-ink">{title}</p>
        <div className="mt-3 grid gap-2 sm:grid-cols-3">
          <SummaryLine label="Entries" value={report.entry_count ?? 0} />
          <SummaryLine label="Total debits" value={money(report.total_debits)} />
          <SummaryLine label="Total credits" value={money(report.total_credits)} bold />
        </div>
        {(report.rows || []).length > 0 && (
          <div className="mt-4 overflow-auto">
            <table className="min-w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border text-xs uppercase text-slate-500">
                  <th className="py-2 pr-3">Date</th>
                  <th className="py-2 pr-3">Journal</th>
                  <th className="py-2 pr-3">Account</th>
                  <th className="py-2 pr-3">Description</th>
                  <th className="py-2 pr-3">Debit</th>
                  <th className="py-2 pr-3">Credit</th>
                  <th className="py-2">Running</th>
                </tr>
              </thead>
              <tbody>
                {report.rows.slice(0, 50).map((row: any, idx: number) => (
                  <tr key={idx} className="border-b border-border/60">
                    <td className="py-2 pr-3">{row.transaction_date || '—'}</td>
                    <td className="py-2 pr-3">{row.journal_number || '—'}</td>
                    <td className="py-2 pr-3">{row.account_code} {row.account_name ? `- ${row.account_name}` : ''}</td>
                    <td className="py-2 pr-3">{row.description || '—'}</td>
                    <td className="py-2 pr-3">{formatCell(row.debit)}</td>
                    <td className="py-2 pr-3">{formatCell(row.credit)}</td>
                    <td className="py-2">{formatCell(row.running_balance)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
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
