import { Link, useLocation } from 'react-router-dom';
import type { ReactNode } from 'react';
import {
  BarChart3,
  Bell,
  BookOpen,
  Building2,
  Download,
  FileText,
  Home,
  Landmark,
  LogOut,
  Menu,
  Package,
  Users,
  Wallet,
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface ClientShellProps {
  title: string;
  eyebrow?: string;
  children: ReactNode;
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
  { label: 'Customers', href: '/customers', icon: Users },
  { label: 'Invoices', href: '/invoices', icon: FileText },
  { label: 'Vendors & Bills', href: '/vendors', icon: Building2 },
  { label: 'Inventory', href: '/inventory', icon: Package },
  { label: 'Bank Reconciliation', href: '/bank-reconciliation', icon: Wallet },
  { label: 'Reports', href: '/reports', icon: BarChart3 },
  { label: 'Chart of Accounts', href: '/chart-of-accounts', icon: BookOpen },
  { label: 'Journals', href: '/journals', icon: Landmark },
  { label: 'Notifications', href: '/notifications', icon: Bell },
  { label: 'My Exports', href: '/my-exports', icon: Download },
  { label: 'Profile', href: '/profile', icon: Users },
];

const partnerNav = [
  { label: 'Dashboard', href: '/dashboard', icon: Home },
  { label: 'Onboarding', href: '/partner-onboarding', icon: Users },
  { label: 'Notifications', href: '/notifications', icon: Bell },
  { label: 'My Exports', href: '/my-exports', icon: Download },
  { label: 'Profile', href: '/profile', icon: Users },
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
  const logoutUrl = (window as any).orabooks_ajax?.logout_url || '/wp-login.php?action=logout';

  return (
    <div className="min-h-screen brand-page-bg">
      <aside className="fixed inset-y-0 left-0 hidden w-72 bg-primary p-5 text-white shadow-xl shadow-primary/20 lg:block">
        <div className="flex items-center gap-3">
          <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-base font-black text-primary">
            OB
          </div>
          <div>
            <p className="text-sm font-bold text-white">OraBooks</p>
            <p className="text-xs text-white/70">{isPartner ? 'Partner Account' : 'Client Workspace'}</p>
          </div>
        </div>

        <div className="mt-6 rounded-2xl border border-white/15 bg-white/10 p-4">
          <p className="truncate text-sm font-semibold text-white">{organization?.name || 'Workspace setup'}</p>
          <div className="mt-2 flex flex-wrap gap-2">
            {organization?.tier && <span className="badge bg-white text-primary">{organization.tier}</span>}
            {organization?.status && <span className="badge bg-accent text-white">{organization.status}</span>}
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
                  active ? 'bg-accent text-white shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white'
                )}
              >
                <Icon className="h-4 w-4" />
                {item.label}
              </Link>
            );
          })}
        </nav>

        <a
          href={logoutUrl}
          onClick={() => {
            window.localStorage.removeItem('orabooks_token');
            window.localStorage.removeItem('orabooks_refresh_token');
          }}
          className="absolute bottom-5 left-5 right-5 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-white/70 hover:bg-white/10 hover:text-white"
        >
          <LogOut className="h-4 w-4" />
          Log out
        </a>
      </aside>

      <main className="lg:pl-72">
        <div className="sticky top-0 z-20 border-b border-primary/10 bg-white/95 shadow-sm shadow-primary/5 backdrop-blur lg:hidden">
          <div className="flex items-center justify-between px-4 py-3">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-sm font-black text-white">
                OB
              </div>
              <div>
                <p className="text-sm font-bold text-ink">OraBooks</p>
                <p className="text-xs text-ink-secondary">{isPartner ? 'Partner Account' : 'Client Workspace'}</p>
              </div>
            </div>
            <Menu className="h-5 w-5 text-primary" />
          </div>
          <nav className="flex gap-2 overflow-x-auto px-4 pb-3">
            {nav.map((item) => {
              const Icon = item.icon;
              const active = location.pathname === item.href;
              return (
                <Link
                  key={item.href}
                  to={item.href}
                  className={cn(
                    'flex shrink-0 items-center gap-2 rounded-full px-3 py-2 text-xs font-bold transition',
                    active ? 'bg-accent text-white' : 'bg-primary/10 text-primary hover:bg-primary hover:text-white'
                  )}
                >
                  <Icon className="h-3.5 w-3.5" />
                  {item.label}
                </Link>
              );
            })}
          </nav>
        </div>
        <div className="w-full px-4 py-6 sm:px-6 lg:px-8">
          <header className="mb-6 overflow-hidden rounded-3xl border border-border bg-white shadow-sm shadow-primary/5">
            <div className="brand-accent-bar h-1.5" />
            <div className="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
              <div>
                {eyebrow && <p className="text-xs font-bold uppercase tracking-wide text-primary">{eyebrow}</p>}
                <h1 className="mt-1 text-2xl font-bold text-ink">{title}</h1>
              </div>
              {organization?.organization_type && (
                <span className="badge border border-primary/20 bg-primary/10 text-primary">
                  {organization.organization_type === 'partner' ? 'Partner Account (Commission)' : 'Customer Account'}
                </span>
              )}
            </div>
          </header>
          {children}
        </div>
      </main>
    </div>
  );
}
