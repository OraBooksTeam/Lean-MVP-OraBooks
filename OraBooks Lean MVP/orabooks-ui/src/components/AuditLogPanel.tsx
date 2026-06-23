import { Fragment, useState } from 'react';
import Button from '@/components/Button';
import {
  AUDIT_SEVERITIES,
  EMPTY_AUDIT_FILTERS,
  formatAuditMetadata,
  parseAuditMetadata,
  severityBadgeClass,
  type AuditLogFilters,
  type AuditLogRow,
} from '@/lib/audit/sl009';
import { Calendar, ChevronDown, ChevronRight, Download, Filter, RefreshCw } from 'lucide-react';

type AuditLogPanelProps = {
  rows: AuditLogRow[];
  loading: boolean;
  error: string;
  filters: AuditLogFilters;
  onFiltersChange: (filters: AuditLogFilters) => void;
  onApplyFilters: () => void;
  onClearFilters?: () => void;
  onRefresh: () => void;
  onExport: () => Promise<{ error?: string } | void>;
  showOrgFilter?: boolean;
  showOrgColumn?: boolean;
  exportDisabled?: boolean;
};

export default function AuditLogPanel({
  rows,
  loading,
  error,
  filters,
  onFiltersChange,
  onApplyFilters,
  onClearFilters,
  onRefresh,
  onExport,
  showOrgFilter = false,
  showOrgColumn = false,
  exportDisabled = false,
}: AuditLogPanelProps) {
  const [exporting, setExporting] = useState(false);
  const [exportError, setExportError] = useState('');
  const [expandedRows, setExpandedRows] = useState<Record<string, boolean>>({});

  const setFilter = <K extends keyof AuditLogFilters>(key: K, value: AuditLogFilters[K]) => {
    onFiltersChange({ ...filters, [key]: value });
  };

  const handleExport = async () => {
    setExporting(true);
    setExportError('');
    const res = await onExport();
    if (res && typeof res === 'object' && 'error' in res && res.error) {
      setExportError(typeof res.error === 'string' ? res.error : 'Audit export failed.');
    }
    setExporting(false);
  };

  const toggleRow = (key: string) => {
    setExpandedRows((prev) => ({ ...prev, [key]: !prev[key] }));
  };

  const filterByCorrelation = (correlationId: string) => {
    onFiltersChange({ ...filters, correlation_id: correlationId });
    onApplyFilters();
  };

  const colSpan = 7 + (showOrgColumn ? 1 : 0) + 1;

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-slate-600">
          Immutable compliance evidence for your organization. Records cannot be edited or deleted.
        </p>
        <div className="flex flex-wrap gap-2">
          <Button
            variant="secondary"
            size="sm"
            loading={exporting}
            disabled={exportDisabled || loading}
            onClick={() => void handleExport()}
            title="Export audit log as CSV for compliance."
          >
            <Download className="h-4 w-4" />
            Export CSV
          </Button>
          <Button variant="secondary" size="sm" onClick={onRefresh} disabled={loading}>
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>
      </div>

      <div className="glass-panel flex flex-wrap items-center gap-2 p-4">
        {showOrgFilter && (
          <input
            className="w-24 rounded-lg border border-border bg-white px-3 py-2 text-sm outline-none"
            placeholder="Org ID"
            value={filters.org_id}
            onChange={(e) => setFilter('org_id', e.target.value)}
          />
        )}
        <input
          className="rounded-lg border border-border bg-white px-3 py-2 text-sm outline-none"
          placeholder="User ID"
          value={filters.user_id}
          onChange={(e) => setFilter('user_id', e.target.value)}
        />
        <input
          className="rounded-lg border border-border bg-white px-3 py-2 text-sm outline-none"
          placeholder="Event type"
          value={filters.event_type}
          onChange={(e) => setFilter('event_type', e.target.value)}
        />
        <input
          className="min-w-[12rem] rounded-lg border border-border bg-white px-3 py-2 text-sm outline-none"
          placeholder="Correlation ID"
          value={filters.correlation_id}
          onChange={(e) => setFilter('correlation_id', e.target.value)}
          title="Filter by correlation ID to trace a workflow."
        />
        <select
          className="rounded-lg border border-border bg-white px-3 py-2 text-sm outline-none"
          value={filters.severity}
          onChange={(e) => setFilter('severity', e.target.value)}
        >
          <option value="">All severities</option>
          {AUDIT_SEVERITIES.map((severity) => (
            <option key={severity} value={severity}>
              {severity.charAt(0).toUpperCase() + severity.slice(1)}
            </option>
          ))}
        </select>
        <div className="flex items-center gap-1.5 rounded-lg border border-border bg-white px-3 py-2 text-sm">
          <Calendar className="h-4 w-4 text-slate-500" />
          <input
            type="date"
            className="bg-transparent text-sm outline-none"
            value={filters.from_date}
            onChange={(e) => setFilter('from_date', e.target.value)}
          />
          <span className="text-slate-400">–</span>
          <input
            type="date"
            className="bg-transparent text-sm outline-none"
            value={filters.to_date}
            onChange={(e) => setFilter('to_date', e.target.value)}
          />
        </div>
        <Button size="sm" onClick={onApplyFilters}>
          <Filter className="h-4 w-4" />
          Filter
        </Button>
        <Button
          size="sm"
          variant="secondary"
          onClick={() => (onClearFilters ? onClearFilters() : onFiltersChange({ ...EMPTY_AUDIT_FILTERS, org_id: filters.org_id }))}
        >
          Clear
        </Button>
      </div>

      {(error || exportError) && (
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">
          {error || exportError}
        </div>
      )}

      <div className="glass-panel overflow-hidden">
        <table className="min-w-full text-left text-sm">
          <thead>
            <tr className="border-b border-border bg-slate-50/60 text-xs uppercase text-slate-500">
              <th className="px-3 py-3 font-semibold" aria-label="Expand" />
              <th className="px-5 py-3 font-semibold">Timestamp</th>
              {showOrgColumn && <th className="px-5 py-3 font-semibold">Org</th>}
              <th className="px-5 py-3 font-semibold">User</th>
              <th className="px-5 py-3 font-semibold">Event</th>
              <th className="px-5 py-3 font-semibold">Severity</th>
              <th className="px-5 py-3 font-semibold">Description</th>
              <th className="px-5 py-3 font-semibold">IP</th>
              <th className="px-5 py-3 font-semibold">Correlation</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {loading ? (
              <tr>
                <td colSpan={colSpan} className="px-5 py-6 text-center text-slate-500">
                  Loading audit evidence…
                </td>
              </tr>
            ) : rows.length === 0 ? (
              <tr>
                <td colSpan={colSpan} className="px-5 py-6 text-center text-slate-500">
                  No audit records found for the current filters.
                </td>
              </tr>
            ) : (
              rows.map((row, index) => {
                const rowKey = `${row.correlation_id}-${row.created_at}-${index}`;
                const metadata = parseAuditMetadata(row.metadata);
                const hasMetadata = Boolean(metadata && Object.keys(metadata).length > 0);
                const expanded = Boolean(expandedRows[rowKey]);

                return (
                  <Fragment key={rowKey}>
                    <tr className="transition hover:bg-slate-50/60">
                      <td className="px-3 py-3">
                        {hasMetadata ? (
                          <button
                            type="button"
                            className="rounded p-1 text-slate-500 hover:bg-slate-100"
                            onClick={() => toggleRow(rowKey)}
                            aria-label={expanded ? 'Hide metadata' : 'Show metadata'}
                          >
                            {expanded ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                          </button>
                        ) : null}
                      </td>
                      <td className="px-5 py-3 text-slate-600">{row.created_at}</td>
                      {showOrgColumn && (
                        <td className="px-5 py-3 font-mono text-xs text-slate-600">{row.org_id ?? '—'}</td>
                      )}
                      <td className="px-5 py-3 font-mono text-xs text-slate-600">{row.user_id || '—'}</td>
                      <td className="px-5 py-3 font-medium text-ink">{row.event_type}</td>
                      <td className="px-5 py-3">
                        <span className={`badge border ${severityBadgeClass(row.severity)}`}>
                          {row.severity || '—'}
                        </span>
                      </td>
                      <td className="px-5 py-3 text-slate-600">{row.description}</td>
                      <td className="px-5 py-3 font-mono text-xs text-slate-600">{row.ip_address || '—'}</td>
                      <td className="px-5 py-3">
                        {row.correlation_id ? (
                          <button
                            type="button"
                            className="font-mono text-xs text-primary hover:underline"
                            title={row.correlation_id}
                            onClick={() => filterByCorrelation(row.correlation_id)}
                          >
                            #{row.correlation_id.slice(0, 12)}
                          </button>
                        ) : (
                          '—'
                        )}
                      </td>
                    </tr>
                    {expanded && hasMetadata && (
                      <tr className="bg-slate-50/40">
                        <td colSpan={colSpan} className="px-5 py-4">
                          <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Evidence metadata</p>
                          <pre className="mt-2 overflow-x-auto rounded-lg border border-border bg-white p-3 text-xs text-slate-700">
                            {formatAuditMetadata(row.metadata)}
                          </pre>
                        </td>
                      </tr>
                    )}
                  </Fragment>
                );
              })
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
