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
  const [disableLoading, setDisableLoading] = useState(false);
  const [regenLoading, setRegenLoading] = useState(false);
  const [policyLoading, setPolicyLoading] = useState(false);
  const [qrUrl, setQrUrl] = useState('');
  const [totpSecret, setTotpSecret] = useState('');
  const [backupCodes, setBackupCodes] = useState<string[]>([]);
  const [otp, setOtp] = useState('');
  const [disableOtp, setDisableOtp] = useState('');
  const [regenOtp, setRegenOtp] = useState('');
  const [orgRequire2fa, setOrgRequire2fa] = useState(false);
  const setupActive = Boolean(qrUrl || totpSecret);

  const load = async () => {
    setLoading(true);
    setError('');
    const res = await api.frontendContext();
    if (res.error) setError(res.error || 'Unable to load profile.');
    else {
      const data = (res as any).data;
      setContext(data);
      setOrgRequire2fa(Boolean(data?.organization?.require_2fa));
    }
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
      setTotpSecret(String((res as any).data?.secret || ''));
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
      setTotpSecret('');
      setOtp('');
      setBackupCodes(confirmedCodes);
      await load();
    }
    setVerifyLoading(false);
  };

  const disable2fa = async (e: FormEvent) => {
    e.preventDefault();
    setSecurityError('');
    setDisableLoading(true);
    const res = await api.disable2fa(disableOtp);
    if (res.error) {
      setSecurityError(typeof res.error === 'string' ? res.error : 'Unable to disable 2FA.');
    } else {
      setSecurityMsg('Two-factor authentication has been disabled.');
      setDisableOtp('');
      setBackupCodes([]);
      await load();
    }
    setDisableLoading(false);
  };

  const regenerateBackupCodes = async (e: FormEvent) => {
    e.preventDefault();
    setSecurityError('');
    setRegenLoading(true);
    const res = await api.regenerate2faBackupCodes(regenOtp);
    if (res.error) {
      setSecurityError(typeof res.error === 'string' ? res.error : 'Unable to regenerate backup codes.');
    } else {
      setBackupCodes(Array.isArray((res as any).data?.backup_codes) ? (res as any).data.backup_codes : []);
      setSecurityMsg('New backup codes generated. Save them now — they will not be shown again.');
      setRegenOtp('');
      await load();
    }
    setRegenLoading(false);
  };

  const toggleOrg2faPolicy = async () => {
    const orgId = context?.organization?.id;
    if (!orgId) return;
    setSecurityError('');
    setPolicyLoading(true);
    const next = !orgRequire2fa;
    const res = await api.setOrg2faPolicy(orgId, next);
    if (res.error) {
      setSecurityError(typeof res.error === 'string' ? res.error : 'Unable to update organization 2FA policy.');
    } else {
      setOrgRequire2fa(next);
      setSecurityMsg(next
        ? 'Organization policy updated: all members must enable 2FA.'
        : 'Organization 2FA requirement removed.');
      await load();
    }
    setPolicyLoading(false);
  };

  const org = context?.organization;
  const isPartner = org?.organization_type === 'partner' || context?.user?.is_partner;
  const twoFaEnabled = Boolean(context?.user?.is_2fa_enabled);
  const needs2faSetup = Boolean(context?.security?.needs_2fa_setup);
  const canManageOrgSettings = Array.isArray(context?.permissions)
    && context.permissions.includes('manage_org_settings');
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

            {canManageOrgSettings && org?.id && !isPartner && (
              <section className="glass-panel p-5 lg:col-span-3">
                <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">Organization Security Policy</h2>
                <p className="mt-2 text-sm text-slate-600">
                  When enabled, every member must set up two-factor authentication before accessing accounting features.
                </p>
                <Button
                  type="button"
                  className="mt-4"
                  variant={orgRequire2fa ? 'secondary' : 'primary'}
                  loading={policyLoading}
                  onClick={toggleOrg2faPolicy}
                >
                  {orgRequire2fa ? 'Remove mandatory 2FA' : 'Require 2FA for all members'}
                </Button>
              </section>
            )}

            <section id="security-2fa" className="glass-panel p-5 lg:col-span-3">
              <div className="flex items-start gap-3">
                <div className="rounded-xl bg-primary/10 p-2.5 text-primary">
                  <ShieldCheck className="h-5 w-5" />
                </div>
                <div className="flex-1">
                  <h2 className="font-bold text-ink">Security (2FA)</h2>
                  <p className="mt-1 text-sm text-slate-600">
                    Protect your account with an authenticator app. Backup codes are shown once when generated.
                  </p>

                  {needs2faSetup && (
                    <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                      Your organization requires two-factor authentication. Enable 2FA below to access OraBooks features.
                    </p>
                  )}

                  {securityMsg && (
                    <p className="mt-3 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-sm text-primary">{securityMsg}</p>
                  )}
                  {securityError && (
                    <p className="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{securityError}</p>
                  )}

                  {!twoFaEnabled && !setupActive && (
                    <Button type="button" className="mt-4" loading={setupLoading} onClick={start2faSetup}>
                      Enable 2FA
                    </Button>
                  )}

                  {setupActive && (
                    <form onSubmit={verify2faSetup} className="mt-4 space-y-4">
                      {qrUrl && (
                        <img
                          src={qrUrl}
                          alt="2FA QR code"
                          className="mx-auto h-44 w-44 rounded-lg border border-border bg-white p-2"
                        />
                      )}
                      {totpSecret && (
                        <div className="rounded-lg border border-border bg-white p-3">
                          <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Manual setup key
                          </p>
                          <p className="mt-2 break-all font-mono text-sm">{totpSecret}</p>
                          <p className="mt-2 text-xs text-slate-500">
                            If the QR code does not load, enter this key manually in your authenticator app (issuer: OraBooks).
                          </p>
                        </div>
                      )}
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
                    <p className="mt-4 text-sm text-emerald-700">
                      Two-factor authentication is active. {remainingBackupCodes} backup code{remainingBackupCodes === 1 ? '' : 's'} remaining.
                    </p>
                  )}

                  {twoFaEnabled && (
                    <div className="mt-6 grid gap-6 lg:grid-cols-2">
                      <form onSubmit={regenerateBackupCodes} className="space-y-3 rounded-lg border border-border bg-white p-4">
                        <h3 className="text-sm font-bold text-ink">Regenerate backup codes</h3>
                        <p className="text-xs text-slate-500">Invalidates all existing backup codes. Requires your current authenticator code.</p>
                        <Input
                          label="Authentication code"
                          value={regenOtp}
                          onChange={(e) => setRegenOtp(e.target.value)}
                          placeholder="000000"
                          inputMode="numeric"
                          maxLength={6}
                          required
                        />
                        <Button type="submit" variant="secondary" loading={regenLoading}>Generate new codes</Button>
                      </form>

                      {!org?.require_2fa && (
                        <form onSubmit={disable2fa} className="space-y-3 rounded-lg border border-red-200 bg-red-50/40 p-4">
                          <h3 className="text-sm font-bold text-ink">Disable 2FA</h3>
                          <p className="text-xs text-slate-600">Confirm with your authenticator code. Not available when your organization requires 2FA.</p>
                          <Input
                            label="Authentication code"
                            value={disableOtp}
                            onChange={(e) => setDisableOtp(e.target.value)}
                            placeholder="000000"
                            inputMode="numeric"
                            maxLength={6}
                            required
                          />
                          <Button type="submit" variant="secondary" loading={disableLoading}>Disable 2FA</Button>
                        </form>
                      )}
                    </div>
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
