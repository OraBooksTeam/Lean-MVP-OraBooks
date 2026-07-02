import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { Mail, RefreshCw, UserPlus, Users } from 'lucide-react';
import NotificationPolicyPanel from '@/components/NotificationPolicyPanel';

const fieldClass =
  'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';

export default function TeamPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [inviteEmail, setInviteEmail] = useState('');
  const [inviteRole, setInviteRole] = useState('staff');
  const [inviting, setInviting] = useState(false);
  const [actionUserId, setActionUserId] = useState<number | null>(null);
  const [actionInviteId, setActionInviteId] = useState<number | null>(null);
  const [partnerCommissionForStaffViewer, setPartnerCommissionForStaffViewer] = useState(false);
  const [savingAccessSettings, setSavingAccessSettings] = useState(false);

  const orgId = data?.context?.organization?.id;
  const currentUserId = data?.context?.user_id;
  const caps = data?.capabilities || {};

  const load = async () => {
    setLoading(true);
    setError('');
    setSuccess('');
    const res = await api.teamDashboard();
    if (res.error) setError(res.error || 'Unable to load team.');
    else {
      const payload = (res as any).data;
      setData(payload);
      const nextOrgId = payload?.context?.organization?.id;
      if (nextOrgId) {
        const settingsRes = await api.teamAccessSettingsGet(nextOrgId);
        if (!settingsRes.error) {
          setPartnerCommissionForStaffViewer(Boolean((settingsRes as any).data?.partner_commission_for_staff_viewer));
        }
      }
      if (payload?.invite_roles?.length && !payload.invite_roles.some((r: any) => r.id === inviteRole)) {
        setInviteRole(payload.invite_roles[0].id);
      }
    }
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  const handleInvite = async () => {
    if (!orgId || !inviteEmail.trim()) {
      setError('Enter an email address to invite.');
      return;
    }

    setInviting(true);
    setError('');
    setSuccess('');
    const res = await api.inviteTeamUser(orgId, inviteEmail.trim(), inviteRole);
    if (res.error) {
      setError(res.error);
    } else {
      setSuccess('Invitation sent.');
      setInviteEmail('');
      await load();
    }
    setInviting(false);
  };

  const handleRoleChange = async (userId: number, role: string) => {
    if (!orgId) return;
    setActionUserId(userId);
    setError('');
    setSuccess('');
    const res = await api.updateTeamRole(orgId, userId, role);
    if (res.error) setError(res.error);
    else {
      const requiresRelogin = Boolean((res as any).data?.requires_relogin);
      setSuccess(requiresRelogin ? 'Role updated. The user should sign in again to refresh access.' : 'Role updated.');
      await load();
    }
    setActionUserId(null);
  };

  const handleRemove = async (userId: number) => {
    if (!orgId || !window.confirm('Remove this user from the organization?')) return;
    setActionUserId(userId);
    setError('');
    setSuccess('');
    const res = await api.removeTeamUser(orgId, userId);
    if (res.error) setError(res.error);
    else {
      setSuccess('User removed.');
      await load();
    }
    setActionUserId(null);
  };

  const handleResend = async (inviteId: number) => {
    if (!orgId) return;
    setActionInviteId(inviteId);
    setError('');
    setSuccess('');
    const res = await api.resendTeamInvite(orgId, inviteId);
    if (res.error) setError(res.error);
    else setSuccess('Invitation resent.');
    setActionInviteId(null);
  };

  const handleCancelInvite = async (inviteId: number) => {
    if (!orgId || !window.confirm('Cancel this invitation?')) return;
    setActionInviteId(inviteId);
    setError('');
    setSuccess('');
    const res = await api.cancelTeamInvite(orgId, inviteId);
    if (res.error) setError(res.error);
    else {
      setSuccess('Invitation cancelled.');
      await load();
    }
    setActionInviteId(null);
  };

  const members = data?.members || [];
  const invites = data?.pending_invites || [];
  const stats = data?.stats || {};
  const isPartner = data?.context?.organization?.organization_type === 'partner';
  const canManageNotificationPolicy = ['owner', 'admin'].includes(data?.context?.role);
  const canViewAccessMatrix = ['owner', 'admin'].includes(data?.context?.role);
  const permissionMatrix = data?.context?.permission_matrix || {};

  const savePartnerAccessSetting = async () => {
    if (!orgId) return;
    setSavingAccessSettings(true);
    setError('');
    const res = await api.teamAccessSettingsSave(orgId, partnerCommissionForStaffViewer);
    if (res.error) {
      setError(res.error || 'Unable to save access setting.');
    } else {
      setSuccess('Access setting updated.');
      await load();
    }
    setSavingAccessSettings(false);
  };

  return (
    <ClientShell
      title="Team"
      eyebrow="Members & invitations"
      organization={data?.context?.organization}
      role={data?.context?.role}
      isPartner={isPartner}
    >
      <div className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Metric label="Total Members" value={stats.total_members ?? 0} />
          <Metric label="Pending Invites" value={stats.pending_invites ?? 0} />
          <Metric label="Admins" value={stats.by_role?.admin ?? 0} />
          <Metric label="Your Role" value={data?.context?.role || '—'} />
        </div>

        {caps.invite_user && (
          <div className="glass-panel p-5">
            <div className="flex items-center gap-2 border-b border-border pb-4">
              <UserPlus className="h-5 w-5 text-primary" />
              <h2 className="font-bold text-ink">Invite Member</h2>
            </div>
            <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <label className="block sm:col-span-2">
                <span className="mb-1.5 block text-xs font-semibold uppercase text-slate-500">Email</span>
                <input
                  type="email"
                  className={fieldClass}
                  value={inviteEmail}
                  onChange={(e) => setInviteEmail(e.target.value)}
                  placeholder="colleague@company.com"
                />
              </label>
              <label className="block">
                <span className="mb-1.5 block text-xs font-semibold uppercase text-slate-500">Role</span>
                <select className={fieldClass} value={inviteRole} onChange={(e) => setInviteRole(e.target.value)}>
                  {(data?.invite_roles || []).map((role: any) => (
                    <option key={role.id} value={role.id}>
                      {role.label}
                    </option>
                  ))}
                </select>
              </label>
              <div className="flex items-end">
                <Button onClick={handleInvite} disabled={inviting} className="w-full">
                  <Mail className="h-4 w-4" />
                  {inviting ? 'Sending...' : 'Send Invite'}
                </Button>
              </div>
            </div>
          </div>
        )}

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

        <div className="glass-panel overflow-hidden">
          <div className="border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Members</h2>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Email</th>
                <th className="px-5 py-3 font-semibold">Role</th>
                <th className="px-5 py-3 font-semibold">Joined</th>
                {(caps.change_role || caps.remove_user) && <th className="px-5 py-3 font-semibold">Actions</th>}
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr>
                  <td colSpan={4} className="px-5 py-8 text-center text-slate-500">
                    Loading members...
                  </td>
                </tr>
              ) : members.length === 0 ? (
                <tr>
                  <td colSpan={4} className="px-5 py-10 text-center">
                    <Users className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No team members found.</p>
                  </td>
                </tr>
              ) : (
                members.map((member: any) => {
                  const isSelf = member.id === currentUserId;
                  const isOwnerRow = member.role === 'owner';
                  const canEdit = caps.change_role && !isSelf && !isOwnerRow;
                  const canRemove = caps.remove_user && !isSelf && !isOwnerRow;

                  return (
                    <tr key={member.id} className="hover:bg-slate-50/70">
                      <td className="px-5 py-3 font-semibold text-ink">
                        {member.email}
                        {isSelf && <span className="ml-2 text-xs font-normal text-slate-500">(you)</span>}
                      </td>
                      <td className="px-5 py-3">
                        {canEdit ? (
                          <select
                            className="rounded-lg border border-border bg-white px-2.5 py-1.5 text-sm"
                            value={member.role}
                            disabled={actionUserId === member.id}
                            onChange={(e) => void handleRoleChange(member.id, e.target.value)}
                          >
                            {(data?.member_roles || []).map((role: any) => (
                              <option key={role.id} value={role.id}>
                                {role.label}
                              </option>
                            ))}
                          </select>
                        ) : (
                          <RoleBadge role={member.role} />
                        )}
                      </td>
                      <td className="px-5 py-3 text-slate-600">{formatDate(member.joined_at)}</td>
                      {(caps.change_role || caps.remove_user) && (
                        <td className="px-5 py-3">
                          {canRemove && (
                            <Button
                              variant="secondary"
                              size="sm"
                              disabled={actionUserId === member.id}
                              onClick={() => void handleRemove(member.id)}
                            >
                              Remove
                            </Button>
                          )}
                        </td>
                      )}
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        {caps.invite_user && (
          <div className="glass-panel overflow-hidden">
            <div className="border-b border-border px-5 py-4">
              <h2 className="font-bold text-ink">Pending Invitations</h2>
            </div>
            <table className="min-w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                  <th className="px-5 py-3 font-semibold">Email</th>
                  <th className="px-5 py-3 font-semibold">Role</th>
                  <th className="px-5 py-3 font-semibold">Expires</th>
                  <th className="px-5 py-3 font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {invites.length === 0 ? (
                  <tr>
                    <td colSpan={4} className="px-5 py-8 text-center text-sm text-slate-500">
                      No pending invitations.
                    </td>
                  </tr>
                ) : (
                  invites.map((invite: any) => (
                    <tr key={invite.id} className="hover:bg-slate-50/70">
                      <td className="px-5 py-3 font-semibold text-ink">{invite.email}</td>
                      <td className="px-5 py-3">
                        <RoleBadge role={invite.role} />
                      </td>
                      <td className="px-5 py-3 text-slate-600">{formatDate(invite.expires_at)}</td>
                      <td className="px-5 py-3">
                        <div className="flex flex-wrap gap-2">
                          <Button
                            variant="secondary"
                            size="sm"
                            disabled={actionInviteId === invite.id}
                            onClick={() => void handleResend(invite.id)}
                          >
                            Resend
                          </Button>
                          <Button
                            variant="secondary"
                            size="sm"
                            disabled={actionInviteId === invite.id}
                            onClick={() => void handleCancelInvite(invite.id)}
                          >
                            Cancel
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        )}

        {canViewAccessMatrix && (
          <div className="glass-panel overflow-hidden">
            <div className="border-b border-border px-5 py-4">
              <h2 className="font-bold text-ink">Roles & Access</h2>
              <p className="mt-1 text-xs text-slate-500">Manage what each fixed role can do. Deny-by-default applies to permissions not listed here.</p>
            </div>
            <div className="grid gap-3 p-5 md:grid-cols-2 xl:grid-cols-3">
              {Object.entries(permissionMatrix).map(([permission, roles]: [string, any]) => (
                <div key={permission} className="rounded-xl border border-border bg-white p-4">
                  <p className="text-sm font-bold text-ink">{permission.replace(/_/g, ' ')}</p>
                  <div className="mt-3 flex flex-wrap gap-1.5">
                    {(Array.isArray(roles) ? roles : []).map((role: string) => (
                      <RoleBadge key={`${permission}-${role}`} role={role} />
                    ))}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {canViewAccessMatrix && isPartner && (
          <div className="glass-panel p-5">
            <h2 className="font-bold text-ink">Partner Access Setting</h2>
            <p className="mt-1 text-sm text-slate-600">
              Allow Staff/Viewer roles to access Partner Program and Commissions.
            </p>
            <label className="mt-4 flex items-center gap-3 text-sm text-slate-700">
              <input
                type="checkbox"
                checked={partnerCommissionForStaffViewer}
                onChange={(e) => setPartnerCommissionForStaffViewer(e.target.checked)}
              />
              Enable partner commission access for Staff/Viewer
            </label>
            <div className="mt-4">
              <Button onClick={() => void savePartnerAccessSetting()} disabled={savingAccessSettings}>
                {savingAccessSettings ? 'Saving...' : 'Save access setting'}
              </Button>
            </div>
          </div>
        )}

        {canManageNotificationPolicy && orgId && !isPartner && (
          <NotificationPolicyPanel orgId={orgId} />
        )}
      </div>
    </ClientShell>
  );
}

function Metric({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="rounded-2xl border border-border bg-white p-4 shadow-sm">
      <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</p>
      <p className="mt-2 text-2xl font-black capitalize text-ink">{value}</p>
    </div>
  );
}

function RoleBadge({ role }: { role: string }) {
  const styles: Record<string, string> = {
    owner: 'border-violet-200 bg-violet-50 text-violet-800',
    admin: 'border-blue-200 bg-blue-50 text-blue-800',
    approver: 'border-indigo-200 bg-indigo-50 text-indigo-800',
    staff: 'border-slate-200 bg-slate-50 text-slate-700',
    viewer: 'border-emerald-200 bg-emerald-50 text-emerald-800',
  };

  return <span className={`badge border capitalize ${styles[role] || styles.staff}`}>{role}</span>;
}

function formatDate(value: string) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString();
}
