import { useEffect, useMemo, useState } from 'react';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { FileText, Info, Percent, RefreshCw } from 'lucide-react';

type Invoice = {
  id: number;
  invoice_number?: string;
  invoice_date?: string;
  due_date?: string;
  workflow_status?: string;
  payment_status?: string;
  total_amount?: string | number;
  tax_amount?: string | number;
  tax_rate?: string | number;
  tax_override_reason?: string | null;
  currency?: string;
};

type TaxConfig = {
  jurisdiction: string;
  override_reasons?: string[];
};

const DEFAULT_REASONS = [
  'WRONG_AI_CLASSIFICATION',
  'LOCAL_TAX_RULE',
  'MANUAL_JURISDICTION_ADJUSTMENT',
  'CUSTOMER_EXEMPTION',
  'REGIONAL_COMPLIANCE_OVERRIDE',
];

export default function InvoicesPage() {
  const [context, setContext] = useState<any>(null);
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [taxConfigs, setTaxConfigs] = useState<TaxConfig[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [overrideInvoice, setOverrideInvoice] = useState<Invoice | null>(null);
  const [overrideRate, setOverrideRate] = useState('');
  const [overrideReason, setOverrideReason] = useState('');
  const [overrideJurisdiction, setOverrideJurisdiction] = useState('US');
  const [taxLocked, setTaxLocked] = useState(false);
  const [saving, setSaving] = useState(false);

  const orgId = context?.organization?.id;

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

    const [invoicesRes, taxRes] = await Promise.all([
      api.invoicesList(nextOrgId, { limit: 100 }),
      api.taxListConfigs(nextOrgId),
    ]);

    if (invoicesRes.error) {
      setError(invoicesRes.error || 'Unable to load invoices.');
    } else {
      setInvoices((invoicesRes as any).data?.invoices || []);
    }

    if (!taxRes.error) {
      setTaxConfigs((taxRes as any).data?.configs || []);
    }

    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const reasonOptions = useMemo(() => {
    const config = taxConfigs.find((c) => c.jurisdiction === overrideJurisdiction);
    const reasons = config?.override_reasons?.length ? config.override_reasons : DEFAULT_REASONS;
    return reasons;
  }, [taxConfigs, overrideJurisdiction]);

  const canOverride = (invoice: Invoice) =>
    ['draft', 'sent'].includes(invoice.workflow_status || '');

  const openOverride = async (invoice: Invoice) => {
    setOverrideInvoice(invoice);
    setOverrideRate(String(Number(invoice.tax_rate || 0)));
    setOverrideReason('');
    setOverrideJurisdiction(taxConfigs[0]?.jurisdiction || 'US');
    setError('');
    setSuccess('');

    if (orgId) {
      const lockRes = await api.taxLockStatus(orgId, invoice.invoice_date);
      setTaxLocked(Boolean((lockRes as any).data?.tax_locked));
    } else {
      setTaxLocked(false);
    }
  };

  const closeOverride = () => {
    setOverrideInvoice(null);
    setOverrideRate('');
    setOverrideReason('');
    setTaxLocked(false);
  };

  const preview = useMemo(() => {
    if (!overrideInvoice) return null;
    const total = Number(overrideInvoice.total_amount || 0);
    const tax = Number(overrideInvoice.tax_amount || 0);
    const taxBase = Math.max(0, Math.round((total - tax) * 100) / 100);
    const rate = parseFloat(overrideRate) || 0;
    const newTax = Math.round(taxBase * (rate / 100) * 100) / 100;
    return {
      taxBase,
      newTax,
      newTotal: Math.round((taxBase + newTax) * 100) / 100,
    };
  }, [overrideInvoice, overrideRate]);

  const handleApplyOverride = async () => {
    if (!orgId || !overrideInvoice) return;
    if (!overrideReason) {
      setError('A reason code is required for tax overrides.');
      return;
    }

    setSaving(true);
    setError('');
    setSuccess('');

    const res = await api.invoiceOverrideTax(
      orgId,
      overrideInvoice.id,
      parseFloat(overrideRate) || 0,
      overrideReason,
      overrideJurisdiction
    );

    if (res.error) {
      setError(res.error);
    } else {
      setSuccess('Tax override applied.');
      closeOverride();
      await load();
    }
    setSaving(false);
  };

  return (
    <ClientShell
      title="Invoices"
      eyebrow="Accounts receivable"
      organization={context?.organization}
      isPartner={context?.organization?.organization_type === 'partner'}
    >
      <div className="space-y-5">
        <div className="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50/80 p-4 text-sm text-sky-900">
          <Info className="mt-0.5 h-4 w-4 shrink-0" />
          <p>
            Override tax on draft or sent invoices before posting. A reason code is required and recorded in the audit log. Overrides are locked after posting.
          </p>
        </div>

        <div className="flex justify-end">
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {error && !overrideInvoice && (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>
        )}
        {success && (
          <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">{success}</div>
        )}

        <div className="glass-panel overflow-hidden">
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Invoice</th>
                <th className="px-5 py-3 font-semibold">Due Date</th>
                <th className="px-5 py-3 font-semibold">Workflow</th>
                <th className="px-5 py-3 font-semibold">Payment</th>
                <th className="px-5 py-3 font-semibold">Tax</th>
                <th className="px-5 py-3 text-right font-semibold">Total</th>
                <th className="px-5 py-3 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={7} className="px-5 py-8 text-center text-slate-500">Loading invoices…</td></tr>
              ) : invoices.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-5 py-10 text-center">
                    <FileText className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No invoices found for this workspace.</p>
                  </td>
                </tr>
              ) : (
                invoices.map((invoice) => (
                  <tr key={invoice.id} className="hover:bg-slate-50/70">
                    <td className="px-5 py-3">
                      <div className="font-semibold text-ink">{invoice.invoice_number || `Invoice #${invoice.id}`}</div>
                      {invoice.tax_override_reason ? (
                        <span
                          className="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                          title={`Override reason: ${invoice.tax_override_reason}`}
                        >
                          Overridden
                        </span>
                      ) : null}
                    </td>
                    <td className="px-5 py-3 text-slate-600">{invoice.due_date || 'Not set'}</td>
                    <td className="px-5 py-3"><Badge value={invoice.workflow_status || 'draft'} /></td>
                    <td className="px-5 py-3"><Badge value={invoice.payment_status || 'unpaid'} /></td>
                    <td className="px-5 py-3 text-slate-600">
                      <span className="inline-flex items-center gap-1">
                        <Percent className="h-3.5 w-3.5" />
                        {Number(invoice.tax_rate || 0).toFixed(2)}%
                      </span>
                      <div className="text-xs text-slate-500">{money(invoice.tax_amount, invoice.currency)}</div>
                    </td>
                    <td className="px-5 py-3 text-right font-bold text-ink">{money(invoice.total_amount, invoice.currency)}</td>
                    <td className="px-5 py-3">
                      {canOverride(invoice) ? (
                        <Button size="sm" variant="secondary" onClick={() => void openOverride(invoice)}>
                          Override tax
                        </Button>
                      ) : (
                        <span className="text-xs text-slate-400">Locked</span>
                      )}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {overrideInvoice && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
            <div className="w-full max-w-lg rounded-2xl border border-border bg-white p-6 shadow-xl">
              <h3 className="text-lg font-semibold text-ink">Override tax</h3>
              <p className="mt-1 text-sm text-slate-600">
                {overrideInvoice.invoice_number || `Invoice #${overrideInvoice.id}`}
              </p>

              {taxLocked && (
                <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                  Tax is locked for this fiscal period. Overrides are not allowed.
                </div>
              )}

              {error && overrideInvoice && (
                <div className="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>
              )}

              <div className="mt-4 grid gap-4">
                <label className="space-y-1.5 text-sm">
                  <span className="font-medium text-slate-700">Jurisdiction</span>
                  <select
                    value={overrideJurisdiction}
                    onChange={(e) => setOverrideJurisdiction(e.target.value)}
                    disabled={taxLocked}
                    className="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm"
                  >
                    {(taxConfigs.length ? taxConfigs : [{ jurisdiction: 'US' }]).map((config) => (
                      <option key={config.jurisdiction} value={config.jurisdiction}>
                        {config.jurisdiction}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="space-y-1.5 text-sm">
                  <span className="font-medium text-slate-700">New tax rate (%)</span>
                  <Input
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    value={overrideRate}
                    onChange={(e) => setOverrideRate(e.target.value)}
                    disabled={taxLocked}
                  />
                </label>
                <label className="space-y-1.5 text-sm">
                  <span className="font-medium text-slate-700">Reason code</span>
                  <select
                    value={overrideReason}
                    onChange={(e) => setOverrideReason(e.target.value)}
                    disabled={taxLocked}
                    className="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm"
                  >
                    <option value="">Select a reason…</option>
                    {reasonOptions.map((reason) => (
                      <option key={reason} value={reason}>{formatReason(reason)}</option>
                    ))}
                  </select>
                </label>
                {preview && (
                  <div className="rounded-lg border border-border bg-slate-50 p-3 text-sm text-slate-700">
                    <div>Taxable base: {money(preview.taxBase, overrideInvoice.currency)}</div>
                    <div>New tax: {money(preview.newTax, overrideInvoice.currency)}</div>
                    <div className="font-semibold text-ink">New total: {money(preview.newTotal, overrideInvoice.currency)}</div>
                  </div>
                )}
              </div>

              <div className="mt-6 flex justify-end gap-2">
                <Button variant="secondary" onClick={closeOverride}>Cancel</Button>
                <Button onClick={handleApplyOverride} disabled={saving || taxLocked || !overrideReason}>
                  {saving ? 'Applying…' : 'Apply override'}
                </Button>
              </div>
            </div>
          </div>
        )}
      </div>
    </ClientShell>
  );
}

function Badge({ value }: { value: string }) {
  return <span className="badge border border-border bg-slate-50 text-slate-700">{value}</span>;
}

function money(value?: string | number, currency = 'USD') {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(Number(value || 0));
}

function formatReason(code: string) {
  return code.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase());
}
