import { useState, type FormEvent } from 'react';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { Flame } from 'lucide-react';
import {
  isValidBackupCode,
  isValidTotpCode,
  normalizeBackupCode,
  normalizeTotpCode,
} from '@/lib/two-factor';

type Props = {
  tempToken: string;
  loading?: boolean;
  error?: string;
  onSubmit: (payload: { otp: string; backupCode: string; useBackupCode: boolean }) => void | Promise<void>;
};

export default function TwoFactorChallengeForm({ tempToken, loading = false, error = '', onSubmit }: Props) {
  const [otp, setOtp] = useState('');
  const [backupCode, setBackupCode] = useState('');
  const [useBackupCode, setUseBackupCode] = useState(false);
  const [localError, setLocalError] = useState('');

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setLocalError('');

    if (!tempToken) {
      setLocalError('Your sign-in session expired. Please log in again.');
      return;
    }

    if (useBackupCode) {
      const normalizedBackup = normalizeBackupCode(backupCode);
      if (!isValidBackupCode(normalizedBackup)) {
        setLocalError('Enter a valid backup code.');
        return;
      }
      await onSubmit({ otp: '', backupCode: normalizedBackup, useBackupCode: true });
      return;
    }

    const normalizedOtp = normalizeTotpCode(otp);
    if (!isValidTotpCode(normalizedOtp)) {
      setLocalError('Enter the 6-digit code from your authenticator app.');
      return;
    }

    await onSubmit({ otp: normalizedOtp, backupCode: '', useBackupCode: false });
  };

  const displayError = localError || error;

  return (
    <div className="brand-auth-bg flex min-h-screen items-center justify-center px-4 py-12">
      <div className="glass-panel w-full max-w-md overflow-hidden">
        <div className="p-8">
          <div className="mx-auto mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-primary">
            <Flame className="h-6 w-6 text-white" />
          </div>
          <h2 className="text-center text-xl font-bold text-ink">Two-Factor Authentication</h2>
          <p className="mt-2 text-center text-sm text-slate-600">
            Enter the 6-digit code from your authenticator app, or use a backup code.
          </p>
          <form onSubmit={handleSubmit} className="mt-6 space-y-4">
            {useBackupCode ? (
              <Input
                label="Backup Code"
                value={backupCode}
                onChange={(e) => setBackupCode(e.target.value)}
                placeholder="Enter backup code"
                autoComplete="one-time-code"
                required
              />
            ) : (
              <Input
                label="Authentication Code"
                value={otp}
                onChange={(e) => setOtp(normalizeTotpCode(e.target.value))}
                placeholder="000000"
                inputMode="numeric"
                autoComplete="one-time-code"
                maxLength={6}
                required
              />
            )}
            <button
              type="button"
              onClick={() => {
                setUseBackupCode((prev) => !prev);
                setLocalError('');
              }}
              className="text-sm font-medium text-primary hover:text-primary-dark"
            >
              {useBackupCode ? 'Use authenticator code instead' : 'Use a backup code instead'}
            </button>
            {displayError && <p className="text-sm text-danger">{displayError}</p>}
            <Button type="submit" loading={loading} className="w-full" disabled={!tempToken}>
              Verify Code
            </Button>
          </form>
        </div>
      </div>
    </div>
  );
}
