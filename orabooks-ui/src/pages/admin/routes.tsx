import { lazy, Suspense } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';

const AdminDashboard = lazy(() => import('@/pages/admin/AdminDashboard'));
const AdminOrganizations = lazy(() => import('@/pages/admin/AdminOrganizations'));
const AdminUsers = lazy(() => import('@/pages/admin/AdminUsers'));
const AdminPartners = lazy(() => import('@/pages/admin/AdminPartners'));
const AdminCoA = lazy(() => import('@/pages/admin/AdminCoA'));
const AdminAudit = lazy(() => import('@/pages/admin/AdminAudit'));
const AdminJobQueue = lazy(() => import('@/pages/admin/AdminJobQueue'));
const AdminObservability = lazy(() => import('@/pages/admin/AdminObservability'));
const AdminNotifications = lazy(() => import('@/pages/admin/AdminNotifications'));
const AdminExports = lazy(() => import('@/pages/admin/AdminExports'));
const AdminCustomers = lazy(() => import('@/pages/admin/AdminCustomers'));
const AdminCsvImports = lazy(() => import('@/pages/admin/AdminCsvImports'));
const AdminCommissions = lazy(() => import('@/pages/admin/AdminCommissions'));

function Fallback() {
  return (
    <div className="flex min-h-[320px] items-center justify-center rounded-2xl border border-border bg-white">
      <p className="text-sm text-slate-500">Loading workspace…</p>
    </div>
  );
}

export default function AdminRoutes() {
  return (
    <div className="orabooks-wp-admin min-h-[640px] rounded-xl bg-muted/40 p-1 text-ink">
      <Suspense fallback={<Fallback />}>
        <Routes>
          <Route path="/admin/dashboard" element={<AdminDashboard />} />
          <Route path="/admin/organizations" element={<AdminOrganizations />} />
          <Route path="/admin/users" element={<AdminUsers />} />
          <Route path="/admin/partners" element={<AdminPartners />} />
          <Route path="/admin/coa" element={<AdminCoA />} />
          <Route path="/admin/audit" element={<AdminAudit />} />
          <Route path="/admin/job-queue" element={<AdminJobQueue />} />
          <Route path="/admin/observability" element={<AdminObservability />} />
          <Route path="/admin/notifications" element={<AdminNotifications />} />
          <Route path="/admin/exports" element={<AdminExports />} />
          <Route path="/admin/customers" element={<AdminCustomers />} />
          <Route path="/admin/csv-imports" element={<AdminCsvImports />} />
          <Route path="/admin/commissions" element={<AdminCommissions />} />
          <Route path="*" element={<Navigate to="/admin/dashboard" replace />} />
        </Routes>
      </Suspense>
    </div>
  );
}
