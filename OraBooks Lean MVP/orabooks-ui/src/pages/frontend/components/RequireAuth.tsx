import { useEffect, useState, type ReactNode } from 'react';
import { api } from '../api';
import {
  absorbAuthTokensFromUrl,
  clearRedirectGuard,
  getNetworkLoginUrl,
  isLogoutLanding,
  redirectToLogin,
} from '../lib/auth-routing';
import { toWpUrl } from '../lib/wp-routing';

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
