import { lazy, Suspense } from 'react';
import { useLocation } from 'react-router-dom';
import type { ReactNode } from 'react';
import Sidebar from '@/components/Sidebar';

const AdminDashboard = lazy(() => import('@/pages/admin/AdminDashboard'));
const AdminOrganizations = lazy(() => import('@/pages/admin/AdminOrganizations'));
const AdminUsers = lazy(() => import('@/pages/admin/AdminUsers'));
const AdminPartners = lazy(() => import('@/pages/admin/AdminPartners'));
const AdminCoA = lazy(() => import('@/pages/admin/AdminCoA'));
const AdminAudit = lazy(() => import('@/pages/admin/AdminAudit'));

function Fallback() {
  return <div className="p-8 text-center text-sm text-slate-500">Loading…</div>;
}

const routeComponents: Record<string, ReactNode> = {
  '/admin/organizations': <AdminOrganizations />,
  '/admin/users': <AdminUsers />,
  '/admin/partners': <AdminPartners />,
  '/admin/coa': <AdminCoA />,
  '/admin/audit': <AdminAudit />,
};

export default function AdminRoutes() {
  const { pathname } = useLocation();
  const component = pathname in routeComponents ? routeComponents[pathname] : <AdminDashboard />;

  return (
    <div className="flex min-h-screen bg-slate-50 text-ink">
      <Sidebar />
      <main className="flex-1 overflow-y-auto p-6">
        <div className="mx-auto max-w-7xl">
          <Suspense fallback={<Fallback />}>{component}</Suspense>
        </div>
      </main>
    </div>
  );
}
