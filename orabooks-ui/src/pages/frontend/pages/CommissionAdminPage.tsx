import ClientShell from '../components/ClientShell';
import CommissionConfigPanel from '@/components/platform/CommissionConfigPanel';

const ORABOOKS_AJAX = (window as any).orabooks_ajax || {};

export default function CommissionAdminPage() {
  if (!ORABOOKS_AJAX.is_admin) {
    return (
      <ClientShell title="Access denied" eyebrow="Platform">
        <p className="text-sm text-danger">Super-admin access is required for commission configuration.</p>
      </ClientShell>
    );
  }

  return (
    <ClientShell title="Commission Platform Configuration" eyebrow="Platform">
      <p className="mb-4 text-sm text-slate-600">
        Global partner commission rules, payout thresholds, and fee settings.
      </p>
      <CommissionConfigPanel />
    </ClientShell>
  );
}
