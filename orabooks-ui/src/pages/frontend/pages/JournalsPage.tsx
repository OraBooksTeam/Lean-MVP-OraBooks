import { useEffect, useMemo, useState } from 'react';
import WpLink from '../components/WpLink';

import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { Bot, Landmark, Paperclip, Plus, RefreshCw, Send } from 'lucide-react';
import WorkflowModal from '../components/WorkflowModal';

type Journal = {
  id: number;
  journal_number?: string | null;
  transaction_date?: string;
  status: string;
  source_type?: string;
  total_amount?: number;
  created_by?: number;
  ai_confidence?: number | null;
  reversal_of_id?: number | null;
  reversal_reason?: string | null;
  rejected_reason?: string | null;
};

type JournalLine = {
  id: number;
  account_code: string;
  debit_amount: number;
  credit_amount: number;
  description?: string;
};

export default function JournalsPage() {
  const [context, setContext] = useState<any>(null);
  const [journals, setJournals] = useState<Journal[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [detail, setDetail] = useState<any>(null);
  const [status, setStatus] = useState('');
  const [loading, setLoading] = useState(true);
  const [detailLoading, setDetailLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');
  const [accountFilter, setAccountFilter] = useState('');
  const [rejectReason, setRejectReason] = useState('');
  const [reverseReason, setReverseReason] = useState('');
  const [actionLoading, setActionLoading] = useState(false);
  const [showCreate, setShowCreate] = useState(false);
  const [createDate, setCreateDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [debitAccount, setDebitAccount] = useState('');
  const [creditAccount, setCreditAccount] = useState('');
  const [entryAmount, setEntryAmount] = useState('');
  const [entryDescription, setEntryDescription] = useState('');
  const [detailTab, setDetailTab] = useState<'lines' | 'history'>('lines');
  const [mfaThreshold, setMfaThreshold] = useState(10000);
  const [rejectModalOpen, setRejectModalOpen] = useState(false);
  const [mfaModal, setMfaModal] = useState({ open: false, code: '' });

  const orgId = context?.organization?.id;
  const currentUserId = context?.user?.id;
  const role = context?.role;
  const canApprove = ['owner', 'admin', 'approver'].includes(role);
  const canReverse = ['owner', 'admin'].includes(role);

  const load = async (nextStatus = status) => {
    setLoading(true);
    setError('');
    setSuccess('');
    const ctx = await api.frontendContext();
    if (ctx.error) {
      setError(ctx.error || 'Unable to load account context.');
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

    const res = await api.journalsList(nextOrgId, {
      status: nextStatus,
      from_date: fromDate,
      to_date: toDate,
      account_code: accountFilter.trim(),
    });
    if (res.error) setError(res.error || 'Unable to load journals.');
    else setJournals((res as any).data?.journals || []);

    const dash = await api.approvalDashboard();
    if (!(dash as any).error) {
      setMfaThreshold(Number((dash as any).data?.policy?.mfa_amount_threshold ?? 10000));
    }

    setLoading(false);
  };

  const loadDetail = async (journalId: number, nextOrgId = orgId) => {
    if (!nextOrgId || !journalId) return;
    setDetailLoading(true);
    const res = await api.journalGet(nextOrgId, journalId);
    if (res.error) setError(res.error);
    else setDetail((res as any).data);
    setDetailLoading(false);
  };

  useEffect(() => { void load(''); }, []);

  useEffect(() => {
    if (selectedId && orgId) void loadDetail(selectedId, orgId);
  }, [selectedId, orgId]);

  const selectedJournal: Journal | undefined = useMemo(
    () => detail?.journal || journals.find((j) => j.id === selectedId),
    [detail, journals, selectedId]
  );

  const lines: JournalLine[] = detail?.lines || [];
  const approvalHistory = detail?.approval_history || [];
  const wasRejected = approvalHistory.some((row: any) => row.action === 'reject');

  const approveJournal = async (mfaOtp?: string) => {
    if (!selectedJournal) return;
    const amount = Number(selectedJournal.total_amount || 0);
    if (amount >= mfaThreshold && !mfaOtp) {
      setMfaModal({ open: true, code: '' });
      return;
    }
    await runAction(() => api.approveJournal(selectedJournal.id, mfaOtp), 'Journal approved.');
    setMfaModal({ open: false, code: '' });
  };

  const runAction = async (action: () => Promise<any>, successMessage: string) => {
    setActionLoading(true);
    setError('');
    setSuccess('');
    const res = await action();
    if (res.error) {
      setError(res.error);
    } else {
      setSuccess(successMessage);
      await load();
      if (selectedId) await loadDetail(selectedId);
    }
    setActionLoading(false);
  };

  const createJournal = async () => {
    if (!orgId) return;
    const amount = Number(entryAmount);
    if (!debitAccount.trim() || !creditAccount.trim() || !amount || amount <= 0) {
      setError('Enter debit account, credit account, and a positive amount.');
      return;
    }

    setActionLoading(true);
    setError('');
    setSuccess('');
    const res = await api.journalCreate(orgId, {
      transaction_date: createDate,
      source_type: 'manual',
      description: entryDescription,
      lines: [
        { account_code: debitAccount.trim(), debit_amount: amount, description: entryDescription },
        { account_code: creditAccount.trim(), credit_amount: amount, description: entryDescription },
      ],
    });
    if (res.error) {
      setError(res.error);
    } else {
      setSuccess('Draft journal created.');
      setShowCreate(false);
      setDebitAccount('');
      setCreditAccount('');
      setEntryAmount('');
      setEntryDescription('');
      const journalId = (res as any).data?.journal_id;
      await load();
      if (journalId) setSelectedId(Number(journalId));
    }
    setActionLoading(false);
  };

  return (
    <ClientShell title="Journals" eyebrow="Posting workflow" organization={context?.organization}>
      <div className="space-y-5">
        <div className="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50/80 p-4 text-sm text-sky-900">
          <Landmark className="mt-0.5 h-4 w-4 shrink-0" />
          <p>
            Approving locks a journal. Posting is atomic and updates the ledger. Reversals require a reason and create a new draft journal with opposite entries.
          </p>
        </div>

        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="grid flex-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
            <select
              value={status}
              onChange={(event) => {
                setStatus(event.target.value);
                void load(event.target.value);
              }}
              className="rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm"
            >
              <option value="">All statuses</option>
              <option value="draft">Draft</option>
              <option value="review_pending">Review Pending</option>
              <option value="approved">Approved</option>
              <option value="posted">Posted</option>
              <option value="locked">Locked</option>
              <option value="reversed">Reversed</option>
            </select>
            <Input type="date" value={fromDate} onChange={(e) => setFromDate(e.target.value)} aria-label="Filter from date" />
            <Input type="date" value={toDate} onChange={(e) => setToDate(e.target.value)} aria-label="Filter to date" />
            <Input value={accountFilter} onChange={(e) => setAccountFilter(e.target.value)} placeholder="Account code" />
          </div>
          <div className="flex gap-2">
            <Button onClick={() => setShowCreate((v) => !v)} size="sm">
              <Plus className="h-4 w-4" />
              New journal
            </Button>
            <Button onClick={() => load()} variant="secondary" size="sm">
              <RefreshCw className="h-4 w-4" />
              Apply Filters
            </Button>
          </div>
        </div>

        {showCreate && (
          <div className="glass-panel grid gap-4 p-5 md:grid-cols-2">
            <Input label="Transaction date" type="date" value={createDate} onChange={(e) => setCreateDate(e.target.value)} />
            <Input label="Amount" type="number" min="0" step="0.01" value={entryAmount} onChange={(e) => setEntryAmount(e.target.value)} placeholder="0.00" />
            <Input label="Debit account code" value={debitAccount} onChange={(e) => setDebitAccount(e.target.value)} placeholder="e.g. 1010" />
            <Input label="Credit account code" value={creditAccount} onChange={(e) => setCreditAccount(e.target.value)} placeholder="e.g. 4010" />
            <div className="md:col-span-2">
              <Input label="Description" value={entryDescription} onChange={(e) => setEntryDescription(e.target.value)} placeholder="Journal entry memo" />
            </div>
            <div className="md:col-span-2 flex gap-2">
              <Button onClick={createJournal} loading={actionLoading}>Create draft</Button>
              <Button variant="secondary" onClick={() => setShowCreate(false)}>Cancel</Button>
            </div>
          </div>
        )}

        {error && <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>}
        {success && <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">{success}</div>}

        <div className="grid gap-5 xl:grid-cols-[1.2fr_1fr]">
          <div className="glass-panel overflow-hidden">
            <table className="min-w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                  <th className="px-5 py-3 font-semibold">Journal</th>
                  <th className="px-5 py-3 font-semibold">Date</th>
                  <th className="px-5 py-3 font-semibold">Status</th>
                  <th className="px-5 py-3 font-semibold">Source</th>
                  <th className="px-5 py-3 text-right font-semibold">Amount</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {loading ? (
                  <tr><td colSpan={5} className="px-5 py-8 text-center text-slate-500">Loading journals...</td></tr>
                ) : journals.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-5 py-10 text-center">
                      <Landmark className="mx-auto h-8 w-8 text-slate-300" />
                      <p className="mt-2 text-sm text-slate-500">No journals found.</p>
                    </td>
                  </tr>
                ) : journals.map((journal) => (
                  <tr
                    key={journal.id}
                    className={`cursor-pointer transition hover:bg-accent/5 ${selectedId === journal.id ? 'bg-accent/10 ring-2 ring-inset ring-accent/30' : ''}`}
                    onClick={() => setSelectedId(journal.id)}
                  >
                    <td className="px-5 py-3 font-semibold text-ink">{journal.journal_number || `Journal #${journal.id}`}</td>
                    <td className="px-5 py-3 text-slate-600">{journal.transaction_date || 'Not set'}</td>
                    <td className="px-5 py-3"><StatusBadge status={journal.status} rejected={Boolean(journal.rejected_reason)} /></td>
                    <td className="px-5 py-3 text-slate-600">{journal.source_type || 'Manual'}</td>
                    <td className="px-5 py-3 text-right font-bold text-ink">{money(journal.total_amount)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="glass-panel p-5">
            {!selectedJournal ? (
              <p className="text-sm text-slate-500">Select a journal to view lines and workflow actions.</p>
            ) : detailLoading ? (
              <p className="text-sm text-slate-500">Loading journal detail...</p>
            ) : (
              <div className="space-y-4">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <h3 className="text-lg font-bold text-ink">
                      {selectedJournal.journal_number || `Journal #${selectedJournal.id}`}
                    </h3>
                    <p className="text-sm text-slate-500">{selectedJournal.transaction_date}</p>
                  </div>
                  <StatusBadge status={selectedJournal.status} rejected={wasRejected || Boolean(selectedJournal.rejected_reason)} />
                </div>

                <div className="flex gap-2 border-b border-border">
                  <button
                    type="button"
                    onClick={() => setDetailTab('lines')}
                    className={`px-3 py-2 text-sm font-semibold transition ${detailTab === 'lines' ? 'border-b-2 border-accent text-accent' : 'text-slate-500 hover:text-ink'}`}
                  >
                    Journal Lines
                  </button>
                  <button
                    type="button"
                    onClick={() => setDetailTab('history')}
                    className={`px-3 py-2 text-sm font-semibold transition ${detailTab === 'history' ? 'border-b-2 border-accent text-accent' : 'text-slate-500 hover:text-ink'}`}
                    title="Immutable audit trail."
                  >
                    Approval History
                  </button>
                </div>

                {selectedJournal.ai_confidence != null ? (
                  <div
                    className="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-xs font-medium text-violet-800"
                    title="AI suggestion only. Human approves."
                  >
                    <Bot className="h-3.5 w-3.5" />
                    Confidence: {Number(selectedJournal.ai_confidence).toFixed(1)}% {confidenceLabel(selectedJournal.ai_confidence)}
                  </div>
                ) : null}

                {selectedJournal.rejected_reason ? (
                  <p className="text-sm text-amber-700">Rejected: {selectedJournal.rejected_reason}</p>
                ) : null}

                <WpLink to={`/attachments?resource_type=journal&resource_id=${selectedJournal.id}`}>
                  <Button variant="secondary" size="sm">
                    <Paperclip className="h-3.5 w-3.5" />
                    View Attachments
                  </Button>
                </WpLink>

                <div className="overflow-hidden rounded-xl border border-border">
                  {detailTab === 'lines' ? (
                  <table className="min-w-full text-left text-sm">
                    <thead>
                      <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                        <th className="px-4 py-2 font-semibold">Account</th>
                        <th className="px-4 py-2 text-right font-semibold">Debit</th>
                        <th className="px-4 py-2 text-right font-semibold">Credit</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                      {lines.map((line) => (
                        <tr key={line.id}>
                          <td className="px-4 py-2">
                            <div className="font-mono font-semibold text-ink">{line.account_code}</div>
                            {line.description ? <div className="text-xs text-slate-500">{line.description}</div> : null}
                          </td>
                          <td className="px-4 py-2 text-right text-slate-700">{line.debit_amount ? money(line.debit_amount) : '—'}</td>
                          <td className="px-4 py-2 text-right text-slate-700">{line.credit_amount ? money(line.credit_amount) : '—'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                  ) : (
                  <table className="min-w-full text-left text-sm">
                    <thead>
                      <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                        <th className="px-4 py-2 font-semibold">Action</th>
                        <th className="px-4 py-2 font-semibold">Round</th>
                        <th className="px-4 py-2 font-semibold">Reason</th>
                        <th className="px-4 py-2 font-semibold">When</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                      {approvalHistory.length === 0 ? (
                        <tr><td colSpan={4} className="px-4 py-6 text-center text-slate-500">No approval history yet.</td></tr>
                      ) : approvalHistory.map((row: any) => (
                        <tr key={row.id}>
                          <td className="px-4 py-2 font-medium capitalize text-ink">{row.action}</td>
                          <td className="px-4 py-2 font-mono text-xs">{row.approval_round}</td>
                          <td className="px-4 py-2 text-slate-600">{row.reason || '—'}</td>
                          <td className="px-4 py-2 text-slate-600">{row.created_at || '—'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                  )}
                </div>

                <div className="flex flex-wrap gap-2">
                  {selectedJournal.status === 'draft' && Number(selectedJournal.approval_round || 0) === 0 && (
                    <Button
                      size="sm"
                      disabled={actionLoading}
                      title="Submit for approval."
                      onClick={() => runAction(() => api.submitJournal(selectedJournal.id), 'Journal submitted for approval.')}
                    >
                      Submit for Approval
                    </Button>
                  )}
                  {selectedJournal.status === 'draft' && Number(selectedJournal.approval_round || 0) > 0 && (
                    <Button
                      size="sm"
                      disabled={actionLoading}
                      title="Resubmit after corrections. New approval round starts."
                      onClick={() => runAction(() => api.resubmitJournal(selectedJournal.id), 'Journal resubmitted for approval.')}
                    >
                      <Send className="h-3.5 w-3.5" />
                      Resubmit for Approval
                    </Button>
                  )}
                  {canApprove && selectedJournal.status === 'review_pending' && selectedJournal.created_by !== currentUserId && (
                    <>
                      <Button
                        size="sm"
                        disabled={actionLoading}
                        title="Approve journal. Snapshot stored. Journal locked."
                        className="bg-accent text-white hover:bg-accent/90"
                        onClick={() => void approveJournal()}
                      >
                        Approve
                      </Button>
                      <Button
                        size="sm"
                        variant="secondary"
                        disabled={actionLoading}
                        title="Reject with reason. Journal returns to draft."
                        onClick={() => setRejectModalOpen(true)}
                      >
                        Reject
                      </Button>
                    </>
                  )}
                  {canApprove && selectedJournal.status === 'approved' && (
                    <Button
                      size="sm"
                      disabled={actionLoading}
                      title="Post atomically. Ledger updated."
                      onClick={() => runAction(() => api.postJournal(selectedJournal.id), 'Journal posted to ledger.')}
                    >
                      Post to Ledger
                    </Button>
                  )}
                  {canReverse && ['posted', 'locked'].includes(selectedJournal.status) && (
                    <div className="w-full space-y-2">
                      <Input
                        value={reverseReason}
                        onChange={(e) => setReverseReason(e.target.value)}
                        placeholder="Reversal reason (required)"
                      />
                      <Button
                        size="sm"
                        variant="secondary"
                        disabled={actionLoading || !reverseReason.trim() || !orgId}
                        title="Requires reason. Creates reversal."
                        onClick={() => runAction(
                          () => api.reverseJournal(orgId!, selectedJournal.id, reverseReason.trim()),
                          'Reversal journal created as draft.'
                        )}
                      >
                        Reverse Journal
                      </Button>
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>
        </div>
      </div>

      <WorkflowModal
        open={rejectModalOpen}
        title="Reject Journal"
        description="Reject with reason. Journal returns to draft."
        confirmLabel="Reject"
        confirmVariant="danger"
        confirmDisabled={!rejectReason.trim()}
        loading={actionLoading}
        onClose={() => {
          setRejectModalOpen(false);
          setRejectReason('');
        }}
        onConfirm={() => {
          if (!selectedJournal) return;
          void runAction(
            () => api.rejectJournal(selectedJournal.id, rejectReason.trim()),
            'Journal rejected and returned to draft.'
          ).then(() => {
            setRejectModalOpen(false);
            setRejectReason('');
          });
        }}
      >
        <Input
          label="Rejection reason"
          value={rejectReason}
          onChange={(e) => setRejectReason(e.target.value)}
          placeholder="Explain why this journal is rejected"
        />
      </WorkflowModal>

      <WorkflowModal
        open={mfaModal.open}
        title="High-Risk Approval"
        description="High value approval requires MFA. Enter your 6-digit 2FA code."
        confirmLabel="Verify & Approve"
        confirmDisabled={mfaModal.code.trim().length < 6}
        loading={actionLoading}
        onClose={() => setMfaModal({ open: false, code: '' })}
        onConfirm={() => void approveJournal(mfaModal.code.trim())}
      >
        <Input
          label="6-digit code"
          value={mfaModal.code}
          onChange={(e) => setMfaModal({ open: true, code: e.target.value.replace(/\D/g, '').slice(0, 6) })}
          placeholder="000000"
          inputMode="numeric"
          autoComplete="one-time-code"
        />
      </WorkflowModal>
    </ClientShell>
  );
}

function StatusBadge({ status, rejected = false }: { status: string; rejected?: boolean }) {
  const map: Record<string, string> = {
    draft: 'border-slate-200 bg-slate-50 text-slate-700',
    review_pending: 'border-amber-200 bg-amber-50 text-amber-700',
    approved: 'border-sky-200 bg-sky-50 text-sky-700',
    posted: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    locked: 'border-emerald-300 bg-emerald-100 text-emerald-800',
    reversed: 'border-rose-200 bg-rose-50 text-rose-700',
  };
  return (
    <span className={`badge border ${map[status] || map.draft}`} title="Current workflow state.">
      {status.replace(/_/g, ' ')}
      {status === 'draft' && rejected ? ' (Rejected)' : ''}
    </span>
  );
}

function money(value?: string | number) {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(Number(value || 0));
}

function confidenceLabel(value: number) {
  if (value >= 90) return 'High';
  if (value >= 70) return 'Medium';
  return 'Low';
}
