import AdminPageShell from '@/components/AdminPageShell';
import ObservabilityPanel from '@/components/platform/ObservabilityPanel';

export default function AdminObservability() {
  return (
    <AdminPageShell
      title="Platform Observability"
      description="SLO compliance, error budgets, queue depth, and subsystem health."
    >
      <ObservabilityPanel />
    </AdminPageShell>
  );
}
