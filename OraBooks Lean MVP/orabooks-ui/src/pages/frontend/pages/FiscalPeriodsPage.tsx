import { useEffect, useState, type ReactNode } from 'react';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { CalendarRange, Lock, Pencil, Plus, RefreshCw, Unlock } from 'lucide-react';

type FiscalPeriod = {
  id: number;
  period_start: string;
  period_end: string;
  status: 'open' | 'soft_closed' | 'hard_closed';
  status_label?: string;
  closed_by?: number | null;
  closed_at?: string | null;
  reopened_by?: number | null;
  reopened_at?: string | null;
  reopen_reason?: string | null;
  pending_total?: number;
  can_close?: boolean;
  can_edit?: boolean;
  can_reopen?: boolean;
  can_override_reopen?: boolean;
};

const ORABOOKS_AJAX = (window as any).orabooks_ajax || {};

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
  const [hardConfirm, setHardConfirm] = useState(false);
  const [closeWarnings, setCloseWarnings] = useState<string[]>([]);
  const [reopenModalId, setReopenModalId] = useState<number | null>(null);
  const [reopenReason, setReopenReason] = useState('');
  const [overrideModalId, setOverrideModalId] = useState<number | null>(null);
  const [overrideReason, setOverrideReason] = useState('');
  const [addModalOpen, setAddModalOpen] = useState(false);
  const [editModalId, setEditModalId] = useState<number | null>(null);
  const [periodStart, setPeriodStart] = useState('');
  const [periodEnd, setPeriodEnd] = useState('');
  const [selectedPeriodId, setSelectedPeriodId] = useState<number | null>(null);

  const orgId = context?.organization?.id;
  const isPlatformAdmin = Boolean(ORABOOKS_AJAX.is_admin);
  const closeModalPeriod = periods.find((period) => period.id === closeModalId);
  const editModalPeriod = periods.find((period) => period.id === editModalId);
  const canManagePeriods = !loading && !error && Boolean(orgId);

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
    else {
      const nextPeriods = (res as any).data || [];
      setPeriods(nextPeriods);
      if (selectedPeriodId && nextPeriods.some((period: FiscalPeriod) => period.id === selectedPeriodId)) {
        // keep selection
      } else if (selectedPeriodId) {
        setSelectedPeriodId(null);
      }
    }
    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const resetCloseModal = () => {
    setCloseModalId(null);
    setCloseNote('');
    setCloseType('soft');
    setHardConfirm(false);
    setCloseWarnings([]);
  };

  const handleClose = async () => {
    if (!orgId || !closeModalId) return;
    if (closeType === 'hard' && !hardConfirm) {
      setError('Hard close requires explicit confirmation.');
      return;
    }

    setActionId(closeModalId);
    setError('');
    setSuccess('');
    const res = await api.fiscalPeriodClose(orgId, closeModalId, closeType, closeNote, closeType === 'hard');
    if (res.error) {
      setError(res.error);
    } else {
      const warnings = (res as any).data?.warnings || [];
      setSuccess(
        warnings.length > 0
          ? `Fiscal period closed. Warning: ${warnings.join(' ')}`
          : 'Fiscal period closed.'
      );
      resetCloseModal();
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

  const handleOverrideReopen = async () => {
    if (!orgId || !overrideModalId || !overrideReason.trim()) {
      setError('A mandatory justification is required for admin override reopen.');
      return;
    }
    setActionId(overrideModalId);
    setError('');
    setSuccess('');
    const res = await api.fiscalPeriodOverrideReopen(orgId, overrideModalId, overrideReason.trim());
    if (res.error) {
      setError(res.error);
    } else {
      setSuccess('Hard-closed fiscal period override-reopened. Audit log recorded.');
      setOverrideModalId(null);
      setOverrideReason('');
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
          <p title="Soft close: no new transactions. Hard close: completely locked. Reopen requires approval.">
            Soft close blocks new transactions. Hard close locks the period completely and blocks reversals.
            Reopening a soft-closed period requires a reason and is recorded in the audit log.
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
                <th className="px-5 py-3 font-semibold">Closed By</th>
                <th className="px-5 py-3 font-semibold">Closed At</th>
                <th className="px-5 py-3 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={7} className="px-5 py-8 text-center text-slate-500">Loading periods...</td></tr>
              ) : periods.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-5 py-10 text-center">
                    <CalendarRange className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No fiscal periods found.</p>
                  </td>
                </tr>
              ) : periods.map((period) => (
                <tr key={period.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3 font-semibold text-ink">
                    <div>{formatPeriodLabel(period)}</div>
                    {(period.pending_total ?? 0) > 0 && period.status === 'open' && (
                      <p className="mt-1 text-xs text-amber-700">
                        {period.pending_total} unposted journal(s) in period
                      </p>
                    )}
                  </td>
                  <td className="px-5 py-3 text-slate-600">{period.period_start}</td>
                  <td className="px-5 py-3 text-slate-600">{period.period_end}</td>
                  <td className="px-5 py-3">
                    <StatusBadge status={period.status} label={period.status_label} />
                  </td>
                  <td className="px-5 py-3 text-slate-600">{period.closed_by ? `#${period.closed_by}` : '—'}</td>
                  <td className="px-5 py-3 text-slate-600">{period.closed_at || '—'}</td>
                  <td className="px-5 py-3">
                    {period.status === 'open' || period.can_close ? (
                      <Button
                        size="sm"
                        variant="secondary"
                        disabled={actionId === period.id}
                        onClick={() => {
                          setCloseWarnings([]);
                          setCloseModalId(period.id);
                        }}
                        title="Soft or hard close. Transactions will be blocked."
                      >
                        <Lock className="h-3.5 w-3.5" />
                        Close Period
                      </Button>
                    ) : period.status === 'soft_closed' || period.can_reopen ? (
                      <Button
                        size="sm"
                        variant="secondary"
                        disabled={actionId === period.id}
                        onClick={() => setReopenModalId(period.id)}
                        title="Reopen period. Reason required."
                      >
                        <Unlock className="h-3.5 w-3.5" />
                        Reopen
                      </Button>
                    ) : isPlatformAdmin && (period.can_override_reopen || period.status === 'hard_closed') ? (
                      <Button
                        size="sm"
                        variant="secondary"
                        disabled={actionId === period.id}
                        onClick={() => setOverrideModalId(period.id)}
                        title="Platform admin override reopen with audit trail."
                      >
                        <Unlock className="h-3.5 w-3.5" />
                        Admin Reopen
                      </Button>
                    ) : (
                      <span className="text-xs text-slate-400" title="Hard-closed periods cannot be reopened without admin override.">
                        Locked
                      </span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {closeModalId ? (
        <Modal title="Close fiscal period" onClose={resetCloseModal}>
          <p className="text-sm text-slate-600" title="Soft close: no new transactions. Hard close: completely locked. Reopen requires approval.">
            Choose how to close this period. Hard close is irreversible without admin override.
          </p>
          {(closeModalPeriod?.pending_total ?? 0) > 0 && (
            <div className="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
              Warning: {closeModalPeriod?.pending_total} unposted journal(s) remain in this period.
            </div>
          )}
          <div className="mt-4 space-y-2 text-sm">
            <label className="flex items-center gap-2" title="Block new transactions; reversals still allowed.">
              <input type="radio" checked={closeType === 'soft'} onChange={() => { setCloseType('soft'); setHardConfirm(false); }} />
              Soft close — block new transactions
            </label>
            <label className="flex items-center gap-2" title="Hard close irreversible without admin override.">
              <input type="radio" checked={closeType === 'hard'} onChange={() => setCloseType('hard')} />
              Hard close — completely locked
            </label>
          </div>
          {closeType === 'hard' && (
            <label className="mt-4 flex items-start gap-2 text-sm text-slate-700">
              <input
                type="checkbox"
                checked={hardConfirm}
                onChange={(e) => setHardConfirm(e.target.checked)}
              />
              I understand hard close is irreversible without platform admin override.
            </label>
          )}
          <div className="mt-4">
            <Input
              value={closeNote}
              onChange={(e) => setCloseNote(e.target.value)}
              placeholder="Optional note for audit log"
            />
          </div>
          {closeWarnings.length > 0 && (
            <div className="mt-3 text-sm text-amber-700">{closeWarnings.join(' ')}</div>
          )}
          <div className="mt-5 flex justify-end gap-2">
            <Button variant="secondary" onClick={resetCloseModal}>Cancel</Button>
            <Button onClick={handleClose} disabled={actionId === closeModalId || (closeType === 'hard' && !hardConfirm)}>
              Confirm close
            </Button>
          </div>
        </Modal>
      ) : null}

      {reopenModalId ? (
        <Modal title="Reopen fiscal period" onClose={() => setReopenModalId(null)}>
          <p className="text-sm text-slate-600" title="Why reopening? (audit trail)">
            Why are you reopening this period? This will be recorded in the audit log.
          </p>
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

      {overrideModalId ? (
        <Modal title="Admin override reopen" onClose={() => setOverrideModalId(null)}>
          <p className="text-sm text-slate-600">
            Override reopen a hard-closed period. Mandatory justification is written to the audit log.
          </p>
          <div className="mt-4">
            <textarea
              value={overrideReason}
              onChange={(e) => setOverrideReason(e.target.value)}
              rows={4}
              className="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
              placeholder="Mandatory justification (required)"
            />
          </div>
          <div className="mt-5 flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setOverrideModalId(null)}>Cancel</Button>
            <Button onClick={handleOverrideReopen} disabled={actionId === overrideModalId || !overrideReason.trim()}>
              Confirm override reopen
            </Button>
          </div>
        </Modal>
      ) : null}
    </ClientShell>
  );
}

function Modal({ title, children, onClose }: { title: string; children: ReactNode; onClose: () => void }) {
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

function StatusBadge({ status, label }: { status: FiscalPeriod['status']; label?: string }) {
  const map = {
    open: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    soft_closed: 'border-amber-200 bg-amber-50 text-amber-700',
    hard_closed: 'border-rose-200 bg-rose-50 text-rose-700',
  };
  const defaultLabel = {
    open: 'Open',
    soft_closed: 'Soft Closed',
    hard_closed: 'Hard Closed',
  };
  return <span className={`badge border ${map[status]}`}>{label || defaultLabel[status]}</span>;
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
