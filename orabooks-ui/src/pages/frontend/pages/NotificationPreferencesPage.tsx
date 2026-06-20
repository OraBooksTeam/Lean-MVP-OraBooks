import { useEffect, useState } from 'react';
import WpLink from '../components/WpLink';

import { api } from '../api';
import ClientShell from '../components/ClientShell';
import NotificationPreferencesForm from '@/components/NotificationPreferencesForm';

export default function NotificationPreferencesPage() {
  const [context, setContext] = useState<any>(null);

  useEffect(() => {
    api.frontendContext().then((res) => {
      if (!res.error) setContext((res as any).data);
    });
  }, []);

  return (
    <ClientShell
      title="Notification Preferences"
      eyebrow="Account settings"
      organization={context?.organization}
      isPartner={context?.organization?.organization_type === 'partner' || context?.user?.is_partner}
    >
      <div className="space-y-4">
        <div className="flex justify-end">
          <WpLink to="/notifications" className="text-sm font-medium text-primary hover:text-primary-dark">
            ← Back to Notification Center
          </Link>
        </div>
        <NotificationPreferencesForm />
      </div>
    </ClientShell>
  );
}
