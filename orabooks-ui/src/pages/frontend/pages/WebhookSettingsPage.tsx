import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import ClientShell from '../components/ClientShell';
import { api } from '../api';

export default function WebhookSettingsPage() {
  const [urls, setUrls] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.webhookSettingsGet().then((res: any) => {
      if (res.error) setError(res.error);
      else setUrls(res.data?.urls || '');
      setLoading(false);
    });
  }, []);

  const save = async () => {
    setError('');
    setMessage('');
    const res = await api.webhookSettingsSave(urls);
    if (res.error) setError(res.error);
    else {
      setUrls(res.data?.urls || urls);
      setMessage('Webhook settings saved.');
    }
  };

  return (
    <ClientShell title="Webhook Settings" eyebrow="Platform">
      <div className="max-w-3xl space-y-4 rounded-2xl border border-border bg-white p-5">
        <p className="text-sm text-slate-600">
          Add one webhook URL per line. Domain events from SL-302 are dispatched through SL-303
          as `webhook_dispatch` background jobs.
        </p>
        <p className="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">
          Localhost URLs are useful for local tests only; external webhook services cannot reach
          your local machine or private port.
        </p>
        {error && <p className="text-sm text-danger">{error}</p>}
        {message && <p className="text-sm text-success">{message}</p>}
        <textarea
          value={urls}
          onChange={(e) => setUrls(e.target.value)}
          disabled={loading}
          rows={8}
          className="w-full rounded-xl border border-border px-3 py-2 text-sm"
          placeholder="https://webhook.site/..."
        />
        <Button type="button" onClick={save} disabled={loading}>
          Save Webhooks
        </Button>
      </div>
    </ClientShell>
  );
}
