import { useState, useEffect, type FormEvent } from 'react';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import { getNetworkAuthUrl, storePendingInviteToken } from '../lib/auth-routing';
import { UserPlus } from 'lucide-react';

type InvitePreview = {
  email?: string;
  role?: string;
  org_name?: string;
};

export default function RegisterPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [userType, setUserType] = useState<'customer' | 'partner'>('customer');
  const [partnerType, setPartnerType] = useState('individual');
  const [orgName, setOrgName] = useState('');
  const [partnerCode, setPartnerCode] = useState('');
  const [acceptTerms, setAcceptTerms] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [invitePreview, setInvitePreview] = useState<InvitePreview | null>(null);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const invitedEmail = params.get('email') || '';
    const inviteToken = params.get('token') || '';

    if (inviteToken) {
      storePendingInviteToken(inviteToken);
      void api.previewInvite(inviteToken).then((res) => {
        if (!res.error) {
          const preview = (res as any).data as InvitePreview;
          setInvitePreview(preview);
          if (preview?.email) {
            setEmail(preview.email);
          }
        }
      });
      return;
    }

    const storedToken = window.sessionStorage.getItem('orabooks_pending_invite_token') || '';
    if (storedToken) {
      void api.previewInvite(storedToken).then((res) => {
        if (!res.error) {
          const preview = (res as any).data as InvitePreview;
          setInvitePreview(preview);
          if (preview?.email) {
            setEmail(preview.email);
          }
        }
      });
    }

    if (invitedEmail) {
      setEmail(invitedEmail);
    }
  }, []);

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    setError('');
    const normalizedEmail = email.trim();

    if (!normalizedEmail || !password) {
      return setError('Email and password are required.');
    }
    if (password !== confirm) return setError('Passwords do not match');
    if (invitePreview?.email && normalizedEmail.toLowerCase() !== invitePreview.email.trim().toLowerCase()) {
      return setError('Use the same email address that received the team invitation.');
    }
    if (userType === 'partner' && !acceptTerms) {
      return setError('Partner terms must be accepted.');
    }
    if (userType === 'partner' && needsOrg && !orgName.trim()) {
      return setError('Organization name is required for this partner type.');
    }

    setLoading(true);
    try {
      const res = await api.register({
        email: normalizedEmail,
        password,
        user_type: userType,
        partner_type: userType === 'partner' ? partnerType : 'individual',
        organization_name: userType === 'partner' ? orgName.trim() : '',
        partner_code: userType === 'customer' ? partnerCode.trim() : '',
        accept_terms: userType === 'partner' ? (acceptTerms ? 1 : 0) : 1,
        terms_version: '1.0',
      });
      if (res.error) setError(typeof res.error === 'string' ? res.error : 'Registration failed');
      else {
        window.sessionStorage.setItem('orabooks_last_registered_email', normalizedEmail);
        window.location.replace(getNetworkAuthUrl('verify-email'));
      }
    } finally {
      setLoading(false);
    }
  };

  const googleRegister = () => {
    setError('');
    if (userType === 'partner' && !acceptTerms) {
      setError('Partner terms must be accepted.');
      return;
    }
    if (userType === 'partner' && needsOrg && !orgName.trim()) {
      setError('Organization name is required for this partner type.');
      return;
    }

    api.oidcInitiate({
      user_type: userType,
      partner_type: partnerType,
      organization_name: orgName,
      partner_code: userType === 'customer' ? partnerCode : '',
      accept_terms: userType === 'partner' ? acceptTerms : false,
      terms_version: '1.0',
    }).then((res) => {
      if (res.error) {
        setError(typeof res.error === 'string' ? res.error : 'Google sign-up is unavailable.');
        return;
      }
      if ((res as any).data?.auth_url) window.location.href = (res as any).data.auth_url;
    });
  };

  const needsOrg = ['agency', 'reseller', 'strategic_partner'].includes(partnerType);
  const invitedFlow = Boolean(invitePreview?.org_name || invitePreview?.role);

  return (
    <div className="brand-auth-bg orabooks-register-auth flex min-h-screen items-center justify-center px-4 py-12">
      <div className="glass-panel orabooks-register-panel w-full max-w-2xl overflow-hidden">
        <div className="orabooks-register-panel-inner p-6 sm:p-8">
          <div className="mx-auto mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-primary">
            <UserPlus className="h-6 w-6 text-white" />
          </div>

          <div className="text-center">
            <h2 className="text-2xl font-bold text-ink sm:text-3xl">Create Account</h2>
            {invitedFlow ? (
              <div className="orabooks-register-invite mt-4 rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm text-slate-700">
                <p className="font-semibold text-ink">Team invitation</p>
                <p className="mt-1">
                  You were invited to join <strong>{invitePreview?.org_name || 'a workspace'}</strong>
                  {invitePreview?.role ? (
                    <>
                      {' '}
                      as <strong className="capitalize">{invitePreview.role}</strong>
                    </>
                  ) : null}
                  .
                </p>
                <p className="mt-2 text-slate-600">
                  Create your account first, verify your email, then log in and open this invitation link again to join the team.
                  You do not need to create your own company workspace.
                </p>
              </div>
            ) : (
              <p className="mt-2 text-sm text-slate-600">Start your OraBooks journey.</p>
            )}
          </div>

          <form onSubmit={submit} className="orabooks-register-form mt-6 space-y-5">
            <section className="orabooks-register-section grid gap-4 md:grid-cols-2">
              <Input label="Email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required readOnly={Boolean(invitePreview?.email)} />
              <Input label="Password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} required minLength={8} hint="Min 8 characters with mixed case, number, special char." />
              <Input label="Confirm Password" type="password" value={confirm} onChange={(e) => setConfirm(e.target.value)} required />
              {!invitedFlow && (
                <div className="orb-register-select-group">
                  <label className="mb-1.5 block text-sm font-medium text-slate-700">I am a:</label>
                  <select
                    value={userType}
                    onChange={(e) => setUserType(e.target.value as any)}
                    className="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                  >
                    <option value="customer">Customer</option>
                    <option value="partner">Partner</option>
                  </select>
                </div>
              )}
            </section>

            {userType === 'partner' && !invitedFlow && (
              <section className="orabooks-register-section rounded-2xl border border-border bg-slate-50/60 p-4 sm:p-5">
                <div className="grid gap-4 md:grid-cols-2">
                  <div>
                    <label className="mb-1.5 block text-sm font-medium text-slate-700">Partner Type</label>
                    <select
                      value={partnerType}
                      onChange={(e) => setPartnerType(e.target.value)}
                      className="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                    >
                      <option value="individual">Individual</option>
                      <option value="accountant">Accountant</option>
                      <option value="agency">Agency</option>
                      <option value="reseller">Reseller</option>
                      <option value="strategic_partner">Strategic Partner</option>
                    </select>
                  </div>
                  {needsOrg && (
                    <Input label="Organization Name" value={orgName} onChange={(e) => setOrgName(e.target.value)} required />
                  )}
                </div>
              </section>
            )}

            {userType === 'customer' && !invitedFlow && (
              <section className="orabooks-register-section rounded-2xl border border-border bg-slate-50/60 p-4 sm:p-5">
                <Input label="Partner Code (Optional)" value={partnerCode} onChange={(e) => setPartnerCode(e.target.value)} placeholder="PARTNER-XXXX" />
              </section>
            )}

            {userType === 'partner' && !invitedFlow && (
              <section className="orabooks-register-section rounded-2xl border border-border bg-slate-50/60 p-4 sm:p-5">
                <label className="flex items-start gap-3 text-sm text-slate-700">
                  <input type="checkbox" checked={acceptTerms} onChange={(e) => setAcceptTerms(e.target.checked)} className="mt-1 h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary" />
                  <span>I agree to Partner Terms v1.0</span>
                </label>
              </section>
            )}

            {error && <p className="text-sm text-danger">{error}</p>}

            <Button type="submit" loading={loading} className="w-full">
              {invitedFlow ? 'Create account' : 'Create Account'}
            </Button>
          </form>

          <div className="my-6 flex items-center gap-3">
            <div className="h-px flex-1 bg-border" />
            <span className="text-xs font-medium text-slate-500">or continue with</span>
            <div className="h-px flex-1 bg-border" />
          </div>

          <button
            type="button"
            onClick={googleRegister}
            className="flex w-full items-center justify-center gap-2 rounded-lg border border-border bg-white px-4 py-2.5 text-sm font-medium text-ink shadow-sm transition hover:bg-muted active:scale-[0.98]"
          >
            Continue with Google
          </button>

          <p className="mt-6 text-center text-sm text-slate-600">
            Already have an account? <a href={getNetworkAuthUrl('login')} className="font-semibold text-primary hover:text-primary-dark">Sign in</a>
          </p>
        </div>
      </div>
    </div>
  );
}
