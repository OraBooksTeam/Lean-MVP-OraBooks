import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import AdminPageShell from '@/components/AdminPageShell';
import { api } from '../api';
import { CheckCircle2, RefreshCw, ShieldCheck, XCircle } from 'lucide-react';

interface PlatformSettings {
 block_same_email_domain: boolean;
 partner_commission_for_staff_viewer: boolean;
 audit_retention_days: number;
 jwt_expiry: number;
 refresh_token_expiry: number;
}

const defaults: PlatformSettings = {
 block_same_email_domain: false,
 partner_commission_for_staff_viewer: false,
 audit_retention_days: 365,
 jwt_expiry: 900,
 refresh_token_expiry: 604800,
};

type DeployCheck = {
 id: string;
 label: string;
 ok: boolean;
 detail?: string;
};

type DeployChecksResult = {
 ok: boolean;
 checks: DeployCheck[];
 timestamp?: string;
 environment?: Record<string, unknown>;
};

export default function AdminSettings {
 const [settings, setSettings] = useState<PlatformSettings>(defaults);
 const [loading, setLoading] = useState(true);
 const [saving, setSaving] = useState(false);
 const [error, setError] = useState('');
 const [message, setMessage] = useState('');
 const [deployChecks, setDeployChecks] = useState<DeployChecksResult | null>(null);
 const [deployLoading, setDeployLoading] = useState(false);
 const [deployError, setDeployError] = useState('');
 const [deployRepairing, setDeployRepairing] = useState(false);
 const [deployRepairMessage, setDeployRepairMessage] = useState('');

 useEffect( => {
 setLoading(true);
 setError('');
 api.platformSettingsGet.then((res) => {
 if (res.error) {
 setError(res.error);
 } else if (res.data) {
 setSettings({
 block_same_email_domain: Boolean(res.data.block_same_email_domain),
 partner_commission_for_staff_viewer: Boolean(res.data.partner_commission_for_staff_viewer),
 audit_retention_days: Number(res.data.audit_retention_days) || defaults.audit_retention_days,
 jwt_expiry: Number(res.data.jwt_expiry) || defaults.jwt_expiry,
 refresh_token_expiry: Number(res.data.refresh_token_expiry) || defaults.refresh_token_expiry,
 });
 }
 setLoading(false);
 });
 }, []);

 const handleSubmit = (event: React.FormEvent) => {
 event.preventDefault;
 setSaving(true);
 setError('');
 setMessage('');
 api.platformSettingsSave(settings).then((res) => {
 if (res.error) {
 setError(res.error);
 } else {
 setMessage('Settings saved.');
 if (res.data) {
 setSettings({
 block_same_email_domain: Boolean(res.data.block_same_email_domain),
 partner_commission_for_staff_viewer: Boolean(res.data.partner_commission_for_staff_viewer),
 audit_retention_days: Number(res.data.audit_retention_days),
 jwt_expiry: Number(res.data.jwt_expiry),
 refresh_token_expiry: Number(res.data.refresh_token_expiry),
 });
 }
 }
 setSaving(false);
 });
 };

 const runDeployChecks = => {
 setDeployLoading(true);
 setDeployError('');
 api.deployChecks.then((res) => {
 if (res.error) {
 setDeployError(typeof res.error === 'string' ? res.error: 'Deploy checks failed.');
 setDeployChecks(null);
 } else {
 setDeployChecks((res as { data?: DeployChecksResult }).data || null);
 }
 setDeployLoading(false);
 });
 };

 const repairDeployIssues = => {
 setDeployRepairing(true);
 setDeployError('');
 setDeployRepairMessage('');
 api.deployRepair.then((res) => {
 if (res.error) {
 setDeployError(typeof res.error === 'string' ? res.error: 'Repair failed.');
 } else {
 const payload = (res as { data?: { repaired?: string[]; checks?: DeployChecksResult } }).data;
 setDeployChecks(payload?.checks || null);
 const repaired = payload?.repaired || [];
 setDeployRepairMessage(
 repaired.length > 0
 ? `Scheduled missing crons: ${repaired.join(', ')}`
: 'No cron repairs were needed.'
 );
 }
 setDeployRepairing(false);
 });
 };

 const hasCronFailures = Boolean(
 deployChecks?.checks?.some((check) => check.id.startsWith('cron_') && !check.ok)
 );

 return (
 <AdminPageShell
 title="Platform Settings"
 description="Security, authentication, audit retention, and post-deploy verification."
 >
 {error && <p className="text-sm text-danger">{error}</p>}
 {message && <p className="text-sm text-success">{message}</p>}

 {loading ? (
 <div className="h-64 animate-pulse rounded-2xl border border-border bg-white" />
 ): (
 <form onSubmit={handleSubmit} className="glass-panel overflow-hidden">
 <div className="divide-y divide-border">
 <label className="flex flex-col gap-2 p-6 sm:flex-row sm:items-start sm:justify-between">
 <span className="text-sm font-semibold text-ink">Block same email domain</span>
 <span className="flex items-start gap-3 text-sm text-ink-secondary sm:max-w-xl">
 <input
 type="checkbox"
 className="mt-1 h-4 w-4 rounded border-border text-primary"
 checked={settings.block_same_email_domain}
 onChange={(e) =>
 setSettings((prev) => ({...prev, block_same_email_domain: e.target.checked }))
 }
 />
 Block partner attribution when customer email domain matches partner email domain
 </span>
 </label>

 <label className="flex flex-col gap-2 p-6 sm:flex-row sm:items-start sm:justify-between">
 <span className="text-sm font-semibold text-ink">Staff/viewer commission access</span>
 <span className="flex items-start gap-3 text-sm text-ink-secondary sm:max-w-xl">
 <input
 type="checkbox"
 className="mt-1 h-4 w-4 rounded border-border text-primary"
 checked={settings.partner_commission_for_staff_viewer}
 onChange={(e) =>
 setSettings((prev) => ({
...prev,
 partner_commission_for_staff_viewer: e.target.checked,
 }))
 }
 />
 Allow Staff and Viewer roles to access partner commission dashboard
 </span>
 </label>

 <label className="flex flex-col gap-2 p-6 sm:flex-row sm:items-center sm:justify-between">
 <span className="text-sm font-semibold text-ink">Audit log retention (days)</span>
 <div className="sm:max-w-xs">
 <input
 type="number"
 min={30}
 max={3650}
 className="w-full rounded-lg border border-border px-3 py-2 text-sm"
 value={settings.audit_retention_days}
 onChange={(e) =>
 setSettings((prev) => ({
...prev,
 audit_retention_days: Number(e.target.value),
 }))
 }
 />
 <p className="mt-1 text-xs text-slate-500">Days to retain audit logs before archival</p>
 </div>
 </label>

 <label className="flex flex-col gap-2 p-6 sm:flex-row sm:items-center sm:justify-between">
 <span className="text-sm font-semibold text-ink">JWT expiry (seconds)</span>
 <div className="sm:max-w-xs">
 <input
 type="number"
 min={60}
 max={86400}
 className="w-full rounded-lg border border-border px-3 py-2 text-sm"
 value={settings.jwt_expiry}
 onChange={(e) =>
 setSettings((prev) => ({...prev, jwt_expiry: Number(e.target.value) }))
 }
 />
 <p className="mt-1 text-xs text-slate-500">Default: 900 (15 minutes)</p>
 </div>
 </label>

 <label className="flex flex-col gap-2 p-6 sm:flex-row sm:items-center sm:justify-between">
 <span className="text-sm font-semibold text-ink">Refresh token expiry (seconds)</span>
 <div className="sm:max-w-xs">
 <input
 type="number"
 min={3600}
 max={2592000}
 className="w-full rounded-lg border border-border px-3 py-2 text-sm"
 value={settings.refresh_token_expiry}
 onChange={(e) =>
 setSettings((prev) => ({
...prev,
 refresh_token_expiry: Number(e.target.value),
 }))
 }
 />
 <p className="mt-1 text-xs text-slate-500">Default: 604800 (7 days)</p>
 </div>
 </label>
 </div>

 <div className="border-t border-border bg-slate-50/60 px-6 py-4">
 <Button type="submit" disabled={saving}>
 {saving ? 'Saving…': 'Save settings'}
 </Button>
 </div>
 </form>
 )}

 <section className="glass-panel mt-6 overflow-hidden" id="deploy-checks">
 <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-muted/60 px-6 py-4">
 <div className="flex items-center gap-2">
 <ShieldCheck className="h-5 w-5 text-primary" />
 <div>
 <h2 className="text-sm font-bold text-ink">Post-deploy verification</h2>
 <p className="text-xs text-slate-500">
 Confirms secrets/TLS, shared table prefix, schema, crons, and async handlers after upload.
 </p>
 </div>
 </div>
 <div className="flex flex-wrap items-center gap-2">
 <Button onClick={runDeployChecks} variant="secondary" size="sm" disabled={deployLoading || deployRepairing}>
 <RefreshCw className={`h-4 w-4 ${deployLoading ? 'animate-spin': ''}`} />
 {deployLoading ? 'Running…': 'Run checks'}
 </Button>
 {hasCronFailures && (
 <Button onClick={repairDeployIssues} size="sm" disabled={deployLoading || deployRepairing}>
 {deployRepairing ? 'Repairing…': 'Repair crons'}
 </Button>
 )}
 </div>
 </div>

 {deployRepairMessage && (
 <p className="px-6 pt-4 text-sm text-success">{deployRepairMessage}</p>
 )}

 {deployError && (
 <p className="px-6 py-4 text-sm text-danger">{deployError}</p>
 )}

 {deployChecks && (
 <div className="px-6 py-4">
 <div
 className={`mb-4 flex items-center gap-2 rounded-xl border px-4 py-3 text-sm font-semibold ${
 deployChecks.ok
 ? 'border-success/30 bg-success/10 text-success'
: 'border-danger/30 bg-danger/10 text-danger'
 }`}
 >
 {deployChecks.ok ? <CheckCircle2 className="h-4 w-4" />: <XCircle className="h-4 w-4" />}
 {deployChecks.ok ? 'All deploy checks passed': 'One or more deploy checks failed'}
 </div>

 {deployChecks.environment && (
 <div className="mb-4 grid gap-2 text-xs text-slate-600 sm:grid-cols-2 lg:grid-cols-3">
 {Object.entries(deployChecks.environment).map(([key, value]) => (
 <p key={key}>
 <span className="font-semibold text-ink">{key}:</span>{' '}
 {value === null || value === undefined || value === '' ? '—': String(value)}
 </p>
 ))}
 </div>
 )}

 <div className="overflow-hidden rounded-xl border border-border">
 <table className="min-w-full text-left text-sm">
 <thead>
 <tr className="border-b border-border bg-slate-50/80 text-xs uppercase text-slate-500">
 <th className="px-4 py-2 font-semibold">Check</th>
 <th className="px-4 py-2 font-semibold">Status</th>
 <th className="px-4 py-2 font-semibold">Detail</th>
 </tr>
 </thead>
 <tbody className="divide-y divide-border">
 {(deployChecks.checks || []).map((check) => (
 <tr key={check.id}>
 <td className="px-4 py-2.5 font-medium text-ink">{check.label}</td>
 <td className="px-4 py-2.5">
 <span
 className={`badge ${check.ok ? 'bg-success/10 text-success': 'bg-danger/10 text-danger'}`}
 >
 {check.ok ? 'OK': 'FAIL'}
 </span>
 </td>
 <td className="px-4 py-2.5 text-xs text-slate-500">{check.detail || '—'}</td>
 </tr>
 ))}
 </tbody>
 </table>
 </div>

 {deployChecks.timestamp && (
 <p className="mt-3 text-xs text-slate-500">
 Checked at {new Date(deployChecks.timestamp).toLocaleString}
 </p>
 )}
 </div>
 )}
 </section>
 </AdminPageShell>
 );
}
