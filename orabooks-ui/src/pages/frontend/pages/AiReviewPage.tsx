import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { AlertTriangle, Bot, RefreshCw, ShieldCheck } from 'lucide-react';

export default function AiReviewPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = async () => {
    setLoading(true);
    setError('');
    const res = await api.aiReviewDashboard();
    if (res.error) setError(res.error || 'Unable to load AI review queue.');
    else setData((res as any).data);
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  const threshold = data?.threshold ?? 70;
  const caps = data?.capabilities || {};

  return (
    <ClientShell title="AI Review Queue" eyebrow="SL-076 automated review" organization={data?.context?.organization}>
      <div className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Metric label="Escalated" value={data?.stats?.escalated ?? 0} tone="warning" />
          <Metric label="Pending AI" value={data?.stats?.pending ?? 0} />
          <Metric label="Processing" value={data?.stats?.processing ?? 0} />
          <Metric label="Open Total" value={data?.stats?.total_open ?? 0} />
        </div>

        <div className="rounded-2xl border border-primary/15 bg-primary/5 p-4 text-sm text-ink">
          <div className="flex items-start gap-3">
            <Bot className="mt-0.5 h-5 w-5 shrink-0 text-primary" />
            <p>
              Items below {threshold}% confidence or medium/high risk are queued for automated review. Escalated items
              need an approver — use <strong>Review</strong> to open the journal in Approvals.
            </p>
          </div>
        </div>

        <div className="flex justify-end">
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {error && (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>
        )}

        <QueueSection
          title="Escalated — Needs Review"
          icon={AlertTriangle}
          items={data?.escalated || []}
          loading={loading}
          emptyText="No escalated AI review items."
          showReview={caps.review}
        />

        <QueueSection
          title="Pending AI Processing"
          icon={Bot}
          items={data?.pending || []}
          loading={loading}
          emptyText="No items waiting for AI processing."
          showReview={false}
        />
      </div>
    </ClientShell>
  );
}

function QueueSection({
  title,
  icon: Icon,
  items,
  loading,
  emptyText,
  showReview,
}: {
  title: string;
  icon: typeof Bot;
  items: any[];
  loading: boolean;
  emptyText: string;
  showReview: boolean;
}) {
  return (
    <div className="glass-panel overflow-hidden">
      <div className="flex items-center gap-2 border-b border-border px-5 py-4">
        <Icon className="h-5 w-5 text-primary" />
        <h2 className="font-bold text-ink">{title}</h2>
      </div>
      <table className="min-w-full text-left text-sm">
        <thead>
          <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
            <th className="px-5 py-3 font-semibold">Journal</th>
            <th className="px-5 py-3 font-semibold">Confidence</th>
            <th className="px-5 py-3 font-semibold">Risk</th>
            <th className="px-5 py-3 font-semibold">Reason</th>
            <th className="px-5 py-3 text-right font-semibold">Amount</th>
            <th className="px-5 py-3 font-semibold">Queued</th>
            {showReview && <th className="px-5 py-3 font-semibold">Action</th>}
          </tr>
        </thead>
        <tbody className="divide-y divide-border">
          {loading ? (
            <tr>
              <td colSpan={showReview ? 7 : 6} className="px-5 py-8 text-center text-slate-500">
                Loading...
              </td>
            </tr>
          ) : items.length === 0 ? (
            <tr>
              <td colSpan={showReview ? 7 : 6} className="px-5 py-8 text-center text-sm text-slate-500">
                {emptyText}
              </td>
            </tr>
          ) : (
            items.map((item) => (
              <tr key={item.id} className="hover:bg-slate-50/70">
                <td className="px-5 py-3 font-semibold text-ink">
                  {item.journal_number || (item.journal_id ? `Journal #${item.journal_id}` : item.resource_type)}
                </td>
                <td className="px-5 py-3">
                  <ConfidenceBadge value={item.confidence_score} />
                </td>
                <td className="px-5 py-3">
                  <RiskBadge level={item.risk_level} />
                </td>
                <td className="max-w-xs px-5 py-3 text-slate-600">{item.explanation || item.escalation_reason || '—'}</td>
                <td className="px-5 py-3 text-right font-bold text-ink">{money(item.total_amount)}</td>
                <td className="px-5 py-3 text-slate-600">{formatDate(item.created_at)}</td>
                {showReview && (
                  <td className="px-5 py-3">
                    {item.journal_id ? (
                      <Link to="/approvals">
                        <Button size="sm">
                          <ShieldCheck className="h-3.5 w-3.5" />
                          Review
                        </Button>
                      </Link>
                    ) : item.resource_type === 'expense' ? (
                      <Link to="/expenses">
                        <Button size="sm">
                          <ShieldCheck className="h-3.5 w-3.5" />
                          Review
                        </Button>
                      </Link>
                    ) : (
                      <span className="text-xs text-slate-500">N/A</span>
                    )}
                  </td>
                )}
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
}

function Metric({
  label,
  value,
  tone = 'default',
}: {
  label: string;
  value: string | number;
  tone?: 'default' | 'warning';
}) {
  const toneClass = tone === 'warning' ? 'border-amber-200 bg-amber-50' : 'border-border bg-white';

  return (
    <div className={`rounded-2xl border p-4 shadow-sm ${toneClass}`}>
      <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</p>
      <p className="mt-2 text-2xl font-black text-ink">{value}</p>
    </div>
  );
}

function ConfidenceBadge({ value }: { value: number }) {
  const tone =
    value >= 70 ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800';
  return <span className={`badge border font-mono ${tone}`}>{Number(value).toFixed(1)}%</span>;
}

function RiskBadge({ level }: { level: string }) {
  const styles: Record<string, string> = {
    low: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    medium: 'border-amber-200 bg-amber-50 text-amber-800',
    high: 'border-red-200 bg-red-50 text-red-800',
  };
  return (
    <span className={`badge border capitalize ${styles[level] || 'border-slate-200 bg-slate-50 text-slate-700'}`}>
      {level}
    </span>
  );
}

function money(value?: string | number) {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(Number(value || 0));
}

function formatDate(value: string) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString();
}
