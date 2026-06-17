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

export default function FrontendRoutes() {
  return (
    <Routes>
      <Route path="/" element={<LoginPage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/tier-selection" element={<TierSelectionPage />} />
      <Route path="/dashboard" element={<DashboardPage />} />
      <Route path="/invoices" element={<InvoicesPage />} />
      <Route path="/chart-of-accounts" element={<ChartOfAccountsPage />} />
      <Route path="/journals" element={<JournalsPage />} />
      <Route path="/partner-onboarding" element={<PartnerOnboardingPage />} />
      <Route path="/notifications" element={<NotificationsPage />} />
      <Route path="/my-exports" element={<ExportStatusPage />} />
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  );
}
