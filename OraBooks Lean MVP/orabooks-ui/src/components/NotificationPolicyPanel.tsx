import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import { api } from '@/pages/frontend/api';

const fieldClass =
  'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';

export default function NotificationPolicyPanel({ orgId }: { orgId: number }) {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [health, setHealth] = useState<any[]>([]);
  const [policy, setPolicy] = useState({
    monthly_budget: '',
    retention_override_days: '',
    max_escalation_attempts: '3',
    mandatory_event_types: '',
    prohibited_channels: '',
  });

  useEffect(() => {
    if (!orgId) return;
    setLoading(true);
    Promise.all([
      api.notificationPolicyGet(orgId),
      api.notificationProviderHealth(orgId),
    ]).then(([policyRes, healthRes]) => {
      if (policyRes.error) setError(policyRes.error);
      else {
        const data = (policyRes as any).data || {};
        setPolicy({
          monthly_budget: data.monthly_budget != null ? String(data.monthly_budget) : '',
          retention_override_days: data.retention_override_days != null ? String(data.retention_override_days) : '',
          max_escalation_attempts: data.max_escalation_attempts != null ? String(data.max_escalation_attempts) : '3',
          mandatory_event_types: Array.isArray(data.mandatory_event_types) ? data.mandatory_event_types.join(', ') : '',
          prohibited_channels: Array.isArray(data.prohibited_channels) ? data.prohibited_channels.join(', ') : '',
        });
      }
      if (!healthRes.error) setHealth((healthRes as any).data || []);
      setLoading(false);
    });
  }, [orgId]);

  const save = async () => {
    setSaving(true);
    setError('');
    setSuccess('');
    const res = await api.notificationPolicySave(orgId, {
      monthly_budget: policy.monthly_budget || 0,
      retention_override_days: policy.retention_override_days || 0,
      max_escalation_attempts: policy.max_escalation_attempts || 3,
      mandatory_event_types: policy.mandatory_event_types.split(',').map((s) => s.trim()).filter(Boolean),
      prohibited_channels: policy.prohibited_channels.split(',').map((s) => s.trim()).filter(Boolean),
    });
    if (res.error) setError(res.error);
    else setSuccess('Notification policy saved.');
    setSaving(false);
  };

  if (loading) {
    return <div className="glass-panel p-6 text-sm text-slate-500">Loading notification policy…</div>;
  }

  return (
    <section className="glass-panel p-5">
      <h2 className="font-bold text-ink">Org Notification Policy</h2>
      <p className="mt-1 text-sm text-slate-600">Owner/admin: mandatory alerts, channel restrictions, budget, and retention.</p>

      {error && <p className="mt-3 text-sm text-red-600">{error}</p>}
      {success && <p className="mt-3 text-sm text-emerald-600">{success}</p>}

      <div className="mt-4 grid gap-3 sm:grid-cols-2">
        <label className="block text-sm">
          <span className="font-semibold text-ink">Monthly budget (USD)</span>
          <input
            type="number"
            min="0"
            step="0.01"
            className={`mt-1 ${fieldClass}`}
            value={policy.monthly_budget}
            onChange={(e) => setPolicy((prev) => ({ ...prev, monthly_budget: e.target.value }))}
          />
        </label>
        <label className="block text-sm">
          <span className="font-semibold text-ink">Retention override (days)</span>
          <input
            type="number"
            min="0"
            className={`mt-1 ${fieldClass}`}
            value={policy.retention_override_days}
            onChange={(e) => setPolicy((prev) => ({ ...prev, retention_override_days: e.target.value }))}
          />
        </label>
        <label className="block text-sm sm:col-span-2">
          <span className="font-semibold text-ink">Mandatory event types (comma-separated)</span>
          <input
            type="text"
            className={`mt-1 ${fieldClass}`}
            value={policy.mandatory_event_types}
            onChange={(e) => setPolicy((prev) => ({ ...prev, mandatory_event_types: e.target.value }))}
            placeholder="invoice_paid, journal_approved"
          />
        </label>
        <label className="block text-sm sm:col-span-2">
          <span className="font-semibold text-ink">Prohibited channels (comma-separated)</span>
          <input
            type="text"
            className={`mt-1 ${fieldClass}`}
            value={policy.prohibited_channels}
            onChange={(e) => setPolicy((prev) => ({ ...prev, prohibited_channels: e.target.value }))}
            placeholder="push"
          />
        </label>
        <label className="block text-sm">
          <span className="font-semibold text-ink">Max escalation attempts</span>
          <input
            type="number"
            min="1"
            max="10"
            className={`mt-1 ${fieldClass}`}
            value={policy.max_escalation_attempts}
            onChange={(e) => setPolicy((prev) => ({ ...prev, max_escalation_attempts: e.target.value }))}
          />
        </label>
      </div>

      {health.length > 0 && (
        <div className="mt-5 overflow-hidden rounded-xl border border-border">
          <div className="border-b border-border bg-slate-50/70 px-4 py-2 text-xs font-bold uppercase text-slate-500">
            Provider health
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border text-xs uppercase text-slate-500">
                <th className="px-4 py-2">Channel</th>
                <th className="px-4 py-2">Provider</th>
                <th className="px-4 py-2">Score</th>
                <th className="px-4 py-2">Latency</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {health.map((row: any, idx: number) => (
                <tr key={idx}>
                  <td className="px-4 py-2">{row.channel}</td>
                  <td className="px-4 py-2">{row.provider_name}</td>
                  <td className="px-4 py-2">{row.health_score}</td>
                  <td className="px-4 py-2">{row.avg_latency_ms}ms</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <div className="mt-4">
        <Button onClick={save} loading={saving}>Save notification policy</Button>
      </div>
    </section>
  );
}
