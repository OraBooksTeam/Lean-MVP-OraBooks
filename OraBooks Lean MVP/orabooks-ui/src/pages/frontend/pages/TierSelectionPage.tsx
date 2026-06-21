import { useEffect, useState, type FormEvent } from 'react';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '@/pages/frontend/api';
import { getTenantDomainSuffix } from '@/lib/utils';
import {
  clearTierSelectionToken,
  getNetworkAuthUrl,
  getTierSelectionToken,
  redirectAfterAuth,
  storeTierSelectionToken,
} from '../lib/auth-routing';

const REGIONS = [
  { id: 'us-east', label: 'US East' },
  { id: 'eu-west-1', label: 'EU West' },
  { id: 'ap-southeast-1', label: 'Asia Pacific' },
];

export default function TierSelectionPage() {
  const tenantDomainSuffix = getTenantDomainSuffix();
  const [tier, setTier] = useState<'free' | 'premium' | 'enterprise'>('free');
  const [subdomain, setSubdomain] = useState('');
  const [region, setRegion] = useState('us-east');
  const [checking, setChecking] = useState(false);
  const [available, setAvailable] = useState<boolean | null>(null);
  const [msg, setMsg] = useState('');
  const [loading, setLoading] = useState(false);
  const [confirmedPermanent, setConfirmedPermanent] = useState(false);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const token = params.get('tier_selection_token');
    if (token) {
      storeTierSelectionToken(token);
      params.delete('tier_selection_token');
      const qs = params.toString();
      window.history.replaceState(null, '', `${window.location.pathname}${qs ? `?${qs}` : ''}`);
    }

    if (!getTierSelectionToken()) {
      setMsg('Your tier-selection session expired. Please log in again.');
    }
  }, []);

  const checkSubdomain = async () => {
    if (!subdomain) return;
    setChecking(true);
    setAvailable(null);
    const res = await api.post('orabooks_check_subdomain', { subdomain });
    if (res.error) {
      const message = typeof res.error === 'string' ? res.error : 'Unable to check subdomain availability.';
      if (message.toLowerCase().includes('too many')) {
        setAvailable(null);
        setMsg('Too many availability checks. Please wait a moment before trying again.');
      } else {
        setAvailable(false);
        setMsg(message);
      }
    } else {
      setAvailable((res as any).data?.available ?? false);
      setMsg((res as any).data?.message || '');
    }
    setChecking(false);
  };

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    const tierToken = getTierSelectionToken();
    if (!tierToken) {
      setMsg('Your tier-selection session expired. Please log in again.');
      return;
    }
    if (available !== true) {
      setMsg('Please check subdomain availability and choose an available name.');
      return;
    }
    if (!confirmedPermanent) {
      setMsg('Please confirm that you understand your subdomain cannot be changed later.');
      return;
    }
    if (tier === 'enterprise' && !region) {
      setMsg('Please select a data residency region for Enterprise.');
      return;
    }

    setLoading(true);
    const payload: Record<string, string> = { tier, subdomain, tier_selection_token: tierToken };
    if (tier === 'enterprise') {
      payload.region = region;
    }

    const res = await api.post('orabooks_select_tier', payload);
    if (!res.error) {
      clearTierSelectionToken();
      redirectAfterAuth((res as any).data || {});
    } else {
      const message = typeof res.error === 'string' ? res.error : 'Failed';
      if (message.toLowerCase().includes('pending team invitation')) {
        window.location.replace(getNetworkAuthUrl('accept-invite'));
        return;
      }
      setMsg(message);
    }
    setLoading(false);
  };

  return (
    <div className="brand-auth-bg flex min-h-screen items-center justify-center px-4 py-12">
      <div className="glass-panel w-full max-w-2xl overflow-hidden">
        <div className="p-8">
        <h2 className="text-center text-2xl font-bold text-ink">Choose Your Plan</h2>
        <p className="mt-2 text-center text-sm text-slate-600">Select a tier and set up your organization subdomain.</p>
        <form onSubmit={submit} className="mt-8 space-y-6">
          <div className="grid gap-4 md:grid-cols-3">
            {(['free', 'premium', 'enterprise'] as const).map((plan) => (
              <button
                key={plan}
                type="button"
                onClick={() => setTier(plan)}
                className={cn_tier(tier === plan)}
              >
                <h3 className="text-sm font-bold uppercase tracking-wide">{plan}</h3>
                <p className={`mt-1 text-xs ${tier === plan ? 'text-white/85' : 'text-slate-500'}`}>
                  {plan === 'free' && 'Basic features for small businesses'}
                  {plan === 'premium' && 'Advanced features for growing businesses'}
                  {plan === 'enterprise' && 'Full features for large organizations'}
                </p>
              </button>
            ))}
          </div>

          {tier === 'enterprise' && (
            <div>
              <label className="mb-1.5 block text-sm font-medium text-slate-700">Data residency region</label>
              <select
                value={region}
                onChange={(e) => setRegion(e.target.value)}
                className="w-full rounded-lg border border-border bg-white px-3 py-2 text-sm"
                required
              >
                {REGIONS.map((r) => (
                  <option key={r.id} value={r.id}>{r.label}</option>
                ))}
              </select>
            </div>
          )}

          <div>
            <label className="mb-1.5 block text-sm font-medium text-slate-700">Choose subdomain</label>
            <div className="flex gap-2">
              <Input value={subdomain} onChange={(e) => { setSubdomain(e.target.value); setAvailable(null); }} placeholder="mycompany" required className="flex-1" />
              <Button type="button" variant="secondary" onClick={checkSubdomain} loading={checking} className="whitespace-nowrap">
                Check availability
              </Button>
            </div>
            <p className="mt-1.5 text-xs text-slate-500">{tenantDomainSuffix}</p>
            {available !== null && (
              <p className={`mt-1.5 text-xs font-medium ${available ? 'text-success' : 'text-danger'}`}>
                {available ? '✓ Available' : `✗ ${msg || 'Not available'}`}
              </p>
            )}
          </div>

          <label className="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
            <input
              type="checkbox"
              checked={confirmedPermanent}
              onChange={(e) => setConfirmedPermanent(e.target.checked)}
              className="mt-1"
            />
            <span>I understand my subdomain is permanent and cannot be changed after setup.</span>
          </label>

          {msg && (available === false || available === null) && (
            <p className="text-sm text-danger">{msg}</p>
          )}
          <Button type="submit" loading={loading} className="w-full">Continue</Button>
        </form>
        </div>
      </div>
    </div>
  );
}

function cn_tier(active: boolean) {
  return [
    'rounded-xl border-2 p-4 text-center transition-all duration-200',
    active
      ? 'border-primary bg-primary text-white shadow-md'
      : 'border-border bg-white hover:border-slate-300 hover:shadow-sm',
  ].join(' ');
}
