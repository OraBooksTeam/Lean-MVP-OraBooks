import { useEffect, useMemo, useState } from 'react';
import { UserPlus } from 'lucide-react';
import Button from '@/components/Button';
import { api, hasStoredAuthToken } from '../api';
import {
  clearPendingInviteToken,
  getNetworkAuthUrl,
  redirectAfterAuth,
  storePendingInviteToken,
} from '../lib/auth-routing';

type InvitePreview = {
  email?: string;
  role?: string;
  org_name?: string;
};

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
  const [preview, setPreview] = useState<InvitePreview | null>(null);

  useEffect(() => {
    if (!token) {
      if (hasStoredAuthToken()) {
        void api.frontendContext().then((ctxRes) => {
          const ctxData = (ctxRes as any).data || {};
          const hasOrg = Number(ctxData?.org_id || 0) > 0 || String(ctxData?.subdomain || '').trim() !== '';
          if (!ctxRes.error && hasOrg) {
            clearPendingInviteToken();
            redirectAfterAuth({ ...ctxData, needs_accept_invite: false });
            return;
          }
          setError('This invitation link is invalid or missing a token.');
        });
      } else {
        setError('This invitation link is invalid or missing a token.');
      }
      setLoading(false);
      return;
    }

    void (async () => {
      const previewRes = await api.previewInvite(token);
      if (!previewRes.error) {
        setPreview((previewRes as any).data || null);
      }

      if (!hasStoredAuthToken()) {
        setNeedsLogin(true);
        setLoading(false);
        return;
      }

      const res = await api.acceptInvite(token);
      if (res.error) {
        const message = typeof res.error === 'string' ? res.error : 'Unable to accept invitation.';
        const messageLower = message.toLowerCase();
        if (messageLower.includes('log in')) {
          setNeedsLogin(true);
        } else {
          const looksInvalidOrExpired = messageLower.includes('invalid') || messageLower.includes('expired');
          if (looksInvalidOrExpired && hasStoredAuthToken()) {
            const ctxRes = await api.frontendContext();
            const ctxData = (ctxRes as any).data || {};
            const hasOrg = Number(ctxData?.org_id || 0) > 0 || String(ctxData?.subdomain || '').trim() !== '';
            if (!ctxRes.error && hasOrg) {
              clearPendingInviteToken();
              setSuccess('Invitation already processed. Redirecting to your workspace…');
              redirectAfterAuth({ ...ctxData, needs_accept_invite: false });
              setLoading(false);
              return;
            }
          }
          clearPendingInviteToken();
          setError(message);
        }
      } else {
        clearPendingInviteToken();
        setSuccess('Invitation accepted. Redirecting to your workspace…');
        redirectAfterAuth((res as any).data);
      }
      setLoading(false);
    })();
  }, [token]);

  const registerUrl = getNetworkAuthUrl('register', preview?.email ? { email: preview.email } : {});
  const loginUrl = getNetworkAuthUrl('login');

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
              {preview?.org_name
                ? `You have been invited to join ${preview.org_name}${preview.role ? ` as ${preview.role}` : ''}.`
                : 'Log in or create an account with the email address that received this invitation.'}
            </p>
            {preview?.email && (
              <p className="mt-2 text-sm font-semibold text-ink">{preview.email}</p>
            )}
            <p className="mt-3 text-sm text-slate-600">
              If you do not have an account yet, create one first and verify your email address. Then log in and open this invitation link again.
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
