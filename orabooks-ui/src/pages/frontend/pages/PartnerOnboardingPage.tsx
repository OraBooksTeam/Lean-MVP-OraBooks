import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import { api } from '../api';
import { Copy, CheckCircle2 } from 'lucide-react';

export default function PartnerOnboardingPage() {
  const [info, setInfo] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    api.getPartnerInfo().then((res) => {
      if (!res.error) setInfo((res as any).data);
      setLoading(false);
    });
  }, []);

  const copy = () => {
    if (!info?.partner_code) return;
    navigator.clipboard.writeText(info.partner_code);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-50">
        <div className="glass-panel p-6 text-sm text-slate-500">Loading…</div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-50 p-6">
      <div className="mx-auto max-w-2xl space-y-6">
        <div className="glass-panel p-8">
          <h2 className="text-2xl font-bold text-ink">Partner Onboarding</h2>
          <div className="mt-6 space-y-4">
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
              <p><span className="font-semibold text-ink">Type:</span> {info?.partner_type}</p>
              {info?.organization_name && <p><span className="font-semibold text-ink">Organization:</span> {info.organization_name}</p>}
              <p><span className="font-semibold text-ink">Active Customers:</span> {info?.active_customers ?? 0}</p>
              <p><span className="font-semibold text-ink">Total Attributions:</span> {info?.total_attributions ?? 0}</p>
            </div>

            <div className="flex items-center justify-between rounded-xl border border-border bg-white p-4">
              <span className="text-sm font-medium text-slate-700">Status</span>
              <StatusBadge status={info?.status} />
            </div>

            <Button onClick={() => (window.location.href = '/dashboard')} className="w-full">Continue to Dashboard</Button>
          </div>
        </div>
      </div>
    </div>
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
