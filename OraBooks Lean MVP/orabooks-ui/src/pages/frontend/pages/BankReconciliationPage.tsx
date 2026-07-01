import { useEffect, useMemo, useState, type ReactNode } from 'react';
import WpLink from '../components/WpLink';

import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { Paperclip, Plug, RefreshCw, ShieldCheck, Wallet } from 'lucide-react';

export default function BankReconciliationPage() {
  const [context, setContext] = useState<any>(null);
  const [accounts, setAccounts] = useState<any[]>([]);
  const [transactions, setTransactions] = useState<any[]>([]);
  const [reconciliations, setReconciliations] = useState<any[]>([]);
  const [stats, setStats] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [saving, setSaving] = useState(false);

  const [selectedAccountId, setSelectedAccountId] = useState('');

  const [showUploadModal, setShowUploadModal] = useState(false);
  const [uploadForm, setUploadForm] = useState({ bank_account_id: '', file: null as File | null });

  const [showFeedModal, setShowFeedModal] = useState(false);
  const [feedForm, setFeedForm] = useState({
    bank_account_id: '',
    provider: 'plaid' as 'plaid' | 'yodlee',
    access_token: '',
    refresh_token: '',
    token_expires_at: '',
  });

  const [matchTxn, setMatchTxn] = useState<any | null>(null);
  const [matchForm, setMatchForm] = useState({
    transaction_type: 'payment' as 'payment' | 'expense' | 'invoice' | 'journal',
    transaction_id: '',
  });

  const [createTxn, setCreateTxn] = useState<{ txn: any; type: 'expense' | 'invoice' } | null>(null);
  const [createForm, setCreateForm] = useState({
    description: '',
    vendor: '',
    category: 'General',
    customer_name: '',
    customer_email: '',
  });

  const [skipTxn, setSkipTxn] = useState<any | null>(null);
  const [skipReason, setSkipReason] = useState('');

  const [showReconcileForm, setShowReconcileForm] = useState(false);
  const [reconcileForm, setReconcileForm] = useState({
    bank_account_id: '',
    statement_date: new Date().toISOString().slice(0, 10),
    ending_balance: '',
    force: false,
    note: '',
  });

  const orgId = context?.organization?.id;

  const load = async (accountId = selectedAccountId) => {
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

    const [dash, accountsRes, txnsRes] = await Promise.all([
      api.bankDashboard(),
      api.bankAccountsList(nextOrgId),
      api.bankTransactionsList(nextOrgId, accountId ? Number(accountId) : 0, { limit: 150 }),
    ]);

    if (dash.error) {
      setError(dash.error || 'Unable to load bank data.');
    } else {
      const data = (dash as any).data;
      setStats(data?.stats || null);
      setReconciliations(data?.recent_reconciliations || []);
    }

    if (!accountsRes.error) {
      const nextAccounts = (accountsRes as any).data?.accounts || [];
      setAccounts(nextAccounts);
      if (!selectedAccountId && nextAccounts.length > 0) {
        const first = String(nextAccounts[0].id);
        setSelectedAccountId(first);
        setUploadForm((p) => ({ ...p, bank_account_id: first }));
        setFeedForm((p) => ({ ...p, bank_account_id: first }));
        setReconcileForm((p) => ({ ...p, bank_account_id: first }));
      }
    }

    if (!txnsRes.error) {
      setTransactions((txnsRes as any).data?.transactions || []);
    }

    setLoading(false);
  };

  useEffect(() => {
    void load('');
  }, []);

  useEffect(() => {
    if (!orgId) {
      return;
    }
    void load(selectedAccountId);
  }, [selectedAccountId]);

  const selectedAccount = useMemo(
    () => accounts.find((a) => String(a.id) === selectedAccountId),
    [accounts, selectedAccountId]
  );

  const systemBalancePreview = useMemo(() => {
    return transactions
      .filter((t) => ['matched', 'reconciled'].includes(String(t.status)))
      .reduce((sum, t) => sum + Number(t.amount || 0), 0);
  }, [transactions]);

  const previewDifference = useMemo(() => {
    if (!reconcileForm.ending_balance) {
      return 0;
    }
    return Number(reconcileForm.ending_balance) - systemBalancePreview;
  }, [reconcileForm.ending_balance, systemBalancePreview]);

  const unmatched = stats?.unmatched_count ?? 0;

  const handleUploadCsv = async () => {
    if (!orgId || !uploadForm.bank_account_id || !uploadForm.file) {
      setError('Bank account and CSV file are required.');
      return;
    }

    setSaving(true);
    setError('');
    const res = await api.bankImportCsv(orgId, Number(uploadForm.bank_account_id), uploadForm.file);
    if (res.error) {
      setError(res.error);
    } else {
      const data = (res as any).data || {};
      setSuccess(
        `Import complete. Rows: ${data.total_rows || 0}, inserted: ${data.inserted || 0}, duplicates: ${data.duplicates || 0}, suggestions: ${data.suggested_matches || 0}.`
      );
      setShowUploadModal(false);
      setUploadForm((p) => ({ ...p, file: null }));
      await load(selectedAccountId);
    }
    setSaving(false);
  };

  const handleConnectFeed = async () => {
    if (!orgId || !feedForm.bank_account_id || !feedForm.access_token.trim()) {
      setError('Bank account and access token are required.');
      return;
    }

    setSaving(true);
    setError('');
    const res = await api.bankFeedConnect(
      orgId,
      Number(feedForm.bank_account_id),
      feedForm.provider,
      feedForm.access_token.trim(),
      feedForm.refresh_token.trim(),
      feedForm.token_expires_at.trim()
    );
    if (res.error) {
      setError(res.error);
    } else {
      setSuccess('Bank feed connected. Automatic sync metadata saved.');
      setShowFeedModal(false);
      setFeedForm((p) => ({ ...p, access_token: '', refresh_token: '', token_expires_at: '' }));
    }
    setSaving(false);
  };

  const handleMatchSuggested = async (txn: any) => {
    if (!orgId || !txn?.id || !txn?.suggested_transaction_id || !txn?.suggested_transaction_type) {
      setError('No suggested match available for this transaction.');
      return;
    }

    setSaving(true);
    setError('');
    const res = await api.bankManualMatch(
      orgId,
      Number(txn.id),
      String(txn.suggested_transaction_type) as any,
      Number(txn.suggested_transaction_id)
    );
    if (res.error) {
      setError(res.error);
    } else {
      setSuccess('Suggested match confirmed.');
      await load(selectedAccountId);
    }
    setSaving(false);
  };

  const handleManualMatch = async () => {
    if (!orgId || !matchTxn || !matchForm.transaction_id) {
      setError('Transaction type and transaction ID are required.');
      return;
    }

    setSaving(true);
    setError('');
    const res = await api.bankManualMatch(
      orgId,
      Number(matchTxn.id),
      matchForm.transaction_type,
      Number(matchForm.transaction_id)
    );
    if (res.error) {
      setError(res.error);
    } else {
      setSuccess('Manual match completed.');
      setMatchTxn(null);
      setMatchForm({ transaction_type: 'payment', transaction_id: '' });
      await load(selectedAccountId);
    }
    setSaving(false);
  };

  const handleCreateTransaction = async () => {
    if (!orgId || !createTxn) {
      setError('Transaction context missing.');
      return;
    }

    setSaving(true);
    setError('');
    const payload = {
      description: createForm.description || createTxn.txn.description || '',
      vendor: createForm.vendor,
      category: createForm.category,
      customer_name: createForm.customer_name,
      customer_email: createForm.customer_email,
      currency: selectedAccount?.currency || 'USD',
    };

    const res = await api.bankCreateTransaction(orgId, Number(createTxn.txn.id), createTxn.type, payload);
    if (res.error) {
      setError(res.error);
    } else {
      setSuccess(`New ${createTxn.type} created and auto-matched.`);
      setCreateTxn(null);
      setCreateForm({ description: '', vendor: '', category: 'General', customer_name: '', customer_email: '' });
      await load(selectedAccountId);
    }
    setSaving(false);
  };

  const handleSkip = async () => {
    if (!orgId || !skipTxn) {
      setError('Transaction context missing.');
      return;
    }
    setSaving(true);
    setError('');
    const res = await api.bankSkip(orgId, Number(skipTxn.id), skipReason.trim());
    if (res.error) {
      setError(res.error);
    } else {
      setSuccess('Transaction skipped.');
      setSkipTxn(null);
      setSkipReason('');
      await load(selectedAccountId);
    }
    setSaving(false);
  };

  const handleReconcile = async () => {
    if (!orgId || !reconcileForm.bank_account_id || !reconcileForm.statement_date || !reconcileForm.ending_balance) {
      setError('Bank account, statement date, and ending balance are required.');
      return;
    }

    setSaving(true);
    setError('');
    const res = await api.bankReconcile(
      orgId,
      Number(reconcileForm.bank_account_id),
      reconcileForm.statement_date,
      Number(reconcileForm.ending_balance),
      reconcileForm.force,
      reconcileForm.note
    );
    if (res.error) {
      setError(res.error);
    } else {
      const data = (res as any).data || {};
      setSuccess(`Reconciliation finalized. Difference: ${money(data.difference || 0, selectedAccount?.currency || 'USD')}.`);
      setShowReconcileForm(false);
      await load(selectedAccountId);
    }
    setSaving(false);
  };

  return (
    <ClientShell title="Bank Reconciliation" eyebrow="Feeds, Rules, Reconcile" organization={context?.organization}>
      <div className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Metric label="Bank Accounts" value={stats?.total_accounts ?? 0} />
          <Metric label="Total Balance" value={money(stats?.total_balance, selectedAccount?.currency || 'USD')} />
          <Metric label="Unmatched" value={unmatched} tone={unmatched > 0 ? 'warning' : 'default'} />
          <Metric label="Reconciled" value={stats?.reconciled_count ?? 0} />
        </div>

        {unmatched > 0 && (
          <div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <Wallet className="mt-0.5 h-5 w-5 shrink-0" />
            <p>
              <span className="font-semibold">{unmatched}</span> transaction(s) need review before full reconciliation.
            </p>
          </div>
        )}

        <div className="glass-panel p-4">
          <div className="grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto_auto_auto]">
            <Field label="Bank Account" note='💳 Select bank account to reconcile.'>
              <select
                value={selectedAccountId}
                onChange={(e) => {
                  const value = e.target.value;
                  setSelectedAccountId(value);
                  setUploadForm((p) => ({ ...p, bank_account_id: value }));
                  setFeedForm((p) => ({ ...p, bank_account_id: value }));
                  setReconcileForm((p) => ({ ...p, bank_account_id: value }));
                }}
                className="w-full rounded-lg border border-border px-3 py-2.5 text-sm"
              >
                <option value="">Select account...</option>
                {accounts.map((a) => (
                  <option key={a.id} value={a.id}>{a.account_name}</option>
                ))}
              </select>
            </Field>
            <Button title='📁 CSV format: date, amount, description. Max 10MB.' onClick={() => setShowUploadModal(true)}>
              Upload Statement
            </Button>
            <Button title='🔌 Plaid / Yodlee integration. Automatic sync.' onClick={() => setShowFeedModal(true)}>
              <Plug className="h-4 w-4" />
              Connect Bank
            </Button>
            <Button title='✅ Reconcile now – all matched transactions will be locked.' onClick={() => setShowReconcileForm(true)}>
              <ShieldCheck className="h-4 w-4" />
              Reconcile Now
            </Button>
          </div>
        </div>

        {error && <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>}
        {success && <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">{success}</div>}

        <div className="glass-panel overflow-hidden">
          <div className="flex items-center justify-between border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Transactions</h2>
            <Button onClick={() => void load(selectedAccountId)} variant="secondary" size="sm">
              <RefreshCw className="h-4 w-4" />
              Refresh
            </Button>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Date</th>
                <th className="px-5 py-3 font-semibold">Description</th>
                <th className="px-5 py-3 font-semibold">Reference</th>
                <th className="px-5 py-3 font-semibold">Status</th>
                <th className="px-5 py-3 font-semibold">AI Suggestion</th>
                <th className="px-5 py-3 text-right font-semibold">Amount</th>
                <th className="px-5 py-3 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={7} className="px-5 py-10 text-center text-slate-500">Loading transactions...</td></tr>
              ) : transactions.length === 0 ? (
                <tr><td colSpan={7} className="px-5 py-10 text-center text-slate-500">No transactions for this account.</td></tr>
              ) : transactions.map((txn) => (
                <tr key={txn.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3 text-slate-600">{txn.transaction_date || '—'}</td>
                  <td className="px-5 py-3 text-ink">{txn.description || '—'}</td>
                  <td className="px-5 py-3 font-mono text-xs text-slate-500">{txn.reference || '—'}</td>
                  <td className="px-5 py-3"><StatusBadge status={txn.status} /></td>
                  <td className="px-5 py-3">
                    {txn.suggested_transaction_id ? (
                      <span className="badge border border-primary/20 bg-primary/10 text-primary-dark" title='🤖 AI suggests match based on amount, date, and description.'>
                        {Math.round(Number(txn.suggested_confidence || 0))}% {Number(txn.suggested_confidence || 0) >= 85 ? 'High' : 'Medium'}
                      </span>
                    ) : (
                      <span className="text-xs text-slate-400">No suggestion</span>
                    )}
                  </td>
                  <td className={`px-5 py-3 text-right font-bold ${Number(txn.amount) >= 0 ? 'text-success' : 'text-red-600'}`}>
                    {money(txn.amount, selectedAccount?.currency || 'USD')}
                  </td>
                  <td className="px-5 py-3">
                    <div className="flex flex-wrap gap-1">
                      {txn.status === 'unmatched' ? (
                        <>
                          {txn.suggested_transaction_id && (
                            <Button size="sm" variant="secondary" onClick={() => void handleMatchSuggested(txn)} disabled={saving}>
                              Match
                            </Button>
                          )}
                          <Button size="sm" variant="secondary" onClick={() => setMatchTxn(txn)}>Manual Match</Button>
                          <Button size="sm" variant="secondary" title='✏️ Add new transaction to match this bank entry.' onClick={() => { setCreateTxn({ txn, type: 'expense' }); setCreateForm((p) => ({ ...p, description: txn.description || '' })); }}>
                            Create Expense
                          </Button>
                          <Button size="sm" variant="secondary" title='✏️ Add new transaction to match this bank entry.' onClick={() => { setCreateTxn({ txn, type: 'invoice' }); setCreateForm((p) => ({ ...p, description: txn.description || '' })); }}>
                            Create Invoice
                          </Button>
                          <Button size="sm" variant="secondary" title='⏭️ Ignore this bank transaction (not an expense or income).' onClick={() => setSkipTxn(txn)}>
                            Skip
                          </Button>
                        </>
                      ) : (
                        <span className="text-xs text-slate-500">Locked / completed</span>
                      )}
                      <WpLink to={`/attachments?resource_type=bank_transaction&resource_id=${txn.id}`}>
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

        <div className="glass-panel p-4 text-sm">
          <p className="font-semibold text-ink">Balance check</p>
          <p className="mt-1 text-slate-600" title='📊 Difference: Ending balance minus matched/reconciled system balance.'>
            Bank balance: {money(Number(reconcileForm.ending_balance || selectedAccount?.current_balance || 0), selectedAccount?.currency || 'USD')} | System balance: {money(systemBalancePreview, selectedAccount?.currency || 'USD')} | Difference: {money(previewDifference, selectedAccount?.currency || 'USD')}
          </p>
        </div>

        <div className="glass-panel overflow-hidden">
          <div className="border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Recent Reconciliation Log</h2>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Statement Date</th>
                <th className="px-5 py-3 font-semibold">Account</th>
                <th className="px-5 py-3 text-right font-semibold">Ending</th>
                <th className="px-5 py-3 text-right font-semibold">System</th>
                <th className="px-5 py-3 text-right font-semibold">Difference</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {reconciliations.length === 0 ? (
                <tr><td colSpan={5} className="px-5 py-8 text-center text-slate-500">No reconciliation history yet.</td></tr>
              ) : reconciliations.map((entry) => (
                <tr key={entry.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3 text-slate-600">{entry.statement_date || '—'}</td>
                  <td className="px-5 py-3 text-slate-600">{entry.account_name || '—'}</td>
                  <td className="px-5 py-3 text-right font-bold text-ink">{money(entry.ending_balance, selectedAccount?.currency || 'USD')}</td>
                  <td className="px-5 py-3 text-right text-slate-600">{money(entry.system_balance, selectedAccount?.currency || 'USD')}</td>
                  <td className={`px-5 py-3 text-right font-bold ${Math.abs(Number(entry.difference || 0)) < 0.01 ? 'text-success' : 'text-amber-700'}`}>
                    {money(entry.difference, selectedAccount?.currency || 'USD')}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {showUploadModal && (
          <Modal title="Upload Statement CSV" onClose={() => setShowUploadModal(false)}>
            <div className="grid gap-4">
              <Field label="Bank account" note='💳 Select bank account to reconcile.'>
                <select
                  value={uploadForm.bank_account_id}
                  onChange={(e) => setUploadForm((p) => ({ ...p, bank_account_id: e.target.value }))}
                  className="w-full rounded-lg border border-border px-3 py-2.5 text-sm"
                >
                  <option value="">Select account...</option>
                  {accounts.map((a) => <option key={a.id} value={a.id}>{a.account_name}</option>)}
                </select>
              </Field>
              <Field label="Statement file (.csv)" note='📁 CSV format: date, amount, description. Max 10MB.'>
                <input
                  type="file"
                  accept=".csv,text/csv"
                  onChange={(e) => setUploadForm((p) => ({ ...p, file: e.target.files?.[0] || null }))}
                  className="w-full rounded-lg border border-border px-3 py-2.5 text-sm"
                />
              </Field>
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setShowUploadModal(false)}>Cancel</Button>
              <Button onClick={() => void handleUploadCsv()} disabled={saving}>Upload Statement</Button>
            </div>
          </Modal>
        )}

        {showFeedModal && (
          <Modal title="Connect Bank Feed" onClose={() => setShowFeedModal(false)}>
            <div className="grid gap-4">
              <Field label="Bank account">
                <select
                  value={feedForm.bank_account_id}
                  onChange={(e) => setFeedForm((p) => ({ ...p, bank_account_id: e.target.value }))}
                  className="w-full rounded-lg border border-border px-3 py-2.5 text-sm"
                >
                  <option value="">Select account...</option>
                  {accounts.map((a) => <option key={a.id} value={a.id}>{a.account_name}</option>)}
                </select>
              </Field>
              <Field label="Provider" note='🔌 Plaid / Yodlee integration. Automatic sync.'>
                <select
                  value={feedForm.provider}
                  onChange={(e) => setFeedForm((p) => ({ ...p, provider: e.target.value as 'plaid' | 'yodlee' }))}
                  className="w-full rounded-lg border border-border px-3 py-2.5 text-sm"
                >
                  <option value="plaid">Plaid</option>
                  <option value="yodlee">Yodlee</option>
                </select>
              </Field>
              <Field label="Access token"><Input value={feedForm.access_token} onChange={(e) => setFeedForm((p) => ({ ...p, access_token: e.target.value }))} /></Field>
              <Field label="Refresh token"><Input value={feedForm.refresh_token} onChange={(e) => setFeedForm((p) => ({ ...p, refresh_token: e.target.value }))} /></Field>
              <Field label="Token expires at (optional)"><Input type="datetime-local" value={feedForm.token_expires_at} onChange={(e) => setFeedForm((p) => ({ ...p, token_expires_at: e.target.value }))} /></Field>
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setShowFeedModal(false)}>Cancel</Button>
              <Button onClick={() => void handleConnectFeed()} disabled={saving}>Connect Bank</Button>
            </div>
          </Modal>
        )}

        {matchTxn && (
          <Modal title="Manual Match" onClose={() => setMatchTxn(null)}>
            <p className="mb-4 text-sm text-slate-600">
              Match {matchTxn.description || `Txn #${matchTxn.id}`} ({money(matchTxn.amount, selectedAccount?.currency || 'USD')}) to a system record.
            </p>
            <div className="grid gap-4">
              <Field label="Transaction type">
                <select
                  value={matchForm.transaction_type}
                  onChange={(e) => setMatchForm((p) => ({ ...p, transaction_type: e.target.value as any }))}
                  className="w-full rounded-lg border border-border px-3 py-2.5 text-sm"
                >
                  <option value="payment">Payment</option>
                  <option value="expense">Expense</option>
                  <option value="invoice">Invoice</option>
                  <option value="journal">Journal</option>
                </select>
              </Field>
              <Field label="Transaction ID"><Input value={matchForm.transaction_id} onChange={(e) => setMatchForm((p) => ({ ...p, transaction_id: e.target.value }))} /></Field>
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setMatchTxn(null)}>Cancel</Button>
              <Button onClick={() => void handleManualMatch()} disabled={saving}>Confirm Match</Button>
            </div>
          </Modal>
        )}

        {createTxn && (
          <Modal title={`Create ${createTxn.type === 'expense' ? 'Expense' : 'Invoice'}`} onClose={() => setCreateTxn(null)}>
            <div className="grid gap-4">
              <Field label="Description"><Input value={createForm.description} onChange={(e) => setCreateForm((p) => ({ ...p, description: e.target.value }))} /></Field>
              {createTxn.type === 'expense' ? (
                <>
                  <Field label="Vendor"><Input value={createForm.vendor} onChange={(e) => setCreateForm((p) => ({ ...p, vendor: e.target.value }))} /></Field>
                  <Field label="Category"><Input value={createForm.category} onChange={(e) => setCreateForm((p) => ({ ...p, category: e.target.value }))} /></Field>
                </>
              ) : (
                <>
                  <Field label="Customer name"><Input value={createForm.customer_name} onChange={(e) => setCreateForm((p) => ({ ...p, customer_name: e.target.value }))} /></Field>
                  <Field label="Customer email (optional)"><Input value={createForm.customer_email} onChange={(e) => setCreateForm((p) => ({ ...p, customer_email: e.target.value }))} /></Field>
                </>
              )}
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setCreateTxn(null)}>Cancel</Button>
              <Button onClick={() => void handleCreateTransaction()} disabled={saving}>
                {createTxn.type === 'expense' ? 'Create Expense' : 'Create Invoice'}
              </Button>
            </div>
          </Modal>
        )}

        {skipTxn && (
          <Modal title="Skip Transaction" onClose={() => setSkipTxn(null)}>
            <p className="mb-4 text-sm text-slate-600">Optional reason for audit trail.</p>
            <Field label="Reason (optional)"><Input value={skipReason} onChange={(e) => setSkipReason(e.target.value)} /></Field>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setSkipTxn(null)}>Cancel</Button>
              <Button onClick={() => void handleSkip()} disabled={saving}>Skip</Button>
            </div>
          </Modal>
        )}

        {showReconcileForm && (
          <Modal title="Finalize Reconciliation" onClose={() => setShowReconcileForm(false)}>
            <div className="grid gap-4">
              <Field label="Bank account">
                <select
                  value={reconcileForm.bank_account_id}
                  onChange={(e) => setReconcileForm((p) => ({ ...p, bank_account_id: e.target.value }))}
                  className="w-full rounded-lg border border-border px-3 py-2.5 text-sm"
                >
                  <option value="">Select account...</option>
                  {accounts.map((a) => <option key={a.id} value={a.id}>{a.account_name}</option>)}
                </select>
              </Field>
              <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Statement date"><Input type="date" value={reconcileForm.statement_date} onChange={(e) => setReconcileForm((p) => ({ ...p, statement_date: e.target.value }))} /></Field>
                <Field label="Ending balance"><Input type="number" step="0.01" value={reconcileForm.ending_balance} onChange={(e) => setReconcileForm((p) => ({ ...p, ending_balance: e.target.value }))} /></Field>
              </div>
              <div className="rounded-lg border border-border bg-slate-50 p-3 text-xs text-slate-600">
                Bank balance: {money(Number(reconcileForm.ending_balance || selectedAccount?.current_balance || 0), selectedAccount?.currency || 'USD')} | System balance: {money(systemBalancePreview, selectedAccount?.currency || 'USD')} | Difference: {money(previewDifference, selectedAccount?.currency || 'USD')}
              </div>
              <label className="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" checked={reconcileForm.force} onChange={(e) => setReconcileForm((p) => ({ ...p, force: e.target.checked }))} />
                Reconcile even when mismatch exists (warning accepted)
              </label>
              <Field label="Note (optional)"><Input value={reconcileForm.note} onChange={(e) => setReconcileForm((p) => ({ ...p, note: e.target.value }))} /></Field>
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setShowReconcileForm(false)}>Cancel</Button>
              <Button onClick={() => void handleReconcile()} disabled={saving}>Finalize Reconciliation</Button>
            </div>
          </Modal>
        )}
      </div>
    </ClientShell>
  );
}

function Metric({ label, value, tone = 'default' }: { label: string; value: string | number; tone?: 'default' | 'warning' }) {
  return (
    <div className="stat-card">
      <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</p>
      <p className={`mt-2 text-3xl font-black ${tone === 'warning' ? 'text-amber-700' : 'text-ink'}`}>{value}</p>
    </div>
  );
}

function StatusBadge({ status }: { status: string }) {
  const colors: Record<string, string> = {
    unmatched: 'border-amber-200 bg-amber-50 text-amber-800',
    matched: 'border-primary/20 bg-primary/10 text-primary-dark',
    reconciled: 'border-success/20 bg-success/10 text-success',
    skipped: 'border-slate-200 bg-slate-100 text-slate-600',
  };

  return (
    <span className={`badge border ${colors[status] || 'border-border bg-slate-50 text-slate-700'}`}>
      {status || 'unknown'}
    </span>
  );
}

function money(value?: string | number, currency = 'USD') {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(Number(value || 0));
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

function Field({ label, children, note = '' }: { label: string; children: ReactNode; note?: string }) {
  return (
    <label className="block space-y-1.5 text-sm">
      <span className="font-medium text-slate-700">{label}</span>
      {children}
      {note ? <span className="block text-xs text-slate-500">{note}</span> : null}
    </label>
  );
}
