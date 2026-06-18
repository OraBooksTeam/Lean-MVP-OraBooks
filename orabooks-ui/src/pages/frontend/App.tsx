import { Routes, Route, Navigate } from 'react-router-dom';
import type { ReactNode } from 'react';
import RequireAuth from './components/RequireAuth';
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
  const protectedPage = (page: ReactNode) => <RequireAuth>{page}</RequireAuth>;

  return (
    <Routes>
      <Route path="/" element={<LoginPage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/reset-password" element={<ResetPasswordPage />} />
      <Route path="/verify-email" element={<VerifyEmailPage />} />
      <Route path="/tier-selection" element={protectedPage(<TierSelectionPage />)} />
      <Route path="/dashboard" element={protectedPage(<DashboardPage />)} />
      <Route path="/customers" element={protectedPage(<CustomersPage />)} />
      <Route path="/vendors" element={protectedPage(<VendorsPage />)} />
      <Route path="/inventory" element={protectedPage(<InventoryPage />)} />
      <Route path="/bank-reconciliation" element={protectedPage(<BankReconciliationPage />)} />
      <Route path="/reports" element={protectedPage(<ReportsPage />)} />
      <Route path="/csv-imports" element={protectedPage(<CsvImportsPage />)} />
      <Route path="/team" element={protectedPage(<TeamPage />)} />
      <Route path="/attachments" element={protectedPage(<AttachmentsPage />)} />
      <Route path="/invoices" element={protectedPage(<InvoicesPage />)} />
      <Route path="/chart-of-accounts" element={protectedPage(<ChartOfAccountsPage />)} />
      <Route path="/fiscal-periods" element={protectedPage(<FiscalPeriodsPage />)} />
      <Route path="/tax-settings" element={protectedPage(<TaxSettingsPage />)} />
      <Route path="/journals" element={protectedPage(<JournalsPage />)} />
      <Route path="/approvals" element={protectedPage(<ApprovalsPage />)} />
      <Route path="/ai-review" element={protectedPage(<AiReviewPage />)} />
      <Route path="/expenses" element={protectedPage(<ExpensesPage />)} />
      <Route path="/voice" element={protectedPage(<VoicePage />)} />
      <Route path="/partner-onboarding" element={protectedPage(<PartnerOnboardingPage />)} />
      <Route path="/partner-program" element={protectedPage(<PartnerProgramPage />)} />
      <Route path="/commissions" element={protectedPage(<CommissionsPage />)} />
      <Route path="/notifications" element={protectedPage(<NotificationsPage />)} />
      <Route path="/notification-preferences" element={protectedPage(<NotificationPreferencesPage />)} />
      <Route path="/my-exports" element={protectedPage(<ExportStatusPage />)} />
      <Route path="/profile" element={protectedPage(<ProfilePage />)} />
      <Route path="/job-queue" element={protectedPage(<JobQueuePage />)} />
      <Route path="/observability" element={protectedPage(<ObservabilityPage />)} />
      <Route path="/notification-admin" element={protectedPage(<NotificationAdminPage />)} />
      <Route path="/commission-admin" element={protectedPage(<CommissionAdminPage />)} />
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  );
}
