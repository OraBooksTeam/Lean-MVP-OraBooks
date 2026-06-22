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
import ApprovalSettingsPage from './pages/ApprovalSettingsPage';
import AiReviewPage from './pages/AiReviewPage';
import ExpensesPage from './pages/ExpensesPage';
import VoicePage from './pages/VoicePage';
import FiscalPeriodsPage from './pages/FiscalPeriodsPage';
import TaxSettingsPage from './pages/TaxSettingsPage';
import ProfilePage from './pages/ProfilePage';
import Security2faPage from './pages/Security2faPage';
import NotificationPreferencesPage from './pages/NotificationPreferencesPage';
import ResetPasswordPage from './pages/ResetPasswordPage';
import VerifyEmailPage from './pages/VerifyEmailPage';
import AcceptInvitePage from './pages/AcceptInvitePage';
import JobQueuePage from './pages/JobQueuePage';
import WebhookSettingsPage from './pages/WebhookSettingsPage';
import ObservabilityPage from './pages/ObservabilityPage';
import NotificationAdminPage from './pages/NotificationAdminPage';
import CommissionAdminPage from './pages/CommissionAdminPage';
import ClassificationLiveTestPage from './pages/ClassificationLiveTestPage';
import { normalizeAppRoute, toWpUrl } from './lib/wp-routing';

type RouteConfig = {
  element: ReactNode;
  requireAuth?: boolean;
};

const ROUTES: Record<string, RouteConfig> = {
  '/': { element: <LoginPage />, requireAuth: false },
  '/login': { element: <LoginPage />, requireAuth: false },
  '/register': { element: <RegisterPage />, requireAuth: false },
  '/reset-password': { element: <ResetPasswordPage />, requireAuth: false },
  '/verify-email': { element: <VerifyEmailPage />, requireAuth: false },
  '/accept-invite': { element: <AcceptInvitePage />, requireAuth: false },
  '/tier-selection': { element: <TierSelectionPage />, requireAuth: false },
  '/dashboard': { element: <DashboardPage /> },
  '/customers': { element: <CustomersPage /> },
  '/vendors': { element: <VendorsPage /> },
  '/inventory': { element: <InventoryPage /> },
  '/bank-reconciliation': { element: <BankReconciliationPage /> },
  '/reports': { element: <ReportsPage /> },
  '/csv-imports': { element: <CsvImportsPage /> },
  '/team': { element: <TeamPage /> },
  '/attachments': { element: <AttachmentsPage /> },
  '/invoices': { element: <InvoicesPage /> },
  '/chart-of-accounts': { element: <ChartOfAccountsPage /> },
  '/fiscal-periods': { element: <FiscalPeriodsPage /> },
  '/tax-settings': { element: <TaxSettingsPage /> },
  '/journals': { element: <JournalsPage /> },
  '/approvals': { element: <ApprovalsPage /> },
  '/approval-settings': { element: <ApprovalSettingsPage /> },
  '/ai-review': { element: <AiReviewPage /> },
  '/classification-test': { element: <ClassificationLiveTestPage /> },
  '/expenses': { element: <ExpensesPage /> },
  '/voice': { element: <VoicePage /> },
  '/onboarding': { element: <PartnerOnboardingPage /> },
  '/partner-onboarding': { element: <PartnerOnboardingPage /> },
  '/partner/onboarding': { element: <PartnerOnboardingPage /> },
  '/partner-program': { element: <PartnerProgramPage /> },
  '/commissions': { element: <CommissionsPage /> },
  '/notifications': { element: <NotificationsPage /> },
  '/notification-preferences': { element: <NotificationPreferencesPage /> },
  '/my-exports': { element: <ExportStatusPage /> },
  '/profile': { element: <ProfilePage /> },
  '/security/2fa': { element: <Security2faPage /> },
  '/security-2fa': { element: <Security2faPage /> },
  '/audit-log': { element: <AuditLogPage /> },
  '/job-queue': { element: <JobQueuePage /> },
  '/webhook-settings': { element: <WebhookSettingsPage /> },
  '/observability': { element: <ObservabilityPage /> },
  '/notification-admin': { element: <NotificationAdminPage /> },
  '/commission-admin': { element: <CommissionAdminPage /> },
};

export function renderFrontendPage(route: string) {
  const key = normalizeAppRoute(route);
  const config = ROUTES[key] ?? ROUTES['/dashboard'];
  if (config.requireAuth === false) {
    return config.element;
  }
  return <RequireAuth>{config.element}</RequireAuth>;
}

export function redirectToDefaultAppPage() {
  window.location.replace(toWpUrl('/dashboard'));
}

export default function FrontendRoutes() {
  const route =
    document.getElementById('orabooks-app-root')?.dataset.initialRoute || '/dashboard';
  return renderFrontendPage(route);
}
