import { useEffect, useMemo, useState, type FormEvent } from 'react';
import BrandLogo from '@/components/BrandLogo';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import { getNetworkAuthUrl, getAcceptInviteUrl } from '../lib/auth-routing';

export default function VerifyEmailPage() {
  const token = useMemo(() => new URLSearchParams(window.location.search).get('token') || '', []);
  const sentFromRegistration = useMemo(() => new URLSearchParams(window.location.search).get('sent') === '1', []);
  const [loading, setLoading] = useState(!!token);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [email, setEmail] = useState(() => window.sessionStorage.getItem('orabooks_last_registered_email') || '');
  const [resendMsg, setResendMsg] = useState('');
  const [resendError, setResendError] = useState('');
  const [resendLoading, setResendLoading] = useState(false);

  useEffect(() => {
    if (!token) {
      setSuccess(
        sentFromRegistration
          ? 'A verification link has been sent to your email. Check your inbox to continue.'
          : 'Please check your email for the verification link, then return here to verify your account.'
      );
      setLoading(false);
      return;
    }
    const pendingInvite = window.sessionStorage.getItem('orabooks_pending_invite_token') || '';
    api.verifyEmailToken(token).then((res) => {
      if (res.error) setError(typeof res.error === 'string' ? res.error : 'Verification failed');
      else {
        setSuccess(
          pendingInvite
            ? 'Email verified. Log in, then open the invitation link to finish joining your team.'
            : 'Email verified successfully. You can now log in.'
        );
      }
      setLoading(false);
    });
  }, [sentFromRegistration, token]);

  const pendingInviteToken = window.sessionStorage.getItem('orabooks_pending_invite_token') || '';
  const loginUrl = pendingInviteToken
    ? getAcceptInviteUrl(pendingInviteToken)
    : getNetworkAuthUrl('login');

  const resend = async (e: FormEvent) => {
    e.preventDefault();
    setResendError('');
    setResendMsg('');
    setResendLoading(true);
    const res = await api.resendVerification(email);
    if (res.error) {
      setResendError(typeof res.error === 'string' ? res.error : 'Unable to resend verification email.');
    } else {
      setResendMsg('If the email exists and is not yet verified, a new verification link has been sent (valid 24 hours).');
    }
    setResendLoading(false);
  };

  return (
    <div className="brand-auth-bg flex min-h-screen items-center justify-center px-4 py-12">
      <div className="glass-panel w-full max-w-lg overflow-hidden p-8 text-center">
        <BrandLogo
          wrapperClassName="mx-auto mb-4 w-full max-w-[240px]"
          imageClassName="h-[100px] w-full object-contain"
          fallbackClassName="flex h-12 w-12 items-center justify-center rounded-xl bg-primary"
          fallbackTextClassName="text-2xl font-black text-white"
        />
        <h2 className="text-2xl font-bold text-ink">Verify Email</h2>
        {loading ? (
          <p className="mt-4 text-sm text-slate-600">Verifying your email address…</p>
        ) : error ? (
          <p className="mt-4 text-sm text-danger">{error}</p>
        ) : (
          <p className="mt-4 text-sm text-emerald-700">{success}</p>
        )}

        <form onSubmit={resend} className="mt-8 space-y-3 text-left">
          <p className="text-sm text-slate-600">Need a new link? Enter your email to resend verification (up to 3 times per hour).</p>
          <Input
            label="Email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="you@company.com"
            required
          />
          {resendMsg && <p className="text-sm text-primary">{resendMsg}</p>}
          {resendError && <p className="text-sm text-danger">{resendError}</p>}
          <Button type="submit" loading={resendLoading} className="w-full">Resend verification link</Button>
        </form>

        <div className="mt-6">
          <Button type="button" variant="secondary" onClick={() => { window.location.href = loginUrl; }}>
            {pendingInviteToken ? 'Continue to team invite' : 'Go to login'}
          </Button>
        </div>
      </div>
    </div>
  );
}
