import { Navigate, Route, Routes } from 'react-router-dom';
import AdminSubnav from '@/components/AdminSubnav';
import AdminDashboard from '@/pages/admin/AdminDashboard';
import AdminOrganizations from '@/pages/admin/AdminOrganizations';
import AdminUsers from '@/pages/admin/AdminUsers';
import AdminPartners from '@/pages/admin/AdminPartners';
import AdminCoA from '@/pages/admin/AdminCoA';
import AdminAudit from '@/pages/admin/AdminAudit';
import AdminJobQueue from '@/pages/admin/AdminJobQueue';
import AdminObservability from '@/pages/admin/AdminObservability';
import AdminNotifications from '@/pages/admin/AdminNotifications';
import AdminExports from '@/pages/admin/AdminExports';
import AdminCustomers from '@/pages/admin/AdminCustomers';
import AdminCsvImports from '@/pages/admin/AdminCsvImports';
import AdminCommissions from '@/pages/admin/AdminCommissions';
import AdminSettings from '@/pages/admin/AdminSettings';

const defaultAdminRoute =
  (window as any).orabooks_ajax?.is_admin ? '/admin/dashboard' : '/admin/commissions';

export default function AdminRoutes() {
  return (
    <div className="orabooks-wp-admin min-h-[640px] rounded-xl bg-muted/40 p-1 text-ink">
      <AdminSubnav />
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
        <Route path="/admin/settings" element={<AdminSettings />} />
        <Route path="*" element={<Navigate to={defaultAdminRoute} replace />} />
      </Routes>
    </div>
  );
}
