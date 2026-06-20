import { useMemo, useState, type FormEvent } from 'react';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import { toWpUrl } from '../lib/wp-routing';
import { KeyRound } from 'lucide-react';

export default function ResetPasswordPage() {
  const token = useMemo(() => {
    const params = new URLSearchParams(window.location.search);
    return params.get('token') || '';
  }, []);

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(false);

  const submitForgot = async (e: FormEvent) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    setLoading(true);
    try {
      const res = await api.forgotPassword(email);
      if (res.error) setError(typeof res.error === 'string' ? res.error : 'Request failed');
      else setSuccess('If the email exists, a reset link has been sent.');
    } finally {
      setLoading(false);
    }
  };

  const submitReset = async (e: FormEvent) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    if (password !== confirm) {
      setError('Passwords do not match');
      return;
    }
    setLoading(true);
    try {
      const res = await api.resetPassword(token, password);
      if (res.error) setError(typeof res.error === 'string' ? res.error : 'Reset failed');
      else {
        setSuccess('Password reset successfully. You can now log in.');
        window.setTimeout(() => {
        window.location.replace(toWpUrl('/login/'));
        }, 1200);
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="brand-auth-bg flex min-h-screen items-center justify-center px-4 py-12">
      <div className="glass-panel w-full max-w-lg overflow-hidden">
        <div className="brand-accent-bar h-1.5" />
        <div className="p-8">
          <div className="mx-auto mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-primary">
            <KeyRound className="h-6 w-6 text-white" />
          </div>
          <h2 className="text-center text-2xl font-bold text-ink">Reset Password</h2>
          <p className="mt-2 text-center text-sm text-slate-600">
            {token
              ? 'Choose a new password for your account.'
              : 'Enter your email and we will send you a reset link.'}
          </p>

          {!token ? (
            <form onSubmit={submitForgot} className="mt-6 space-y-4">
              <Input
                label="Email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="you@company.com"
                required
              />
              {error && <p className="text-sm text-danger">{error}</p>}
              {success && <p className="text-sm text-emerald-700">{success}</p>}
              <Button type="submit" loading={loading} className="w-full">
                Send Reset Link
              </Button>
            </form>
          ) : (
            <form onSubmit={submitReset} className="mt-6 space-y-4">
              <Input
                label="New Password"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
                required
              />
              <Input
                label="Confirm New Password"
                type="password"
                value={confirm}
                onChange={(e) => setConfirm(e.target.value)}
                placeholder="••••••••"
                required
              />
              {error && <p className="text-sm text-danger">{error}</p>}
              {success && <p className="text-sm text-emerald-700">{success}</p>}
              <Button type="submit" loading={loading} className="w-full">
                Reset Password
              </Button>
            </form>
          )}

          <p className="mt-6 text-center text-sm">
            <a href="/login/" className="font-medium text-primary hover:text-primary-dark">
              Back to login
            </a>
          </p>
        </div>
      </div>
    </div>
  );
}
