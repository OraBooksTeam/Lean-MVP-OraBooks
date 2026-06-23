import { useEffect, useState } from 'react';
import AdminPageShell from '@/components/AdminPageShell';
import AuditLogPanel from '@/components/AuditLogPanel';
import {
  buildAuditQueryParams,
  EMPTY_AUDIT_FILTERS,
  normalizeAuditRows,
  type AuditLogFilters,
  type AuditLogRow,
} from '@/lib/audit/sl009';
import { api } from '../api';

export default function AdminAudit() {
  const isAdmin = Boolean((window as any).orabooks_ajax?.is_admin);
  const [filters, setFilters] = useState<AuditLogFilters>(EMPTY_AUDIT_FILTERS);
  const [applied, setApplied] = useState<AuditLogFilters>(EMPTY_AUDIT_FILTERS);
  const [rows, setRows] = useState<AuditLogRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const buildParams = (f: AuditLogFilters) =>
    buildAuditQueryParams(f, { includeOrgFilter: isAdmin });

  const load = async (f = applied) => {
    setLoading(true);
    setError('');
    const res = await api.auditLogs(buildParams(f));
    if (res.error) setError(res.error);
    else setRows(normalizeAuditRows((res as any).data));
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  const applyFilters = (next?: AuditLogFilters) => {
    const f = next ?? filters;
    if (next) setFilters(next);
    setApplied(f);
    void load(f);
  };

  const clearFilters = () => {
    setFilters(EMPTY_AUDIT_FILTERS);
    setApplied(EMPTY_AUDIT_FILTERS);
    void load(EMPTY_AUDIT_FILTERS);
  };

  const exportCsv = async () => {
    const res = await api.exportAuditLogs(buildParams(applied));
    if (res?.error) return { error: res.error };
  };

  return (
    <AdminPageShell
      title="Audit Log"
      description="Security and compliance events across the platform."
    >
      <AuditLogPanel
        rows={rows}
        loading={loading}
        error={error}
        filters={filters}
        onFiltersChange={setFilters}
        onApplyFilters={applyFilters}
        onClearFilters={clearFilters}
        onRefresh={() => void load()}
        onExport={exportCsv}
        showOrgFilter={isAdmin}
        showOrgColumn={isAdmin}
      />
    </AdminPageShell>
  );
}
