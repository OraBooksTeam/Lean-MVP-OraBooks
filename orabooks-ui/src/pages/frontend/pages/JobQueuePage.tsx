import { useEffect, useState } from 'react';
import ClientShell from '../components/ClientShell';
import JobQueuePanel from '@/components/platform/JobQueuePanel';
import { api } from '../api';

export default function JobQueuePage() {
  const [context, setContext] = useState<any>(null);

  useEffect(() => {
    api.frontendContext().then((res: any) => {
      if (!res.error) setContext(res.data);
    });
  }, []);

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
