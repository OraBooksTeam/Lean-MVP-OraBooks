import { useEffect, useState, type ReactNode } from 'react';
import { api } from '../api';
import {
  clearRedirectGuard,
  clearStoredAuthTokens,
  getNetworkLoginUrl,
  redirectToLogin,
} from '../lib/auth-routing';

export default function RequireAuth({ children }: { children: ReactNode }) {
  const [ready, setReady] = useState(false);
  const [blocked, setBlocked] = useState(false);
  const loginUrl = getNetworkLoginUrl();

  useEffect(() => {
    api.frontendContext().then((res) => {
      if (!res.error) {
        clearRedirectGuard();
        setReady(true);
        return;
      }

      if (redirectToLogin()) {
        return;
      }

      clearStoredAuthTokens();
      setBlocked(true);
    });
  }, []);

  if (blocked) {
    return (
      <div className="brand-page-bg flex min-h-screen items-center justify-center px-4">
        <div className="max-w-md rounded-2xl border border-border bg-white p-6 text-center shadow-sm">
          <p className="text-sm font-medium text-slate-600">
            Your session expired. Please log in again.
          </p>
          <a
            href={`${loginUrl}#/login`}
            className="mt-4 inline-flex rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white"
          >
            Go to login
          </a>
        </div>
      </div>
    );
  }

  if (!ready) {
    return (
      <div className="brand-page-bg flex min-h-screen items-center justify-center">
        <p className="text-sm font-medium text-slate-600">Loading workspace…</p>
      </div>
    );
  }

  return <>{children}</>;
}
