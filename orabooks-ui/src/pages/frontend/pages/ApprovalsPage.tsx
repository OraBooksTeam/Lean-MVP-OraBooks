import { useEffect, useState, type ReactNode } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { CheckCircle2, Eye, Paperclip, RefreshCw, Send, ShieldCheck, XCircle } from 'lucide-react';

export default function ApprovalsPage() {
  const [searchParams] = useSearchParams();
  const highlightJournalId = Number(searchParams.get('journal_id') || 0) || null;
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [actionId, setActionId] = useState<number | null>(null);
  const [selectedJournalId, setSelectedJournalId] = useState<number | null>(null);
  const [journalDetail, setJournalDetail] = useState<any>(null);
  const [detailLoading, setDetailLoading] = useState(false);

  const caps = data?.capabilities || {};
  const orgId = data?.context?.organization?.id;

  const load = async () => {
    setLoading(true);
    setError('');
    setSuccess('');
    const res = await api.approvalDashboard();
    if (res.error) setError(res.error || 'Unable to load approvals.');
    else setData((res as any).data);
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  useEffect(() => {
    if (!highlightJournalId || loading) return;
    const row = document.getElementById(`journal-row-${highlightJournalId}`);
    if (row) {
      row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    if (orgId) {
      void loadJournalDetail(highlightJournalId);
    }
  }, [highlightJournalId, loading, data, orgId]);

  const loadJournalDetail = async (journalId: number) => {
    if (!orgId) return;
    setSelectedJournalId(journalId);
    setDetailLoading(true);
    setError('');
    const res = await api.journalGet(orgId, journalId);
    if (res.error) setError(res.error);
    else setJournalDetail((res as any).data);
    setDetailLoading(false);
  };

  const refreshAfterAction = async (journalId: number) => {
    await load();
    if (selectedJournalId === journalId || highlightJournalId === journalId) {
      await loadJournalDetail(journalId);
    }
  };

  const handleSubmit = async (journalId: number) => {
    setActionId(journalId);
    setError('');
    const res = await api.submitJournal(journalId);
    if (res.error) setError(res.error);
    else {
      const aiReview = (res as any).data?.ai_review;
      setSuccess(aiReview ? 'Journal queued for AI review before approval.' : 'Journal submitted for approval.');
      await refreshAfterAction(journalId);
    }
    setActionId(null);
  };

  const handleApprove = async (journalId: number) => {
    setActionId(journalId);
    setError('');
    const res = await api.approveJournal(journalId);
    if (res.error) setError(res.error);
    else {
      setSuccess('Journal approved.');
      await refreshAfterAction(journalId);
    }
    setActionId(null);
  };

  const handleReject = async (journalId: number) => {
    const reason = window.prompt('Enter rejection reason:');
    if (!reason?.trim()) return;
    setActionId(journalId);
    setError('');
    const res = await api.rejectJournal(journalId, reason.trim());
    if (res.error) setError(res.error);
    else {
      setSuccess('Journal rejected and returned to draft.');
      await refreshAfterAction(journalId);
    }
    setActionId(null);
  };

  const handlePost = async (journalId: number) => {
    setActionId(journalId);
    setError('');
    const res = await api.postJournal(journalId);
    if (res.error) setError(res.error);
    else {
      setSuccess(`Journal posted${(res as any).data?.journal_number ? `: ${(res as any).data.journal_number}` : ''}.`);
      await refreshAfterAction(journalId);
    }
    setActionId(null);
  };

  return (
    <ClientShell title="Approvals" eyebrow="Journal approval gate" organization={data?.context?.organization}>
      <div className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Metric label="Pending Review" value={data?.stats?.pending_review ?? 0} tone="warning" />
          <Metric label="Approved (Ready to Post)" value={data?.stats?.approved_ready ?? 0} />
          <Metric label="Draft Journals" value={data?.stats?.draft_count ?? 0} />
          <Metric label="Posted (MTD)" value={data?.stats?.posted_mtd ?? 0} tone="success" />
        </div>

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

        {highlightJournalId && (
          <div className="rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm text-ink">
            Reviewing journal #{highlightJournalId} from AI Review queue.
          </div>
        )}

        {selectedJournalId && (
          <JournalDetailPanel
            journalId={selectedJournalId}
            detail={journalDetail}
            loading={detailLoading}
            caps={caps}
            actionId={actionId}
            onClose={() => {
              setSelectedJournalId(null);
              setJournalDetail(null);
            }}
            onApprove={(id) => void handleApprove(id)}
            onReject={(id) => void handleReject(id)}
            onPost={(id) => void handlePost(id)}
            onSubmit={(id) => void handleSubmit(id)}
          />
        )}

        <JournalSection
          title="Pending Approval"
          icon={ShieldCheck}
          journals={data?.pending_review || []}
          loading={loading}
          emptyText="No journals awaiting approval."
          highlightJournalId={highlightJournalId}
          selectedJournalId={selectedJournalId}
          onSelect={(id) => void loadJournalDetail(id)}
          actions={(journal) => (
            <>
              {caps.approve && (
                <>
                  <Button size="sm" disabled={actionId === journal.id} onClick={() => void handleApprove(journal.id)}>
                    <CheckCircle2 className="h-3.5 w-3.5" />
                    Approve
                  </Button>
                  <Button
                    variant="secondary"
                    size="sm"
                    disabled={actionId === journal.id}
                    onClick={() => void handleReject(journal.id)}
                  >
                    <XCircle className="h-3.5 w-3.5" />
                    Reject
                  </Button>
                </>
              )}
            </>
          )}
        />

        <JournalSection
          title="Approved — Ready to Post"
          icon={Send}
          journals={data?.approved_ready || []}
          loading={loading}
          emptyText="No approved journals waiting to post."
          selectedJournalId={selectedJournalId}
          onSelect={(id) => void loadJournalDetail(id)}
          actions={(journal) =>
            caps.post ? (
              <Button size="sm" disabled={actionId === journal.id} onClick={() => void handlePost(journal.id)}>
                <Send className="h-3.5 w-3.5" />
                Post
              </Button>
            ) : null
          }
        />

        <JournalSection
          title="Draft Journals"
          icon={Send}
          journals={data?.draft_journals || []}
          loading={loading}
          emptyText="No draft journals."
          selectedJournalId={selectedJournalId}
          onSelect={(id) => void loadJournalDetail(id)}
          actions={(journal) =>
            caps.submit ? (
              <Button size="sm" disabled={actionId === journal.id} onClick={() => void handleSubmit(journal.id)}>
                Submit
              </Button>
            ) : null
          }
        />

        <div className="glass-panel overflow-hidden">
          <div className="border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Recent Approval History</h2>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Journal</th>
                <th className="px-5 py-3 font-semibold">Action</th>
                <th className="px-5 py-3 font-semibold">Round</th>
                <th className="px-5 py-3 font-semibold">Reason</th>
                <th className="px-5 py-3 font-semibold">When</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr>
                  <td colSpan={5} className="px-5 py-8 text-center text-slate-500">
                    Loading history...
                  </td>
                </tr>
              ) : (data?.recent_history || []).length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-5 py-8 text-center text-sm text-slate-500">
                    No approval history yet.
                  </td>
                </tr>
              ) : (
                (data?.recent_history || []).map((row: any) => (
                  <tr key={row.id} className="hover:bg-slate-50/70">
                    <td className="px-5 py-3 font-mono text-xs">#{row.journal_id}</td>
                    <td className="px-5 py-3">
                      <ActionBadge action={row.action} />
                    </td>
                    <td className="px-5 py-3 font-mono text-xs">{row.approval_round}</td>
                    <td className="px-5 py-3 text-slate-600">{row.reason || '—'}</td>
                    <td className="px-5 py-3 text-slate-600">{formatDate(row.created_at)}</td>
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

function JournalSection({
  title,
  icon: Icon,
  journals,
  loading,
  emptyText,
  actions,
  highlightJournalId = null,
  selectedJournalId = null,
  onSelect,
}: {
  title: string;
  icon: typeof ShieldCheck;
  journals: any[];
  loading: boolean;
  emptyText: string;
  actions: (journal: any) => ReactNode;
  highlightJournalId?: number | null;
  selectedJournalId?: number | null;
  onSelect?: (journalId: number) => void;
}) {
  return (
    <div className="glass-panel overflow-hidden">
      <div className="flex items-center gap-2 border-b border-border px-5 py-4">
        <Icon className="h-5 w-5 text-primary" />
        <h2 className="font-bold text-ink">{title}</h2>
      </div>
      <table className="min-w-full text-left text-sm">
        <thead>
          <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
            <th className="px-5 py-3 font-semibold">Journal</th>
            <th className="px-5 py-3 font-semibold">Date</th>
            <th className="px-5 py-3 font-semibold">Source</th>
            <th className="px-5 py-3 text-right font-semibold">Amount</th>
            <th className="px-5 py-3 font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-border">
          {loading ? (
            <tr>
              <td colSpan={5} className="px-5 py-8 text-center text-slate-500">
                Loading...
              </td>
            </tr>
          ) : journals.length === 0 ? (
            <tr>
              <td colSpan={5} className="px-5 py-8 text-center text-sm text-slate-500">
                {emptyText}
              </td>
            </tr>
          ) : (
            journals.map((journal) => (
              <tr
                key={journal.id}
                id={`journal-row-${journal.id}`}
                className={`hover:bg-slate-50/70 ${
                  highlightJournalId === journal.id || selectedJournalId === journal.id
                    ? 'bg-primary/10 ring-2 ring-inset ring-primary/30'
                    : ''
                }`}
              >
                <td className="px-5 py-3 font-semibold text-ink">
                  {journal.journal_number || `Journal #${journal.id}`}
                </td>
                <td className="px-5 py-3 text-slate-600">{journal.transaction_date || '—'}</td>
                <td className="px-5 py-3 text-slate-600">{journal.source_type || 'manual'}</td>
                <td className="px-5 py-3 text-right font-bold text-ink">{money(journal.total_amount)}</td>
                <td className="px-5 py-3">
                  <div className="flex flex-wrap gap-2">
                    {onSelect && (
                      <Button variant="secondary" size="sm" onClick={() => onSelect(journal.id)}>
                        <Eye className="h-3.5 w-3.5" />
                        Review
                      </Button>
                    )}
                    {actions(journal)}
                    <Link to={`/attachments?resource_type=journal&resource_id=${journal.id}`}>
                      <Button variant="secondary" size="sm">
                        <Paperclip className="h-3.5 w-3.5" />
                        Files
                      </Button>
                    </Link>
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

function JournalDetailPanel({
  journalId,
  detail,
  loading,
  caps,
  actionId,
  onClose,
  onApprove,
  onReject,
  onPost,
  onSubmit,
}: {
  journalId: number;
  detail: any;
  loading: boolean;
  caps: Record<string, boolean>;
  actionId: number | null;
  onClose: () => void;
  onApprove: (id: number) => void;
  onReject: (id: number) => void;
  onPost: (id: number) => void;
  onSubmit: (id: number) => void;
}) {
  const journal = detail?.journal;
  const lines = detail?.lines || [];
  const history = detail?.approval_history || [];
  const status = journal?.status || '';

  return (
    <div className="glass-panel overflow-hidden">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
        <div>
          <h2 className="font-bold text-ink">
            Journal Detail — {journal?.journal_number || `#${journalId}`}
          </h2>
          <p className="mt-1 text-sm text-slate-600">
            {journal?.transaction_date || '—'} · {journal?.source_type || 'manual'}
            {journal?.source_id ? ` #${journal.source_id}` : ''}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {journal?.status && <JournalStatusBadge status={journal.status} />}
          <Button variant="secondary" size="sm" onClick={onClose}>
            Close
          </Button>
        </div>
      </div>

      {loading ? (
        <p className="px-5 py-8 text-center text-sm text-slate-500">Loading journal detail...</p>
      ) : !journal ? (
        <p className="px-5 py-8 text-center text-sm text-slate-500">Journal not found.</p>
      ) : (
        <div className="space-y-4 p-5">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
            <p>
              <span className="text-slate-500">Amount:</span>{' '}
              <strong className="text-ink">{money(journal.total_amount)}</strong>
            </p>
            <p>
              <span className="text-slate-500">Round:</span> {journal.approval_round ?? 0}
            </p>
            <p>
              <span className="text-slate-500">Created:</span> {formatDate(journal.created_at)}
            </p>
            {journal.ai_confidence != null && (
              <p>
                <span className="text-slate-500">AI confidence:</span>{' '}
                <strong>{Number(journal.ai_confidence).toFixed(1)}%</strong>
              </p>
            )}
          </div>

          {journal.rejected_reason && (
            <p className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
              Rejected: {journal.rejected_reason}
            </p>
          )}

          <div className="overflow-hidden rounded-xl border border-border">
            <table className="min-w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                  <th className="px-4 py-2 font-semibold">Account</th>
                  <th className="px-4 py-2 text-right font-semibold">Debit</th>
                  <th className="px-4 py-2 text-right font-semibold">Credit</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {lines.length === 0 ? (
                  <tr>
                    <td colSpan={3} className="px-4 py-6 text-center text-slate-500">
                      No journal lines.
                    </td>
                  </tr>
                ) : (
                  lines.map((line: any) => (
                    <tr key={line.id}>
                      <td className="px-4 py-2">
                        <div className="font-mono font-semibold text-ink">{line.account_code}</div>
                        {line.description && (
                          <div className="text-xs text-slate-500">{line.description}</div>
                        )}
                      </td>
                      <td className="px-4 py-2 text-right text-slate-700">
                        {line.debit_amount ? money(line.debit_amount) : '—'}
                      </td>
                      <td className="px-4 py-2 text-right text-slate-700">
                        {line.credit_amount ? money(line.credit_amount) : '—'}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          {history.length > 0 && (
            <div>
              <h3 className="mb-2 text-sm font-bold text-ink">Approval History</h3>
              <div className="overflow-hidden rounded-xl border border-border">
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
                    {history.map((row: any) => (
                      <tr key={row.id}>
                        <td className="px-4 py-2">
                          <ActionBadge action={row.action} />
                        </td>
                        <td className="px-4 py-2 font-mono text-xs">{row.approval_round}</td>
                        <td className="px-4 py-2 text-slate-600">{row.reason || '—'}</td>
                        <td className="px-4 py-2 text-slate-600">{formatDate(row.created_at)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          <div className="flex flex-wrap gap-2 border-t border-border pt-4">
            {caps.approve && status === 'review_pending' && (
              <>
                <Button size="sm" disabled={actionId === journalId} onClick={() => onApprove(journalId)}>
                  <CheckCircle2 className="h-3.5 w-3.5" />
                  Approve
                </Button>
                <Button
                  variant="secondary"
                  size="sm"
                  disabled={actionId === journalId}
                  onClick={() => onReject(journalId)}
                >
                  <XCircle className="h-3.5 w-3.5" />
                  Reject
                </Button>
              </>
            )}
            {caps.post && status === 'approved' && (
              <Button size="sm" disabled={actionId === journalId} onClick={() => onPost(journalId)}>
                <Send className="h-3.5 w-3.5" />
                Post
              </Button>
            )}
            {caps.submit && status === 'draft' && (
              <Button size="sm" disabled={actionId === journalId} onClick={() => onSubmit(journalId)}>
                Submit for Approval
              </Button>
            )}
            <Link to={`/attachments?resource_type=journal&resource_id=${journalId}`}>
              <Button variant="secondary" size="sm">
                <Paperclip className="h-3.5 w-3.5" />
                Attachments
              </Button>
            </Link>
          </div>
        </div>
      )}
    </div>
  );
}

function JournalStatusBadge({ status }: { status: string }) {
  const styles: Record<string, string> = {
    draft: 'border-slate-200 bg-slate-50 text-slate-700',
    review_pending: 'border-amber-200 bg-amber-50 text-amber-800',
    approved: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    posted: 'border-primary/20 bg-primary/10 text-primary',
    reversed: 'border-red-200 bg-red-50 text-red-800',
  };
  return (
    <span className={`badge border capitalize ${styles[status] || styles.draft}`}>
      {status.replace(/_/g, ' ')}
    </span>
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

function ActionBadge({ action }: { action: string }) {
  const styles: Record<string, string> = {
    submit: 'border-blue-200 bg-blue-50 text-blue-800',
    approve: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    reject: 'border-red-200 bg-red-50 text-red-800',
  };

  return <span className={`badge border capitalize ${styles[action] || 'border-slate-200 bg-slate-50 text-slate-700'}`}>{action}</span>;
}

function money(value?: string | number) {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(Number(value || 0));
}

function formatDate(value: string) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString();
}
