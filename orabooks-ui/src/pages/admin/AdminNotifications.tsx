import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import AdminPageShell from '@/components/AdminPageShell';
import NotificationPreferencesForm from '@/components/NotificationPreferencesForm';
import { api } from '../api';
import { Bell, Mail, RefreshCw } from 'lucide-react';

export default function AdminNotifications() {
  const [tab, setTab] = useState<'feed' | 'prefs'>('feed');
  const [loading, setLoading] = useState(true);
  const [notifications, setNotifications] = useState<any[]>([]);
  const [unread, setUnread] = useState(0);

  const load = () => {
    setLoading(true);
    api.notificationsList({ limit: 50 }).then((res: any) => {
      if (!res.error) {
        const data = res.data;
        setNotifications(data?.notifications || []);
        setUnread(data?.unread_count || 0);
      }
      setLoading(false);
    });
  };

  useEffect(() => {
    load();
  }, []);

  const markAllRead = () => {
    api.notificationsMarkAllRead().then(() => load());
  };

  return (
    <AdminPageShell
      title="Notifications"
      description="Platform and account activity delivered to your inbox and in-app feed."
      actions={
        <div className="flex gap-2">
          {tab === 'feed' && (
            <>
              <Button onClick={markAllRead} variant="secondary" size="sm">Mark all read</Button>
              <Button onClick={load} variant="secondary" size="sm">
                <RefreshCw className="h-4 w-4" />
                Refresh
              </Button>
            </>
          )}
        </div>
      }
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
        <>
      <div className="grid gap-4 sm:grid-cols-3">
        <div className="stat-card">
          <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Unread</p>
          <p className="mt-2 text-3xl font-black text-ink">{unread}</p>
        </div>
        <div className="stat-card">
          <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Total loaded</p>
          <p className="mt-2 text-3xl font-black text-ink">{notifications.length}</p>
        </div>
      </div>
      <div className="glass-panel divide-y divide-border overflow-hidden">
        {loading ? (
          <p className="p-6 text-sm text-slate-500">Loading notifications…</p>
        ) : notifications.length === 0 ? (
          <p className="p-6 text-sm text-slate-500">No notifications yet.</p>
        ) : (
          notifications.map((n) => (
            <div key={n.id} className="flex gap-4 px-5 py-4 hover:bg-slate-50/60">
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                {n.channel === 'email' ? <Mail className="h-4 w-4" /> : <Bell className="h-4 w-4" />}
              </div>
              <div className="min-w-0 flex-1">
                <p className="font-semibold text-ink">{n.title || n.event_type}</p>
                <p className="mt-0.5 text-sm text-slate-600">{n.message || n.body}</p>
                <p className="mt-1 text-xs text-slate-400">{n.created_at}</p>
              </div>
              {!n.is_read && <span className="badge bg-accent text-white">New</span>}
            </div>
          ))
        )}
      </div>
        </>
      )}
    </AdminPageShell>
  );
}
