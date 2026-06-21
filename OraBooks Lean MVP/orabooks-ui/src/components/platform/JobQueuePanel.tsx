import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import { api } from '@/pages/frontend/api';
import { RefreshCw } from 'lucide-react';

export default function JobQueuePanel({ showExports = true }: { showExports?: boolean }) {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [exportMessage, setExportMessage] = useState('');
  const [filters, setFilters] = useState({ status: '', job_type: '', queue_name: '' });

  const load = () => {
    setLoading(true);
    setError('');
    api.asyncQueueStats({ ...filters, limit: 75 }).then((res: any) => {
      if (res.error) setError(res.error);
      else setData(res.data);
      setLoading(false);
    });
  };

  useEffect(() => {
    load();
  }, []);

  useEffect(() => {
    load();
  }, [filters.status, filters.job_type, filters.queue_name]);

  const requestExport = async (format: 'csv' | 'pdf') => {
    setExportMessage('');
    const res = await api.exportRequest('async_queue_data', format);
    if (res.error) setExportMessage(res.error);
    else setExportMessage('Export queued — you will get a notification when ready.');
  };

  const failures = data?.recent_failures || [];
  const jobs = data?.jobs || [];

  const action = async (fn: () => Promise<any>) => {
    setError('');
    const res = await fn();
    if (res?.error) setError(res.error);
    await load();
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center gap-2">
        <Button onClick={load} variant="secondary" size="sm">
          <RefreshCw className="h-4 w-4" />
          Refresh
        </Button>
        <Button onClick={() => action(() => api.asyncQueuePollNow())} variant="secondary" size="sm">
          Poll Now
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
          <div className="grid gap-3 rounded-2xl border border-border bg-white p-4 sm:grid-cols-3">
            <label className="text-xs font-bold uppercase tracking-wide text-slate-500">
              Status
              <select
                value={filters.status}
                onChange={(e) => setFilters((prev) => ({ ...prev, status: e.target.value }))}
                className="mt-1 w-full rounded-xl border border-border px-3 py-2 text-sm font-normal normal-case text-ink"
              >
                <option value="">All</option>
                <option value="pending">Pending</option>
                <option value="processing">Processing</option>
                <option value="completed">Completed</option>
                <option value="dead_letter">Dead Letter</option>
                <option value="cancelled">Cancelled</option>
                <option value="discarded">Discarded</option>
              </select>
            </label>
            <label className="text-xs font-bold uppercase tracking-wide text-slate-500">
              Queue
              <select
                value={filters.queue_name}
                onChange={(e) => setFilters((prev) => ({ ...prev, queue_name: e.target.value }))}
                className="mt-1 w-full rounded-xl border border-border px-3 py-2 text-sm font-normal normal-case text-ink"
              >
                <option value="">All</option>
                <option value="default">default</option>
                <option value="reports">reports</option>
                <option value="webhooks">webhooks</option>
                <option value="exports">exports</option>
              </select>
            </label>
            <label className="text-xs font-bold uppercase tracking-wide text-slate-500">
              Job Type
              <input
                value={filters.job_type}
                onChange={(e) => setFilters((prev) => ({ ...prev, job_type: e.target.value }))}
                placeholder="webhook_dispatch"
                className="mt-1 w-full rounded-xl border border-border px-3 py-2 text-sm font-normal normal-case text-ink"
              />
            </label>
          </div>
          <div className="glass-panel overflow-hidden">
            <div className="border-b border-border bg-muted/60 px-5 py-3 text-sm font-bold text-ink">
              Jobs
            </div>
            <table className="min-w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border bg-slate-50/60 text-xs uppercase text-slate-500">
                  <th className="px-5 py-3 font-semibold">ID</th>
                  <th className="px-5 py-3 font-semibold">Queue</th>
                  <th className="px-5 py-3 font-semibold">Type</th>
                  <th className="px-5 py-3 font-semibold">Status</th>
                  <th className="px-5 py-3 font-semibold">Retries</th>
                  <th className="px-5 py-3 font-semibold">Next Retry</th>
                  <th className="px-5 py-3 font-semibold">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {jobs.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-5 py-6 text-center text-slate-500">
                      No jobs found
                    </td>
                  </tr>
                ) : (
                  jobs.map((job: any) => {
                    const payload = safePayload(job.payload);
                    const isRetryWait =
                      job.status === 'pending' && job.next_retry_at && new Date(job.next_retry_at).getTime() > Date.now();
                    return (
                      <tr key={job.id} className="hover:bg-slate-50/60">
                        <td className="px-5 py-3 font-mono text-slate-600">#{job.id}</td>
                        <td className="px-5 py-3">{job.queue_name || 'default'}</td>
                        <td className="px-5 py-3">{job.job_type || '—'}</td>
                        <td className="px-5 py-3">{isRetryWait ? 'pending (retry wait)' : job.status}</td>
                        <td className="px-5 py-3">
                          {job.retry_count ?? 0}/{job.max_retries ?? 0}
                        </td>
                        <td className="px-5 py-3 text-slate-600">{job.next_retry_at || '—'}</td>
                        <td className="space-x-2 px-5 py-3">
                          {payload.file_url && (
                            <a href={payload.file_url} className="text-sm font-semibold text-primary" target="_blank" rel="noreferrer">
                              Download CSV
                            </a>
                          )}
                          {['dead_letter', 'failed', 'completed'].includes(job.status) && (
                            <Button size="sm" variant="secondary" onClick={() => action(() => api.asyncQueueReplay(job.id))}>
                              Replay
                            </Button>
                          )}
                          {['dead_letter', 'failed'].includes(job.status) && (
                            <Button size="sm" variant="secondary" onClick={() => action(() => api.asyncQueueDiscard(job.id))}>
                              Discard
                            </Button>
                          )}
                          {isRetryWait && (
                            <Button size="sm" variant="secondary" onClick={() => action(() => api.asyncQueueCancel(job.id))}>
                              Cancel
                            </Button>
                          )}
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
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

function safePayload(payload: any) {
  if (!payload) return {};
  if (typeof payload === 'object') return payload;
  try {
    return JSON.parse(payload);
  } catch {
    return {};
  }
}
