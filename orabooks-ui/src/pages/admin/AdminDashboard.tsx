import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import AdminPageShell from '@/components/AdminPageShell';
import { api } from '../api';
import {
  Building2,
  Clock,
  Link2,
  RefreshCw,
  Settings,
  UserCheck,
  Users,
  Wrench,
} from 'lucide-react';

interface Stats {
  organizations: {
    total: number;
    customer: number;
    partner: number;
    active: number;
    pending: number;
    suspended: number;
    recent_7d: number;
  };
  partners: { active: number; pending: number; inactive: number; disabled: number };
  users: {
    total: number;
    partner: number;
    customer: number;
    verified: number;
    '2fa_enabled': number;
    recent_7d: number;
  };
  attributions: {
    total: number;
    verified: number;
    pending: number;
    blocked: number;
    recent_7d: number;
  };
  event_bus_health?: {
    pending: number;
    sent: number;
    dead_letter: number;
    status: string;
    dashboard_url?: string;
  } | null;
  timestamp?: string;
}

function adminLink(page: string) {
  const base = (window as any).orabooks_ajax?.admin_base || '/wp-admin/admin.php';
  return `${base}?page=${page}`;
}

export default function AdminDashboard() {
  const [stats, setStats] = useState<Stats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = () => {
    setLoading(true);
    setError('');
    api.dashboardStats().then((res: any) => {
      if (res.error) setError(typeof res.error === 'string' ? res.error : 'Failed to load dashboard.');
      else if (res.data) setStats(res.data);
      setLoading(false);
    });
  };

  useEffect(() => {
    void load();
  }, []);

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="h-24 animate-pulse rounded-2xl border border-border bg-white" />
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {[...Array(4)].map((_, i) => (
            <div key={i} className="h-32 animate-pulse rounded-2xl border border-border bg-white" />
          ))}
        </div>
        <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
          {[...Array(4)].map((_, i) => (
            <div key={i} className="h-52 animate-pulse rounded-2xl border border-border bg-white" />
          ))}
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="glass-panel p-6 text-center">
        <p className="font-medium text-danger">{error}</p>
        <Button onClick={load} variant="secondary" className="mt-4">Try again</Button>
      </div>
    );
  }

  const orgs = stats?.organizations;
  const partners = stats?.partners;
  const users = stats?.users;
  const attrs = stats?.attributions;
  const eventBus = stats?.event_bus_health;

  const cards = [
    {
      label: 'Organizations',
      value: orgs?.total ?? 0,
      footer: `${orgs?.customer ?? 0} customer · ${orgs?.partner ?? 0} partner`,
      icon: Building2,
      tone: 'bg-primary/10 text-primary',
    },
    {
      label: 'Active Partners',
      value: partners?.active ?? 0,
      footer: `${partners?.pending ?? 0} pending · ${partners?.inactive ?? 0} inactive`,
      icon: UserCheck,
      tone: 'bg-accent/10 text-accent',
    },
    {
      label: 'Total Users',
      value: users?.total ?? 0,
      footer: `${users?.verified ?? 0} verified · ${users?.['2fa_enabled'] ?? 0} 2FA`,
      icon: Users,
      tone: 'bg-primary/10 text-primary-dark',
    },
    {
      label: 'Verified Attributions',
      value: attrs?.verified ?? 0,
      footer: `${attrs?.total ?? 0} total · ${attrs?.pending ?? 0} pending`,
      icon: Link2,
      tone: 'bg-accent/10 text-accent',
    },
    {
      label: 'Event Bus Health',
      value: (eventBus?.status || 'healthy').toUpperCase(),
      footer: `${eventBus?.pending ?? 0} pending · ${eventBus?.dead_letter ?? 0} dead`,
      icon: Clock,
      tone: eventBus?.status === 'critical' ? 'bg-danger/10 text-danger' : 'bg-success/10 text-success',
      href: adminLink('orabooks-event-dead-letter'),
    },
  ];

  const quickActions = [
    {
      label: 'Review Pending Partners',
      href: adminLink('orabooks-partners'),
      badge: partners?.pending ?? 0,
    },
    {
      label: 'Pending Organizations',
      href: adminLink('orabooks-orgs'),
      badge: orgs?.pending ?? 0,
    },
    {
      label: 'Job Queue',
      href: adminLink('orabooks-job-queue'),
    },
    {
      label: 'Observability',
      href: adminLink('orabooks-observability'),
    },
    {
      label: 'System Settings',
      href: adminLink('orabooks-settings'),
      icon: Settings,
    },
    {
      label: 'View Audit Log',
      href: adminLink('orabooks-audit'),
    },
  ];

  return (
    <AdminPageShell
      title="Platform Dashboard"
      description="Live counts across organizations, partners, users, and attributions."
      actions={
        <Button onClick={load} variant="secondary" size="sm">
          <RefreshCw className="h-4 w-4" />
          Refresh
        </Button>
      }
    >
      <div className="space-y-6">
      {stats?.timestamp && (
        <p className="text-xs text-ink-secondary">Last updated: {new Date(stats.timestamp).toLocaleString()}</p>
      )}

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {cards.map((card) => {
          const Icon = card.icon;
          const content = (
            <div key={card.label} className="stat-card">
              <div className={`inline-flex rounded-xl p-2.5 ${card.tone}`}>
                <Icon className="h-5 w-5" />
              </div>
              <p className="mt-4 text-xs font-bold uppercase tracking-wide text-slate-500">{card.label}</p>
              <p className="mt-1 text-3xl font-black text-ink">{card.value}</p>
              <p className="mt-1 text-xs text-slate-500">{card.footer}</p>
            </div>
          );
          return card.href ? (
            <a key={card.label} href={card.href} className="block no-underline">
              {content}
            </a>
          ) : content;
        })}
      </div>

      <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
        <DetailPanel
          title="Organization Breakdown"
          icon={Building2}
          link={adminLink('orabooks-orgs')}
          linkLabel="View all organizations"
          rows={[
            { label: 'Customer orgs', value: orgs?.customer ?? 0 },
            { label: 'Partner orgs', value: orgs?.partner ?? 0 },
            { label: 'Active', value: orgs?.active ?? 0, tone: 'success' },
            { label: 'Pending setup', value: orgs?.pending ?? 0, tone: 'warning' },
            { label: 'Suspended', value: orgs?.suspended ?? 0, tone: 'danger' },
          ]}
        />
        <DetailPanel
          title="Partner Codes"
          icon={UserCheck}
          link={adminLink('orabooks-partners')}
          linkLabel="Manage partners"
          rows={[
            { label: 'Active', value: partners?.active ?? 0, tone: 'success' },
            { label: 'Pending review', value: partners?.pending ?? 0, tone: 'warning' },
            { label: 'Inactive', value: partners?.inactive ?? 0 },
            { label: 'Disabled', value: partners?.disabled ?? 0, tone: 'muted' },
          ]}
        />
        <DetailPanel
          title="User Stats"
          icon={Users}
          link={adminLink('orabooks-users')}
          linkLabel="View all users"
          rows={[
            { label: 'Customer users', value: users?.customer ?? 0 },
            { label: 'Partner users', value: users?.partner ?? 0 },
            { label: 'Verified email', value: users?.verified ?? 0, tone: 'success' },
            { label: '2FA enabled', value: users?.['2fa_enabled'] ?? 0, tone: 'success' },
          ]}
        />
        <DetailPanel
          title="Attribution Stats"
          icon={Link2}
          link={adminLink('orabooks-partners')}
          linkLabel="View attributions"
          rows={[
            { label: 'Total', value: attrs?.total ?? 0 },
            { label: 'Verified', value: attrs?.verified ?? 0, tone: 'success' },
            { label: 'Pending', value: attrs?.pending ?? 0, tone: 'warning' },
            { label: 'Blocked', value: attrs?.blocked ?? 0, tone: 'danger' },
          ]}
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <section className="glass-panel overflow-hidden">
          <div className="flex items-center gap-2 border-b border-border bg-muted/60 px-5 py-3">
            <Clock className="h-4 w-4 text-primary" />
            <h2 className="text-sm font-bold text-ink">Recent Activity (7 days)</h2>
          </div>
          <div className="divide-y divide-border">
            <ActivityRow icon={Building2} count={orgs?.recent_7d ?? 0} label="new organizations" />
            <ActivityRow icon={Users} count={users?.recent_7d ?? 0} label="new users registered" />
            <ActivityRow icon={Link2} count={attrs?.recent_7d ?? 0} label="new attributions" />
          </div>
        </section>

        <section className="glass-panel overflow-hidden">
          <div className="flex items-center gap-2 border-b border-border bg-muted/60 px-5 py-3">
            <Wrench className="h-4 w-4 text-primary" />
            <h2 className="text-sm font-bold text-ink">Quick Actions</h2>
          </div>
          <div className="divide-y divide-border">
            {quickActions.map((action) => (
              <a
                key={action.label}
                href={action.href}
                className="flex items-center justify-between px-5 py-3 text-sm font-medium text-ink-secondary transition hover:bg-primary/5 hover:text-primary"
              >
                <span>{action.label}</span>
                {action.badge && action.badge > 0 ? (
                  <span className="badge bg-danger text-white">{action.badge}</span>
                ) : null}
              </a>
            ))}
          </div>
        </section>
      </div>
      </div>
    </AdminPageShell>
  );
}

function DetailPanel({
  title,
  icon: Icon,
  link,
  linkLabel,
  rows,
}: {
  title: string;
  icon: typeof Building2;
  link: string;
  linkLabel: string;
  rows: { label: string; value: number; tone?: 'success' | 'warning' | 'danger' | 'muted' }[];
}) {
  const toneClass = {
    success: 'text-success',
    warning: 'text-warning',
    danger: 'text-danger',
    muted: 'text-slate-400',
  };

  return (
    <section className="glass-panel overflow-hidden">
      <div className="flex items-center gap-2 border-b border-border bg-muted/60 px-5 py-3">
        <Icon className="h-4 w-4 text-primary" />
        <h2 className="text-sm font-bold text-ink">{title}</h2>
      </div>
      <div className="space-y-0 px-5 py-3">
        {rows.map((row) => (
          <div
            key={row.label}
            className="flex items-center justify-between border-b border-border/60 py-2.5 text-sm last:border-0"
          >
            <span className={row.tone ? toneClass[row.tone] : 'text-slate-600'}>{row.label}</span>
            <span className="font-bold text-ink">{row.value}</span>
          </div>
        ))}
      </div>
      <div className="border-t border-border px-5 py-3">
        <a href={link} className="text-xs font-semibold text-primary hover:text-primary-dark">
          {linkLabel} →
        </a>
      </div>
    </section>
  );
}

function ActivityRow({
  icon: Icon,
  count,
  label,
}: {
  icon: typeof Building2;
  count: number;
  label: string;
}) {
  return (
    <div className="flex items-center gap-3 px-5 py-3">
      <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
        <Icon className="h-4 w-4" />
      </div>
      <p className="text-sm text-slate-600">
        <strong className="font-bold text-ink">{count}</strong> {label}
      </p>
    </div>
  );
}
