import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import TwoFactorSetupPanel from '../components/TwoFactorSetupPanel';
import { RefreshCw } from 'lucide-react';

export default function Security2faPage() {
  const [context, setContext] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [orgRequire2fa, setOrgRequire2fa] = useState(false);
  const [policyLoading, setPolicyLoading] = useState(false);
  const [policyMsg, setPolicyMsg] = useState('');

  const load = async () => {
    setLoading(true);
    setError('');
    const res = await api.frontendContext();
    if (res.error) setError(res.error || 'Unable to load security settings.');
    else {
      const data = (res as any).data;
      setContext(data);
      setOrgRequire2fa(Boolean(data?.organization?.require_2fa));
    }
    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const toggleOrg2faPolicy = async () => {
    const orgId = context?.organization?.id;
    if (!orgId) return;
    setPolicyLoading(true);
    setPolicyMsg('');
    const next = !orgRequire2fa;
    const res = await api.setOrg2faPolicy(orgId, next);
    if (res.error) {
      setPolicyMsg(typeof res.error === 'string' ? res.error : 'Unable to update organization 2FA policy.');
    } else {
      setOrgRequire2fa(next);
      setPolicyMsg(next
        ? 'Organization policy updated: all members must enable 2FA.'
        : 'Organization 2FA requirement removed.');
      await load();
    }
    setPolicyLoading(false);
  };

  const org = context?.organization;
  const isPartner = org?.organization_type === 'partner' || context?.user?.is_partner;
  const canManageOrgSettings = Array.isArray(context?.permissions)
    && context.permissions.includes('manage_org_settings');

  return (
    <ClientShell title="Two-Factor Authentication" eyebrow="Security" organization={org} isPartner={isPartner}>
      <div className="space-y-5">
        <div className="flex justify-end">
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {error && <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>}
        {policyMsg && <div className="rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm text-primary">{policyMsg}</div>}

        {loading ? (
          <div className="glass-panel p-8 text-sm text-slate-500">Loading security settings...</div>
        ) : (
          <TwoFactorSetupPanel
            context={context}
            onChanged={load}
            showOrgPolicy={canManageOrgSettings && Boolean(org?.id) && !isPartner}
            orgRequire2fa={orgRequire2fa}
            onToggleOrgPolicy={toggleOrg2faPolicy}
            policyLoading={policyLoading}
          />
        )}
      </div>
    </ClientShell>
  );
}
