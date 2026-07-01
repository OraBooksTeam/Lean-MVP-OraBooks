import { useEffect, useMemo, useState } from 'react';
import BrandLogo from '@/components/BrandLogo';
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

function getAuthRedirectDataFromFrontendContext(ctxData: any) {
  const orgId = Number(ctxData?.org_id || ctxData?.organization?.id || 0);
  const subdomain = String(ctxData?.subdomain || ctxData?.organization?.subdomain || '').trim();
  const isPartner = Boolean(ctxData?.is_partner ?? ctxData?.user?.is_partner);

  return {
    org_id: orgId,
    subdomain,
    is_partner: isPartner,
    needs_accept_invite: false,
  };
}

function hasResolvedOrganization(ctxData: any) {
  const authData = getAuthRedirectDataFromFrontendContext(ctxData);
  return authData.org_id > 0 || authData.subdomain !== '';
}

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
          const hasOrg = hasResolvedOrganization(ctxData);
          if (!ctxRes.error && hasOrg) {
            clearPendingInviteToken();
            redirectAfterAuth(getAuthRedirectDataFromFrontendContext(ctxData));
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
        } else if (messageLower.includes('verify your email')) {
          setNeedsLogin(true);
          setError(message);
        } else {
          const looksInvalidOrExpired = messageLower.includes('invalid') || messageLower.includes('expired');
          if (looksInvalidOrExpired && hasStoredAuthToken()) {
            const ctxRes = await api.frontendContext();
            const ctxData = (ctxRes as any).data || {};
            const hasOrg = hasResolvedOrganization(ctxData);
            if (!ctxRes.error && hasOrg) {
              clearPendingInviteToken();
              setSuccess('Invitation already processed. Redirecting to your workspace…');
              redirectAfterAuth(getAuthRedirectDataFromFrontendContext(ctxData));
              setLoading(false);
              return;
            }
          }
          if (looksInvalidOrExpired) {
            clearPendingInviteToken();
          }
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
        <BrandLogo
          wrapperClassName="mx-auto mb-4 w-full max-w-[240px]"
          imageClassName="h-[100px] w-full object-contain"
          fallbackClassName="flex h-12 w-12 items-center justify-center rounded-xl bg-primary"
          fallbackTextClassName="text-2xl font-black text-white"
        />
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
