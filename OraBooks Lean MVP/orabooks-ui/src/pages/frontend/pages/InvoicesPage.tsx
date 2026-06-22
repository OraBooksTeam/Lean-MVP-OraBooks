import { useEffect, useMemo, useState, type ReactNode } from 'react';
import WpLink from '../components/WpLink';
import { getSearchParam } from '../lib/wp-routing';

import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import ResourceAttachmentsPanel from '../components/ResourceAttachmentsPanel';
import { FileText, Info, Paperclip, Percent, Plus, RefreshCw, Wallet } from 'lucide-react';
import ClassificationPanel from '@/components/classification/ClassificationPanel';
import OverrideClassificationModal from '@/components/classification/OverrideClassificationModal';
import { useClassificationPolling } from '@/components/classification/useClassificationPolling';
import TaxOverrideModal, { type TaxConfig } from '@/components/tax/TaxOverrideModal';

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
  tax_override_reason?: string | null;
  currency?: string;
  classification?: any;
};

type Customer = { id: number; display_name?: string | null; email?: string };

export default function InvoicesPage() {
  const [context, setContext] = useState<any>(null);
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [taxConfigs, setTaxConfigs] = useState<TaxConfig[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [saving, setSaving] = useState(false);

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
  const [createPreview, setCreatePreview] = useState<{ tax_rate: number; tax_amount: number; total_amount: number } | null>(null);

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
  const [classifying, setClassifying] = useState(false);
  const [showClassOverride, setShowClassOverride] = useState(false);
  const classificationThreshold = 70;

  const orgId = context?.organization?.id;
  const permissions: string[] = context?.permissions || [];
  const canCreateInvoice = permissions.includes('create_invoice');
  const canRecordPayment = permissions.includes('create_invoice');
  const canOverrideTax = permissions.includes('override_tax');

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
    const nextCanOverrideTax = (nextContext?.permissions || []).includes('override_tax');
    if (!nextOrgId) {
      setError('Organization is not set up yet.');
      setLoading(false);
      return;
    }

    const customerFilter = Number(getSearchParam('customer_id') || 0);

    const [invoicesRes, taxRes, customersRes] = await Promise.all([
      api.invoicesList(nextOrgId, {
        limit: 100,
        customer_id: customerFilter > 0 ? customerFilter : undefined,
      }),
      nextCanOverrideTax
        ? api.taxOverrideReasons(nextOrgId)
        : api.taxListConfigs(nextOrgId),
      api.customersList(nextOrgId, { limit: 100 }),
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

    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const loadInvoiceDetail = async (invoiceId: number) => {
    const res = await api.invoiceGet(invoiceId);
    if (res.error) {
      setError(res.error);
      return;
    }
    const invoice = (res as any).data?.invoice;
    if (invoice) setSelectedInvoice(invoice);
  };

  useClassificationPolling({
    recordType: 'invoice',
    recordId: selectedInvoice?.id ?? 0,
    enabled: selectedInvoice?.classification?.status === 'pending',
    onUpdate: (classification) => {
      setSelectedInvoice((prev) => (prev ? { ...prev, classification } : prev));
    },
  });

  useEffect(() => {
    const invoiceId = Number(getSearchParam('invoice_id') || 0);
    if (invoiceId <= 0 || invoices.length === 0) {
      return;
    }

    const match = invoices.find((invoice) => invoice.id === invoiceId);
    if (match) {
      void loadInvoiceDetail(match.id);
    }
  }, [invoices]);

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
    setOverrideReason('');
    setOverrideJurisdiction(invoice.tax_jurisdiction || taxConfigs[0]?.jurisdiction || 'US');
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
    const res = await api.invoiceClearTaxOverride(orgId, overrideInvoice.id, overrideJurisdiction);
    if (res.error) setError(res.error);
    else {
      setSuccess('Tax override cleared.');
      setOverrideInvoice(null);
      await load();
    }
    setSaving(false);
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
            {selectedInvoice.classification && (
              <ClassificationPanel
                classification={selectedInvoice.classification}
                threshold={classificationThreshold}
                canManage={canCreateInvoice}
                loading={classifying}
                recordType="invoice"
                onApply={async () => {
                  if (!selectedInvoice?.id) return;
                  setClassifying(true);
                  const res = await api.classificationApply('invoice', selectedInvoice.id);
                  if (res.error) setError(res.error);
                  else {
                    setSuccess('AI suggestions applied.');
                    await loadInvoiceDetail(selectedInvoice.id);
                  }
                  setClassifying(false);
                }}
                onOverride={() => setShowClassOverride(true)}
                onRerun={async () => {
                  if (!selectedInvoice?.id) return;
                  setClassifying(true);
                  const res = await api.classificationRun('invoice', selectedInvoice.id, false);
                  if (res.error) setError(res.error);
                  else {
                    setSuccess('Classification refreshed.');
                    await loadInvoiceDetail(selectedInvoice.id);
                  }
                  setClassifying(false);
                }}
              />
            )}
            <OverrideClassificationModal
              open={showClassOverride}
              accountCode={selectedInvoice.classification?.suggested_account_code || ''}
              taxRate={
                selectedInvoice.classification?.tax_hints?.tax_rate != null
                  ? String(selectedInvoice.classification.tax_hints.tax_rate)
                  : ''
              }
              saving={classifying}
              onClose={() => setShowClassOverride(false)}
              onSubmit={async (accountCode, taxRate) => {
                if (!selectedInvoice?.id) return;
                setClassifying(true);
                const res = await api.classificationOverride('invoice', selectedInvoice.id, accountCode, taxRate);
                if (res.error) setError(res.error);
                else {
                  setSuccess('Classification overridden.');
                  setShowClassOverride(false);
                  await loadInvoiceDetail(selectedInvoice.id);
                }
                setClassifying(false);
              }}
            />
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
                    <span className="inline-flex items-center gap-1" title={invoice.tax_override_reason ? `Tax manually changed. Reason: ${invoice.tax_override_reason}` : undefined}>
                      <Percent className="h-3.5 w-3.5" />
                      {Number(invoice.tax_rate || 0).toFixed(2)}%
                    </span>
                    <div className="text-xs text-slate-500">{money(invoice.tax_amount, invoice.currency)}</div>
                  </td>
                  <td className="px-5 py-3 text-right font-bold text-ink">{money(invoice.total_amount, invoice.currency)}</td>
                  <td className="px-5 py-3">
                    <div className="flex flex-wrap gap-1">
                      <Button size="sm" variant="secondary" onClick={() => void loadInvoiceDetail(invoice.id)}>
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
                          Override tax
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
                  <div>Tax ({createPreview.tax_rate.toFixed(2)}%): {money(createPreview.tax_amount)}</div>
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

        <TaxOverrideModal
          open={Boolean(overrideInvoice)}
          title="Override tax"
          subtitle={overrideInvoice?.invoice_number || (overrideInvoice ? `Invoice #${overrideInvoice.id}` : '')}
          taxRate={overrideRate}
          reasonCode={overrideReason}
          jurisdiction={overrideJurisdiction}
          taxConfigs={taxConfigs}
          taxLocked={taxLocked}
          saving={saving}
          error={overrideInvoice ? error : undefined}
          hasExistingOverride={Boolean(overrideInvoice?.tax_override_reason)}
          currency={overrideInvoice?.currency}
          preview={overridePreview ? { newTax: overridePreview.newTax, newTotal: overridePreview.newTotal } : null}
          onClose={() => setOverrideInvoice(null)}
          onTaxRateChange={setOverrideRate}
          onReasonChange={setOverrideReason}
          onJurisdictionChange={setOverrideJurisdiction}
          onApply={() => void handleApplyOverride()}
          onClear={() => void handleClearOverride()}
        />

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
