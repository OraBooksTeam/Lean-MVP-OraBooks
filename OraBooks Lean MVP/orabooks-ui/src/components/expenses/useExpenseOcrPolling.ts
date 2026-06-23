import { useEffect, useRef } from 'react';
import { api } from '@/pages/frontend/api';

type PollOptions = {
  orgId?: number;
  expenseId?: number;
  enabled?: boolean;
  onUpdate: (expense: Record<string, unknown>) => void;
};

export function useExpenseOcrPolling({ orgId, expenseId, enabled = true, onUpdate }: PollOptions) {
  const attemptsRef = useRef(0);
  const onUpdateRef = useRef(onUpdate);

  useEffect(() => {
    onUpdateRef.current = onUpdate;
  }, [onUpdate]);

  useEffect(() => {
    if (!enabled || !orgId || !expenseId) {
      return undefined;
    }

    attemptsRef.current = 0;

    const poll = async () => {
      const res = await api.expenseGet(orgId, expenseId);
      if (res.error) {
        return null;
      }

      const expense = (res as { data?: { expense?: Record<string, unknown> } }).data?.expense;
      if (expense) {
        onUpdateRef.current(expense);
      }
      return expense ?? null;
    };

    void poll();

    const timer = window.setInterval(async () => {
      attemptsRef.current += 1;
      if (attemptsRef.current > 30) {
        window.clearInterval(timer);
        return;
      }

      const expense = await poll();
      const queueStatus = (expense?.ocr_queue as { status?: string } | undefined)?.status;
      const finished =
        expense?.ocr_confidence != null ||
        queueStatus === 'failed' ||
        queueStatus === 'completed';

      if (finished) {
        window.clearInterval(timer);
      }
    }, 2000);

    return () => window.clearInterval(timer);
  }, [orgId, expenseId, enabled]);
}
