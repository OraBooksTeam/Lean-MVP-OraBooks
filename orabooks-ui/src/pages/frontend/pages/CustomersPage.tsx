import { useEffect, useState, type ReactNode } from 'react';
import WpLink from '../components/WpLink';

import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { Info, Paperclip, Pencil, Plus, RefreshCw, Upload, Users } from 'lucide-react';

type Customer = {
  id: number;
  customer_code?: string | null;
  display_name?: string | null;
  email?: string;
  mobile?: string | null;
  phone?: string | null;
  gstin?: string | null;
  tax_number?: string | null;
  opening_balance?: string | number;
  country_id?: string | null;
  state_id?: string | null;
  city?: string | null;
  postcode?: string | null;
  address?: string | null;
  location_link?: string | null;
  ship_country_id?: string | null;
  ship_state_id?: string | null;
  ship_city?: string | null;
  ship_postcode?: string | null;
  ship_address?: string | null;
  price_level_type?: 'Increase' | 'Decrease' | string | null;
  price_level?: string | number;
  is_active?: number;
  invoice_count?: number;
  total_paid?: string | number;
  wallet_balance?: string | number;
  credit_balance?: string | number;
  credit_limit?: string | number;
  credit_hold?: number;
  auto_apply_credit?: number;
  payment_terms?: number;
  default_currency?: string;
  last_paid_invoice_date?: string | null;
  notes?: string | null;
};

type CustomerFormState = {
  customer_code: string;
  name: string;
  email: string;
  mobile: string;
  phone: string;
  gstin: string;
  tax_number: string;
  opening_balance: string;
  country_id: string;
  state_id: string;
  city: string;
  postcode: string;
  address: string;
  location_link: string;
  ship_country_id: string;
  ship_state_id: string;
  ship_city: string;
  ship_postcode: string;
  ship_address: string;
  price_level_type: 'Increase' | 'Decrease';
  price_level: string;
  notes: string;
  payment_terms: string;
  default_currency: string;
  credit_limit: string;
  credit_hold: boolean;
  auto_apply_credit: boolean;
};

type CountryStateOption = {
  name: string;
  states: string[];
};

const selectClassName = 'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';

const emptyCustomerForm = (): CustomerFormState => ({
  customer_code: '',
  name: '',
  email: '',
  mobile: '',
  phone: '',
  gstin: '',
  tax_number: '',
  opening_balance: '0',
  country_id: '',
  state_id: '',
  city: '',
  postcode: '',
  address: '',
  location_link: '',
  ship_country_id: '',
  ship_state_id: '',
  ship_city: '',
  ship_postcode: '',
  ship_address: '',
  price_level_type: 'Increase',
  price_level: '0',
  notes: '',
  payment_terms: '30',
  default_currency: 'USD',
  credit_limit: '0',
  credit_hold: false,
  auto_apply_credit: true,
});

function customerLabel(customer: Pick<Customer, 'id' | 'display_name' | 'email'>) {
  return customer.display_name?.trim() || customer.email || `Customer #${customer.id}`;
}

function customerToForm(customer: Customer): CustomerFormState {
  return {
    customer_code: customer.customer_code || '',
    name: customer.display_name?.trim() || '',
    email: customer.email || '',
    mobile: customer.mobile || '',
    phone: customer.phone || '',
    gstin: customer.gstin || '',
    tax_number: customer.tax_number || '',
    opening_balance: String(customer.opening_balance ?? 0),
    country_id: customer.country_id || '',
    state_id: customer.state_id || '',
    city: customer.city || '',
    postcode: customer.postcode || '',
    address: customer.address || '',
    location_link: customer.location_link || '',
    ship_country_id: customer.ship_country_id || '',
    ship_state_id: customer.ship_state_id || '',
    ship_city: customer.ship_city || '',
    ship_postcode: customer.ship_postcode || '',
    ship_address: customer.ship_address || '',
    price_level_type: customer.price_level_type === 'Decrease' ? 'Decrease' : 'Increase',
    price_level: String(customer.price_level ?? 0),
    notes: customer.notes || '',
    payment_terms: String(customer.payment_terms ?? 30),
    default_currency: customer.default_currency || 'USD',
    credit_limit: String(customer.credit_limit ?? 0),
    credit_hold: Number(customer.credit_hold) === 1,
    auto_apply_credit: Number(customer.auto_apply_credit ?? 1) === 1,
  };
}

function customerFormPayload(form: CustomerFormState) {
  return {
    display_name: form.name.trim(),
    email: form.email.trim(),
    mobile: form.mobile.trim(),
    phone: form.phone.trim(),
    gstin: form.gstin.trim(),
    tax_number: form.tax_number.trim(),
    opening_balance: parseFloat(form.opening_balance) || 0,
    country_id: form.country_id.trim(),
    state_id: form.state_id.trim(),
    city: form.city.trim(),
    postcode: form.postcode.trim(),
    address: form.address.trim(),
    location_link: form.location_link.trim(),
    ship_country_id: form.ship_country_id.trim(),
    ship_state_id: form.ship_state_id.trim(),
    ship_city: form.ship_city.trim(),
    ship_postcode: form.ship_postcode.trim(),
    ship_address: form.ship_address.trim(),
    price_level_type: form.price_level_type,
    price_level: parseFloat(form.price_level) || 0,
    notes: form.notes.trim(),
    payment_terms: parseInt(form.payment_terms, 10) || 30,
    default_currency: form.default_currency.trim() || 'USD',
    credit_limit: parseFloat(form.credit_limit) || 0,
    credit_hold: form.credit_hold ? 1 : 0,
    auto_apply_credit: form.auto_apply_credit ? 1 : 0,
  };
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
  const [editForm, setEditForm] = useState<CustomerFormState>(emptyCustomerForm());
  const [showCustomerForm, setShowCustomerForm] = useState(false);
  const [customerForm, setCustomerForm] = useState<CustomerFormState>(emptyCustomerForm());
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
    setEditForm(customerToForm(customer));
    setSuccess('');
    setError('');
  };

  const saveCustomer = async () => {
    if (!editing) return;
    if (!editForm.name.trim()) {
      setError('Customer name is required.');
      return;
    }

    setSaving(true);
    setSuccess('');
    const res = await api.customerUpdate(editing.id, customerFormPayload(editForm));
    if (res.error) setError(typeof res.error === 'string' ? res.error : 'Unable to update customer.');
    else {
      setSuccess('Customer profile saved.');
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
    const res = await api.customerCreate(orgId, customerFormPayload(customerForm));

    if (res.error) {
      setError(typeof res.error === 'string' ? res.error : 'Unable to create customer.');
    } else {
      setSuccess('Customer profile created.');
      setShowCustomerForm(false);
      setCustomerForm(emptyCustomerForm());
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
            Manage AR customer profiles with payment terms, credit limits, and credit hold. Wallet balance shows open AR; credit balance stores overpayments per SL-021.
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
                setCustomerForm(emptyCustomerForm());
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
                <th className="px-5 py-3 font-semibold">Terms</th>
                <th className="px-5 py-3 font-semibold">Status</th>
                <th className="px-5 py-3 text-right font-semibold">AR due</th>
                <th className="px-5 py-3 text-right font-semibold">Credit</th>
                <th className="px-5 py-3 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={6} className="px-5 py-8 text-center text-slate-500">Loading customers…</td></tr>
              ) : customers.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-5 py-10 text-center">
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
                    {customer.customer_code && <p className="text-xs font-medium text-slate-500">{customer.customer_code}</p>}
                    {customer.email && customer.display_name && (
                      <p className="text-xs text-slate-500">{customer.email}</p>
                    )}
                    {customer.mobile && <p className="text-xs text-slate-500">{customer.mobile}</p>}
                    {Number(customer.credit_hold) === 1 && (
                      <span className="mt-1 inline-block rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Credit hold</span>
                    )}
                  </td>
                  <td className="px-5 py-3 text-slate-600">{customer.payment_terms ?? 30} days</td>
                  <td className="px-5 py-3">
                    <span className={`badge border ${Number(customer.is_active) === 1 ? 'border-success/20 bg-success/10 text-success' : 'border-slate-200 bg-slate-100 text-slate-600'}`}>
                      {Number(customer.is_active) === 1 ? 'active' : 'inactive'}
                    </span>
                  </td>
                  <td className="px-5 py-3 text-right font-semibold text-ink">{money(customer.wallet_balance, customer.default_currency)}</td>
                  <td className="px-5 py-3 text-right text-slate-600">{money(customer.credit_balance, customer.default_currency)}</td>
                  <td className="px-5 py-3">
                    <div className="flex flex-wrap gap-1">
                      {canManageCustomers && (
                        <Button size="sm" variant="secondary" onClick={() => openEdit(customer)}>
                          <Pencil className="h-3.5 w-3.5" />
                          Edit
                        </Button>
                      )}
                      <WpLink to={`/invoices?customer_id=${customer.id}`}>
                        <Button size="sm" variant="secondary">Invoices</Button>
                      </WpLink>
                      <WpLink to={`/attachments?resource_type=customer&resource_id=${customer.id}`}>
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

        {showCustomerForm && (
          <Modal title="Add customer" onClose={() => setShowCustomerForm(false)}>
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
            <CustomerFields form={customerForm} onChange={setCustomerForm} />
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setShowCustomerForm(false)}>Cancel</Button>
              <Button onClick={handleCreateCustomer} loading={saving} disabled={!customerForm.name.trim()}>
                Create customer
              </Button>
            </div>
          </Modal>
        )}

        {editing && (
          <Modal title="Edit customer" onClose={() => setEditing(null)}>
            <p className="mb-4 text-sm text-slate-600">{customerLabel(editing)}</p>
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
            <CustomerFields form={editForm} onChange={setEditForm} />
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setEditing(null)}>Cancel</Button>
              <Button onClick={saveCustomer} loading={saving} disabled={!editForm.name.trim()}>Save changes</Button>
            </div>
          </Modal>
        )}
      </div>
    </ClientShell>
  );
}

function CustomerFields({
  form,
  onChange,
}: {
  form: CustomerFormState;
  onChange: (next: CustomerFormState) => void;
}) {
  const set = (patch: Partial<CustomerFormState>) => onChange({ ...form, ...patch });
  const [countries, setCountries] = useState<CountryStateOption[]>([]);
  const [countriesLoading, setCountriesLoading] = useState(true);
  const [countriesError, setCountriesError] = useState('');
  const billingStates = getStatesForCountry(countries, form.country_id);
  const shippingStates = getStatesForCountry(countries, form.ship_country_id);

  useEffect(() => {
    let cancelled = false;

    async function loadCountries() {
      setCountriesLoading(true);
      setCountriesError('');

      try {
        const res = await fetch('https://countriesnow.space/api/v0.1/countries/states');
        const json = await res.json();
        const nextCountries = Array.isArray(json?.data)
          ? json.data
            .map((country: any) => ({
              name: String(country?.name || '').trim(),
              states: Array.isArray(country?.states)
                ? country.states.map((state: any) => String(state?.name || '').trim()).filter(Boolean)
                : [],
            }))
            .filter((country: CountryStateOption) => country.name)
            .sort((a: CountryStateOption, b: CountryStateOption) => a.name.localeCompare(b.name))
          : [];

        if (!cancelled) {
          setCountries(nextCountries);
          setCountriesError(nextCountries.length ? '' : 'Country list is unavailable right now.');
        }
      } catch {
        if (!cancelled) {
          setCountries([]);
          setCountriesError('Country list is unavailable right now.');
        }
      } finally {
        if (!cancelled) {
          setCountriesLoading(false);
        }
      }
    }

    void loadCountries();

    return () => {
      cancelled = true;
    };
  }, []);

  const copyBillingToShipping = () => {
    set({
      ship_country_id: form.country_id,
      ship_state_id: form.state_id,
      ship_city: form.city,
      ship_postcode: form.postcode,
      ship_address: form.address,
    });
  };

  return (
    <div className="grid gap-6 lg:grid-cols-2">
      <section className="space-y-4 rounded-2xl border border-border bg-slate-50/60 p-4">
        <h4 className="text-sm font-bold uppercase tracking-wide text-ink">Basic Information</h4>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Customer Code">
            <div className="rounded-lg border border-border bg-slate-100 px-3.5 py-2.5 text-sm text-slate-600">
              {form.customer_code || 'Auto-generated from CUS-000001'}
            </div>
          </Field>
          <Field label="Customer Name">
            <Input value={form.name} onChange={(e) => set({ name: e.target.value })} placeholder="Acme Corp" required />
          </Field>
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Mobile">
            <Input value={form.mobile} onChange={(e) => set({ mobile: e.target.value })} />
          </Field>
          <Field label="Phone">
            <Input value={form.phone} onChange={(e) => set({ phone: e.target.value })} />
          </Field>
        </div>
        <Field label="Email">
          <Input type="email" value={form.email} onChange={(e) => set({ email: e.target.value })} placeholder="billing@acme.com" />
        </Field>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="GST Number">
            <Input value={form.gstin} onChange={(e) => set({ gstin: e.target.value })} />
          </Field>
          <Field label="Tax Number">
            <Input value={form.tax_number} onChange={(e) => set({ tax_number: e.target.value })} />
          </Field>
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Credit Limit">
            <Input type="number" min="0" step="0.01" value={form.credit_limit} onChange={(e) => set({ credit_limit: e.target.value })} />
          </Field>
          <Field label="Opening Balance">
            <Input type="number" step="0.01" value={form.opening_balance} onChange={(e) => set({ opening_balance: e.target.value })} />
          </Field>
        </div>
      </section>

      <section className="space-y-4 rounded-2xl border border-border bg-slate-50/60 p-4">
        <h4 className="text-sm font-bold uppercase tracking-wide text-ink">Address Details</h4>
        {countriesError && <p className="text-xs text-amber-700">{countriesError}</p>}
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Country">
            <CountrySelect
              value={form.country_id}
              countries={countries}
              loading={countriesLoading}
              onChange={(country) => set({ country_id: country, state_id: '' })}
            />
          </Field>
          <Field label="State">
            <StateSelect
              value={form.state_id}
              country={form.country_id}
              states={billingStates}
              onChange={(state) => set({ state_id: state })}
            />
          </Field>
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="City">
            <Input value={form.city} onChange={(e) => set({ city: e.target.value })} />
          </Field>
          <Field label="Postcode">
            <Input value={form.postcode} onChange={(e) => set({ postcode: e.target.value })} />
          </Field>
        </div>
        <Field label="Address">
          <textarea
            value={form.address}
            onChange={(e) => set({ address: e.target.value })}
            rows={3}
            className="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
          />
        </Field>
        <Field label="Location Link">
          <Input type="url" value={form.location_link} onChange={(e) => set({ location_link: e.target.value })} placeholder="https://maps.google.com/..." />
        </Field>
      </section>

      <section className="space-y-4 rounded-2xl border border-border bg-slate-50/60 p-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h4 className="text-sm font-bold uppercase tracking-wide text-ink">Shipping Address</h4>
          <Button type="button" size="sm" variant="secondary" onClick={copyBillingToShipping}>Same as billing</Button>
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Country">
            <CountrySelect
              value={form.ship_country_id}
              countries={countries}
              loading={countriesLoading}
              onChange={(country) => set({ ship_country_id: country, ship_state_id: '' })}
            />
          </Field>
          <Field label="State">
            <StateSelect
              value={form.ship_state_id}
              country={form.ship_country_id}
              states={shippingStates}
              onChange={(state) => set({ ship_state_id: state })}
            />
          </Field>
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="City">
            <Input value={form.ship_city} onChange={(e) => set({ ship_city: e.target.value })} />
          </Field>
          <Field label="Postcode">
            <Input value={form.ship_postcode} onChange={(e) => set({ ship_postcode: e.target.value })} />
          </Field>
        </div>
        <Field label="Address">
          <textarea
            value={form.ship_address}
            onChange={(e) => set({ ship_address: e.target.value })}
            rows={3}
            className="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
          />
        </Field>
      </section>

      <section className="space-y-4 rounded-2xl border border-border bg-slate-50/60 p-4">
        <h4 className="text-sm font-bold uppercase tracking-wide text-ink">Advanced Settings</h4>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Price Level Type">
            <select
              value={form.price_level_type}
              onChange={(e) => set({ price_level_type: e.target.value === 'Decrease' ? 'Decrease' : 'Increase' })}
              className={selectClassName}
            >
              <option value="Increase">Increase</option>
              <option value="Decrease">Decrease</option>
            </select>
          </Field>
          <Field label="Price Level (%)">
            <Input type="number" step="0.01" value={form.price_level} onChange={(e) => set({ price_level: e.target.value })} />
          </Field>
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Payment Terms (days)">
            <Input type="number" min="0" value={form.payment_terms} onChange={(e) => set({ payment_terms: e.target.value })} />
          </Field>
          <Field label="Default Currency">
            <Input value={form.default_currency} onChange={(e) => set({ default_currency: e.target.value.toUpperCase() })} maxLength={3} />
          </Field>
        </div>
        <div className="flex flex-wrap gap-6">
          <label className="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" checked={form.credit_hold} onChange={(e) => set({ credit_hold: e.target.checked })} />
            Credit hold
          </label>
          <label className="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" checked={form.auto_apply_credit} onChange={(e) => set({ auto_apply_credit: e.target.checked })} />
            Auto-apply credit balance
          </label>
        </div>
        <Field label="Notes">
          <Input value={form.notes} onChange={(e) => set({ notes: e.target.value })} placeholder="Internal notes (optional)" />
        </Field>
      </section>
    </div>
  );
}

function getStatesForCountry(countries: CountryStateOption[], countryName: string) {
  if (!countryName) return [];
  return countries.find((country) => country.name === countryName)?.states || [];
}

function CountrySelect({
  value,
  countries,
  loading,
  onChange,
}: {
  value: string;
  countries: CountryStateOption[];
  loading: boolean;
  onChange: (value: string) => void;
}) {
  const hasSavedValue = value && !countries.some((country) => country.name === value);

  return (
    <select value={value} onChange={(e) => onChange(e.target.value)} className={selectClassName} disabled={loading && countries.length === 0}>
      <option value="">{loading ? 'Loading countries...' : 'Select Country'}</option>
      {hasSavedValue && <option value={value}>{value}</option>}
      {countries.map((country) => (
        <option key={country.name} value={country.name}>{country.name}</option>
      ))}
    </select>
  );
}

function StateSelect({
  value,
  country,
  states,
  onChange,
}: {
  value: string;
  country: string;
  states: string[];
  onChange: (value: string) => void;
}) {
  const hasSavedValue = value && !states.includes(value);
  const disabled = !country || states.length === 0;

  return (
    <select value={value} onChange={(e) => onChange(e.target.value)} className={selectClassName} disabled={disabled && !hasSavedValue}>
      <option value="">{country ? 'Select State' : 'Select Country First'}</option>
      {hasSavedValue && <option value={value}>{value}</option>}
      {states.map((state) => (
        <option key={state} value={state}>{state}</option>
      ))}
      {country && states.length === 0 && !hasSavedValue && <option value="" disabled>No states found</option>}
    </select>
  );
}

function Modal({ title, children, onClose }: { title: string; children: ReactNode; onClose: () => void }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" onClick={onClose}>
      <div className="max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-2xl border border-border bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
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

function money(value?: string | number, currency = 'USD') {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(Number(value || 0));
}
