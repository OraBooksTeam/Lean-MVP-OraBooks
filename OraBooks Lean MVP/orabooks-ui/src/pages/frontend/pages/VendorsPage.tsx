import { useEffect, useMemo, useState, type ReactNode } from 'react';
import WpLink from '../components/WpLink';

import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { Building2, FileText, Info, Paperclip, Plus, RefreshCw, Settings2, Trash2, Wallet } from 'lucide-react';

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
  description?: string | null;
  line_items?: BillLineItem[];
};

type BillLineItem = {
  id?: number;
  line_number?: number;
  description?: string;
  quantity?: string | number;
  unit_price?: string | number;
  line_total?: string | number;
  sku_code?: string | null;
};

type BillLineItemForm = {
  description: string;
  quantity: string;
  unit_price: string;
  sku_code: string;
};

const emptyBillLineItem = (): BillLineItemForm => ({
  description: '',
  quantity: '1',
  unit_price: '',
  sku_code: '',
});

const billLineItemsSubtotal = (items: BillLineItemForm[]) =>
  items.reduce((sum, line) => {
    const qty = parseFloat(line.quantity) || 0;
    const price = parseFloat(line.unit_price) || 0;
    return sum + (qty * price);
  }, 0);

const billLineItemTotal = (line: BillLineItemForm) => {
  const qty = parseFloat(line.quantity) || 0;
  const price = parseFloat(line.unit_price) || 0;
  return Math.round(qty * price * 100) / 100;
};

const buildBillLineItemsPayload = (items: BillLineItemForm[]) =>
  items
    .filter((line) => line.description.trim())
    .map((line) => {
      const quantity = parseFloat(line.quantity) || 1;
      const unit_price = parseFloat(line.unit_price) || 0;
      return {
        description: line.description.trim(),
        quantity,
        unit_price,
        line_total: Math.round(quantity * unit_price * 100) / 100,
        sku_code: line.sku_code.trim() || undefined,
      };
    })
    .filter((line) => line.line_total > 0);

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
  const [billLineItems, setBillLineItems] = useState<BillLineItemForm[]>([emptyBillLineItem()]);

  const [paymentVendor, setPaymentVendor] = useState<Vendor | null>(null);
  const [paymentForm, setPaymentForm] = useState({
    amount: '',
    payment_date: new Date().toISOString().slice(0, 10),
    payment_method: 'bank_transfer',
    reference: '',
    notes: '',
  });

  const [creditNoteVendor, setCreditNoteVendor] = useState<Vendor | null>(null);
  const [creditNoteBill, setCreditNoteBill] = useState<Bill | null>(null);
  const [creditNoteForm, setCreditNoteForm] = useState({
    amount: '',
    reason: '',
    credit_date: new Date().toISOString().slice(0, 10),
    is_adjustment: false,
  });

  const [selectedVendorId, setSelectedVendorId] = useState<number | null>(null);
  const [vendorDetail, setVendorDetail] = useState<any>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [selectedBillId, setSelectedBillId] = useState<number | null>(null);
  const [billDetail, setBillDetail] = useState<any>(null);
  const [billDetailLoading, setBillDetailLoading] = useState(false);
  const [showApSettings, setShowApSettings] = useState(false);
  const [apConfig, setApConfig] = useState<any>(null);
  const [apConfigForm, setApConfigForm] = useState({
    auto_post_bill_on_approve: true,
    auto_apply_vendor_credit: true,
    adjustment_threshold: '1000',
    vendor_adjustment_account: '5000',
    ap_account_code: '2000',
    expense_account_code: '5000',
    cash_account_code: '1000',
  });

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

    const [dashRes, vendorsRes, billsRes, agingRes, taxRes, apConfigRes] = await Promise.all([
      api.vendorDashboard(),
      api.vendorsList(nextOrgId, { limit: 100 }),
      api.billsList(nextOrgId, { limit: 100 }),
      api.apAging(nextOrgId),
      api.taxListConfigs(nextOrgId),
      api.apConfigGet(nextOrgId),
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
    if (!(apConfigRes as any).error) {
      const cfg = (apConfigRes as any).data?.config || {};
      setApConfig(cfg);
      setApConfigForm({
        auto_post_bill_on_approve: Number(cfg.auto_post_bill_on_approve ?? 1) === 1,
        auto_apply_vendor_credit: Number(cfg.auto_apply_vendor_credit ?? 1) === 1,
        adjustment_threshold: String(cfg.adjustment_threshold ?? 1000),
        vendor_adjustment_account: cfg.vendor_adjustment_account || '5000',
        ap_account_code: cfg.ap_account_code || '2000',
        expense_account_code: cfg.expense_account_code || '5000',
        cash_account_code: cfg.cash_account_code || '1000',
      });
    }

    setLoading(false);
  };

  const loadVendorDetail = async (vendorId: number) => {
    if (!orgId) return;
    setSelectedVendorId(vendorId);
    setDetailLoading(true);
    const res = await api.vendorGet(orgId, vendorId);
    if (res.error) setError(res.error);
    else setVendorDetail((res as any).data);
    setDetailLoading(false);
  };

  const loadBillDetail = async (billId: number) => {
    if (!orgId) return;
    setSelectedBillId(billId);
    setBillDetailLoading(true);
    const res = await api.billGet(orgId, billId);
    if (res.error) setError(res.error);
    else setBillDetail((res as any).data);
    setBillDetailLoading(false);
  };

  const saveApConfig = async () => {
    if (!orgId) return;
    setSaving(true);
    setError('');
    const res = await api.apConfigSet(orgId, {
      auto_post_bill_on_approve: apConfigForm.auto_post_bill_on_approve ? 1 : 0,
      auto_apply_vendor_credit: apConfigForm.auto_apply_vendor_credit ? 1 : 0,
      adjustment_threshold: parseFloat(apConfigForm.adjustment_threshold) || 1000,
      vendor_adjustment_account: apConfigForm.vendor_adjustment_account.trim(),
      ap_account_code: apConfigForm.ap_account_code.trim(),
      expense_account_code: apConfigForm.expense_account_code.trim(),
      cash_account_code: apConfigForm.cash_account_code.trim(),
    });
    if (res.error) setError(res.error);
    else {
      setSuccess('AP settings saved.');
      setShowApSettings(false);
      await load();
    }
    setSaving(false);
  };

  const runCreditNoteAction = async (
    action: 'submit' | 'approve' | 'post' | 'void',
    creditNoteId: number
  ) => {
    if (!orgId) return;
    setSaving(true);
    setError('');
    const res = action === 'submit'
      ? await api.vendorCreditNoteSubmit(orgId, creditNoteId)
      : action === 'approve'
        ? await api.vendorCreditNoteApprove(orgId, creditNoteId)
        : action === 'post'
          ? await api.vendorCreditNotePost(orgId, creditNoteId)
          : await api.vendorCreditNoteVoid(orgId, creditNoteId);
    if (res.error) setError(res.error);
    else {
      setSuccess(`Credit note ${action === 'void' ? 'voided' : action + 'ed'}.`);
      if (selectedVendorId) await loadVendorDetail(selectedVendorId);
      if (selectedBillId) await loadBillDetail(selectedBillId);
      await load();
    }
    setSaving(false);
  };

  const reversePayment = async (paymentId: number, reason: string) => {
    if (!orgId || !reason.trim()) {
      setError('Reversal reason is required.');
      return;
    }
    setSaving(true);
    setError('');
    const res = await api.vendorPaymentReverse(orgId, paymentId, reason.trim());
    if (res.error) setError(res.error);
    else {
      setSuccess('Payment reversed.');
      if (selectedVendorId) await loadVendorDetail(selectedVendorId);
      await load();
    }
    setSaving(false);
  };

  useEffect(() => { void load(); }, []);

  useEffect(() => {
    if (!showBillForm || !orgId) {
      setBillPreview(null);
      return;
    }
    const subtotal = billLineItemsSubtotal(billLineItems);
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
  }, [showBillForm, billLineItems, billForm.jurisdiction, orgId]);

  const billCreateTotals = useMemo(() => {
    const subtotal = billLineItemsSubtotal(billLineItems);
    const taxAmount = billPreview?.tax_amount ?? 0;
    const taxRate = billPreview?.tax_rate ?? 0;
    return { subtotal, taxAmount, taxRate, total: subtotal + taxAmount };
  }, [billLineItems, billPreview]);

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
    const lines = buildBillLineItemsPayload(billLineItems);
    const subtotal = lines.length ? billLineItemsSubtotal(billLineItems) : 0;

    if (!orgId || !billForm.vendor_id || subtotal <= 0) {
      setError('Vendor and at least one valid line item are required.');
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
      line_items: lines,
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
      setBillLineItems([emptyBillLineItem()]);
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
      bill_id: creditNoteBill?.id,
      amount: parseFloat(creditNoteForm.amount) || 0,
      reason: creditNoteForm.reason.trim(),
      credit_date: creditNoteForm.credit_date,
      is_adjustment: creditNoteForm.is_adjustment ? 1 : 0,
    });
    if (res.error) setError(res.error);
    else {
      setSuccess('Vendor credit note created.');
      setCreditNoteVendor(null);
      setCreditNoteBill(null);
      setCreditNoteForm({
        amount: '',
        reason: '',
        credit_date: new Date().toISOString().slice(0, 10),
        is_adjustment: false,
      });
      if (selectedVendorId) await loadVendorDetail(selectedVendorId);
      await load();
    }
    setSaving(false);
  };

  return (
    <ClientShell
      title="Vendors & Bills"
      eyebrow="Accounts payable"
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
          <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">AP Aging</h2>
            <WpLink
              href="/ap-aging"
              className="inline-flex items-center justify-center rounded-lg border border-primary/30 bg-white px-3 py-1.5 text-sm font-semibold text-primary hover:border-primary hover:bg-primary/5"
            >
              Full AP aging report
            </WpLink>
          </div>
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
          <Button size="sm" onClick={() => { setShowBillForm(true); setBillLineItems([emptyBillLineItem()]); setError(''); setSuccess(''); }}>
            <Plus className="h-4 w-4" />
            Create bill
          </Button>
          <Button size="sm" variant="secondary" onClick={() => { setShowApSettings(true); setError(''); setSuccess(''); }}>
            <Settings2 className="h-4 w-4" />
            AP settings
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

        <div className="grid gap-5 xl:grid-cols-[1.2fr_1fr]">
        <div className="space-y-5">
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
                <tr
                  key={vendor.id}
                  className={`cursor-pointer hover:bg-slate-50/70 ${selectedVendorId === vendor.id ? 'bg-accent/10 ring-2 ring-inset ring-accent/30' : ''}`}
                  onClick={() => void loadVendorDetail(vendor.id)}
                >
                  <td className="px-5 py-3">
                    <p className="font-semibold text-ink">{vendor.name}</p>
                    {vendor.email && <p className="text-xs text-slate-500">{vendor.email}</p>}
                  </td>
                  <td className="px-5 py-3 text-slate-600">{vendor.payment_terms ?? 30} days</td>
                  <td className="px-5 py-3"><StatusBadge active={Number(vendor.is_active) === 1} /></td>
                  <td className="px-5 py-3 text-right font-bold text-ink">{money(vendor.payable_balance)}</td>
                  <td className="px-5 py-3 text-right text-slate-600">{money(vendor.credit_balance)}</td>
                  <td className="px-5 py-3">
                    <div className="flex flex-wrap gap-1" onClick={(e) => e.stopPropagation()}>
                      <Button size="sm" variant="secondary" onClick={() => { setEditingVendor(vendor); setVendorForm(vendorToForm(vendor)); setError(''); }}>
                        Edit
                      </Button>
                      {Number(vendor.payable_balance || 0) > 0 && (
                        <Button size="sm" variant="secondary" onClick={() => openPayment(vendor)}>
                          <Wallet className="h-3.5 w-3.5" />
                          Pay
                        </Button>
                      )}
                      <Button size="sm" variant="secondary" onClick={() => { setCreditNoteVendor(vendor); setCreditNoteBill(null); setError(''); }}>
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
                <tr
                  key={bill.id}
                  className={`cursor-pointer hover:bg-slate-50/70 ${selectedBillId === bill.id ? 'bg-accent/10 ring-2 ring-inset ring-accent/30' : ''}`}
                  onClick={() => void loadBillDetail(bill.id)}
                >
                  <td className="px-5 py-3 font-semibold text-ink">{bill.bill_number || `Bill #${bill.id}`}</td>
                  <td className="px-5 py-3 text-slate-600">{bill.vendor_name || '—'}</td>
                  <td className="px-5 py-3 text-slate-600">{bill.due_date || '—'}</td>
                  <td className="px-5 py-3"><WorkflowBadge value={bill.workflow_status || 'draft'} /></td>
                  <td className="px-5 py-3"><WorkflowBadge value={bill.payment_status || 'unpaid'} /></td>
                  <td className="px-5 py-3 text-right font-bold text-ink">{money(bill.total_amount, bill.currency)}</td>
                  <td className="px-5 py-3">
                    <div className="flex flex-wrap gap-1" onClick={(e) => e.stopPropagation()}>
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
        </div>

        <div className="glass-panel p-5">
          {!selectedVendorId && !selectedBillId ? (
            <p className="text-sm text-slate-500">Select a vendor or bill to view details, payments, and credit notes.</p>
          ) : selectedBillId ? (
            <BillDetailPanel
              bill={billDetail?.bill}
              lineItems={billDetail?.line_items || []}
              creditNotes={billDetail?.credit_notes || []}
              loading={billDetailLoading}
              actionBillId={actionBillId}
              onClose={() => { setSelectedBillId(null); setBillDetail(null); }}
              onAction={runBillAction}
              onVoid={(bill) => { setVoidBill(bill); setVoidReason(''); }}
              onCreditNote={(bill) => {
                const vendor = vendors.find((v) => v.id === bill.vendor_id);
                if (vendor) {
                  setCreditNoteVendor(vendor);
                  setCreditNoteBill(bill);
                  setCreditNoteForm((p) => ({ ...p, amount: String(Number(bill.total_amount || 0) - Number(bill.paid_amount || 0)) }));
                }
              }}
              onPayment={(bill) => {
                const vendor = vendors.find((v) => v.id === bill.vendor_id);
                if (vendor) openPayment(vendor);
              }}
              canVoid={canVoidBill}
            />
          ) : (
            <VendorDetailPanel
              detail={vendorDetail}
              loading={detailLoading}
              saving={saving}
              onClose={() => { setSelectedVendorId(null); setVendorDetail(null); }}
              onPayment={(vendor) => openPayment(vendor)}
              onCreditNote={(vendor) => { setCreditNoteVendor(vendor); setCreditNoteBill(null); setError(''); }}
              onCreateBill={(vendorId) => {
                setShowBillForm(true);
                setBillLineItems([emptyBillLineItem()]);
                setBillForm((p) => ({ ...p, vendor_id: String(vendorId) }));
              }}
              onCreditNoteAction={runCreditNoteAction}
              onReversePayment={reversePayment}
            />
          )}
        </div>
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
          <Modal title="Create bill" onClose={() => setShowBillForm(false)} wide>
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
              <Field label="Jurisdiction">
                <select value={billForm.jurisdiction} onChange={(e) => setBillForm((p) => ({ ...p, jurisdiction: e.target.value }))} className="w-full rounded-lg border border-border px-3 py-2.5 text-sm">
                  {(taxConfigs.length ? taxConfigs : [{ jurisdiction: 'US' }]).map((c) => (
                    <option key={c.jurisdiction} value={c.jurisdiction}>{c.jurisdiction}</option>
                  ))}
                </select>
              </Field>

              <div>
                <div className="mb-2 flex items-center justify-between">
                  <span className="text-sm font-medium text-slate-700">Line items</span>
                  <Button size="sm" variant="secondary" onClick={() => setBillLineItems((items) => [...items, emptyBillLineItem()])}>
                    <Plus className="h-3.5 w-3.5" />
                    Add line
                  </Button>
                </div>
                <div className="mb-2 hidden text-xs font-semibold uppercase tracking-wide text-slate-500 sm:grid sm:grid-cols-12 sm:gap-2 sm:px-3">
                  <div className="sm:col-span-2">Code</div>
                  <div className="sm:col-span-4">Description</div>
                  <div className="sm:col-span-2">Qty</div>
                  <div className="sm:col-span-2">Unit price</div>
                  <div className="sm:col-span-2 text-right">Line total</div>
                </div>
                <div className="space-y-2">
                  {billLineItems.map((line, index) => (
                    <div key={index} className="grid gap-2 rounded-lg border border-border p-3 sm:grid-cols-12">
                      <div className="sm:col-span-2">
                        <Input value={line.sku_code} onChange={(e) => setBillLineItems((items) => items.map((row, i) => i === index ? { ...row, sku_code: e.target.value } : row))} placeholder="Code" />
                      </div>
                      <div className="sm:col-span-4">
                        <Input value={line.description} onChange={(e) => setBillLineItems((items) => items.map((row, i) => i === index ? { ...row, description: e.target.value } : row))} placeholder="Description" />
                      </div>
                      <div className="sm:col-span-2">
                        <Input type="number" min="0" step="0.01" value={line.quantity} onChange={(e) => setBillLineItems((items) => items.map((row, i) => i === index ? { ...row, quantity: e.target.value } : row))} placeholder="Qty" />
                      </div>
                      <div className="sm:col-span-2">
                        <Input type="number" min="0" step="0.01" value={line.unit_price} onChange={(e) => setBillLineItems((items) => items.map((row, i) => i === index ? { ...row, unit_price: e.target.value } : row))} placeholder="Unit price" />
                      </div>
                      <div className="flex items-center justify-end sm:col-span-2">
                        <span className="mr-2 text-sm font-medium text-ink">{money(billLineItemTotal(line), billForm.currency)}</span>
                        {billLineItems.length > 1 && (
                          <Button size="sm" variant="secondary" onClick={() => setBillLineItems((items) => items.filter((_, i) => i !== index))}>
                            <Trash2 className="h-3.5 w-3.5" />
                          </Button>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              <Field label="Notes"><Input value={billForm.description} onChange={(e) => setBillForm((p) => ({ ...p, description: e.target.value }))} placeholder="Optional bill note" /></Field>
              {billCreateTotals.subtotal > 0 && (
                <div className="rounded-lg border border-border bg-slate-50 p-4 text-sm">
                  <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Bill totals</div>
                  <div className="space-y-1">
                    <div className="flex items-center justify-between">
                      <span className="text-slate-600">Subtotal</span>
                      <span>{money(billCreateTotals.subtotal, billForm.currency)}</span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="text-slate-600">Tax ({billCreateTotals.taxRate.toFixed(2)}%)</span>
                      <span>{money(billCreateTotals.taxAmount, billForm.currency)}</span>
                    </div>
                    <div className="mt-2 flex items-center justify-between border-t border-border pt-2 text-base font-semibold text-ink">
                      <span>Total</span>
                      <span>{money(billCreateTotals.total, billForm.currency)}</span>
                    </div>
                  </div>
                </div>
              )}
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setShowBillForm(false)}>Cancel</Button>
              <Button onClick={handleCreateBill} disabled={saving || !billForm.vendor_id || billCreateTotals.subtotal <= 0}>Create bill</Button>
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
          <Modal title="Create vendor credit note" onClose={() => { setCreditNoteVendor(null); setCreditNoteBill(null); }}>
            <p className="mb-4 text-sm text-slate-600">
              {creditNoteVendor.name}
              {creditNoteBill ? ` — bill ${creditNoteBill.bill_number || `#${creditNoteBill.id}`}` : ''}
            </p>
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
            <div className="grid gap-4">
              <Field label="Amount"><Input type="number" min="0" step="0.01" value={creditNoteForm.amount} onChange={(e) => setCreditNoteForm((p) => ({ ...p, amount: e.target.value }))} /></Field>
              <Field label="Credit date"><Input type="date" value={creditNoteForm.credit_date} onChange={(e) => setCreditNoteForm((p) => ({ ...p, credit_date: e.target.value }))} /></Field>
              <Field label="Reason"><Input value={creditNoteForm.reason} onChange={(e) => setCreditNoteForm((p) => ({ ...p, reason: e.target.value }))} placeholder="Return, adjustment, etc." /></Field>
              <label className="flex items-start gap-2 text-sm text-slate-700" title="Uses company's vendor adjustment account (configurable).">
                <input
                  type="checkbox"
                  checked={creditNoteForm.is_adjustment}
                  onChange={(e) => setCreditNoteForm((p) => ({ ...p, is_adjustment: e.target.checked }))}
                  className="mt-1"
                />
                <span>
                  Treat as adjustment
                  <span className="block text-xs text-slate-500">Uses vendor adjustment account from AP settings.</span>
                </span>
              </label>
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => { setCreditNoteVendor(null); setCreditNoteBill(null); }}>Cancel</Button>
              <Button onClick={handleCreateCreditNote} disabled={saving}>Create credit note</Button>
            </div>
          </Modal>
        )}

        {showApSettings && (
          <Modal title="AP company settings" onClose={() => setShowApSettings(false)}>
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
            <div className="grid gap-4">
              <label className="flex items-center gap-2 text-sm text-slate-700" title="Approve and post immediately.">
                <input type="checkbox" checked={apConfigForm.auto_post_bill_on_approve} onChange={(e) => setApConfigForm((p) => ({ ...p, auto_post_bill_on_approve: e.target.checked }))} />
                Auto-post bill on approve
              </label>
              <label className="flex items-center gap-2 text-sm text-slate-700" title="Use credit balance automatically.">
                <input type="checkbox" checked={apConfigForm.auto_apply_vendor_credit} onChange={(e) => setApConfigForm((p) => ({ ...p, auto_apply_vendor_credit: e.target.checked }))} />
                Auto-apply vendor credit
              </label>
              <Field label="Adjustment threshold"><Input type="number" min="0" step="0.01" value={apConfigForm.adjustment_threshold} onChange={(e) => setApConfigForm((p) => ({ ...p, adjustment_threshold: e.target.value }))} /></Field>
              <Field label="Vendor adjustment account"><Input value={apConfigForm.vendor_adjustment_account} onChange={(e) => setApConfigForm((p) => ({ ...p, vendor_adjustment_account: e.target.value }))} /></Field>
              <Field label="AP account code"><Input value={apConfigForm.ap_account_code} onChange={(e) => setApConfigForm((p) => ({ ...p, ap_account_code: e.target.value }))} /></Field>
              <Field label="Expense account code"><Input value={apConfigForm.expense_account_code} onChange={(e) => setApConfigForm((p) => ({ ...p, expense_account_code: e.target.value }))} /></Field>
              <Field label="Cash account code"><Input value={apConfigForm.cash_account_code} onChange={(e) => setApConfigForm((p) => ({ ...p, cash_account_code: e.target.value }))} /></Field>
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setShowApSettings(false)}>Cancel</Button>
              <Button onClick={() => void saveApConfig()} disabled={saving}>Save settings</Button>
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

function Modal({ title, children, onClose, wide = false }: { title: string; children: ReactNode; onClose: () => void; wide?: boolean }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" onClick={onClose}>
      <div className={`max-h-[90vh] w-full overflow-y-auto rounded-2xl border border-border bg-white p-6 shadow-xl ${wide ? 'max-w-3xl' : 'max-w-lg'}`} onClick={(e) => e.stopPropagation()}>
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

function VendorDetailPanel({
  detail,
  loading,
  saving,
  onClose,
  onPayment,
  onCreditNote,
  onCreateBill,
  onCreditNoteAction,
  onReversePayment,
}: {
  detail: any;
  loading: boolean;
  saving: boolean;
  onClose: () => void;
  onPayment: (vendor: Vendor) => void;
  onCreditNote: (vendor: Vendor) => void;
  onCreateBill: (vendorId: number) => void;
  onCreditNoteAction: (action: 'submit' | 'approve' | 'post' | 'void', id: number) => void;
  onReversePayment: (paymentId: number, reason: string) => void;
}) {
  const vendor = detail?.vendor as Vendor | undefined;
  const bills = detail?.bills || [];
  const payments = detail?.payments || [];
  const creditNotes = detail?.credit_notes || [];

  if (loading) return <p className="text-sm text-slate-500">Loading vendor detail…</p>;
  if (!vendor) return <p className="text-sm text-slate-500">Vendor not found.</p>;

  return (
    <div className="space-y-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h3 className="text-lg font-bold text-ink">{vendor.name}</h3>
          <p className="text-sm text-slate-500">{vendor.email || 'No email'} · {vendor.payment_terms ?? 30} day terms</p>
        </div>
        <Button variant="secondary" size="sm" onClick={onClose}>Close</Button>
      </div>

      <div className="grid gap-3 sm:grid-cols-2 text-sm">
        <div className="rounded-lg border border-border bg-slate-50/70 p-3">
          <p className="text-xs uppercase text-slate-500">Payable balance</p>
          <p className="mt-1 text-xl font-bold text-ink">{money(vendor.payable_balance)}</p>
        </div>
        <div className="rounded-lg border border-border bg-slate-50/70 p-3">
          <p className="text-xs uppercase text-slate-500">Credit balance</p>
          <p className="mt-1 text-xl font-bold text-ink">{money(vendor.credit_balance)}</p>
        </div>
      </div>

      <div className="flex flex-wrap gap-2">
        <Button size="sm" onClick={() => onCreateBill(vendor.id)}>Create bill</Button>
        <Button size="sm" variant="secondary" onClick={() => onPayment(vendor)}>Record payment</Button>
        <Button size="sm" variant="secondary" onClick={() => onCreditNote(vendor)}>Issue credit note</Button>
      </div>

      <DetailTable title="Bills" empty="No bills for this vendor.">
        {bills.map((bill: Bill) => (
          <tr key={bill.id}>
            <td className="px-3 py-2 font-medium">{bill.bill_number || `#${bill.id}`}</td>
            <td className="px-3 py-2"><WorkflowBadge value={bill.workflow_status || 'draft'} /></td>
            <td className="px-3 py-2 text-right">{money(bill.total_amount, bill.currency)}</td>
          </tr>
        ))}
      </DetailTable>

      <DetailTable title="Payments" empty="No payments recorded.">
        {payments.map((payment: any) => (
          <tr key={payment.id}>
            <td className="px-3 py-2">{payment.payment_date}</td>
            <td className="px-3 py-2">{payment.type || 'payment'}</td>
            <td className="px-3 py-2 text-right">{money(payment.amount)}</td>
            <td className="px-3 py-2">
              {payment.type === 'payment' && (
                <Button
                  size="sm"
                  variant="secondary"
                  disabled={saving}
                  onClick={() => {
                    const reason = window.prompt('Reversal reason:');
                    if (reason) void onReversePayment(Number(payment.id), reason);
                  }}
                >
                  Reverse
                </Button>
              )}
            </td>
          </tr>
        ))}
      </DetailTable>

      <DetailTable title="Credit notes" empty="No credit notes.">
        {creditNotes.map((note: any) => (
          <tr key={note.id}>
            <td className="px-3 py-2 font-medium">{note.credit_note_number}</td>
            <td className="px-3 py-2"><WorkflowBadge value={note.workflow_status} /></td>
            <td className="px-3 py-2 text-right">{money(note.amount)}</td>
            <td className="px-3 py-2">
              <div className="flex flex-wrap gap-1">
                {note.workflow_status === 'draft' && (
                  <Button size="sm" variant="secondary" disabled={saving} onClick={() => void onCreditNoteAction('submit', note.id)}>Submit</Button>
                )}
                {note.workflow_status === 'submitted' && (
                  <Button size="sm" variant="secondary" disabled={saving} onClick={() => void onCreditNoteAction('approve', note.id)}>Approve</Button>
                )}
                {['draft', 'submitted', 'approved'].includes(note.workflow_status) && (
                  <Button size="sm" variant="secondary" disabled={saving} onClick={() => void onCreditNoteAction('post', note.id)}>Post</Button>
                )}
                {['draft', 'submitted'].includes(note.workflow_status) && (
                  <Button size="sm" variant="secondary" disabled={saving} onClick={() => void onCreditNoteAction('void', note.id)}>Void</Button>
                )}
              </div>
            </td>
          </tr>
        ))}
      </DetailTable>
    </div>
  );
}

function BillDetailPanel({
  bill,
  lineItems,
  creditNotes,
  loading,
  actionBillId,
  onClose,
  onAction,
  onVoid,
  onCreditNote,
  onPayment,
  canVoid,
}: {
  bill: Bill | undefined;
  lineItems: BillLineItem[];
  creditNotes: any[];
  loading: boolean;
  actionBillId: number | null;
  onClose: () => void;
  onAction: (action: 'submit' | 'approve' | 'post', billId: number) => void;
  onVoid: (bill: Bill) => void;
  onCreditNote: (bill: Bill) => void;
  onPayment: (bill: Bill) => void;
  canVoid: (bill: Bill) => boolean;
}) {
  if (loading) return <p className="text-sm text-slate-500">Loading bill detail…</p>;
  if (!bill) return <p className="text-sm text-slate-500">Bill not found.</p>;

  const outstanding = Math.max(0, Number(bill.total_amount || 0) - Number(bill.paid_amount || 0));

  return (
    <div className="space-y-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h3 className="text-lg font-bold text-ink">{bill.bill_number || `Bill #${bill.id}`}</h3>
          <p className="text-sm text-slate-500">{bill.vendor_name} · Due {bill.due_date || '—'}</p>
        </div>
        <Button variant="secondary" size="sm" onClick={onClose}>Close</Button>
      </div>

      <div className="flex flex-wrap gap-2">
        <WorkflowBadge value={bill.workflow_status || 'draft'} />
        <WorkflowBadge value={bill.payment_status || 'unpaid'} />
      </div>

      <div className="grid gap-2 text-sm">
        <p><span className="text-slate-500">Subtotal:</span> {money(bill.subtotal_amount, bill.currency)}</p>
        <p><span className="text-slate-500">Tax:</span> {money(bill.tax_amount, bill.currency)}</p>
        <p><span className="text-slate-500">Total:</span> <strong>{money(bill.total_amount, bill.currency)}</strong></p>
        <p><span className="text-slate-500">Paid:</span> {money(bill.paid_amount, bill.currency)}</p>
        <p><span className="text-slate-500">Outstanding:</span> <strong>{money(outstanding, bill.currency)}</strong></p>
      </div>

      <div className="flex flex-wrap gap-2">
        {bill.workflow_status === 'draft' && (
          <Button size="sm" variant="secondary" disabled={actionBillId === bill.id} onClick={() => void onAction('submit', bill.id)}>Submit</Button>
        )}
        {bill.workflow_status === 'submitted' && (
          <Button size="sm" variant="secondary" disabled={actionBillId === bill.id} onClick={() => void onAction('approve', bill.id)}>Approve</Button>
        )}
        {bill.workflow_status === 'approved' && (
          <Button size="sm" variant="secondary" disabled={actionBillId === bill.id} onClick={() => void onAction('post', bill.id)}>Post</Button>
        )}
        {bill.workflow_status === 'posted' && outstanding > 0 && (
          <Button size="sm" variant="secondary" onClick={() => onPayment(bill)}>Record payment</Button>
        )}
        {bill.workflow_status === 'posted' && (
          <Button size="sm" variant="secondary" onClick={() => onCreditNote(bill)}>Issue credit note</Button>
        )}
        {canVoid(bill) && (
          <Button size="sm" variant="secondary" disabled={actionBillId === bill.id} onClick={() => onVoid(bill)}>Void</Button>
        )}
      </div>

      {bill.description && <p className="text-sm text-slate-600">{bill.description}</p>}

      {lineItems.length > 0 && (
        <DetailTable
          title="Line items"
          empty="No line items."
          headers={['Code', 'Description', 'Qty', 'Unit price', 'Total']}
        >
          {lineItems.map((line) => (
            <tr key={line.id || `${line.line_number}-${line.description}`}>
              <td className="px-3 py-2 text-slate-500">{line.sku_code || '—'}</td>
              <td className="px-3 py-2">{line.description}</td>
              <td className="px-3 py-2 text-right">{Number(line.quantity || 0)}</td>
              <td className="px-3 py-2 text-right">{money(line.unit_price, bill.currency)}</td>
              <td className="px-3 py-2 text-right font-medium">{money(line.line_total, bill.currency)}</td>
            </tr>
          ))}
        </DetailTable>
      )}

      <DetailTable title="Credit notes" empty="No credit notes for this bill.">
        {creditNotes.map((note: any) => (
          <tr key={note.id}>
            <td className="px-3 py-2">{note.credit_note_number}</td>
            <td className="px-3 py-2"><WorkflowBadge value={note.workflow_status} /></td>
            <td className="px-3 py-2 text-right">{money(note.amount)}</td>
          </tr>
        ))}
      </DetailTable>
    </div>
  );
}

function DetailTable({
  title,
  empty,
  children,
  headers,
}: {
  title: string;
  empty: string;
  children: ReactNode;
  headers?: string[];
}) {
  const rows = Array.isArray(children) ? children : [children];
  const hasRows = rows.some((row) => row);

  return (
    <div>
      <h4 className="mb-2 text-sm font-bold text-ink">{title}</h4>
      <div className="overflow-hidden rounded-xl border border-border">
        <table className="min-w-full text-left text-sm">
          {headers && headers.length > 0 && (
            <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                {headers.map((header) => (
                  <th key={header} className="px-3 py-2">{header}</th>
                ))}
              </tr>
            </thead>
          )}
          <tbody className="divide-y divide-border">
            {!hasRows ? (
              <tr><td colSpan={headers?.length || 1} className="px-3 py-4 text-center text-slate-500">{empty}</td></tr>
            ) : children}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function money(value?: string | number, currency = 'USD') {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(Number(value || 0));
}
