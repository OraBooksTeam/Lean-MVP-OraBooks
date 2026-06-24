import { useEffect, useState } from 'react';
import AdminPageShell from '@/components/AdminPageShell';
import { api } from '../api';
import Button from '@/components/Button';
import { ShieldCheck, ShieldOff } from 'lucide-react';

const REGIONS = ['us-east', 'eu-west-1', 'ap-southeast-1'] as const;

interface Org {
  id: number;
  name: string;
  subdomain: string;
  organization_type: string;
  tier: string;
  status: string;
  region?: string;
  created_at: string;
}

function adminLink(page: string) {
  const cfg = (window as any).orabooks_ajax || {};
  const base = typeof cfg.admin_url === 'string' && cfg.admin_url.trim() !== ''
    ? cfg.admin_url.replace(/\/?$/, '')
    : '/wp-admin/admin.php';
  return `${base}?page=${page}`;
}

export default function AdminOrganizations() {
  const [orgs, setOrgs] = useState<Org[]>([]);
  const [loading, setLoading] = useState(true);
  const [typeFilter, setTypeFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');

  const load = () => {
    setLoading(true);
    api.listOrgs(typeFilter, statusFilter).then((res) => {
      if (!res.error) setOrgs((res as any).data || []);
      setLoading(false);
    });
  };

  useEffect(() => { load(); }, [typeFilter, statusFilter]);

  const suspend = (id: number) => {
    if (!confirm('Suspend this organization?')) return;
    api.suspendOrg(id).then(() => load());
  };

  const activate = (id: number) => {
    if (!confirm('Activate this organization?')) return;
    api.activateOrg(id).then(() => load());
  };

  const changeRegion = (id: number, region: string) => {
    if (!confirm(`Change data residency to ${region}? This queues an async migration.`)) return;
    api.changeOrgRegion(id, region).then((res) => {
      if (res.error) {
        alert(res.error);
        return;
      }
      load();
    });
  };

  return (
    <AdminPageShell
      title="Organizations"
      description="Manage customer and partner organizations across the platform. Partner approvals are handled separately in Partner Management."
      actions={<Button variant="secondary" onClick={load}>Refresh</Button>}
    >
      <div className="flex flex-wrap gap-2">
        <select
          value={typeFilter}
          onChange={(e) => setTypeFilter(e.target.value)}
          className="rounded-lg border border-border bg-white px-3 py-2 text-sm"
        >
          <option value="">All Types</option>
          <option value="customer">Customer</option>
          <option value="partner">Partner</option>
        </select>
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="rounded-lg border border-border bg-white px-3 py-2 text-sm"
        >
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="pending_setup">Pending Setup</option>
          <option value="suspended">Suspended</option>
          <option value="payout_hold">Payout Hold</option>
          <option value="fraud_freeze">Fraud Freeze</option>
        </select>
      </div>
      <div className="glass-panel overflow-hidden">
        <table className="min-w-full text-left text-sm">
          <thead>
            <tr className="border-b border-border bg-slate-50/60 text-xs uppercase text-slate-500">
              <th className="px-5 py-3 font-semibold">ID</th>
              <th className="px-5 py-3 font-semibold">Name</th>
              <th className="px-5 py-3 font-semibold">Subdomain</th>
              <th className="px-5 py-3 font-semibold">Type</th>
              <th className="px-5 py-3 font-semibold">Tier</th>
              <th className="px-5 py-3 font-semibold">Region</th>
              <th className="px-5 py-3 font-semibold">Status</th>
              <th className="px-5 py-3 font-semibold">Created</th>
              <th className="px-5 py-3 font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {loading ? (
              <tr><td colSpan={9} className="px-5 py-6 text-center text-slate-500">Loading…</td></tr>
            ) : orgs.length === 0 ? (
              <tr><td colSpan={9} className="px-5 py-6 text-center text-slate-500">No organizations found.</td></tr>
            ) : orgs.map((org) => (
              <tr key={org.id} className="transition hover:bg-slate-50/60">
                <td className="px-5 py-3 font-mono text-slate-600">{org.id}</td>
                <td className="px-5 py-3 font-medium text-ink">{org.name}</td>
                <td className="px-5 py-3 font-mono text-xs text-slate-600">{org.subdomain}</td>
                <td className="px-5 py-3 capitalize text-slate-600">{org.organization_type}</td>
                <td className="px-5 py-3 capitalize text-slate-600">{org.tier}</td>
                <td className="px-5 py-3">
                  <RegionCell org={org} onChange={changeRegion} />
                </td>
                <td className="px-5 py-3"><StatusBadge status={org.status} /></td>
                <td className="px-5 py-3 text-slate-600">{org.created_at}</td>
                <td className="px-5 py-3">
                  <OrgActions org={org} onSuspend={suspend} onActivate={activate} />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </AdminPageShell>
  );
}

function RegionCell({
  org,
  onChange,
}: {
  org: Org;
  onChange: (id: number, region: string) => void;
}) {
  const region = org.region || 'us-east';
  const canChange = org.organization_type === 'customer' && org.tier === 'enterprise' && org.status !== 'fraud_freeze';

  if (!canChange) {
    return <span className="font-mono text-xs text-slate-600">{region}</span>;
  }

  return (
    <select
      value={region}
      onChange={(e) => onChange(org.id, e.target.value)}
      className="rounded border border-border bg-white px-2 py-1 font-mono text-xs"
      title="Enterprise data residency (SL-004)"
    >
      {REGIONS.map((r) => (
        <option key={r} value={r}>{r}</option>
      ))}
    </select>
  );
}

function OrgActions({
  org,
  onSuspend,
  onActivate,
}: {
  org: Org;
  onSuspend: (id: number) => void;
  onActivate: (id: number) => void;
}) {
  const isPartner = org.organization_type === 'partner';
  const partnersUrl = adminLink('orabooks-partners');

  if (org.status === 'fraud_freeze') {
    return <span className="text-xs text-red-600">Permanently frozen</span>;
  }

  if (org.status === 'active') {
    return (
      <button
        onClick={() => onSuspend(org.id)}
        className="inline-flex items-center gap-1 text-xs font-medium text-amber-700 hover:text-amber-800"
      >
        <ShieldOff className="h-3.5 w-3.5" />
        Suspend
      </button>
    );
  }

  if (isPartner && org.status === 'pending_setup') {
    return (
      <a
        href={partnersUrl}
        className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:text-primary-dark"
      >
        Approve in Partner Management
      </a>
    );
  }

  if (isPartner) {
    return (
      <span className="text-xs text-slate-500" title="Partner reactivation uses the review workflow in Partner Management.">
        Use Partner Management
      </span>
    );
  }

  return (
    <button
      onClick={() => onActivate(org.id)}
      className="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 hover:text-emerald-800"
    >
      <ShieldCheck className="h-3.5 w-3.5" />
      Activate
    </button>
  );
}

function StatusBadge({ status }: { status?: string }) {
  const map: Record<string, string> = {
    active: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    pending_setup: 'bg-amber-50 text-amber-700 border-amber-200',
    suspended: 'bg-red-50 text-red-700 border-red-200',
    payout_hold: 'bg-orange-50 text-orange-700 border-orange-200',
    fraud_freeze: 'bg-red-100 text-red-800 border-red-300',
  };
  const cls = map[status || ''] || 'bg-slate-100 text-slate-600 border-slate-200';
  return <span className={`badge border ${cls}`}>{status || '—'}</span>;
}
