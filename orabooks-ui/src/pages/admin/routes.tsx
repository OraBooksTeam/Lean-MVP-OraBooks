import { lazy, Suspense } from 'react';
import type { ReactNode } from 'react';

const AdminDashboard = lazy(() => import('@pages/admin/AdminDashboard'));
const AdminOrganizations = lazy(() => import('@pages/admin/AdminOrganizations'));
const AdminUsers = lazy(() => import('@pages/admin/AdminUsers'));
const AdminPartners = lazy(() => import('@pages/admin/AdminPartners'));
const AdminCoA = lazy(() => import('@pages/admin/AdminCoA'));
const AdminAudit = lazy(() => import('@pages/admin/AdminAudit'));

function Fallback() {
  return <div className="p-8 text-center text-sm text-slate-500">Loading…</div>;
}

export default function AdminRoutes() {
  const path = window.location.pathname;

  const Component = (() => {
    if (path.endsWith('/organizations')) return AdminOrganizations;
    if (path.endsWith('/users')) return AdminUsers;
    if (path.endsWith('/partners')) return AdminPartners;
    if (path.endsWith('/coa')) return AdminCoA;
    if (path.endsWith('/audit')) return AdminAudit;
    return AdminDashboard;
  })();

  return (
    <div className="flex min-h-screen bg-slate-50 text-ink">
      <Sidebar />
      <main className="flex-1 overflow-y-auto p-6">
        <div className="mx-auto max-w-7xl">
          <Suspense fallback={<Fallback />}>
            <Component />
          </Suspense>
        </div>
      </main>
    </div>
  );
}
