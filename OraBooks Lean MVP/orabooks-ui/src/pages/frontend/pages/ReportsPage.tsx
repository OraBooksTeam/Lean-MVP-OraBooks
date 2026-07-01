import { useEffect, useMemo, useState, type ReactNode } from 'react';
import WpLink from '../components/WpLink';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { BarChart3, CalendarRange, Download, FileText, RefreshCw, TriangleAlert } from 'lucide-react';

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
  const [inventoryStatus, setInventoryStatus] = useState('all');
  const [groupBy, setGroupBy] = useState<'day' | 'week' | 'month'>('day');
  const [selectedCustomerId, setSelectedCustomerId] = useState('');
  const [selectedVendorId, setSelectedVendorId] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('');
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
  const canExport = Boolean(data?.permissions?.can_export_operational) || ['owner', 'admin', 'staff'].includes(data?.context?.role);
  const operationalTypes = data?.operational_types || [];
  const filterOptions = data?.operational_filters || {};
  const presets = data?.operational_presets || [];

  const reportTooltips: Record<string, string> = {
    ar_aging: 'AR aging by customer buckets (0-30, 31-60, 61-90, 90+).',
    ap_aging: 'AP aging by vendor buckets (0-30, 31-60, 61-90, 90+).',
    inventory_status: 'Inventory stock and low stock alerts from read models.',
    bank_reconciliation: 'Unmatched transaction summary per bank account.',
    sales_summary: 'Sales, returns, and net sales for selected period.',
    purchase_summary: 'Purchase totals by vendor for selected period.',
  };

  const activeTooltip = reportTooltips[operationalType] || 'Operational reports from read models.';

  const applyPreset = (preset: string) => {
    const now = new Date();
    const y = now.getFullYear();
    const m = `${now.getMonth() + 1}`.padStart(2, '0');
    const d = `${now.getDate()}`.padStart(2, '0');
    const today = `${y}-${m}-${d}`;

    if (preset === 'today') {
      setPeriodStart(today);
      setPeriodEnd(today);
      setAsOfDate(today);
      return;
    }

    if (preset === 'this_week') {
      const weekday = now.getDay();
      const mondayShift = weekday === 0 ? -6 : 1 - weekday;
      const start = new Date(now);
      start.setDate(now.getDate() + mondayShift);
      const end = new Date(start);
      end.setDate(start.getDate() + 6);
      setPeriodStart(formatDate(start));
      setPeriodEnd(formatDate(end));
      setAsOfDate(today);
      return;
    }

    if (preset === 'this_month') {
      setPeriodStart(`${y}-${m}-01`);
      setPeriodEnd(today);
      setAsOfDate(today);
    }
  };

  const queryParams = useMemo(() => {
    const params: Record<string, string | number> = {};
    if (asOfDate) params.as_of_date = asOfDate;
    if (periodStart) params.start_date = periodStart;
    if (periodEnd) params.end_date = periodEnd;

    if (operationalType === 'ar_aging' && selectedCustomerId) {
      params.customer_id = Number(selectedCustomerId);
    }

    if ((operationalType === 'ap_aging' || operationalType === 'purchase_summary') && selectedVendorId) {
      params.vendor_id = Number(selectedVendorId);
    }

    if ((operationalType === 'sales_summary') && selectedCustomerId) {
      params.customer_id = Number(selectedCustomerId);
    }

    if (operationalType === 'inventory_status') {
      if (selectedCategory) params.category = selectedCategory;
      if (inventoryStatus !== 'all') params.status = inventoryStatus;
    }

    if (operationalType === 'sales_summary' || operationalType === 'purchase_summary') {
      params.group_by = groupBy;
    }

    return params;
  }, [asOfDate, periodStart, periodEnd, operationalType, selectedCustomerId, selectedVendorId, selectedCategory, inventoryStatus, groupBy]);

  const runOperational = async () => {
    if (!orgId) return;
    setGeneratingOperational(true);
    setOperationalResult(null);
    setError('');
    setSuccess('');
    const res = await api.generateOperationalReport(orgId, operationalType, queryParams);
    if (res.error) setError(res.error);
    else setOperationalResult((res as any).data);
    setGeneratingOperational(false);
  };

  const exportOperational = async (format: 'csv' | 'pdf') => {
    if (!orgId) return;
    setExportingOperational(format);
    setError('');
    setSuccess('');
    const res = await api.operationalReportExport(orgId, operationalType, format, queryParams);
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
        {loading && (
          <div className="rounded-xl border border-border bg-white p-4 text-sm text-slate-600">Loading reports dashboard...</div>
        )}

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
            <h2 className="font-bold text-ink">Operational Reports</h2>
            <p className="mt-1 text-sm text-slate-600">📊 Operational reports – near real-time from read models.</p>

            <div className="mt-4 flex flex-wrap gap-2">
              {operationalTypes.map((item: any) => {
                const active = item.id === operationalType;
                return (
                  <button
                    key={item.id}
                    type="button"
                    title={reportTooltips[item.id] || item.label}
                    onClick={() => {
                      setOperationalType(item.id);
                      setOperationalResult(null);
                    }}
                    className={`rounded-lg border px-3 py-1.5 text-sm font-semibold transition ${active ? 'border-primary bg-primary/10 text-primary-dark' : 'border-border bg-white text-slate-700 hover:bg-slate-50'}`}
                  >
                    {item.label}
                  </button>
                );
              })}
            </div>

            <div className="mt-4 grid gap-3 lg:grid-cols-4">
              <Field label="From" hint='📅 Select period.'>
                <input type="date" className={fieldClass} value={periodStart} onChange={(e) => setPeriodStart(e.target.value)} />
              </Field>
              <Field label="To" hint='📅 Select period.'>
                <input type="date" className={fieldClass} value={periodEnd} onChange={(e) => setPeriodEnd(e.target.value)} />
              </Field>
              <Field label="As of date" hint='📅 Select period.'>
                <input type="date" className={fieldClass} value={asOfDate} onChange={(e) => setAsOfDate(e.target.value)} />
              </Field>
              <Field label="Presets">
                <div className="flex h-full flex-wrap gap-2 rounded-lg border border-border bg-white p-2">
                  {presets.map((preset: any) => (
                    <button
                      key={preset.id}
                      type="button"
                      className="rounded border border-border px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                      onClick={() => applyPreset(preset.id)}
                    >
                      {preset.label}
                    </button>
                  ))}
                </div>
              </Field>
            </div>

            <div className="mt-4 grid gap-3 lg:grid-cols-3">
              {(operationalType === 'ar_aging' || operationalType === 'sales_summary') && (
                <Field label="Customer" hint='🔍 Filter by entity.'>
                  <select className={fieldClass} value={selectedCustomerId} onChange={(e) => setSelectedCustomerId(e.target.value)}>
                    <option value="">All customers</option>
                    {(filterOptions.customers || []).map((row: any) => (
                      <option key={row.id} value={row.id}>{row.label}</option>
                    ))}
                  </select>
                </Field>
              )}

              {(operationalType === 'ap_aging' || operationalType === 'purchase_summary') && (
                <Field label="Vendor" hint='🔍 Filter by entity.'>
                  <select className={fieldClass} value={selectedVendorId} onChange={(e) => setSelectedVendorId(e.target.value)}>
                    <option value="">All vendors</option>
                    {(filterOptions.vendors || []).map((row: any) => (
                      <option key={row.id} value={row.id}>{row.label}</option>
                    ))}
                  </select>
                </Field>
              )}

              {operationalType === 'inventory_status' && (
                <>
                  <Field label="Product Category" hint='🔍 Filter by entity.'>
                    <select className={fieldClass} value={selectedCategory} onChange={(e) => setSelectedCategory(e.target.value)}>
                      <option value="">All categories</option>
                      {(filterOptions.categories || []).map((category: string) => (
                        <option key={category} value={category}>{category}</option>
                      ))}
                    </select>
                  </Field>
                  <Field label="Stock Status">
                    <select className={fieldClass} value={inventoryStatus} onChange={(e) => setInventoryStatus(e.target.value)}>
                      <option value="all">All</option>
                      <option value="low">Low stock only</option>
                      <option value="ok">In stock</option>
                    </select>
                  </Field>
                </>
              )}

              {(operationalType === 'sales_summary' || operationalType === 'purchase_summary') && (
                <Field label="Group by">
                  <select className={fieldClass} value={groupBy} onChange={(e) => setGroupBy(e.target.value as 'day' | 'week' | 'month')}>
                    <option value="day">Day</option>
                    <option value="week">Week</option>
                    <option value="month">Month</option>
                  </select>
                </Field>
              )}
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-2 text-xs text-slate-500">
              <CalendarRange className="h-4 w-4" />
              <span>{activeTooltip}</span>
            </div>

            <div className="mt-4 flex flex-wrap gap-2">
              <Button onClick={runOperational} disabled={generatingOperational || !orgId}>
                {generatingOperational ? 'Generating...' : 'Generate Report'}
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

            {operationalResult && (
              <div className="mt-4 rounded-lg border border-border bg-slate-50/60 p-3 text-xs text-slate-600">
                📁 Export for daily review. Correlation: {operationalResult?.correlation_id || 'n/a'}
              </div>
            )}

            {operationalResult && <ReportOutput reportType={operationalType} payload={operationalResult} />}
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

function ReportOutput({ reportType, payload }: { reportType: string; payload: any }) {
  const report = payload?.data;

  if (reportType === 'inventory_status' && report?.products) {
    const rows = report.products as Array<any>;
    return (
      <div className="mt-5 overflow-hidden rounded-xl border border-border">
        <div className="border-b border-border bg-slate-50/70 px-4 py-2 text-sm font-bold text-ink">
          Inventory Status ({rows.length} items)
        </div>
        <div className="max-h-72 overflow-auto">
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-white text-xs uppercase text-slate-500">
                <th className="px-4 py-2 font-semibold">Product</th>
                <th className="px-4 py-2 font-semibold">SKU</th>
                <th className="px-4 py-2 text-right font-semibold">Stock</th>
                <th className="px-4 py-2 text-right font-semibold">Reorder Level</th>
                <th className="px-4 py-2 font-semibold">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {rows.map((row, idx) => (
                <tr key={`${row.product_id || idx}`}>
                  <td className="px-4 py-2 text-slate-700">{row.product_name || '—'}</td>
                  <td className="px-4 py-2 text-slate-700">{row.sku || '—'}</td>
                  <td className="px-4 py-2 text-right text-slate-700">{formatCell(row.current_stock)}</td>
                  <td className="px-4 py-2 text-right text-slate-700">{formatCell(row.reorder_level)}</td>
                  <td className="px-4 py-2">
                    {String(row.status) === 'low' ? (
                      <span className="inline-flex items-center gap-1 rounded border border-red-200 bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700" title='🔴 Low stock alert. Reorder soon.'>
                        <TriangleAlert className="h-3.5 w-3.5" />
                        Low Stock
                      </span>
                    ) : (
                      <span className="inline-flex rounded border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                        OK
                      </span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    );
  }

  if ((reportType === 'ar_aging' || reportType === 'ap_aging') && Array.isArray(reportType === 'ap_aging' ? report?.rows : report)) {
    const rows = (reportType === 'ap_aging' ? report.rows : report) as Array<any>;
    const entityKey = reportType === 'ap_aging' ? 'vendor_id' : 'customer_id';
    return (
      <div className="mt-5 overflow-hidden rounded-xl border border-border">
        <div className="border-b border-border bg-slate-50/70 px-4 py-2 text-sm font-bold text-ink">
          {reportType === 'ap_aging' ? 'AP Aging' : 'AR Aging'} ({rows.length} entities)
        </div>
        <div className="max-h-72 overflow-auto">
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-white text-xs uppercase text-slate-500">
                <th className="px-4 py-2 font-semibold">{reportType === 'ap_aging' ? 'Vendor' : 'Customer'}</th>
                <th className="px-4 py-2 text-right font-semibold">0-30</th>
                <th className="px-4 py-2 text-right font-semibold">31-60</th>
                <th className="px-4 py-2 text-right font-semibold">61-90</th>
                <th className="px-4 py-2 text-right font-semibold">90+</th>
                <th className="px-4 py-2 text-right font-semibold">Total Due</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {rows.map((row, idx) => (
                <tr key={`${row[entityKey] || idx}`}>
                  <td className="px-4 py-2 text-slate-700">#{row[entityKey] || '—'}</td>
                  <td className="px-4 py-2 text-right text-slate-700">{formatCell(row['30'] || row.current)}</td>
                  <td className="px-4 py-2 text-right text-slate-700">{formatCell(row['60'])}</td>
                  <td className="px-4 py-2 text-right text-slate-700">{formatCell(row['90_plus'])}</td>
                  <td className="px-4 py-2 text-right text-slate-700">{formatCell(row['90_plus'])}</td>
                  <td className="px-4 py-2 text-right font-semibold text-slate-800">{formatCell(row.total_due)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    );
  }

  if (Array.isArray(report) && report.length > 0) {
    const sample = report[0];
    const columns = typeof sample === 'object' ? Object.keys(sample) : ['value'];

    return (
      <div className="mt-5 overflow-hidden rounded-xl border border-border">
        <div className="border-b border-border bg-slate-50/70 px-4 py-2 text-sm font-bold text-ink">Operational Result</div>
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
        <p className="text-sm font-bold text-ink">Operational Result</p>
        <pre className="mt-3 max-h-64 overflow-auto text-xs text-slate-700">{JSON.stringify(report, null, 2)}</pre>
      </div>
    );
  }

  return (
    <div className="mt-5 rounded-xl border border-border bg-slate-50/70 p-4 text-sm text-slate-600">
      No rows returned for this period.
    </div>
  );
}

function Field({ label, children, hint = '' }: { label: string; children: ReactNode; hint?: string }) {
  return (
    <label className="block">
      <span className="text-xs font-semibold uppercase text-slate-500">{label}</span>
      <div className="mt-1">{children}</div>
      {hint ? <span className="mt-1 block text-[11px] text-slate-500">{hint}</span> : null}
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

function formatDate(date: Date) {
  const y = date.getFullYear();
  const m = `${date.getMonth() + 1}`.padStart(2, '0');
  const d = `${date.getDate()}`.padStart(2, '0');
  return `${y}-${m}-${d}`;
}
