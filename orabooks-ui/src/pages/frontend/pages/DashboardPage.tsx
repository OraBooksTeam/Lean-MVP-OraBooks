import { useEffect, useState } from 'react';
import { api } from './api';
import { Building2, Users, UserCheck, Link2, TrendingUp, ArrowRight } from 'lucide-react';

interface Stats {
  organizations: { total: number; customer: number; partner: number; active: number; pending: number; suspended: number; recent_7d: number };
  partners: { active: number; pending: number; inactive: number; disabled: number };
  users: { total: number; partner: number; customer: number; verified: number; '2fa_enabled': number; recent_7d: number };
  attributions: { total: number; verified: number; pending: number; blocked: number; recent_7d: number };
  invoices?: { total: number; paid: number };
  timestamp?: string;
}

export default function DashboardPage() {
  const [stats, setStats] = useState<Stats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    api.dashboardStats()
      .then((res) => {
        if (res.error) setError(typeof res.error === 'string' ? res.error : 'Failed to load');
        else if ((res as any).data) setStats((res as any).data);
      })
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div className="min-h-screen bg-slate-50 p-6">
        <div className="mx-auto max-w-7xl space-y-6">
          <div className="h-10 w-48 animate-pulse rounded-lg bg-slate-200" />
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {[...Array(4)].map((_, i) => (
              <div key={i} className="h-32 animate-pulse rounded-2xl bg-white border border-slate-200" />
            ))}
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-50">
        <div className="glass-panel max-w-md p-6 text-center">
          <p className="text-danger font-medium">{error}</p>
        </div>
      </div>
    );
  }

  const cards = [
    { label: 'Organizations', value: stats?.organizations.total ?? 0, sub: `${stats?.organizations.customer ?? 0} customer · ${stats?.organizations.partner ?? 0} partner`, icon: <Building2 className="h-5 w-5 text-primary" />, color: 'bg-sky-500/10 text-sky-700' },
    { label: 'Active Partners', value: stats?.partners.active ?? 0, sub: `${stats?.partners.pending ?? 0} pending · ${stats?.partners.inactive ?? 0} inactive`, icon: <UserCheck className="h-5 w-5 text-rose-600" />, color: 'bg-rose-500/10 text-rose-700' },
    { label: 'Total Users', value: stats?.users.total ?? 0, sub: `${stats?.users.verified ?? 0} verified · ${stats?.users['2fa_enabled'] ?? 0} 2FA`, icon: <Users className="h-5 w-5 text-emerald-600" />, color: 'bg-emerald-500/10 text-emerald-700' },
    { label: 'Verified Attributions', value: stats?.attributions.verified ?? 0, sub: `${stats?.attributions.total ?? 0} total · ${stats?.attributions.pending ?? 0} pending`, icon: <Link2 className="h-5 w-5 text-orange-600" />, color: 'bg-orange-500/10 text-orange-700' },
  ];

  return (
    <div className="min-h-screen bg-slate-50 p-6">
      <div className="mx-auto max-w-7xl space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-ink">Dashboard</h1>
            {stats?.timestamp && (
              <p className="mt-0.5 text-xs text-slate-500">Last updated: {new Date(stats.timestamp).toLocaleString()}</p>
            )}
          </div>
          <button
            onClick={() => {
              setLoading(true);
              setError('');
              api.dashboardStats().then((res) => {
                if ((res as any).data) setStats((res as any).data);
                else setError('Failed');
              }).finally(() => setLoading(false));
            }}
            className="flex items-center gap-1.5 rounded-lg border border-border bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 active:scale-[0.98]"
          >
            <TrendingUp className="h-4 w-4" />
            Refresh
          </button>
        </div>

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {cards.map((c) => (
            <div key={c.label} className="stat-card">
              <div className="flex items-center justify-between">
                <div className={`rounded-xl p-2.5 ${c.color}`}>{c.icon}</div>
                <ArrowRight className="h-4 w-4 text-slate-300" />
              </div>
              <div className="mt-4">
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{c.label}</p>
                <p className="mt-1 text-3xl font-bold text-ink">{c.value}</p>
                <p className="mt-1 truncate text-xs text-slate-500">{c.sub}</p>
              </div>
            </div>
          ))}
        </div>

        <div className="grid gap-4 lg:grid-cols-2">
          <div className="glass-panel p-6">
            <h3 className="text-sm font-bold uppercase tracking-wide text-slate-500">Organizations</h3>
            <div className="mt-4 space-y-2.5">
              <StatRow label="Active" value={stats?.organizations.active ?? 0} />
              <StatRow label="Pending Setup" value={stats?.organizations.pending ?? 0} />
              <StatRow label="Suspended" value={stats?.organizations.suspended ?? 0} />
              <StatRow label="Recent (7d)" value={stats?.organizations.recent_7d ?? 0} />
            </div>
          </div>
          <div className="glass-panel p-6">
            <h3 className="text-sm font-bold uppercase tracking-wide text-slate-500">Partners</h3>
            <div className="mt-4 space-y-2.5">
              <StatRow label="Active" value={stats?.partners.active ?? 0} />
              <StatRow label="Pending Review" value={stats?.partners.pending ?? 0} />
              <StatRow label="Inactive" value={stats?.partners.inactive ?? 0} />
              <StatRow label="Disabled" value={stats?.partners.disabled ?? 0} />
            </div>
          </div>
          <div className="glass-panel p-6">
            <h3 className="text-sm font-bold uppercase tracking-wide text-slate-500">Users</h3>
            <div className="mt-4 space-y-2.5">
              <StatRow label="Customer Users" value={stats?.users.customer ?? 0} />
              <StatRow label="Partner Users" value={stats?.users.partner ?? 0} />
              <StatRow label="Verified Email" value={stats?.users.verified ?? 0} />
              <StatRow label="2FA Enabled" value={stats?.users['2fa_enabled'] ?? 0} />
            </div>
          </div>
          <div className="glass-panel p-6">
            <h3 className="text-sm font-bold uppercase tracking-wide text-slate-500">Attributions</h3>
            <div className="mt-4 space-y-2.5">
              <StatRow label="Total" value={stats?.attributions.total ?? 0} />
              <StatRow label="Verified" value={stats?.attributions.verified ?? 0} />
              <StatRow label="Pending" value={stats?.attributions.pending ?? 0} />
              <StatRow label="Blocked" value={stats?.attributions.blocked ?? 0} />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function StatRow({ label, value }: { label: string; value: number }) {
  return (
    <div className="flex items-center justify-between rounded-lg border border-border bg-white px-4 py-2.5">
      <span className="text-sm text-slate-600">{label}</span>
      <span className="text-sm font-bold text-ink">{value}</span>
    </div>
  );
}
