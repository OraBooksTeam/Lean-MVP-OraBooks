import { Routes, Route, Navigate } from 'react-router-dom';
import LoginPage from './pages/LoginPage';
import RegisterPage from './pages/RegisterPage';
import TierSelectionPage from './pages/TierSelectionPage';
import DashboardPage from './pages/DashboardPage';
import PartnerOnboardingPage from './pages/PartnerOnboardingPage';
import PartnerProgramPage from './pages/PartnerProgramPage';
import CommissionsPage from './pages/CommissionsPage';
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
import ApprovalsPage from './pages/ApprovalsPage';
import AiReviewPage from './pages/AiReviewPage';
import ExpensesPage from './pages/ExpensesPage';
import VoicePage from './pages/VoicePage';
import FiscalPeriodsPage from './pages/FiscalPeriodsPage';
import TaxSettingsPage from './pages/TaxSettingsPage';
import ProfilePage from './pages/ProfilePage';
import NotificationPreferencesPage from './pages/NotificationPreferencesPage';
import ResetPasswordPage from './pages/ResetPasswordPage';
import VerifyEmailPage from './pages/VerifyEmailPage';
import JobQueuePage from './pages/JobQueuePage';
import ObservabilityPage from './pages/ObservabilityPage';
import NotificationAdminPage from './pages/NotificationAdminPage';
import CommissionAdminPage from './pages/CommissionAdminPage';

export default function FrontendRoutes() {
  return (
    <Routes>
      <Route path="/" element={<LoginPage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/reset-password" element={<ResetPasswordPage />} />
      <Route path="/verify-email" element={<VerifyEmailPage />} />
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
      <Route path="/fiscal-periods" element={<FiscalPeriodsPage />} />
      <Route path="/tax-settings" element={<TaxSettingsPage />} />
      <Route path="/journals" element={<JournalsPage />} />
      <Route path="/approvals" element={<ApprovalsPage />} />
      <Route path="/ai-review" element={<AiReviewPage />} />
      <Route path="/expenses" element={<ExpensesPage />} />
      <Route path="/voice" element={<VoicePage />} />
      <Route path="/partner-onboarding" element={<PartnerOnboardingPage />} />
      <Route path="/partner-program" element={<PartnerProgramPage />} />
      <Route path="/commissions" element={<CommissionsPage />} />
      <Route path="/notifications" element={<NotificationsPage />} />
      <Route path="/notification-preferences" element={<NotificationPreferencesPage />} />
      <Route path="/my-exports" element={<ExportStatusPage />} />
      <Route path="/profile" element={<ProfilePage />} />
      <Route path="/job-queue" element={<JobQueuePage />} />
      <Route path="/observability" element={<ObservabilityPage />} />
      <Route path="/notification-admin" element={<NotificationAdminPage />} />
      <Route path="/commission-admin" element={<CommissionAdminPage />} />
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  );
}
