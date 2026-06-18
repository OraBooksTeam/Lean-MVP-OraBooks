import { useEffect, useState } from 'react';
import AdminPageShell from '@/components/AdminPageShell';
import { api } from '../api';
import { RefreshCw } from 'lucide-react';
import Button from '@/components/Button';

export default function AdminCommissions() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = () => {
    setLoading(true);
    setError('');
    api.partnerDashboard().then((res: any) => {
      if (res.error) setError(res.error);
      else setData(res.data);
      setLoading(false);
    });
  };

  useEffect(() => {
    load();
  }, []);

  return (
    <AdminPageShell
      title="Partner Program"
      description="Commission workspace for partner accounts linked to your user."
      actions={
        <Button onClick={load} variant="secondary" size="sm">
          <RefreshCw className="h-4 w-4" />
          Refresh
        </Button>
      }
    >
      {error ? (
        <div className="glass-panel p-6 text-sm text-danger">{error}</div>
      ) : loading ? (
        <div className="h-48 animate-pulse rounded-2xl border border-border bg-white" />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div className="stat-card">
            <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Partner Code</p>
            <p className="mt-2 font-mono text-lg font-bold text-ink">{data?.partner_code || '—'}</p>
          </div>
          <div className="stat-card">
            <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Total Earned</p>
            <p className="mt-2 text-3xl font-black text-ink">
              ${Number(data?.commission_summary?.total_earned || 0).toFixed(2)}
            </p>
          </div>
          <div className="stat-card">
            <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Pending Payout</p>
            <p className="mt-2 text-3xl font-black text-ink">
              ${Number(data?.commission_summary?.pending_payout || 0).toFixed(2)}
            </p>
          </div>
          <div className="stat-card">
            <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Active Customers</p>
            <p className="mt-2 text-3xl font-black text-ink">{data?.active_customer_count ?? 0}</p>
          </div>
        </div>
      )}
    </AdminPageShell>
  );
}
