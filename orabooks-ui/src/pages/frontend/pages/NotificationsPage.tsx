import { useCallback, useEffect, useState } from 'react';
import WpLink from '../components/WpLink';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { Bell, Mail, Monitor, RefreshCw, Settings, Smartphone } from 'lucide-react';

const fieldClass =
  'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';

export default function NotificationsPage() {
  const [loading, setLoading] = useState(true);
  const [context, setContext] = useState<any>(null);
  const [notifications, setNotifications] = useState<any[]>([]);
  const [unread, setUnread] = useState(0);
  const [error, setError] = useState('');
  const [proof, setProof] = useState<any>(null);
  const [filters, setFilters] = useState({
    unread_only: false,
    priority: '',
    event_type: '',
    correlation_id: '',
    from_date: '',
    to_date: '',
  });

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    const params: Record<string, string | number> = { limit: 50 };
    if (filters.unread_only) params.unread_only = 1;
    if (filters.priority) params.priority = filters.priority;
    if (filters.event_type) params.event_type = filters.event_type;
    if (filters.correlation_id) params.correlation_id = filters.correlation_id;
    if (filters.from_date) params.from_date = filters.from_date;
    if (filters.to_date) params.to_date = `${filters.to_date} 23:59:59`;

    const res = await api.notificationsList(params);
    if (res.error) {
      setError(res.error);
    } else {
      const data = (res as any).data;
      setNotifications(data?.notifications || []);
      setUnread(data?.unread_count || 0);
    }
    setLoading(false);
  }, [filters]);

  useEffect(() => {
    api.frontendContext().then((res) => {
      if (!res.error) setContext((res as any).data);
    });
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const markRead = async (id: number) => {
    const res = await api.notificationsMarkRead(id);
    if (!res.error) {
      setNotifications((prev) => prev.map((n) => (n.id === id ? { ...n, is_read: true } : n)));
      setUnread((count) => Math.max(0, count - 1));
    }
  };

  const markAllRead = async () => {
    const res = await api.notificationsMarkAllRead();
    if (!res.error) {
      setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true })));
      setUnread(0);
    }
  };

  return (
    <ClientShell
      title="Notifications"
      eyebrow="Activity center"
      organization={context?.organization}
      isPartner={context?.organization?.organization_type === 'partner' || context?.user?.is_partner}
    >
      <div className="space-y-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <div className="rounded-xl bg-primary/10 p-2.5 text-primary">
              <Bell className="h-6 w-6" />
            </div>
            <div>
              <h1 className="text-2xl font-bold text-ink">Notification Center</h1>
              {unread > 0 && (
                <span className="badge bg-primary/10 text-primary">{unread} unread</span>
              )}
            </div>
          </div>
          <div className="flex flex-wrap gap-2">
            <Link
              to="/notification-preferences"
              className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
            >
              <Settings className="h-4 w-4" />
              Preferences
            </WpLink>
            <Button onClick={load} variant="secondary" size="sm" disabled={loading}>
              <RefreshCw className="h-4 w-4" />
              Refresh
            </Button>
            <Button onClick={markAllRead} variant="secondary" size="sm" disabled={unread === 0}>
              Mark all read
            </Button>
          </div>
        </div>

        <section className="glass-panel p-5">
          <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">Filters</h2>
          <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <label className="flex items-center gap-2 text-sm text-slate-700 sm:col-span-2 xl:col-span-3">
              <input
                type="checkbox"
                className="h-4 w-4 rounded border-border text-primary"
                checked={filters.unread_only}
                onChange={(e) => setFilters((prev) => ({ ...prev, unread_only: e.target.checked }))}
              />
              Unread only
            </label>
            <select
              className={fieldClass}
              value={filters.priority}
              onChange={(e) => setFilters((prev) => ({ ...prev, priority: e.target.value }))}
            >
              <option value="">All priorities</option>
              <option value="critical">Critical</option>
              <option value="high">High</option>
              <option value="normal">Normal</option>
              <option value="low">Low</option>
            </select>
            <input
              type="text"
              className={fieldClass}
              placeholder="Event type"
              value={filters.event_type}
              onChange={(e) => setFilters((prev) => ({ ...prev, event_type: e.target.value }))}
            />
            <input
              type="text"
              className={fieldClass}
              placeholder="Correlation ID"
              value={filters.correlation_id}
              onChange={(e) => setFilters((prev) => ({ ...prev, correlation_id: e.target.value }))}
            />
            <input
              type="date"
              className={fieldClass}
              value={filters.from_date}
              onChange={(e) => setFilters((prev) => ({ ...prev, from_date: e.target.value }))}
            />
            <input
              type="date"
              className={fieldClass}
              value={filters.to_date}
              onChange={(e) => setFilters((prev) => ({ ...prev, to_date: e.target.value }))}
            />
          </div>
        </section>

        {error && (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>
        )}

        <div className="space-y-3">
          {loading && <p className="text-sm text-slate-500">Loading…</p>}
          {!loading && notifications.length === 0 && (
            <div className="glass-panel p-8 text-center text-sm text-slate-500">No notifications found</div>
          )}
          {notifications.map((n) => (
            <div
              key={n.id}
              role="button"
              tabIndex={0}
              onClick={() => { if (!n.is_read) void markRead(n.id); }}
              onKeyDown={(e) => { if (e.key === 'Enter' && !n.is_read) void markRead(n.id); }}
              className={`glass-panel cursor-pointer p-5 transition-all duration-200 ${!n.is_read ? 'border-l-4 border-l-success bg-success/5' : ''}`}
            >
              <div className="flex items-start justify-between gap-3">
                <div className="space-y-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <h3 className="text-sm font-semibold text-ink">{n.title || n.event_type}</h3>
                    <PriorityBadge priority={n.priority} />
                    {n.sla_breached && (
                      <span className="badge border border-red-200 bg-red-50 text-red-700">SLA breach</span>
                    )}
                    <StatusBadge status={n.status} />
                  </div>
                  {n.message && <p className="text-sm text-slate-600">{n.message}</p>}
                  <div className="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                    <span>{n.created_at}</span>
                    <ChannelIcon channel={n.delivery_channel} />
                    {n.correlation_id && (
                      <span className="font-mono text-slate-400">#{String(n.correlation_id).slice(0, 12)}</span>
                    )}
                    {n.event_type && <span className="capitalize">{n.event_type.replace(/_/g, ' ')}</span>}
                  </div>
                  {n.has_delivery_proof && (
                    <button
                      type="button"
                      onClick={(e) => { e.stopPropagation(); setProof(n.delivery_proof); }}
                      className="mt-2 text-xs font-semibold text-primary hover:text-primary-dark"
                    >
                      View delivery proof
                    </button>
                  )}
                </div>
                {!n.is_read && <span className="h-2.5 w-2.5 shrink-0 rounded-full bg-primary" />}
              </div>
            </div>
          ))}
        </div>
      </div>

      {proof && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-lg rounded-2xl border border-border bg-white p-6 shadow-xl">
            <h3 className="text-lg font-bold text-ink">Delivery proof</h3>
            <pre className="mt-4 max-h-80 overflow-auto rounded-xl border border-border bg-slate-50 p-4 text-xs text-slate-700">
              {JSON.stringify(proof, null, 2)}
            </pre>
            <div className="mt-6 flex justify-end">
              <Button variant="secondary" onClick={() => setProof(null)}>Close</Button>
            </div>
          </div>
        </div>
      )}
    </ClientShell>
  );
}

function PriorityBadge({ priority }: { priority?: string }) {
  const map: Record<string, string> = {
    critical: 'bg-red-50 text-red-700 border-red-200',
    high: 'bg-amber-50 text-amber-700 border-amber-200',
    normal: 'bg-primary/10 text-primary border-primary/20',
    low: 'bg-slate-100 text-slate-600 border-slate-200',
  };
  const cls = map[priority || 'normal'] || map.normal;
  return <span className={`badge border ${cls}`}>{priority || 'normal'}</span>;
}

function StatusBadge({ status }: { status?: string }) {
  const map: Record<string, string> = {
    delivered: 'bg-success/10 text-success border-success/20',
    failed: 'bg-red-50 text-red-700 border-red-200',
    dead_letter: 'bg-red-50 text-red-700 border-red-200',
    pending: 'bg-amber-50 text-amber-700 border-amber-200',
    queued: 'bg-primary/10 text-primary border-primary/20',
    delivering: 'bg-primary/10 text-primary border-primary/20',
  };
  const cls = map[status || ''] || 'bg-slate-100 text-slate-600 border-slate-200';
  return <span className={`badge border capitalize ${cls}`}>{status || 'unknown'}</span>;
}

function ChannelIcon({ channel }: { channel?: string }) {
  if (channel === 'email') return <span className="inline-flex items-center gap-1"><Mail className="h-3.5 w-3.5" /> email</span>;
  if (channel === 'push') return <span className="inline-flex items-center gap-1"><Smartphone className="h-3.5 w-3.5" /> push</span>;
  return <span className="inline-flex items-center gap-1"><Monitor className="h-3.5 w-3.5" /> in-app</span>;
}
