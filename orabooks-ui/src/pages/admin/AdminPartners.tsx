import { useEffect, useState } from 'react';
import { api } from '../api';
import Button from '@/components/Button';
import { UserCheck, UserX, RefreshCw } from 'lucide-react';

interface Partner {
  id: number;
  partner_code: string;
  email: string;
  partner_type: string;
  org_name?: string;
  org_status?: string;
  created_at: string;
}

export default function AdminPartners() {
  const [pending, setPending] = useState<Partner[]>([]);
  const [active, setActive] = useState<Partner[]>([]);
  const [loading, setLoading] = useState(true);
  const [rejectId, setRejectId] = useState<number | null>(null);
  const [rejectReason, setRejectReason] = useState('');

  const load = () => {
    setLoading(true);
    Promise.all([api.pendingPartners(), api.activePartners()]).then(([pr, ar]) => {
      if (!pr.error) setPending((pr as any).data || []);
      if (!ar.error) setActive((ar as any).data || []);
      setLoading(false);
    });
  };

  useEffect(() => { load(); }, []);

  const approve = (id: number) => {
    if (!confirm('Approve partner and activate org?')) return;
    api.approvePartner(id).then(() => load());
  };

  const reject = (id: number) => {
    if (!rejectReason) return alert('Please enter a reason');
    api.rejectPartner(id, rejectReason).then(() => {
      setRejectId(null);
      setRejectReason('');
      load();
    });
  };

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-ink">Partner Management</h1>
      <div className="grid gap-6 lg:grid-cols-2">
        <div className="glass-panel p-6">
          <div className="mb-4 flex items-center justify-between">
            <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">Pending Approvals</h2>
            <span className="badge bg-amber-50 text-amber-700 border-amber-200">{pending.length}</span>
          </div>
          <div className="space-y-3">
            {loading ? <p className="text-sm text-slate-500">Loading…</p> : pending.length === 0 ? <p className="text-sm text-slate-500">No pending partners.</p> : pending.map((p) => (
              <div key={p.id} className="flex items-center justify-between rounded-lg border border-border bg-white p-3">
                <div>
                  <p className="text-sm font-semibold text-ink">{p.partner_code}</p>
                  <p className="text-xs text-slate-500">{p.email} · {p.org_name || '—'}</p>
                </div>
                <div className="flex gap-2">
                  <Button size="sm" onClick={() => approve(p.id)}>Approve</Button>
                  <Button size="sm" variant="danger" onClick={() => setRejectId(p.id)}>Reject</Button>
                </div>
              </div>
            ))}
          </div>
          {rejectId && (
            <div className="mt-4 rounded-lg border border-border bg-slate-50 p-4">
              <label className="mb-1.5 block text-sm font-medium text-slate-700">Rejection reason</label>
              <textarea className="mb-2 w-full rounded-lg border border-border bg-white px-3 py-2 text-sm" rows={3} value={rejectReason} onChange={(e) => setRejectReason(e.target.value)} />
              <div className="flex gap-2">
                <Button size="sm" onClick={() => reject(rejectId)}>Confirm Reject</Button>
                <Button size="sm" variant="ghost" onClick={() => setRejectId(null)}>Cancel</Button>
              </div>
            </div>
          )}
        </div>

        <div className="glass-panel p-6">
          <div className="mb-4 flex items-center justify-between">
            <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">Active Partners</h2>
            <Button size="sm" variant="secondary" onClick={load}><RefreshCw className="h-3.5 w-3.5" /></Button>
          </div>
          <div className="space-y-3">
            {loading ? <p className="text-sm text-slate-500">Loading…</p> : active.length === 0 ? <p className="text-sm text-slate-500">No active partners.</p> : active.map((p) => (
              <div key={p.id} className="flex items-center justify-between rounded-lg border border-border bg-white p-3">
                <div>
                  <p className="text-sm font-semibold text-ink">{p.partner_code}</p>
                  <p className="text-xs text-slate-500">{p.email} · {p.org_name || '—'}</p>
                </div>
                <span className="badge bg-emerald-50 text-emerald-700 border-emerald-200">Active</span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
