import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import { api } from '@/pages/frontend/api';

export interface NotificationPrefs {
  channels: string[];
  quiet_hours_start: string;
  quiet_hours_end: string;
  digest: string;
  language: string;
  escalation_enabled: boolean;
}

const defaults: NotificationPrefs = {
  channels: ['email', 'inapp'],
  quiet_hours_start: '',
  quiet_hours_end: '',
  digest: 'none',
  language: 'en',
  escalation_enabled: true,
};

function normalizePrefs(data: any): NotificationPrefs {
  return {
    channels: Array.isArray(data?.channels) ? data.channels : defaults.channels,
    quiet_hours_start: data?.quiet_hours_start || '',
    quiet_hours_end: data?.quiet_hours_end || '',
    digest: data?.digest || 'none',
    language: data?.language || 'en',
    escalation_enabled: Boolean(data?.escalation_enabled),
  };
}

export default function NotificationPreferencesForm({ compact = false }: { compact?: boolean }) {
  const [prefs, setPrefs] = useState<NotificationPrefs>(defaults);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  useEffect(() => {
    api.notificationPrefsGet().then((res) => {
      if (res.error) setError(res.error);
      else if (res.data) setPrefs(normalizePrefs(res.data));
      setLoading(false);
    });
  }, []);

  const toggleChannel = (channel: string) => {
    setPrefs((prev) => {
      const channels = prev.channels.includes(channel)
        ? prev.channels.filter((c) => c !== channel)
        : [...prev.channels, channel];
      return { ...prev, channels };
    });
  };

  const handleSubmit = (event: React.FormEvent) => {
    event.preventDefault();
    setSaving(true);
    setError('');
    setMessage('');
    api.notificationPrefsSave({
      channels: prefs.channels,
      quiet_hours_start: prefs.quiet_hours_start,
      quiet_hours_end: prefs.quiet_hours_end,
      digest: prefs.digest,
      language: prefs.language,
      escalation_enabled: prefs.escalation_enabled ? 1 : 0,
    }).then((res) => {
      if (res.error) setError(res.error);
      else {
        setMessage('Preferences saved.');
        if (res.data) setPrefs(normalizePrefs(res.data));
      }
      setSaving(false);
    });
  };

  if (loading) {
    return <div className="h-48 animate-pulse rounded-2xl border border-border bg-white" />;
  }

  return (
    <form onSubmit={handleSubmit} className="glass-panel overflow-hidden">
      {error && <p className="px-6 pt-6 text-sm text-danger">{error}</p>}
      {message && <p className="px-6 pt-6 text-sm text-success">{message}</p>}

      <div className="divide-y divide-border">
        <div className="p-6">
          <p className="text-sm font-semibold text-ink">Notification channels</p>
          <div className="mt-3 flex flex-wrap gap-4 text-sm text-ink-secondary">
            {['email', 'push', 'inapp'].map((channel) => (
              <label key={channel} className="flex items-center gap-2">
                <input
                  type="checkbox"
                  className="h-4 w-4 rounded border-border text-primary"
                  checked={prefs.channels.includes(channel)}
                  onChange={() => toggleChannel(channel)}
                />
                {channel === 'inapp' ? 'In-app' : channel.charAt(0).toUpperCase() + channel.slice(1)}
              </label>
            ))}
          </div>
          {!compact && (
            <p className="mt-2 text-xs text-slate-500">Email routed via nearest region for faster delivery.</p>
          )}
        </div>

        <div className="p-6">
          <p className="text-sm font-semibold text-ink">Quiet hours</p>
          <div className="mt-3 flex flex-wrap items-center gap-2 text-sm">
            <input
              type="time"
              className="rounded-lg border border-border px-3 py-2"
              value={prefs.quiet_hours_start}
              onChange={(e) => setPrefs((prev) => ({ ...prev, quiet_hours_start: e.target.value }))}
            />
            <span className="text-slate-500">to</span>
            <input
              type="time"
              className="rounded-lg border border-border px-3 py-2"
              value={prefs.quiet_hours_end}
              onChange={(e) => setPrefs((prev) => ({ ...prev, quiet_hours_end: e.target.value }))}
            />
          </div>
        </div>

        <div className="grid gap-6 p-6 sm:grid-cols-2">
          <label className="block text-sm">
            <span className="font-semibold text-ink">Digest frequency</span>
            <select
              className="mt-2 w-full rounded-lg border border-border px-3 py-2"
              value={prefs.digest}
              onChange={(e) => setPrefs((prev) => ({ ...prev, digest: e.target.value }))}
            >
              <option value="none">Real-time</option>
              <option value="daily">Daily summary</option>
              <option value="weekly">Weekly summary</option>
            </select>
          </label>
          <label className="block text-sm">
            <span className="font-semibold text-ink">Language</span>
            <select
              className="mt-2 w-full rounded-lg border border-border px-3 py-2"
              value={prefs.language}
              onChange={(e) => setPrefs((prev) => ({ ...prev, language: e.target.value }))}
            >
              <option value="en">English</option>
              <option value="bn">বাংলা</option>
              <option value="ar">العربية</option>
            </select>
          </label>
        </div>

        <label className="flex items-start gap-3 p-6 text-sm text-ink-secondary">
          <input
            type="checkbox"
            className="mt-1 h-4 w-4 rounded border-border text-primary"
            checked={prefs.escalation_enabled}
            onChange={(e) => setPrefs((prev) => ({ ...prev, escalation_enabled: e.target.checked }))}
          />
          <span>
            <span className="font-semibold text-ink">Cross-channel escalation</span>
            <span className="mt-1 block text-xs text-slate-500">
              If email is not read within 10 minutes, send a push notification.
            </span>
          </span>
        </label>
      </div>

      <div className="border-t border-border bg-slate-50/60 px-6 py-4">
        <Button type="submit" disabled={saving}>
          {saving ? 'Saving…' : 'Save preferences'}
        </Button>
      </div>
    </form>
  );
}
