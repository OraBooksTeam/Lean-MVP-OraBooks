import { useEffect, useState } from 'react';
import { api } from '../api';
import Button from '@/components/Button';
import { UserCheck, Mail, Shield } from 'lucide-react';

interface User {
  id: number;
  email: string;
  is_partner: number;
  is_email_verified: number;
  is_2fa_enabled: number;
  org_id: number;
  created_at: string;
}

export default function AdminUsers() {
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);

  const load = () => {
    setLoading(true);
    api.listUsers().then((res) => {
      if (!res.error) setUsers((res as any).data || []);
      setLoading(false);
    });
  };

  useEffect(() => { load(); }, []);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-ink">Users & Teams</h1>
        <Button variant="secondary" onClick={load}>Refresh</Button>
      </div>
      <div className="glass-panel overflow-hidden">
        <table className="min-w-full text-left text-sm">
          <thead>
            <tr className="border-b border-border bg-slate-50/60 text-xs uppercase text-slate-500">
              <th className="px-5 py-3 font-semibold">ID</th>
              <th className="px-5 py-3 font-semibold">Email</th>
              <th className="px-5 py-3 font-semibold">Type</th>
              <th className="px-5 py-3 font-semibold">Verified</th>
              <th className="px-5 py-3 font-semibold">2FA</th>
              <th className="px-5 py-3 font-semibold">Org ID</th>
              <th className="px-5 py-3 font-semibold">Created</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {loading ? (
              <tr><td colSpan={7} className="px-5 py-6 text-center text-slate-500">Loading…</td></tr>
            ) : users.length === 0 ? (
              <tr><td colSpan={7} className="px-5 py-6 text-center text-slate-500">No users found.</td></tr>
            ) : users.map((u) => (
              <tr key={u.id} className="transition hover:bg-slate-50/60">
                <td className="px-5 py-3 font-mono text-slate-600">{u.id}</td>
                <td className="px-5 py-3 font-medium text-ink">{u.email}</td>
                <td className="px-5 py-3 capitalize text-slate-600">{u.is_partner ? 'Partner' : 'Customer'}</td>
                <td className="px-5 py-3"><span className={`badge border ${u.is_email_verified ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'}`}>{u.is_email_verified ? 'Yes' : 'No'}</span></td>
                <td className="px-5 py-3"><span className={`badge border ${u.is_2fa_enabled ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'}`}>{u.is_2fa_enabled ? 'Enabled' : 'Disabled'}</span></td>
                <td className="px-5 py-3 font-mono text-xs text-slate-600">{u.org_id ?? '—'}</td>
                <td className="px-5 py-3 text-slate-600">{u.created_at}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
