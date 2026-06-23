import { useEffect, useState } from 'react';
import WpLink from '../components/WpLink';

import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { AlertTriangle, Bot, CheckCircle2, RefreshCw, ShieldCheck } from 'lucide-react';

export default function AiReviewPage {
 const [data, setData] = useState<any>(null);
 const [resolvedItems, setResolvedItems] = useState<any[]>([]);
 const [loading, setLoading] = useState(true);
 const [error, setError] = useState('');
 const [success, setSuccess] = useState('');
 const [resolvingId, setResolvingId] = useState<number | null>(null);

 const orgId = data?.context?.organization?.id;

 const load = async => {
 setLoading(true);
 setError('');
 setSuccess('');
 const res = await api.aiReviewDashboard;
 if (res.error) {
 setError(res.error || 'Unable to load AI review queue.');
 setLoading(false);
 return;
 }

 const payload = (res as any).data;
 setData(payload);

 const resolvedOrgId = payload?.context?.organization?.id;
 if (resolvedOrgId) {
 const resolvedRes = await api.aiReviewList(resolvedOrgId, { status: 'resolved', limit: 10 });
 if (!resolvedRes.error) {
 setResolvedItems((resolvedRes as any).data?.items || []);
 }
 }

 setLoading(false);
 };

 useEffect( => {
 void load;
 }, []);

 const handleDismiss = async (item: any) => {
 if (!orgId) return;
 setResolvingId(item.id);
 setError('');
 setSuccess('');

 const params = item.journal_id
 ? { journal_id: item.journal_id }
: { queue_id: item.id };

 const res = await api.aiReviewResolve(orgId, params);
 if (res.error) {
 setError(res.error);
 } else {
 setSuccess((res as any).data?.message || 'Item dismissed from AI review queue.');
 await load;
 }
 setResolvingId(null);
 };

 const threshold = data?.threshold ?? 70;
 const caps = data?.capabilities || {};

 return (
 <ClientShell title="AI Review Queue" eyebrow="automated review" organization={data?.context?.organization}>
 <div className="space-y-5">
 <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
 <Metric label="Escalated" value={data?.stats?.escalated ?? 0} tone="warning" />
 <Metric label="Pending AI" value={data?.stats?.pending ?? 0} />
 <Metric label="Processing" value={data?.stats?.processing ?? 0} />
 <Metric label="Open Total" value={data?.stats?.total_open ?? 0} />
 <Metric label="Recently Resolved" value={data?.stats?.resolved ?? 0} tone="success" />
 </div>

 <div className="rounded-2xl border border-primary/15 bg-primary/5 p-4 text-sm text-ink">
 <div className="flex items-start gap-3">
 <Bot className="mt-0.5 h-5 w-5 shrink-0 text-primary" />
 <p>
 Items below {threshold}% confidence or medium/high risk are queued for automated review. Escalated items
 need an approver — use <strong>Review</strong> to open the journal in Approvals. Approving or rejecting a
 journal also resolves its queue entry.
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
 {success && (
 <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
 {success}
 </div>
 )}

 <QueueSection
 title="Escalated — Needs Review"
 icon={AlertTriangle}
 items={data?.escalated || []}
 loading={loading}
 emptyText="No escalated AI review items."
 showReview={caps.review}
 showDismiss={caps.review}
 threshold={threshold}
 resolvingId={resolvingId}
 onDismiss={handleDismiss}
 />

 <QueueSection
 title="Pending AI Processing"
 icon={Bot}
 items={data?.pending || []}
 loading={loading}
 emptyText="No items waiting for AI processing."
 showReview={false}
 showDismiss={false}
 threshold={threshold}
 resolvingId={resolvingId}
 onDismiss={handleDismiss}
 />

 {resolvedItems.length > 0 && (
 <QueueSection
 title="Recently Resolved"
 icon={CheckCircle2}
 items={resolvedItems}
 loading={loading}
 emptyText="No recently resolved items."
 showReview={false}
 showDismiss={false}
 threshold={threshold}
 resolvingId={resolvingId}
 onDismiss={handleDismiss}
 />
 )}
 </div>
 </ClientShell>
 );
}

function reviewLink(item: any) {
 if (item.journal_id) {
 return `/approvals?journal_id=${item.journal_id}`;
 }
 if (item.resource_type === 'expense') {
 return '/expenses';
 }
 if (item.resource_type === 'voice_input') {
 return '/voice';
 }
 if (item.resource_type === 'csv_import' && item.resource_id) {
 return `/csv-imports?import_id=${item.resource_id}`;
 }
 return null;
}

function QueueSection({
 title,
 icon: Icon,
 items,
 loading,
 emptyText,
 showReview,
 showDismiss,
 threshold,
 resolvingId,
 onDismiss,
}: {
 title: string;
 icon: typeof Bot;
 items: any[];
 loading: boolean;
 emptyText: string;
 showReview: boolean;
 showDismiss: boolean;
 threshold: number;
 resolvingId: number | null;
 onDismiss: (item: any) => void;
}) {
 const hasActions = showReview || showDismiss;

 return (
 <div className="glass-panel overflow-hidden">
 <div className="flex items-center gap-2 border-b border-border px-5 py-4">
 <Icon className="h-5 w-5 text-primary" />
 <h2 className="font-bold text-ink">{title}</h2>
 </div>
 <table className="min-w-full text-left text-sm">
 <thead>
 <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
 <th className="px-5 py-3 font-semibold">Resource</th>
 <th className="px-5 py-3 font-semibold">Confidence</th>
 <th className="px-5 py-3 font-semibold">Risk</th>
 <th className="px-5 py-3 font-semibold">Reason</th>
 <th className="px-5 py-3 text-right font-semibold">Amount</th>
 <th className="px-5 py-3 font-semibold">Queued</th>
 {hasActions && <th className="px-5 py-3 font-semibold">Action</th>}
 </tr>
 </thead>
 <tbody className="divide-y divide-border">
 {loading ? (
 <tr>
 <td colSpan={hasActions ? 7: 6} className="px-5 py-8 text-center text-slate-500">
 Loading...
 </td>
 </tr>
 ): items.length === 0 ? (
 <tr>
 <td colSpan={hasActions ? 7: 6} className="px-5 py-8 text-center text-sm text-slate-500">
 {emptyText}
 </td>
 </tr>
 ): (
 items.map((item) => {
 const href = reviewLink(item);
 return (
 <tr key={item.id} className="hover:bg-slate-50/70">
 <td className="px-5 py-3 font-semibold text-ink">
 {item.journal_number || (item.journal_id ? `Journal #${item.journal_id}`: formatResource(item))}
 </td>
 <td className="px-5 py-3">
 <ConfidenceBadge value={item.confidence_score} threshold={threshold} explanation={item.explanation} />
 </td>
 <td className="px-5 py-3">
 <RiskBadge level={item.risk_level} />
 </td>
 <td className="max-w-xs px-5 py-3 text-slate-600" title={item.explanation || undefined}>
 {item.explanation || item.escalation_reason || '—'}
 </td>
 <td className="px-5 py-3 text-right font-bold text-ink">{money(item.total_amount)}</td>
 <td className="px-5 py-3 text-slate-600">{formatDate(item.created_at)}</td>
 {hasActions && (
 <td className="px-5 py-3">
 <div className="flex flex-wrap gap-2">
 {showReview && href && (
 <WpLink to={href}>
 <Button size="sm">
 <ShieldCheck className="h-3.5 w-3.5" />
 Review
 </Button>
 </WpLink>
 )}
 {showReview && !href && (
 <span className="text-xs text-slate-500">N/A</span>
 )}
 {showDismiss && item.status === 'escalated' && (
 <Button
 variant="secondary"
 size="sm"
 disabled={resolvingId === item.id}
 onClick={ => void onDismiss(item)}
 >
 Dismiss
 </Button>
 )}
 </div>
 </td>
 )}
 </tr>
 );
 })
 )}
 </tbody>
 </table>
 </div>
 );
}

function formatResource(item: any) {
 const labels: Record<string, string> = {
 csv_import: 'CSV Import',
 expense: 'Expense',
 voice_input: 'Voice Input',
 journal: 'Journal',
 };
 const label = labels[item.resource_type] || item.resource_type;
 return item.resource_id ? `${label} #${item.resource_id}`: label;
}

function Metric({
 label,
 value,
 tone = 'default',
}: {
 label: string;
 value: string | number;
 tone?: 'default' | 'warning' | 'success';
}) {
 const toneClass =
 tone === 'warning'
 ? 'border-amber-200 bg-amber-50'
: tone === 'success'
 ? 'border-emerald-200 bg-emerald-50'
: 'border-border bg-white';

 return (
 <div className={`rounded-2xl border p-4 shadow-sm ${toneClass}`}>
 <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</p>
 <p className="mt-2 text-2xl font-black text-ink">{value}</p>
 </div>
 );
}

function ConfidenceBadge({
 value,
 threshold,
 explanation,
}: {
 value: number;
 threshold: number;
 explanation?: string;
}) {
 const tone =
 value >= threshold ? 'border-emerald-200 bg-emerald-50 text-emerald-800': 'border-amber-200 bg-amber-50 text-amber-800';
 const tooltip = explanation || `Confidence ${Number(value).toFixed(1)}% (threshold ${threshold}%)`;
 return (
 <span className={`badge border font-mono ${tone}`} title={tooltip}>
 {Number(value).toFixed(1)}%
 </span>
 );
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
 if (Number.isNaN(date.getTime)) return value;
 return date.toLocaleString;
}
