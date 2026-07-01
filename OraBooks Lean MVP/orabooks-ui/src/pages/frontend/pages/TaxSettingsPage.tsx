import { useEffect, useMemo, useState, type Dispatch, type ReactNode, type SetStateAction } from 'react';
import { createPortal } from 'react-dom';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { Calculator, Info, Lock, Percent, Plus, RefreshCw, Save } from 'lucide-react';

type TaxConfig = {
  id: number;
  jurisdiction: string;
  default_tax_rate: number;
  tax_type: 'VAT' | 'GST' | 'Sales Tax' | 'None';
  is_active: number;
  exemption_certificate_url?: string | null;
  override_reasons?: string[];
  updated_at?: string | null;
};

type Jurisdiction = {
  jurisdiction_code: string;
  name: string;
  default_tax_rate: number;
  tax_type: string;
};

type TaxSnapshot = {
  id: number;
  transaction_type: string;
  transaction_id: number;
  taxable_amount: number;
  tax_rate: number;
  tax_amount: number;
  jurisdiction: string;
  tax_type: string;
  override_reason?: string | null;
  created_at?: string;
};

const TAX_TYPES = ['VAT', 'GST', 'Sales Tax', 'None'] as const;

const DEFAULT_JURISDICTIONS: Jurisdiction[] = [
  { jurisdiction_code: 'US', name: 'United States Sales Tax', default_tax_rate: 0, tax_type: 'Sales Tax' },
  { jurisdiction_code: 'BD', name: 'Bangladesh VAT', default_tax_rate: 15, tax_type: 'VAT' },
  { jurisdiction_code: 'IN', name: 'India GST', default_tax_rate: 18, tax_type: 'GST' },
];

const DEFAULT_OVERRIDE_REASONS = [
  'WRONG_AI_CLASSIFICATION',
  'LOCAL_TAX_RULE',
  'MANUAL_JURISDICTION_ADJUSTMENT',
  'CUSTOMER_EXEMPTION',
  'REGIONAL_COMPLIANCE_OVERRIDE',
];

const OVERRIDE_REASON_LABELS: Record<string, string> = {
  WRONG_AI_CLASSIFICATION: 'Wrong AI classification',
  LOCAL_TAX_RULE: 'Local tax rule',
  MANUAL_JURISDICTION_ADJUSTMENT: 'Manual jurisdiction adjustment',
  CUSTOMER_EXEMPTION: 'Customer exemption',
  REGIONAL_COMPLIANCE_OVERRIDE: 'Regional compliance override',
};

function reasonLabel(code: string) {
  return OVERRIDE_REASON_LABELS[code] || code.replace(/_/g, ' ').toLowerCase();
}

export default function TaxSettingsPage() {
  const [context, setContext] = useState<any>(null);
  const [configs, setConfigs] = useState<TaxConfig[]>([]);
  const [jurisdictions, setJurisdictions] = useState<Jurisdiction[]>([]);
  const [snapshots, setSnapshots] = useState<TaxSnapshot[]>([]);
  const [lockStatus, setLockStatus] = useState<{ tax_locked?: boolean; message?: string } | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [saving, setSaving] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [formMode, setFormMode] = useState<'add' | 'edit'>('add');
  const [form, setForm] = useState({
    jurisdiction: 'US',
    default_tax_rate: '0',
    tax_type: 'Sales Tax' as TaxConfig['tax_type'],
    is_active: true,
    exemption_certificate_url: '',
    override_reasons: [...DEFAULT_OVERRIDE_REASONS] as string[],
  });
  const [calcForm, setCalcForm] = useState({
    amount: '100',
    jurisdiction: 'US',
    customer_tax_status: 'taxable',
    product_type: 'standard',
  });
  const [calcResult, setCalcResult] = useState<{
    tax_rate: number;
    tax_amount: number;
    tax_type: string;
    rule_id?: string;
  } | null>(null);
  const [calcLoading, setCalcLoading] = useState(false);

  const orgId = context?.organization?.id;
  const taxLocked = Boolean(lockStatus?.tax_locked);

  const jurisdictionOptions = useMemo(() => {
    if (jurisdictions.length) return jurisdictions;
    if (configs.length) {
      return configs.map((c) => ({
        jurisdiction_code: c.jurisdiction,
        name: c.jurisdiction,
        default_tax_rate: c.default_tax_rate,
        tax_type: c.tax_type,
      }));
    }
    return DEFAULT_JURISDICTIONS;
  }, [jurisdictions, configs]);

  const addableJurisdictions = useMemo(() => {
    const configured = new Set(configs.map((c) => c.jurisdiction));
    const available = jurisdictionOptions.filter((j) => !configured.has(j.jurisdiction_code));
    return available.length ? available : jurisdictionOptions;
  }, [jurisdictionOptions, configs]);

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

    const [configsRes, jurisdictionsRes, snapshotsRes] = await Promise.all([
      api.taxListConfigs(nextOrgId),
      api.taxListJurisdictions(nextOrgId),
      api.taxListSnapshots(nextOrgId, 20),
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
    if (!snapshotsRes.error) {
      setSnapshots((snapshotsRes as any).data?.snapshots || []);
    }

    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const openNewConfig = (jurisdictionCode?: string) => {
    const pool = addableJurisdictions;
    const jurisdiction = pool.find((j) => j.jurisdiction_code === jurisdictionCode) || pool[0];
    setFormMode('add');
    setForm({
      jurisdiction: jurisdictionCode || jurisdiction?.jurisdiction_code || 'US',
      default_tax_rate: String(jurisdiction?.default_tax_rate ?? 0),
      tax_type: (jurisdiction?.tax_type as TaxConfig['tax_type']) || 'Sales Tax',
      is_active: true,
      exemption_certificate_url: '',
      override_reasons: [...DEFAULT_OVERRIDE_REASONS],
    });
    setShowForm(true);
    setSuccess('');
  };

  const openEditConfig = (config: TaxConfig) => {
    setFormMode('edit');
    setForm({
      jurisdiction: config.jurisdiction,
      default_tax_rate: String(config.default_tax_rate),
      tax_type: config.tax_type,
      is_active: Boolean(config.is_active),
      exemption_certificate_url: config.exemption_certificate_url || '',
      override_reasons: config.override_reasons?.length ? [...config.override_reasons] : [...DEFAULT_OVERRIDE_REASONS],
    });
    setShowForm(true);
    setSuccess('');
  };

  const handleJurisdictionChange = (code: string) => {
    const jurisdiction = jurisdictionOptions.find((j) => j.jurisdiction_code === code);
    setForm((prev) => ({
      ...prev,
      jurisdiction: code,
      default_tax_rate: String(jurisdiction?.default_tax_rate ?? prev.default_tax_rate),
      tax_type: (jurisdiction?.tax_type as TaxConfig['tax_type']) || prev.tax_type,
    }));
  };

  const toggleOverrideReason = (reason: string) => {
    setForm((prev) => {
      const selected = new Set(prev.override_reasons);
      if (selected.has(reason)) {
        selected.delete(reason);
      } else {
        selected.add(reason);
      }
      const next = Array.from(selected);
      return { ...prev, override_reasons: next.length ? next : [reason] };
    });
  };

  const handleSave = async () => {
    if (!orgId) return;
    setSaving(true);
    setError('');
    setSuccess('');

    const session = await api.verifySession();
    if (session.error) {
      setError(session.error || 'Your session expired. Please refresh and log in again.');
      setSaving(false);
      return;
    }

    const res = await api.taxSaveConfig(orgId, {
      jurisdiction: form.jurisdiction,
      default_tax_rate: parseFloat(form.default_tax_rate) || 0,
      tax_type: form.tax_type,
      is_active: form.is_active ? 1 : 0,
      exemption_certificate_url: form.exemption_certificate_url.trim() || undefined,
      override_reasons: JSON.stringify(form.override_reasons),
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

  const runCalculator = async () => {
    if (!orgId) return;
    setCalcLoading(true);
    setCalcResult(null);
    const res = await api.taxCalculate({
      org_id: orgId,
      amount: parseFloat(calcForm.amount) || 0,
      jurisdiction: calcForm.jurisdiction,
      customer_tax_status: calcForm.customer_tax_status,
      product_type: calcForm.product_type,
    });
    if (res.error) {
      setError(res.error);
    } else {
      const data = (res as any).data || {};
      setCalcResult({
        tax_rate: Number(data.tax_rate || 0),
        tax_amount: Number(data.tax_amount || 0),
        tax_type: data.tax_type || 'Sales Tax',
        rule_id: data.rule_id,
      });
    }
    setCalcLoading(false);
  };

  return (
    <ClientShell
      title="Tax Settings"
      eyebrow="Tax governance"
      organization={context?.organization}
      isPartner={context?.organization?.organization_type === 'partner'}
    >
      <div className="space-y-5">
        <div className="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50/80 p-4 text-sm text-sky-900">
          <Info className="mt-0.5 h-4 w-4 shrink-0" />
          <p>
            Configure default tax rates per jurisdiction, allowed override reason codes, and exemption certificates.
            Tax is calculated centrally; overrides on invoices and expenses require an approved reason and are blocked when the fiscal period is closed.
          </p>
        </div>

        {taxLocked && (
          <div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <Lock className="mt-0.5 h-4 w-4 shrink-0" />
            <div>
              <p className="font-semibold">Tax locked</p>
              <p>{lockStatus?.message || 'Tax settings are locked for closed fiscal periods.'}</p>
            </div>
          </div>
        )}

        <div className="flex flex-wrap items-center gap-3">
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
          {orgId && !loading && (
            <Button size="sm" type="button" onClick={() => openNewConfig()}>
              <Plus className="h-4 w-4" />
              Add jurisdiction
            </Button>
          )}
        </div>

        {error && (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>
        )}
        {success && (
          <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">{success}</div>
        )}

        <div className="grid gap-5 lg:grid-cols-2">
          <div className="glass-panel overflow-hidden">
            <div className="border-b border-border px-5 py-4">
              <h2 className="font-bold text-ink">Org tax configurations</h2>
            </div>
            <table className="min-w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                  <th className="px-5 py-3 font-semibold">Jurisdiction</th>
                  <th className="px-5 py-3 font-semibold">Type</th>
                  <th className="px-5 py-3 font-semibold">Rate</th>
                  <th className="px-5 py-3 font-semibold">Status</th>
                  <th className="px-5 py-3 font-semibold" />
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr><td colSpan={5} className="px-5 py-8 text-center text-slate-500">Loading tax settings…</td></tr>
                ) : configs.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-5 py-8 text-center text-slate-500">
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
                      <td className="px-5 py-3 text-right">
                        <Button variant="secondary" size="sm" type="button" onClick={() => openEditConfig(config)}>
                          Edit
                        </Button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          <div className="glass-panel p-5">
            <div className="flex items-center gap-2">
              <Calculator className="h-4 w-4 text-slate-500" />
              <h2 className="font-bold text-ink">Tax calculator</h2>
            </div>
            <p className="mt-1 text-sm text-slate-600">Preview engine output before creating invoices or expenses.</p>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              <label className="space-y-1 text-sm">
                <span className="font-medium text-slate-700">Taxable amount</span>
                <Input type="number" min="0" step="0.01" value={calcForm.amount} onChange={(e) => setCalcForm((p) => ({ ...p, amount: e.target.value }))} />
              </label>
              <label className="space-y-1 text-sm">
                <span className="font-medium text-slate-700">Jurisdiction</span>
                <select value={calcForm.jurisdiction} onChange={(e) => setCalcForm((p) => ({ ...p, jurisdiction: e.target.value }))} className="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                  {jurisdictionOptions.map((j) => (
                    <option key={j.jurisdiction_code} value={j.jurisdiction_code}>{j.name} ({j.jurisdiction_code})</option>
                  ))}
                </select>
              </label>
              <label className="space-y-1 text-sm">
                <span className="font-medium text-slate-700">Customer tax status</span>
                <select value={calcForm.customer_tax_status} onChange={(e) => setCalcForm((p) => ({ ...p, customer_tax_status: e.target.value }))} className="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                  <option value="taxable">Taxable</option>
                  <option value="exempt">Exempt</option>
                </select>
              </label>
              <label className="space-y-1 text-sm">
                <span className="font-medium text-slate-700">Product type</span>
                <select value={calcForm.product_type} onChange={(e) => setCalcForm((p) => ({ ...p, product_type: e.target.value }))} className="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                  <option value="standard">Standard</option>
                  <option value="exempt">Exempt</option>
                </select>
              </label>
            </div>
            <Button className="mt-4" size="sm" onClick={() => { void runCalculator(); }} disabled={calcLoading}>
              {calcLoading ? 'Calculating…' : 'Calculate tax'}
            </Button>
            {calcResult && (
              <div className="mt-4 rounded-lg border border-border bg-slate-50 p-3 text-sm">
                <div>Rate: {calcResult.tax_rate.toFixed(2)}% ({calcResult.tax_type})</div>
                <div>Tax amount: {calcResult.tax_amount.toFixed(2)}</div>
                {calcResult.rule_id && <div className="text-xs text-slate-500">Rule: {calcResult.rule_id}</div>}
              </div>
            )}
          </div>
        </div>

        {showForm && createPortal(
          <TaxConfigModal
            title={formMode === 'add' ? 'Add jurisdiction' : 'Edit tax configuration'}
            taxLocked={taxLocked}
            lockMessage={lockStatus?.message}
            form={form}
            formMode={formMode}
            jurisdictionOptions={formMode === 'add' ? addableJurisdictions : jurisdictionOptions}
            saving={saving}
            onClose={() => setShowForm(false)}
            onSave={() => { void handleSave(); }}
            onJurisdictionChange={handleJurisdictionChange}
            onFormChange={setForm}
            onToggleReason={toggleOverrideReason}
          />,
          document.body
        )}

        <div className="glass-panel overflow-hidden">
          <div className="border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Recent tax snapshots</h2>
            <p className="text-sm text-slate-600">Immutable tax records captured at transaction post time.</p>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Date</th>
                <th className="px-5 py-3 font-semibold">Transaction</th>
                <th className="px-5 py-3 font-semibold">Jurisdiction</th>
                <th className="px-5 py-3 text-right font-semibold">Rate</th>
                <th className="px-5 py-3 text-right font-semibold">Tax</th>
                <th className="px-5 py-3 font-semibold">Override</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={6} className="px-5 py-8 text-center text-slate-500">Loading snapshots…</td></tr>
              ) : snapshots.length === 0 ? (
                <tr><td colSpan={6} className="px-5 py-8 text-center text-slate-500">No tax snapshots recorded yet.</td></tr>
              ) : (
                snapshots.map((snapshot) => (
                  <tr key={snapshot.id} className="border-b border-border/70">
                    <td className="px-5 py-3 text-slate-600">{snapshot.created_at || '—'}</td>
                    <td className="px-5 py-3 capitalize text-ink">{snapshot.transaction_type} #{snapshot.transaction_id}</td>
                    <td className="px-5 py-3 text-slate-600">{snapshot.jurisdiction}</td>
                    <td className="px-5 py-3 text-right text-slate-600">{snapshot.tax_rate.toFixed(2)}%</td>
                    <td className="px-5 py-3 text-right font-medium text-ink">{snapshot.tax_amount.toFixed(2)}</td>
                    <td className="px-5 py-3 text-xs text-slate-500">{snapshot.override_reason ? reasonLabel(snapshot.override_reason) : '—'}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </ClientShell>
  );
}

type TaxFormState = {
  jurisdiction: string;
  default_tax_rate: string;
  tax_type: TaxConfig['tax_type'];
  is_active: boolean;
  exemption_certificate_url: string;
  override_reasons: string[];
};

function TaxConfigModal({
  title,
  taxLocked,
  lockMessage,
  form,
  formMode,
  jurisdictionOptions,
  saving,
  onClose,
  onSave,
  onJurisdictionChange,
  onFormChange,
  onToggleReason,
}: {
  title: string;
  taxLocked: boolean;
  lockMessage?: string;
  form: TaxFormState;
  formMode: 'add' | 'edit';
  jurisdictionOptions: Jurisdiction[];
  saving: boolean;
  onClose: () => void;
  onSave: () => void;
  onJurisdictionChange: (code: string) => void;
  onFormChange: Dispatch<SetStateAction<TaxFormState>>;
  onToggleReason: (reason: string) => void;
}) {
  return (
    <div
      className="fixed inset-0 z-[200] flex items-center justify-center bg-slate-900/50 p-4"
      onClick={onClose}
      role="dialog"
      aria-modal="true"
      aria-labelledby="tax-config-modal-title"
    >
      <div
        className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-border bg-white p-6 shadow-2xl"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-start justify-between gap-3">
          <div>
            <h3 id="tax-config-modal-title" className="text-lg font-semibold text-ink">{title}</h3>
            <p className="mt-1 text-sm text-slate-600">
              Set default tax rate, type, and allowed override reasons for this jurisdiction.
            </p>
          </div>
          <button type="button" onClick={onClose} className="text-sm font-medium text-slate-500 hover:text-slate-700">
            Close
          </button>
        </div>

        {taxLocked && (
          <div className="mt-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
            <Lock className="mt-0.5 h-4 w-4 shrink-0" />
            <p>{lockMessage || 'Tax configuration is locked for closed fiscal periods. Saving is disabled.'}</p>
          </div>
        )}

        <div className="mt-5 grid gap-4 sm:grid-cols-2">
          <Field label="Jurisdiction">
            {formMode === 'add' ? (
              <select
                value={form.jurisdiction}
                onChange={(e) => onJurisdictionChange(e.target.value)}
                className="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm"
              >
                {jurisdictionOptions.map((j) => (
                  <option key={j.jurisdiction_code} value={j.jurisdiction_code}>
                    {j.name} ({j.jurisdiction_code})
                  </option>
                ))}
              </select>
            ) : (
              <Input value={form.jurisdiction} readOnly disabled className="bg-slate-50" />
            )}
          </Field>
          <Field label="Tax type">
            <select
              value={form.tax_type}
              onChange={(e) => onFormChange((prev) => ({ ...prev, tax_type: e.target.value as TaxConfig['tax_type'] }))}
              disabled={taxLocked}
              className="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm disabled:bg-slate-50"
            >
              {TAX_TYPES.map((type) => (
                <option key={type} value={type}>{type}</option>
              ))}
            </select>
          </Field>
          <Field label="Default rate (%)">
            <Input
              type="number"
              min="0"
              max="100"
              step="0.01"
              value={form.default_tax_rate}
              onChange={(e) => onFormChange((prev) => ({ ...prev, default_tax_rate: e.target.value }))}
              disabled={taxLocked}
            />
          </Field>
          <Field label="Exemption certificate URL">
            <Input
              type="url"
              value={form.exemption_certificate_url}
              onChange={(e) => onFormChange((prev) => ({ ...prev, exemption_certificate_url: e.target.value }))}
              placeholder="https://..."
              disabled={taxLocked}
            />
          </Field>
          <label className="flex items-center gap-2 pt-7 text-sm sm:col-span-2">
            <input
              type="checkbox"
              checked={form.is_active}
              onChange={(e) => onFormChange((prev) => ({ ...prev, is_active: e.target.checked }))}
              disabled={taxLocked}
              className="rounded border-border"
            />
            <span className="font-medium text-slate-700">Active configuration</span>
          </label>
        </div>

        <div className="mt-5">
          <p className="text-sm font-medium text-slate-700">Allowed override reason codes</p>
          <p className="text-xs text-slate-500">Only selected codes can be used when overriding tax on invoices or expenses.</p>
          <div className="mt-2 grid gap-2 sm:grid-cols-2">
            {DEFAULT_OVERRIDE_REASONS.map((reason) => (
              <label key={reason} className="flex items-start gap-2 rounded-lg border border-border bg-white px-3 py-2 text-sm">
                <input
                  type="checkbox"
                  checked={form.override_reasons.includes(reason)}
                  onChange={() => onToggleReason(reason)}
                  disabled={taxLocked}
                  className="mt-0.5 rounded border-border"
                />
                <span>
                  <span className="font-medium text-ink">{reasonLabel(reason)}</span>
                  <span className="block text-xs text-slate-500">{reason}</span>
                </span>
              </label>
            ))}
          </div>
        </div>

        <div className="mt-6 flex flex-wrap gap-2">
          <Button type="button" onClick={onSave} disabled={saving || taxLocked}>
            <Save className="h-4 w-4" />
            {saving ? 'Saving…' : 'Save configuration'}
          </Button>
          <Button type="button" variant="secondary" onClick={onClose}>Cancel</Button>
        </div>
      </div>
    </div>
  );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="block space-y-1.5 text-sm">
      <span className="font-medium text-slate-700">{label}</span>
      {children}
    </label>
  );
}
