import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import WpLink from '../components/WpLink';
import { RefreshCw, ShieldCheck } from 'lucide-react';

export default function ProfilePage() {
  const [context, setContext] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = async () => {
    setLoading(true);
    setError('');
    const res = await api.frontendContext();
    if (res.error) setError(res.error || 'Unable to load profile.');
    else setContext((res as any).data);
    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const org = context?.organization;
  const isPartner = org?.organization_type === 'partner' || context?.user?.is_partner;
  const twoFaEnabled = Boolean(context?.user?.is_2fa_enabled);
  const needs2faSetup = Boolean(context?.security?.needs_2fa_setup);
  const remainingBackupCodes = Number(context?.user?.remaining_backup_codes ?? 0);
  const formatRole = (value?: string) => {
    if (!value) return '—';
    return value.charAt(0).toUpperCase() + value.slice(1);
  };

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
                <ProfileRow label="Role" value={formatRole(context?.role)} />
                <ProfileRow label="Email Verified" value={context?.user?.is_email_verified ? 'Yes' : 'No'} />
                <ProfileRow label="2FA Enabled" value={twoFaEnabled ? 'Yes' : 'No'} />
                {twoFaEnabled && (
                  <ProfileRow label="Backup codes left" value={remainingBackupCodes} />
                )}
              </div>
              <p className="mt-4 rounded-xl border border-primary/20 bg-primary/10 p-3 text-sm font-medium text-primary-dark">
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
                <ProfileRow label="Requires 2FA" value={org?.require_2fa ? 'Yes' : 'No'} />
              </div>
            </section>

            <section id="security-2fa" className="glass-panel p-5 lg:col-span-3">
              <div className="flex items-start gap-3">
                <div className="rounded-xl bg-primary/10 p-2.5 text-primary">
                  <ShieldCheck className="h-5 w-5" />
                </div>
                <div className="flex-1">
                  <h2 className="font-bold text-ink">Security</h2>
                  <p className="mt-1 text-sm text-slate-600">
                    Manage two-factor authentication, backup codes, and organization 2FA policy.
                  </p>
                  {needs2faSetup && (
                    <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                      Your organization requires two-factor authentication before you can use OraBooks features.
                    </p>
                  )}
                  <WpLink
                    to="/security/2fa"
                    className="mt-4 inline-flex items-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark"
                  >
                    {twoFaEnabled ? 'Manage 2FA settings' : 'Set up two-factor authentication'}
                  </WpLink>
                </div>
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
