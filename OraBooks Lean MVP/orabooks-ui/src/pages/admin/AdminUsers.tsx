import { useEffect, useState } from 'react';
import AdminPageShell from '@/components/AdminPageShell';
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
  const [recoveringId, setRecoveringId] = useState<number | null>(null);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const load = () => {
    setLoading(true);
    api.listUsers().then((res) => {
      if (!res.error) setUsers((res as any).data || []);
      setLoading(false);
    });
  };

  useEffect(() => { load(); }, []);

  const recover2fa = async (user: User) => {
    const justification = window.prompt(
      `Reset 2FA for ${user.email}? Enter a recovery justification (audit log will record this):`
    );
    if (!justification?.trim()) return;

    setRecoveringId(user.id);
    setError('');
    setMessage('');
    const res = await api.adminRecover2fa(user.id, justification.trim());
    if (res.error) {
      setError(typeof res.error === 'string' ? res.error : '2FA recovery failed.');
    } else {
      setMessage(`2FA reset for ${user.email}. User must set up 2FA again.`);
      load();
    }
    setRecoveringId(null);
  };

  return (
    <AdminPageShell title="Users & Teams" description="Platform user accounts, verification, and security posture." actions={<Button variant="secondary" onClick={load}>Refresh</Button>}>
      {message && <p className="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{message}</p>}
      {error && <p className="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}
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
              <th className="px-5 py-3 font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {loading ? (
              <tr><td colSpan={8} className="px-5 py-6 text-center text-slate-500">Loading…</td></tr>
            ) : users.length === 0 ? (
              <tr><td colSpan={8} className="px-5 py-6 text-center text-slate-500">No users found.</td></tr>
            ) : users.map((u) => (
              <tr key={u.id} className="transition hover:bg-slate-50/60">
                <td className="px-5 py-3 font-mono text-slate-600">{u.id}</td>
                <td className="px-5 py-3 font-medium text-ink">{u.email}</td>
                <td className="px-5 py-3 capitalize text-slate-600">{u.is_partner ? 'Partner' : 'Customer'}</td>
                <td className="px-5 py-3"><span className={`badge border ${u.is_email_verified ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'}`}>{u.is_email_verified ? 'Yes' : 'No'}</span></td>
                <td className="px-5 py-3"><span className={`badge border ${u.is_2fa_enabled ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'}`}>{u.is_2fa_enabled ? 'Enabled' : 'Disabled'}</span></td>
                <td className="px-5 py-3 font-mono text-xs text-slate-600">{u.org_id ?? '—'}</td>
                <td className="px-5 py-3 text-slate-600">{u.created_at}</td>
                <td className="px-5 py-3">
                  {u.is_2fa_enabled ? (
                    <Button
                      size="sm"
                      variant="secondary"
                      loading={recoveringId === u.id}
                      onClick={() => void recover2fa(u)}
                    >
                      Reset 2FA
                    </Button>
                  ) : (
                    <span className="text-xs text-slate-400">—</span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </AdminPageShell>
  );
}
