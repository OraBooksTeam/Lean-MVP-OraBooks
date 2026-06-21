import AdminPageShell from '@/components/AdminPageShell';
import JobQueuePanel from '@/components/platform/JobQueuePanel';

export default function AdminJobQueue() {
  return (
    <AdminPageShell
      title="Async Job Queue"
      description="Monitor background jobs, failures, and replay dead-letter tasks."
    >
      <JobQueuePanel />
    </AdminPageShell>
  );
}
