import { useEffect, useMemo, useState, type ReactNode } from 'react';
import WpLink from '../components/WpLink';
import { getSearchParam } from '../lib/wp-routing';

import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import ResourceAttachmentsPanel from '../components/ResourceAttachmentsPanel';
import { FileText, Info, Paperclip, Percent, Plus, RefreshCw, Sparkles, Wallet } from 'lucide-react';

type Invoice = {
  id: number;
  customer_id?: number;
  invoice_number?: string;
  invoice_date?: string;
  due_date?: string;
  workflow_status?: string;
  payment_status?: string;
  total_amount?: string | number;
  paid_amount?: string | number;
  tax_amount?: string | number;
  tax_rate?: string | number;
  tax_type?: string | null;
  tax_jurisdiction?: string | null;
  tax_override_reason?: string | null;
  tax_override_by?: number | null;
  tax_override_at?: string | null;
  currency?: string;
  classification?: {
    status?: string;
    suggested_account_code?: string | null;
    account_confidence?: number | null;
    tax_hints?: { tax_type?: string; tax_rate?: number };
    reason?: string | null;
    low_confidence?: boolean;
  };
};

type Customer = { id: number; display_name?: string | null; email?: string };
type TaxConfig = { jurisdiction: string; tax_type?: string; override_reasons?: string[] };

const DEFAULT_REASONS = [
  'WRONG_AI_CLASSIFICATION',
  'LOCAL_TAX_RULE',
  'MANUAL_JURISDICTION_ADJUSTMENT',
  'CUSTOMER_EXEMPTION',
  'REGIONAL_COMPLIANCE_OVERRIDE',
];

const REASON_LABELS: Record<string, string> = {
  WRONG_AI_CLASSIFICATION: 'Wrong AI classification',
  LOCAL_TAX_RULE: 'Local tax rule',
  MANUAL_JURISDICTION_ADJUSTMENT: 'Manual jurisdiction adjustment',
  CUSTOMER_EXEMPTION: 'Customer exemption',
  REGIONAL_COMPLIANCE_OVERRIDE: 'Regional compliance override',
};

export default function InvoicesPage() {
  const [context, setContext] = useState<any>(null);
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [taxConfigs, setTaxConfigs] = useState<TaxConfig[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [saving, setSaving] = useState(false);
  const [classificationBusyId, setClassificationBusyId] = useState<number | null>(null);
  const [classificationOverrideCode, setClassificationOverrideCode] = useState('');
  const [accounts, setAccounts] = useState<Array<{ id?: number; code?: string; account_code?: string; name?: string }>>([]);

  const [showCreate, setShowCreate] = useState(false);
  const [createForm, setCreateForm] = useState({
    customer_id: '',
    invoice_date: new Date().toISOString().slice(0, 10),
    due_date: '',
    due_days: '30',
    use_due_date: false,
    subtotal_amount: '',
    jurisdiction: 'US',
    currency: 'USD',
    description: '',
  });
  const [createPreview, setCreatePreview] = useState<{ tax_rate: number; tax_amount: number; total_amount: number; tax_type?: string } | null>(null);

  const [paymentInvoice, setPaymentInvoice] = useState<Invoice | null>(null);
  const [paymentForm, setPaymentForm] = useState({
    amount: '',
    payment_date: new Date().toISOString().slice(0, 10),
    payment_method: 'bank_transfer',
    reference: '',
    notes: '',
  });

  const [actionInvoiceId, setActionInvoiceId] = useState<number | null>(null);

  const [overrideInvoice, setOverrideInvoice] = useState<Invoice | null>(null);
  const [overrideRate, setOverrideRate] = useState('');
  const [overrideReason, setOverrideReason] = useState('');
  const [overrideJurisdiction, setOverrideJurisdiction] = useState('US');
  const [taxLocked, setTaxLocked] = useState(false);
  const [selectedInvoice, setSelectedInvoice] = useState<Invoice | null>(null);
  const [cancelInvoice, setCancelInvoice] = useState<Invoice | null>(null);
  const [cancelReason, setCancelReason] = useState('');

  const orgId = context?.organization?.id;
  const permissions: string[] = context?.permissions || [];
  const canCreateInvoice = permissions.includes('create_invoice');
  const canRecordPayment = permissions.includes('create_invoice');
  const canOverrideTax =
    permissions.includes('manage_settings')
    || permissions.includes('manage_org_settings')
    || permissions.includes('approve_journal');

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

    const customerFilter = Number(getSearchParam('customer_id') || 0);

    const [invoicesRes, taxRes, customersRes, coaRes] = await Promise.all([
      api.invoicesList(nextOrgId, {
        limit: 100,
        customer_id: customerFilter > 0 ? customerFilter : undefined,
      }),
      api.taxListConfigs(nextOrgId),
      api.customersList(nextOrgId, { limit: 100 }),
      api.coaGet(nextOrgId),
    ]);

    if (invoicesRes.error) setError(invoicesRes.error || 'Unable to load invoices.');
    else setInvoices((invoicesRes as any).data?.invoices || []);

    if (!taxRes.error) setTaxConfigs((taxRes as any).data?.configs || []);
    if (!customersRes.error) {
      const list = (customersRes as any).data?.customers || [];
      setCustomers(list);
      if (customerFilter > 0) {
        setCreateForm((prev) => ({ ...prev, customer_id: String(customerFilter) }));
      }
    }
    if (!coaRes.error) {
      setAccounts((coaRes as any).data?.accounts || []);
    }

    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  useEffect(() => {
    const invoiceId = Number(getSearchParam('invoice_id') || 0);
    if (invoiceId <= 0 || invoices.length === 0) {
      return;
    }

    const match = invoices.find((invoice) => invoice.id === invoiceId);
    if (match) {
      setSelectedInvoice(match);
    }
  }, [invoices]);

  const reasonOptions = useMemo(() => {
    const config = taxConfigs.find((c) => c.jurisdiction === overrideJurisdiction);
    return config?.override_reasons?.length ? config.override_reasons : DEFAULT_REASONS;
  }, [taxConfigs, overrideJurisdiction]);

  const canOverride = (invoice: Invoice) =>
    canOverrideTax && ['draft', 'sent'].includes(invoice.workflow_status || '');
  const canPay = (invoice: Invoice) =>
    canRecordPayment &&
    !['paid', 'cancelled'].includes(invoice.payment_status || '') &&
    invoice.workflow_status !== 'cancelled';

  const canSend = (invoice: Invoice) =>
    canCreateInvoice && invoice.workflow_status === 'draft';

  const canPost = (invoice: Invoice) =>
    canCreateInvoice && ['draft', 'sent'].includes(invoice.workflow_status || '');

  const canCancel = (invoice: Invoice) =>
    canCreateInvoice
    && ['draft', 'sent'].includes(invoice.workflow_status || '')
    && !['paid', 'partial'].includes(invoice.payment_status || '')
    && Number(invoice.paid_amount || 0) <= 0;

  const runInvoiceAction = async (action: 'send' | 'post', invoiceId: number) => {
    if (!orgId) return;
    setActionInvoiceId(invoiceId);
    setError('');
    const res = action === 'send'
      ? await api.invoiceSend(orgId, invoiceId)
      : await api.invoicePost(orgId, invoiceId);
    if (res.error) setError(res.error);
    else {
      setSuccess(action === 'send' ? 'Invoice sent.' : 'Invoice posted to AR.');
      await load();
    }
    setActionInvoiceId(null);
  };

  const handleCancelInvoice = async () => {
    if (!orgId || !cancelInvoice) return;
    setActionInvoiceId(cancelInvoice.id);
    setError('');
    const res = await api.invoiceCancel(orgId, cancelInvoice.id, cancelReason.trim());
    if (res.error) setError(res.error);
    else {
      setSuccess('Invoice cancelled.');
      setCancelInvoice(null);
      setCancelReason('');
      await load();
    }
    setActionInvoiceId(null);
  };

  const remainingBalance = (invoice: Invoice) =>
    Math.max(0, Number(invoice.total_amount || 0) - Number(invoice.paid_amount || 0));

  const previewCreateTax = async () => {
    if (!orgId || !createForm.subtotal_amount) {
      setCreatePreview(null);
      return;
    }
    const res = await api.taxCalculate({
      org_id: orgId,
      amount: parseFloat(createForm.subtotal_amount) || 0,
      jurisdiction: createForm.jurisdiction,
    });
    if (!res.error) {
      const data = (res as any).data;
      setCreatePreview({
        tax_rate: Number(data.tax_rate || 0),
        tax_amount: Number(data.tax_amount || 0),
        total_amount: Number(data.taxable_amount || 0) + Number(data.tax_amount || 0),
        tax_type: data.tax_type || undefined,
      });
    }
  };

  useEffect(() => {
    if (!showCreate) return;
    const timer = setTimeout(() => { void previewCreateTax(); }, 300);
    return () => clearTimeout(timer);
  }, [showCreate, createForm.subtotal_amount, createForm.jurisdiction, orgId]);

  const handleCreateInvoice = async () => {
    if (!orgId || !createForm.customer_id || !createForm.subtotal_amount) {
      setError('Customer and subtotal are required.');
      return;
    }

    setSaving(true);
    setError('');
    const payload: Record<string, unknown> = {
      org_id: orgId,
      customer_id: parseInt(createForm.customer_id, 10),
      invoice_date: createForm.invoice_date,
      subtotal_amount: parseFloat(createForm.subtotal_amount) || 0,
      jurisdiction: createForm.jurisdiction,
      currency: createForm.currency || 'USD',
      description: createForm.description,
      workflow_status: 'draft',
    };

    if (createForm.use_due_date && createForm.due_date) {
      payload.due_date = createForm.due_date;
    } else {
      payload.due_days = parseInt(createForm.due_days, 10) || 30;
    }

    const res = await api.invoiceCreate(payload);

    if (res.error) {
      setError(res.error);
    } else {
      setSuccess('Invoice created.');
      setShowCreate(false);
      setCreateForm({
        customer_id: getSearchParam('customer_id') || '',
        invoice_date: new Date().toISOString().slice(0, 10),
        due_date: '',
        due_days: '30',
        use_due_date: false,
        subtotal_amount: '',
        jurisdiction: taxConfigs[0]?.jurisdiction || 'US',
        currency: 'USD',
        description: '',
      });
      setCreatePreview(null);
      await load();
    }
    setSaving(false);
  };

  const openPayment = (invoice: Invoice) => {
    const remaining = remainingBalance(invoice);
    setPaymentInvoice(invoice);
    setPaymentForm({
      amount: remaining > 0 ? String(remaining) : '',
      payment_date: new Date().toISOString().slice(0, 10),
      payment_method: 'bank_transfer',
      reference: '',
      notes: '',
    });
    setError('');
  };

  const handleRecordPayment = async () => {
    if (!orgId || !paymentInvoice) return;
    setSaving(true);
    setError('');

    const res = await api.recordPayment({
      org_id: orgId,
      invoice_id: paymentInvoice.id,
      amount: parseFloat(paymentForm.amount) || 0,
      payment_date: paymentForm.payment_date,
      payment_method: paymentForm.payment_method,
      reference: paymentForm.reference,
      notes: paymentForm.notes,
    });

    if (res.error) {
      setError(res.error);
    } else {
      setSuccess('Payment recorded.');
      setPaymentInvoice(null);
      await load();
    }
    setSaving(false);
  };

  const openOverride = async (invoice: Invoice) => {
    setOverrideInvoice(invoice);
    setOverrideRate(String(Number(invoice.tax_rate || 0)));
    setOverrideJurisdiction(invoice.tax_jurisdiction || taxConfigs[0]?.jurisdiction || 'US');

    const aiHintRate = invoice.classification?.tax_hints?.tax_rate;
    const currentRate = Number(invoice.tax_rate || 0);
    if (
      aiHintRate != null
      && Math.abs(Number(aiHintRate) - currentRate) > 0.001
      && !invoice.tax_override_reason
    ) {
      setOverrideRate(String(aiHintRate));
      setOverrideReason('WRONG_AI_CLASSIFICATION');
    } else {
      setOverrideReason('');
    }

    setError('');

    if (orgId) {
      const lockRes = await api.taxLockStatus(orgId, invoice.invoice_date);
      setTaxLocked(Boolean((lockRes as any).data?.tax_locked));
    } else {
      setTaxLocked(false);
    }
  };

  const overridePreview = useMemo(() => {
    if (!overrideInvoice) return null;
    const total = Number(overrideInvoice.total_amount || 0);
    const tax = Number(overrideInvoice.tax_amount || 0);
    const taxBase = Math.max(0, Math.round((total - tax) * 100) / 100);
    const rate = parseFloat(overrideRate) || 0;
    const newTax = Math.round(taxBase * (rate / 100) * 100) / 100;
    return { taxBase, newTax, newTotal: Math.round((taxBase + newTax) * 100) / 100 };
  }, [overrideInvoice, overrideRate]);

  const handleApplyOverride = async () => {
    if (!orgId || !overrideInvoice || !overrideReason) {
      setError('A reason code is required for tax overrides.');
      return;
    }

    setSaving(true);
    setError('');
    const res = await api.invoiceOverrideTax(
      orgId,
      overrideInvoice.id,
      parseFloat(overrideRate) || 0,
      overrideReason,
      overrideJurisdiction
    );

    if (res.error) setError(res.error);
    else {
      setSuccess('Tax override applied.');
      setOverrideInvoice(null);
      await load();
    }
    setSaving(false);
  };

  const handleClearOverride = async () => {
    if (!orgId || !overrideInvoice) return;

    setSaving(true);
    setError('');
    const res = await api.invoiceClearTaxOverride(
      orgId,
      overrideInvoice.id,
      overrideJurisdiction || overrideInvoice.tax_jurisdiction || ''
    );

    if (res.error) {
      setError(res.error);
    } else {
      setSuccess('Tax override cleared. Rate recalculated from tax engine.');
      setOverrideInvoice(null);
      await load();
    }
    setSaving(false);
  };

  const runClassification = async (invoiceId: number) => {
    setClassificationBusyId(invoiceId);
    setError('');
    const res = await api.classificationRun('invoice', invoiceId, false);
    if (res.error) setError(res.error);
    else {
      setSuccess('Classification updated.');
      await load();
    }
    setClassificationBusyId(null);
  };

  const applyClassification = async (invoiceId: number) => {
    setClassificationBusyId(invoiceId);
    setError('');
    const res = await api.classificationApply('invoice', invoiceId);
    if (res.error) setError(res.error);
    else {
      setSuccess('Classification suggestions applied.');
      await load();
    }
    setClassificationBusyId(null);
  };

  const overrideClassification = async (invoiceId: number) => {
    if (!classificationOverrideCode) {
      setError('Select an account code to override classification.');
      return;
    }

    setClassificationBusyId(invoiceId);
    setError('');
    const res = await api.classificationOverride('invoice', invoiceId, classificationOverrideCode);
    if (res.error) setError(res.error);
    else {
      setSuccess('Classification override applied.');
      setClassificationOverrideCode('');
      await load();
    }
    setClassificationBusyId(null);
  };

  return (
    <ClientShell
      title="Invoices"
      eyebrow="SL-021 Accounts receivable"
      organization={context?.organization}
      isPartner={context?.organization?.organization_type === 'partner'}
    >
      <div className="space-y-5">
        <div className="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50/80 p-4 text-sm text-sky-900">
          <Info className="mt-0.5 h-4 w-4 shrink-0" />
          <p>
            Create draft invoices with automatic tax calculation, send to customers, post to AR, and record payments.
          </p>
        </div>

        <div className="flex flex-wrap justify-end gap-2">
          {canCreateInvoice && (
            <Button size="sm" onClick={() => { setShowCreate(true); setError(''); setSuccess(''); }}>
              <Plus className="h-4 w-4" />
              Create invoice
            </Button>
          )}
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {error && !overrideInvoice && !paymentInvoice && !showCreate && (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>
        )}
        {success && (
          <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">{success}</div>
        )}

        {selectedInvoice && orgId && (
          <div className="glass-panel p-5">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
              <div>
                <h2 className="font-bold text-ink">
                  {selectedInvoice.invoice_number || `Invoice #${selectedInvoice.id}`}
                </h2>
                <p className="text-sm text-slate-600">
                  Due {selectedInvoice.due_date || '—'} · {money(selectedInvoice.total_amount, selectedInvoice.currency)}
                </p>
              </div>
              <Button variant="secondary" size="sm" onClick={() => setSelectedInvoice(null)}>
                Close
              </Button>
            </div>
            <div className="mt-4">
              <ResourceAttachmentsPanel
                orgId={orgId}
                resourceType="invoice"
                resourceId={selectedInvoice.id}
                title="Invoice files"
              />
            </div>
            <div className="mt-4 rounded-xl border border-border bg-slate-50/70 p-4">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p className="text-sm font-semibold text-ink">Tax (VAT / GST / Sales Tax)</p>
                  <p className="mt-1 text-sm text-slate-700">
                    {invoiceTaxLabel(selectedInvoice)} · {Number(selectedInvoice.tax_rate || 0).toFixed(2)}% ·{' '}
                    {money(selectedInvoice.tax_amount, selectedInvoice.currency)}
                  </p>
                  {selectedInvoice.tax_override_reason && (
                    <span
                      className="mt-2 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                      title={`Tax manually changed. Reason: ${formatReason(selectedInvoice.tax_override_reason)}`}
                    >
                      Overridden
                    </span>
                  )}
                </div>
                {canOverride(selectedInvoice) && (
                  <Button
                    size="sm"
                    variant="secondary"
                    title="Override tax rate. Reason code required for audit."
                    onClick={() => void openOverride(selectedInvoice)}
                  >
                    Override Tax
                  </Button>
                )}
              </div>
              {selectedInvoice.classification?.tax_hints?.tax_type && (
                <p className="mt-2 text-xs text-slate-500">
                  AI suggestion: {selectedInvoice.classification.tax_hints.tax_type}{' '}
                  {selectedInvoice.classification.tax_hints.tax_rate ?? 0}% (human override has final authority)
                </p>
              )}
              {canOverride(selectedInvoice) && (
                <p className="mt-2 text-xs text-amber-700">Override will be locked after posting.</p>
              )}
            </div>
            <div className="mt-4 rounded-xl border border-primary/20 bg-primary/5 p-4">
              <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2 text-sm font-semibold text-ink">
                  <Sparkles className="h-4 w-4 text-primary" />
                  AI Classification
                </div>
                <span className="badge border border-primary/20 bg-white text-primary">
                  {selectedInvoice.classification?.status || 'pending'}
                </span>
              </div>
              <div className="mt-3 grid gap-2 text-sm text-slate-700 md:grid-cols-2">
                <p>
                  <strong>Suggested account:</strong>{' '}
                  {selectedInvoice.classification?.suggested_account_code || '—'}
                  {selectedInvoice.classification?.account_confidence != null && (
                    <span className="ml-2 badge border border-slate-200 bg-slate-50 text-slate-700">
                      {Number(selectedInvoice.classification.account_confidence).toFixed(1)}%
                    </span>
                  )}
                </p>
                <p>
                  <strong>Tax hint:</strong>{' '}
                  {selectedInvoice.classification?.tax_hints?.tax_type
                    ? `${selectedInvoice.classification.tax_hints.tax_type} ${selectedInvoice.classification.tax_hints.tax_rate ?? 0}%`
                    : '—'}
                </p>
                {selectedInvoice.classification?.reason && (
                  <p className="md:col-span-2 text-xs text-slate-500">{selectedInvoice.classification.reason}</p>
                )}
              </div>
              {selectedInvoice.classification?.low_confidence && (
                <p className="mt-2 text-xs font-medium text-amber-700">Low confidence. Please verify account mapping.</p>
              )}
              <div className="mt-3 flex flex-wrap gap-2">
                <Button size="sm" variant="secondary" onClick={() => void runClassification(selectedInvoice.id)} disabled={classificationBusyId === selectedInvoice.id}>
                  <RefreshCw className="h-4 w-4" />
                  Rerun
                </Button>
                <Button size="sm" onClick={() => void applyClassification(selectedInvoice.id)} disabled={classificationBusyId === selectedInvoice.id || selectedInvoice.classification?.status !== 'processed'}>
                  Apply
                </Button>
                <select
                  value={classificationOverrideCode}
                  onChange={(e) => setClassificationOverrideCode(e.target.value)}
                  className="min-w-[220px] rounded-lg border border-border px-2 py-1.5 text-xs"
                >
                  <option value="">Select override account…</option>
                  {accounts.map((a) => {
                    const code = a.account_code || a.code || '';
                    return (
                      <option key={String(a.id || code)} value={code}>
                        {code} {a.name ? `- ${a.name}` : ''}
                      </option>
                    );
                  })}
                </select>
                <Button size="sm" variant="secondary" onClick={() => void overrideClassification(selectedInvoice.id)} disabled={classificationBusyId === selectedInvoice.id || !classificationOverrideCode}>
                  Override
                </Button>
              </div>
            </div>
          </div>
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
                    <p className="mt-2 text-sm text-slate-500">
                      {canCreateInvoice
                        ? 'No invoices found. Create your first invoice.'
                        : 'No invoices found.'}
                    </p>
                  </td>
                </tr>
              ) : invoices.map((invoice) => (
                <tr key={invoice.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3">
                    <div className="font-semibold text-ink">{invoice.invoice_number || `Invoice #${invoice.id}`}</div>
                    {invoice.tax_override_reason && (
                      <span className="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800" title={`Override: ${invoice.tax_override_reason}`}>
                        Overridden
                      </span>
                    )}
                  </td>
                  <td className="px-5 py-3 text-slate-600">{invoice.due_date || '—'}</td>
                  <td className="px-5 py-3"><Badge value={invoice.workflow_status || 'draft'} /></td>
                  <td className="px-5 py-3"><Badge value={invoice.payment_status || 'unpaid'} /></td>
                  <td className="px-5 py-3 text-slate-600">
                    <span className="inline-flex items-center gap-1">
                      <Percent className="h-3.5 w-3.5" />
                      {invoiceTaxLabel(invoice)} {Number(invoice.tax_rate || 0).toFixed(2)}%
                    </span>
                    <div className="text-xs text-slate-500">{money(invoice.tax_amount, invoice.currency)}</div>
                  </td>
                  <td className="px-5 py-3 text-right font-bold text-ink">{money(invoice.total_amount, invoice.currency)}</td>
                  <td className="px-5 py-3">
                    <div className="flex flex-wrap gap-1">
                      <Button size="sm" variant="secondary" onClick={() => setSelectedInvoice(invoice)}>
                        View
                      </Button>
                      {canSend(invoice) && (
                        <Button size="sm" variant="secondary" disabled={actionInvoiceId === invoice.id} onClick={() => void runInvoiceAction('send', invoice.id)}>
                          Send
                        </Button>
                      )}
                      {canPost(invoice) && (
                        <Button size="sm" variant="secondary" disabled={actionInvoiceId === invoice.id} onClick={() => void runInvoiceAction('post', invoice.id)}>
                          Post
                        </Button>
                      )}
                      {canPay(invoice) && (
                        <Button size="sm" variant="secondary" onClick={() => openPayment(invoice)}>
                          <Wallet className="h-3.5 w-3.5" />
                          Pay
                        </Button>
                      )}
                      {canCancel(invoice) && (
                        <Button size="sm" variant="secondary" disabled={actionInvoiceId === invoice.id} onClick={() => { setCancelInvoice(invoice); setCancelReason(''); setError(''); }}>
                          Cancel
                        </Button>
                      )}
                      {canOverride(invoice) && (
                        <Button
                          size="sm"
                          variant="secondary"
                          title="Manually change tax rate."
                          onClick={() => void openOverride(invoice)}
                        >
                          Override Tax
                        </Button>
                      )}
                      <WpLink to={`/attachments?resource_type=invoice&resource_id=${invoice.id}`}>
                        <Button size="sm" variant="secondary">
                          <Paperclip className="h-3.5 w-3.5" />
                          Files
                        </Button>
                      </WpLink>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {showCreate && canCreateInvoice && (
          <Modal title="Create invoice" onClose={() => setShowCreate(false)}>
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
            <div className="grid gap-4">
              <Field label="Customer">
                <select
                  value={createForm.customer_id}
                  onChange={(e) => setCreateForm((p) => ({ ...p, customer_id: e.target.value }))}
                  className="w-full rounded-lg border border-border px-3 py-2.5 text-sm"
                >
                  <option value="">Select customer…</option>
                  {customers.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.display_name?.trim() || c.email || `Customer #${c.id}`}
                    </option>
                  ))}
                </select>
              </Field>
              <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Invoice date">
                  <Input type="date" value={createForm.invoice_date} onChange={(e) => setCreateForm((p) => ({ ...p, invoice_date: e.target.value }))} />
                </Field>
                <Field label="Currency">
                  <Input value={createForm.currency} onChange={(e) => setCreateForm((p) => ({ ...p, currency: e.target.value.toUpperCase() }))} maxLength={3} />
                </Field>
              </div>
              <div className="flex items-center gap-2 text-sm text-slate-700">
                <input
                  type="checkbox"
                  checked={createForm.use_due_date}
                  onChange={(e) => setCreateForm((p) => ({ ...p, use_due_date: e.target.checked }))}
                />
                Use explicit due date
              </div>
              {createForm.use_due_date ? (
                <Field label="Due date">
                  <Input type="date" value={createForm.due_date} onChange={(e) => setCreateForm((p) => ({ ...p, due_date: e.target.value }))} />
                </Field>
              ) : (
                <Field label="Due days">
                  <Input type="number" min="1" value={createForm.due_days} onChange={(e) => setCreateForm((p) => ({ ...p, due_days: e.target.value }))} />
                </Field>
              )}
              <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Subtotal">
                  <Input type="number" min="0" step="0.01" value={createForm.subtotal_amount} onChange={(e) => setCreateForm((p) => ({ ...p, subtotal_amount: e.target.value }))} />
                </Field>
                <Field label="Jurisdiction">
                  <select
                    value={createForm.jurisdiction}
                    onChange={(e) => setCreateForm((p) => ({ ...p, jurisdiction: e.target.value }))}
                    className="w-full rounded-lg border border-border px-3 py-2.5 text-sm"
                  >
                    {(taxConfigs.length ? taxConfigs : [{ jurisdiction: 'US' }]).map((c) => (
                      <option key={c.jurisdiction} value={c.jurisdiction}>{c.jurisdiction}</option>
                    ))}
                  </select>
                </Field>
              </div>
              <Field label="Description">
                <Input value={createForm.description} onChange={(e) => setCreateForm((p) => ({ ...p, description: e.target.value }))} />
              </Field>
              {createPreview && (
                <div className="rounded-lg border border-border bg-slate-50 p-3 text-sm">
                  <div>
                    {createPreview.tax_type || 'Tax'} ({createPreview.tax_rate.toFixed(2)}%):{' '}
                    {money(createPreview.tax_amount)}
                  </div>
                  <div className="font-semibold">Total: {money(createPreview.total_amount)}</div>
                </div>
              )}
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setShowCreate(false)}>Cancel</Button>
              <Button onClick={handleCreateInvoice} disabled={saving || !createForm.customer_id}>Create</Button>
            </div>
          </Modal>
        )}

        {paymentInvoice && (
          <Modal title="Record payment" onClose={() => setPaymentInvoice(null)}>
            <p className="mb-4 text-sm text-slate-600">
              {paymentInvoice.invoice_number} — balance due {money(remainingBalance(paymentInvoice), paymentInvoice.currency)}
            </p>
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
            <div className="grid gap-4">
              <Field label="Amount">
                <Input type="number" min="0" step="0.01" value={paymentForm.amount} onChange={(e) => setPaymentForm((p) => ({ ...p, amount: e.target.value }))} />
              </Field>
              <Field label="Payment date">
                <Input type="date" value={paymentForm.payment_date} onChange={(e) => setPaymentForm((p) => ({ ...p, payment_date: e.target.value }))} />
              </Field>
              <Field label="Method">
                <select
                  value={paymentForm.payment_method}
                  onChange={(e) => setPaymentForm((p) => ({ ...p, payment_method: e.target.value }))}
                  className="w-full rounded-lg border border-border px-3 py-2.5 text-sm"
                >
                  <option value="bank_transfer">Bank transfer</option>
                  <option value="credit_card">Credit card</option>
                  <option value="cash">Cash</option>
                  <option value="check">Check</option>
                  <option value="other">Other</option>
                </select>
              </Field>
              <Field label="Reference">
                <Input value={paymentForm.reference} onChange={(e) => setPaymentForm((p) => ({ ...p, reference: e.target.value }))} />
              </Field>
              <Field label="Notes">
                <Input value={paymentForm.notes} onChange={(e) => setPaymentForm((p) => ({ ...p, notes: e.target.value }))} />
              </Field>
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setPaymentInvoice(null)}>Cancel</Button>
              <Button onClick={handleRecordPayment} disabled={saving}>Record payment</Button>
            </div>
          </Modal>
        )}

        {overrideInvoice && (
          <Modal title="Override tax" onClose={() => setOverrideInvoice(null)}>
            <p className="mb-4 text-sm text-slate-600">{overrideInvoice.invoice_number || `Invoice #${overrideInvoice.id}`}</p>
            <p className="mb-4 text-xs text-slate-500" title="Override tax rate. Reason code required for audit.">
              Override tax rate. A reason code is required for audit compliance.
            </p>
            {taxLocked && (
              <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                Tax is locked for this fiscal period.
              </div>
            )}
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
            <div className="grid gap-4">
              <Field label="Jurisdiction">
                <select value={overrideJurisdiction} onChange={(e) => setOverrideJurisdiction(e.target.value)} disabled={taxLocked} className="w-full rounded-lg border border-border px-3 py-2.5 text-sm">
                  {(taxConfigs.length ? taxConfigs : [{ jurisdiction: 'US' }]).map((c) => (
                    <option key={c.jurisdiction} value={c.jurisdiction}>{c.jurisdiction}</option>
                  ))}
                </select>
              </Field>
              <Field label="New tax rate (%)">
                <Input type="number" min="0" max="100" step="0.01" value={overrideRate} onChange={(e) => setOverrideRate(e.target.value)} disabled={taxLocked} />
              </Field>
              <Field label="Reason code">
                <select
                  value={overrideReason}
                  onChange={(e) => setOverrideReason(e.target.value)}
                  disabled={taxLocked}
                  className="w-full rounded-lg border border-border px-3 py-2.5 text-sm"
                  title="Reason for override (audit required)."
                >
                  <option value="">Select a reason…</option>
                  {reasonOptions.map((r) => <option key={r} value={r}>{formatReason(r)}</option>)}
                </select>
              </Field>
              {overridePreview && (
                <div className="rounded-lg border border-border bg-slate-50 p-3 text-sm">
                  <div>New tax: {money(overridePreview.newTax, overrideInvoice.currency)}</div>
                  <div className="font-semibold">New total: {money(overridePreview.newTotal, overrideInvoice.currency)}</div>
                </div>
              )}
              <p className="text-xs text-amber-700">Override will be locked after posting.</p>
            </div>
            <div className="mt-6 flex flex-wrap justify-end gap-2">
              {overrideInvoice.tax_override_reason && (
                <Button variant="secondary" onClick={() => void handleClearOverride()} disabled={saving || taxLocked}>
                  Clear override
                </Button>
              )}
              <Button variant="secondary" onClick={() => setOverrideInvoice(null)}>Cancel</Button>
              <Button onClick={handleApplyOverride} disabled={saving || taxLocked || !overrideReason}>Apply Override</Button>
            </div>
          </Modal>
        )}

        {cancelInvoice && (
          <Modal title="Cancel invoice" onClose={() => setCancelInvoice(null)}>
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
            <p className="text-sm text-slate-600">
              Cancel invoice {cancelInvoice.invoice_number || `#${cancelInvoice.id}`}? This cannot be undone.
            </p>
            <div className="mt-4">
              <label className="mb-1 block text-sm font-medium text-slate-700">Reason (optional)</label>
              <Input value={cancelReason} onChange={(e) => setCancelReason(e.target.value)} placeholder="Why is this invoice being cancelled?" />
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setCancelInvoice(null)}>Keep invoice</Button>
              <Button onClick={() => void handleCancelInvoice()} disabled={actionInvoiceId === cancelInvoice.id}>
                Confirm cancel
              </Button>
            </div>
          </Modal>
        )}
      </div>
    </ClientShell>
  );
}

function Modal({ title, children, onClose }: { title: string; children: ReactNode; onClose: () => void }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" onClick={onClose}>
      <div className="w-full max-w-lg rounded-2xl border border-border bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between gap-3">
          <h3 className="text-lg font-semibold text-ink">{title}</h3>
          <button type="button" onClick={onClose} className="text-sm text-slate-500 hover:text-slate-700">Close</button>
        </div>
        <div className="mt-4">{children}</div>
      </div>
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="block space-y-1.5 text-sm">
      <span className="font-medium text-slate-700">{label}</span>
      {children}
    </label>
  );
}

function Badge({ value }: { value: string }) {
  return <span className="badge border border-border bg-slate-50 text-slate-700">{value}</span>;
}

function money(value?: string | number, currency = 'USD') {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(Number(value || 0));
}

function formatReason(code: string) {
  return REASON_LABELS[code] || code.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase());
}

function invoiceTaxLabel(invoice: Invoice) {
  const type = invoice.tax_type || invoice.classification?.tax_hints?.tax_type;
  return type && type !== 'None' ? type : 'Tax';
}
