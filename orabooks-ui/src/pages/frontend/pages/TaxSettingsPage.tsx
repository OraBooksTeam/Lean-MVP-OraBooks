import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { Info, Lock, Percent, Plus, RefreshCw, Save } from 'lucide-react';

type TaxConfig = {
  id: number;
  jurisdiction: string;
  default_tax_rate: number;
  tax_type: 'VAT' | 'GST' | 'Sales Tax' | 'None';
  is_active: number;
  override_reasons?: string[];
  updated_at?: string | null;
};

type Jurisdiction = {
  jurisdiction_code: string;
  name: string;
  default_tax_rate: number;
  tax_type: string;
};

const TAX_TYPES = ['VAT', 'GST', 'Sales Tax', 'None'] as const;

export default function TaxSettingsPage() {
  const [context, setContext] = useState<any>(null);
  const [configs, setConfigs] = useState<TaxConfig[]>([]);
  const [jurisdictions, setJurisdictions] = useState<Jurisdiction[]>([]);
  const [lockStatus, setLockStatus] = useState<{ tax_locked?: boolean; message?: string } | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [saving, setSaving] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({
    jurisdiction: 'US',
    default_tax_rate: '0',
    tax_type: 'Sales Tax' as TaxConfig['tax_type'],
    is_active: true,
  });

  const orgId = context?.organization?.id;
  const taxLocked = Boolean(lockStatus?.tax_locked);

  const load = async () => {
    setLoading(true);
    setError('');
    setSuccess('');
    const ctx = await api.frontendContext();
    if (ctx.error) {
      setError(ctx.error || 'Unable to load organization context.');
      setLoading(false);
      return;
    }

    const nextContext = (ctx as any).data;
    setContext(nextContext);
    const nextOrgId = nextContext?.organization?.id;
    if (!nextOrgId) {
      setError('Organization is not set up yet.');
      setLoading(false);
      return;
    }

    const [configsRes, jurisdictionsRes] = await Promise.all([
      api.taxListConfigs(nextOrgId),
      api.taxListJurisdictions(nextOrgId),
    ]);

    if (configsRes.error) {
      setError(configsRes.error || 'Unable to load tax settings.');
    } else {
      const data = (configsRes as any).data || {};
      setConfigs(data.configs || []);
      setLockStatus(data.lock_status || null);
    }

    if (!jurisdictionsRes.error) {
      setJurisdictions((jurisdictionsRes as any).data?.jurisdictions || []);
    }

    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const openNewConfig = (jurisdictionCode?: string) => {
    const jurisdiction = jurisdictions.find((j) => j.jurisdiction_code === jurisdictionCode);
    setForm({
      jurisdiction: jurisdictionCode || jurisdictions[0]?.jurisdiction_code || 'US',
      default_tax_rate: String(jurisdiction?.default_tax_rate ?? 0),
      tax_type: (jurisdiction?.tax_type as TaxConfig['tax_type']) || 'Sales Tax',
      is_active: true,
    });
    setShowForm(true);
    setSuccess('');
    setError('');
  };

  const handleJurisdictionChange = (code: string) => {
    const jurisdiction = jurisdictions.find((j) => j.jurisdiction_code === code);
    setForm((prev) => ({
      ...prev,
      jurisdiction: code,
      default_tax_rate: String(jurisdiction?.default_tax_rate ?? prev.default_tax_rate),
      tax_type: (jurisdiction?.tax_type as TaxConfig['tax_type']) || prev.tax_type,
    }));
  };

  const handleSave = async () => {
    if (!orgId) return;
    setSaving(true);
    setError('');
    setSuccess('');

    const res = await api.taxSaveConfig(orgId, {
      jurisdiction: form.jurisdiction,
      default_tax_rate: parseFloat(form.default_tax_rate) || 0,
      tax_type: form.tax_type,
      is_active: form.is_active ? 1 : 0,
    });

    if (res.error) {
      setError(res.error);
    } else {
      setSuccess('Tax configuration saved.');
      setShowForm(false);
      await load();
    }
    setSaving(false);
  };

  return (
    <ClientShell
      title="Tax Settings"
      eyebrow="SL-305 Tax governance"
      organization={context?.organization}
      isPartner={context?.organization?.organization_type === 'partner'}
    >
      <div className="space-y-5">
        <div className="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50/80 p-4 text-sm text-sky-900">
          <Info className="mt-0.5 h-4 w-4 shrink-0" />
          <p>
            Configure default tax rates per jurisdiction. Overrides require an approved reason code and are blocked when the fiscal period is closed.
          </p>
        </div>

        {taxLocked && (
          <div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <Lock className="mt-0.5 h-4 w-4 shrink-0" />
            <p>{lockStatus?.message || 'Tax settings are locked for closed fiscal periods.'}</p>
          </div>
        )}

        <div className="flex flex-wrap items-center gap-3">
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
          {!taxLocked && (
            <Button size="sm" onClick={() => openNewConfig()}>
              <Plus className="h-4 w-4" />
              Add jurisdiction
            </Button>
          )}
        </div>

        {error && (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">
            {error}
          </div>
        )}
        {success && (
          <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">
            {success}
          </div>
        )}

        <div className="glass-panel overflow-hidden">
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Jurisdiction</th>
                <th className="px-5 py-3 font-semibold">Tax type</th>
                <th className="px-5 py-3 font-semibold">Default rate</th>
                <th className="px-5 py-3 font-semibold">Status</th>
                <th className="px-5 py-3 font-semibold">Updated</th>
                <th className="px-5 py-3 font-semibold" />
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={6} className="px-5 py-8 text-center text-slate-500">Loading tax settings…</td>
                </tr>
              ) : configs.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-5 py-8 text-center text-slate-500">
                    No tax configurations yet. Add a jurisdiction to get started.
                  </td>
                </tr>
              ) : (
                configs.map((config) => (
                  <tr key={config.id} className="border-b border-border/70">
                    <td className="px-5 py-3 font-medium text-ink">{config.jurisdiction}</td>
                    <td className="px-5 py-3 text-slate-600">{config.tax_type}</td>
                    <td className="px-5 py-3 text-slate-600">
                      <span className="inline-flex items-center gap-1">
                        <Percent className="h-3.5 w-3.5" />
                        {config.default_tax_rate.toFixed(2)}%
                      </span>
                    </td>
                    <td className="px-5 py-3">
                      <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${config.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'}`}>
                        {config.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="px-5 py-3 text-slate-500">{config.updated_at || '—'}</td>
                    <td className="px-5 py-3 text-right">
                      {!taxLocked && (
                        <Button
                          variant="secondary"
                          size="sm"
                          onClick={() => {
                            setForm({
                              jurisdiction: config.jurisdiction,
                              default_tax_rate: String(config.default_tax_rate),
                              tax_type: config.tax_type,
                              is_active: Boolean(config.is_active),
                            });
                            setShowForm(true);
                            setSuccess('');
                            setError('');
                          }}
                        >
                          Edit
                        </Button>
                      )}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {showForm && !taxLocked && (
          <div className="glass-panel space-y-4 p-5">
            <h3 className="text-base font-semibold text-ink">Tax configuration</h3>
            <div className="grid gap-4 sm:grid-cols-2">
              <label className="space-y-1.5 text-sm">
                <span className="font-medium text-slate-700">Jurisdiction</span>
                <select
                  value={form.jurisdiction}
                  onChange={(e) => handleJurisdictionChange(e.target.value)}
                  className="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm"
                >
                  {jurisdictions.map((j) => (
                    <option key={j.jurisdiction_code} value={j.jurisdiction_code}>
                      {j.name} ({j.jurisdiction_code})
                    </option>
                  ))}
                </select>
              </label>
              <label className="space-y-1.5 text-sm">
                <span className="font-medium text-slate-700">Tax type</span>
                <select
                  value={form.tax_type}
                  onChange={(e) => setForm((prev) => ({ ...prev, tax_type: e.target.value as TaxConfig['tax_type'] }))}
                  className="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm"
                >
                  {TAX_TYPES.map((type) => (
                    <option key={type} value={type}>{type}</option>
                  ))}
                </select>
              </label>
              <label className="space-y-1.5 text-sm">
                <span className="font-medium text-slate-700">Default rate (%)</span>
                <Input
                  type="number"
                  min="0"
                  max="100"
                  step="0.01"
                  value={form.default_tax_rate}
                  onChange={(e) => setForm((prev) => ({ ...prev, default_tax_rate: e.target.value }))}
                />
              </label>
              <label className="flex items-center gap-2 pt-7 text-sm">
                <input
                  type="checkbox"
                  checked={form.is_active}
                  onChange={(e) => setForm((prev) => ({ ...prev, is_active: e.target.checked }))}
                  className="rounded border-border"
                />
                <span className="font-medium text-slate-700">Active</span>
              </label>
            </div>
            <div className="flex gap-2">
              <Button onClick={handleSave} disabled={saving}>
                <Save className="h-4 w-4" />
                {saving ? 'Saving…' : 'Save configuration'}
              </Button>
              <Button variant="secondary" onClick={() => setShowForm(false)}>Cancel</Button>
            </div>
          </div>
        )}
      </div>
    </ClientShell>
  );
}
