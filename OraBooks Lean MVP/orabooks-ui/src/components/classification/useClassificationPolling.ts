import { useEffect, useRef } from 'react';
import { api } from '@/pages/frontend/api';

type PollOptions = {
  recordType: string;
  recordId: number;
  enabled?: boolean;
  onUpdate?: (classification: any) => void;
};

export function useClassificationPolling({ recordType, recordId, enabled = true, onUpdate }: PollOptions) {
  const attemptsRef = useRef(0);

  useEffect(() => {
    if (!enabled || !recordId) return undefined;

    attemptsRef.current = 0;
    const timer = window.setInterval(async () => {
      attemptsRef.current += 1;
      if (attemptsRef.current > 15) {
        window.clearInterval(timer);
        return;
      }

      const res = await api.classificationStatus(recordType, recordId);
      const status = (res as any).data?.classification?.status;
      if (status === 'processed' || status === 'failed' || status === 'overridden') {
        onUpdate?.((res as any).data?.classification);
        window.clearInterval(timer);
      }
    }, 2000);

    return () => window.clearInterval(timer);
  }, [recordType, recordId, enabled, onUpdate]);
}
