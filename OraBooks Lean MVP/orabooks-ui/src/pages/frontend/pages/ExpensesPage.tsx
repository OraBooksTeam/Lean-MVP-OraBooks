import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import WpLink from '../components/WpLink';

import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import ResourceAttachmentsPanel from '../components/ResourceAttachmentsPanel';
import {
  isOffline,
  listQueuedReceipts,
  queueReceipt,
  syncQueuedReceipts,
} from '@/lib/pwa/offline-receipt-queue';
import {
  isMobileDevice,
  isStandalonePwa,
  onOnline,
} from '@/lib/pwa/register-pwa';
import { Camera, CheckCircle2, CloudOff, Paperclip, Percent, Receipt, RefreshCw, Upload, XCircle } from 'lucide-react';
import ClassificationPanel from '@/components/classification/ClassificationPanel';
import ConfidenceBadge from '@/components/classification/ConfidenceBadge';
import OverrideClassificationModal from '@/components/classification/OverrideClassificationModal';
import { useClassificationPolling } from '@/components/classification/useClassificationPolling';
import TaxOverrideModal, { type TaxConfig } from '@/components/tax/TaxOverrideModal';
import ExpenseLineItemsPanel from '@/components/expenses/ExpenseLineItemsPanel';
import ExpenseOcrField from '@/components/expenses/ExpenseOcrField';
import OcrProcessingBanner from '@/components/expenses/OcrProcessingBanner';
import PaymentStatusBadge from '@/components/expenses/PaymentStatusBadge';
import { useExpenseOcrPolling } from '@/components/expenses/useExpenseOcrPolling';

const fieldClass =
  'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';

const PAYMENT_STATUS_OPTIONS = [
  { value: 'unpaid', label: 'Unpaid' },
  { value: 'paid', label: 'Paid' },
  { value: 'reimbursable', label: 'Reimbursable' },
] as const;

function buildEditFields(expense: any): Record<string, string> {
  return {
    vendor: expense?.vendor || '',
    vendor_tax_id: expense?.vendor_tax_id || '',
    invoice_number: expense?.invoice_number || '',
    transaction_date: expense?.transaction_date || '',
    due_date: expense?.due_date || '',
    subtotal: expense?.subtotal != null ? String(expense.subtotal) : '',
    total_amount: expense?.total_amount != null ? String(expense.total_amount) : '',
    tax_amount: expense?.tax_amount != null ? String(expense.tax_amount) : '',
    category: expense?.category || '',
    payment_method: expense?.payment_method || '',
    payment_status: expense?.payment_status || 'unpaid',
    merchant_address: expense?.merchant_address || '',
    description: expense?.description || '',
    account_code:
      expense?.account_code ||
      (expense?.classification?.status === 'processed' ? expense?.classification?.suggested_account_code || '' : ''),
  };
}

function fieldConfidence(expense: any, fieldKey: string): number | null {
  const value = expense?.ocr_data?.fields?.[fieldKey]?.confidence;
  return value != null ? Number(value) : null;
}

export default function ExpensesPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [uploading, setUploading] = useState(false);
  const [selectedExpense, setSelectedExpense] = useState<any>(null);
  const [editFields, setEditFields] = useState<Record<string, string>>({});
  const [confirming, setConfirming] = useState(false);
  const [actionId, setActionId] = useState<number | null>(null);
  const [classifying, setClassifying] = useState(false);
  const [showClassOverride, setShowClassOverride] = useState(false);
  const [taxConfigs, setTaxConfigs] = useState<TaxConfig[]>([]);
  const [overrideExpense, setOverrideExpense] = useState<any>(null);
  const [overrideRate, setOverrideRate] = useState('');
  const [overrideReason, setOverrideReason] = useState('');
  const [overrideJurisdiction, setOverrideJurisdiction] = useState('US');
  const [taxLocked, setTaxLocked] = useState(false);
  const [saving, setSaving] = useState(false);
  const [offlineQueueCount, setOfflineQueueCount] = useState(0);
  const [syncingOffline, setSyncingOffline] = useState(false);
  const [showMobileCamera, setShowMobileCamera] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const cameraInputRef = useRef<HTMLInputElement>(null);

  const orgId = data?.context?.organization?.id;
  const caps = data?.capabilities || {};
  const threshold = data?.threshold ?? 70;
  const maxMb = Math.round((data?.limits?.max_file_size || 10485760) / 1048576);

  const load = async () => {
    setLoading(true);
    setError('');
    setSuccess('');
    const res = await api.expensesDashboard();
    if (res.error) setError(res.error || 'Unable to load expenses.');
    else setData((res as any).data);
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  const refreshOfflineQueue = async () => {
    try {
      const items = await listQueuedReceipts();
      setOfflineQueueCount(items.length);
    } catch {
      setOfflineQueueCount(0);
    }
  };

  const syncOfflineQueue = async () => {
    if (!orgId || syncingOffline) return;
    setSyncingOffline(true);
    try {
      const result = await syncQueuedReceipts((oid, file, key) =>
        api.uploadExpenseReceipt(oid, file, key)
      );
      await refreshOfflineQueue();
      if (result.synced > 0) {
        setSuccess(`Synced ${result.synced} offline receipt${result.synced === 1 ? '' : 's'}.`);
        await load();
      }
      if (result.failed > 0) {
        setError(`${result.failed} offline receipt(s) could not sync yet.`);
      }
    } finally {
      setSyncingOffline(false);
    }
  };

  useEffect(() => {
    const updateCameraVisibility = () => {
      setShowMobileCamera(isMobileDevice() || isStandalonePwa());
    };

    updateCameraVisibility();
    window.addEventListener('resize', updateCameraVisibility);
    return () => window.removeEventListener('resize', updateCameraVisibility);
  }, []);

  useEffect(() => {
    void refreshOfflineQueue();
    return onOnline(() => {
      void syncOfflineQueue();
    });
  }, [orgId]);

  useEffect(() => {
    if (orgId && offlineQueueCount > 0 && !isOffline()) {
      void syncOfflineQueue();
    }
  }, [orgId, offlineQueueCount]);

  useEffect(() => {
    if (!orgId) return;
    void api.taxOverrideReasons(orgId).then((res) => {
      const configs = (res as any).data?.configs || [];
      setTaxConfigs(configs);
    });
  }, [orgId]);

  const openOverride = async (expense: any) => {
    setOverrideExpense(expense);
    setOverrideRate(String(Number(expense.tax_rate || 0)));
    setOverrideReason('');
    setOverrideJurisdiction(expense.tax_jurisdiction || taxConfigs[0]?.jurisdiction || 'US');
    setError('');

    if (orgId) {
      const lockRes = await api.taxLockStatus(orgId, expense.transaction_date);
      setTaxLocked(Boolean((lockRes as any).data?.tax_locked));
    } else {
      setTaxLocked(false);
    }
  };

  const overridePreview = useMemo(() => {
    if (!overrideExpense) return null;
    const total = Number(overrideExpense.total_amount || 0);
    const tax = Number(overrideExpense.tax_amount || 0);
    const taxBase = Math.max(0, Math.round((total - tax) * 100) / 100);
    const rate = parseFloat(overrideRate) || 0;
    const newTax = Math.round(taxBase * (rate / 100) * 100) / 100;
    return { taxBase, newTax, newTotal: Math.round((taxBase + newTax) * 100) / 100 };
  }, [overrideExpense, overrideRate]);

  const handleApplyOverride = async () => {
    if (!orgId || !overrideExpense || !overrideReason) {
      setError('A reason code is required for tax overrides.');
      return;
    }

    setSaving(true);
    setError('');
    const res = await api.expenseOverrideTax(
      orgId,
      overrideExpense.id,
      parseFloat(overrideRate) || 0,
      overrideReason,
      overrideJurisdiction
    );

    if (res.error) setError(res.error);
    else {
      setSuccess('Tax override applied.');
      setOverrideExpense(null);
      await load();
      if (selectedExpense?.id === overrideExpense.id) void loadExpense(overrideExpense.id);
    }
    setSaving(false);
  };

  const handleClearOverride = async () => {
    if (!orgId || !overrideExpense) return;
    setSaving(true);
    setError('');
    const res = await api.expenseClearTaxOverride(orgId, overrideExpense.id, overrideJurisdiction);
    if (res.error) setError(res.error);
    else {
      setSuccess('Tax override cleared.');
      setOverrideExpense(null);
      await load();
      if (selectedExpense?.id === overrideExpense.id) void loadExpense(overrideExpense.id);
    }
    setSaving(false);
  };

  const loadExpense = async (expenseId: number) => {
    if (!orgId) return;
    const res = await api.expenseGet(orgId, expenseId);
    if (res.error) setError(res.error);
    else {
      const expense = (res as any).data?.expense;
      setSelectedExpense(expense);
      setEditFields(buildEditFields(expense));
    }
  };

  const applyExpenseUpdate = useCallback((expense: Record<string, unknown>) => {
    setSelectedExpense(expense);
    setEditFields(buildEditFields(expense));
  }, []);

  const ocrPollingEnabled =
    selectedExpense?.workflow_status === 'draft' &&
    selectedExpense?.ocr_confidence == null &&
    selectedExpense?.ocr_queue?.status !== 'failed';

  useExpenseOcrPolling({
    orgId,
    expenseId: selectedExpense?.id,
    enabled: ocrPollingEnabled,
    onUpdate: applyExpenseUpdate,
  });

  useClassificationPolling({
    recordType: 'expense',
    recordId: selectedExpense?.id ?? 0,
    enabled: selectedExpense?.classification?.status === 'pending',
    onUpdate: (classification) => {
      setSelectedExpense((prev: any) => (prev ? { ...prev, classification } : prev));
      if (classification?.status === 'processed' && classification?.suggested_account_code) {
        setEditFields((prev) => ({
          ...prev,
          account_code: prev.account_code || classification.suggested_account_code,
        }));
      }
    },
  });

  const handleUpload = async (fileOverride?: File | null) => {
    const file = fileOverride ?? selectedFile;
    if (!orgId || !file) {
      setError('Select a receipt file to upload.');
      return;
    }

    setUploading(true);
    setError('');
    const idempotencyKey =
      typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `receipt-${Date.now()}-${Math.random().toString(36).slice(2)}`;

    if (isOffline()) {
      await queueReceipt(orgId, file, idempotencyKey);
      setSelectedFile(null);
      if (fileInputRef.current) fileInputRef.current.value = '';
      if (cameraInputRef.current) cameraInputRef.current.value = '';
      await refreshOfflineQueue();
      setSuccess('Offline — receipt saved locally and will sync when you are back online.');
      setUploading(false);
      return;
    }

    const res = await api.uploadExpenseReceipt(orgId, file, idempotencyKey);
    if (res.error) {
      setError(res.error);
    } else {
      setSelectedFile(null);
      if (fileInputRef.current) fileInputRef.current.value = '';
      if (cameraInputRef.current) cameraInputRef.current.value = '';
      setSuccess('Receipt uploaded. OCR extraction has started — fields will appear shortly.');
      await load();
      const expense = (res as any).data?.expense;
      if (expense?.id) void loadExpense(expense.id);
    }
    setUploading(false);
  };

  const handleCameraCapture = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0] || null;
    if (file) {
      setSelectedFile(file);
      void handleUpload(file);
    }
  };

  const handleConfirm = async () => {
    if (!orgId || !selectedExpense || selectedExpense.workflow_status !== 'draft') return;

    setConfirming(true);
    setError('');
    const idempotencyKey =
      typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `confirm-${Date.now()}-${Math.random().toString(36).slice(2)}`;

    const res = await api.expenseConfirm(orgId, selectedExpense.id, idempotencyKey, {
      ...editFields,
      subtotal: editFields.subtotal ? parseFloat(editFields.subtotal) : undefined,
      total_amount: parseFloat(editFields.total_amount || '0'),
      tax_amount: parseFloat(editFields.tax_amount || '0'),
    });

    if (res.error) {
      setError(res.error);
    } else {
      const expense = (res as any).data?.expense;
      setSuccess(
        expense?.workflow_status === 'ai_review'
          ? 'Expense sent to AI review (low confidence or elevated risk).'
          : 'Expense submitted for approval.'
      );
      setSelectedExpense(expense);
      await load();
    }
    setConfirming(false);
  };

  const handleApprove = async (expenseId: number) => {
    if (!orgId) return;
    setActionId(expenseId);
    setError('');
    const res = await api.expenseApprove(orgId, expenseId);
    if (res.error) setError(res.error);
    else {
      setSuccess('Expense approved and posted.');
      await load();
      if (selectedExpense?.id === expenseId) void loadExpense(expenseId);
    }
    setActionId(null);
  };

  const handleReject = async (expenseId: number) => {
    const reason = window.prompt('Enter rejection reason:');
    if (!reason?.trim() || !orgId) return;
    setActionId(expenseId);
    setError('');
    const res = await api.expenseReject(orgId, expenseId, reason.trim());
    if (res.error) setError(res.error);
    else {
      setSuccess('Expense rejected and returned to draft.');
      await load();
      if (selectedExpense?.id === expenseId) void loadExpense(expenseId);
    }
    setActionId(null);
  };

  const expenses = data?.expenses || [];
  const pending = data?.pending_approval || [];
  const stats = data?.stats || {};

  const ocrFailed = selectedExpense?.ocr_queue?.status === 'failed';
  const canEditDraft =
    selectedExpense?.workflow_status === 'draft' &&
    (selectedExpense?.ocr_confidence != null || ocrFailed);
  const canConfirmSubmit = caps.submit && canEditDraft;

  return (
    <ClientShell title="Expenses" eyebrow="Receipt OCR & approval" organization={data?.context?.organization}>
      <div className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Metric label="Total Expenses" value={stats.total ?? 0} />
          <Metric label="Draft / OCR" value={(stats.draft ?? 0) + (stats.pending_ocr ?? 0)} />
          <Metric label="Awaiting Approval" value={(stats.submitted ?? 0) + (stats.ai_review ?? 0)} tone="warning" />
          <Metric label="Posted" value={stats.posted ?? 0} tone="success" />
        </div>

        {caps.upload && (
          <div className="glass-panel p-5">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
              <div className="flex items-center gap-2">
                <Upload className="h-5 w-5 text-primary" />
                <h2 className="font-bold text-ink">Upload Receipt</h2>
              </div>
              {offlineQueueCount > 0 && (
                <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800">
                  <CloudOff className="h-3.5 w-3.5" />
                  {offlineQueueCount} queued offline
                  <button
                    type="button"
                    className="ml-1 underline"
                    disabled={syncingOffline || isOffline()}
                    onClick={() => void syncOfflineQueue()}
                  >
                    {syncingOffline ? 'Syncing…' : 'Sync now'}
                  </button>
                </span>
              )}
            </div>
            <p className="mt-3 text-sm text-slate-600">
              Supported: PDF, JPG, PNG. Max {maxMb}MB. AI extracts vendor, amount, date, and category.
            </p>
            <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
              <div className="flex-1">
                <input
                  ref={fileInputRef}
                  type="file"
                  accept=".pdf,.jpg,.jpeg,.png,image/*,application/pdf"
                  className={fieldClass}
                  onChange={(e) => setSelectedFile(e.target.files?.[0] || null)}
                />
              </div>
              <Button onClick={() => void handleUpload()} disabled={uploading || !selectedFile}>
                <Receipt className="h-4 w-4" />
                {uploading ? 'Processing...' : 'Upload Receipt'}
              </Button>
              {showMobileCamera && (
                <Button
                  variant="secondary"
                  onClick={() => cameraInputRef.current?.click()}
                  disabled={uploading}
                  title="Take a photo of receipt."
                >
                  <Camera className="h-4 w-4" />
                  Scan with Camera
                </Button>
              )}
              <input
                ref={cameraInputRef}
                type="file"
                accept="image/*"
                capture="environment"
                className="hidden"
                onChange={handleCameraCapture}
              />
            </div>
            {(showMobileCamera || offlineQueueCount > 0) && (
              <p className="mt-2 flex items-center gap-1.5 text-xs text-slate-500">
                {showMobileCamera && (
                  <>
                    <Camera className="h-3.5 w-3.5" />
                    PWA camera scan on mobile; receipts queue offline until connectivity returns.
                  </>
                )}
                {offlineQueueCount > 0 && isOffline() && (
                  <span className="inline-flex items-center gap-1 text-amber-700">
                    <CloudOff className="h-3.5 w-3.5" />
                    You are offline — queued receipts will sync automatically when back online.
                  </span>
                )}
              </p>
            )}
          </div>
        )}

        {selectedExpense && (
          <div className="glass-panel p-5">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
              <div>
                <h2 className="font-bold text-ink">OCR Result</h2>
                <p className="text-sm text-slate-600">
                  Expense #{selectedExpense.id} · {selectedExpense.workflow_status}
                </p>
                {selectedExpense.tax_override_reason && (
                  <span
                    className="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                    title={`Override: ${selectedExpense.tax_override_reason}`}
                  >
                    Tax Overridden
                  </span>
                )}
              </div>
              <div className="flex flex-wrap gap-2">
                {selectedExpense.ocr_confidence != null && (
                  <ConfidenceBadge value={selectedExpense.ocr_confidence} threshold={threshold} />
                )}
                {selectedExpense.ocr_risk_level && <RiskBadge level={selectedExpense.ocr_risk_level} />}
                {selectedExpense.tax_rate != null && (
                  <span className="inline-flex items-center gap-1 rounded-full border border-border bg-white px-2 py-0.5 text-xs text-slate-600">
                    <Percent className="h-3 w-3" />
                    {Number(selectedExpense.tax_rate).toFixed(2)}%
                  </span>
                )}
              </div>
            </div>

            {selectedExpense.workflow_status === 'draft' && selectedExpense.ocr_confidence != null ? (
              <div className="mt-4 grid gap-4 md:grid-cols-2">
                {(['vendor', 'invoice_number', 'transaction_date', 'total_amount', 'tax_amount', 'category', 'account_code'] as const).map(
                  (field) => (
                    <label key={field} className="block text-sm">
                      <span className="mb-1 block font-semibold capitalize text-slate-700">
                        {field.replace('_', ' ')}
                      </span>
                      <input
                        className={fieldClass}
                        value={editFields[field] || ''}
                        onChange={(e) => setEditFields((prev) => ({ ...prev, [field]: e.target.value }))}
                      />
                    </label>
                  )
                )}
                <label className="block text-sm md:col-span-2">
                  <span className="mb-1 block font-semibold text-slate-700">Description</span>
                  <input
                    className={fieldClass}
                    value={editFields.description || ''}
                    onChange={(e) => setEditFields((prev) => ({ ...prev, description: e.target.value }))}
                  />
                </label>
              </div>
            ) : (
              <div className="mt-4 grid gap-2 text-sm text-slate-700 md:grid-cols-2">
                <p>
                  <strong>Vendor:</strong> {selectedExpense.vendor || '—'}
                </p>
                <p>
                  <strong>Amount:</strong> {money(selectedExpense.total_amount)}
                </p>
                <p>
                  <strong>Date:</strong> {selectedExpense.transaction_date || '—'}
                </p>
                <p>
                  <strong>Category:</strong> {selectedExpense.category || '—'}
                </p>
              </div>
            )}

            {selectedExpense.classification && (
              <ClassificationPanel
                classification={selectedExpense.classification}
                threshold={threshold}
                canManage={!!caps.submit}
                loading={classifying}
                recordType="expense"
                onApply={async () => {
                  if (!selectedExpense?.id) return;
                  setClassifying(true);
                  const res = await api.classificationApply('expense', selectedExpense.id);
                  if (res.error) setError(res.error);
                  else {
                    setSuccess('AI suggestions applied.');
                    await loadExpense(selectedExpense.id);
                  }
                  setClassifying(false);
                }}
                onOverride={() => setShowClassOverride(true)}
                onRerun={async () => {
                  if (!selectedExpense?.id) return;
                  setClassifying(true);
                  const res = await api.classificationRun('expense', selectedExpense.id, false);
                  if (res.error) setError(res.error);
                  else {
                    setSuccess('Classification refreshed.');
                    await loadExpense(selectedExpense.id);
                  }
                  setClassifying(false);
                }}
              />
            )}

            <OverrideClassificationModal
              open={showClassOverride}
              accountCode={selectedExpense.classification?.suggested_account_code || editFields.account_code || ''}
              taxRate={
                selectedExpense.classification?.tax_hints?.tax_rate != null
                  ? String(selectedExpense.classification.tax_hints.tax_rate)
                  : ''
              }
              saving={classifying}
              onClose={() => setShowClassOverride(false)}
              onSubmit={async (accountCode, taxRate) => {
                if (!selectedExpense?.id) return;
                setClassifying(true);
                const res = await api.classificationOverride('expense', selectedExpense.id, accountCode, taxRate);
                if (res.error) setError(res.error);
                else {
                  setSuccess('Classification overridden.');
                  setShowClassOverride(false);
                  await loadExpense(selectedExpense.id);
                }
                setClassifying(false);
              }}
            />

            {orgId && selectedExpense?.id && (
              <div className="mt-4">
                <ResourceAttachmentsPanel
                  orgId={orgId}
                  resourceType="expense"
                  resourceId={selectedExpense.id}
                  title="Receipt & files"
                />
              </div>
            )}

            <div className="mt-4 flex flex-wrap gap-2">
              <WpLink to={`/attachments?resource_type=expense&resource_id=${selectedExpense.id}`}>
                <Button variant="secondary" size="sm">
                  <Paperclip className="h-3.5 w-3.5" />
                  View Files
                </Button>
              </WpLink>
              {selectedExpense.attachment_id && (
                <WpLink to={`/attachments?attachment_id=${selectedExpense.attachment_id}`}>
                  <Button variant="secondary" size="sm">
                    <Receipt className="h-3.5 w-3.5" />
                    Receipt
                  </Button>
                </WpLink>
              )}
              {caps.submit && selectedExpense.workflow_status === 'draft' && selectedExpense.ocr_confidence != null && (
                <Button onClick={() => void handleConfirm()} disabled={confirming}>
                  <CheckCircle2 className="h-4 w-4" />
                  {confirming ? 'Submitting...' : 'Confirm & Submit'}
                </Button>
              )}
              {selectedExpense.workflow_status === 'draft' && (caps.submit || caps.approve) && (
                <Button size="sm" variant="secondary" onClick={() => void openOverride(selectedExpense)}>
                  <Percent className="h-3.5 w-3.5" />
                  Override tax
                </Button>
              )}
              {caps.approve &&
                ['submitted', 'ai_review'].includes(selectedExpense.workflow_status) && (
                  <>
                    <Button disabled={actionId === selectedExpense.id} onClick={() => void handleApprove(selectedExpense.id)}>
                      Approve
                    </Button>
                    <Button
                      variant="secondary"
                      disabled={actionId === selectedExpense.id}
                      onClick={() => void handleReject(selectedExpense.id)}
                    >
                      <XCircle className="h-4 w-4" />
                      Reject
                    </Button>
                  </>
                )}
            </div>
          </div>
        )}

        <div className="flex justify-end">
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {error && (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>
        )}
        {success && (
          <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
            {success}
          </div>
        )}

        {caps.approve && pending.length > 0 && (
          <ExpenseTable
            title="Pending Approval"
            expenses={pending}
            loading={loading}
            onSelect={(id) => void loadExpense(id)}
            onApprove={caps.approve ? (id) => void handleApprove(id) : undefined}
            onReject={caps.approve ? (id) => void handleReject(id) : undefined}
            actionId={actionId}
          />
        )}

        <ExpenseTable
          title="All Expenses"
          expenses={expenses}
          loading={loading}
          onSelect={(id) => void loadExpense(id)}
          onOverride={(expense) => void openOverride(expense)}
          canOverride={!!caps.override_tax}
          actionId={actionId}
        />

        <TaxOverrideModal
          open={Boolean(overrideExpense)}
          title="Override tax"
          subtitle={overrideExpense?.vendor || (overrideExpense ? `Expense #${overrideExpense.id}` : '')}
          taxRate={overrideRate}
          reasonCode={overrideReason}
          jurisdiction={overrideJurisdiction}
          taxConfigs={taxConfigs}
          taxLocked={taxLocked}
          saving={saving}
          error={overrideExpense ? error : undefined}
          hasExistingOverride={Boolean(overrideExpense?.tax_override_reason)}
          currency={overrideExpense?.currency}
          preview={overridePreview ? { newTax: overridePreview.newTax, newTotal: overridePreview.newTotal } : null}
          onClose={() => setOverrideExpense(null)}
          onTaxRateChange={setOverrideRate}
          onReasonChange={setOverrideReason}
          onJurisdictionChange={setOverrideJurisdiction}
          onApply={() => void handleApplyOverride()}
          onClear={() => void handleClearOverride()}
        />
      </div>
    </ClientShell>
  );
}

function ExpenseTable({
  title,
  expenses,
  loading,
  onSelect,
  onApprove,
  onReject,
  onOverride,
  canOverride,
  actionId,
}: {
  title: string;
  expenses: any[];
  loading: boolean;
  onSelect: (id: number) => void;
  onApprove?: (id: number) => void;
  onReject?: (id: number) => void;
  onOverride?: (expense: any) => void;
  canOverride?: boolean;
  actionId: number | null;
}) {
  return (
    <div className="glass-panel overflow-hidden">
      <div className="border-b border-border px-5 py-4">
        <h2 className="font-bold text-ink">{title}</h2>
      </div>
      <table className="min-w-full text-left text-sm">
        <thead>
          <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
            <th className="px-5 py-3 font-semibold">Vendor</th>
            <th className="px-5 py-3 font-semibold">Date</th>
            <th className="px-5 py-3 text-right font-semibold">Amount</th>
            <th className="px-5 py-3 font-semibold">Workflow</th>
            <th className="px-5 py-3 font-semibold">Risk</th>
            <th className="px-5 py-3 font-semibold">Confidence</th>
            <th className="px-5 py-3 font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-border">
          {loading ? (
            <tr>
              <td colSpan={7} className="px-5 py-8 text-center text-slate-500">
                Loading...
              </td>
            </tr>
          ) : expenses.length === 0 ? (
            <tr>
              <td colSpan={7} className="px-5 py-8 text-center text-sm text-slate-500">
                No expenses yet.
              </td>
            </tr>
          ) : (
            expenses.map((expense) => (
              <tr key={expense.id} className="hover:bg-slate-50/70">
                <td className="px-5 py-3">
                  <div className="font-semibold text-ink">{expense.vendor || '—'}</div>
                  {expense.tax_override_reason && (
                    <span
                      className="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                      title={`Override: ${expense.tax_override_reason}`}
                    >
                      Overridden
                    </span>
                  )}
                </td>
                <td className="px-5 py-3 text-slate-600">{expense.transaction_date || '—'}</td>
                <td className="px-5 py-3 text-right font-bold text-ink">{money(expense.total_amount)}</td>
                <td className="px-5 py-3">
                  <StatusBadge status={expense.workflow_status} />
                </td>
                <td className="px-5 py-3">
                  {expense.ocr_risk_level ? <RiskBadge level={expense.ocr_risk_level} /> : '—'}
                </td>
                <td className="px-5 py-3">
                  {expense.ocr_confidence != null ? `${Number(expense.ocr_confidence).toFixed(1)}%` : '—'}
                </td>
                <td className="px-5 py-3">
                  <div className="flex flex-wrap gap-2">
                    <Button size="sm" variant="secondary" onClick={() => onSelect(expense.id)}>
                      View
                    </Button>
                    {canOverride && expense.workflow_status === 'draft' && onOverride && (
                      <Button
                        size="sm"
                        variant="secondary"
                        title="Manually change tax rate."
                        onClick={() => onOverride(expense)}
                      >
                        Override tax
                      </Button>
                    )}
                    {onApprove && ['submitted', 'ai_review'].includes(expense.workflow_status) && (
                      <>
                        <Button size="sm" disabled={actionId === expense.id} onClick={() => onApprove(expense.id)}>
                          Approve
                        </Button>
                        <Button
                          size="sm"
                          variant="secondary"
                          disabled={actionId === expense.id}
                          onClick={() => onReject?.(expense.id)}
                        >
                          Reject
                        </Button>
                      </>
                    )}
                  </div>
                </td>
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
}

function Metric({
  label,
  value,
  tone = 'default',
}: {
  label: string;
  value: string | number;
  tone?: 'default' | 'warning' | 'success';
}) {
  const toneClass =
    tone === 'warning'
      ? 'border-amber-200 bg-amber-50'
      : tone === 'success'
        ? 'border-emerald-200 bg-emerald-50'
        : 'border-border bg-white';

  return (
    <div className={`rounded-2xl border p-4 shadow-sm ${toneClass}`}>
      <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</p>
      <p className="mt-2 text-2xl font-black text-ink">{value}</p>
    </div>
  );
}

function RiskBadge({ level }: { level: string }) {
  const styles: Record<string, string> = {
    low: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    medium: 'border-amber-200 bg-amber-50 text-amber-800',
    high: 'border-red-200 bg-red-50 text-red-800',
  };
  return (
    <span className={`badge border capitalize ${styles[level] || 'border-slate-200 bg-slate-50 text-slate-700'}`}>
      Risk: {level}
    </span>
  );
}

function StatusBadge({ status }: { status: string }) {
  const styles: Record<string, string> = {
    draft: 'border-slate-200 bg-slate-50 text-slate-700',
    submitted: 'border-blue-200 bg-blue-50 text-blue-800',
    ai_review: 'border-amber-200 bg-amber-50 text-amber-800',
    approved: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    posted: 'border-primary/20 bg-primary/10 text-primary',
    locked: 'border-slate-300 bg-slate-100 text-slate-800',
  };
  return <span className={`badge border capitalize ${styles[status] || styles.draft}`}>{status.replace('_', ' ')}</span>;
}

function money(value?: string | number | null) {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(Number(value || 0));
}

function Modal({ title, children, onClose }: { title: string; children: ReactNode; onClose: () => void }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" onClick={onClose}>
      <div
        className="w-full max-w-lg rounded-2xl border border-border bg-white p-6 shadow-xl"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center justify-between gap-3">
          <h3 className="text-lg font-semibold text-ink">{title}</h3>
          <button type="button" onClick={onClose} className="text-sm text-slate-500 hover:text-slate-700">
            Close
          </button>
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
