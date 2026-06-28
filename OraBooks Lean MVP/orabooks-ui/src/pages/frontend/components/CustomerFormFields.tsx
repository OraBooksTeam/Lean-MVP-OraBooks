import { useEffect, useState, type ReactNode } from 'react';

import Button from '@/components/Button';
import Input from '@/components/Input';

export type CustomerFormState = {
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

export const emptyCustomerForm = (defaultCurrency = 'USD'): CustomerFormState => ({
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
  default_currency: defaultCurrency,
  credit_limit: '0',
  credit_hold: false,
  auto_apply_credit: true,
});

export function customerFormPayload(form: CustomerFormState) {
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

function getStatesForCountry(countries: CountryStateOption[], countryName: string) {
  if (!countryName) return [];
  return countries.find((country) => country.name === countryName)?.states || [];
}

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="block space-y-1.5">
      <span className="text-sm font-medium text-slate-700">{label}</span>
      {children}
    </label>
  );
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

export default function CustomerFormFields({
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
