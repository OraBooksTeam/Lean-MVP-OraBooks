import { useEffect, useState, type ReactNode } from 'react';
import WpLink from '../components/WpLink';

import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { Building2, FileText, Info, Paperclip, Plus, RefreshCw, Wallet } from 'lucide-react';

type Vendor = {
  id: number;
  name: string;
  email?: string;
  tax_id?: string;
  payment_terms?: number;
  default_currency?: string;
  auto_apply_credit?: number;
  notes?: string;
  is_active?: number;
  payable_balance?: string | number;
  credit_balance?: string | number;
};

type VendorFormState = {
  name: string;
  email: string;
  tax_id: string;
  payment_terms: string;
  default_currency: string;
  auto_apply_credit: boolean;
  notes: string;
};

const emptyVendorForm = (): VendorFormState => ({
  name: '',
  email: '',
  tax_id: '',
  payment_terms: '30',
  default_currency: 'USD',
  auto_apply_credit: true,
  notes: '',
});

function vendorToForm(vendor: Vendor): VendorFormState {
  return {
    name: vendor.name,
    email: vendor.email || '',
    tax_id: vendor.tax_id || '',
    payment_terms: String(vendor.payment_terms ?? 30),
    default_currency: vendor.default_currency || 'USD',
    auto_apply_credit: Number(vendor.auto_apply_credit ?? 1) === 1,
    notes: vendor.notes || '',
  };
}

function vendorFormPayload(form: VendorFormState) {
  return {
    name: form.name.trim(),
    email: form.email.trim(),
    tax_id: form.tax_id.trim(),
    payment_terms: parseInt(form.payment_terms, 10) || 30,
    default_currency: form.default_currency.trim() || 'USD',
    auto_apply_credit: form.auto_apply_credit ? 1 : 0,
    notes: form.notes.trim(),
  };
}

type Bill = {
  id: number;
  bill_number?: string;
  vendor_id?: number;
  vendor_name?: string;
  due_date?: string;
  workflow_status?: string;
  payment_status?: string;
  subtotal_amount?: string | number;
  tax_amount?: string | number;
  total_amount?: string | number;
  paid_amount?: string | number;
  currency?: string;
};

export default function VendorsPage() {
  const [context, setContext] = useState<any>(null);
  const [vendors, setVendors] = useState<Vendor[]>([]);
  const [bills, setBills] = useState<Bill[]>([]);
  const [stats, setStats] = useState<any>(null);
  const [aging, setAging] = useState<any>({});
  const [taxConfigs, setTaxConfigs] = useState<{ jurisdiction: string }[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [saving, setSaving] = useState(false);
  const [actionBillId, setActionBillId] = useState<number | null>(null);
  const [voidBill, setVoidBill] = useState<Bill | null>(null);
  const [voidReason, setVoidReason] = useState('');

  const [showVendorForm, setShowVendorForm] = useState(false);
  const [editingVendor, setEditingVendor] = useState<Vendor | null>(null);
  const [vendorForm, setVendorForm] = useState<VendorFormState>(emptyVendorForm());

  const [showBillForm, setShowBillForm] = useState(false);
  const [billForm, setBillForm] = useState({
    vendor_id: '',
    bill_date: new Date().toISOString().slice(0, 10),
    due_date: '',
    due_days: '30',
    use_due_date: false,
    currency: 'USD',
    subtotal_amount: '',
    jurisdiction: 'US',
    description: '',
  });
  const [billPreview, setBillPreview] = useState<{ tax_amount: number; total_amount: number; tax_rate: number } | null>(null);

  const [paymentVendor, setPaymentVendor] = useState<Vendor | null>(null);
  const [paymentForm, setPaymentForm] = useState({
    amount: '',
    payment_date: new Date().toISOString().slice(0, 10),
    payment_method: 'bank_transfer',
    reference: '',
    notes: '',
  });

  const [creditNoteVendor, setCreditNoteVendor] = useState<Vendor | null>(null);
  const [creditNoteForm, setCreditNoteForm] = useState({ amount: '', reason: '', credit_date: new Date().toISOString().slice(0, 10) });

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

    const [dashRes, vendorsRes, billsRes, agingRes, taxRes] = await Promise.all([
      api.vendorDashboard(),
      api.vendorsList(nextOrgId, { limit: 100 }),
      api.billsList(nextOrgId, { limit: 100 }),
      api.apAging(nextOrgId),
      api.taxListConfigs(nextOrgId),
    ]);

    if (dashRes.error) setError(dashRes.error);
    else {
      const dash = (dashRes as any).data;
      setStats(dash?.stats || null);
    }

    if (!vendorsRes.error) setVendors((vendorsRes as any).data?.vendors || []);
    if (!billsRes.error) setBills((billsRes as any).data?.bills || []);
    if (!agingRes.error) setAging((agingRes as any).data || {});
    if (!taxRes.error) setTaxConfigs((taxRes as any).data?.configs || []);

    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  useEffect(() => {
    if (!showBillForm || !orgId || !billForm.subtotal_amount) {
      setBillPreview(null);
      return;
    }
    const timer = setTimeout(async () => {
      const res = await api.taxCalculate({
        org_id: orgId,
        amount: parseFloat(billForm.subtotal_amount) || 0,
        jurisdiction: billForm.jurisdiction,
      });
      if (!res.error) {
        const data = (res as any).data;
        const tax = Number(data.tax_amount || 0);
        const subtotal = parseFloat(billForm.subtotal_amount) || 0;
        setBillPreview({
          tax_rate: Number(data.tax_rate || 0),
          tax_amount: tax,
          total_amount: subtotal + tax,
        });
      }
    }, 300);
    return () => clearTimeout(timer);
  }, [showBillForm, billForm.subtotal_amount, billForm.jurisdiction, orgId]);

  const handleCreateVendor = async () => {
    if (!orgId || !vendorForm.name.trim()) {
      setError('Vendor name is required.');
      return;
    }
    setSaving(true);
    setError('');
    const res = await api.vendorCreate(orgId, vendorFormPayload(vendorForm));
    if (res.error) setError(res.error);
    else {
      setSuccess('Vendor created.');
      setShowVendorForm(false);
      setVendorForm(emptyVendorForm());
      await load();
    }
    setSaving(false);
  };

  const handleUpdateVendor = async () => {
    if (!orgId || !editingVendor || !vendorForm.name.trim()) {
      setError('Vendor name is required.');
      return;
    }
    setSaving(true);
    setError('');
    const res = await api.vendorUpdate(orgId, editingVendor.id, vendorFormPayload(vendorForm));
    if (res.error) setError(res.error);
    else {
      setSuccess('Vendor updated.');
      setEditingVendor(null);
      await load();
    }
    setSaving(false);
  };

  const handleCreateBill = async () => {
    if (!orgId || !billForm.vendor_id || !billForm.subtotal_amount) {
      setError('Vendor and subtotal are required.');
      return;
    }
    setSaving(true);
    setError('');
    const payload: Record<string, unknown> = {
      vendor_id: parseInt(billForm.vendor_id, 10),
      bill_date: billForm.bill_date,
      subtotal_amount: parseFloat(billForm.subtotal_amount) || 0,
      jurisdiction: billForm.jurisdiction,
      currency: billForm.currency || 'USD',
      description: billForm.description,
    };
    if (billForm.use_due_date && billForm.due_date) {
      payload.due_date = billForm.due_date;
    } else {
      payload.due_days = parseInt(billForm.due_days, 10) || 30;
    }
    const res = await api.billCreate(orgId, payload);
    if (res.error) setError(res.error);
    else {
      setSuccess('Bill created in draft.');
      setShowBillForm(false);
      setBillForm({
        vendor_id: '',
        bill_date: new Date().toISOString().slice(0, 10),
        due_date: '',
        due_days: '30',
        use_due_date: false,
        currency: 'USD',
        subtotal_amount: '',
        jurisdiction: taxConfigs[0]?.jurisdiction || 'US',
        description: '',
      });
      setBillPreview(null);
      await load();
    }
    setSaving(false);
  };

  const runBillAction = async (action: 'submit' | 'approve' | 'post', billId: number) => {
    if (!orgId) return;
    setActionBillId(billId);
    setError('');
    const res = action === 'submit'
      ? await api.billSubmit(orgId, billId)
      : action === 'approve'
        ? await api.billApprove(orgId, billId)
        : await api.billPost(orgId, billId);

    if (res.error) setError(res.error);
    else {
      setSuccess(`Bill ${action === 'submit' ? 'submitted' : action === 'approve' ? 'approved' : 'posted'}.`);
      await load();
    }
    setActionBillId(null);
  };

  const canVoidBill = (bill: Bill) =>
    ['draft', 'submitted', 'approved'].includes(bill.workflow_status || '')
    && !['paid', 'partial'].includes(bill.payment_status || '')
    && Number(bill.paid_amount || 0) <= 0;

  const handleVoidBill = async () => {
    if (!orgId || !voidBill) return;
    setActionBillId(voidBill.id);
    setError('');
    const res = await api.billVoid(orgId, voidBill.id, voidReason.trim());
    if (res.error) setError(res.error);
    else {
      setSuccess('Bill voided.');
      setVoidBill(null);
      setVoidReason('');
      await load();
    }
    setActionBillId(null);
  };

  const openPayment = (vendor: Vendor) => {
    setPaymentVendor(vendor);
    setPaymentForm({
      amount: String(Number(vendor.payable_balance || 0) || ''),
      payment_date: new Date().toISOString().slice(0, 10),
      payment_method: 'bank_transfer',
      reference: '',
      notes: '',
    });
    setError('');
  };

  const handleRecordPayment = async () => {
    if (!orgId || !paymentVendor) return;
    setSaving(true);
    setError('');
    const res = await api.vendorPaymentRecord(orgId, {
      vendor_id: paymentVendor.id,
      amount: parseFloat(paymentForm.amount) || 0,
      payment_date: paymentForm.payment_date,
      payment_method: paymentForm.payment_method,
      reference: paymentForm.reference,
      notes: paymentForm.notes,
    });
    if (res.error) setError(res.error);
    else {
      setSuccess('Vendor payment recorded (FIFO allocation).');
      setPaymentVendor(null);
      await load();
    }
    setSaving(false);
  };

  const handleCreateCreditNote = async () => {
    if (!orgId || !creditNoteVendor) return;
    if (!creditNoteForm.amount || !creditNoteForm.reason.trim()) {
      setError('Credit note amount and reason are required.');
      return;
    }
    setSaving(true);
    setError('');
    const res = await api.vendorCreditNoteCreate(orgId, {
      vendor_id: creditNoteVendor.id,
      amount: parseFloat(creditNoteForm.amount) || 0,
      reason: creditNoteForm.reason.trim(),
      credit_date: creditNoteForm.credit_date,
    });
    if (res.error) setError(res.error);
    else {
      setSuccess('Vendor credit note created.');
      setCreditNoteVendor(null);
      setCreditNoteForm({ amount: '', reason: '', credit_date: new Date().toISOString().slice(0, 10) });
      await load();
    }
    setSaving(false);
  };

  return (
    <ClientShell
      title="Vendors & Bills"
      eyebrow="SL-027 Accounts payable"
      organization={context?.organization}
      isPartner={context?.organization?.organization_type === 'partner'}
    >
      <div className="space-y-5">
        <div className="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50/80 p-4 text-sm text-sky-900">
          <Info className="mt-0.5 h-4 w-4 shrink-0" />
          <p>
            Manage vendor master data, bill lifecycle (draft → submitted → approved → posted), FIFO payments, and AP aging.
          </p>
        </div>

        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Metric label="Total Vendors" value={stats?.total_vendors ?? 0} />
          <Metric label="Active Vendors" value={stats?.active_vendors ?? 0} />
          <Metric label="Total Payable" value={money(stats?.total_payable)} />
          <Metric label="Vendor Credits" value={money(stats?.total_credit)} />
        </div>

        <section className="glass-panel p-5">
          <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">AP Aging</h2>
          <div className="mt-4 grid gap-3 sm:grid-cols-4">
            <AgingBucket label="Current" value={aging.current} />
            <AgingBucket label="1–30 days" value={aging['30']} />
            <AgingBucket label="31–60 days" value={aging['60']} />
            <AgingBucket label="90+ days" value={aging['90_plus']} />
          </div>
        </section>

        <div className="flex flex-wrap justify-end gap-2">
          <Button size="sm" onClick={() => { setShowVendorForm(true); setVendorForm(emptyVendorForm()); setError(''); setSuccess(''); }}>
            <Plus className="h-4 w-4" />
            Add vendor
          </Button>
          <Button size="sm" onClick={() => { setShowBillForm(true); setError(''); setSuccess(''); }}>
            <Plus className="h-4 w-4" />
            Create bill
          </Button>
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {error && !showVendorForm && !showBillForm && !paymentVendor && !editingVendor && !creditNoteVendor && (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>
        )}
        {success && (
          <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">{success}</div>
        )}

        <div className="glass-panel overflow-hidden">
          <div className="border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Vendors</h2>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Vendor</th>
                <th className="px-5 py-3 font-semibold">Terms</th>
                <th className="px-5 py-3 font-semibold">Status</th>
                <th className="px-5 py-3 text-right font-semibold">Payable</th>
                <th className="px-5 py-3 text-right font-semibold">Credit</th>
                <th className="px-5 py-3 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={6} className="px-5 py-8 text-center text-slate-500">Loading vendors…</td></tr>
              ) : vendors.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-5 py-10 text-center">
                    <Building2 className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No vendor records found.</p>
                  </td>
                </tr>
              ) : vendors.map((vendor) => (
                <tr key={vendor.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3">
                    <p className="font-semibold text-ink">{vendor.name}</p>
                    {vendor.email && <p className="text-xs text-slate-500">{vendor.email}</p>}
                  </td>
                  <td className="px-5 py-3 text-slate-600">{vendor.payment_terms ?? 30} days</td>
                  <td className="px-5 py-3"><StatusBadge active={Number(vendor.is_active) === 1} /></td>
                  <td className="px-5 py-3 text-right font-bold text-ink">{money(vendor.payable_balance)}</td>
                  <td className="px-5 py-3 text-right text-slate-600">{money(vendor.credit_balance)}</td>
                  <td className="px-5 py-3">
                    <div className="flex flex-wrap gap-1">
                      <Button size="sm" variant="secondary" onClick={() => { setEditingVendor(vendor); setVendorForm(vendorToForm(vendor)); setError(''); }}>
                        Edit
                      </Button>
                      {Number(vendor.payable_balance || 0) > 0 && (
                        <Button size="sm" variant="secondary" onClick={() => openPayment(vendor)}>
                          <Wallet className="h-3.5 w-3.5" />
                          Pay
                        </Button>
                      )}
                      <Button size="sm" variant="secondary" onClick={() => { setCreditNoteVendor(vendor); setError(''); }}>
                        Credit note
                      </Button>
                      <WpLink to={`/attachments?resource_type=vendor&resource_id=${vendor.id}`}>
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

        <div className="glass-panel overflow-hidden">
          <div className="border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Bills</h2>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Bill</th>
                <th className="px-5 py-3 font-semibold">Vendor</th>
                <th className="px-5 py-3 font-semibold">Due Date</th>
                <th className="px-5 py-3 font-semibold">Workflow</th>
                <th className="px-5 py-3 font-semibold">Payment</th>
                <th className="px-5 py-3 text-right font-semibold">Total</th>
                <th className="px-5 py-3 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={7} className="px-5 py-8 text-center text-slate-500">Loading bills…</td></tr>
              ) : bills.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-5 py-10 text-center">
                    <FileText className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No bills found. Create your first bill.</p>
                  </td>
                </tr>
              ) : bills.map((bill) => (
                <tr key={bill.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3 font-semibold text-ink">{bill.bill_number || `Bill #${bill.id}`}</td>
                  <td className="px-5 py-3 text-slate-600">{bill.vendor_name || '—'}</td>
                  <td className="px-5 py-3 text-slate-600">{bill.due_date || '—'}</td>
                  <td className="px-5 py-3"><WorkflowBadge value={bill.workflow_status || 'draft'} /></td>
                  <td className="px-5 py-3"><WorkflowBadge value={bill.payment_status || 'unpaid'} /></td>
                  <td className="px-5 py-3 text-right font-bold text-ink">{money(bill.total_amount, bill.currency)}</td>
                  <td className="px-5 py-3">
                    <div className="flex flex-wrap gap-1">
                      {bill.workflow_status === 'draft' && (
                        <Button size="sm" variant="secondary" disabled={actionBillId === bill.id} onClick={() => void runBillAction('submit', bill.id)}>
                          Submit
                        </Button>
                      )}
                      {bill.workflow_status === 'submitted' && (
                        <Button size="sm" variant="secondary" disabled={actionBillId === bill.id} onClick={() => void runBillAction('approve', bill.id)}>
                          Approve
                        </Button>
                      )}
                      {bill.workflow_status === 'approved' && (
                        <Button size="sm" variant="secondary" disabled={actionBillId === bill.id} onClick={() => void runBillAction('post', bill.id)}>
                          Post
                        </Button>
                      )}
                      <WpLink to={`/attachments?resource_type=bill&resource_id=${bill.id}`}>
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

        {showVendorForm && (
          <Modal title="Add vendor" onClose={() => setShowVendorForm(false)}>
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
            <VendorFields form={vendorForm} onChange={setVendorForm} />
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setShowVendorForm(false)}>Cancel</Button>
              <Button onClick={handleCreateVendor} disabled={saving}>Create vendor</Button>
            </div>
          </Modal>
        )}

        {editingVendor && (
          <Modal title="Edit vendor" onClose={() => setEditingVendor(null)}>
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
            <VendorFields form={vendorForm} onChange={setVendorForm} />
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setEditingVendor(null)}>Cancel</Button>
              <Button onClick={handleUpdateVendor} disabled={saving}>Save vendor</Button>
            </div>
          </Modal>
        )}

        {showBillForm && (
          <Modal title="Create bill" onClose={() => setShowBillForm(false)}>
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
            <div className="grid gap-4">
              <Field label="Vendor">
                <select value={billForm.vendor_id} onChange={(e) => setBillForm((p) => ({ ...p, vendor_id: e.target.value }))} className="w-full rounded-lg border border-border px-3 py-2.5 text-sm">
                  <option value="">Select vendor…</option>
                  {vendors.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}
                </select>
              </Field>
              <Field label="Bill date"><Input type="date" value={billForm.bill_date} onChange={(e) => setBillForm((p) => ({ ...p, bill_date: e.target.value }))} /></Field>
              <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Currency">
                  <Input value={billForm.currency} onChange={(e) => setBillForm((p) => ({ ...p, currency: e.target.value.toUpperCase() }))} maxLength={3} />
                </Field>
                <div className="flex items-end pb-2">
                  <label className="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" checked={billForm.use_due_date} onChange={(e) => setBillForm((p) => ({ ...p, use_due_date: e.target.checked }))} />
                    Explicit due date
                  </label>
                </div>
              </div>
              {billForm.use_due_date ? (
                <Field label="Due date"><Input type="date" value={billForm.due_date} onChange={(e) => setBillForm((p) => ({ ...p, due_date: e.target.value }))} /></Field>
              ) : (
                <Field label="Due days"><Input type="number" min="1" value={billForm.due_days} onChange={(e) => setBillForm((p) => ({ ...p, due_days: e.target.value }))} /></Field>
              )}
              <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Subtotal"><Input type="number" min="0" step="0.01" value={billForm.subtotal_amount} onChange={(e) => setBillForm((p) => ({ ...p, subtotal_amount: e.target.value }))} /></Field>
                <Field label="Jurisdiction">
                  <select value={billForm.jurisdiction} onChange={(e) => setBillForm((p) => ({ ...p, jurisdiction: e.target.value }))} className="w-full rounded-lg border border-border px-3 py-2.5 text-sm">
                    {(taxConfigs.length ? taxConfigs : [{ jurisdiction: 'US' }]).map((c) => (
                      <option key={c.jurisdiction} value={c.jurisdiction}>{c.jurisdiction}</option>
                    ))}
                  </select>
                </Field>
              </div>
              <Field label="Description"><Input value={billForm.description} onChange={(e) => setBillForm((p) => ({ ...p, description: e.target.value }))} /></Field>
              {billPreview && (
                <div className="rounded-lg border border-border bg-slate-50 p-3 text-sm">
                  <div>Tax ({billPreview.tax_rate.toFixed(2)}%): {money(billPreview.tax_amount)}</div>
                  <div className="font-semibold">Total: {money(billPreview.total_amount)}</div>
                </div>
              )}
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setShowBillForm(false)}>Cancel</Button>
              <Button onClick={handleCreateBill} disabled={saving || !billForm.vendor_id}>Create bill</Button>
            </div>
          </Modal>
        )}

        {paymentVendor && (
          <Modal title="Record vendor payment" onClose={() => setPaymentVendor(null)}>
            <p className="mb-4 text-sm text-slate-600">
              {paymentVendor.name} — payable {money(paymentVendor.payable_balance)}
            </p>
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
            <div className="grid gap-4">
              <Field label="Amount"><Input type="number" min="0" step="0.01" value={paymentForm.amount} onChange={(e) => setPaymentForm((p) => ({ ...p, amount: e.target.value }))} /></Field>
              <Field label="Payment date"><Input type="date" value={paymentForm.payment_date} onChange={(e) => setPaymentForm((p) => ({ ...p, payment_date: e.target.value }))} /></Field>
              <Field label="Method">
                <select value={paymentForm.payment_method} onChange={(e) => setPaymentForm((p) => ({ ...p, payment_method: e.target.value }))} className="w-full rounded-lg border border-border px-3 py-2.5 text-sm">
                  <option value="bank_transfer">Bank transfer</option>
                  <option value="credit_card">Credit card</option>
                  <option value="cash">Cash</option>
                  <option value="check">Check</option>
                  <option value="other">Other</option>
                </select>
              </Field>
              <Field label="Reference"><Input value={paymentForm.reference} onChange={(e) => setPaymentForm((p) => ({ ...p, reference: e.target.value }))} /></Field>
              <Field label="Notes"><Input value={paymentForm.notes} onChange={(e) => setPaymentForm((p) => ({ ...p, notes: e.target.value }))} /></Field>
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setPaymentVendor(null)}>Cancel</Button>
              <Button onClick={handleRecordPayment} disabled={saving}>Record payment</Button>
            </div>
          </Modal>
        )}

        {creditNoteVendor && (
          <Modal title="Create vendor credit note" onClose={() => setCreditNoteVendor(null)}>
            <p className="mb-4 text-sm text-slate-600">{creditNoteVendor.name}</p>
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
            <div className="grid gap-4">
              <Field label="Amount"><Input type="number" min="0" step="0.01" value={creditNoteForm.amount} onChange={(e) => setCreditNoteForm((p) => ({ ...p, amount: e.target.value }))} /></Field>
              <Field label="Credit date"><Input type="date" value={creditNoteForm.credit_date} onChange={(e) => setCreditNoteForm((p) => ({ ...p, credit_date: e.target.value }))} /></Field>
              <Field label="Reason"><Input value={creditNoteForm.reason} onChange={(e) => setCreditNoteForm((p) => ({ ...p, reason: e.target.value }))} placeholder="Return, adjustment, etc." /></Field>
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setCreditNoteVendor(null)}>Cancel</Button>
              <Button onClick={handleCreateCreditNote} disabled={saving}>Create credit note</Button>
            </div>
          </Modal>
        )}
      </div>
    </ClientShell>
  );
}

function VendorFields({
  form,
  onChange,
}: {
  form: VendorFormState;
  onChange: (next: VendorFormState) => void;
}) {
  const set = (patch: Partial<VendorFormState>) => onChange({ ...form, ...patch });

  return (
    <div className="grid gap-4">
      <Field label="Name"><Input value={form.name} onChange={(e) => set({ name: e.target.value })} /></Field>
      <Field label="Email"><Input type="email" value={form.email} onChange={(e) => set({ email: e.target.value })} /></Field>
      <Field label="Tax ID"><Input value={form.tax_id} onChange={(e) => set({ tax_id: e.target.value })} /></Field>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Payment terms (days)"><Input type="number" min="0" value={form.payment_terms} onChange={(e) => set({ payment_terms: e.target.value })} /></Field>
        <Field label="Default currency"><Input value={form.default_currency} onChange={(e) => set({ default_currency: e.target.value.toUpperCase() })} maxLength={3} /></Field>
      </div>
      <label className="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" checked={form.auto_apply_credit} onChange={(e) => set({ auto_apply_credit: e.target.checked })} />
        Auto-apply vendor credit on new bills
      </label>
      <Field label="Notes"><Input value={form.notes} onChange={(e) => set({ notes: e.target.value })} /></Field>
    </div>
  );
}

function Modal({ title, children, onClose }: { title: string; children: ReactNode; onClose: () => void }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" onClick={onClose}>
      <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-border bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between gap-3">
          <h3 className="text-lg font-semibold text-ink">{title}</h3>
          <button type="button" onClick={onClose} className="text-sm text-slate-500 hover:text-slate-700">Close</button>
        </div>
        <div className="mt-4">{children}</div>
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

function Metric({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="stat-card">
      <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</p>
      <p className="mt-2 text-3xl font-black text-ink">{value}</p>
    </div>
  );
}

function AgingBucket({ label, value }: { label: string; value?: number }) {
  return (
    <div className="rounded-xl border border-border bg-slate-50/70 p-3">
      <p className="text-xs font-semibold uppercase text-slate-500">{label}</p>
      <p className="mt-1 text-lg font-bold text-ink">{money(value)}</p>
    </div>
  );
}

function StatusBadge({ active }: { active: boolean }) {
  return (
    <span className={`badge border ${active ? 'border-success/20 bg-success/10 text-success' : 'border-slate-200 bg-slate-100 text-slate-600'}`}>
      {active ? 'active' : 'inactive'}
    </span>
  );
}

function WorkflowBadge({ value }: { value: string }) {
  return <span className="badge border border-border bg-slate-50 text-slate-700">{value}</span>;
}

function money(value?: string | number, currency = 'USD') {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(Number(value || 0));
}
