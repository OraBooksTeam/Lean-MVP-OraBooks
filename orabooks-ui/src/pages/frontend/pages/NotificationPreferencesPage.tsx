import { useEffect, useState } from 'react';
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
      <NotificationPreferencesForm />
    </ClientShell>
  );
}
