import { Link, useLocation } from 'react-router-dom';
import {
  Bell,
  BookOpen,
  FileText,
  Home,
  Landmark,
  LogOut,
  Users,
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface ClientShellProps {
  title: string;
  eyebrow?: string;
  children: React.ReactNode;
  organization?: {
    name?: string;
    tier?: string;
    status?: string;
    organization_type?: string;
  } | null;
  isPartner?: boolean;
}

const customerNav = [
  { label: 'Dashboard', href: '/dashboard', icon: Home },
  { label: 'Invoices', href: '/invoices', icon: FileText },
  { label: 'Chart of Accounts', href: '/chart-of-accounts', icon: BookOpen },
  { label: 'Journals', href: '/journals', icon: Landmark },
  { label: 'Notifications', href: '/notifications', icon: Bell },
];

const partnerNav = [
  { label: 'Dashboard', href: '/dashboard', icon: Home },
  { label: 'Onboarding', href: '/partner-onboarding', icon: Users },
  { label: 'Notifications', href: '/notifications', icon: Bell },
];

export default function ClientShell({
  title,
  eyebrow,
  children,
  organization,
  isPartner = false,
}: ClientShellProps) {
  const location = useLocation();
  const nav = isPartner ? partnerNav : customerNav;

  return (
    <div className="min-h-screen bg-slate-50">
      <aside className="fixed inset-y-0 left-0 hidden w-72 border-r border-border bg-white/90 p-5 shadow-sm backdrop-blur lg:block">
        <div className="flex items-center gap-3">
          <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary text-base font-black text-white">
            OB
          </div>
          <div>
            <p className="text-sm font-bold text-ink">OraBooks</p>
            <p className="text-xs text-slate-500">{isPartner ? 'Partner Account' : 'Client Workspace'}</p>
          </div>
        </div>

        <div className="mt-6 rounded-2xl border border-border bg-slate-50 p-4">
          <p className="truncate text-sm font-semibold text-ink">{organization?.name || 'Workspace setup'}</p>
          <div className="mt-2 flex flex-wrap gap-2">
            {organization?.tier && <span className="badge bg-primary/10 text-primary">{organization.tier}</span>}
            {organization?.status && <span className="badge bg-slate-200 text-slate-700">{organization.status}</span>}
          </div>
        </div>

        <nav className="mt-6 space-y-1.5">
          {nav.map((item) => {
            const Icon = item.icon;
            const active = location.pathname === item.href;
            return (
              <Link
                key={item.href}
                to={item.href}
                className={cn(
                  'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                  active ? 'bg-primary text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-ink'
                )}
              >
                <Icon className="h-4 w-4" />
                {item.label}
              </Link>
            );
          })}
        </nav>

        <a
          href="/wp-login.php?action=logout"
          className="absolute bottom-5 left-5 right-5 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-500 hover:bg-slate-100 hover:text-ink"
        >
          <LogOut className="h-4 w-4" />
          Log out
        </a>
      </aside>

      <main className="lg:pl-72">
        <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
          <header className="mb-6 flex flex-col gap-3 rounded-3xl border border-border bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
              {eyebrow && <p className="text-xs font-bold uppercase tracking-wide text-primary">{eyebrow}</p>}
              <h1 className="mt-1 text-2xl font-bold text-ink">{title}</h1>
            </div>
            {organization?.organization_type && (
              <span className="badge border border-border bg-slate-50 text-slate-700">
                {organization.organization_type === 'partner' ? 'Partner Account (Commission)' : 'Customer Account'}
              </span>
            )}
          </header>
          {children}
        </div>
      </main>
    </div>
  );
}
