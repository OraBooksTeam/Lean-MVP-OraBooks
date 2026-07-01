import { useCallback, useEffect, useState } from 'react';
import AdminPageShell from '@/components/AdminPageShell';
import Button from '@/components/Button';
import { api } from '@/pages/admin/api';

type EventBusHealth = {
  status?: string;
  pending?: number;
  sent?: number;
  dead_letter?: number;
};

type DeadLetterItem = {
  id: number;
  event_type?: string;
  aggregate_id?: number;
  retry_count?: number;
  error_message?: string;
  created_at?: string;
};

export default function AdminEventDeadLetter() {
  const [health, setHealth] = useState<EventBusHealth>({});
  const [items, setItems] = useState<DeadLetterItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    const res = await api.eventBusDeadLetters();
    if ((res as any).error) {
      setError((res as any).error || 'Unable to load dead-letter events.');
      setLoading(false);
      return;
    }

    const data = (res as any).data || {};
    setHealth((data.health || {}) as EventBusHealth);
    setItems(((data.dead_letters || []) as DeadLetterItem[]).map((item) => ({
      ...item,
      id: Number(item.id),
    })));
    setLoading(false);
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const runAction = async (action: 'poll' | 'replay-all' | 'replay' | 'discard', deadLetterId?: number) => {
    setBusy(true);
    setError('');

    let res: any;
    if (action === 'poll') {
      res = await api.eventBusPollNow();
    } else if (action === 'replay-all') {
      res = await api.eventBusReplayAll();
    } else if (action === 'replay' && deadLetterId) {
      res = await api.eventBusReplay(deadLetterId);
    } else if (action === 'discard' && deadLetterId) {
      res = await api.eventBusDiscard(deadLetterId);
    }

    if (res?.error) {
      setError(res.error || 'Action failed.');
      setBusy(false);
      return;
    }

    await load();
    setBusy(false);
  };

  return (
    <AdminPageShell
      title="Event Dead Letters"
      description="Replay or discard failed event-bus messages with full OraBooks admin styling."
    >
      <section className="glass-panel p-5">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
          <div className="space-y-1">
            <p className="text-sm font-semibold text-ink">Event Bus Health</p>
            <p className="text-xs text-slate-500">Status: {(health.status || 'healthy').toUpperCase()}</p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button size="sm" variant="secondary" onClick={() => void runAction('poll')} loading={busy}>
              Poll now
            </Button>
            <Button
              size="sm"
              variant="secondary"
              onClick={() => void runAction('replay-all')}
              loading={busy}
              disabled={items.length === 0}
            >
              Replay all
            </Button>
          </div>
        </div>

        <div className="mt-4 grid gap-3 sm:grid-cols-3">
          <div className="rounded-xl border border-border bg-slate-50 p-3 text-sm">
            <p className="text-xs text-slate-500">Pending</p>
            <p className="text-lg font-bold text-ink">{health.pending ?? 0}</p>
          </div>
          <div className="rounded-xl border border-border bg-slate-50 p-3 text-sm">
            <p className="text-xs text-slate-500">Sent</p>
            <p className="text-lg font-bold text-ink">{health.sent ?? 0}</p>
          </div>
          <div className="rounded-xl border border-border bg-slate-50 p-3 text-sm">
            <p className="text-xs text-slate-500">Dead letters</p>
            <p className="text-lg font-bold text-ink">{health.dead_letter ?? 0}</p>
          </div>
        </div>

        {error && <p className="mt-4 text-sm text-danger">{error}</p>}

        {loading ? (
          <div className="mt-4 rounded-xl border border-border bg-white p-4 text-sm text-slate-500">Loading dead-letter events...</div>
        ) : items.length === 0 ? (
          <div className="mt-4 rounded-xl border border-dashed border-border bg-white p-4 text-sm text-slate-500">
            No open dead-letter events.
          </div>
        ) : (
          <div className="mt-4 space-y-3">
            {items.map((dead) => (
              <div key={dead.id} className="rounded-xl border border-border bg-white p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p className="text-sm font-semibold text-ink">{dead.event_type || 'Unknown event'}</p>
                    <p className="text-xs text-slate-500">
                      Aggregate #{dead.aggregate_id ?? 0} · Retries {dead.retry_count ?? 0}
                    </p>
                    {dead.created_at && <p className="mt-1 text-xs text-slate-500">Created: {dead.created_at}</p>}
                  </div>
                  <div className="flex gap-2">
                    <Button size="sm" variant="secondary" onClick={() => void runAction('replay', dead.id)} loading={busy}>
                      Replay
                    </Button>
                    <Button size="sm" variant="secondary" onClick={() => void runAction('discard', dead.id)} loading={busy}>
                      Discard
                    </Button>
                  </div>
                </div>
                {dead.error_message && <p className="mt-2 text-xs text-slate-500">{String(dead.error_message).slice(0, 220)}</p>}
              </div>
            ))}
          </div>
        )}
      </section>
    </AdminPageShell>
  );
}
