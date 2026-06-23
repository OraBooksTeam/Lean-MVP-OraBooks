import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import ClientShell from '../components/ClientShell';
import { validateHttpsWebhookLines } from '@/lib/security/sl008';
import { api } from '../api';

export default function WebhookSettingsPage {
 const [context, setContext] = useState<any>(null);
 const [urls, setUrls] = useState('');
 const [message, setMessage] = useState('');
 const [error, setError] = useState('');
 const [loading, setLoading] = useState(true);

 const canManage = (context?.permissions || []).includes('manage_settings');

 const load = async => {
 setLoading(true);
 setError('');

 const ctx = await api.frontendContext;
 if (ctx.error) {
 setError(ctx.error || 'Please log in to manage webhook settings.');
 setLoading(false);
 return;
 }

 const nextContext = (ctx as any).data;
 setContext(nextContext);

 const permissions: string[] = nextContext?.permissions || [];
 if (!permissions.includes('manage_settings')) {
 setError('You do not have permission to manage webhook settings. Contact Owner or Admin.');
 setLoading(false);
 return;
 }

 const res = await api.webhookSettingsGet(
 nextContext?.organization?.id ? { org_id: nextContext.organization.id }: {}
 );
 if (res.error) setError(res.error);
 else setUrls((res as any).data?.urls || '');
 setLoading(false);
 };

 useEffect( => {
 void load;
 }, []);

 const save = async => {
 setError('');
 setMessage('');
 const validation = validateHttpsWebhookLines(urls);
 if (!validation.valid) {
 setError(validation.errors.join(' '));
 return;
 }
 const res = await api.webhookSettingsSave(
 urls,
 context?.organization?.id ? { org_id: context.organization.id }: undefined
 );
 if (res.error) setError(res.error);
 else {
 setUrls(res.data?.urls || urls);
 setMessage('Webhook settings saved.');
 }
 };

 const isPartner = context?.organization?.organization_type === 'partner' || context?.user?.is_partner;

 return (
 <ClientShell
 title="Webhook Settings"
 eyebrow="Platform"
 organization={context?.organization}
 isPartner={isPartner}
 >
 {!canManage && !loading ? (
 <div className="glass-panel max-w-lg p-6 text-center text-sm text-slate-600">
 You do not have permission to manage webhook settings. Contact Owner or Admin.
 </div>
 ): (
 <div className="max-w-3xl space-y-4 rounded-2xl border border-border bg-white p-5">
 <p className="text-sm text-slate-600">
 Add one webhook URL per line. Domain events from are dispatched through
 as `webhook_dispatch` background jobs.
 </p>
 <p className="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">
 Production webhooks must use HTTPS ( / SSRF policy). Localhost HTTP URLs are allowed for local testing only.
 </p>
 {error && <p className="text-sm text-danger">{error}</p>}
 {message && <p className="text-sm text-success">{message}</p>}
 <textarea
 value={urls}
 onChange={(e) => setUrls(e.target.value)}
 disabled={loading}
 rows={8}
 className="w-full rounded-xl border border-border px-3 py-2 text-sm"
 placeholder="https://webhook.site/..."
 />
 <Button type="button" onClick={save} disabled={loading}>
 Save Webhooks
 </Button>
 </div>
 )}
 </ClientShell>
 );
}
