import { useEffect, useMemo, useState, type ReactNode } from 'react';
import WpLink from '../components/WpLink';

import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { Landmark, Paperclip, Plus, RefreshCw, ShieldCheck, Wallet } from 'lucide-react';

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

  const [showAccountForm, setShowAccountForm] = useState(false);
  const [accountForm, setAccountForm] = useState({
    account_name: '',
    account_number: '',
    currency: 'USD',
    current_balance: '',
  });

  const [showImportForm, setShowImportForm] = useState(false);
  const [importForm, setImportForm] = useState({
    bank_account_id: '',
    date: new Date().toISOString().slice(0, 10),
    amount: '',
    description: '',
    reference: '',
  });

  const [matchTxn, setMatchTxn] = useState<any | null>(null);
  const [matchForm, setMatchForm] = useState({
    transaction_type: 'payment' as 'payment' | 'expense' | 'journal',
    transaction_id: '',
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
    const orgId = nextContext?.organization?.id;
    if (!orgId) {
      setError('Organization is not set up yet.');
      setLoading(false);
      return;
    }

    const [dash, accountsRes, txnsRes] = await Promise.all([
      api.bankDashboard(),
      api.bankAccountsList(orgId),
      api.bankTransactionsList(orgId, 0, { limit: 100 }),
    ]);

    if (dash.error) {
      setError(dash.error || 'Unable to load bank data.');
    } else {
      const data = (dash as any).data;
      setStats(data?.stats || null);
      setReconciliations(data?.recent_reconciliations || []);
    }
    if (!accountsRes.error) setAccounts((accountsRes as any).data?.accounts || []);
    if (!txnsRes.error) setTransactions((txnsRes as any).data?.transactions || []);
    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const orgId = context?.organization?.id;
  const unmatched = stats?.unmatched_count ?? 0;
  const selectedImportAccount = useMemo(
    () => accounts.find((a) => String(a.id) === importForm.bank_account_id),
    [accounts, importForm.bank_account_id]
  );

  const handleCreateAccount = async () => {
    if (!orgId || !accountForm.account_name.trim()) {
      setError('Account name is required.');
      return;
    }
    setSaving(true);
    setError('');
    const res = await api.bankAccountCreate(orgId, {
      account_name: accountForm.account_name.trim(),
      account_number: accountForm.account_number.trim(),
      currency: accountForm.currency,
      current_balance: parseFloat(accountForm.current_balance) || 0,
    });
    if (res.error) {
      setError(res.error);
    } else {
      setSuccess('Bank account created.');
      setShowAccountForm(false);
      setAccountForm({ account_name: '', account_number: '', currency: 'USD', current_balance: '' });
      await load();
    }
    setSaving(false);
  };

  const handleImportRow = async () => {
    if (!orgId || !importForm.bank_account_id || !importForm.date || !importForm.amount) {
      setError('Account, date, and amount are required.');
      return;
    }
    setSaving(true);
    setError('');
    const res = await api.bankImportRows(orgId, Number(importForm.bank_account_id), [{
      transaction_date: importForm.date,
      amount: Number(importForm.amount),
      description: importForm.description,
      reference: importForm.reference,
    }]);
    if (res.error) {
      setError(res.error);
    } else {
      const result = (res as any).data || {};
      setSuccess(`Imported ${result.inserted || 0} row(s), ${result.duplicates || 0} duplicate(s).`);
      setShowImportForm(false);
      setImportForm({
        bank_account_id: '',
        date: new Date().toISOString().slice(0, 10),
        amount: '',
        description: '',
        reference: '',
      });
      await load();
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
      setSuccess('Transaction matched.');
      setMatchTxn(null);
      setMatchForm({ transaction_type: 'payment', transaction_id: '' });
      await load();
    }
    setSaving(false);
  };

  const handleSkip = async () => {
    if (!orgId || !skipTxn || !skipReason.trim()) {
      setError('Skip reason is required.');
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
      await load();
    }
    setSaving(false);
  };

  const handleReconcile = async () => {
    if (!orgId || !reconcileForm.bank_account_id || !reconcileForm.statement_date || !reconcileForm.ending_balance) {
      setError('Account, statement date, and ending balance are required.');
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
      setSuccess(`Reconciled. Difference: ${money(data.difference || 0)}.`);
      setShowReconcileForm(false);
      setReconcileForm({
        bank_account_id: '',
        statement_date: new Date().toISOString().slice(0, 10),
        ending_balance: '',
        force: false,
        note: '',
      });
      await load();
    }
    setSaving(false);
  };

  return (
    <ClientShell title="Bank Reconciliation" eyebrow="Feeds & matching" organization={context?.organization}>
      <div className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Metric label="Bank Accounts" value={stats?.total_accounts ?? 0} />
          <Metric label="Total Balance" value={money(stats?.total_balance)} />
          <Metric label="Unmatched" value={unmatched} tone={unmatched > 0 ? 'warning' : 'default'} />
          <Metric label="Reconciled" value={stats?.reconciled_count ?? 0} />
        </div>

        {unmatched > 0 && (
          <div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <Wallet className="mt-0.5 h-5 w-5 shrink-0" />
            <p>
              <span className="font-semibold">{unmatched}</span> bank transaction{unmatched === 1 ? '' : 's'} still need matching or review.
            </p>
          </div>
        )}

        <div className="flex flex-wrap justify-end gap-2">
          <Button size="sm" onClick={() => { setShowAccountForm(true); setError(''); setSuccess(''); }}>
            <Plus className="h-4 w-4" />
            Add account
          </Button>
          <Button size="sm" onClick={() => { setShowImportForm(true); setError(''); setSuccess(''); }}>
            <Plus className="h-4 w-4" />
            Import row
          </Button>
          <Button size="sm" onClick={() => { setShowReconcileForm(true); setError(''); setSuccess(''); }}>
            <ShieldCheck className="h-4 w-4" />
            Reconcile
          </Button>
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {error && (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>
        )}
        {success && (
          <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">{success}</div>
        )}

        <div className="glass-panel overflow-hidden">
          <div className="border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Bank Accounts</h2>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Account</th>
                <th className="px-5 py-3 font-semibold">Number</th>
                <th className="px-5 py-3 font-semibold">Currency</th>
                <th className="px-5 py-3 text-right font-semibold">Balance</th>
                <th className="px-5 py-3 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={5} className="px-5 py-8 text-center text-slate-500">Loading accounts...</td></tr>
              ) : accounts.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-5 py-10 text-center">
                    <Landmark className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No bank accounts configured yet.</p>
                  </td>
                </tr>
              ) : accounts.map((account: any) => (
                <tr key={account.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3 font-semibold text-ink">{account.account_name}</td>
                  <td className="px-5 py-3 text-slate-600">{account.account_number || '—'}</td>
                  <td className="px-5 py-3 text-slate-600">{account.currency || 'USD'}</td>
                  <td className="px-5 py-3 text-right font-bold text-ink">{money(account.current_balance, account.currency)}</td>
                  <td className="px-5 py-3">
                    <WpLink to={`/attachments?resource_type=bank_account&resource_id=${account.id}`}>
                      <Button size="sm" variant="secondary">
                        <Paperclip className="h-3.5 w-3.5" />
                        Statements
                      </Button>
                    </WpLink>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="glass-panel overflow-hidden">
          <div className="border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Recent Transactions</h2>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Date</th>
                <th className="px-5 py-3 font-semibold">Account</th>
                <th className="px-5 py-3 font-semibold">Description</th>
                <th className="px-5 py-3 font-semibold">Reference</th>
                <th className="px-5 py-3 font-semibold">Status</th>
                <th className="px-5 py-3 text-right font-semibold">Amount</th>
                <th className="px-5 py-3 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={7} className="px-5 py-8 text-center text-slate-500">Loading transactions...</td></tr>
              ) : transactions.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-5 py-10 text-center text-sm text-slate-500">No bank transactions imported yet.</td>
                </tr>
              ) : transactions.map((txn: any) => (
                <tr key={txn.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3 text-slate-600">{txn.transaction_date || '—'}</td>
                  <td className="px-5 py-3 text-slate-600">{txn.account_name || '—'}</td>
                  <td className="px-5 py-3 text-ink">{txn.description || '—'}</td>
                  <td className="px-5 py-3 font-mono text-xs text-slate-500">{txn.reference || '—'}</td>
                  <td className="px-5 py-3"><StatusBadge status={txn.status} /></td>
                  <td className={`px-5 py-3 text-right font-bold ${Number(txn.amount) >= 0 ? 'text-success' : 'text-red-600'}`}>
                    {money(txn.amount)}
                  </td>
                  <td className="px-5 py-3">
                    <div className="flex flex-wrap gap-1">
                      {txn.status === 'unmatched' ? (
                        <>
                          <Button size="sm" variant="secondary" onClick={() => { setMatchTxn(txn); setError(''); }}>
                            Match
                          </Button>
                          <Button size="sm" variant="secondary" onClick={() => { setSkipTxn(txn); setError(''); }}>
                            Skip
                          </Button>
                        </>
                      ) : (
                        <span className="text-xs text-slate-500">No action</span>
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

        <div className="glass-panel overflow-hidden">
          <div className="border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Recent Reconciliations</h2>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Statement Date</th>
                <th className="px-5 py-3 font-semibold">Account</th>
                <th className="px-5 py-3 text-right font-semibold">Ending Balance</th>
                <th className="px-5 py-3 text-right font-semibold">System Balance</th>
                <th className="px-5 py-3 text-right font-semibold">Difference</th>
                <th className="px-5 py-3 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={6} className="px-5 py-8 text-center text-slate-500">Loading reconciliation history...</td></tr>
              ) : reconciliations.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-5 py-10 text-center text-sm text-slate-500">No reconciliations finalized yet.</td>
                </tr>
              ) : reconciliations.map((entry: any) => (
                <tr key={entry.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3 text-slate-600">{entry.statement_date || '—'}</td>
                  <td className="px-5 py-3 text-slate-600">{entry.account_name || '—'}</td>
                  <td className="px-5 py-3 text-right font-bold text-ink">{money(entry.ending_balance)}</td>
                  <td className="px-5 py-3 text-right text-slate-600">{money(entry.system_balance)}</td>
                  <td className={`px-5 py-3 text-right font-bold ${Math.abs(Number(entry.difference)) < 0.01 ? 'text-success' : 'text-amber-700'}`}>
                    {money(entry.difference)}
                  </td>
                  <td className="px-5 py-3">
                    {entry.bank_account_id ? (
                      <WpLink to={`/attachments?resource_type=bank_account&resource_id=${entry.bank_account_id}`}>
                        <Button size="sm" variant="secondary">
                          <Paperclip className="h-3.5 w-3.5" />
                          Statement
                        </Button>
                      </WpLink>
                    ) : (
                      <span className="text-xs text-slate-400">—</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {showAccountForm && (
          <Modal title="Add Bank Account" onClose={() => setShowAccountForm(false)}>
            <div className="grid gap-4">
              <Field label="Account name">
                <Input value={accountForm.account_name} onChange={(e) => setAccountForm((p) => ({ ...p, account_name: e.target.value }))} />
              </Field>
              <Field label="Account number">
                <Input value={accountForm.account_number} onChange={(e) => setAccountForm((p) => ({ ...p, account_number: e.target.value }))} />
              </Field>
              <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Currency">
                  <Input value={accountForm.currency} onChange={(e) => setAccountForm((p) => ({ ...p, currency: e.target.value.toUpperCase() }))} />
                </Field>
                <Field label="Opening balance">
                  <Input type="number" step="0.01" value={accountForm.current_balance} onChange={(e) => setAccountForm((p) => ({ ...p, current_balance: e.target.value }))} />
                </Field>
              </div>
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setShowAccountForm(false)}>Cancel</Button>
              <Button onClick={handleCreateAccount} disabled={saving}>Create</Button>
            </div>
          </Modal>
        )}

        {showImportForm && (
          <Modal title="Import Statement Row" onClose={() => setShowImportForm(false)}>
            <div className="grid gap-4">
              <Field label="Bank account">
                <select
                  value={importForm.bank_account_id}
                  onChange={(e) => setImportForm((p) => ({ ...p, bank_account_id: e.target.value }))}
                  className="w-full rounded-lg border border-border px-3 py-2.5 text-sm"
                >
                  <option value="">Select account...</option>
                  {accounts.map((a) => (
                    <option key={a.id} value={a.id}>{a.account_name}</option>
                  ))}
                </select>
              </Field>
              <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Date">
                  <Input type="date" value={importForm.date} onChange={(e) => setImportForm((p) => ({ ...p, date: e.target.value }))} />
                </Field>
                <Field label="Amount">
                  <Input type="number" step="0.01" value={importForm.amount} onChange={(e) => setImportForm((p) => ({ ...p, amount: e.target.value }))} />
                </Field>
              </div>
              <Field label="Description">
                <Input value={importForm.description} onChange={(e) => setImportForm((p) => ({ ...p, description: e.target.value }))} />
              </Field>
              <Field label="Reference">
                <Input value={importForm.reference} onChange={(e) => setImportForm((p) => ({ ...p, reference: e.target.value }))} />
              </Field>
              {selectedImportAccount && (
                <div className="rounded-lg border border-border bg-slate-50 p-3 text-xs text-slate-600">
                  Importing into `{selectedImportAccount.account_name}`.
                </div>
              )}
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setShowImportForm(false)}>Cancel</Button>
              <Button onClick={handleImportRow} disabled={saving}>Import</Button>
            </div>
          </Modal>
        )}

        {matchTxn && (
          <Modal title="Manual Match" onClose={() => setMatchTxn(null)}>
            <p className="mb-4 text-sm text-slate-600">
              Match `{matchTxn.description || matchTxn.reference || `Txn #${matchTxn.id}`}` ({money(matchTxn.amount)}) to a system record.
            </p>
            <div className="grid gap-4">
              <Field label="Transaction type">
                <select
                  value={matchForm.transaction_type}
                  onChange={(e) => setMatchForm((p) => ({ ...p, transaction_type: e.target.value as 'payment' | 'expense' | 'journal' }))}
                  className="w-full rounded-lg border border-border px-3 py-2.5 text-sm"
                >
                  <option value="payment">Payment</option>
                  <option value="expense">Expense</option>
                  <option value="journal">Journal</option>
                </select>
              </Field>
              <Field label="Transaction ID">
                <Input value={matchForm.transaction_id} onChange={(e) => setMatchForm((p) => ({ ...p, transaction_id: e.target.value }))} />
              </Field>
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setMatchTxn(null)}>Cancel</Button>
              <Button onClick={handleManualMatch} disabled={saving}>Confirm Match</Button>
            </div>
          </Modal>
        )}

        {skipTxn && (
          <Modal title="Skip Transaction" onClose={() => setSkipTxn(null)}>
            <p className="mb-4 text-sm text-slate-600">
              Provide a reason to skip this transaction from matching.
            </p>
            <Field label="Reason">
              <Input value={skipReason} onChange={(e) => setSkipReason(e.target.value)} />
            </Field>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setSkipTxn(null)}>Cancel</Button>
              <Button onClick={handleSkip} disabled={saving}>Skip</Button>
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
                  {accounts.map((a) => (
                    <option key={a.id} value={a.id}>{a.account_name}</option>
                  ))}
                </select>
              </Field>
              <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Statement date">
                  <Input type="date" value={reconcileForm.statement_date} onChange={(e) => setReconcileForm((p) => ({ ...p, statement_date: e.target.value }))} />
                </Field>
                <Field label="Ending balance">
                  <Input type="number" step="0.01" value={reconcileForm.ending_balance} onChange={(e) => setReconcileForm((p) => ({ ...p, ending_balance: e.target.value }))} />
                </Field>
              </div>
              <label className="flex items-center gap-2 text-sm text-slate-700">
                <input
                  type="checkbox"
                  checked={reconcileForm.force}
                  onChange={(e) => setReconcileForm((p) => ({ ...p, force: e.target.checked }))}
                />
                Force reconcile if small balance mismatch exists
              </label>
              <Field label="Note (optional)">
                <Input value={reconcileForm.note} onChange={(e) => setReconcileForm((p) => ({ ...p, note: e.target.value }))} />
              </Field>
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setShowReconcileForm(false)}>Cancel</Button>
              <Button onClick={handleReconcile} disabled={saving}>Finalize</Button>
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

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="block space-y-1.5 text-sm">
      <span className="font-medium text-slate-700">{label}</span>
      {children}
    </label>
  );
}
