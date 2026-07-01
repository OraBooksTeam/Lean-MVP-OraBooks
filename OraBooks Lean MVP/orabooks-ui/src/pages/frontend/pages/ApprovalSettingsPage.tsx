import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { RefreshCw, Save, ShieldCheck, UserPlus, XCircle } from 'lucide-react';

type Policy = {
  org_id?: number;
  approval_expiry_hours?: number;
  reminder_hours_before_expiry?: number;
  max_approval_rounds?: number;
  maker_checker_required?: number | boolean;
  mfa_amount_threshold?: number;
  escalation_after_hours?: number;
  escalation_role?: 'admin' | 'owner';
};

type Delegation = {
  id: number;
  delegator_user_id: number;
  delegate_user_id: number;
  starts_at: string;
  ends_at: string;
  revoked_at?: string | null;
};

type TeamMember = {
  user_id: number;
  email?: string;
  display_name?: string;
  role?: string;
};

export default function ApprovalSettingsPage() {
  const [context, setContext] = useState<any>(null);
  const [policy, setPolicy] = useState<Policy>({});
  const [delegations, setDelegations] = useState<Delegation[]>([]);
  const [members, setMembers] = useState<TeamMember[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [delegateUserId, setDelegateUserId] = useState('');
  const [startsAt, setStartsAt] = useState(() => new Date().toISOString().slice(0, 16));
  const [endsAt, setEndsAt] = useState(() => {
    const d = new Date();
    d.setDate(d.getDate() + 7);
    return d.toISOString().slice(0, 16);
  });

  const orgId = context?.organization?.id;
  const canManage = context?.permissions?.includes('manage_org_settings') || context?.role === 'owner';

  const load = async () => {
    setLoading(true);
    setError('');
    const ctx = await api.frontendContext();
    if (ctx.error) {
      setError(ctx.error || 'Unable to load context.');
      setLoading(false);
      return;
    }

    const nextContext = (ctx as any).data;
    setContext(nextContext);
    const nextOrgId = nextContext?.organization?.id;
    if (!nextOrgId) {
      setError('Organization is not set up yet.');
      setLoading(false);
      return;
    }

    const [policyRes, delegationRes, teamRes] = await Promise.all([
      api.approvalPolicyGet(nextOrgId),
      api.approvalDelegationsList(nextOrgId),
      api.teamDashboard(),
    ]);

    if (policyRes.error) setError(policyRes.error);
    else setPolicy((policyRes as any).data?.policy || {});

    if (!delegationRes.error) {
      setDelegations((delegationRes as any).data?.delegations || []);
    }

    if (!teamRes.error) {
      const teamMembers = (teamRes as any).data?.members || (teamRes as any).data?.users || [];
      setMembers(teamMembers);
    }

    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  const savePolicy = async () => {
    if (!orgId) return;
    setSaving(true);
    setError('');
    setSuccess('');
    const res = await api.approvalPolicySave(orgId, {
      approval_expiry_hours: Number(policy.approval_expiry_hours ?? 72),
      reminder_hours_before_expiry: Number(policy.reminder_hours_before_expiry ?? 24),
      max_approval_rounds: Number(policy.max_approval_rounds ?? 5),
      maker_checker_required: policy.maker_checker_required ? 1 : 0,
      mfa_amount_threshold: Number(policy.mfa_amount_threshold ?? 10000),
      escalation_after_hours: Number(policy.escalation_after_hours ?? 48),
      escalation_role: policy.escalation_role || 'admin',
    });
    if (res.error) setError(res.error);
    else {
      setSuccess('Approval policy saved.');
      setPolicy((res as any).data?.policy || policy);
    }
    setSaving(false);
  };

  const createDelegation = async () => {
    if (!orgId || !delegateUserId) return;
    setSaving(true);
    setError('');
    setSuccess('');
    const res = await api.approvalDelegationCreate(orgId, {
      delegate_user_id: Number(delegateUserId),
      starts_at: startsAt.replace('T', ' ') + ':00',
      ends_at: endsAt.replace('T', ' ') + ':00',
    });
    if (res.error) setError(res.error);
    else {
      setSuccess('Delegation created.');
      setDelegateUserId('');
      await load();
    }
    setSaving(false);
  };

  const revokeDelegation = async (delegationId: number) => {
    if (!orgId) return;
    setSaving(true);
    setError('');
    const res = await api.approvalDelegationRevoke(orgId, delegationId);
    if (res.error) setError(res.error);
    else {
      setSuccess('Delegation revoked.');
      await load();
    }
    setSaving(false);
  };

  if (!canManage && !loading) {
    return (
      <ClientShell title="Approval Settings" eyebrow="Governance" organization={context?.organization}>
        <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
          Only organization owners can manage approval policy and delegation.
        </div>
      </ClientShell>
    );
  }

  return (
    <ClientShell title="Approval Settings" eyebrow="Policy & delegation" organization={context?.organization}>
      <div className="space-y-5">
        <div className="flex justify-end">
          <Button variant="secondary" size="sm" onClick={() => void load()}>
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {error && <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>}
        {success && (
          <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">{success}</div>
        )}

        <section className="glass-panel p-5">
          <div className="mb-4 flex items-center gap-2">
            <ShieldCheck className="h-5 w-5 text-accent" />
            <h2 className="text-lg font-bold text-ink">Approval Policy</h2>
          </div>

          {loading ? (
            <p className="text-sm text-slate-500">Loading policy...</p>
          ) : (
            <div className="grid gap-4 md:grid-cols-2">
              <Input
                label="Approval expiry (hours)"
                type="number"
                min="1"
                value={String(policy.approval_expiry_hours ?? 72)}
                onChange={(e) => setPolicy((p) => ({ ...p, approval_expiry_hours: Number(e.target.value) }))}
              />
              <Input
                label="Reminder before expiry (hours)"
                type="number"
                min="1"
                value={String(policy.reminder_hours_before_expiry ?? 24)}
                onChange={(e) => setPolicy((p) => ({ ...p, reminder_hours_before_expiry: Number(e.target.value) }))}
              />
              <Input
                label="Max approval rounds"
                type="number"
                min="1"
                value={String(policy.max_approval_rounds ?? 5)}
                onChange={(e) => setPolicy((p) => ({ ...p, max_approval_rounds: Number(e.target.value) }))}
              />
              <Input
                label="MFA threshold amount"
                type="number"
                min="0"
                step="0.01"
                value={String(policy.mfa_amount_threshold ?? 10000)}
                onChange={(e) => setPolicy((p) => ({ ...p, mfa_amount_threshold: Number(e.target.value) }))}
              />
              <Input
                label="Escalation after (hours)"
                type="number"
                min="1"
                value={String(policy.escalation_after_hours ?? 48)}
                onChange={(e) => setPolicy((p) => ({ ...p, escalation_after_hours: Number(e.target.value) }))}
              />
              <label className="block text-sm">
                <span className="mb-1.5 block font-semibold text-ink">Escalation role</span>
                <select
                  value={policy.escalation_role || 'admin'}
                  onChange={(e) => setPolicy((p) => ({ ...p, escalation_role: e.target.value as 'admin' | 'owner' }))}
                  className="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20"
                >
                  <option value="admin">Admin</option>
                  <option value="owner">Owner</option>
                </select>
              </label>
              <label className="flex items-center gap-2 text-sm font-medium text-ink md:col-span-2">
                <input
                  type="checkbox"
                  checked={Boolean(policy.maker_checker_required ?? true)}
                  onChange={(e) => setPolicy((p) => ({ ...p, maker_checker_required: e.target.checked }))}
                  className="h-4 w-4 rounded border-border text-accent focus:ring-accent/30"
                />
                Require maker-checker (creator cannot approve own journal)
              </label>
              <div className="md:col-span-2">
                <Button onClick={() => void savePolicy()} loading={saving}>
                  <Save className="h-4 w-4" />
                  Save Policy
                </Button>
              </div>
            </div>
          )}
        </section>

        <section className="glass-panel p-5">
          <div className="mb-4 flex items-center gap-2">
            <UserPlus className="h-5 w-5 text-accent" />
            <h2 className="text-lg font-bold text-ink">Delegate Approval</h2>
          </div>
          <p className="mb-4 text-sm text-slate-600">Temporarily assign approval authority to another team member.</p>

          <div className="grid gap-4 md:grid-cols-3">
            <label className="block text-sm md:col-span-1">
              <span className="mb-1.5 block font-semibold text-ink">Delegate user</span>
              <select
                value={delegateUserId}
                onChange={(e) => setDelegateUserId(e.target.value)}
                className="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20"
              >
                <option value="">Select team member</option>
                {members.map((member) => (
                  <option key={member.user_id} value={member.user_id}>
                    {member.display_name || member.email || `User #${member.user_id}`} ({member.role || 'member'})
                  </option>
                ))}
              </select>
            </label>
            <Input label="Starts" type="datetime-local" value={startsAt} onChange={(e) => setStartsAt(e.target.value)} />
            <Input label="Ends" type="datetime-local" value={endsAt} onChange={(e) => setEndsAt(e.target.value)} />
          </div>
          <div className="mt-4">
            <Button onClick={() => void createDelegation()} loading={saving} disabled={!delegateUserId}>
              <UserPlus className="h-4 w-4" />
              Create Delegation
            </Button>
          </div>

          <div className="mt-6 overflow-hidden rounded-xl border border-border">
            <table className="min-w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                  <th className="px-4 py-3 font-semibold">Delegate</th>
                  <th className="px-4 py-3 font-semibold">Period</th>
                  <th className="px-4 py-3 font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {delegations.length === 0 ? (
                  <tr>
                    <td colSpan={3} className="px-4 py-6 text-center text-slate-500">
                      No active delegations.
                    </td>
                  </tr>
                ) : (
                  delegations.map((row) => (
                    <tr key={row.id} className="hover:bg-slate-50/70">
                      <td className="px-4 py-3 font-medium text-ink">User #{row.delegate_user_id}</td>
                      <td className="px-4 py-3 text-slate-600">
                        {row.starts_at} → {row.ends_at}
                      </td>
                      <td className="px-4 py-3">
                        {!row.revoked_at && (
                          <Button variant="secondary" size="sm" onClick={() => void revokeDelegation(row.id)} loading={saving}>
                            <XCircle className="h-3.5 w-3.5" />
                            Revoke
                          </Button>
                        )}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </ClientShell>
  );
}
