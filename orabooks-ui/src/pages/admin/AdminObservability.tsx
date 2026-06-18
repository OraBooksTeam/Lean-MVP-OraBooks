import AdminPageShell from '@/components/AdminPageShell';
import ObservabilityPanel from '@/components/platform/ObservabilityPanel';

export default function AdminObservability() {
  return (
    <AdminPageShell
      title="Platform Observability"
      description="Queue depth, subsystem health, and failure signals across the platform."
    >
      <ObservabilityPanel />
    </AdminPageShell>
  );
}
