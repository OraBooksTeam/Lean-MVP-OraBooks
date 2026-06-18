import { useEffect, useMemo, useState } from 'react';
import { MailCheck } from 'lucide-react';
import Button from '@/components/Button';
import { api } from '../api';

export default function VerifyEmailPage() {
  const token = useMemo(() => new URLSearchParams(window.location.search).get('token') || '', []);
  const [loading, setLoading] = useState(!!token);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  useEffect(() => {
    if (!token) {
      setError('Invalid verification link.');
      return;
    }
    api.verifyEmailToken(token).then((res) => {
      if (res.error) setError(typeof res.error === 'string' ? res.error : 'Verification failed');
      else setSuccess('Email verified successfully. You can now log in.');
      setLoading(false);
    });
  }, [token]);

  return (
    <div className="brand-auth-bg flex min-h-screen items-center justify-center px-4 py-12">
      <div className="glass-panel w-full max-w-lg overflow-hidden p-8 text-center">
        <div className="brand-accent-bar -mx-8 -mt-8 mb-6 h-1.5" />
        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-primary">
          <MailCheck className="h-6 w-6 text-white" />
        </div>
        <h2 className="text-2xl font-bold text-ink">Verify Email</h2>
        {loading ? (
          <p className="mt-4 text-sm text-slate-600">Verifying your email address…</p>
        ) : error ? (
          <p className="mt-4 text-sm text-danger">{error}</p>
        ) : (
          <p className="mt-4 text-sm text-emerald-700">{success}</p>
        )}
        <div className="mt-6">
          <Button type="button" onClick={() => { window.location.hash = '#/login'; }}>
            Go to login
          </Button>
        </div>
      </div>
    </div>
  );
}
