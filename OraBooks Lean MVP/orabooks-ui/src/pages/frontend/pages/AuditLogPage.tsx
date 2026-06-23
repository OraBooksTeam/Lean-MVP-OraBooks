import { useEffect, useState } from 'react';
import AuditLogPanel from '@/components/AuditLogPanel';
import {
  buildAuditQueryParams,
  EMPTY_AUDIT_FILTERS,
  normalizeAuditRows,
  type AuditLogFilters,
  type AuditLogRow,
} from '@/lib/audit/sl009';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { ShieldCheck } from 'lucide-react';

export default function AuditLogPage() {
  const [context, setContext] = useState<any>(null);
  const [filters, setFilters] = useState<AuditLogFilters>(EMPTY_AUDIT_FILTERS);
  const [applied, setApplied] = useState<AuditLogFilters>(EMPTY_AUDIT_FILTERS);
  const [rows, setRows] = useState<AuditLogRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const orgId = context?.organization?.id;
  const canView = (context?.permissions || []).includes('view_audit_logs');

  const buildParams = (f: AuditLogFilters, resolvedOrgId?: number) =>
    buildAuditQueryParams(f, { orgId: resolvedOrgId ?? orgId });

  const load = async (f = applied) => {
    setLoading(true);
    setError('');

    const ctx = await api.frontendContext();
    if (ctx.error) {
      setError(ctx.error || 'Please log in to view audit logs.');
      setLoading(false);
      return;
    }

    const nextContext = (ctx as any).data;
    setContext(nextContext);

    const permissions: string[] = nextContext?.permissions || [];
    if (!permissions.includes('view_audit_logs')) {
      setError('You do not have permission to view audit logs. Contact Owner or Admin.');
      setLoading(false);
      return;
    }

    const res = await api.auditLogs(buildParams(f, nextContext?.organization?.id));
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

  const isPartner = context?.organization?.organization_type === 'partner' || context?.user?.is_partner;

  return (
    <ClientShell
      title="Audit Log"
      eyebrow="Compliance & security"
      organization={context?.organization}
      isPartner={isPartner}
    >
      {!canView && !loading ? (
        <div className="glass-panel max-w-lg p-6 text-center">
          <ShieldCheck className="mx-auto h-8 w-8 text-slate-400" />
          <p className="mt-3 font-medium text-ink">Access denied</p>
          <p className="mt-1 text-sm text-slate-600">
            You do not have permission to view audit logs. Contact Owner or Admin.
          </p>
        </div>
      ) : (
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
          exportDisabled={!canView}
        />
      )}
    </ClientShell>
  );
}
