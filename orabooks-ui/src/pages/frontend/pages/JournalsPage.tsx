import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { Landmark, RefreshCw } from 'lucide-react';

export default function JournalsPage() {
  const [context, setContext] = useState<any>(null);
  const [journals, setJournals] = useState<any[]>([]);
  const [status, setStatus] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = async (nextStatus = status) => {
    setLoading(true);
    setError('');
    const ctx = await api.frontendContext();
    if (ctx.error) {
      setError(ctx.error || 'Unable to load account context.');
      setLoading(false);
      return;
    }

    const nextContext = (ctx as any).data;
    setContext(nextContext);
    const orgId = nextContext?.organization?.id;
    if (!orgId) {
      setError('Organization is not set up yet.');
      setLoading(false);
      return;
    }

    const res = await api.journalsList(orgId, { status: nextStatus });
    if (res.error) setError(res.error || 'Unable to load journals.');
    else setJournals((res as any).data?.journals || (res as any).data || []);
    setLoading(false);
  };

  useEffect(() => { void load(''); }, []);

  return (
    <ClientShell title="Journals" eyebrow="Posting workflow" organization={context?.organization}>
      <div className="space-y-5">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <select
            value={status}
            onChange={(event) => {
              setStatus(event.target.value);
              void load(event.target.value);
            }}
            className="rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
          >
            <option value="">All statuses</option>
            <option value="draft">Draft</option>
            <option value="review_pending">Review Pending</option>
            <option value="approved">Approved</option>
            <option value="posted">Posted</option>
            <option value="locked">Locked</option>
            <option value="reversed">Reversed</option>
          </select>
          <Button onClick={() => load()} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>
        {error && <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>}
        <div className="glass-panel overflow-hidden">
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Journal</th>
                <th className="px-5 py-3 font-semibold">Date</th>
                <th className="px-5 py-3 font-semibold">Status</th>
                <th className="px-5 py-3 font-semibold">Source</th>
                <th className="px-5 py-3 text-right font-semibold">Amount</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={5} className="px-5 py-8 text-center text-slate-500">Loading journals...</td></tr>
              ) : journals.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-5 py-10 text-center">
                    <Landmark className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No journals found.</p>
                  </td>
                </tr>
              ) : journals.map((journal) => (
                <tr key={journal.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3 font-semibold text-ink">{journal.journal_number || `Journal #${journal.id}`}</td>
                  <td className="px-5 py-3 text-slate-600">{journal.transaction_date || 'Not set'}</td>
                  <td className="px-5 py-3"><span className="badge border border-border bg-slate-50 text-slate-700">{journal.status}</span></td>
                  <td className="px-5 py-3 text-slate-600">{journal.source_type || 'Manual'}</td>
                  <td className="px-5 py-3 text-right font-bold text-ink">{money(journal.total_amount)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </ClientShell>
  );
}

function money(value?: string | number) {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(Number(value || 0));
}
