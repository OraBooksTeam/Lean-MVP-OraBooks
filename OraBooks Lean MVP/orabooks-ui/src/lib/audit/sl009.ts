/** — audit log filters, metadata display, and export helpers. */

export type AuditSeverity = 'info' | 'warning' | 'critical';

export type AuditLogRow = {
 id?: number;
 org_id?: number;
 created_at: string;
 user_id?: number | null;
 user_email?: string;
 event_type: string;
 severity: string;
 description: string;
 ip_address?: string;
 user_agent?: string;
 correlation_id: string;
 metadata?: string | Record<string, unknown> | null;
};

export type AuditLogFilters = {
 user_id: string;
 event_type: string;
 severity: string;
 from_date: string;
 to_date: string;
 org_id: string;
 correlation_id: string;
};

export const EMPTY_AUDIT_FILTERS: AuditLogFilters = {
 user_id: '',
 event_type: '',
 severity: '',
 from_date: '',
 to_date: '',
 org_id: '',
 correlation_id: '',
};

export const AUDIT_SEVERITIES: AuditSeverity[] = ['info', 'warning', 'critical'];

export function buildAuditQueryParams(
 filters: AuditLogFilters,
 options: { orgId?: number; limit?: number; includeOrgFilter?: boolean } = {}
) {
 const params: Record<string, string | number> = {
 limit: options.limit ?? 100,
 };

 const resolvedOrgId = options.orgId ?? (filters.org_id ? Number(filters.org_id): 0);
 if (resolvedOrgId > 0) {
 params.org_id = resolvedOrgId;
 } else if (options.includeOrgFilter && filters.org_id) {
 params.org_id = Number(filters.org_id);
 }

 if (filters.user_id) params.user_id = Number(filters.user_id);
 if (filters.event_type.trim) params.event_type = filters.event_type.trim;
 if (filters.severity) params.severity = filters.severity;
 if (filters.from_date) params.from_date = filters.from_date;
 if (filters.to_date) params.to_date = `${filters.to_date} 23:59:59`;
 if (filters.correlation_id.trim) params.correlation_id = filters.correlation_id.trim;

 return params;
}

export function normalizeAuditRows(payload: unknown): AuditLogRow[] {
 if (Array.isArray(payload)) {
 return payload as AuditLogRow[];
 }
 if (payload && typeof payload === 'object') {
 const record = payload as Record<string, unknown>;
 if (Array.isArray(record.logs)) {
 return record.logs as AuditLogRow[];
 }
 if (Array.isArray(record.data)) {
 return record.data as AuditLogRow[];
 }
 }
 return [];
}

export function parseAuditMetadata(metadata: AuditLogRow['metadata']): Record<string, unknown> | null {
 if (!metadata) return null;
 if (typeof metadata === 'object') return metadata;
 if (typeof metadata !== 'string' || metadata.trim === '') return null;
 try {
 const parsed = JSON.parse(metadata);
 return parsed && typeof parsed === 'object' ? (parsed as Record<string, unknown>): null;
 } catch {
 return null;
 }
}

export function formatAuditMetadata(metadata: AuditLogRow['metadata']): string {
 const parsed = parseAuditMetadata(metadata);
 if (!parsed) {
 return typeof metadata === 'string' ? metadata: '';
 }
 return JSON.stringify(parsed, null, 2);
}

export function severityBadgeClass(severity?: string) {
 const map: Record<string, string> = {
 info: 'bg-sky-50 text-sky-700 border-sky-200',
 warning: 'bg-amber-50 text-amber-700 border-amber-200',
 critical: 'bg-red-50 text-red-700 border-red-200',
 error: 'bg-red-50 text-red-700 border-red-200',
 };
 return map[severity || ''] || 'bg-slate-100 text-slate-600 border-slate-200';
}

export function extractExportFilename(contentDisposition: string | null, fallback: string) {
 if (!contentDisposition) return fallback;
 const match = contentDisposition.match(/filename\*?=(?:UTF-8''|")?([^";]+)/i);
 return match?.[1]?.trim.replace(/"/g, '') || fallback;
}
