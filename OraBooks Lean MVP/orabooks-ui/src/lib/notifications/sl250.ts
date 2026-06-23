/** SL-250 — notification center filters, badges, and list helpers. */

export type NotificationPriority = 'critical' | 'high' | 'normal' | 'low';

export type NotificationStatus =
  | 'pending'
  | 'queued'
  | 'delivering'
  | 'delivered'
  | 'failed'
  | 'dead_letter';

export type NotificationRow = {
  id: number;
  event_type: string;
  priority: string;
  title: string;
  message?: string;
  status: string;
  delivery_channel?: string;
  correlation_id: string;
  created_at: string;
  delivered_at?: string | null;
  is_read: boolean;
  read_at?: string | null;
  sla_breached?: boolean;
  has_delivery_proof?: boolean;
  delivery_proof?: Record<string, unknown> | null;
  delivery_region?: string | null;
  payload?: Record<string, unknown> | null;
};

export type NotificationFilters = {
  unread_only: boolean;
  priority: string;
  status: string;
  event_type: string;
  correlation_id: string;
  from_date: string;
  to_date: string;
};

export const EMPTY_NOTIFICATION_FILTERS: NotificationFilters = {
  unread_only: false,
  priority: '',
  status: '',
  event_type: '',
  correlation_id: '',
  from_date: '',
  to_date: '',
};

export const NOTIFICATION_PRIORITIES: NotificationPriority[] = ['critical', 'high', 'normal', 'low'];

export const NOTIFICATION_STATUSES: NotificationStatus[] = [
  'pending',
  'queued',
  'delivering',
  'delivered',
  'failed',
  'dead_letter',
];

export function buildNotificationQueryParams(filters: NotificationFilters, limit = 50) {
  const params: Record<string, string | number> = { limit };
  if (filters.unread_only) params.unread_only = 1;
  if (filters.priority) params.priority = filters.priority;
  if (filters.status) params.status = filters.status;
  if (filters.event_type.trim()) params.event_type = filters.event_type.trim();
  if (filters.correlation_id.trim()) params.correlation_id = filters.correlation_id.trim();
  if (filters.from_date) params.from_date = filters.from_date;
  if (filters.to_date) params.to_date = `${filters.to_date} 23:59:59`;
  return params;
}

export function normalizeNotificationList(payload: unknown): { notifications: NotificationRow[]; unread: number } {
  if (!payload || typeof payload !== 'object') {
    return { notifications: [], unread: 0 };
  }
  const record = payload as Record<string, unknown>;
  const notifications = Array.isArray(record.notifications) ? (record.notifications as NotificationRow[]) : [];
  const unread =
    typeof record.unread_count === 'number'
      ? record.unread_count
      : typeof record.count === 'number'
        ? record.count
        : 0;
  return { notifications, unread };
}

export function priorityBadgeClass(priority?: string) {
  const map: Record<string, string> = {
    critical: 'bg-red-50 text-red-700 border-red-200',
    high: 'bg-amber-50 text-amber-700 border-amber-200',
    normal: 'bg-primary/10 text-primary border-primary/20',
    low: 'bg-slate-100 text-slate-600 border-slate-200',
  };
  return map[priority || 'normal'] || map.normal;
}

export function statusBadgeClass(status?: string) {
  const map: Record<string, string> = {
    delivered: 'bg-success/10 text-success border-success/20',
    failed: 'bg-red-50 text-red-700 border-red-200',
    dead_letter: 'bg-red-50 text-red-700 border-red-200',
    pending: 'bg-amber-50 text-amber-700 border-amber-200',
    queued: 'bg-primary/10 text-primary border-primary/20',
    delivering: 'bg-primary/10 text-primary border-primary/20',
  };
  return map[status || ''] || 'bg-slate-100 text-slate-600 border-slate-200';
}

export function resolveNotificationViewUrl(url: string) {
  try {
    const target = new URL(url, window.location.href);
    if (target.origin === window.location.origin) {
      return `${target.pathname}${target.search}`;
    }
    return url;
  } catch {
    return url;
  }
}

export function canRetryDelivery(status?: string) {
  return status === 'failed' || status === 'dead_letter';
}

export function formatBudgetAmount(value: number) {
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
}
