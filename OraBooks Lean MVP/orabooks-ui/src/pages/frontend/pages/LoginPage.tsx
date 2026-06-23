import { useState, useEffect, type FormEvent } from 'react';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import TwoFactorChallengeForm from '../components/TwoFactorChallengeForm';
import {
  clearRedirectGuard,
  clearLogoutSessionFlag,
  clearStoredAuthTokens,
  getNetworkAuthUrl,
  isLogoutLanding,
  redirectAfterLogin,
  absorbAuthTokensFromUrl,
} from '../lib/auth-routing';

export default function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [loading, setLoading] = useState(false);
  const [show2fa, setShow2fa] = useState(false);
  const [tempToken, setTempToken] = useState('');
  const [twoFaError, setTwoFaError] = useState('');

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);

    const stripAuthQueryFlags = () => {
      params.delete('logged_out');
      params.delete('auth_reset');
      params.delete('session_expired');
      params.delete('verified');
      const qs = params.toString();
      window.history.replaceState(null, '', `${window.location.pathname}${qs ? `?${qs}` : ''}`);
    };

    if (isLogoutLanding()) {
      clearStoredAuthTokens();
      clearRedirectGuard();

      if (params.get('logged_out') === '1') {
        setNotice('You have been logged out.');
      } else if (params.get('auth_reset') === '1') {
        setNotice('Your sign-in was reset for security. Please log in again.');
      } else if (params.get('session_expired') === '1') {
        setNotice('Your session has expired. Please log in again.');
      }

      stripAuthQueryFlags();
      clearLogoutSessionFlag();
      return;
    }

    void absorbAuthTokensFromUrl().catch(() => undefined);

    if (params.get('verified') === '1') {
      setNotice('Email verified. You can log in now.');
      stripAuthQueryFlags();
    }

    const oidcError = params.get('oidc_error');
    if (oidcError) {
      setError(decodeURIComponent(oidcError));
      const nextParams = new URLSearchParams(window.location.search);
      nextParams.delete('oidc_error');
      nextParams.delete('code');
      nextParams.delete('state');
      const qs = nextParams.toString();
      window.history.replaceState(null, '', `${window.location.pathname}${qs ? `?${qs}` : ''}`);
      return;
    }

    const url = new URL(window.location.href);
    const code = url.searchParams.get('code');
    const state = url.searchParams.get('state');
    if (code && state) {
      api.oidcCallback(code, state).then((res) => {
        if (res.error) setError(typeof res.error === 'string' ? res.error : 'Authentication failed');
        else if ((res as any).data?.requires_2fa) {
          const token = String((res as any).data?.temp_token || '');
          if (!token) {
            setError('Two-factor authentication is required, but the challenge token was missing. Please try again.');
            return;
          }
          setShow2fa(true);
          setTempToken(token);
        } else redirectAfterLogin((res as any).data);
      });
    }
  }, []);

  const begin2faChallenge = (data: any) => {
    const token = String(data?.temp_token || '');
    if (!token) {
      setError('Two-factor authentication is required, but the challenge token was missing. Please try again.');
      return;
    }
    setTwoFaError('');
    setShow2fa(true);
    setTempToken(token);
  };

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      const res = await api.login(email, password);
      if (res.error) setError(typeof res.error === 'string' ? res.error : 'Login failed');
      else if ((res as any).data?.requires_2fa) begin2faChallenge((res as any).data);
      else redirectAfterLogin((res as any).data);
    } finally {
      setLoading(false);
    }
  };

  const submit2fa = async ({
    otp,
    backupCode,
    useBackupCode,
  }: {
    otp: string;
    backupCode: string;
    useBackupCode: boolean;
  }) => {
    setTwoFaError('');
    setLoading(true);
    try {
      const res = await api.twoFactorChallenge(
        tempToken,
        useBackupCode ? '' : otp,
        useBackupCode ? backupCode : ''
      );
      if (res.error) {
        setTwoFaError(typeof res.error === 'string' ? res.error : '2FA verification failed.');
        return;
      }

      const data = (res as any).data;
      if (data?.token) {
        await api.establishSession(String(data.token));
      }
      redirectAfterLogin(data);
    } finally {
      setLoading(false);
    }
  };

  const googleLogin = () => {
    setError('');
    api.oidcInitiate().then((res) => {
      if (res.error) {
        setError(typeof res.error === 'string' ? res.error : 'Google login is unavailable.');
        return;
      }
      if ((res as any).data?.auth_url) window.location.href = (res as any).data.auth_url;
    });
  };

  if (show2fa) {
    return (
      <TwoFactorChallengeForm
        tempToken={tempToken}
        loading={loading}
        error={twoFaError}
        onSubmit={submit2fa}
      />
    );
  }

  return (
    <div className="brand-auth-bg flex min-h-screen items-center justify-center px-4 py-12">
      <div className="glass-panel w-full max-w-md overflow-hidden">
        <div className="p-8">
          <div className="mx-auto mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-primary">
            <span className="text-2xl font-black text-white">OB</span>
          </div>
          <h2 className="text-center text-2xl font-bold text-ink">Log In</h2>
          <p className="mt-2 text-center text-sm text-slate-600">Welcome back. Sign in to your account.</p>
          {notice && (
            <p className="mt-4 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-sm text-primary">
              {notice}
            </p>
          )}
          <form onSubmit={submit} className="mt-6 space-y-4">
            <Input label="Email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="you@company.com" required />
            <Input label="Password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} placeholder="••••••••" required />
            {error && <p className="text-sm text-danger">{error}</p>}
            <div className="flex items-center justify-between text-sm">
              <a href={getNetworkAuthUrl('reset-password')} className="text-primary hover:text-primary-dark font-medium">Forgot password?</a>
              <a href={getNetworkAuthUrl('register')} className="text-primary hover:text-primary-dark font-medium">Create account</a>
            </div>
            <Button type="submit" loading={loading} className="w-full">Log In</Button>
          </form>
          <div className="my-6 flex items-center gap-3">
            <div className="h-px flex-1 bg-border" />
            <span className="text-xs font-medium text-slate-500">or continue with</span>
            <div className="h-px flex-1 bg-border" />
          </div>
          <button
            onClick={googleLogin}
            className="flex w-full items-center justify-center gap-2 rounded-lg border border-border bg-white px-4 py-2.5 text-sm font-medium text-ink shadow-sm transition hover:bg-muted active:scale-[0.98]"
          >
            <svg viewBox="0 0 24 24" className="h-4 w-4" aria-hidden="true">
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.3v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.08z" />
              <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
            </svg>
            Continue with Google
          </button>
        </div>
      </div>
    </div>
  );
}
