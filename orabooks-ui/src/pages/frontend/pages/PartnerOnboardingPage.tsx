import { useEffect, useState } from 'react';
import WpLink from '../components/WpLink';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { Copy, CheckCircle2 } from 'lucide-react';

export default function PartnerOnboardingPage() {
  const [info, setInfo] = useState<any>(null);
  const [context, setContext] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    api.frontendContext().then((res) => {
      if (!res.error) setContext((res as any).data);
    });
    api.partnerOnboarding().then((res) => {
      if (res.error) setError(res.error);
      else setInfo((res as any).data);
      setLoading(false);
    });
  }, []);

  const copy = async () => {
    if (!info?.partner_code) return;
    await navigator.clipboard.writeText(info.partner_code);
    setCopied(true);
    void api.partnerCodeCopied('onboarding');
    setTimeout(() => setCopied(false), 2000);
  };

  const organization = context?.organization || {
    name: info?.org_name || info?.organization_name,
    organization_type: 'partner',
    status: info?.org_status,
    tier: 'partner',
  };

  return (
    <ClientShell
      title="Partner Onboarding"
      eyebrow="Referral setup"
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
            <div className="brand-accent-bar h-1.5" />
            <div className="p-8">
              <div className="space-y-4">
                <div>
                  <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Your Partner Code</label>
                  <div className="mt-2 flex gap-2">
                    <input
                      readOnly
                      value={info?.partner_code || ''}
                      className="flex-1 rounded-lg border border-border bg-slate-50 px-3.5 py-2.5 font-mono text-sm text-ink"
                    />
                    <Button variant="secondary" onClick={copy} className="whitespace-nowrap">
                      {copied ? <CheckCircle2 className="h-4 w-4 text-success" /> : <Copy className="h-4 w-4" />}
                      {copied ? 'Copied' : 'Copy Code'}
                    </Button>
                  </div>
                </div>

                <div className="rounded-xl border border-border bg-slate-50 p-4 text-sm text-slate-700">
                  <p><span className="font-semibold text-ink">Type:</span> {info?.partner_type || 'individual'}</p>
                  {info?.organization_name && (
                    <p><span className="font-semibold text-ink">Organization:</span> {info.organization_name}</p>
                  )}
                  <p><span className="font-semibold text-ink">Code Status:</span> {info?.code_status || info?.status}</p>
                </div>

                {info?.status_message && (
                  <div className="rounded-xl border border-primary/20 bg-primary/10 p-4 text-sm font-medium text-primary-dark">
                    {info.status_message}
                  </div>
                )}

                <div className="flex items-center justify-between rounded-xl border border-border bg-white p-4">
                  <span className="text-sm font-medium text-slate-700">Status</span>
                  <StatusBadge status={info?.code_status || info?.status} />
                </div>

                <WpLink to="/dashboard">
                  <Button className="w-full">Continue to Dashboard</Button>
                </Link>
              </div>
            </div>
          </div>
        </div>
      )}
    </ClientShell>
  );
}

function StatusBadge({ status }: { status?: string }) {
  const map: Record<string, string> = {
    pending_review: 'bg-amber-50 text-amber-700 border-amber-200',
    active: 'bg-success/10 text-success border-success/20',
    disabled: 'bg-red-50 text-red-700 border-red-200',
    inactive: 'bg-slate-100 text-slate-600 border-slate-200',
  };
  const cls = map[status || ''] || 'bg-slate-100 text-slate-600 border-slate-200';
  return <span className={`badge border ${cls}`}>{status || 'unknown'}</span>;
}
