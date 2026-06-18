import { useEffect, useState, type ReactNode } from 'react';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { CalendarRange, Lock, RefreshCw, Unlock } from 'lucide-react';

type FiscalPeriod = {
  id: number;
  period_start: string;
  period_end: string;
  status: 'open' | 'soft_closed' | 'hard_closed';
  closed_by?: number | null;
  closed_at?: string | null;
  reopened_by?: number | null;
  reopened_at?: string | null;
  reopen_reason?: string | null;
};

export default function FiscalPeriodsPage() {
  const [context, setContext] = useState<any>(null);
  const [periods, setPeriods] = useState<FiscalPeriod[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [actionId, setActionId] = useState<number | null>(null);
  const [closeModalId, setCloseModalId] = useState<number | null>(null);
  const [closeType, setCloseType] = useState<'soft' | 'hard'>('soft');
  const [closeNote, setCloseNote] = useState('');
  const [reopenModalId, setReopenModalId] = useState<number | null>(null);
  const [reopenReason, setReopenReason] = useState('');

  const orgId = context?.organization?.id;

  const load = async () => {
    setLoading(true);
    setError('');
    setSuccess('');
    const ctx = await api.frontendContext();
    if (ctx.error) {
      setError(ctx.error || 'Unable to load organization context.');
      setLoading(false);
      return;
    }

    const nextContext = (ctx as any).data;
    setContext(nextContext);
    const nextOrgId = nextContext?.organization?.id;
    if (!nextOrgId) {
      setError('Organization is not set up yet.');
      setLoading(false);
      return;
    }

    const res = await api.fiscalPeriodsList(nextOrgId);
    if (res.error) setError(res.error || 'Unable to load fiscal periods.');
    else setPeriods((res as any).data || []);
    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const handleClose = async () => {
    if (!orgId || !closeModalId) return;
    setActionId(closeModalId);
    setError('');
    setSuccess('');
    const res = await api.fiscalPeriodClose(orgId, closeModalId, closeType, closeNote);
    if (res.error) {
      setError(res.error);
    } else {
      setSuccess('Fiscal period closed.');
      setCloseModalId(null);
      setCloseNote('');
      setCloseType('soft');
      await load();
    }
    setActionId(null);
  };

  const handleReopen = async () => {
    if (!orgId || !reopenModalId || !reopenReason.trim()) {
      setError('A reason is required to reopen a period.');
      return;
    }
    setActionId(reopenModalId);
    setError('');
    setSuccess('');
    const res = await api.fiscalPeriodReopen(orgId, reopenModalId, reopenReason.trim());
    if (res.error) {
      setError(res.error);
    } else {
      setSuccess('Fiscal period reopened.');
      setReopenModalId(null);
      setReopenReason('');
      await load();
    }
    setActionId(null);
  };

  return (
    <ClientShell
      title="Fiscal Periods"
      eyebrow="Settings"
      organization={context?.organization}
      isPartner={context?.organization?.organization_type === 'partner'}
    >
      <div className="space-y-5">
        <div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50/80 p-4 text-sm text-amber-900">
          <Lock className="mt-0.5 h-4 w-4 shrink-0" />
          <p>
            Soft close blocks new transactions. Hard close locks the period completely. Reopening a soft-closed period requires a reason and is recorded in the audit log.
          </p>
        </div>

        <div className="flex justify-end">
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {error && <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>}
        {success && <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">{success}</div>}

        <div className="glass-panel overflow-hidden">
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Period</th>
                <th className="px-5 py-3 font-semibold">Start</th>
                <th className="px-5 py-3 font-semibold">End</th>
                <th className="px-5 py-3 font-semibold">Status</th>
                <th className="px-5 py-3 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={5} className="px-5 py-8 text-center text-slate-500">Loading periods...</td></tr>
              ) : periods.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-5 py-10 text-center">
                    <CalendarRange className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No fiscal periods found.</p>
                  </td>
                </tr>
              ) : periods.map((period) => (
                <tr key={period.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3 font-semibold text-ink">{formatPeriodLabel(period)}</td>
                  <td className="px-5 py-3 text-slate-600">{period.period_start}</td>
                  <td className="px-5 py-3 text-slate-600">{period.period_end}</td>
                  <td className="px-5 py-3">
                    <StatusBadge status={period.status} />
                  </td>
                  <td className="px-5 py-3">
                    {period.status === 'open' ? (
                      <Button
                        size="sm"
                        variant="secondary"
                        disabled={actionId === period.id}
                        onClick={() => setCloseModalId(period.id)}
                      >
                        <Lock className="h-3.5 w-3.5" />
                        Close Period
                      </Button>
                    ) : period.status === 'soft_closed' ? (
                      <Button
                        size="sm"
                        variant="secondary"
                        disabled={actionId === period.id}
                        onClick={() => setReopenModalId(period.id)}
                      >
                        <Unlock className="h-3.5 w-3.5" />
                        Reopen
                      </Button>
                    ) : (
                      <span className="text-xs text-slate-400">Locked</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {closeModalId ? (
        <Modal title="Close fiscal period" onClose={() => setCloseModalId(null)}>
          <p className="text-sm text-slate-600">Choose how to close this period. Hard close is irreversible without admin override.</p>
          <div className="mt-4 space-y-2 text-sm">
            <label className="flex items-center gap-2">
              <input type="radio" checked={closeType === 'soft'} onChange={() => setCloseType('soft')} />
              Soft close — block new transactions
            </label>
            <label className="flex items-center gap-2">
              <input type="radio" checked={closeType === 'hard'} onChange={() => setCloseType('hard')} />
              Hard close — completely locked
            </label>
          </div>
          <div className="mt-4">
            <Input
              value={closeNote}
              onChange={(e) => setCloseNote(e.target.value)}
              placeholder="Optional note for audit log"
            />
          </div>
          <div className="mt-5 flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setCloseModalId(null)}>Cancel</Button>
            <Button onClick={handleClose} disabled={actionId === closeModalId}>Confirm close</Button>
          </div>
        </Modal>
      ) : null}

      {reopenModalId ? (
        <Modal title="Reopen fiscal period" onClose={() => setReopenModalId(null)}>
          <p className="text-sm text-slate-600">Why are you reopening this period? This will be recorded in the audit log.</p>
          <div className="mt-4">
            <textarea
              value={reopenReason}
              onChange={(e) => setReopenReason(e.target.value)}
              rows={4}
              className="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
              placeholder="Reason for reopening (required)"
            />
          </div>
          <div className="mt-5 flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setReopenModalId(null)}>Cancel</Button>
            <Button onClick={handleReopen} disabled={actionId === reopenModalId || !reopenReason.trim()}>Confirm reopen</Button>
          </div>
        </Modal>
      ) : null}
    </ClientShell>
  );
}

function Modal({ title, children, onClose }: { title: string; children: React.ReactNode; onClose: () => void }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
      <div className="w-full max-w-md rounded-2xl border border-border bg-white p-6 shadow-xl">
        <div className="flex items-start justify-between gap-3">
          <h3 className="text-lg font-bold text-ink">{title}</h3>
          <button type="button" onClick={onClose} className="text-slate-400 hover:text-slate-600">×</button>
        </div>
        <div className="mt-4">{children}</div>
      </div>
    </div>
  );
}

function StatusBadge({ status }: { status: FiscalPeriod['status'] }) {
  const map = {
    open: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    soft_closed: 'border-amber-200 bg-amber-50 text-amber-700',
    hard_closed: 'border-rose-200 bg-rose-50 text-rose-700',
  };
  const label = {
    open: 'Open',
    soft_closed: 'Soft Closed',
    hard_closed: 'Hard Closed',
  };
  return <span className={`badge border ${map[status]}`}>{label[status]}</span>;
}

function formatPeriodLabel(period: FiscalPeriod) {
  const start = new Date(`${period.period_start}T00:00:00`);
  const end = new Date(`${period.period_end}T00:00:00`);
  const sameMonth = start.getUTCFullYear() === end.getUTCFullYear() && start.getUTCMonth() === end.getUTCMonth();
  if (sameMonth) {
    return start.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
  }
  if (start.getUTCMonth() === 0 && end.getUTCMonth() === 11) {
    return String(start.getUTCFullYear());
  }
  return `${period.period_start} – ${period.period_end}`;
}
