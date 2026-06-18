import AdminPageShell from '@/components/AdminPageShell';

export default function AdminCsvImports() {
  return (
    <AdminPageShell
      title="CSV Imports"
      description="Bulk import customers, vendors, and chart-of-account data from CSV files."
    >
      <div className="glass-panel p-6">
        <p className="text-sm text-ink-secondary">
          CSV import uploads are processed asynchronously per organization. Select an organization in the
          client workspace, then use the import workflow from your accounting menu.
        </p>
        <p className="mt-4 text-sm text-ink-secondary">
          Admin-wide import management is coming soon. For now, monitor job queue status for import parsing tasks.
        </p>
        <a
          href="admin.php?page=orabooks-job-queue"
          className="mt-6 inline-flex items-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-dark"
        >
          Open Job Queue
        </a>
      </div>
    </AdminPageShell>
  );
}
