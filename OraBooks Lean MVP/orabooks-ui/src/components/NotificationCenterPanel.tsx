import { useEffect, useRef, useState } from 'react';
import Button from '@/components/Button';
import WpLink from '@/pages/frontend/components/WpLink';
import {
  canRetryDelivery,
  EMPTY_NOTIFICATION_FILTERS,
  NOTIFICATION_PRIORITIES,
  NOTIFICATION_STATUSES,
  priorityBadgeClass,
  resolveNotificationViewUrl,
  statusBadgeClass,
  type NotificationFilters,
  type NotificationRow,
} from '@/lib/notifications/sl250';
import { Calendar, Filter, Mail, Monitor, RefreshCw, Settings, Smartphone } from 'lucide-react';

const fieldClass =
  'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';

type NotificationCenterPanelProps = {
  rows: NotificationRow[];
  loading: boolean;
  error: string;
  unread: number;
  filters: NotificationFilters;
  onFiltersChange: (filters: NotificationFilters) => void;
  onApplyFilters: (nextFilters?: NotificationFilters) => void;
  onClearFilters: () => void;
  onRefresh: () => void;
  onMarkRead: (id: number) => Promise<void>;
  onMarkAllRead: () => Promise<void>;
  onRetry?: (id: number) => Promise<{ error?: string } | void>;
  showOwnerAdminLink?: boolean;
  highlightId?: number | null;
  showStats?: boolean;
};

export default function NotificationCenterPanel({
  rows,
  loading,
  error,
  unread,
  filters,
  onFiltersChange,
  onApplyFilters,
  onClearFilters,
  onRefresh,
  onMarkRead,
  onMarkAllRead,
  onRetry,
  showOwnerAdminLink = false,
  highlightId = null,
  showStats = false,
}: NotificationCenterPanelProps) {
  const [proof, setProof] = useState<Record<string, unknown> | null>(null);
  const [retryingId, setRetryingId] = useState<number | null>(null);
  const [retryError, setRetryError] = useState('');
  const highlightRef = useRef<HTMLDivElement | null>(null);

  const setFilter = <K extends keyof NotificationFilters>(key: K, value: NotificationFilters[K]) => {
    onFiltersChange({ ...filters, [key]: value });
  };

  const filterByCorrelation = (correlationId: string) => {
    const next = { ...filters, correlation_id: correlationId };
    onFiltersChange(next);
    onApplyFilters(next);
  };

  useEffect(() => {
    if (!highlightId || !highlightRef.current) return;
    highlightRef.current.scrollIntoView({ behavior: 'smooth', block: 'center' });
    const row = rows.find((n) => n.id === highlightId);
    if (row && !row.is_read && row.status === 'delivered') {
      void onMarkRead(highlightId);
    }
  }, [highlightId, rows, onMarkRead]);

  const handleRetry = async (id: number) => {
    if (!onRetry) return;
    setRetryingId(id);
    setRetryError('');
    const res = await onRetry(id);
    if (res && typeof res === 'object' && 'error' in res && res.error) {
      setRetryError(res.error);
    } else {
      onRefresh();
    }
    setRetryingId(null);
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="text-sm text-slate-600">
            Immutable delivery evidence with correlation tracing. Critical alerts bypass budget and deduplication.
          </p>
          {unread > 0 && (
            <span className="mt-2 inline-flex badge bg-primary/10 text-primary">{unread} unread</span>
          )}
        </div>
        <div className="flex flex-wrap gap-2">
          {showOwnerAdminLink && (
            <WpLink
              to="/notification-admin"
              className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
              title="Org notification policy, provider health, and audit export"
            >
              <Settings className="h-4 w-4" />
              Org settings
            </WpLink>
          )}
          <WpLink
            to="/notification-preferences"
            className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
            title="Quiet hours, digest, and escalation preferences"
          >
            Preferences
          </WpLink>
          <Button onClick={onRefresh} variant="secondary" size="sm" disabled={loading}>
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
          <Button onClick={() => void onMarkAllRead()} variant="secondary" size="sm" disabled={unread === 0}>
            Mark all read
          </Button>
        </div>
      </div>

      {showStats && (
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="stat-card">
            <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Unread</p>
            <p className="mt-2 text-3xl font-black text-ink">{unread}</p>
          </div>
          <div className="stat-card">
            <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Loaded</p>
            <p className="mt-2 text-3xl font-black text-ink">{rows.length}</p>
          </div>
        </div>
      )}

      <section className="glass-panel p-5">
        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">Filters</h2>
        <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          <label className="flex items-center gap-2 text-sm text-slate-700 sm:col-span-2 xl:col-span-3">
            <input
              type="checkbox"
              className="h-4 w-4 rounded border-border text-primary"
              checked={filters.unread_only}
              onChange={(e) => setFilter('unread_only', e.target.checked)}
            />
            Unread only (delivered)
          </label>
          <select
            className={fieldClass}
            value={filters.priority}
            onChange={(e) => setFilter('priority', e.target.value)}
          >
            <option value="">All priorities</option>
            {NOTIFICATION_PRIORITIES.map((priority) => (
              <option key={priority} value={priority}>
                {priority.charAt(0).toUpperCase() + priority.slice(1)}
              </option>
            ))}
          </select>
          <select
            className={fieldClass}
            value={filters.status}
            onChange={(e) => setFilter('status', e.target.value)}
            title="Filter by delivery status"
          >
            <option value="">All statuses</option>
            {NOTIFICATION_STATUSES.map((status) => (
              <option key={status} value={status}>
                {status.replace(/_/g, ' ')}
              </option>
            ))}
          </select>
          <input
            type="text"
            className={fieldClass}
            placeholder="Event type"
            value={filters.event_type}
            onChange={(e) => setFilter('event_type', e.target.value)}
          />
          <input
            type="text"
            className={fieldClass}
            placeholder="Correlation ID"
            value={filters.correlation_id}
            onChange={(e) => setFilter('correlation_id', e.target.value)}
            title="Trace a workflow by correlation ID"
          />
          <div className="flex items-center gap-1.5 rounded-lg border border-border bg-white px-3 py-2 text-sm sm:col-span-2">
            <Calendar className="h-4 w-4 shrink-0 text-slate-500" />
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
        </div>
        <div className="mt-4 flex flex-wrap gap-2">
          <Button size="sm" onClick={() => onApplyFilters()}>
            <Filter className="h-4 w-4" />
            Apply filters
          </Button>
          <Button size="sm" variant="secondary" onClick={onClearFilters}>
            Clear
          </Button>
        </div>
      </section>

      {(error || retryError) && (
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">
          {error || retryError}
        </div>
      )}

      <div className="space-y-3">
        {loading && <p className="text-sm text-slate-500">Loading notifications…</p>}
        {!loading && rows.length === 0 && (
          <div className="glass-panel p-8 text-center text-sm text-slate-500">
            No notifications found for the current filters.
          </div>
        )}
        {rows.map((n) => {
          const highlighted = highlightId === n.id;
          return (
            <div
              key={n.id}
              ref={highlighted ? highlightRef : undefined}
              role="button"
              tabIndex={0}
              onClick={() => {
                if (!n.is_read && n.status === 'delivered') void onMarkRead(n.id);
              }}
              onKeyDown={(e) => {
                if (e.key === 'Enter' && !n.is_read && n.status === 'delivered') void onMarkRead(n.id);
              }}
              className={`glass-panel cursor-pointer p-5 transition-all duration-200 ${
                !n.is_read && n.status === 'delivered' ? 'border-l-4 border-l-success bg-success/5' : ''
              } ${highlighted ? 'ring-2 ring-primary ring-offset-2' : ''}`}
            >
              <div className="flex items-start justify-between gap-3">
                <div className="space-y-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <h3 className="text-sm font-semibold text-ink">{n.title || n.event_type}</h3>
                    <span className={`badge border ${priorityBadgeClass(n.priority)}`}>{n.priority || 'normal'}</span>
                    {n.sla_breached && (
                      <span className="badge border border-red-200 bg-red-50 text-red-700">SLA breach</span>
                    )}
                    <span className={`badge border capitalize ${statusBadgeClass(n.status)}`}>{n.status || 'unknown'}</span>
                  </div>
                  {n.message && <p className="text-sm text-slate-600">{n.message}</p>}
                  {n.payload?.view_url && (
                    <a
                      href={resolveNotificationViewUrl(String(n.payload.view_url))}
                      onClick={(e) => e.stopPropagation()}
                      className="mt-2 inline-flex text-xs font-semibold text-primary hover:text-primary-dark"
                    >
                      View details
                    </a>
                  )}
                  <div className="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                    <span>{n.created_at}</span>
                    <ChannelIcon channel={n.delivery_channel} />
                    {n.delivery_region && (
                      <span title="Routed via nearest region for faster delivery">🌍 {n.delivery_region}</span>
                    )}
                    {n.correlation_id && (
                      <button
                        type="button"
                        className="font-mono text-primary hover:underline"
                        title={n.correlation_id}
                        onClick={(e) => {
                          e.stopPropagation();
                          filterByCorrelation(n.correlation_id);
                        }}
                      >
                        #{n.correlation_id.slice(0, 12)}
                      </button>
                    )}
                    {n.event_type && <span className="capitalize">{n.event_type.replace(/_/g, ' ')}</span>}
                  </div>
                  <div className="mt-2 flex flex-wrap gap-3">
                    {n.has_delivery_proof && (
                      <button
                        type="button"
                        onClick={(e) => {
                          e.stopPropagation();
                          setProof((n.delivery_proof as Record<string, unknown>) || null);
                        }}
                        className="text-xs font-semibold text-primary hover:text-primary-dark"
                        title="Show delivery timestamp and signature"
                      >
                        View delivery proof
                      </button>
                    )}
                    {onRetry && canRetryDelivery(n.status) && (
                      <button
                        type="button"
                        onClick={(e) => {
                          e.stopPropagation();
                          void handleRetry(n.id);
                        }}
                        disabled={retryingId === n.id}
                        className="text-xs font-semibold text-amber-700 hover:text-amber-900 disabled:opacity-50"
                        title="Manually retry delivery for failed or dead-letter notifications"
                      >
                        {retryingId === n.id ? 'Retrying…' : 'Retry delivery'}
                      </button>
                    )}
                  </div>
                </div>
                {!n.is_read && n.status === 'delivered' && (
                  <span className="h-2.5 w-2.5 shrink-0 rounded-full bg-primary" />
                )}
              </div>
            </div>
          );
        })}
      </div>

      {proof && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-lg rounded-2xl border border-border bg-white p-6 shadow-xl">
            <h3 className="text-lg font-bold text-ink">Delivery proof</h3>
            <pre className="mt-4 max-h-80 overflow-auto rounded-xl border border-border bg-slate-50 p-4 text-xs text-slate-700">
              {JSON.stringify(proof, null, 2)}
            </pre>
            <div className="mt-6 flex justify-end">
              <Button variant="secondary" onClick={() => setProof(null)}>
                Close
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function ChannelIcon({ channel }: { channel?: string }) {
  if (channel === 'email') {
    return (
      <span className="inline-flex items-center gap-1">
        <Mail className="h-3.5 w-3.5" /> email
      </span>
    );
  }
  if (channel === 'push') {
    return (
      <span className="inline-flex items-center gap-1">
        <Smartphone className="h-3.5 w-3.5" /> push
      </span>
    );
  }
  return (
    <span className="inline-flex items-center gap-1">
      <Monitor className="h-3.5 w-3.5" /> in-app
    </span>
  );
}
