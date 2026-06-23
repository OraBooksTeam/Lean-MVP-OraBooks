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
  lock_status?: string;
  subtotal_amount?: string | number;
  tax_amount?: string | number;
  total_amount?: string | number;
  paid_amount?: string | number;
  currency?: string;
};

type VendorCreditNote = {
  id: number;
  credit_note_number: string;
  amount: number;
  reason: string;
  credit_date: string;
  workflow_status: string;
  is_adjustment?: number;
  requires_second_approval?: number;
  bill_id?: number | null;
};

type VendorPayment = {
  id: number;
  payment_date: string;
  amount: number;
  payment_method: string;
  type?: string;
  reference?: string;
  can_reverse?: boolean;
};

type VendorStatement = {
  id: number;
  statement_month: string;
  payable_balance: number | string;
  credit_balance?: number | string;
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
  const [inventoryProducts, setInventoryProducts] = useState<{ id: number; sku: string; name: string; average_cost?: number }[]>([]);
  const [billLineItems, setBillLineItems] = useState<Array<{ product_id: string; quantity: string; unit_cost: string }>>([]);
  const [useInventoryLines, setUseInventoryLines] = useState(false);

  const [paymentVendor, setPaymentVendor] = useState<Vendor | null>(null);
  const [paymentForm, setPaymentForm] = useState({
    amount: '',
    payment_date: new Date().toISOString().slice(0, 10),
    payment_method: 'bank_transfer',
    reference: '',
    notes: '',
  });

  const [creditNoteVendor, setCreditNoteVendor] = useState<Vendor | null>(null);
  const [creditNoteForm, setCreditNoteForm] = useState({
    amount: '',
    reason: '',
    credit_date: new Date().toISOString().slice(0, 10),
    is_adjustment: false,
    bill_id: '',
  });
  const [vendorCreditNotes, setVendorCreditNotes] = useState<VendorCreditNote[]>([]);
  const [walletPayments, setWalletPayments] = useState<VendorPayment[]>([]);
  const [statements, setStatements] = useState<VendorStatement[]>([]);
  const [reversePayment, setReversePayment] = useState<VendorPayment | null>(null);
  const [reverseReason, setReverseReason] = useState('');
  const [detailBill, setDetailBill] = useState<Bill | null>(null);
  const [billCreditNotes, setBillCreditNotes] = useState<VendorCreditNote[]>([]);
  const [billPayments, setBillPayments] = useState<VendorPayment[]>([]);
  const [voidCreditNote, setVoidCreditNote] = useState<VendorCreditNote | null>(null);
  const [voidCreditReason, setVoidCreditReason] = useState('');
  const [actionCreditNoteId, setActionCreditNoteId] = useState<number | null>(null);

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

  const billLinesSubtotal = () => billLineItems.reduce((sum, line) => {
    const qty = parseFloat(line.quantity) || 0;
    const cost = parseFloat(line.unit_cost) || 0;
    return sum + qty * cost;
  }, 0);

  useEffect(() => {
    if (!showBillForm || !orgId) return;
    void api.inventoryProductsList(orgId, { limit: 200, is_active: 1 }).then((res) => {
      if (!res.error) setInventoryProducts((res as any).data?.products || []);
    });
  }, [showBillForm, orgId]);

  useEffect(() => {
    if (!showBillForm || !orgId) {
      setBillPreview(null);
      return;
    }
    const subtotal = useInventoryLines && billLineItems.length > 0
      ? billLinesSubtotal()
      : parseFloat(billForm.subtotal_amount) || 0;
    if (subtotal <= 0) {
      setBillPreview(null);
      return;
    }
    const timer = setTimeout(async () => {
      const res = await api.taxCalculate({
        org_id: orgId,
        amount: subtotal,
        jurisdiction: billForm.jurisdiction,
      });
      if (!res.error) {
        const data = (res as any).data;
        const tax = Number(data.tax_amount || 0);
        setBillPreview({
          tax_rate: Number(data.tax_rate || 0),
          tax_amount: tax,
          total_amount: subtotal + tax,
        });
      }
    }, 300);
    return () => clearTimeout(timer);
  }, [showBillForm, billForm.subtotal_amount, billForm.jurisdiction, orgId, useInventoryLines, billLineItems]);

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
    if (!orgId || !billForm.vendor_id) {
      setError('Vendor is required.');
      return;
    }
    const linesSubtotal = useInventoryLines ? billLinesSubtotal() : 0;
    const subtotal = useInventoryLines && linesSubtotal > 0
      ? linesSubtotal
      : parseFloat(billForm.subtotal_amount) || 0;
    if (subtotal <= 0) {
      setError('Bill subtotal or inventory lines are required.');
      return;
    }
    setSaving(true);
    setError('');
    const payload: Record<string, unknown> = {
      vendor_id: parseInt(billForm.vendor_id, 10),
      bill_date: billForm.bill_date,
      subtotal_amount: subtotal,
      jurisdiction: billForm.jurisdiction,
      currency: billForm.currency || 'USD',
      description: billForm.description,
    };
    if (useInventoryLines && billLineItems.length > 0) {
      payload.line_items = JSON.stringify(
        billLineItems
          .filter((l) => l.product_id && parseFloat(l.quantity) > 0)
          .map((l) => ({
            product_id: parseInt(l.product_id, 10),
            quantity: parseFloat(l.quantity) || 0,
            unit_cost: parseFloat(l.unit_cost) || 0,
          }))
      );
    }
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
      setBillLineItems([]);
      setUseInventoryLines(false);
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

  const openPayment = async (vendor: Vendor) => {
    setPaymentVendor(vendor);
    setPaymentForm({
      amount: String(Number(vendor.payable_balance || 0) || ''),
      payment_date: new Date().toISOString().slice(0, 10),
      payment_method: 'bank_transfer',
      reference: '',
      notes: '',
    });
    setError('');
    if (orgId) {
      const [paymentsRes, statementsRes] = await Promise.all([
        api.vendorPaymentsList(orgId, { vendor_id: vendor.id }),
        api.vendorStatementsList(orgId, vendor.id),
      ]);
      if (!paymentsRes.error) setWalletPayments((paymentsRes as any).data?.payments || []);
      if (!statementsRes.error) setStatements((statementsRes as any).data?.statements || []);
    }
  };

  const openBillDetail = async (bill: Bill) => {
    if (!orgId) return;
    setDetailBill(bill);
    setError('');
    const [cnRes, payRes] = await Promise.all([
      api.vendorCreditNotesList(orgId, { bill_id: bill.id }),
      api.vendorPaymentsList(orgId, { bill_id: bill.id }),
    ]);
    if (!cnRes.error) setBillCreditNotes((cnRes as any).data?.credit_notes || []);
    if (!payRes.error) setBillPayments((payRes as any).data?.payments || []);
  };

  const loadVendorCreditNotes = async (vendorId: number) => {
    if (!orgId) return;
    const res = await api.vendorCreditNotesList(orgId, { vendor_id: vendorId });
    if (!res.error) setVendorCreditNotes((res as any).data?.credit_notes || []);
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
      await openPayment(paymentVendor);
      await load();
    }
    setSaving(false);
  };

  const handleReversePayment = async () => {
    if (!orgId || !paymentVendor || !reversePayment) return;
    setSaving(true);
    setError('');
    const res = await api.vendorPaymentReverse(orgId, reversePayment.id, reverseReason.trim());
    if (res.error) setError(res.error);
    else {
      setSuccess('Payment reversed.');
      setReversePayment(null);
      setReverseReason('');
      await openPayment(paymentVendor);
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
    const payload: Record<string, unknown> = {
      vendor_id: creditNoteVendor.id,
      amount: parseFloat(creditNoteForm.amount) || 0,
      reason: creditNoteForm.reason.trim(),
      credit_date: creditNoteForm.credit_date,
      is_adjustment: creditNoteForm.is_adjustment ? 1 : 0,
    };
    if (creditNoteForm.bill_id) payload.bill_id = parseInt(creditNoteForm.bill_id, 10);
    const res = await api.vendorCreditNoteCreate(orgId, payload);
    if (res.error) setError(res.error);
    else {
      setSuccess('Vendor credit note created in draft.');
      setCreditNoteForm({
        amount: '',
        reason: '',
        credit_date: new Date().toISOString().slice(0, 10),
        is_adjustment: false,
        bill_id: '',
      });
      await loadVendorCreditNotes(creditNoteVendor.id);
    }
    setSaving(false);
  };

  const runCreditNoteAction = async (
    action: 'submit' | 'approve' | 'post',
    creditNoteId: number,
    context: 'vendor' | 'bill' = 'vendor'
  ) => {
    if (!orgId) return;
    setActionCreditNoteId(creditNoteId);
    setError('');
    const res = action === 'submit'
      ? await api.vendorCreditNoteSubmit(orgId, creditNoteId)
      : action === 'approve'
        ? await api.vendorCreditNoteApprove(orgId, creditNoteId)
        : await api.vendorCreditNotePost(orgId, creditNoteId);
    if (res.error) setError(res.error);
    else {
      setSuccess(`Credit note ${action === 'submit' ? 'submitted' : action === 'approve' ? 'approved' : 'posted'}.`);
      if (creditNoteVendor) await loadVendorCreditNotes(creditNoteVendor.id);
      if (detailBill) await openBillDetail(detailBill);
      await load();
    }
    setActionCreditNoteId(null);
  };

  const handleVoidCreditNote = async () => {
    if (!orgId || !voidCreditNote) return;
    setActionCreditNoteId(voidCreditNote.id);
    setError('');
    const res = await api.vendorCreditNoteVoid(orgId, voidCreditNote.id, voidCreditReason.trim());
    if (res.error) setError(res.error);
    else {
      setSuccess('Credit note voided.');
      setVoidCreditNote(null);
      setVoidCreditReason('');
      if (creditNoteVendor) await loadVendorCreditNotes(creditNoteVendor.id);
      if (detailBill) await openBillDetail(detailBill);
      await load();
    }
    setActionCreditNoteId(null);
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
                      <Button size="sm" variant="secondary" onClick={() => { setCreditNoteVendor(vendor); setError(''); void loadVendorCreditNotes(vendor.id); }}>
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
                      {canVoidBill(bill) && (
                        <Button size="sm" variant="secondary" disabled={actionBillId === bill.id} onClick={() => { setVoidBill(bill); setVoidReason(''); setError(''); }}>
                          Void
                        </Button>
                      )}
                      <Button size="sm" variant="secondary" onClick={() => void openBillDetail(bill)}>
                        View
                      </Button>
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
              <label className="flex items-center gap-2 text-sm text-slate-700">
                <input
                  type="checkbox"
                  checked={useInventoryLines}
                  onChange={(e) => {
                    setUseInventoryLines(e.target.checked);
                    if (e.target.checked && billLineItems.length === 0) {
                      setBillLineItems([{ product_id: '', quantity: '1', unit_cost: '' }]);
                    }
                  }}
                />
                Add inventory products (updates stock on post)
              </label>
              {useInventoryLines ? (
                <div className="space-y-2 rounded-lg border border-border p-3">
                  <div className="flex items-center justify-between">
                    <p className="text-sm font-semibold text-ink">Inventory lines</p>
                    <Button
                      size="sm"
                      variant="secondary"
                      onClick={() => setBillLineItems((lines) => [...lines, { product_id: '', quantity: '1', unit_cost: '' }])}
                    >
                      Add line
                    </Button>
                  </div>
                  {billLineItems.map((line, idx) => (
                    <div key={idx} className="grid gap-2 sm:grid-cols-[2fr_1fr_1fr_auto]">
                      <select
                        value={line.product_id}
                        onChange={(e) => {
                          const productId = e.target.value;
                          const product = inventoryProducts.find((p) => String(p.id) === productId);
                          setBillLineItems((lines) => lines.map((l, i) => i === idx ? {
                            ...l,
                            product_id: productId,
                            unit_cost: l.unit_cost || (product?.average_cost != null ? String(product.average_cost) : ''),
                          } : l));
                        }}
                        className="rounded-lg border border-border px-3 py-2 text-sm"
                      >
                        <option value="">Select product…</option>
                        {inventoryProducts.map((p) => (
                          <option key={p.id} value={p.id}>{p.sku} — {p.name}</option>
                        ))}
                      </select>
                      <Input type="number" min="0" step="0.0001" placeholder="Qty" value={line.quantity} onChange={(e) => setBillLineItems((lines) => lines.map((l, i) => i === idx ? { ...l, quantity: e.target.value } : l))} />
                      <Input type="number" min="0" step="0.01" placeholder="Unit cost" value={line.unit_cost} onChange={(e) => setBillLineItems((lines) => lines.map((l, i) => i === idx ? { ...l, unit_cost: e.target.value } : l))} />
                      <Button size="sm" variant="secondary" onClick={() => setBillLineItems((lines) => lines.filter((_, i) => i !== idx))}>Remove</Button>
                    </div>
                  ))}
                  <p className="text-sm text-slate-600">Lines subtotal: {money(billLinesSubtotal())}</p>
                </div>
              ) : (
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
              )}
              {!useInventoryLines && (
              <Field label="Jurisdiction">
                <select value={billForm.jurisdiction} onChange={(e) => setBillForm((p) => ({ ...p, jurisdiction: e.target.value }))} className="w-full rounded-lg border border-border px-3 py-2.5 text-sm">
                  {(taxConfigs.length ? taxConfigs : [{ jurisdiction: 'US' }]).map((c) => (
                    <option key={c.jurisdiction} value={c.jurisdiction}>{c.jurisdiction}</option>
                  ))}
                </select>
              </Field>
              )}
              {useInventoryLines && (
              <Field label="Jurisdiction">
                <select value={billForm.jurisdiction} onChange={(e) => setBillForm((p) => ({ ...p, jurisdiction: e.target.value }))} className="w-full rounded-lg border border-border px-3 py-2.5 text-sm">
                  {(taxConfigs.length ? taxConfigs : [{ jurisdiction: 'US' }]).map((c) => (
                    <option key={c.jurisdiction} value={c.jurisdiction}>{c.jurisdiction}</option>
                  ))}
                </select>
              </Field>
              )}
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
            {walletPayments.length > 0 && (
              <div className="mt-4 border-t border-border pt-4">
                <h4 className="mb-2 text-sm font-semibold text-ink">Recent payments</h4>
                <ul className="space-y-2 text-sm">
                  {walletPayments.map((payment) => (
                    <li key={payment.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border px-3 py-2">
                      <div>
                        <p className="font-medium text-ink">{money(payment.amount, paymentVendor.default_currency)}</p>
                        <p className="text-slate-600">{payment.payment_date} · {payment.payment_method} · {payment.type || 'payment'}</p>
                      </div>
                      {payment.can_reverse && (
                        <Button size="sm" variant="secondary" onClick={() => { setReversePayment(payment); setReverseReason(''); }}>
                          Reverse
                        </Button>
                      )}
                    </li>
                  ))}
                </ul>
              </div>
            )}
            {statements.length > 0 && (
              <div className="mt-4 border-t border-border pt-4">
                <h4 className="mb-2 text-sm font-semibold text-ink">Monthly statements</h4>
                <ul className="space-y-2 text-sm text-slate-600">
                  {statements.map((row) => (
                    <li key={row.id} className="flex justify-between rounded-lg border border-border px-3 py-2">
                      <span>{row.statement_month}</span>
                      <span>{money(row.payable_balance, paymentVendor.default_currency)}</span>
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </Modal>
        )}

        {reversePayment && paymentVendor && (
          <Modal title="Reverse payment" onClose={() => setReversePayment(null)}>
            <p className="mb-4 text-sm text-slate-600">
              Reverse {money(reversePayment.amount, paymentVendor.default_currency)} payment on {reversePayment.payment_date}?
            </p>
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
            <Field label="Reason">
              <Input value={reverseReason} onChange={(e) => setReverseReason(e.target.value)} placeholder="Why is this payment being reversed?" />
            </Field>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setReversePayment(null)}>Cancel</Button>
              <Button onClick={() => void handleReversePayment()} disabled={saving || !reverseReason.trim()}>Confirm reversal</Button>
            </div>
          </Modal>
        )}

        {creditNoteVendor && (
          <Modal title="Vendor credit note" onClose={() => setCreditNoteVendor(null)}>
            <p className="mb-4 text-sm text-slate-600">{creditNoteVendor.name}</p>
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
            <div className="grid gap-4">
              <Field label="Amount"><Input type="number" min="0" step="0.01" value={creditNoteForm.amount} onChange={(e) => setCreditNoteForm((p) => ({ ...p, amount: e.target.value }))} /></Field>
              <Field label="Credit date"><Input type="date" value={creditNoteForm.credit_date} onChange={(e) => setCreditNoteForm((p) => ({ ...p, credit_date: e.target.value }))} /></Field>
              <Field label="Linked bill (optional)">
                <select value={creditNoteForm.bill_id} onChange={(e) => setCreditNoteForm((p) => ({ ...p, bill_id: e.target.value }))} className="w-full rounded-lg border border-border px-3 py-2.5 text-sm">
                  <option value="">No specific bill</option>
                  {bills.filter((b) => b.vendor_id === creditNoteVendor.id && b.workflow_status === 'posted').map((b) => (
                    <option key={b.id} value={b.id}>{b.bill_number || `#${b.id}`}</option>
                  ))}
                </select>
              </Field>
              <Field label="Reason"><Input value={creditNoteForm.reason} onChange={(e) => setCreditNoteForm((p) => ({ ...p, reason: e.target.value }))} placeholder="Return, adjustment, etc." /></Field>
              <label className="flex items-start gap-2 text-sm text-slate-700" title="Uses company's vendor adjustment account (configurable).">
                <input type="checkbox" checked={creditNoteForm.is_adjustment} onChange={(e) => setCreditNoteForm((p) => ({ ...p, is_adjustment: e.target.checked }))} className="mt-1" />
                <span>
                  Treat as adjustment
                  <span className="mt-0.5 block text-xs text-amber-700">Uses company vendor adjustment account when posted.</span>
                </span>
              </label>
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setCreditNoteVendor(null)}>Close</Button>
              <Button onClick={handleCreateCreditNote} disabled={saving}>Create credit note</Button>
            </div>
            {vendorCreditNotes.length > 0 && (
              <div className="mt-4 border-t border-border pt-4">
                <h4 className="mb-2 text-sm font-semibold text-ink">Credit notes</h4>
                <CreditNoteList
                  notes={vendorCreditNotes}
                  actionId={actionCreditNoteId}
                  onSubmit={(id) => void runCreditNoteAction('submit', id)}
                  onApprove={(id) => void runCreditNoteAction('approve', id)}
                  onPost={(id) => void runCreditNoteAction('post', id)}
                  onVoid={(note) => { setVoidCreditNote(note); setVoidCreditReason(''); }}
                />
              </div>
            )}
          </Modal>
        )}

        {detailBill && (
          <Modal title={`Bill ${detailBill.bill_number || `#${detailBill.id}`}`} onClose={() => setDetailBill(null)}>
            <div className="space-y-2 text-sm text-slate-600">
              <p>Vendor: {detailBill.vendor_name}</p>
              <p>Due: {detailBill.due_date}</p>
              <p>Total: {money(detailBill.total_amount, detailBill.currency)}</p>
              <p>Paid: {money(detailBill.paid_amount, detailBill.currency)}</p>
              <div className="flex gap-2 pt-1">
                <WorkflowBadge value={detailBill.workflow_status || 'draft'} />
                <WorkflowBadge value={detailBill.payment_status || 'unpaid'} />
              </div>
            </div>
            {detailBill.workflow_status === 'posted' && (
              <div className="mt-4">
                <Button
                  size="sm"
                  variant="secondary"
                  onClick={() => {
                    setCreditNoteVendor(vendors.find((v) => v.id === detailBill.vendor_id) || null);
                    setCreditNoteForm((p) => ({ ...p, bill_id: String(detailBill.id) }));
                    if (detailBill.vendor_id) void loadVendorCreditNotes(detailBill.vendor_id);
                  }}
                >
                  Issue credit note
                </Button>
              </div>
            )}
            {billPayments.length > 0 && (
              <div className="mt-4 border-t border-border pt-4">
                <h4 className="mb-2 text-sm font-semibold text-ink">Payments</h4>
                <ul className="space-y-2 text-sm">
                  {billPayments.map((payment) => (
                    <li key={payment.id} className="rounded-lg border border-border px-3 py-2">
                      {money(payment.amount, detailBill.currency)} · {payment.payment_date} · {payment.type || 'payment'}
                    </li>
                  ))}
                </ul>
              </div>
            )}
            {billCreditNotes.length > 0 && (
              <div className="mt-4 border-t border-border pt-4">
                <h4 className="mb-2 text-sm font-semibold text-ink">Credit notes</h4>
                <CreditNoteList
                  notes={billCreditNotes}
                  actionId={actionCreditNoteId}
                  onSubmit={(id) => void runCreditNoteAction('submit', id, 'bill')}
                  onApprove={(id) => void runCreditNoteAction('approve', id, 'bill')}
                  onPost={(id) => void runCreditNoteAction('post', id, 'bill')}
                  onVoid={(note) => { setVoidCreditNote(note); setVoidCreditReason(''); }}
                />
              </div>
            )}
          </Modal>
        )}

        {voidCreditNote && (
          <Modal title="Void credit note" onClose={() => setVoidCreditNote(null)}>
            <p className="mb-4 text-sm text-slate-600">Void credit note {voidCreditNote.credit_note_number}?</p>
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
            <Field label="Reason (optional)">
              <Input value={voidCreditReason} onChange={(e) => setVoidCreditReason(e.target.value)} />
            </Field>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setVoidCreditNote(null)}>Keep</Button>
              <Button onClick={() => void handleVoidCreditNote()} disabled={actionCreditNoteId === voidCreditNote.id}>Confirm void</Button>
            </div>
          </Modal>
        )}

        {voidBill && (
          <Modal title="Void bill" onClose={() => setVoidBill(null)}>
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
            <p className="text-sm text-slate-600">
              Void bill {voidBill.bill_number || `#${voidBill.id}`}? Posted bills cannot be voided.
            </p>
            <div className="mt-4">
              <label className="mb-1 block text-sm font-medium text-slate-700">Reason (optional)</label>
              <Input value={voidReason} onChange={(e) => setVoidReason(e.target.value)} placeholder="Why is this bill being voided?" />
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setVoidBill(null)}>Keep bill</Button>
              <Button onClick={() => void handleVoidBill()} disabled={actionBillId === voidBill.id}>
                Confirm void
              </Button>
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

function CreditNoteList({
  notes,
  actionId,
  onSubmit,
  onApprove,
  onPost,
  onVoid,
}: {
  notes: VendorCreditNote[];
  actionId: number | null;
  onSubmit: (id: number) => void;
  onApprove: (id: number) => void;
  onPost: (id: number) => void;
  onVoid: (note: VendorCreditNote) => void;
}) {
  return (
    <ul className="space-y-2 text-sm">
      {notes.map((note) => (
        <li key={note.id} className="rounded-lg border border-border px-3 py-2">
          <div className="flex flex-wrap items-start justify-between gap-2">
            <div>
              <p className="font-medium text-ink">{note.credit_note_number}</p>
              <p className="text-slate-600">{money(note.amount)} · {note.credit_date}</p>
              {Number(note.is_adjustment) === 1 && <p className="text-xs text-amber-700">Adjustment</p>}
              {Number(note.requires_second_approval) === 1 && <p className="text-xs text-amber-700">Requires second approval</p>}
            </div>
            <WorkflowBadge value={note.workflow_status} />
          </div>
          <div className="mt-2 flex flex-wrap gap-1">
            {note.workflow_status === 'draft' && (
              <>
                <Button size="sm" variant="secondary" disabled={actionId === note.id} onClick={() => onSubmit(note.id)}>Submit</Button>
                <Button size="sm" variant="secondary" disabled={actionId === note.id} onClick={() => onVoid(note)}>Void</Button>
              </>
            )}
            {note.workflow_status === 'submitted' && (
              <>
                <Button size="sm" variant="secondary" disabled={actionId === note.id} onClick={() => onApprove(note.id)}>Approve</Button>
                <Button size="sm" variant="secondary" disabled={actionId === note.id} onClick={() => onVoid(note)}>Void</Button>
              </>
            )}
            {note.workflow_status === 'approved' && (
              <Button size="sm" variant="secondary" disabled={actionId === note.id} onClick={() => onPost(note.id)}>Post</Button>
            )}
          </div>
        </li>
      ))}
    </ul>
  );
}

function money(value?: string | number, currency = 'USD') {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(Number(value || 0));
}
