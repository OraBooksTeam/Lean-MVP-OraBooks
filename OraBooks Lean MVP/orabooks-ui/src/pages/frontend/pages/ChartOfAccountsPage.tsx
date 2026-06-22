import { useEffect, useMemo, useState, type ReactNode } from 'react';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { BookOpen, Download, Info, Link2, Pencil, Plus, RefreshCw } from 'lucide-react';

const ACCOUNT_TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'] as const;
const NORMAL_BALANCES = ['debit', 'credit'] as const;

type CoaAccount = {
  id: number;
  code: string;
  name: string;
  type: string;
  normal_balance: string;
  system_generated?: number;
  has_journal_entries?: number;
  can_edit?: boolean;
};

const DEFAULT_NORMAL_BALANCE: Record<string, string> = {
  asset: 'debit',
  liability: 'credit',
  equity: 'credit',
  revenue: 'credit',
  expense: 'debit',
};

export default function ChartOfAccountsPage() {
  const [context, setContext] = useState<any>(null);
  const [accounts, setAccounts] = useState<CoaAccount[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [search, setSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState('all');
  const [actionId, setActionId] = useState<number | null>(null);
  const [addModalOpen, setAddModalOpen] = useState(false);
  const [editAccount, setEditAccount] = useState<CoaAccount | null>(null);
  const [form, setForm] = useState({
    code: '',
    name: '',
    type: 'asset' as typeof ACCOUNT_TYPES[number],
    normal_balance: 'debit' as typeof NORMAL_BALANCES[number],
  });

  const orgId = context?.organization?.id;
  const canManage = ['owner', 'admin'].includes(context?.role || '');

  const load = async () => {
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

    const res = await api.coaGet(nextOrgId);
    if (res.error) setError(res.error || 'Unable to load chart of accounts.');
    else setAccounts((res as any).data || []);
    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const filtered = useMemo(() => {
    const query = search.trim().toLowerCase();
    return accounts.filter((account) => {
      const matchesType = typeFilter === 'all' || account.type === typeFilter;
      const matchesSearch =
        !query ||
        String(account.code || '').toLowerCase().includes(query) ||
        String(account.name || '').toLowerCase().includes(query);
      return matchesType && matchesSearch;
    });
  }, [accounts, search, typeFilter]);

  const resetForm = () => {
    setForm({
      code: '',
      name: '',
      type: 'asset',
      normal_balance: 'debit',
    });
  };

  const openAddModal = () => {
    resetForm();
    setAddModalOpen(true);
    setError('');
    setSuccess('');
  };

  const openEditModal = (account: CoaAccount) => {
    setEditAccount(account);
    setForm({
      code: account.code,
      name: account.name,
      type: account.type as typeof ACCOUNT_TYPES[number],
      normal_balance: account.normal_balance as typeof NORMAL_BALANCES[number],
    });
    setError('');
    setSuccess('');
  };

  const handleTypeChange = (type: typeof ACCOUNT_TYPES[number]) => {
    setForm((prev) => ({
      ...prev,
      type,
      normal_balance: (DEFAULT_NORMAL_BALANCE[type] || 'debit') as typeof NORMAL_BALANCES[number],
    }));
  };

  const handleCreate = async () => {
    if (!orgId || !form.code.trim() || !form.name.trim()) {
      setError('Account code and name are required.');
      return;
    }

    setActionId(-1);
    setError('');
    setSuccess('');
    const res = await api.coaCreate(orgId, {
      code: form.code.trim(),
      name: form.name.trim(),
      type: form.type,
      normal_balance: form.normal_balance,
    });
    if (res.error) {
      setError(res.error);
    } else {
      setSuccess('Account created.');
      setAddModalOpen(false);
      resetForm();
      await load();
    }
    setActionId(null);
  };

  const handleUpdate = async () => {
    if (!orgId || !editAccount || !form.name.trim()) {
      setError('Account name is required.');
      return;
    }

    setActionId(editAccount.id);
    setError('');
    setSuccess('');
    const res = await api.coaUpdate(orgId, editAccount.id, {
      code: form.code.trim(),
      name: form.name.trim(),
      type: form.type,
      normal_balance: form.normal_balance,
    });
    if (res.error) {
      setError(res.error);
    } else {
      setSuccess('Account updated.');
      setEditAccount(null);
      resetForm();
      await load();
    }
    setActionId(null);
  };

  const editLockedStructure = Boolean(
    editAccount && (Number(editAccount.system_generated) === 1 || Number(editAccount.has_journal_entries) === 1)
  );

  return (
    <ClientShell
      title="Chart of Accounts"
      eyebrow="Accounting setup"
      organization={context?.organization}
      isPartner={context?.organization?.organization_type === 'partner'}
    >
      <div className="space-y-5">
        <div className="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50/80 p-4 text-sm text-sky-900">
          <Info className="mt-0.5 h-4 w-4 shrink-0" />
          <p>
            Tier templates seed system accounts automatically. Owners and admins can add custom accounts and edit names;
            code, type, and normal balance are locked once an account is used in journals or marked as system-generated.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          <div className="min-w-[200px] flex-1">
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search accounts by code or name…"
            />
          </div>
          <select
            value={typeFilter}
            onChange={(e) => setTypeFilter(e.target.value)}
            className="rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink shadow-sm"
          >
            <option value="all">All types</option>
            {ACCOUNT_TYPES.map((type) => (
              <option key={type} value={type}>{titleCase(type)}</option>
            ))}
          </select>
          {canManage ? (
            <Button onClick={openAddModal} size="sm">
              <Plus className="h-4 w-4" />
              Add account
            </Button>
          ) : null}
          {orgId ? (
            <Button variant="secondary" size="sm" onClick={() => api.coaExport(orgId)}>
              <Download className="h-4 w-4" />
              Export CSV
            </Button>
          ) : null}
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {error && (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">
            {error}
          </div>
        )}
        {success && (
          <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">
            {success}
          </div>
        )}

        <div className="glass-panel overflow-hidden">
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Code</th>
                <th className="px-5 py-3 font-semibold">Account</th>
                <th className="px-5 py-3 font-semibold">Type</th>
                <th className="px-5 py-3 font-semibold">Normal Balance</th>
                <th className="px-5 py-3 font-semibold">Source</th>
                <th className="px-5 py-3 font-semibold">Usage</th>
                {canManage ? <th className="px-5 py-3 font-semibold">Actions</th> : null}
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={canManage ? 7 : 6} className="px-5 py-8 text-center text-slate-500">Loading accounts...</td></tr>
              ) : filtered.length === 0 ? (
                <tr>
                  <td colSpan={canManage ? 7 : 6} className="px-5 py-10 text-center">
                    <BookOpen className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No accounts match your filters.</p>
                  </td>
                </tr>
              ) : filtered.map((account) => (
                <tr key={account.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3 font-mono text-sm font-semibold text-ink">{account.code}</td>
                  <td className="px-5 py-3 font-semibold text-ink">{account.name}</td>
                  <td className="px-5 py-3 text-slate-600">{titleCase(account.type)}</td>
                  <td className="px-5 py-3 capitalize text-slate-600">{account.normal_balance}</td>
                  <td className="px-5 py-3">
                    <span className="badge border border-border bg-slate-50 text-slate-700">
                      {Number(account.system_generated) === 1 ? 'System' : 'Custom'}
                    </span>
                  </td>
                  <td className="px-5 py-3 text-slate-600">
                    {Number(account.has_journal_entries) === 1 ? (
                      <span className="inline-flex items-center gap-1 text-xs font-medium text-amber-700" title="This account has journal entries. Structure fields are locked.">
                        <Link2 className="h-3.5 w-3.5" />
                        In journals
                      </span>
                    ) : (
                      <span className="text-xs text-slate-400">—</span>
                    )}
                  </td>
                  {canManage ? (
                    <td className="px-5 py-3">
                      <Button
                        size="sm"
                        variant="secondary"
                        disabled={actionId === account.id}
                        onClick={() => openEditModal(account)}
                        title="Edit account name and, for unused custom accounts, code/type/balance."
                      >
                        <Pencil className="h-3.5 w-3.5" />
                        Edit
                      </Button>
                    </td>
                  ) : null}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {addModalOpen ? (
        <Modal title="Add account" onClose={() => { setAddModalOpen(false); resetForm(); }}>
          <AccountFormFields form={form} onChange={setForm} onTypeChange={handleTypeChange} />
          <div className="mt-5 flex justify-end gap-2">
            <Button variant="secondary" onClick={() => { setAddModalOpen(false); resetForm(); }}>Cancel</Button>
            <Button onClick={handleCreate} disabled={actionId === -1 || !form.code.trim() || !form.name.trim()}>
              Create account
            </Button>
          </div>
        </Modal>
      ) : null}

      {editAccount ? (
        <Modal title="Edit account" onClose={() => { setEditAccount(null); resetForm(); }}>
          {editLockedStructure ? (
            <p className="text-sm text-amber-700">
              This account is system-generated or already used in journals. Only the account name can be changed.
            </p>
          ) : (
            <p className="text-sm text-slate-600">
              Custom unused accounts allow editing code, type, and normal balance.
            </p>
          )}
          <AccountFormFields
            form={form}
            onChange={setForm}
            onTypeChange={handleTypeChange}
            lockStructure={editLockedStructure}
          />
          <div className="mt-5 flex justify-end gap-2">
            <Button variant="secondary" onClick={() => { setEditAccount(null); resetForm(); }}>Cancel</Button>
            <Button onClick={handleUpdate} disabled={actionId === editAccount.id || !form.name.trim()}>
              Save changes
            </Button>
          </div>
        </Modal>
      ) : null}
    </ClientShell>
  );
}

function AccountFormFields({
  form,
  onChange,
  onTypeChange,
  lockStructure = false,
}: {
  form: {
    code: string;
    name: string;
    type: typeof ACCOUNT_TYPES[number];
    normal_balance: typeof NORMAL_BALANCES[number];
  };
  onChange: (next: typeof form) => void;
  onTypeChange: (type: typeof ACCOUNT_TYPES[number]) => void;
  lockStructure?: boolean;
}) {
  return (
    <div className="mt-4 grid gap-4 sm:grid-cols-2">
      <Input
        label="Account code"
        value={form.code}
        disabled={lockStructure}
        onChange={(e) => onChange({ ...form, code: e.target.value })}
        placeholder="e.g. 6100"
      />
      <Input
        label="Account name"
        value={form.name}
        onChange={(e) => onChange({ ...form, name: e.target.value })}
        placeholder="e.g. Marketing Expense"
      />
      <div>
        <label className="mb-1.5 block text-sm font-medium text-ink-secondary">Type</label>
        <select
          value={form.type}
          disabled={lockStructure}
          onChange={(e) => onTypeChange(e.target.value as typeof ACCOUNT_TYPES[number])}
          className="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm disabled:bg-slate-50"
        >
          {ACCOUNT_TYPES.map((type) => (
            <option key={type} value={type}>{titleCase(type)}</option>
          ))}
        </select>
      </div>
      <div>
        <label className="mb-1.5 block text-sm font-medium text-ink-secondary">Normal balance</label>
        <select
          value={form.normal_balance}
          disabled={lockStructure}
          onChange={(e) => onChange({ ...form, normal_balance: e.target.value as typeof NORMAL_BALANCES[number] })}
          className="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm disabled:bg-slate-50"
        >
          {NORMAL_BALANCES.map((balance) => (
            <option key={balance} value={balance}>{titleCase(balance)}</option>
          ))}
        </select>
      </div>
    </div>
  );
}

function Modal({ title, children, onClose }: { title: string; children: ReactNode; onClose: () => void }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
      <div className="w-full max-w-lg rounded-2xl border border-border bg-white p-6 shadow-xl">
        <div className="flex items-start justify-between gap-3">
          <h3 className="text-lg font-bold text-ink">{title}</h3>
          <button type="button" onClick={onClose} className="text-slate-400 hover:text-slate-600">×</button>
        </div>
        <div className="mt-4">{children}</div>
      </div>
    </div>
  );
}

function titleCase(value?: string) {
  return (value || 'Other').replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}
