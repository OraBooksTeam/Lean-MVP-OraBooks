const ORABOOKS_NONCE = (window as any).orabooks_ajax?.nonce || '';
const ORABOOKS_URL = (window as any).orabooks_ajax?.ajax_url || '/wp-admin/admin-ajax.php';
const ORABOOKS_USER_ID = (window as any).orabooks_ajax?.current_user_id ?? null;
const TOKEN_KEY = 'orabooks_token';
const REFRESH_TOKEN_KEY = 'orabooks_refresh_token';

type Json = Record<string, any> | any[] | null;
type ApiResult<T = Json> =
  | { data: T; error?: never; success?: never }
  | { data?: never; error: string; success?: never }
  | { data?: never; error?: never; success: true };

function extractError(data: any, fallback: string) {
  if (typeof data === 'string') return data;
  if (data?.message) return String(data.message);
  if (data?.error) return String(data.error);
  return fallback;
}

function getStoredToken() {
  return window.localStorage.getItem(TOKEN_KEY) || '';
}

function persistTokens(data: any) {
  if (data?.token) {
    window.localStorage.setItem(TOKEN_KEY, String(data.token));
  }

  if (data?.refresh_token) {
    window.localStorage.setItem(REFRESH_TOKEN_KEY, String(data.refresh_token));
  }
}

function normalizeResponse<T = any>(json: any): ApiResult<T> {
  if (json?.success === false) {
    return { error: extractError(json.data, 'OraBooks request failed.') };
  }

  if (json?.success === true && Object.prototype.hasOwnProperty.call(json, 'data')) {
    persistTokens(json.data);
    return { data: json.data as T };
  }

  if (json?.error === false && Object.prototype.hasOwnProperty.call(json, 'data')) {
    persistTokens(json.data);
    return { data: json.data as T };
  }

  if (json?.error) {
    return { error: extractError(json.error, 'OraBooks request failed.') };
  }

  return json as ApiResult<T>;
}

async function parseResponse<T = any>(res: Response): Promise<ApiResult<T>> {
  const text = await res.text();

  if (!res.ok) {
    return { error: `OraBooks request failed with HTTP ${res.status}.` };
  }

  try {
    return normalizeResponse<T>(JSON.parse(text));
  } catch {
    const trimmed = text.trim();
    const preview = trimmed ? trimmed.slice(0, 160) : 'Empty response';
    return { error: `OraBooks returned an invalid AJAX response: ${preview}` };
  }
}

async function request<T = any>(
  payload: Record<string, any>,
  method = 'POST'
): Promise<ApiResult<T>> {
  const body = new URLSearchParams();
  const token = getStoredToken();
  body.set('action', payload.action);
  body.set('_ajax_nonce', ORABOOKS_NONCE);
  if (token) body.set('orabooks_token', token);
  if (ORABOOKS_USER_ID) body.set('current_user_id', String(ORABOOKS_USER_ID));
  Object.entries(payload).forEach(([k, v]) => {
    if (k === 'action' || k === '_ajax_nonce' || k === 'current_user_id') return;
    if (typeof v === 'object') body.set(k, JSON.stringify(v));
    else body.set(k, String(v ?? ''));
  });

  try {
    const res = await fetch(ORABOOKS_URL, {
      method,
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body,
    });

    return parseResponse<T>(res);
  } catch (error) {
    return { error: error instanceof Error ? error.message : 'OraBooks request failed.' };
  }
}

export const api = {
  get<T = any>(action: string, params: Record<string, any> = {}): Promise<ApiResult<T>> {
    const qs = new URLSearchParams();
    const token = getStoredToken();
    qs.set('action', action);
    qs.set('_ajax_nonce', ORABOOKS_NONCE);
    if (token) qs.set('orabooks_token', token);
    if (ORABOOKS_USER_ID) qs.set('current_user_id', String(ORABOOKS_USER_ID));
    Object.entries(params).forEach(([k, v]) => {
      if (typeof v === 'object') qs.set(k, JSON.stringify(v));
      else if (v !== undefined && v !== null && v !== '') qs.set(k, String(v));
    });

    return fetch(`${ORABOOKS_URL}?${qs.toString()}`, {
      headers: token ? { Authorization: `Bearer ${token}` } : {},
    })
      .then((r) => parseResponse<T>(r))
      .catch((error) => ({
        error: error instanceof Error ? error.message : 'OraBooks request failed.',
      }));
  },

  post<T = any>(action: string, params: Record<string, any> = {}): Promise<ApiResult<T>> {
    return request<T>({ action, ...params }, 'POST');
  },

  // Auth
  login: (email: string, password: string) =>
    api.post('orabooks_login', { email, password }),
  register: (data: Record<string, any>) =>
    api.post('orabooks_register', data),
  oidcInitiate: () =>
    api.post('orabooks_oidc_initiate'),
  oidcCallback: (code: string, state: string) =>
    api.post('orabooks_oidc_callback', { code, state }),
  twoFactorChallenge: (tempToken: string, otp: string, backup = '') =>
    api.post('orabooks_2fa_challenge', { temp_token: tempToken, otp_code: otp, backup_code: backup }),
  getPartnerInfo: () =>
    api.post('orabooks_get_partner_info'),
  requestReactivation: (orgId: number, reason: string) =>
    api.post('orabooks_request_reactivation', { org_id: orgId, reason }),
  partnerCodeCopied: (source = 'dashboard') =>
    api.post('orabooks_partner_code_copied', { source }),

  // Dashboard / Stats
  frontendContext: () =>
    api.get('orabooks_frontend_context'),
  customerDashboard: () =>
    api.get('orabooks_customer_dashboard'),
  dashboardStats: () =>
    api.get('orabooks_dashboard_stats'),
  customerStats: (orgId = 0) =>
    api.get('orabooks_customer_stats', { org_id: orgId }),
  partnerDashboard: () =>
    api.get('orabooks_partner_dashboard'),
  commissionStats: (partnerUserId?: number) =>
    api.get('orabooks_commission_stats', { partner_user_id: partnerUserId ?? 0 }),
  commissionEarned: (partnerUserId?: number) =>
    api.get('orabooks_commission_earned', { partner_user_id: partnerUserId ?? 0 }),
  commissionPayouts: (partnerUserId?: number) =>
    api.get('orabooks_commission_payouts', { partner_user_id: partnerUserId ?? 0 }),
  commissionAging: (partnerUserId?: number) =>
    api.get('orabooks_commission_aging', { partner_user_id: partnerUserId ?? 0 }),
  commissionEscrow: (partnerUserId?: number) =>
    api.get('orabooks_commission_escrow_schedule', { partner_user_id: partnerUserId ?? 0 }),

  // Org / Users
  listOrgs: (type = '', status = '') =>
    api.get('orabooks_list_orgs', { type, status }),
  suspendOrg: (orgId: number) =>
    api.post('orabooks_suspend_org', { org_id: orgId }),
  activateOrg: (orgId: number) =>
    api.post('orabooks_activate_org', { org_id: orgId }),
  listUsers: () =>
    api.get('orabooks_list_users'),

  // Customers / Invoices
  customersList: (orgId = 0, filters = {}) =>
    api.get('orabooks_customers_list', { org_id: orgId, ...filters }),
  customerGet: (customerId: number) =>
    api.get('orabooks_customer_get', { customer_id: customerId }),
  customerUpdate: (customerId: number, data: Record<string, any>) =>
    api.post('orabooks_customer_update', { customer_id: customerId, ...data }),
  invoicesList: (orgId = 0, filters = {}) =>
    api.get('orabooks_invoices_list', { org_id: orgId, ...filters }),
  invoiceGet: (invoiceId: number) =>
    api.get('orabooks_invoice_get', { invoice_id: invoiceId }),
  invoiceCreate: (data: Record<string, any>) =>
    api.post('orabooks_invoice_create', data),
  recordPayment: (data: Record<string, any>) =>
    api.post('orabooks_invoice_record_payment', data),

  // CoA / Audit
  coaGet: (orgId: number) =>
    api.get('orabooks_get_coa', { org_id: orgId }),
  journalsList: (orgId: number, filters = {}) =>
    api.get('orabooks_get_journals', { org_id: orgId, ...filters }),
  auditLogs: (filters = {}) =>
    api.get('orabooks_get_audit_logs', filters),
  pendingPartners: () =>
    api.get('orabooks_admin_list_pending_partners'),
  activePartners: () =>
    api.get('orabooks_admin_list_active_partners'),
  reactivationRequests: () =>
    api.get('orabooks_admin_list_reactivation_requests'),
  approvePartner: (codeId: number) =>
    api.post('orabooks_admin_approve_partner', { partner_code_id: codeId }),
  rejectPartner: (codeId: number, reason: string) =>
    api.post('orabooks_admin_reject_partner', { partner_code_id: codeId, reason }),
  reviewReactivation: (reviewId: number, decision: 'approved' | 'denied', notes = '') =>
    api.post('orabooks_admin_review_reactivation', { review_id: reviewId, decision, notes }),

  // Notifications
  notificationsList: (params = {}) =>
    api.get('orabooks_notifications_list', params),
  notificationsMarkRead: (notificationId: number) =>
    api.post('orabooks_notifications_mark_read', { notification_id: notificationId }),
  notificationsMarkAllRead: () =>
    api.post('orabooks_notifications_mark_all_read'),
  notificationPrefsGet: () =>
    api.get('orabooks_notification_preferences_get'),
  notificationPrefsSave: (data: Record<string, any>) =>
    api.post('orabooks_notification_preferences_save', data),
  notificationPolicyGet: (orgId = 0) =>
    api.get('orabooks_notification_admin_policy_get', { org_id: orgId }),
  notificationPolicySave: (data: Record<string, any>) =>
    api.post('orabooks_notification_admin_policy_save', data),

  // Exports
  exportRequest: (exportType: string, format: 'csv' | 'pdf', parameters?: Record<string, any>) =>
    api.post('orabooks_export_request', {
      export_type: exportType,
      format,
      parameters: parameters ? JSON.stringify(parameters) : '',
    }),
};
