import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { RefreshCw, ShieldCheck } from 'lucide-react';

export default function ProfilePage() {
  const [context, setContext] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = async () => {
    setLoading(true);
    setError('');
    const res = await api.frontendContext();
    if (res.error) setError((res as any).message || 'Unable to load profile.');
    else setContext((res as any).data);
    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const org = context?.organization;
  const isPartner = org?.organization_type === 'partner' || context?.user?.is_partner;

  return (
    <ClientShell title="Profile" eyebrow="User and role" organization={org} isPartner={isPartner}>
      <div className="space-y-5">
        <div className="flex justify-end">
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {error && <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>}

        {loading ? (
          <div className="glass-panel p-8 text-sm text-slate-500">Loading profile...</div>
        ) : (
          <div className="grid gap-5 lg:grid-cols-3">
            <section className="glass-panel p-5 lg:col-span-2">
              <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">Your Details</h2>
              <div className="mt-4 space-y-3">
                <ProfileRow label="Email" value={context?.user?.email || '—'} />
                <ProfileRow label="Role" value={context?.role || '—'} />
                <ProfileRow label="Email Verified" value={context?.user?.is_email_verified ? 'Yes' : 'No'} />
                <ProfileRow label="2FA Enabled" value={context?.user?.is_2fa_enabled ? 'Yes' : 'No'} />
              </div>
              <p className="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-3 text-sm font-medium text-sky-800">
                Contact organization owner to change your role.
              </p>
            </section>

            <section className="glass-panel p-5">
              <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">Organization</h2>
              <div className="mt-4 space-y-3">
                <ProfileRow label="Name" value={org?.name || '—'} />
                <ProfileRow label="Subdomain" value={org?.subdomain || '—'} />
                <ProfileRow label="Status" value={org?.status || '—'} />
                <ProfileRow label="Type" value={org?.organization_type || '—'} />
                {!isPartner && <ProfileRow label="Plan" value={org?.tier || '—'} />}
              </div>
            </section>

            {isPartner && (
              <section className="glass-panel p-5 lg:col-span-3">
                <div className="flex items-start gap-3">
                  <div className="rounded-xl bg-primary/10 p-2.5 text-primary">
                    <ShieldCheck className="h-5 w-5" />
                  </div>
                  <div>
                    <h2 className="font-bold text-ink">Partner Account (Commission)</h2>
                    <p className="mt-1 text-sm text-slate-600">
                      You earn commissions from qualified customers attributed to your Partner Code. No accounting features.
                    </p>
                    {org?.name && (
                      <p className="mt-3 text-sm text-slate-600">
                        Organization: <span className="font-semibold text-ink">{org.name}</span>. Your organization's registered name is shared by all members.
                      </p>
                    )}
                  </div>
                </div>
              </section>
            )}
          </div>
        )}
      </div>
    </ClientShell>
  );
}

function ProfileRow({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="flex items-center justify-between gap-4 rounded-xl border border-border bg-white px-4 py-3">
      <span className="text-sm text-slate-500">{label}</span>
      <span className="text-right text-sm font-bold text-ink">{value}</span>
    </div>
  );
}
