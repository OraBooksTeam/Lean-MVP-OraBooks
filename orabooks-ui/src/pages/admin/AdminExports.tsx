import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import AdminPageShell from '@/components/AdminPageShell';
import { api } from '../api';
import { Download, RefreshCw } from 'lucide-react';

export default function AdminExports() {
  const [loading, setLoading] = useState(true);
  const [exports, setExports] = useState<any[]>([]);

  const load = () => {
    setLoading(true);
    api.exportsList(1).then((res: any) => {
      if (!res.error && res.data) setExports(res.data.exports || []);
      setLoading(false);
    });
  };

  useEffect(() => {
    load();
  }, []);

  return (
    <AdminPageShell
      title="My Exports"
      description="Async report and data exports with download links when ready."
      actions={
        <Button onClick={load} variant="secondary" size="sm">
          <RefreshCw className="h-4 w-4" />
          Refresh
        </Button>
      }
    >
      <div className="glass-panel overflow-hidden">
        <table className="min-w-full text-left text-sm">
          <thead>
            <tr className="border-b border-border bg-slate-50/60 text-xs uppercase text-slate-500">
              <th className="px-5 py-3 font-semibold">Type</th>
              <th className="px-5 py-3 font-semibold">Format</th>
              <th className="px-5 py-3 font-semibold">Status</th>
              <th className="px-5 py-3 font-semibold">Created</th>
              <th className="px-5 py-3 font-semibold">Download</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {loading ? (
              <tr><td colSpan={5} className="px-5 py-6 text-center text-slate-500">Loading exports…</td></tr>
            ) : exports.length === 0 ? (
              <tr><td colSpan={5} className="px-5 py-6 text-center text-slate-500">No exports yet.</td></tr>
            ) : (
              exports.map((row) => (
                <tr key={row.id} className="hover:bg-slate-50/60">
                  <td className="px-5 py-3 font-medium text-ink">{row.export_type}</td>
                  <td className="px-5 py-3 uppercase text-slate-600">{row.format}</td>
                  <td className="px-5 py-3">
                    <span className="badge border border-primary/20 bg-primary/10 text-primary">{row.status}</span>
                  </td>
                  <td className="px-5 py-3 text-slate-600">{row.created_at}</td>
                  <td className="px-5 py-3">
                    {row.download_url ? (
                      <a href={row.download_url} className="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:text-primary-dark">
                        <Download className="h-4 w-4" /> Download
                      </a>
                    ) : '—'}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </AdminPageShell>
  );
}
