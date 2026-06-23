import { useEffect, useState } from 'react';
import WpLink from '../components/WpLink';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { CheckCircle2, ExternalLink, RefreshCw, Sparkles, XCircle } from 'lucide-react';

type LiveCheck = {
 id: string;
 label: string;
 ok: boolean;
 detail?: string;
 action_url?: string;
};

type LiveCheckResult = {
 ok?: boolean;
 checks?: LiveCheck[];
 environment?: Record<string, unknown>;
 manual_steps?: { step: string; title: string; detail: string; url?: string }[];
};

export default function ClassificationLiveTestPage {
 const [context, setContext] = useState<any>(null);
 const [result, setResult] = useState<LiveCheckResult | null>(null);
 const [loading, setLoading] = useState(true);
 const [running, setRunning] = useState(false);
 const [error, setError] = useState('');

 const load = async => {
 setLoading(true);
 setError('');
 const ctx = await api.frontendContext;
 if (ctx.error) {
 setError(ctx.error);
 setLoading(false);
 return;
 }
 setContext((ctx as any).data);
 setLoading(false);
 };

 const runLiveCheck = async => {
 setRunning(true);
 setError('');
 const res = await api.classificationLiveCheck;
 if (res.error) {
 setError(res.error);
 setResult(null);
 } else {
 setResult((res as any).data || null);
 }
 setRunning(false);
 };

 useEffect( => {
 void load;
 }, []);

 useEffect( => {
 if (context?.organization?.id) {
 void runLiveCheck;
 }
 }, [context?.organization?.id]);

 return (
 <ClientShell
 title=" Live Test"
 eyebrow="Smart Classification — production smoke test"
 organization={context?.organization}
 >
 <div className="space-y-5">
 <div className="flex items-start gap-3 rounded-xl border border-violet-200 bg-violet-50/80 p-4 text-sm text-violet-950">
 <Sparkles className="mt-0.5 h-4 w-4 shrink-0" />
 <p>
 Production smoke test for smart classification. Runs against the deployed plugin — no local
 environment required. Save PHP changes directly to the plugin folder; run <code className="text-xs">build-live.cmd</code>{' '}
 after UI edits, then hard-refresh this page.
 </p>
 </div>

 <div className="flex flex-wrap gap-2">
 <Button onClick={ => void runLiveCheck} disabled={running || loading}>
 <RefreshCw className={`h-4 w-4 ${running ? 'animate-spin': ''}`} />
 {running ? 'Checking…': 'Run live check'}
 </Button>
 <WpLink to="/expenses">
 <Button variant="secondary" size="sm">
 Expenses
 </Button>
 </WpLink>
 <WpLink to="/invoices">
 <Button variant="secondary" size="sm">
 Invoices
 </Button>
 </WpLink>
 <WpLink to="/tax-settings">
 <Button variant="secondary" size="sm">
 Tax &amp; Rules
 </Button>
 </WpLink>
 </div>

 {error && (
 <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>
 )}

 {result && (
 <div className="glass-panel overflow-hidden">
 <div className="border-b border-border px-5 py-4">
 <div className="flex flex-wrap items-center gap-2">
 {result.ok ? (
 <CheckCircle2 className="h-5 w-5 text-emerald-600" />
 ): (
 <XCircle className="h-5 w-5 text-red-600" />
 )}
 <h2 className="font-bold text-ink">
 {result.ok ? ' live checks passed': 'Some live checks need attention'}
 </h2>
 </div>
 {result.environment && (
 <p className="mt-2 text-xs text-slate-500">
 Org #{String(result.environment.org_id ?? '—')} · Rules over AI:{' '}
 {result.environment.rule_precedence_ai ? 'on': 'off'} · UI bundle:{' '}
 {String(result.environment.react_bundle_at ?? 'unknown')}
 </p>
 )}
 </div>
 <table className="min-w-full text-left text-sm">
 <thead>
 <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
 <th className="px-5 py-3 font-semibold">Check</th>
 <th className="px-5 py-3 font-semibold">Status</th>
 <th className="px-5 py-3 font-semibold">Detail</th>
 <th className="px-5 py-3 font-semibold" />
 </tr>
 </thead>
 <tbody>
 {(result.checks || []).map((check) => (
 <tr key={check.id} className="border-b border-border/70">
 <td className="px-5 py-3 font-medium text-ink">{check.label}</td>
 <td className="px-5 py-3">
 <span
 className={`badge border ${check.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-800': 'border-red-200 bg-red-50 text-red-800'}`}
 >
 {check.ok ? 'OK': 'FAIL'}
 </span>
 </td>
 <td className="px-5 py-3 text-slate-600">{check.detail || '—'}</td>
 <td className="px-5 py-3 text-right">
 {check.action_url && (
 <WpLink to={check.action_url}>
 <Button variant="secondary" size="sm">
 <ExternalLink className="h-3.5 w-3.5" />
 Open
 </Button>
 </WpLink>
 )}
 </td>
 </tr>
 ))}
 </tbody>
 </table>
 </div>
 )}

 <div className="glass-panel p-5">
 <h2 className="font-bold text-ink">Manual test steps (live)</h2>
 <ol className="mt-4 space-y-4">
 {(result?.manual_steps || []).map((step) => (
 <li key={step.step} className="flex gap-3 text-sm">
 <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10 font-bold text-primary">
 {step.step}
 </span>
 <div>
 <p className="font-semibold text-ink">{step.title}</p>
 <p className="mt-0.5 text-slate-600">{step.detail}</p>
 {step.url && (
 <WpLink to={step.url} className="mt-1 inline-block text-xs font-medium text-primary underline">
 Go to page
 </WpLink>
 )}
 </div>
 </li>
 ))}
 </ol>
 </div>
 </div>
 </ClientShell>
 );
}
