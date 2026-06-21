import { useEffect, useState, type MouseEvent } from 'react';
import type { ReactNode } from 'react';
import WpLink from './WpLink';
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
  Info,
  LogOut,
  Menu,
  Mic,
  Package,
  Paperclip,
  Percent,
  Receipt,
  ShieldCheck,
  TrendingUp,
  Upload,
  UserCog,
  Users,
  Wallet,
  X,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { getCurrentAppRoute, normalizeAppRoute } from '../lib/wp-routing';
import { api } from '../api';
import { performLogout } from '../lib/auth-routing';

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
  permission?: string;
};

const customerNav: NavItem[] = [
  { label: 'Dashboard', href: '/dashboard', icon: Home, permission: 'view_reports' },
  { label: 'Notifications', href: '/notifications', icon: Bell },
  { label: 'Customers', href: '/customers', icon: Users, permission: 'view_invoices' },
  { label: 'Invoices & Sales', href: '/invoices', icon: FileText, permission: 'view_invoices' },
  { label: 'Vendors & Bills', href: '/vendors', icon: Building2, permission: 'view_reports' },
  { label: 'Inventory', href: '/inventory', icon: Package, permission: 'manage_inventory' },
  { label: 'Reports', href: '/reports', icon: BarChart3, permission: 'view_reports' },
  { label: 'Expenses', href: '/expenses', icon: Receipt, permission: 'view_expenses' },
  { label: 'Chart of Accounts', href: '/chart-of-accounts', icon: BookOpen, permission: 'view_coa' },
  { label: 'Fiscal Periods', href: '/fiscal-periods', icon: CalendarRange, permission: 'manage_settings' },
  { label: 'Tax Settings', href: '/tax-settings', icon: Percent, permission: 'manage_settings' },
  { label: 'Journals', href: '/journals', icon: BookOpen, permission: 'submit_transaction' },
  { label: 'CSV Imports', href: '/csv-imports', icon: Upload, permission: 'submit_transaction' },
  { label: 'Bank Reconciliation', href: '/bank-reconciliation', icon: Wallet, permission: 'view_reports' },
  { label: 'Voice Input', href: '/voice', icon: Mic, permission: 'view_voice_inputs' },
  { label: 'Attachments', href: '/attachments', icon: Paperclip, permission: 'view_reports' },
  { label: 'AI Review', href: '/ai-review', icon: Bot, permission: 'view_ai_review_queue' },
  { label: 'Approvals', href: '/approvals', icon: ShieldCheck, permission: 'approve_journal' },
  { label: 'Approval Settings', href: '/approval-settings', icon: ShieldCheck, permission: 'manage_org_settings' },
  { label: 'My Exports', href: '/my-exports', icon: Download, permission: 'export_reports' },
  { label: 'Team', href: '/team', icon: UserCog, permission: 'manage_employees' },
  { label: 'Job Monitor', href: '/job-queue', icon: ShieldCheck, permission: 'manage_settings' },
  { label: 'Webhook Settings', href: '/webhook-settings', icon: Upload, permission: 'manage_settings' },
  { label: 'Audit Log', href: '/audit-log', icon: ShieldCheck, permission: 'view_audit_logs' },
  { label: 'Profile', href: '/profile', icon: Users },
];

const partnerNav: NavItem[] = [
  { label: 'Partner Program', href: '/dashboard', icon: Home, permission: 'partner_commission_access' },
  { label: 'Notifications', href: '/notifications', icon: Bell },
  { label: 'Commissions', href: '/commissions', icon: TrendingUp, permission: 'partner_commission_access' },
  { label: 'Onboarding', href: '/onboarding', icon: Users },
  { label: 'My Exports', href: '/my-exports', icon: Download, permission: 'export_reports' },
  { label: 'Team', href: '/team', icon: UserCog, permission: 'manage_employees' },
  { label: 'Job Monitor', href: '/job-queue', icon: ShieldCheck, permission: 'manage_settings' },
  { label: 'Webhook Settings', href: '/webhook-settings', icon: Upload, permission: 'manage_settings' },
  { label: 'Audit Log', href: '/audit-log', icon: ShieldCheck, permission: 'view_audit_logs' },
  { label: 'Profile', href: '/profile', icon: Users },
];

function filterNavByPermissions(items: NavItem[], permissions: string[]) {
  return items.filter((item) => !item.permission || permissions.includes(item.permission));
}

const adminBarTop = { top: 'var(--orabooks-wp-admin-bar, 0px)' } as const;
const shellHeight = { minHeight: 'calc(100dvh - var(--orabooks-wp-admin-bar, 0px))' } as const;
const sidebarHeight = { height: 'calc(100dvh - var(--orabooks-wp-admin-bar, 0px))' } as const;

function NavLinks({
  nav,
  currentRoute,
  onNavigate,
  className,
  linkClassName,
  activeClassName,
  inactiveClassName,
  unreadCount = 0,
}: {
  nav: NavItem[];
  currentRoute: string;
  onNavigate?: () => void;
  className?: string;
  linkClassName?: string;
  activeClassName: string;
  inactiveClassName: string;
  unreadCount?: number;
}) {
  return (
    <nav className={className}>
      {nav.map((item) => {
        const Icon = item.icon;
        const active = !item.external && currentRoute === normalizeAppRoute(item.href);
        const classNames = cn(
          linkClassName,
          active ? activeClassName : inactiveClassName
        );
        const showUnread = item.href === '/notifications' && unreadCount > 0;
        const label = (
          <>
            <Icon className="h-4 w-4 shrink-0" />
            <span className="flex-1">{item.label}</span>
            {showUnread && (
              <span className="ml-auto rounded-full bg-accent px-2 py-0.5 text-xs font-bold text-white">
                {unreadCount > 99 ? '99+' : unreadCount}
              </span>
            )}
          </>
        );

        if (item.external) {
          return (
            <a key={item.href} href={item.href} className={classNames} onClick={onNavigate}>
              {label}
            </a>
          );
        }

        return (
          <WpLink key={item.href} to={item.href} className={classNames} onClick={onNavigate}>
            {label}
          </WpLink>
        );
      })}
    </nav>
  );
}

export default function ClientShell({
  title,
  eyebrow,
  children,
  organization,
  isPartner = false,
}: ClientShellProps) {
  const currentRoute = getCurrentAppRoute();
  const [permissions, setPermissions] = useState<string[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const baseNav = isPartner ? partnerNav : customerNav;
  const nav = filterNavByPermissions(baseNav, permissions);
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);
  const [userRole, setUserRole] = useState('');
  const [needs2faSetup, setNeeds2faSetup] = useState(false);

  useEffect(() => {
    void api.frontendContext().then((res) => {
      const data = (res as any).data;
      const role = data?.role;
      if (typeof role === 'string' && role.trim() !== '') {
        setUserRole(role.charAt(0).toUpperCase() + role.slice(1));
      }
      if (Array.isArray(data?.permissions)) {
        setPermissions(data.permissions);
      }
      setNeeds2faSetup(Boolean(data?.security?.needs_2fa_setup));
    });

    void api.notificationsUnreadCount().then((res) => {
      const count = (res as any).data?.count ?? (res as any).data?.unread_count;
      if (typeof count === 'number') {
        setUnreadCount(count);
      }
    });
  }, []);

  const handleLogout = async (event: MouseEvent<HTMLAnchorElement>) => {
    event.preventDefault();
    if (loggingOut) {
      return;
    }
    setLoggingOut(true);
    await performLogout(() => api.logout());
  };

  useEffect(() => {
    setMobileNavOpen(false);
  }, [currentRoute]);

  useEffect(() => {
    document.body.style.overflow = mobileNavOpen ? 'hidden' : '';
    return () => {
      document.body.style.overflow = '';
    };
  }, [mobileNavOpen]);

  const closeMobileNav = () => setMobileNavOpen(false);

  const brandBlock = (
    <div className="flex items-center gap-3">
      <div className="flex min-w-0 flex-1 items-center gap-3">
        <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-base font-black text-primary">
          OB
        </div>
        <div className="min-w-0">
          <p className="truncate text-sm font-bold text-white">OraBooks</p>
          <p className="truncate text-xs text-white/70">{isPartner ? 'Partner Account (Commission)' : 'Accounting Workspace'}</p>
        </div>
      </div>
      <WpLink
        to="/notifications"
        onClick={closeMobileNav}
        className={cn(
          'relative flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border transition',
          currentRoute === '/notifications'
            ? 'border-accent/40 bg-accent text-white shadow-lg shadow-accent/30'
            : 'border-white/20 bg-white/10 text-white hover:border-white/40 hover:bg-white/15'
        )}
        title="Notifications"
        aria-label="Notifications"
      >
        <Bell className="h-5 w-5" />
        {unreadCount > 0 && (
          <span className="absolute -right-1 -top-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-accent px-1 text-[10px] font-bold text-white ring-2 ring-primary">
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        )}
      </WpLink>
    </div>
  );

  const orgBlock = (
    <div className="mt-6 rounded-2xl border border-white/15 bg-white/10 p-4">
      <p className="truncate text-sm font-semibold text-white">{organization?.name || 'Workspace setup'}</p>
      <div className="mt-2 flex flex-wrap gap-2">
        {isPartner && (
          <span
            className="badge inline-flex items-center gap-1 border border-success/30 bg-success/15 text-success"
            title="You earn commissions from qualified customers attributed to your Partner Code. No accounting features."
          >
            Partner Account (Commission)
            <Info className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
          </span>
        )}
        {userRole && <span className="badge bg-white/90 text-ink">Role: {userRole}</span>}
        {!isPartner && organization?.tier && <span className="badge bg-white text-primary">{organization.tier}</span>}
        {organization?.status && <span className="badge bg-accent text-white">{organization.status}</span>}
      </div>
    </div>
  );

  const logoutLink = (
    <a
      href="#"
      onClick={handleLogout}
      className="mt-4 flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-white/70 hover:bg-white/10 hover:text-white"
    >
      <LogOut className="h-4 w-4 shrink-0" />
      {loggingOut ? 'Logging out…' : 'Log out'}
    </a>
  );

  const sidebarContent = (
    <>
      <div className="shrink-0">
        {brandBlock}
        {orgBlock}
      </div>

      <NavLinks
        nav={nav}
        currentRoute={currentRoute}
        onNavigate={closeMobileNav}
        unreadCount={unreadCount}
        className="scrollbar-hide mt-6 min-h-0 flex-1 space-y-1.5 overflow-y-auto overscroll-contain"
        linkClassName="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition"
        activeClassName="bg-accent text-white shadow-sm"
        inactiveClassName="text-white/75 hover:bg-white/10 hover:text-white"
      />

      {logoutLink}
    </>
  );

  return (
    <div className="orabooks-client-shell brand-page-bg w-full" style={shellHeight}>
      <aside
        className="orabooks-client-sidebar fixed left-0 z-[100] hidden w-72 flex-col bg-primary p-5 text-white shadow-xl shadow-primary/20 lg:flex"
        style={{ ...adminBarTop, ...sidebarHeight }}
      >
        {sidebarContent}
      </aside>

      {mobileNavOpen && (
        <button
          type="button"
          className="orabooks-mobile-drawer-backdrop fixed inset-x-0 bottom-0 bg-black/40 lg:hidden"
          style={adminBarTop}
          aria-label="Close navigation menu"
          onClick={closeMobileNav}
        />
      )}

      <aside
        className={cn(
          'orabooks-mobile-drawer fixed left-0 z-[111] flex w-[min(100vw,18rem)] flex-col bg-primary p-5 text-white shadow-xl transition-transform duration-200 lg:hidden',
          mobileNavOpen ? 'translate-x-0' : 'pointer-events-none -translate-x-full'
        )}
        style={{ ...adminBarTop, ...sidebarHeight }}
        aria-hidden={!mobileNavOpen}
      >
        <div className="flex shrink-0 items-center justify-between gap-3">
          {brandBlock}
          <button
            type="button"
            className="rounded-lg p-2 text-white/80 hover:bg-white/10 hover:text-white"
            aria-label="Close navigation menu"
            onClick={closeMobileNav}
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {orgBlock}

        <NavLinks
          nav={nav}
          currentRoute={currentRoute}
          onNavigate={closeMobileNav}
          unreadCount={unreadCount}
          className="scrollbar-hide mt-6 min-h-0 flex-1 space-y-1.5 overflow-y-auto overscroll-contain"
          linkClassName="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition"
          activeClassName="bg-accent text-white shadow-sm"
          inactiveClassName="text-white/75 hover:bg-white/10 hover:text-white"
        />

        {logoutLink}
      </aside>

      <div role="main" className="orabooks-client-main min-w-0 lg:pl-72">
        <div
          className="sticky z-[99] border-b border-primary/10 bg-white/95 shadow-sm shadow-primary/5 backdrop-blur lg:hidden"
          style={adminBarTop}
        >
          <div className="flex items-center justify-between px-4 py-3">
            <div className="flex min-w-0 items-center gap-3">
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary text-sm font-black text-white">
                OB
              </div>
              <div className="min-w-0">
                <p className="truncate text-sm font-bold text-ink">OraBooks</p>
                <p className="truncate text-xs text-ink-secondary">{title}</p>
              </div>
            </div>
            <button
              type="button"
              className="rounded-lg p-2 text-primary hover:bg-primary/10"
              aria-label="Open navigation menu"
              aria-expanded={mobileNavOpen}
              onClick={() => setMobileNavOpen(true)}
            >
              <Menu className="h-5 w-5" />
            </button>
          </div>
        </div>

        <div className="w-full px-4 py-6 sm:px-6 lg:px-8">
          <header className="mb-6 overflow-hidden rounded-3xl border border-border bg-white shadow-sm shadow-primary/5">
            <div className="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
              <div className="min-w-0">
                {eyebrow && <p className="text-xs font-bold uppercase tracking-wide text-primary">{eyebrow}</p>}
                <h1 className="text-2xl font-black text-ink sm:text-3xl">{title}</h1>
              </div>
            </div>
          </header>
          {needs2faSetup && currentRoute !== '/security/2fa' && currentRoute !== '/profile' && (
            <div className="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
              Your organization requires two-factor authentication.{' '}
              <WpLink href="/security/2fa" className="font-semibold text-primary underline">
                Set up 2FA on the security page
              </WpLink>{' '}
              to continue using OraBooks.
            </div>
          )}
          {children}
        </div>
      </div>
    </div>
  );
}
