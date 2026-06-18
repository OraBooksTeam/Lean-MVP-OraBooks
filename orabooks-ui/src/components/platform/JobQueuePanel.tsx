import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import { api } from '@/pages/frontend/api';
import { RefreshCw } from 'lucide-react';

export default function JobQueuePanel({ showExports = true }: { showExports?: boolean }) {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [exportMessage, setExportMessage] = useState('');

  const load = () => {
    setLoading(true);
    setError('');
    api.asyncQueueStats().then((res: any) => {
      if (res.error) setError(res.error);
      else setData(res.data);
      setLoading(false);
    });
  };

  useEffect(() => {
    load();
  }, []);

  const requestExport = async (format: 'csv' | 'pdf') => {
    setExportMessage('');
    const res = await api.exportRequest('async_queue_data', format);
    if (res.error) setExportMessage(res.error);
    else setExportMessage('Export queued — you will get a notification when ready.');
  };

  const failures = data?.recent_failures || [];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center gap-2">
        <Button onClick={load} variant="secondary" size="sm">
          <RefreshCw className="h-4 w-4" />
          Refresh
        </Button>
        {showExports && (
          <>
            <Button type="button" variant="secondary" size="sm" onClick={() => requestExport('csv')}>
              Export CSV
            </Button>
            <Button type="button" size="sm" onClick={() => requestExport('pdf')}>
              Export PDF
            </Button>
          </>
        )}
        {exportMessage && <span className="text-sm text-slate-600">{exportMessage}</span>}
      </div>

      {error && <p className="text-sm text-danger">{error}</p>}
      {loading ? (
        <div className="h-48 animate-pulse rounded-2xl border border-border bg-white" />
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <Stat label="Total Jobs" value={data?.total ?? 0} />
            <Stat label="Pending" value={data?.pending_count ?? 0} />
            <Stat label="Processing" value={data?.processing_count ?? 0} />
            <Stat label="Completed" value={data?.completed_count ?? 0} />
            <Stat label="Failed" value={data?.failed_count ?? 0} />
            <Stat label="Dead Letter" value={data?.dead_letter_count ?? 0} />
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <Stat label="Avg Latency (24h)" value={data?.avg_latency_seconds ? `${data.avg_latency_seconds}s` : '—'} />
            <Stat label="Failure Rate (24h)" value={data?.failure_rate_24h ? `${data.failure_rate_24h}%` : '—'} />
          </div>
          <div className="glass-panel overflow-hidden">
            <div className="border-b border-border bg-muted/60 px-5 py-3 text-sm font-bold text-ink">
              Recent Failures
            </div>
            <table className="min-w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border bg-slate-50/60 text-xs uppercase text-slate-500">
                  <th className="px-5 py-3 font-semibold">ID</th>
                  <th className="px-5 py-3 font-semibold">Type</th>
                  <th className="px-5 py-3 font-semibold">Retries</th>
                  <th className="px-5 py-3 font-semibold">Error</th>
                  <th className="px-5 py-3 font-semibold">Last Attempt</th>
                  <th className="px-5 py-3 font-semibold">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {failures.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-5 py-6 text-center text-slate-500">
                      No recent failures
                    </td>
                  </tr>
                ) : (
                  failures.map((job: any) => (
                    <tr key={job.id} className="hover:bg-slate-50/60">
                      <td className="px-5 py-3 font-mono text-slate-600">#{job.id}</td>
                      <td className="px-5 py-3">{job.job_type || '—'}</td>
                      <td className="px-5 py-3">{job.retry_count ?? 0}</td>
                      <td className="max-w-xs truncate px-5 py-3 text-slate-600">{job.last_error || '—'}</td>
                      <td className="px-5 py-3 text-slate-600">{job.last_attempt_at || job.created_at || '—'}</td>
                      <td className="px-5 py-3">
                        <Button size="sm" variant="secondary" onClick={() => api.asyncQueueReplay(job.id).then(() => load())}>
                          Retry
                        </Button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}

function Stat({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="stat-card">
      <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</p>
      <p className="mt-2 text-3xl font-black text-ink">{value}</p>
    </div>
  );
}
