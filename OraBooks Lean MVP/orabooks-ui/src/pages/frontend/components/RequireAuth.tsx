import { useEffect, useState, type ReactNode } from 'react';
import { api } from '../api';
import {
  absorbAuthTokensFromUrl,
  clearRedirectGuard,
  getNetworkLoginUrl,
  isLogoutLanding,
  redirectToLogin,
} from '../lib/auth-routing';
import { routeRequires2faSetupRedirect } from '@/lib/two-factor';
import { getCurrentAppRoute, toWpUrl } from '../lib/wp-routing';

export default function RequireAuth({ children }: { children: ReactNode }) {
  const [ready, setReady] = useState(false);
  const loginUrl = toWpUrl(getNetworkLoginUrl());

  useEffect(() => {
    let cancelled = false;

    if (isLogoutLanding()) {
      redirectToLogin(true, true);
      return () => {
        cancelled = true;
      };
    }

    absorbAuthTokensFromUrl()
      .catch(() => undefined)
      .then(() => {
        if (cancelled) {
          return;
        }

        return api.verifySession();
      })
      .then((res) => {
        if (cancelled || res === undefined) {
          return;
        }

        if (!res.error) {
          const needs2faSetup = Boolean((res as any).data?.security?.needs_2fa_setup);
          const route = getCurrentAppRoute();
          if (routeRequires2faSetupRedirect(route, needs2faSetup)) {
            window.location.replace(toWpUrl('/security/2fa/'));
            return;
          }

          clearRedirectGuard();
          setReady(true);
          return;
        }

        redirectToLogin(true, true);
      })
      .catch(() => {
        if (!cancelled) {
          redirectToLogin(true, true);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  if (!ready) {
    return (
      <div className="brand-page-bg flex min-h-screen items-center justify-center">
        <p className="text-sm font-medium text-slate-600">Loading workspace…</p>
        <noscript>
          <a href={loginUrl} className="ml-2 text-sm text-primary">
            Go to login
          </a>
        </noscript>
      </div>
    );
  }

  return <>{children}</>;
}
