import { useEffect, useMemo, useState, type FormEvent } from 'react';
import Button from '@/components/Button';
import Input from '@/components/Input';
import ClientShell from '../components/ClientShell';
import { api } from '../api';
import { RefreshCw } from 'lucide-react';

const ORABOOKS_AJAX = (window as any).orabooks_ajax || {};

type Tab = 'policy' | 'health' | 'audit';

export default function NotificationAdminPage() {
  const [tab, setTab] = useState<Tab>('policy');
  const [context, setContext] = useState<any>(null);
  const [policy, setPolicy] = useState({
    monthly_budget: '',
    mandatory_event_types: [] as string[],
    prohibited_channels: [] as string[],
    retention_override_days: '',
    max_escalation_attempts: '3',
    escalation_fallback_chain: ['email', 'inapp'] as string[],
  });
  const [health, setHealth] = useState<any[]>([]);
  const [auditStart, setAuditStart] = useState(() => {
    const d = new Date();
    d.setDate(d.getDate() - 30);
    return d.toISOString().slice(0, 10);
  });
  const [auditEnd, setAuditEnd] = useState(() => new Date().toISOString().slice(0, 10));
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const orgId = Number(context?.organization?.id || context?.user?.org_id || 0);
  const isPlatformAdmin = Boolean(ORABOOKS_AJAX.is_admin);
  const canManage = isPlatformAdmin || context?.user?.role === 'owner';

  const loadPolicy = (resolvedOrgId: number) =>
    api.notificationPolicyGet(resolvedOrgId).then((res: any) => {
      if (res.error) return;
      const p = res.data || {};
      const parseList = (val: unknown) => {
        if (Array.isArray(val)) return val.map(String);
        if (typeof val === 'string' && val) {
          try {
            return JSON.parse(val);
          } catch {
            return [];
          }
        }
        return [];
      };
      setPolicy({
        monthly_budget: String(p.monthly_budget ?? ''),
        mandatory_event_types: parseList(p.mandatory_event_types),
        prohibited_channels: parseList(p.prohibited_channels),
        retention_override_days: String(p.retention_override_days ?? ''),
        max_escalation_attempts: String(p.max_escalation_attempts ?? '3'),
        escalation_fallback_chain: parseList(p.escalation_fallback_chain).length
          ? parseList(p.escalation_fallback_chain)
          : ['email', 'inapp'],
      });
    });

  const loadHealth = (resolvedOrgId: number) =>
    api.notificationProviderHealth(resolvedOrgId).then((res: any) => {
      if (!res.error) setHealth(res.data || []);
    });

  useEffect(() => {
    api.frontendContext().then((res: any) => {
      if (res.error) {
        setError(res.error || 'Please log in to manage notification settings.');
        setLoading(false);
        return;
      }
      const data = res.data;
      setContext(data);
      const resolvedOrgId = Number(data?.organization?.id || data?.user?.org_id || 0);
      const allowed = Boolean(ORABOOKS_AJAX.is_admin) || data?.user?.role === 'owner';
      if (!allowed) {
        setLoading(false);
        return;
      }
      if (!resolvedOrgId) {
        setError('Organization context is required.');
        setLoading(false);
        return;
      }
      Promise.all([loadPolicy(resolvedOrgId), loadHealth(resolvedOrgId)]).finally(() => setLoading(false));
    });
  }, []);

  const toggleList = (field: 'mandatory_event_types' | 'prohibited_channels' | 'escalation_fallback_chain', value: string) => {
    setPolicy((prev) => {
      const list = prev[field];
      const next = list.includes(value) ? list.filter((v) => v !== value) : [...list, value];
      return { ...prev, [field]: next };
    });
  };

  const savePolicy = async (e: FormEvent) => {
    e.preventDefault();
    setError('');
    setMessage('');
    if (!orgId) {
      setError('Organization context is required.');
      return;
    }
    const res = await api.notificationPolicySave(orgId, policy);
    if (res.error) setError(res.error);
    else setMessage('Policy saved.');
  };

  const exportAudit = async (e: FormEvent) => {
    e.preventDefault();
    setError('');
    setMessage('');
    if (!orgId) {
      setError('Organization context is required.');
      return;
    }
    const res = await api.notificationAuditExport(orgId, auditStart, auditEnd);
    if (res.error) {
      setError(res.error);
      return;
    }
    const blob = new Blob([JSON.stringify(res.data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `notification-audit-bundle-${auditStart}-to-${auditEnd}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    setMessage('Audit bundle downloaded.');
  };

  const tabs = useMemo(
    () => [
      { id: 'policy' as Tab, label: 'Org Policy' },
      { id: 'health' as Tab, label: 'Provider Health' },
      { id: 'audit' as Tab, label: 'Audit Export' },
    ],
    []
  );

  if (!canManage) {
    return (
      <ClientShell title="Access denied" eyebrow="Notifications">
        <p className="text-sm text-danger">
          Only organization owners can manage notification policies. Contact your org owner for access.
        </p>
      </ClientShell>
    );
  }

  return (
    <ClientShell
      title="Notification Settings (Admin)"
      eyebrow="Notifications"
      organization={context?.organization}
    >
      <div className="mb-4 flex flex-wrap gap-2">
        {tabs.map((t) => (
          <button
            key={t.id}
            type="button"
            onClick={() => setTab(t.id)}
            className={`rounded-lg px-3 py-2 text-sm font-semibold transition ${
              tab === t.id ? 'bg-primary text-white' : 'bg-primary/10 text-white hover:bg-primary/25'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {loading ? (
        <div className="h-48 animate-pulse rounded-2xl border border-border bg-white" />
      ) : tab === 'policy' ? (
        <form onSubmit={savePolicy} className="glass-panel space-y-4 p-6">
          <Input
            label="Monthly Budget ($)"
            type="number"
            step="0.01"
            min="0"
            value={policy.monthly_budget}
            onChange={(e) => setPolicy((p) => ({ ...p, monthly_budget: e.target.value }))}
          />
          <div>
            <p className="mb-2 text-sm font-medium text-ink">Mandatory event types</p>
            <label className="mr-4 text-sm">
              <input
                type="checkbox"
                checked={policy.mandatory_event_types.includes('security_alert')}
                onChange={() => toggleList('mandatory_event_types', 'security_alert')}
              />{' '}
              Security Alert
            </label>
            <label className="text-sm">
              <input
                type="checkbox"
                checked={policy.mandatory_event_types.includes('system_maintenance')}
                onChange={() => toggleList('mandatory_event_types', 'system_maintenance')}
              />{' '}
              System Maintenance
            </label>
          </div>
          <div>
            <p className="mb-2 text-sm font-medium text-ink">Prohibited channels</p>
            {['push', 'email'].map((ch) => (
              <label key={ch} className="mr-4 text-sm">
                <input
                  type="checkbox"
                  checked={policy.prohibited_channels.includes(ch)}
                  onChange={() => toggleList('prohibited_channels', ch)}
                />{' '}
                {ch}
              </label>
            ))}
          </div>
          <Input
            label="Retention override (days)"
            type="number"
            min="30"
            max="3650"
            value={policy.retention_override_days}
            onChange={(e) => setPolicy((p) => ({ ...p, retention_override_days: e.target.value }))}
          />
          <Input
            label="Max escalation attempts"
            type="number"
            min="1"
            max="10"
            value={policy.max_escalation_attempts}
            onChange={(e) => setPolicy((p) => ({ ...p, max_escalation_attempts: e.target.value }))}
          />
          <div>
            <p className="mb-2 text-sm font-medium text-ink">Escalation fallback chain</p>
            {['email', 'push', 'inapp'].map((ch) => (
              <label key={ch} className="mr-4 text-sm">
                <input
                  type="checkbox"
                  checked={policy.escalation_fallback_chain.includes(ch)}
                  onChange={() => toggleList('escalation_fallback_chain', ch)}
                />{' '}
                {ch}
              </label>
            ))}
          </div>
          {error && <p className="text-sm text-danger">{error}</p>}
          {message && <p className="text-sm text-emerald-700">{message}</p>}
          <Button type="submit">Save Policy</Button>
        </form>
      ) : tab === 'health' ? (
        <div className="space-y-4">
          <Button variant="secondary" size="sm" onClick={() => orgId && loadHealth(orgId)}>
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
          <div className="glass-panel overflow-hidden">
            <table className="min-w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border bg-slate-50/60 text-xs uppercase text-slate-500">
                  <th className="px-5 py-3">Channel</th>
                  <th className="px-5 py-3">Provider</th>
                  <th className="px-5 py-3">Region</th>
                  <th className="px-5 py-3">Success</th>
                  <th className="px-5 py-3">Latency</th>
                  <th className="px-5 py-3">Score</th>
                  <th className="px-5 py-3">Last Outage</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {health.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-5 py-6 text-center text-slate-500">
                      No provider data yet.
                    </td>
                  </tr>
                ) : (
                  health.map((row: any) => (
                    <tr key={`${row.channel}-${row.provider_name}`}>
                      <td className="px-5 py-3">{row.channel}</td>
                      <td className="px-5 py-3">{row.provider_name}</td>
                      <td className="px-5 py-3">{row.region}</td>
                      <td className="px-5 py-3">{Number(row.success_rate || 0).toFixed(2)}%</td>
                      <td className="px-5 py-3">{row.avg_latency_ms}ms</td>
                      <td className="px-5 py-3">{row.health_score}</td>
                      <td className="px-5 py-3">{row.last_outage_at || '—'}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      ) : (
        <form onSubmit={exportAudit} className="glass-panel max-w-lg space-y-4 p-6">
          <p className="text-sm text-slate-600">Download signed JSON bundle with delivery proofs.</p>
          <Input label="Start date" type="date" value={auditStart} onChange={(e) => setAuditStart(e.target.value)} />
          <Input label="End date" type="date" value={auditEnd} onChange={(e) => setAuditEnd(e.target.value)} />
          {error && <p className="text-sm text-danger">{error}</p>}
          {message && <p className="text-sm text-emerald-700">{message}</p>}
          <Button type="submit">Export Audit Bundle</Button>
        </form>
      )}
    </ClientShell>
  );
}
