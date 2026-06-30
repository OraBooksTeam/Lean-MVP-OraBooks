import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import WpLink from '../components/WpLink';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import {
  BarChart3,
  Download,
  FileText,
  Info,
  Lock,
  PenLine,
  RefreshCw,
  Shield,
  Archive,
} from 'lucide-react';

const REPORT_TABS = [
  { id: 'profit_loss', label: 'P&L' },
  { id: 'balance_sheet', label: 'Balance Sheet' },
  { id: 'cash_flow', label: 'Cash Flow' },
  { id: 'trial_balance', label: 'Trial Balance' },
  { id: 'general_ledger', label: 'General Ledger' },
  { id: 'changes_equity', label: 'Changes in Equity' },
] as const;

const fieldClass =
  'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';

type ReportTabId = (typeof REPORT_TABS)[number]['id'];

export default function FinancialReportsPage() {
  const [context, setContext] = useState<any>(null);
  const [dash, setDash] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [activeTab, setActiveTab] = useState<ReportTabId>('profit_loss');
  const [periodStart, setPeriodStart] = useState('');
  const [periodEnd, setPeriodEnd] = useState('');
  const [compareWith, setCompareWith] = useState('');
  const [accountType, setAccountType] = useState('');
  const [accountId, setAccountId] = useState('');
  const [cashFlowMethod, setCashFlowMethod] = useState('indirect');
  const [result, setResult] = useState<any>(null);
  const [generating, setGenerating] = useState(false);
  const [exporting, setExporting] = useState<'csv' | 'pdf' | null>(null);
  const [signTarget, setSignTarget] = useState<{ id: number; label: string } | null>(null);
  const [boardRef, setBoardRef] = useState('');
  const [signing, setSigning] = useState(false);
  const [showAdmin, setShowAdmin] = useState(false);
  const [savingConfig, setSavingConfig] = useState(false);
  const [replaying, setReplaying] = useState(false);
  const [retentionDays, setRetentionDays] = useState(365);
  const [encryptSnapshots, setEncryptSnapshots] = useState(false);
  const [replayBatch, setReplayBatch] = useState(1000);
  const [replayThrottle, setReplayThrottle] = useState(100);
  const [replayProjection, setReplayProjection] = useState('ledger_summary');
  const [replayUseQueue, setReplayUseQueue] = useState(false);

  const orgId = context?.context?.organization?.id as number | undefined;
  const permissions = dash?.permissions || {};
  const reportConfig = dash?.report_config || {};

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    const ctxRes = await api.reportsDashboard();
    if (ctxRes.error) {
      setError(ctxRes.error || 'Unable to load reports.');
      setLoading(false);
      return;
    }
    const ctxPayload = (ctxRes as any).data;
    setContext(ctxPayload);
    const oid = ctxPayload?.context?.organization?.id;
    if (!oid) {
      setLoading(false);
      return;
    }
    setPeriodStart(ctxPayload?.period?.start || '');
    setPeriodEnd(ctxPayload?.period?.end || '');

    const dashRes = await api.financialReportsDashboard(oid);
    if (dashRes.error) {
      setError(dashRes.error);
    } else {
      const dashPayload = (dashRes as any).data;
      setDash(dashPayload);
      setCashFlowMethod(dashPayload?.report_config?.cash_flow_method || 'indirect');
      setRetentionDays(dashPayload?.report_config?.snapshot_retention_days || 365);
      setEncryptSnapshots(!!dashPayload?.report_config?.encrypt_snapshots);
    }
    setLoading(false);
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const generateOptions = useMemo(
    () => ({
      compare_with: compareWith,
      account_type: accountType,
      account_id: accountId ? Number(accountId) : 0,
      method: cashFlowMethod,
    }),
    [compareWith, accountType, accountId, cashFlowMethod],
  );

  const runGenerate = async () => {
    if (!orgId) return;
    setGenerating(true);
    setResult(null);
    setError('');
    setSuccess('');
    const res = await api.generateFinancialReport(orgId, activeTab, periodStart, periodEnd, generateOptions);
    if (res.error) setError(res.error);
    else {
      setResult((res as any).data);
      await load();
    }
    setGenerating(false);
  };

  const exportReport = async (format: 'csv' | 'pdf') => {
    if (!orgId) return;
    setExporting(format);
    setError('');
    setSuccess('');
    const res = await api.financialReportExport(orgId, activeTab, format, periodStart, periodEnd);
    if (res.error) setError(res.error);
    else {
      const exportId = (res as any).data?.id;
      setSuccess(
        exportId
          ? `Export queued (#${exportId}). Check My Exports when ready.`
          : 'Export queued. Check My Exports when ready.',
      );
    }
    setExporting(null);
  };

  const submitSign = async () => {
    if (!orgId || !signTarget) return;
    setSigning(true);
    setError('');
    const res = await api.financialReportSign(orgId, signTarget.id, boardRef.trim());
    if (res.error) setError(res.error);
    else {
      setSuccess(`Report signed (${(res as any).data?.watermark || 'APPROVED'}).`);
      setSignTarget(null);
      await load();
    }
    setSigning(false);
  };

  const saveConfig = async () => {
    if (!orgId) return;
    setSavingConfig(true);
    setError('');
    const res = await api.financialReportConfigSave(orgId, {
      cash_flow_method: cashFlowMethod,
      snapshot_retention_days: retentionDays,
      encrypt_snapshots: encryptSnapshots,
    });
    if (res.error) setError(res.error);
    else {
      setSuccess('Report settings saved.');
      await load();
    }
    setSavingConfig(false);
  };

  const triggerReplay = async (queued = false) => {
    if (!orgId) return;
    setReplaying(true);
    setError('');
    const fn = queued ? api.financialReportRebuild : api.financialReportReplay;
    const res = await fn(orgId, replayProjection, {
      batch_size: replayBatch,
      throttle_per_sec: replayThrottle,
      period_start: periodStart,
      period_end: periodEnd,
      use_queue: queued ? 1 : 0,
    });
    if (res.error) setError(res.error);
    else setSuccess(queued ? 'Replay queued.' : 'Replay completed.');
    setReplaying(false);
  };

  const activeTabLabel = REPORT_TABS.find((t) => t.id === activeTab)?.label || activeTab;

  return (
    <ClientShell
      title="Financial Reports"
      eyebrow="Core financial statements (SL-074)"
      organization={context?.context?.organization}
    >
      <div className="space-y-5">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="flex flex-wrap gap-2 text-xs text-slate-600">
            <TooltipBadge icon={<Info className="h-3.5 w-3.5" />} text="Reports from read models – near real-time." />
            <TooltipBadge icon={<Lock className="h-3.5 w-3.5" />} text="Hard-closed periods show frozen snapshots." />
            <TooltipBadge icon={<Archive className="h-3.5 w-3.5" />} text={`Snapshots archived after ${reportConfig.snapshot_retention_days ?? 365} days.`} />
            <TooltipBadge icon={<PenLine className="h-3.5 w-3.5" />} text="Sign report for regulatory submission." />
          </div>
          <div className="flex flex-wrap gap-2">
            <WpLink
              to="/my-exports"
              className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
            >
              <Download className="h-4 w-4" />
              My Exports
            </WpLink>
            <WpLink
              to="/reports"
              className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
            >
              Operational Reports
            </WpLink>
            <Button onClick={load} variant="secondary" size="sm">
              <RefreshCw className="h-4 w-4" />
              Refresh
            </Button>
          </div>
        </div>

        {error && (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>
        )}
        {success && (
          <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">
            {success}
          </div>
        )}

        <div className="glass-panel overflow-hidden">
          <div className="flex flex-wrap gap-1 border-b border-border bg-slate-50/70 p-2">
            {REPORT_TABS.map((tab) => (
              <button
                key={tab.id}
                type="button"
                onClick={() => {
                  setActiveTab(tab.id);
                  setResult(null);
                }}
                className={`rounded-lg px-3 py-2 text-sm font-semibold transition ${
                  activeTab === tab.id
                    ? 'bg-white text-primary shadow-sm ring-1 ring-border'
                    : 'text-slate-600 hover:bg-white/70 hover:text-ink'
                }`}
              >
                {tab.label}
              </button>
            ))}
          </div>

          <div className="p-5">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <Field label="From" tooltip="Select period start date.">
                <input type="date" className={fieldClass} value={periodStart} onChange={(e) => setPeriodStart(e.target.value)} />
              </Field>
              <Field label="To" tooltip="Select period end date.">
                <input type="date" className={fieldClass} value={periodEnd} onChange={(e) => setPeriodEnd(e.target.value)} />
              </Field>
              <Field label="Compare with" tooltip="YoY, QoQ, rolling 12 months, or previous period.">
                <select className={fieldClass} value={compareWith} onChange={(e) => setCompareWith(e.target.value)}>
                  {(dash?.comparison_options || []).map((opt: any) => (
                    <option key={opt.id || 'none'} value={opt.id}>
                      {opt.label}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Account type" tooltip="Filter by account classification.">
                <select className={fieldClass} value={accountType} onChange={(e) => setAccountType(e.target.value)}>
                  {(dash?.account_types || []).map((opt: any) => (
                    <option key={opt.id || 'all'} value={opt.id}>
                      {opt.label}
                    </option>
                  ))}
                </select>
              </Field>
              {(activeTab === 'general_ledger' || activeTab === 'cash_flow') && (
                <Field label="Account" tooltip="Filter to a specific GL account.">
                  <select className={fieldClass} value={accountId} onChange={(e) => setAccountId(e.target.value)}>
                    <option value="">All accounts</option>
                    {(dash?.accounts || []).map((acct: any) => (
                      <option key={acct.id} value={String(acct.id)}>
                        {acct.code} — {acct.name}
                      </option>
                    ))}
                  </select>
                </Field>
              )}
              {activeTab === 'cash_flow' && (
                <Field label="Cash flow method" tooltip="Indirect (default) or direct method.">
                  <select className={fieldClass} value={cashFlowMethod} onChange={(e) => setCashFlowMethod(e.target.value)}>
                    <option value="indirect">Indirect</option>
                    <option value="direct">Direct</option>
                  </select>
                </Field>
              )}
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-2">
              <Button onClick={runGenerate} disabled={generating || !orgId || loading}>
                {generating ? 'Generating...' : 'Generate Report'}
              </Button>
              {permissions.can_export && (
                <>
                  <Button variant="secondary" size="sm" onClick={() => exportReport('csv')} disabled={!!exporting || !orgId}>
                    <FileText className="h-4 w-4" />
                    {exporting === 'csv' ? 'Exporting...' : 'Export CSV'}
                  </Button>
                  <Button variant="secondary" size="sm" onClick={() => exportReport('pdf')} disabled={!!exporting || !orgId}>
                    <Download className="h-4 w-4" />
                    {exporting === 'pdf' ? 'Exporting...' : 'Export PDF'}
                  </Button>
                </>
              )}
              {result?.snapshot_id && permissions.can_sign && (
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={() =>
                    setSignTarget({
                      id: result.snapshot_id,
                      label: `${activeTabLabel} (${result.period_start} → ${result.period_end})`,
                    })
                  }
                >
                  <PenLine className="h-4 w-4" />
                  Sign Report
                </Button>
              )}
            </div>

            {result && (
              <div className="mt-5 space-y-3">
                <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                  <span>Snapshot #{result.snapshot_id}</span>
                  {result.correlation_id && <span title="Use this ID for support">ID: {result.correlation_id}</span>}
                  {result.frozen && <span className="badge border border-slate-200 bg-slate-50">Frozen</span>}
                  {result.from_cache && <span className="badge border border-blue-200 bg-blue-50 text-blue-800">Cached</span>}
                  {result.board_approved && (
                    <span className="badge border border-emerald-200 bg-emerald-50 text-emerald-800">Board Approved</span>
                  )}
                  {result.watermark && (
                    <span className="badge border border-amber-200 bg-amber-50 text-amber-900">{result.watermark}</span>
                  )}
                </div>
                <FinancialReportOutput payload={result} tab={activeTab} />
                {result.comparison?.report && (
                  <div className="rounded-xl border border-dashed border-border bg-white p-4">
                    <p className="text-sm font-bold text-ink">
                      Comparison ({result.comparison.period_start} → {result.comparison.period_end})
                    </p>
                    <FinancialReportOutput
                      payload={{
                        report_type: result.report_type,
                        report: result.comparison.report,
                        period_start: result.comparison.period_start,
                        period_end: result.comparison.period_end,
                      }}
                      tab={activeTab}
                    />
                  </div>
                )}
              </div>
            )}
          </div>
        </div>

        {(permissions.can_admin_replay || permissions.can_manage_config) && (
          <section className="glass-panel p-5">
            <button
              type="button"
              className="flex w-full items-center justify-between text-left"
              onClick={() => setShowAdmin((v) => !v)}
            >
              <div>
                <h2 className="font-bold text-ink">Admin Panel</h2>
                <p className="mt-1 text-sm text-slate-600">Snapshot retention, projector replay, and integrity checks.</p>
              </div>
              <Shield className="h-5 w-5 text-slate-400" />
            </button>

            {showAdmin && (
              <div className="mt-5 grid gap-5 lg:grid-cols-2">
                {permissions.can_manage_config && (
                  <div className="rounded-xl border border-border p-4">
                    <h3 className="font-semibold text-ink">Report settings</h3>
                    <p className="mt-1 text-xs text-slate-500">
                      Snapshots older than {retentionDays} days will be archived (non-frozen).
                    </p>
                    <div className="mt-3 grid gap-3">
                      <Field label="Snapshot retention (days)">
                        <input
                          type="number"
                          min={30}
                          max={3650}
                          className={fieldClass}
                          value={retentionDays}
                          onChange={(e) => setRetentionDays(Number(e.target.value) || 365)}
                        />
                      </Field>
                      <Field label="Cash flow default method">
                        <select className={fieldClass} value={cashFlowMethod} onChange={(e) => setCashFlowMethod(e.target.value)}>
                          <option value="indirect">Indirect</option>
                          <option value="direct">Direct</option>
                        </select>
                      </Field>
                      <label className="flex items-center gap-2 text-sm text-slate-700">
                        <input
                          type="checkbox"
                          checked={encryptSnapshots}
                          onChange={(e) => setEncryptSnapshots(e.target.checked)}
                        />
                        Encrypt snapshots (KMS)
                      </label>
                      <Button onClick={saveConfig} disabled={savingConfig}>
                        {savingConfig ? 'Saving...' : 'Save settings'}
                      </Button>
                    </div>
                  </div>
                )}

                {permissions.can_admin_replay && (
                  <div className="rounded-xl border border-border p-4">
                    <h3 className="font-semibold text-ink">Projector replay</h3>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                      <Field label="Projection">
                        <select className={fieldClass} value={replayProjection} onChange={(e) => setReplayProjection(e.target.value)}>
                          <option value="ledger_summary">ledger_summary</option>
                          <option value="ar_aging">ar_aging</option>
                          <option value="ap_aging">ap_aging</option>
                          <option value="inventory_valuation">inventory_valuation</option>
                        </select>
                      </Field>
                      <Field label="Batch size">
                        <input
                          type="number"
                          className={fieldClass}
                          value={replayBatch}
                          onChange={(e) => setReplayBatch(Number(e.target.value) || 1000)}
                        />
                      </Field>
                      <Field label="Throttle / sec">
                        <input
                          type="number"
                          className={fieldClass}
                          value={replayThrottle}
                          onChange={(e) => setReplayThrottle(Number(e.target.value) || 100)}
                        />
                      </Field>
                      <label className="flex items-center gap-2 self-end pb-2 text-sm text-slate-700">
                        <input type="checkbox" checked={replayUseQueue} onChange={(e) => setReplayUseQueue(e.target.checked)} />
                        Queue replay (low priority)
                      </label>
                    </div>
                    <div className="mt-3 flex flex-wrap gap-2">
                      <Button size="sm" onClick={() => triggerReplay(false)} disabled={replaying}>
                        {replaying ? 'Running...' : 'Run replay'}
                      </Button>
                      <Button size="sm" variant="secondary" onClick={() => triggerReplay(true)} disabled={replaying}>
                        Queue rebuild
                      </Button>
                    </div>
                  </div>
                )}

                <div className="rounded-xl border border-border p-4 lg:col-span-2">
                  <h3 className="font-semibold text-ink">Projection dependency graph</h3>
                  <div className="mt-3 overflow-auto">
                    <table className="min-w-full text-left text-sm">
                      <thead>
                        <tr className="border-b border-border text-xs uppercase text-slate-500">
                          <th className="py-2 pr-3">Projection</th>
                          <th className="py-2 pr-3">Depends on</th>
                          <th className="py-2 pr-3">Order</th>
                          <th className="py-2">Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        {(dash?.governance?.dependencies || []).map((dep: any) => {
                          const cp = (dash?.governance?.checkpoints || []).find(
                            (c: any) => c.projection_name === dep.projection_name,
                          );
                          return (
                            <tr key={dep.projection_name} className="border-b border-border/60">
                              <td className="py-2 pr-3 font-medium">{dep.projection_name}</td>
                              <td className="py-2 pr-3 text-slate-600">{dep.depends_on || '—'}</td>
                              <td className="py-2 pr-3">{dep.rebuild_order}</td>
                              <td className="py-2">
                                <span className={`badge ${statusBadgeClass(cp?.status)}`}>{cp?.status || 'unknown'}</span>
                                {cp?.lag_seconds ? ` · lag ${cp.lag_seconds}s` : ''}
                              </td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>

                  <h4 className="mt-5 font-semibold text-ink">Recent integrity checks</h4>
                  <div className="mt-2 overflow-auto">
                    <table className="min-w-full text-left text-sm">
                      <thead>
                        <tr className="border-b border-border text-xs uppercase text-slate-500">
                          <th className="py-2 pr-3">Date</th>
                          <th className="py-2 pr-3">Projection</th>
                          <th className="py-2 pr-3">Difference</th>
                          <th className="py-2">Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        {(dash?.governance?.integrity_checks || []).length === 0 ? (
                          <tr>
                            <td colSpan={4} className="py-4 text-slate-500">
                              No integrity checks recorded yet.
                            </td>
                          </tr>
                        ) : (
                          dash.governance.integrity_checks.map((row: any) => (
                            <tr key={row.id} className="border-b border-border/60">
                              <td className="py-2 pr-3">{row.check_date}</td>
                              <td className="py-2 pr-3">{row.projection_name}</td>
                              <td className="py-2 pr-3">{money(row.difference)}</td>
                              <td className="py-2">
                                <span className={`badge ${integrityBadgeClass(row.status)}`}>{row.status}</span>
                              </td>
                            </tr>
                          ))
                        )}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            )}
          </section>
        )}

        <div className="glass-panel overflow-hidden">
          <div className="border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Recent Snapshots</h2>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Type</th>
                <th className="px-5 py-3 font-semibold">Period</th>
                <th className="px-5 py-3 font-semibold">Frozen</th>
                <th className="px-5 py-3 font-semibold">Correlation</th>
                <th className="px-5 py-3 font-semibold">Created</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr>
                  <td colSpan={5} className="px-5 py-8 text-center text-slate-500">
                    Loading...
                  </td>
                </tr>
              ) : (dash?.recent_snapshots || []).length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-5 py-10 text-center">
                    <BarChart3 className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No snapshots yet. Generate a report to create one.</p>
                  </td>
                </tr>
              ) : (
                dash.recent_snapshots.map((snap: any) => (
                  <tr key={snap.id} className="hover:bg-slate-50/70">
                    <td className="px-5 py-3 font-semibold text-ink">{snap.report_type}</td>
                    <td className="px-5 py-3 text-slate-600">
                      {snap.period_start} → {snap.period_end}
                    </td>
                    <td className="px-5 py-3">{Number(snap.frozen) === 1 ? 'Yes' : 'No'}</td>
                    <td className="px-5 py-3 font-mono text-xs text-slate-500">{snap.correlation_id || '—'}</td>
                    <td className="px-5 py-3 text-slate-600">{snap.created_at || '—'}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {result?.correlation_id && (
          <p className="text-center text-xs text-slate-400" title="Use this ID for support">
            Correlation ID: {result.correlation_id}
          </p>
        )}
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

function FinancialReportOutput({ payload, tab }: { payload: any; tab: ReportTabId }) {
  const report = payload?.report || {};

  if (tab === 'profit_loss' || payload?.report_type === 'profit_loss') {
    return (
      <div className="rounded-xl border border-border bg-slate-50/70 p-4">
        <div className="grid gap-2 sm:grid-cols-3 lg:grid-cols-6">
          <SummaryLine label="Revenue" value={money(report.total_revenue)} />
          <SummaryLine label="COGS" value={money(report.total_cogs)} />
          <SummaryLine label="Gross profit" value={money(report.gross_profit)} />
          <SummaryLine label="Operating exp." value={money(report.total_operating_expenses)} />
          <SummaryLine label="Operating income" value={money(report.operating_income)} />
          <SummaryLine label="Net income" value={money(report.net_income)} bold />
        </div>
        <AccountSection title="Revenue" rows={report.revenue} />
        <AccountSection title="COGS" rows={report.cogs} />
        <AccountSection title="Operating expenses" rows={report.operating_expenses} />
      </div>
    );
  }

  if (tab === 'balance_sheet' || payload?.report_type === 'balance_sheet') {
    return (
      <div className="rounded-xl border border-border bg-slate-50/70 p-4">
        <div
          className={`rounded-lg border px-3 py-2 text-sm ${report.balanced ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-900'}`}
        >
          {report.balance_message || (report.balanced ? 'Balanced' : 'Unbalanced')}
        </div>
        <div className="mt-3 grid gap-2 sm:grid-cols-3">
          <SummaryLine label="Total assets" value={money(report.total_assets)} />
          <SummaryLine label="Total liabilities" value={money(report.total_liabilities)} />
          <SummaryLine label="Total equity" value={money(report.total_equity)} bold />
        </div>
        <AccountSection title="Assets" rows={report.assets} />
        <AccountSection title="Liabilities" rows={report.liabilities} />
        <AccountSection title="Equity" rows={report.equity} />
      </div>
    );
  }

  if (tab === 'cash_flow' || payload?.report_type === 'cash_flow') {
    return (
      <div className="rounded-xl border border-border bg-slate-50/70 p-4">
        <p className="text-xs uppercase text-slate-500">Method: {report.method || 'indirect'}</p>
        <div className="mt-3 grid gap-2 sm:grid-cols-4">
          <SummaryLine label="Operating" value={money(sumAmounts(report.operating))} />
          <SummaryLine label="Investing" value={money(sumAmounts(report.investing))} />
          <SummaryLine label="Financing" value={money(sumAmounts(report.financing))} />
          <SummaryLine label="Net cash flow" value={money(report.net_cash_flow)} bold />
        </div>
        <AccountSection title="Operating activities" rows={report.operating} />
        <AccountSection title="Investing activities" rows={report.investing} />
        <AccountSection title="Financing activities" rows={report.financing} />
      </div>
    );
  }

  if (tab === 'trial_balance' || payload?.report_type === 'trial_balance') {
    return (
      <div className="rounded-xl border border-border bg-slate-50/70 p-4">
        <div
          className={`rounded-lg border px-3 py-2 text-sm ${report.balanced ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-900'}`}
        >
          {report.balance_message || (report.balanced ? 'Balanced' : 'Unbalanced')}
        </div>
        <div className="mt-3 grid gap-2 sm:grid-cols-3">
          <SummaryLine label="Total debits" value={money(report.total_debits)} />
          <SummaryLine label="Total credits" value={money(report.total_credits)} />
          <SummaryLine label="Difference" value={money(report.difference)} bold />
        </div>
        <LedgerTable
          columns={['code', 'name', 'opening_balance', 'closing_balance', 'debit', 'credit']}
          rows={report.accounts || []}
        />
      </div>
    );
  }

  if (tab === 'general_ledger' || payload?.report_type === 'general_ledger') {
    return (
      <div className="rounded-xl border border-border bg-slate-50/70 p-4">
        <div className="grid gap-2 sm:grid-cols-3">
          <SummaryLine label="Entries" value={report.entry_count ?? 0} />
          <SummaryLine label="Total debits" value={money(report.total_debits)} />
          <SummaryLine label="Total credits" value={money(report.total_credits)} bold />
        </div>
        <LedgerTable
          columns={['transaction_date', 'journal_number', 'account_code', 'description', 'debit', 'credit', 'running_balance']}
          rows={report.rows || []}
        />
      </div>
    );
  }

  if (tab === 'changes_equity' || payload?.report_type === 'changes_equity') {
    return (
      <div className="rounded-xl border border-border bg-slate-50/70 p-4">
        <div className="grid gap-2 sm:grid-cols-2">
          <SummaryLine label="Net income" value={money(report.net_income)} />
          <SummaryLine label="Ending equity" value={money(report.ending_equity)} bold />
        </div>
        <AccountSection title="Equity accounts" rows={report.equity_accounts} />
      </div>
    );
  }

  return (
    <div className="rounded-xl border border-border bg-slate-50/70 p-4 text-sm text-slate-600">
      No report data for this period.
    </div>
  );
}

function AccountSection({ title, rows }: { title: string; rows?: any[] }) {
  if (!rows?.length) return null;
  return (
    <div className="mt-4">
      <p className="text-xs font-bold uppercase text-slate-500">{title}</p>
      <div className="mt-2 overflow-auto">
        <table className="min-w-full text-left text-sm">
          <thead>
            <tr className="border-b border-border text-xs uppercase text-slate-500">
              <th className="py-2 pr-3">Code</th>
              <th className="py-2 pr-3">Account</th>
              <th className="py-2">Amount</th>
            </tr>
          </thead>
          <tbody>
            {rows.slice(0, 50).map((row, idx) => (
              <tr key={idx} className="border-b border-border/60">
                <td className="py-2 pr-3">{row.code}</td>
                <td className="py-2 pr-3">{row.name}</td>
                <td className="py-2">{money(row.amount)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function LedgerTable({ columns, rows }: { columns: string[]; rows: any[] }) {
  if (!rows.length) return <p className="mt-3 text-sm text-slate-500">No rows.</p>;
  return (
    <div className="mt-4 overflow-auto">
      <table className="min-w-full text-left text-sm">
        <thead>
          <tr className="border-b border-border text-xs uppercase text-slate-500">
            {columns.map((col) => (
              <th key={col} className="py-2 pr-3">
                {col.replace(/_/g, ' ')}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.slice(0, 50).map((row, idx) => (
            <tr key={idx} className="border-b border-border/60">
              {columns.map((col) => (
                <td key={col} className="py-2 pr-3">
                  {formatCell(row[col])}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function TooltipBadge({ icon, text }: { icon: ReactNode; text: string }) {
  return (
    <span className="inline-flex items-center gap-1 rounded-full border border-border bg-white px-2.5 py-1" title={text}>
      {icon}
      <span className="max-w-[12rem] truncate">{text}</span>
    </span>
  );
}

function Field({ label, tooltip, children }: { label: string; tooltip?: string; children: ReactNode }) {
  return (
    <label className="block" title={tooltip}>
      <span className="text-xs font-semibold uppercase text-slate-500">{label}</span>
      <div className="mt-1">{children}</div>
    </label>
  );
}

function SummaryLine({ label, value, bold = false }: { label: string; value: string | number; bold?: boolean }) {
  return (
    <div>
      <p className="text-xs uppercase text-slate-500">{label}</p>
      <p className={`text-lg ${bold ? 'font-black text-ink' : 'font-semibold text-slate-700'}`}>{value}</p>
    </div>
  );
}

function sumAmounts(rows?: any[]) {
  return (rows || []).reduce((sum, row) => sum + Number(row.amount || 0), 0);
}

function statusBadgeClass(status?: string) {
  if (status === 'running') return 'border-emerald-200 bg-emerald-50 text-emerald-800';
  if (status === 'lagging') return 'border-amber-200 bg-amber-50 text-amber-900';
  if (status === 'failed') return 'border-red-200 bg-red-50 text-red-800';
  return 'border-slate-200 bg-slate-50 text-slate-700';
}

function integrityBadgeClass(status?: string) {
  if (status === 'pass') return 'border-emerald-200 bg-emerald-50 text-emerald-800';
  if (status === 'repaired') return 'border-blue-200 bg-blue-50 text-blue-800';
  return 'border-red-200 bg-red-50 text-red-800';
}

function formatCell(value: unknown) {
  if (value == null || value === '') return '—';
  if (typeof value === 'number') return value.toLocaleString(undefined, { maximumFractionDigits: 2 });
  return String(value);
}

function money(value?: string | number) {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(Number(value || 0));
}
