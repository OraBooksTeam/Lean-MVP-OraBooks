import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import AdminPageShell from '@/components/AdminPageShell';
import { api } from '../api';

interface PlatformSettings {
  block_same_email_domain: boolean;
  partner_commission_for_staff_viewer: boolean;
  audit_retention_days: number;
  jwt_expiry: number;
  refresh_token_expiry: number;
}

const defaults: PlatformSettings = {
  block_same_email_domain: false,
  partner_commission_for_staff_viewer: false,
  audit_retention_days: 365,
  jwt_expiry: 900,
  refresh_token_expiry: 604800,
};

export default function AdminSettings() {
  const [settings, setSettings] = useState<PlatformSettings>(defaults);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  useEffect(() => {
    setLoading(true);
    setError('');
    api.platformSettingsGet().then((res) => {
      if (res.error) {
        setError(res.error);
      } else if (res.data) {
        setSettings({
          block_same_email_domain: Boolean(res.data.block_same_email_domain),
          partner_commission_for_staff_viewer: Boolean(res.data.partner_commission_for_staff_viewer),
          audit_retention_days: Number(res.data.audit_retention_days) || defaults.audit_retention_days,
          jwt_expiry: Number(res.data.jwt_expiry) || defaults.jwt_expiry,
          refresh_token_expiry: Number(res.data.refresh_token_expiry) || defaults.refresh_token_expiry,
        });
      }
      setLoading(false);
    });
  }, []);

  const handleSubmit = (event: React.FormEvent) => {
    event.preventDefault();
    setSaving(true);
    setError('');
    setMessage('');
    api.platformSettingsSave(settings).then((res) => {
      if (res.error) {
        setError(res.error);
      } else {
        setMessage('Settings saved.');
        if (res.data) {
          setSettings({
            block_same_email_domain: Boolean(res.data.block_same_email_domain),
            partner_commission_for_staff_viewer: Boolean(res.data.partner_commission_for_staff_viewer),
            audit_retention_days: Number(res.data.audit_retention_days),
            jwt_expiry: Number(res.data.jwt_expiry),
            refresh_token_expiry: Number(res.data.refresh_token_expiry),
          });
        }
      }
      setSaving(false);
    });
  };

  return (
    <AdminPageShell
      title="Platform Settings"
      description="Security, authentication, and audit retention defaults."
    >
      {error && <p className="text-sm text-danger">{error}</p>}
      {message && <p className="text-sm text-success">{message}</p>}

      {loading ? (
        <div className="h-64 animate-pulse rounded-2xl border border-border bg-white" />
      ) : (
        <form onSubmit={handleSubmit} className="glass-panel overflow-hidden">
          <div className="divide-y divide-border">
            <label className="flex flex-col gap-2 p-6 sm:flex-row sm:items-start sm:justify-between">
              <span className="text-sm font-semibold text-ink">Block same email domain</span>
              <span className="flex items-start gap-3 text-sm text-ink-secondary sm:max-w-xl">
                <input
                  type="checkbox"
                  className="mt-1 h-4 w-4 rounded border-border text-primary"
                  checked={settings.block_same_email_domain}
                  onChange={(e) =>
                    setSettings((prev) => ({ ...prev, block_same_email_domain: e.target.checked }))
                  }
                />
                Block partner attribution when customer email domain matches partner email domain
              </span>
            </label>

            <label className="flex flex-col gap-2 p-6 sm:flex-row sm:items-start sm:justify-between">
              <span className="text-sm font-semibold text-ink">Staff/viewer commission access</span>
              <span className="flex items-start gap-3 text-sm text-ink-secondary sm:max-w-xl">
                <input
                  type="checkbox"
                  className="mt-1 h-4 w-4 rounded border-border text-primary"
                  checked={settings.partner_commission_for_staff_viewer}
                  onChange={(e) =>
                    setSettings((prev) => ({
                      ...prev,
                      partner_commission_for_staff_viewer: e.target.checked,
                    }))
                  }
                />
                Allow Staff and Viewer roles to access partner commission dashboard
              </span>
            </label>

            <label className="flex flex-col gap-2 p-6 sm:flex-row sm:items-center sm:justify-between">
              <span className="text-sm font-semibold text-ink">Audit log retention (days)</span>
              <div className="sm:max-w-xs">
                <input
                  type="number"
                  min={30}
                  max={3650}
                  className="w-full rounded-lg border border-border px-3 py-2 text-sm"
                  value={settings.audit_retention_days}
                  onChange={(e) =>
                    setSettings((prev) => ({
                      ...prev,
                      audit_retention_days: Number(e.target.value),
                    }))
                  }
                />
                <p className="mt-1 text-xs text-slate-500">Days to retain audit logs before archival</p>
              </div>
            </label>

            <label className="flex flex-col gap-2 p-6 sm:flex-row sm:items-center sm:justify-between">
              <span className="text-sm font-semibold text-ink">JWT expiry (seconds)</span>
              <div className="sm:max-w-xs">
                <input
                  type="number"
                  min={60}
                  max={86400}
                  className="w-full rounded-lg border border-border px-3 py-2 text-sm"
                  value={settings.jwt_expiry}
                  onChange={(e) =>
                    setSettings((prev) => ({ ...prev, jwt_expiry: Number(e.target.value) }))
                  }
                />
                <p className="mt-1 text-xs text-slate-500">Default: 900 (15 minutes)</p>
              </div>
            </label>

            <label className="flex flex-col gap-2 p-6 sm:flex-row sm:items-center sm:justify-between">
              <span className="text-sm font-semibold text-ink">Refresh token expiry (seconds)</span>
              <div className="sm:max-w-xs">
                <input
                  type="number"
                  min={3600}
                  max={2592000}
                  className="w-full rounded-lg border border-border px-3 py-2 text-sm"
                  value={settings.refresh_token_expiry}
                  onChange={(e) =>
                    setSettings((prev) => ({
                      ...prev,
                      refresh_token_expiry: Number(e.target.value),
                    }))
                  }
                />
                <p className="mt-1 text-xs text-slate-500">Default: 604800 (7 days)</p>
              </div>
            </label>
          </div>

          <div className="border-t border-border bg-slate-50/60 px-6 py-4">
            <Button type="submit" disabled={saving}>
              {saving ? 'Saving…' : 'Save settings'}
            </Button>
          </div>
        </form>
      )}
    </AdminPageShell>
  );
}
