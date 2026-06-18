import { Routes, Route, Navigate } from 'react-router-dom';
import LoginPage from './pages/LoginPage';
import RegisterPage from './pages/RegisterPage';
import TierSelectionPage from './pages/TierSelectionPage';
import DashboardPage from './pages/DashboardPage';
import PartnerOnboardingPage from './pages/PartnerOnboardingPage';
import NotificationsPage from './pages/NotificationsPage';
import ExportStatusPage from './pages/ExportStatusPage';
import InvoicesPage from './pages/InvoicesPage';
import ChartOfAccountsPage from './pages/ChartOfAccountsPage';
import JournalsPage from './pages/JournalsPage';
import CustomersPage from './pages/CustomersPage';
import VendorsPage from './pages/VendorsPage';
import InventoryPage from './pages/InventoryPage';
import BankReconciliationPage from './pages/BankReconciliationPage';
import ReportsPage from './pages/ReportsPage';
import CsvImportsPage from './pages/CsvImportsPage';
import TeamPage from './pages/TeamPage';
import AttachmentsPage from './pages/AttachmentsPage';
import ProfilePage from './pages/ProfilePage';
import NotificationPreferencesPage from './pages/NotificationPreferencesPage';

export default function FrontendRoutes() {
  return (
    <Routes>
      <Route path="/" element={<LoginPage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/tier-selection" element={<TierSelectionPage />} />
      <Route path="/dashboard" element={<DashboardPage />} />
      <Route path="/customers" element={<CustomersPage />} />
      <Route path="/vendors" element={<VendorsPage />} />
      <Route path="/inventory" element={<InventoryPage />} />
      <Route path="/bank-reconciliation" element={<BankReconciliationPage />} />
      <Route path="/reports" element={<ReportsPage />} />
      <Route path="/csv-imports" element={<CsvImportsPage />} />
      <Route path="/team" element={<TeamPage />} />
      <Route path="/attachments" element={<AttachmentsPage />} />
      <Route path="/invoices" element={<InvoicesPage />} />
      <Route path="/chart-of-accounts" element={<ChartOfAccountsPage />} />
      <Route path="/journals" element={<JournalsPage />} />
      <Route path="/partner-onboarding" element={<PartnerOnboardingPage />} />
      <Route path="/notifications" element={<NotificationsPage />} />
      <Route path="/notification-preferences" element={<NotificationPreferencesPage />} />
      <Route path="/my-exports" element={<ExportStatusPage />} />
      <Route path="/profile" element={<ProfilePage />} />
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  );
}
