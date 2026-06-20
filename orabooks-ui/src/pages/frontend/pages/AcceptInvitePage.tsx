import { useEffect, useMemo, useState } from 'react';
import { UserPlus } from 'lucide-react';
import Button from '@/components/Button';
import { api, hasStoredAuthToken } from '../api';
import {
  getNetworkAuthUrl,
  redirectAfterAuth,
  storePendingInviteToken,
} from '../lib/auth-routing';

export default function AcceptInvitePage() {
  const token = useMemo(() => {
    const fromUrl = new URLSearchParams(window.location.search).get('token') || '';
    if (fromUrl) {
      storePendingInviteToken(fromUrl);
      return fromUrl;
    }
    return '';
  }, []);
  const [loading, setLoading] = useState(!!token);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [needsLogin, setNeedsLogin] = useState(false);

  useEffect(() => {
    if (!token) {
      setError('This invitation link is invalid or missing a token.');
      setLoading(false);
      return;
    }

    if (!hasStoredAuthToken()) {
      setNeedsLogin(true);
      setLoading(false);
      return;
    }

    void (async () => {
      const res = await api.acceptInvite(token);
      if (res.error) {
        const message = typeof res.error === 'string' ? res.error : 'Unable to accept invitation.';
        if (message.toLowerCase().includes('log in')) {
          setNeedsLogin(true);
        } else {
          setError(message);
        }
      } else {
        setSuccess('Invitation accepted. Redirecting to your workspace…');
        redirectAfterAuth((res as any).data);
      }
      setLoading(false);
    })();
  }, [token]);

  const loginUrl = getNetworkAuthUrl('login');
  const registerUrl = getNetworkAuthUrl('register');

  return (
    <div className="brand-auth-bg flex min-h-screen items-center justify-center px-4 py-12">
      <div className="glass-panel w-full max-w-lg overflow-hidden p-8 text-center">
        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-primary">
          <UserPlus className="h-6 w-6 text-white" />
        </div>
        <h2 className="text-2xl font-bold text-ink">Accept Team Invitation</h2>

        {loading ? (
          <p className="mt-4 text-sm text-slate-600">Confirming your invitation…</p>
        ) : needsLogin ? (
          <>
            <p className="mt-4 text-sm text-slate-600">
              Log in or create an account with the email address that received this invitation, then return here to join the team.
            </p>
            <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
              <Button type="button" onClick={() => { window.location.href = loginUrl; }}>
                Log in
              </Button>
              <Button type="button" variant="secondary" onClick={() => { window.location.href = registerUrl; }}>
                Create account
              </Button>
            </div>
          </>
        ) : error ? (
          <p className="mt-4 text-sm text-danger">{error}</p>
        ) : (
          <p className="mt-4 text-sm text-emerald-700">{success}</p>
        )}
      </div>
    </div>
  );
}
