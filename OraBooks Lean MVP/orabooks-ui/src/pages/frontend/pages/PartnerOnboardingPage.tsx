import { useEffect, useState, type ReactNode } from 'react';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { toWpUrl } from '../lib/wp-routing';
import { CheckCircle2, Copy, HelpCircle } from 'lucide-react';

const PARTNER_CODE_TOOLTIP =
  'Your unique partner code. Share this code with customers. They enter it during signup.';
const COPY_CODE_TOOLTIP = 'Copy code to clipboard. (Audited)';
const TYPE_ORG_TOOLTIP =
  'Your partner type and organization name (from registration).';
const CONTINUE_TOOLTIP = 'Go to your partner dashboard.';

export default function PartnerOnboardingPage() {
  const [info, setInfo] = useState<any>(null);
  const [context, setContext] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [copied, setCopied] = useState(false);
  const [continuing, setContinuing] = useState(false);

  useEffect(() => {
    void (async () => {
      const ctx = await api.frontendContext();
      if (ctx.error) {
        setError(ctx.error);
        setLoading(false);
        return;
      }

      const session = (ctx as any).data;
      setContext(session);

      if (!session?.is_partner) {
        setError('Access denied. Partner accounts only.');
        setLoading(false);
        return;
      }

      const res = await api.partnerOnboarding();
      if (res.error) setError(res.error);
      else setInfo((res as any).data);
      setLoading(false);
    })();
  }, []);

  const copy = async () => {
    if (!info?.partner_code) return;
    await navigator.clipboard.writeText(info.partner_code);
    setCopied(true);
    void api.partnerCodeCopied('onboarding');
    setTimeout(() => setCopied(false), 2000);
  };

  const continueToDashboard = async () => {
    setContinuing(true);
    setError('');
    const res = await api.partnerOnboardingComplete();
    if (res.error) {
      setError(res.error);
      setContinuing(false);
      return;
    }
    const redirect = (res as any).data?.redirect_to || '/partner-program';
    window.location.replace(toWpUrl(redirect));
  };

  const organization = context?.organization || {
    name: info?.org_name || info?.organization_name,
    organization_type: 'partner',
    status: info?.org_status,
    tier: 'partner',
  };

  const typeLabel = info?.partner_type_label || formatPartnerType(info?.partner_type);
  const typeOrgLine = info?.organization_name
    ? `Type: ${typeLabel} | Organization: ${info.organization_name}`
    : `Type: ${typeLabel}`;

  return (
    <ClientShell
      title="Partner Onboarding"
      eyebrow="Partner program"
      organization={organization}
      isPartner
    >
      {loading ? (
        <div className="glass-panel p-6 text-sm text-slate-500">Loading…</div>
      ) : error ? (
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>
      ) : (
        <div className="mx-auto max-w-2xl">
          <div className="glass-panel overflow-hidden">
            <div className="p-8">
              <div className="space-y-5">
                <div>
                  <FieldLabel tooltip={PARTNER_CODE_TOOLTIP}>Your Partner Code</FieldLabel>
                  <div className="mt-2 flex gap-2">
                    <input
                      readOnly
                      value={info?.partner_code || ''}
                      title={PARTNER_CODE_TOOLTIP}
                      className="flex-1 rounded-lg border border-border bg-slate-50 px-3.5 py-2.5 font-mono text-sm text-ink"
                    />
                    <Button
                      variant="secondary"
                      onClick={copy}
                      title={COPY_CODE_TOOLTIP}
                      className="whitespace-nowrap"
                    >
                      {copied ? <CheckCircle2 className="h-4 w-4 text-success" /> : <Copy className="h-4 w-4" />}
                      {copied ? 'Copied' : 'Copy Code'}
                    </Button>
                  </div>
                </div>

                <div
                  className="rounded-xl border border-border bg-slate-50 p-4 text-sm text-slate-700"
                  title={TYPE_ORG_TOOLTIP}
                >
                  <p className="font-medium text-ink">{typeOrgLine}</p>
                </div>

                {info?.status_message && (
                  <div className="rounded-xl border border-primary/20 bg-primary/10 p-4 text-sm font-medium text-primary-dark">
                    {info.status_message}
                  </div>
                )}

                <Button
                  className="w-full"
                  onClick={continueToDashboard}
                  disabled={continuing}
                  title={CONTINUE_TOOLTIP}
                >
                  {continuing ? 'Continuing…' : 'Continue to Dashboard'}
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}
    </ClientShell>
  );
}

function FieldLabel({ children, tooltip }: { children: ReactNode; tooltip: string }) {
  return (
    <div className="flex items-center gap-1.5">
      <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">{children}</label>
      <span title={tooltip} className="inline-flex text-slate-400">
        <HelpCircle className="h-3.5 w-3.5" aria-hidden />
      </span>
    </div>
  );
}

function formatPartnerType(type?: string) {
  if (!type) return 'Individual';
  return type.charAt(0).toUpperCase() + type.slice(1).replace(/_/g, ' ');
}
