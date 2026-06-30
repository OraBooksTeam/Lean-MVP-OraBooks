import { useEffect, useState } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import AdminSubnav from '@/components/AdminSubnav';
import AdminDashboard from '@/pages/admin/AdminDashboard';
import AdminOrganizations from '@/pages/admin/AdminOrganizations';
import AdminUsers from '@/pages/admin/AdminUsers';
import AdminPartners from '@/pages/admin/AdminPartners';
import AdminCoA from '@/pages/admin/AdminCoA';
import AdminAudit from '@/pages/admin/AdminAudit';
import AdminJobQueue from '@/pages/admin/AdminJobQueue';
import AdminWebhookSettings from '@/pages/admin/AdminWebhookSettings';
import AdminObservability from '@/pages/admin/AdminObservability';
import AdminSecurity from '@/pages/admin/AdminSecurity';
import AdminNotifications from '@/pages/admin/AdminNotifications';
import AdminExports from '@/pages/admin/AdminExports';
import AdminCustomers from '@/pages/admin/AdminCustomers';
import AdminCsvImports from '@/pages/admin/AdminCsvImports';
import AdminCommissions from '@/pages/admin/AdminCommissions';
import AdminSettings from '@/pages/admin/AdminSettings';
import { cn } from '@/lib/utils';
import { LayoutDashboard, Building2, Users, UserCheck, BookOpen, ClipboardList, Settings as SettingsIcon, Menu, X, BarChart3, FileText, Bell, ShieldCheck, RefreshCw, Eye } from 'lucide-react';

interface AdminNavItem {
  slug: string;
  label: string;
  route: string;
}

function adminUrl(slug: string) {
  const base = (window as any).orabooks_ajax?.admin_base || '/wp-admin/admin.php';
  return `${base}?page=${slug}`;
}

function getAdminNavItems(): AdminNavItem[] {
  return (window as any).orabooks_ajax?.admin_nav || [];
}

const defaultAdminRoute = (() => {
  const ajax = (window as any).orabooks_ajax;
  if (ajax?.is_admin) return '/admin/dashboard';
  const nav = ajax?.admin_nav as { route: string }[] | undefined;
  return nav?.[0]?.route || '/admin/notifications';
})();

const navIconMap: Record<string, typeof LayoutDashboard> = {
  dashboard: LayoutDashboard,
  organizations: Building2,
  users: Users,
  partners: UserCheck,
  coa: BookOpen,
  audit: ClipboardList,
  security: ShieldCheck,
  notifications: Bell,
  exports: FileText,
  customers: UserCheck,
  'csv-imports': BarChart3,
  commissions: BarChart3,
  'job-queue': RefreshCw,
  'webhook-settings': Eye,
  observability: Eye,
  settings: SettingsIcon,
};

function getNavIcon(slug: string) {
  const Icon = navIconMap[slug] || LayoutDashboard;
  return <Icon className="h-4 w-4 shrink-0" />;
}

export default function AdminRoutes() {
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const navItems = getAdminNavItems();

  const closeMobileNav = () => setMobileNavOpen(false);

  useEffect(() => {
    document.body.style.overflow = mobileNavOpen ? 'hidden' : '';
    return () => {
      document.body.style.overflow = '';
    };
  }, [mobileNavOpen]);

  const drawerContent = (
    <>
      <div className="flex shrink-0 items-center justify-between gap-2">
        <div className="flex items-center gap-2.5">
          <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-sm font-black text-white">
            OB
          </div>
          <span className="text-base font-bold text-ink">OraBooks</span>
        </div>
        <button
          type="button"
          className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
          aria-label="Close navigation menu"
          onClick={closeMobileNav}
        >
          <X className="h-5 w-5" />
        </button>
      </div>

      <nav className="mt-6 flex-1 space-y-1 overflow-y-auto overscroll-contain scrollbar-hide">
        {navItems.map((item) => (
          <a
            key={item.slug}
            href={adminUrl(item.slug)}
            className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 hover:text-ink"
            onClick={closeMobileNav}
          >
            {getNavIcon(item.slug)}
            {item.label}
          </a>
        ))}
        ))}
      </nav>
    </>
  );

  return (
    <div className="orabooks-wp-admin min-h-[640px] rounded-xl bg-muted/40 p-1 text-ink">
      {/* Mobile header bar */}
      <div className="sticky top-0 z-[99] -mx-1 mb-4 rounded-t-xl border-b border-border bg-white/95 backdrop-blur lg:hidden">
        <div className="flex items-center justify-between px-4 py-3">
          <div className="flex min-w-0 items-center gap-2.5">
            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary text-xs font-black text-white">
              OB
            </div>
            <div className="min-w-0">
              <p className="truncate text-sm font-bold text-ink">OraBooks</p>
              <p className="truncate text-xs text-slate-500">Admin Panel</p>
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

      {/* Backdrop */}
      {mobileNavOpen && (
        <button
          type="button"
          className="fixed inset-0 z-[100] bg-black/40 lg:hidden"
          aria-label="Close navigation menu"
          onClick={closeMobileNav}
        />
      )}

      {/* Mobile drawer */}
      <aside
        className={cn(
          'fixed left-0 top-0 z-[111] flex h-dvh w-[min(100vw,18rem)] flex-col bg-white p-5 shadow-xl transition-transform duration-200 lg:hidden',
          mobileNavOpen ? 'translate-x-0' : 'pointer-events-none -translate-x-full'
        )}
        aria-hidden={!mobileNavOpen}
      >
        {drawerContent}
      </aside>

      <AdminSubnav />
      <Routes>
        <Route path="/admin/dashboard" element={<AdminDashboard />} />
        <Route path="/admin/organizations" element={<AdminOrganizations />} />
        <Route path="/admin/users" element={<AdminUsers />} />
        <Route path="/admin/partners" element={<AdminPartners />} />
        <Route path="/admin/coa" element={<AdminCoA />} />
        <Route path="/admin/audit" element={<AdminAudit />} />
        <Route path="/admin/job-queue" element={<AdminJobQueue />} />
        <Route path="/admin/webhook-settings" element={<AdminWebhookSettings />} />
        <Route path="/admin/observability" element={<AdminObservability />} />
        <Route path="/admin/security" element={<AdminSecurity />} />
        <Route path="/admin/notifications" element={<AdminNotifications />} />
        <Route path="/admin/exports" element={<AdminExports />} />
        <Route path="/admin/customers" element={<AdminCustomers />} />
        <Route path="/admin/csv-imports" element={<AdminCsvImports />} />
        <Route path="/admin/commissions" element={<AdminCommissions />} />
        <Route path="/admin/settings" element={<AdminSettings />} />
        <Route path="*" element={<Navigate to={defaultAdminRoute} replace />} />
      </Routes>
    </div>
  );
}
