import { useState, type FormEvent } from 'react';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import TwoFactorQrCode from './TwoFactorQrCode';
import { isValidTotpCode, normalizeTotpCode } from '@/lib/two-factor';
import { ShieldCheck } from 'lucide-react';

export type TwoFactorContext = {
  user?: {
    is_2fa_enabled?: boolean;
    remaining_backup_codes?: number;
  };
  organization?: {
    id?: number;
    require_2fa?: boolean;
  };
  security?: {
    needs_2fa_setup?: boolean;
  };
};

interface TwoFactorSetupPanelProps {
  context: TwoFactorContext | null;
  onChanged?: () => void | Promise<void>;
  showOrgPolicy?: boolean;
  orgRequire2fa?: boolean;
  onToggleOrgPolicy?: () => void;
  policyLoading?: boolean;
}

export default function TwoFactorSetupPanel({
  context,
  onChanged,
  showOrgPolicy = false,
  orgRequire2fa = false,
  onToggleOrgPolicy,
  policyLoading = false,
}: TwoFactorSetupPanelProps) {
  const [securityMsg, setSecurityMsg] = useState('');
  const [securityError, setSecurityError] = useState('');
  const [setupLoading, setSetupLoading] = useState(false);
  const [verifyLoading, setVerifyLoading] = useState(false);
  const [disableLoading, setDisableLoading] = useState(false);
  const [regenLoading, setRegenLoading] = useState(false);
  const [revealLoading, setRevealLoading] = useState(false);
  const [otpauthUri, setOtpauthUri] = useState('');
  const [totpSecret, setTotpSecret] = useState('');
  const [backupCodes, setBackupCodes] = useState<string[]>([]);
  const [showSetupBackupCodes, setShowSetupBackupCodes] = useState(false);
  const [otp, setOtp] = useState('');
  const [disableOtp, setDisableOtp] = useState('');
  const [regenOtp, setRegenOtp] = useState('');
  const [revealOtp, setRevealOtp] = useState('');

  const setupActive = Boolean(otpauthUri || totpSecret);
  const org = context?.organization;
  const twoFaEnabled = Boolean(context?.user?.is_2fa_enabled);
  const needs2faSetup = Boolean(context?.security?.needs_2fa_setup);
  const remainingBackupCodes = Number(context?.user?.remaining_backup_codes ?? 0);

  const start2faSetup = async () => {
    setSecurityError('');
    setSecurityMsg('');
    setSetupLoading(true);
    const res = await api.setup2fa();
    if (res.error) {
      setSecurityError(typeof res.error === 'string' ? res.error : 'Unable to start 2FA setup.');
    } else {
      setOtpauthUri(String((res as any).data?.otpauth_uri || ''));
      setTotpSecret(String((res as any).data?.secret || ''));
      setBackupCodes(Array.isArray((res as any).data?.backup_codes) ? (res as any).data.backup_codes : []);
      setShowSetupBackupCodes(false);
      setSecurityMsg('Scan the QR code with your authenticator app, then enter the 6-digit code to enable 2FA.');
    }
    setSetupLoading(false);
  };

  const verify2faSetup = async (e: FormEvent) => {
    e.preventDefault();
    setSecurityError('');
    const normalizedOtp = normalizeTotpCode(otp);
    if (!isValidTotpCode(normalizedOtp)) {
      setSecurityError('Enter the 6-digit code from your authenticator app.');
      return;
    }
    setVerifyLoading(true);
    const res = await api.verify2faSetup(normalizedOtp);
    if (res.error) {
      setSecurityError(typeof res.error === 'string' ? res.error : 'Invalid code.');
    } else {
      const confirmedCodes = Array.isArray((res as any).data?.backup_codes)
        ? (res as any).data.backup_codes
        : backupCodes;
      setSecurityMsg('Two-factor authentication is now enabled. Save your backup codes in a secure place.');
      setOtpauthUri('');
      setTotpSecret('');
      setOtp('');
      setBackupCodes(confirmedCodes);
      setShowSetupBackupCodes(true);
      await onChanged?.();
    }
    setVerifyLoading(false);
  };

  const disable2fa = async (e: FormEvent) => {
    e.preventDefault();
    setSecurityError('');
    const normalizedOtp = normalizeTotpCode(disableOtp);
    if (!isValidTotpCode(normalizedOtp)) {
      setSecurityError('Enter the 6-digit code from your authenticator app.');
      return;
    }
    setDisableLoading(true);
    const res = await api.disable2fa(normalizedOtp);
    if (res.error) {
      setSecurityError(typeof res.error === 'string' ? res.error : 'Unable to disable 2FA.');
    } else {
      setSecurityMsg('Two-factor authentication has been disabled.');
      setDisableOtp('');
      setBackupCodes([]);
      await onChanged?.();
    }
    setDisableLoading(false);
  };

  const regenerateBackupCodes = async (e: FormEvent) => {
    e.preventDefault();
    setSecurityError('');
    const normalizedOtp = normalizeTotpCode(regenOtp);
    if (!isValidTotpCode(normalizedOtp)) {
      setSecurityError('Enter the 6-digit code from your authenticator app.');
      return;
    }
    setRegenLoading(true);
    const res = await api.regenerate2faBackupCodes(normalizedOtp);
    if (res.error) {
      setSecurityError(typeof res.error === 'string' ? res.error : 'Unable to regenerate backup codes.');
    } else {
      setBackupCodes(Array.isArray((res as any).data?.backup_codes) ? (res as any).data.backup_codes : []);
      setShowSetupBackupCodes(true);
      setSecurityMsg('New backup codes generated. Save them in a secure place.');
      setRegenOtp('');
      await onChanged?.();
    }
    setRegenLoading(false);
  };

  const revealBackupCodes = async (e: FormEvent) => {
    e.preventDefault();
    setSecurityError('');
    const normalizedOtp = normalizeTotpCode(revealOtp);
    if (!isValidTotpCode(normalizedOtp)) {
      setSecurityError('Enter the 6-digit code from your authenticator app.');
      return;
    }
    setRevealLoading(true);
    const res = await api.reveal2faBackupCodes(normalizedOtp);
    if (res.error) {
      setSecurityError(typeof res.error === 'string' ? res.error : 'Unable to retrieve backup codes.');
    } else {
      setBackupCodes(Array.isArray((res as any).data?.backup_codes) ? (res as any).data.backup_codes : []);
      setShowSetupBackupCodes(true);
      setSecurityMsg('Unused backup codes shown below. Store them securely.');
      setRevealOtp('');
    }
    setRevealLoading(false);
  };

  return (
    <section id="security-2fa" className="glass-panel p-5">
      <div className="flex items-start gap-3">
        <div className="rounded-xl bg-primary/10 p-2.5 text-primary">
          <ShieldCheck className="h-5 w-5" />
        </div>
        <div className="flex-1">
          <h2 className="font-bold text-ink">Two-Factor Authentication</h2>
          <p className="mt-1 text-sm text-slate-600">
            Protect your account with an authenticator app. View backup codes anytime with your authenticator code.
          </p>

          {showOrgPolicy && onToggleOrgPolicy && (
            <div className="mt-4 rounded-lg border border-border bg-white p-4">
              <h3 className="text-sm font-bold text-ink">Organization Security Policy</h3>
              <p className="mt-1 text-xs text-slate-500">
                When enabled, every member must set up two-factor authentication before accessing accounting features.
              </p>
              <Button
                type="button"
                className="mt-3"
                variant={orgRequire2fa ? 'secondary' : 'primary'}
                loading={policyLoading}
                onClick={onToggleOrgPolicy}
              >
                {orgRequire2fa ? 'Remove mandatory 2FA' : 'Require 2FA for all members'}
              </Button>
            </div>
          )}

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
              {otpauthUri && (
                <div className="space-y-2">
                  <TwoFactorQrCode value={otpauthUri} />
                  <p className="text-center text-xs text-slate-500">
                    Scan with Google Authenticator or another TOTP app (issuer: OraBooks).
                  </p>
                </div>
              )}
              {totpSecret && (
                <div className="rounded-lg border border-border bg-white p-3">
                  <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Manual setup key</p>
                  <p className="mt-2 break-all font-mono text-sm">{totpSecret}</p>
                </div>
              )}
              {backupCodes.length > 0 && (
                <div className="rounded-lg border border-border bg-white p-3">
                  <div className="flex items-center justify-between gap-3">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Backup codes</p>
                    <Button
                      type="button"
                      size="sm"
                      variant="secondary"
                      onClick={() => setShowSetupBackupCodes((v) => !v)}
                    >
                      {showSetupBackupCodes ? 'Hide backup codes' : 'Show backup codes'}
                    </Button>
                  </div>
                  {showSetupBackupCodes && (
                    <ul className="mt-2 grid grid-cols-2 gap-1 font-mono text-sm">
                      {backupCodes.map((code) => (
                        <li key={code}>{code}</li>
                      ))}
                    </ul>
                  )}
                </div>
              )}
              <Input
                label="Authentication code"
                value={otp}
                onChange={(e) => setOtp(normalizeTotpCode(e.target.value))}
                placeholder="000000"
                inputMode="numeric"
                maxLength={6}
                autoComplete="one-time-code"
                required
              />
              <Button type="submit" loading={verifyLoading}>Verify and enable</Button>
            </form>
          )}

          {twoFaEnabled && backupCodes.length > 0 && showSetupBackupCodes && (
            <div className="mt-4 rounded-lg border border-border bg-white p-3">
              <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Backup codes</p>
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
              <form onSubmit={revealBackupCodes} className="space-y-3 rounded-lg border border-border bg-white p-4">
                <h3 className="text-sm font-bold text-ink">View backup codes</h3>
                <p className="text-xs text-slate-500">Shows unused backup codes without regenerating them.</p>
                <Input
                  label="Authentication code"
                  value={revealOtp}
                  onChange={(e) => setRevealOtp(normalizeTotpCode(e.target.value))}
                  placeholder="000000"
                  inputMode="numeric"
                  maxLength={6}
                  autoComplete="one-time-code"
                  required
                />
                <Button type="submit" variant="secondary" loading={revealLoading}>Show backup codes</Button>
              </form>

              <form onSubmit={regenerateBackupCodes} className="space-y-3 rounded-lg border border-border bg-white p-4">
                <h3 className="text-sm font-bold text-ink">Regenerate backup codes</h3>
                <p className="text-xs text-slate-500">Invalidates all existing backup codes. Requires your current authenticator code.</p>
                <Input
                  label="Authentication code"
                  value={regenOtp}
                  onChange={(e) => setRegenOtp(normalizeTotpCode(e.target.value))}
                  placeholder="000000"
                  inputMode="numeric"
                  maxLength={6}
                  autoComplete="one-time-code"
                  required
                />
                <Button type="submit" variant="secondary" loading={regenLoading}>Generate new codes</Button>
              </form>

              {!org?.require_2fa && (
                <form onSubmit={disable2fa} className="space-y-3 rounded-lg border border-red-200 bg-red-50/40 p-4 lg:col-span-2">
                  <h3 className="text-sm font-bold text-ink">Disable 2FA</h3>
                  <p className="text-xs text-slate-600">Confirm with your authenticator code. Not available when your organization requires 2FA.</p>
                  <Input
                    label="Authentication code"
                    value={disableOtp}
                    onChange={(e) => setDisableOtp(normalizeTotpCode(e.target.value))}
                    placeholder="000000"
                    inputMode="numeric"
                    maxLength={6}
                    autoComplete="one-time-code"
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
  );
}
