import { useEffect, useState, type ReactNode } from 'react';
import WpLink from '../components/WpLink';

import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { Info, Paperclip, Pencil, Plus, RefreshCw, Upload, Users } from 'lucide-react';

type Customer = {
  id: number;
  display_name?: string | null;
  email?: string;
  is_active?: number;
  invoice_count?: number;
  total_paid?: string | number;
  wallet_balance?: string | number;
  last_paid_invoice_date?: string | null;
  notes?: string | null;
};

function customerLabel(customer: Pick<Customer, 'id' | 'display_name' | 'email'>) {
  return customer.display_name?.trim() || customer.email || `Customer #${customer.id}`;
}

export default function CustomersPage() {
  const [context, setContext] = useState<any>(null);
  const [orgId, setOrgId] = useState<number | null>(null);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [stats, setStats] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');
  const [editing, setEditing] = useState<Customer | null>(null);
  const [editNotes, setEditNotes] = useState('');
  const [showCustomerForm, setShowCustomerForm] = useState(false);
  const [customerForm, setCustomerForm] = useState({ name: '', email: '', notes: '' });
  const [saving, setSaving] = useState(false);
  const [success, setSuccess] = useState('');

  const permissions: string[] = context?.permissions || [];
  const canManageCustomers = permissions.includes('create_invoice');

  const load = async () => {
    setLoading(true);
    setError('');
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

    setOrgId(nextOrgId);

    const [customersRes, statsRes] = await Promise.all([
      api.customersList(nextOrgId, { limit: 100, search: search.trim() || undefined }),
      api.customerStats(nextOrgId),
    ]);

    if (customersRes.error) {
      setError(customersRes.error || 'Unable to load customers.');
    } else {
      setCustomers((customersRes as any).data?.customers || []);
    }

    if (!statsRes.error) {
      setStats((statsRes as any).data);
    }

    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const handleSearch = () => { void load(); };

  const openEdit = (customer: Customer) => {
    setEditing(customer);
    setEditNotes(customer.notes || '');
    setSuccess('');
  };

  const saveNotes = async () => {
    if (!editing) return;
    setSaving(true);
    setSuccess('');
    const res = await api.customerUpdate(editing.id, { notes: editNotes });
    if (res.error) setError(typeof res.error === 'string' ? res.error : 'Unable to update customer.');
    else {
      setSuccess('Customer notes saved.');
      setEditing(null);
      await load();
    }
    setSaving(false);
  };

  const handleCreateCustomer = async () => {
    if (!orgId || !customerForm.name.trim()) {
      setError('Customer name is required.');
      return;
    }

    setSaving(true);
    setError('');
    const res = await api.customerCreate(orgId, {
      display_name: customerForm.name.trim(),
      email: customerForm.email.trim(),
      notes: customerForm.notes.trim(),
    });

    if (res.error) {
      setError(typeof res.error === 'string' ? res.error : 'Unable to create customer.');
    } else {
      setSuccess('Customer profile created.');
      setShowCustomerForm(false);
      setCustomerForm({ name: '', email: '', notes: '' });
      await load();
    }

    setSaving(false);
  };

  return (
    <ClientShell
      title="Customers"
      eyebrow="SL-021 Accounts receivable"
      organization={context?.organization}
      isPartner={context?.organization?.organization_type === 'partner'}
    >
      <div className="space-y-5">
        <div className="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50/80 p-4 text-sm text-sky-900">
          <Info className="mt-0.5 h-4 w-4 shrink-0" />
          <p>
            Add customer profiles for invoicing and AR tracking. Wallet balance shows open AR per customer (unpaid and partial invoices). Active status is maintained automatically from paid invoice activity.
          </p>
        </div>

        <div className="grid gap-4 sm:grid-cols-4">
          <Metric label="Total Customers" value={stats?.total_customers ?? 0} />
          <Metric label="Active" value={stats?.active_customers ?? 0} />
          <Metric label="Outstanding AR" value={money(stats?.outstanding_ar)} />
          <Metric label="Unpaid Invoices" value={stats?.unpaid_invoices ?? 0} />
        </div>

        <div className="flex flex-wrap items-center gap-3">
          <div className="min-w-[200px] flex-1">
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search by name, email, or notes…"
              onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
            />
          </div>
          <Button onClick={handleSearch} variant="secondary" size="sm">Search</Button>
          {canManageCustomers && (
            <Button
              size="sm"
              onClick={() => {
                setShowCustomerForm(true);
                setError('');
                setSuccess('');
              }}
            >
              <Plus className="h-4 w-4" />
              Add customer
            </Button>
          )}
          <WpLink to="/csv-imports">
            <Button variant="secondary" size="sm"><Upload className="h-4 w-4" />Import customers</Button>
          </WpLink>
          <WpLink to="/invoices">
            <Button size="sm">Create invoice</Button>
          </WpLink>
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {success && (
          <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">{success}</div>
        )}

        {error && !showCustomerForm && !editing && (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>
        )}

        <div className="orabooks-table-scroll glass-panel overflow-hidden">
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Customer</th>
                <th className="px-5 py-3 font-semibold">Status</th>
                <th className="px-5 py-3 font-semibold">Invoices</th>
                <th className="px-5 py-3 text-right font-semibold">Wallet (AR due)</th>
                <th className="px-5 py-3 text-right font-semibold">Paid</th>
                <th className="px-5 py-3 font-semibold">Last Paid</th>
                <th className="px-5 py-3 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={7} className="px-5 py-8 text-center text-slate-500">Loading customers…</td></tr>
              ) : customers.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-5 py-10 text-center">
                    <Users className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No customer records found.</p>
                    {canManageCustomers && (
                      <Button className="mt-4" size="sm" onClick={() => setShowCustomerForm(true)}>
                        <Plus className="h-4 w-4" />
                        Add your first customer
                      </Button>
                    )}
                  </td>
                </tr>
              ) : customers.map((customer) => (
                <tr key={customer.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3">
                    <p className="font-semibold text-ink">{customerLabel(customer)}</p>
                    {customer.email && customer.display_name && (
                      <p className="text-xs text-slate-500">{customer.email}</p>
                    )}
                    {customer.notes && <p className="text-xs text-slate-500">{customer.notes}</p>}
                  </td>
                  <td className="px-5 py-3">
                    <span className={`badge border ${Number(customer.is_active) === 1 ? 'border-success/20 bg-success/10 text-success' : 'border-slate-200 bg-slate-100 text-slate-600'}`}>
                      {Number(customer.is_active) === 1 ? 'active' : 'inactive'}
                    </span>
                  </td>
                  <td className="px-5 py-3 text-slate-600">{customer.invoice_count ?? 0}</td>
                  <td className="px-5 py-3 text-right font-semibold text-ink">{money(customer.wallet_balance)}</td>
                  <td className="px-5 py-3 text-right font-bold text-ink">{money(customer.total_paid)}</td>
                  <td className="px-5 py-3 text-slate-600">{customer.last_paid_invoice_date || '—'}</td>
                  <td className="px-5 py-3">
                    <div className="flex flex-wrap gap-1">
                      {canManageCustomers && (
                        <Button size="sm" variant="secondary" onClick={() => openEdit(customer)}>
                          <Pencil className="h-3.5 w-3.5" />
                          Notes
                        </Button>
                      )}
                      <WpLink to={`/attachments?resource_type=customer&resource_id=${customer.id}`}>
                        <Button size="sm" variant="secondary">
                          <Paperclip className="h-3.5 w-3.5" />
                          Files
                        </Button>
                      </WpLink>
                      <WpLink to="/invoices">
                        <Button size="sm" variant="secondary">
                          Invoices
                        </Button>
                      </WpLink>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {showCustomerForm && (
          <Modal title="Add customer" onClose={() => setShowCustomerForm(false)}>
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
            <div className="grid gap-4">
              <Field label="Name">
                <Input
                  value={customerForm.name}
                  onChange={(e) => setCustomerForm((p) => ({ ...p, name: e.target.value }))}
                  placeholder="Acme Corp"
                  required
                />
              </Field>
              <Field label="Email">
                <Input
                  type="email"
                  value={customerForm.email}
                  onChange={(e) => setCustomerForm((p) => ({ ...p, email: e.target.value }))}
                  placeholder="billing@acme.com"
                />
              </Field>
              <Field label="Notes">
                <Input
                  value={customerForm.notes}
                  onChange={(e) => setCustomerForm((p) => ({ ...p, notes: e.target.value }))}
                  placeholder="Internal notes (optional)"
                />
              </Field>
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setShowCustomerForm(false)}>Cancel</Button>
              <Button onClick={handleCreateCustomer} loading={saving} disabled={!customerForm.name.trim()}>
                Create customer
              </Button>
            </div>
          </Modal>
        )}

        {editing && (
          <div className="glass-panel space-y-4 p-5">
            <h3 className="text-lg font-bold text-ink">Edit customer notes</h3>
            <p className="text-sm text-slate-600">{customerLabel(editing)}</p>
            <Input
              label="Notes"
              value={editNotes}
              onChange={(e) => setEditNotes(e.target.value)}
              placeholder="Internal notes about this customer"
            />
            <div className="flex gap-2">
              <Button onClick={saveNotes} loading={saving}>Save notes</Button>
              <Button variant="secondary" onClick={() => setEditing(null)}>Cancel</Button>
            </div>
          </div>
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

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="block space-y-1.5">
      <span className="text-sm font-medium text-slate-700">{label}</span>
      {children}
    </label>
  );
}

function Metric({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="glass-panel p-4">
      <p className="text-xs uppercase tracking-wide text-slate-500">{label}</p>
      <p className="mt-1 text-2xl font-bold text-ink">{value}</p>
    </div>
  );
}

function money(value?: string | number) {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(Number(value || 0));
}
