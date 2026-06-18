import { Link, useLocation } from 'react-router-dom';
import type { ReactNode } from 'react';
import {
  BarChart3,
  Bell,
  BookOpen,
  Bot,
  Building2,
  CalendarRange,
  Download,
  FileText,
  Home,
  LogOut,
  Menu,
  Mic,
  Package,
  Paperclip,
  Percent,
  Receipt,
  RefreshCw,
  ShieldCheck,
  TrendingUp,
  Upload,
  UserCog,
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

type NavItem = {
  label: string;
  href: string;
  icon: typeof Home;
  external?: boolean;
};

const acc = (view = '') => (view ? `/dashboard/?view=${view}` : '/dashboard/');

const customerNav: NavItem[] = [
  { label: 'Dashboard', href: acc(), icon: Home, external: true },
  { label: 'Customers', href: acc('customers'), icon: Users, external: true },
  { label: 'Invoices & Sales', href: acc('view-sales'), icon: FileText, external: true },
  { label: 'Vendors & Bills', href: acc('suppliers'), icon: Building2, external: true },
  { label: 'Inventory', href: acc('view-items'), icon: Package, external: true },
  { label: 'Reports', href: acc('journal-report'), icon: BarChart3, external: true },
  { label: 'Expenses', href: acc('expense-list'), icon: Receipt, external: true },
  { label: 'Chart of Accounts', href: acc('coa-list'), icon: BookOpen, external: true },
  { label: 'Fiscal Periods', href: acc('fiscal-periods'), icon: CalendarRange, external: true },
  { label: 'Tax Settings', href: acc('setting-tax-list'), icon: Percent, external: true },
  { label: 'Journals', href: acc('journal-entry-list'), icon: BookOpen, external: true },
  { label: 'CSV Imports', href: acc('import-customers'), icon: Upload, external: true },
  { label: 'Bank Reconciliation', href: '/bank-reconciliation', icon: Wallet },
  { label: 'Voice Input', href: '/voice', icon: Mic },
  { label: 'Attachments', href: '/attachments', icon: Paperclip },
  { label: 'AI Review', href: '/ai-review', icon: Bot },
  { label: 'Approvals', href: '/approvals', icon: ShieldCheck },
  { label: 'Notifications', href: '/notifications', icon: Bell },
  { label: 'My Exports', href: '/my-exports', icon: Download },
  { label: 'Team', href: '/team', icon: UserCog },
  { label: 'Profile', href: '/profile', icon: Users },
];

const partnerNav = [
  { label: 'Partner Program', href: '/dashboard', icon: Home },
  { label: 'Commissions', href: '/commissions', icon: TrendingUp },
  { label: 'Onboarding', href: '/partner-onboarding', icon: Users },
  { label: 'Notifications', href: '/notifications', icon: Bell },
  { label: 'My Exports', href: '/my-exports', icon: Download },
  { label: 'Team', href: '/team', icon: UserCog },
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
      <aside className="fixed inset-y-0 left-0 z-30 hidden w-72 flex-col bg-primary p-5 text-white shadow-xl shadow-primary/20 lg:flex">
        <div className="shrink-0">
          <div className="flex items-center gap-3">
            <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-base font-black text-primary">
              OB
            </div>
            <div>
              <p className="text-sm font-bold text-white">OraBooks</p>
              <p className="text-xs text-white/70">{isPartner ? 'Partner Account' : 'Accounting Workspace'}</p>
            </div>
          </div>

          <div className="mt-6 rounded-2xl border border-white/15 bg-white/10 p-4">
            <p className="truncate text-sm font-semibold text-white">{organization?.name || 'Workspace setup'}</p>
            <div className="mt-2 flex flex-wrap gap-2">
              {organization?.tier && <span className="badge bg-white text-primary">{organization.tier}</span>}
              {organization?.status && <span className="badge bg-accent text-white">{organization.status}</span>}
            </div>
          </div>
        </div>

        <nav className="mt-6 min-h-0 flex-1 space-y-1.5 overflow-y-auto overscroll-contain pr-1">
          {nav.map((item) => {
            const Icon = item.icon;
            const active = !item.external && location.pathname === item.href;
            const className = cn(
              'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
              active ? 'bg-accent text-white shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white'
            );

            if (item.external) {
              return (
                <a key={item.href} href={item.href} className={className}>
                  <Icon className="h-4 w-4 shrink-0" />
                  {item.label}
                </a>
              );
            }

            return (
              <Link key={item.href} to={item.href} className={className}>
                <Icon className="h-4 w-4 shrink-0" />
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
          className="mt-4 flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-white/70 hover:bg-white/10 hover:text-white"
        >
          <LogOut className="h-4 w-4 shrink-0" />
          Log out
        </a>
      </aside>

      <main className="min-w-0 lg:pl-72">
        <div className="sticky top-0 z-20 border-b border-primary/10 bg-white/95 shadow-sm shadow-primary/5 backdrop-blur lg:hidden">
          <div className="flex items-center justify-between px-4 py-3">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-sm font-black text-white">
                OB
              </div>
              <div>
                <p className="text-sm font-bold text-ink">OraBooks</p>
                <p className="text-xs text-ink-secondary">{isPartner ? 'Partner Account' : 'Accounting Workspace'}</p>
              </div>
            </div>
            <Menu className="h-5 w-5 text-primary" />
          </div>
          <nav className="flex gap-2 overflow-x-auto px-4 pb-3">
            {nav.map((item) => {
              const Icon = item.icon;
              const active = !item.external && location.pathname === item.href;
              const className = cn(
                'flex shrink-0 items-center gap-2 rounded-full px-3 py-2 text-xs font-bold transition',
                active ? 'bg-accent text-white' : 'bg-primary/10 text-primary hover:bg-primary hover:text-white'
              );

              if (item.external) {
                return (
                  <a key={item.href} href={item.href} className={className}>
                    <Icon className="h-3.5 w-3.5" />
                    {item.label}
                  </a>
                );
              }

              return (
                <Link key={item.href} to={item.href} className={className}>
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
                <h1 className="text-2xl font-black text-ink sm:text-3xl">{title}</h1>
              </div>
            </div>
          </header>
          {children}
        </div>
      </main>
    </div>
  );
}
