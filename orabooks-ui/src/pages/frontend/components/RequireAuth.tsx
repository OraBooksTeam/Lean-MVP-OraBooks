import { useEffect, useState, type ReactNode } from 'react';
import { api } from '../api';

export default function RequireAuth({ children }: { children: ReactNode }) {
  const [ready, setReady] = useState(false);

  useEffect(() => {
    api.frontendContext().then((res) => {
      if (res.error) {
        window.location.href = '/login/';
        return;
      }
      setReady(true);
    });
  }, []);

  if (!ready) {
    return (
      <div className="brand-page-bg flex min-h-screen items-center justify-center">
        <p className="text-sm font-medium text-slate-600">Loading workspace…</p>
      </div>
    );
  }

  return <>{children}</>;
}
