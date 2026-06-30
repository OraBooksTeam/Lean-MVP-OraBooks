import { useEffect, useState } from 'react';
import AdminPageShell from '@/components/AdminPageShell';
import { api } from '../api';
import { RefreshCw } from 'lucide-react';
import Button from '@/components/Button';

interface CsvImportRow {
  id: number;
  org_id: number;
  user_id: number;
  resource_type: string;
  original_filename: string;
  status: string;
  total_rows: number;
  processed_rows: number;
  created_at: string;
}

export default function AdminCsvImports() {
  const adminBase = (window as any).orabooks_ajax?.admin_base || '/wp-admin/admin.php';
  const jobQueueUrl = `${adminBase}?page=orabooks-job-queue`;
  const [imports, setImports] = useState<CsvImportRow[]>([]);
  const [isPlatformAdmin, setIsPlatformAdmin] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = () => {
    setLoading(true);
    setError('');
    api.csvImportsDashboard().then((res) => {
      if (res.error) setError(res.error);
      else {
        setImports(res.data?.imports || res.data?.recent_imports || []);
        setIsPlatformAdmin(Boolean(res.data?.is_platform_admin));
      }
      setLoading(false);
    });
  };

  useEffect(() => {
    load();
  }, []);

  const statusCounts = imports.reduce<Record<string, number>>((acc, row) => {
    acc[row.status] = (acc[row.status] || 0) + 1;
    return acc;
  }, {});

  return (
    <AdminPageShell
      title="CSV Imports"
      description="Bulk import jobs for expenses, invoices, inventory, contacts, journals, and more."
      actions={
        <Button onClick={load} variant="secondary" size="sm">
          <RefreshCw className="h-4 w-4" />
          Refresh
        </Button>
      }
    >
      {error && <p className="text-sm text-danger">{error}</p>}

      <div className="grid gap-4 sm:grid-cols-4">
        <div className="stat-card">
          <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Total</p>
          <p className="mt-2 text-3xl font-black text-ink">{imports.length}</p>
        </div>
        {['pending_confirm', 'confirmed', 'failed', 'parsing'].map((status) => (
          <div key={status} className="stat-card">
            <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{status.replace(/_/g, ' ')}</p>
            <p className="mt-2 text-3xl font-black text-ink">{statusCounts[status] || 0}</p>
          </div>
        ))}
      </div>

      <div className="-mx-4 overflow-x-auto overflow-y-hidden px-4 sm:mx-0 sm:px-0">
        <div className="glass-panel min-w-0 overflow-hidden">
          <div className="min-w-[700px]">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border bg-slate-50/60 text-xs uppercase text-slate-500">
                  <th className="px-5 py-3 font-semibold">ID</th>
                  {isPlatformAdmin && <th className="px-5 py-3 font-semibold">Org</th>}
                  <th className="px-5 py-3 font-semibold">Type</th>
                  <th className="px-5 py-3 font-semibold">File</th>
                  <th className="px-5 py-3 font-semibold">Status</th>
                  <th className="px-5 py-3 font-semibold">Rows</th>
                  <th className="px-5 py-3 font-semibold">Created</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {loading ? (
                  <tr>
                    <td colSpan={isPlatformAdmin ? 7 : 6} className="px-5 py-6 text-center text-slate-500">Loading…</td>
                  </tr>
                ) : imports.length === 0 ? (
                  <tr>
                    <td colSpan={isPlatformAdmin ? 7 : 6} className="px-5 py-6 text-center text-slate-500">
                      No import jobs yet. Upload CSV files from your organization workspace.
                    </td>
                  </tr>
                ) : (
                  imports.map((row) => (
                    <tr key={row.id} className="hover:bg-slate-50/60">
                      <td className="px-5 py-3 font-mono text-xs text-slate-600">#{row.id}</td>
                      {isPlatformAdmin && (
                        <td className="px-5 py-3 font-mono text-xs text-slate-600">{row.org_id}</td>
                      )}
                      <td className="px-5 py-3 font-medium text-ink">{row.resource_type.replace(/_/g, ' ')}</td>
                      <td className="px-5 py-3 text-slate-600">{row.original_filename}</td>
                      <td className="px-5 py-3">
                        <StatusBadge status={row.status} />
                      </td>
                      <td className="px-5 py-3 text-slate-600">
                        {row.processed_rows}/{row.total_rows}
                      </td>
                      <td className="px-5 py-3 text-slate-600">{row.created_at}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <p className="text-sm text-ink-secondary">
        Parse and confirm workflows run in the client workspace. Monitor background parsing in the job queue.
      </p>
      <a
        href={jobQueueUrl}
        className="inline-flex items-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-dark"
      >
        Open Job Queue
      </a>
    </AdminPageShell>
  );
}

function StatusBadge({ status }: { status: string }) {
  const map: Record<string, string> = {
    uploaded: 'bg-slate-100 text-slate-600 border-slate-200',
    parsing: 'bg-blue-50 text-blue-800 border-blue-200',
    mapping: 'bg-indigo-50 text-indigo-800 border-indigo-200',
    pending_confirm: 'bg-amber-50 text-amber-700 border-amber-200',
    confirmed: 'bg-success/10 text-success border-success/20',
    failed: 'bg-red-50 text-red-700 border-red-200',
  };
  const cls = map[status] || map.uploaded;
  return <span className={`badge border capitalize ${cls}`}>{status.replace(/_/g, ' ')}</span>;
}
