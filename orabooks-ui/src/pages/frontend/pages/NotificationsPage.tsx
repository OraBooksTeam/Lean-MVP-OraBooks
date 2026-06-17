import { useEffect, useState } from 'react';
import { api } from '../api';
import { Bell, Mail, Smartphone, Monitor } from 'lucide-react';

export default function NotificationsPage() {
  const [loading, setLoading] = useState(true);
  const [notifications, setNotifications] = useState<any[]>([]);
  const [unread, setUnread] = useState(0);

  useEffect(() => {
    api.notificationsList({ limit: 50 }).then((res) => {
      if (!res.error) {
        const data = (res as any).data;
        setNotifications(data?.notifications || []);
        setUnread(data?.unread_count || 0);
      }
      setLoading(false);
    });
  }, []);

  const markAllRead = () => {
    api.notificationsMarkAllRead().then(() => {
      setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true })));
      setUnread(0);
    });
  };

  return (
    <div className="min-h-screen bg-slate-50 p-6">
      <div className="mx-auto max-w-4xl space-y-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="rounded-xl bg-primary/10 p-2.5 text-primary">
              <Bell className="h-6 w-6" />
            </div>
            <div>
              <h1 className="text-2xl font-bold text-ink">Notifications</h1>
              {unread > 0 && (
                <span className="badge bg-primary/10 text-primary">{unread} unread</span>
              )}
            </div>
          </div>
          <button
            onClick={markAllRead}
            disabled={unread === 0}
            className="rounded-lg border border-border bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-50"
          >
            Mark all read
          </button>
        </div>

        <div className="space-y-3">
          {loading && <p className="text-sm text-slate-500">Loading…</p>}
          {!loading && notifications.length === 0 && (
            <div className="glass-panel p-8 text-center text-sm text-slate-500">No notifications found</div>
          )}
          {notifications.map((n) => (
            <div
              key={n.id}
              className={`glass-panel p-5 transition-all duration-200 ${!n.is_read ? 'border-l-4 border-l-primary bg-sky-50/40' : ''}`}
            >
              <div className="flex items-start justify-between gap-3">
                <div className="space-y-1">
                  <div className="flex items-center gap-2">
                    <h3 className="text-sm font-semibold text-ink">{n.title || n.event_type}</h3>
                    <Badge priority={n.priority} />
                  </div>
                  {n.message && <p className="text-sm text-slate-600">{n.message}</p>}
                  <div className="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                    <span>{n.created_at}</span>
                    <ChannelIcon channel={n.delivery_channel} />
                    {n.correlation_id && (
                      <span className="font-mono text-slate-400">#{n.correlation_id.slice(0, 12)}</span>
                    )}
                  </div>
                </div>
                {!n.is_read && <span className="h-2.5 w-2.5 shrink-0 rounded-full bg-primary" />}
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

function Badge({ priority }: { priority?: string }) {
  const map: Record<string, string> = {
    critical: 'bg-red-50 text-red-700 border-red-200',
    high: 'bg-amber-50 text-amber-700 border-amber-200',
    normal: 'bg-sky-50 text-sky-700 border-sky-200',
    low: 'bg-slate-100 text-slate-600 border-slate-200',
  };
  const cls = map[priority || 'normal'] || map.normal;
  return <span className={`badge border ${cls}`}>{priority || 'normal'}</span>;
}

function ChannelIcon({ channel }: { channel?: string }) {
  if (channel === 'email') return <Mail className="h-3.5 w-3.5 text-slate-500" />;
  if (channel === 'push') return <Smartphone className="h-3.5 w-3.5 text-slate-500" />;
  return <Monitor className="h-3.5 w-3.5 text-slate-500" />;
}
