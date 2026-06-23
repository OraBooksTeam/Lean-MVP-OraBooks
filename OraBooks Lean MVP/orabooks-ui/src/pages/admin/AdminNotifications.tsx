import { useEffect, useState } from 'react';
import AdminPageShell from '@/components/AdminPageShell';
import NotificationCenterPanel from '@/components/NotificationCenterPanel';
import NotificationPreferencesForm from '@/components/NotificationPreferencesForm';
import {
  buildNotificationQueryParams,
  EMPTY_NOTIFICATION_FILTERS,
  normalizeNotificationList,
  type NotificationFilters,
  type NotificationRow,
} from '@/lib/notifications/sl250';
import { api } from '../api';

export default function AdminNotifications() {
  const [tab, setTab] = useState<'feed' | 'prefs'>('feed');
  const [loading, setLoading] = useState(true);
  const [notifications, setNotifications] = useState<NotificationRow[]>([]);
  const [unread, setUnread] = useState(0);
  const [error, setError] = useState('');
  const [filters, setFilters] = useState<NotificationFilters>(EMPTY_NOTIFICATION_FILTERS);
  const [applied, setApplied] = useState<NotificationFilters>(EMPTY_NOTIFICATION_FILTERS);

  const load = useCallback(async (f = applied) => {
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
  }, [applied]);

  useEffect(() => {
    void load();
  }, [load]);

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
  };

  return (
    <AdminPageShell
      title="Notifications"
      description="Platform and account activity with delivery status, SLA badges, and manual retry."
    >
      <div className="flex gap-2">
        <button
          type="button"
          onClick={() => setTab('feed')}
          className={`rounded-lg px-3 py-2 text-sm font-semibold transition ${
            tab === 'feed' ? 'bg-primary text-white' : 'bg-white text-ink-secondary hover:bg-primary/5'
          }`}
        >
          Activity feed
        </button>
        <button
          type="button"
          onClick={() => setTab('prefs')}
          className={`rounded-lg px-3 py-2 text-sm font-semibold transition ${
            tab === 'prefs' ? 'bg-primary text-white' : 'bg-white text-ink-secondary hover:bg-primary/5'
          }`}
        >
          Preferences
        </button>
      </div>

      {tab === 'prefs' ? (
        <NotificationPreferencesForm compact />
      ) : (
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
          showPreferencesLink={false}
          showStats
        />
      )}
    </AdminPageShell>
  );
}
