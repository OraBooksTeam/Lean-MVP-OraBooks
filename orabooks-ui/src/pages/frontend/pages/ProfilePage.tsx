import { useEffect, useState, type FormEvent } from 'react';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { RefreshCw, ShieldCheck } from 'lucide-react';

export default function ProfilePage() {
  const [context, setContext] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [securityMsg, setSecurityMsg] = useState('');
  const [securityError, setSecurityError] = useState('');
  const [setupLoading, setSetupLoading] = useState(false);
  const [verifyLoading, setVerifyLoading] = useState(false);
  const [qrUrl, setQrUrl] = useState('');
  const [totpSecret, setTotpSecret] = useState('');
  const [backupCodes, setBackupCodes] = useState<string[]>([]);
  const [otp, setOtp] = useState('');
  const setupActive = Boolean(qrUrl || totpSecret);

  const load = async () => {
    setLoading(true);
    setError('');
    const res = await api.frontendContext();
    if (res.error) setError(res.error || 'Unable to load profile.');
    else setContext((res as any).data);
    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const start2faSetup = async () => {
    setSecurityError('');
    setSecurityMsg('');
    setSetupLoading(true);
    const res = await api.setup2fa();
    if (res.error) {
      setSecurityError(typeof res.error === 'string' ? res.error : 'Unable to start 2FA setup.');
    } else {
      setQrUrl(String((res as any).data?.qr_code_url || ''));
      setBackupCodes(Array.isArray((res as any).data?.backup_codes) ? (res as any).data.backup_codes : []);
      setSecurityMsg('Scan the QR code with your authenticator app, then enter the 6-digit code to enable 2FA.');
    }
    setSetupLoading(false);
  };

  const verify2faSetup = async (e: FormEvent) => {
    e.preventDefault();
    setSecurityError('');
    setVerifyLoading(true);
    const res = await api.verify2faSetup(otp);
    if (res.error) {
      setSecurityError(typeof res.error === 'string' ? res.error : 'Invalid code.');
    } else {
      const confirmedCodes = Array.isArray((res as any).data?.backup_codes)
        ? (res as any).data.backup_codes
        : backupCodes;
      setSecurityMsg('Two-factor authentication is now enabled. Save your backup codes in a secure place.');
      setQrUrl('');
      setOtp('');
      setBackupCodes(confirmedCodes);
      await load();
    }
    setVerifyLoading(false);
  };

  const org = context?.organization;
  const isPartner = org?.organization_type === 'partner' || context?.user?.is_partner;
  const twoFaEnabled = Boolean(context?.user?.is_2fa_enabled);
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
              </div>
            </section>

            <section className="glass-panel p-5 lg:col-span-3">
              <div className="flex items-start gap-3">
                <div className="rounded-xl bg-primary/10 p-2.5 text-primary">
                  <ShieldCheck className="h-5 w-5" />
                </div>
                <div className="flex-1">
                  <h2 className="font-bold text-ink">Security (2FA)</h2>
                  <p className="mt-1 text-sm text-slate-600">
                    Protect your account with an authenticator app. Backup codes are shown once during setup.
                  </p>

                  {securityMsg && (
                    <p className="mt-3 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-sm text-primary">{securityMsg}</p>
                  )}
                  {securityError && (
                    <p className="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{securityError}</p>
                  )}

                  {!twoFaEnabled && !qrUrl && (
                    <Button type="button" className="mt-4" loading={setupLoading} onClick={start2faSetup}>
                      Enable 2FA
                    </Button>
                  )}

                  {qrUrl && (
                    <form onSubmit={verify2faSetup} className="mt-4 space-y-4">
                      <img src={qrUrl} alt="2FA QR code" className="mx-auto h-44 w-44 rounded-lg border border-border bg-white p-2" />
                      {backupCodes.length > 0 && (
                        <div className="rounded-lg border border-border bg-white p-3">
                          <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Backup codes (save these now)</p>
                          <ul className="mt-2 grid grid-cols-2 gap-1 font-mono text-sm">
                            {backupCodes.map((code) => (
                              <li key={code}>{code}</li>
                            ))}
                          </ul>
                        </div>
                      )}
                      <Input
                        label="Authentication code"
                        value={otp}
                        onChange={(e) => setOtp(e.target.value)}
                        placeholder="000000"
                        inputMode="numeric"
                        maxLength={6}
                        required
                      />
                      <Button type="submit" loading={verifyLoading}>Verify and enable</Button>
                    </form>
                  )}

                  {twoFaEnabled && backupCodes.length > 0 && (
                        <div className="mt-4 rounded-lg border border-border bg-white p-3">
                          <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Backup codes (save these now)</p>
                          <ul className="mt-2 grid grid-cols-2 gap-1 font-mono text-sm">
                            {backupCodes.map((code) => (
                              <li key={code}>{code}</li>
                            ))}
                          </ul>
                        </div>
                      )}

                  {twoFaEnabled && backupCodes.length === 0 && (
                    <p className="mt-4 text-sm text-emerald-700">Two-factor authentication is active on this account.</p>
                  )}
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
