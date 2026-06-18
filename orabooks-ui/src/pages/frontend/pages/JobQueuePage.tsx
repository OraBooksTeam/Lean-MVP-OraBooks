import { useEffect, useState } from 'react';
import ClientShell from '../components/ClientShell';
import JobQueuePanel from '@/components/platform/JobQueuePanel';
import { api } from '../api';

const ORABOOKS_AJAX = (window as any).orabooks_ajax || {};

export default function JobQueuePage() {
  const [context, setContext] = useState<any>(null);

  useEffect(() => {
    if (!ORABOOKS_AJAX.is_admin) return;
    api.frontendContext().then((res: any) => {
      if (!res.error) setContext(res.data);
    });
  }, []);

  if (!ORABOOKS_AJAX.is_admin) {
    return (
      <ClientShell title="Access denied" eyebrow="Platform">
        <p className="text-sm text-danger">You do not have permission to view the job queue.</p>
      </ClientShell>
    );
  }

  return (
    <ClientShell
      title="Async Job Queue"
      eyebrow="Platform"
      organization={context?.organization}
      isPartner={context?.is_partner}
    >
      <p className="mb-4 text-sm text-slate-600">
        Monitor background jobs, failures, and replay dead-letter tasks.
      </p>
      <JobQueuePanel />
    </ClientShell>
  );
}
