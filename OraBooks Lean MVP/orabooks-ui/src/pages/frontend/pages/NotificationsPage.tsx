import { useEffect, useMemo, useState } from 'react';
import NotificationCenterPanel from '@/components/NotificationCenterPanel';
import {
  buildNotificationQueryParams,
  EMPTY_NOTIFICATION_FILTERS,
  normalizeNotificationList,
  type NotificationFilters,
  type NotificationRow,
} from '@/lib/notifications/sl250';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { Bell } from 'lucide-react';

function readHighlightId(): number | null {
  const params = new URLSearchParams(window.location.search);
  const raw = params.get('notification_id');
  if (!raw) return null;
  const id = Number(raw);
  return Number.isFinite(id) && id > 0 ? id : null;
}

export default function NotificationsPage() {
  const [loading, setLoading] = useState(true);
  const [context, setContext] = useState<any>(null);
  const [notifications, setNotifications] = useState<NotificationRow[]>([]);
  const [unread, setUnread] = useState(0);
  const [error, setError] = useState('');
  const [filters, setFilters] = useState<NotificationFilters>(EMPTY_NOTIFICATION_FILTERS);
  const [applied, setApplied] = useState<NotificationFilters>(EMPTY_NOTIFICATION_FILTERS);
  const [highlightId, setHighlightId] = useState<number | null>(() => readHighlightId());

  const isOwner = context?.user?.role === 'owner';
  const isPartner = context?.organization?.organization_type === 'partner' || context?.user?.is_partner;

  const load = async (f = applied) => {
    setLoading(true);
    setError('');
    const res = await api.notificationsList(buildNotificationQueryParams(f));
    if (res.error) {
      setError(res.error);
    } else {
      const { notifications: rows, unread: count } = normalizeNotificationList((res as any).data);
      setNotifications(rows);
      setUnread(count);
    }
    setLoading(false);
  };

  useEffect(() => {
    api.frontendContext().then((res) => {
      if (!res.error) setContext((res as any).data);
    });
    void load();
  }, []);

  useEffect(() => {
    if (!highlightId) return;
    const params = new URLSearchParams(window.location.search);
    if (!params.has('notification_id')) return;
    params.delete('notification_id');
    const next = params.toString();
    const cleanUrl = `${window.location.pathname}${next ? `?${next}` : ''}`;
    window.history.replaceState({}, document.title, cleanUrl);
  }, [highlightId]);

  const applyFilters = (next?: NotificationFilters) => {
    const f = next ?? filters;
    if (next) setFilters(next);
    setApplied(f);
    void load(f);
  };

  const clearFilters = () => {
    setFilters(EMPTY_NOTIFICATION_FILTERS);
    setApplied(EMPTY_NOTIFICATION_FILTERS);
    void load(EMPTY_NOTIFICATION_FILTERS);
  };

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

  const retryDelivery = async (id: number) => {
    const res = await api.notificationsRetryDelivery(id);
    if (res?.error) return { error: res.error };
    setHighlightId(id);
  };

  const header = useMemo(
    () => (
      <div className="mb-2 flex items-center gap-3">
        <div className="rounded-xl bg-primary/10 p-2.5 text-primary">
          <Bell className="h-6 w-6" />
        </div>
        <div>
          <h1 className="text-2xl font-bold text-ink">Notification Center</h1>
          <p className="text-sm text-slate-600">View all system notifications and delivery evidence.</p>
        </div>
      </div>
    ),
    []
  );

  return (
    <ClientShell
      title="Notifications"
      eyebrow="Activity center"
      organization={context?.organization}
      isPartner={isPartner}
    >
      <div className="space-y-4">
        {header}
        <NotificationCenterPanel
          rows={notifications}
          loading={loading}
          error={error}
          unread={unread}
          filters={filters}
          onFiltersChange={setFilters}
          onApplyFilters={applyFilters}
          onClearFilters={clearFilters}
          onRefresh={() => void load()}
          onMarkRead={markRead}
          onMarkAllRead={markAllRead}
          onRetry={retryDelivery}
          showOwnerAdminLink={isOwner}
          highlightId={highlightId}
        />
      </div>
    </ClientShell>
  );
}
