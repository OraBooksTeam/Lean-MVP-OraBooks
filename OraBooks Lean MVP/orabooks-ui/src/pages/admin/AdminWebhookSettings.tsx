import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import AdminPageShell from '@/components/AdminPageShell';
import { validateHttpsWebhookLines } from '@/lib/security/sl008';
import { api } from '../frontend/api';

export default function AdminWebhookSettings() {
  const [urls, setUrls] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  useEffect(() => {
    api.webhookSettingsGet().then((res: any) => {
      if (res.error) setError(res.error);
      else setUrls(res.data?.urls || '');
      setLoading(false);
    });
  }, []);

  const save = async () => {
    setSaving(true);
    setError('');
    setMessage('');
    const validation = validateHttpsWebhookLines(urls);
    if (!validation.valid) {
      setError(validation.errors.join(' '));
      setSaving(false);
      return;
    }
    const res = await api.webhookSettingsSave(urls);
    if (res.error) setError(res.error);
    else {
      setUrls(res.data?.urls || urls);
      setMessage('Webhook settings saved.');
    }
    setSaving(false);
  };

  return (
    <AdminPageShell
      title="Webhook Settings"
      description="Configure external webhook endpoints for SL-302 events dispatched through SL-303 jobs."
    >
      <div className="max-w-3xl space-y-4 rounded-2xl border border-border bg-white p-5">
        <p className="text-sm text-slate-600">
          Add one webhook URL per line. Webhook delivery runs asynchronously on the `webhooks`
          queue and appears in Job Monitor.
        </p>
        <p className="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">
          Production webhooks must use HTTPS (SL-008 / SSRF policy). Localhost HTTP URLs are allowed for local testing only.
        </p>
        {error && <p className="text-sm text-danger">{error}</p>}
        {message && <p className="text-sm text-success">{message}</p>}
        <textarea
          value={urls}
          onChange={(event) => setUrls(event.target.value)}
          disabled={loading || saving}
          rows={9}
          className="w-full rounded-xl border border-border px-3 py-2 text-sm"
          placeholder="https://webhook.site/..."
        />
        <Button type="button" onClick={save} disabled={loading || saving}>
          {saving ? 'Saving...' : 'Save Webhooks'}
        </Button>
      </div>
    </AdminPageShell>
  );
}
