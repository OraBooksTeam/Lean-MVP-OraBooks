import { useState, type FormEvent } from 'react';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '@/pages/frontend/api';
import { getTenantDomainSuffix } from '@/lib/utils';

export default function TierSelectionPage() {
  const tenantDomainSuffix = getTenantDomainSuffix();
  const [tier, setTier] = useState<'free' | 'premium' | 'enterprise'>('free');
  const [subdomain, setSubdomain] = useState('');
  const [checking, setChecking] = useState(false);
  const [available, setAvailable] = useState<boolean | null>(null);
  const [msg, setMsg] = useState('');
  const [loading, setLoading] = useState(false);

  const checkSubdomain = async () => {
    if (!subdomain) return;
    setChecking(true);
    setAvailable(null);
    const res = await api.post('orabooks_check_subdomain', { subdomain });
    if (!res.error) {
      setAvailable((res as any).data?.available ?? false);
      setMsg((res as any).data?.message || '');
    }
    setChecking(false);
  };

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    if (available === false) return setMsg('Please choose an available subdomain.');
    setLoading(true);
    const res = await api.post('orabooks_select_tier', { tier, subdomain });
    if (!res.error) {
      const redirectTo = String((res as any).data?.redirect_to || '/dashboard/');
      window.location.href = redirectTo.startsWith('http')
        ? redirectTo
        : (redirectTo.startsWith('/') ? redirectTo : `/${redirectTo}`);
    } else setMsg(typeof res.error === 'string' ? res.error : 'Failed');
    setLoading(false);
  };

  return (
    <div className="brand-auth-bg flex min-h-screen items-center justify-center px-4 py-12">
      <div className="glass-panel w-full max-w-2xl overflow-hidden">
        <div className="brand-accent-bar h-1.5" />
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

          <div>
            <label className="mb-1.5 block text-sm font-medium text-slate-700">Choose subdomain</label>
            <div className="flex gap-2">
              <Input value={subdomain} onChange={(e) => setSubdomain(e.target.value)} placeholder="mycompany" required className="flex-1" />
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

          {msg && !available && <p className="text-sm text-danger">{msg}</p>}
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
