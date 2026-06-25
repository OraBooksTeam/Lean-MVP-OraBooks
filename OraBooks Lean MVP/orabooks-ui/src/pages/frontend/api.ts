type AjaxConfig = {
  ajax_url?: string;
  nonce?: string;
  current_user_id?: number | null;
  login_url?: string;
};

function getAjaxConfig(): Required<Pick<AjaxConfig, 'ajax_url' | 'nonce'>> & AjaxConfig {
  const cfg = ((window as any).orabooks_ajax || {}) as AjaxConfig;
  return {
    ajax_url: cfg.ajax_url || '/wp-admin/admin-ajax.php',
    nonce: cfg.nonce || '',
    current_user_id: cfg.current_user_id ?? null,
    login_url: cfg.login_url,
    ...cfg,
  };
}

export const AUTH_TOKEN_STORAGE_KEY = 'orabooks_token';
const TOKEN_KEY = AUTH_TOKEN_STORAGE_KEY;

type Json = Record<string, any> | any[] | null;
type ApiResult<T = Json> =
  | { data: T; error?: never; success?: never }
  | { data?: never; error: string; success?: never }
  | { data?: never; error?: never; success: true };

type RequestOptions = {
  clearAuthOnFailure?: boolean;
};

function extractError(data: any, fallback: string) {
  if (typeof data === 'string') return data;
  if (data?.message) return String(data.message);
  if (data?.error && typeof data.error === 'string') return String(data.error);
  return fallback;
}

function getStoredToken() {
  return window.localStorage.getItem(TOKEN_KEY) || '';
}

function authRequestHeaders(token = getStoredToken()): Record<string, string> {
  if (!token) {
    return {};
  }

  return {
    Authorization: `Bearer ${token}`,
    'X-OraBooks-Token': token,
  };
}

export function hasStoredAuthToken() {
  return Boolean(getStoredToken());
}

function persistTokens(data: any) {
  if (data?.token) {
    window.localStorage.setItem(TOKEN_KEY, String(data.token));
  }
}

export function clearPersistedAuthTokens() {
  window.localStorage.removeItem(TOKEN_KEY);
}

function normalizeResponse<T = any>(json: any): ApiResult<T> {
  if (json?.success === false) {
    return { error: extractError(json.data ?? json.message, 'OraBooks request failed.') };
  }

  if (json?.success === true && Object.prototype.hasOwnProperty.call(json, 'data')) {
    persistTokens(json.data);
    return { data: json.data as T };
  }

  if (json?.error === false && Object.prototype.hasOwnProperty.call(json, 'data')) {
    persistTokens(json.data);
    return { data: json.data as T };
  }

  if (json?.error === true || json?.error === 'true') {
    return { error: extractError(json.message ?? json.data, 'OraBooks request failed.') };
  }

  if (json?.error && typeof json.error === 'string') {
    return { error: json.error };
  }

  return json as ApiResult<T>;
}

async function parseResponse<T = any>(
  res: Response,
  options: RequestOptions = {}
): Promise<ApiResult<T>> {
  const clearAuthOnFailure = options.clearAuthOnFailure !== false;
  const text = await res.text();
  let parsed: any = null;
  let hasParsedJson = false;

  try {
    parsed = JSON.parse(text);
    hasParsedJson = true;
  } catch {
    // Non-JSON error bodies are handled below with a short preview.
  }

  if (!res.ok) {
    if (clearAuthOnFailure && res.status === 401) {
      clearPersistedAuthTokens();
    }
    if (hasParsedJson) {
      return { error: extractError(parsed, `OraBooks request failed with HTTP ${res.status}.`) };
    }
    const trimmed = text.trim();
    const preview = trimmed ? trimmed.slice(0, 160) : `HTTP ${res.status}`;
    return { error: `OraBooks request failed: ${preview}` };
  }

  if (hasParsedJson) {
    return normalizeResponse<T>(parsed);
  }

  {
    const trimmed = text.trim();
    const preview = trimmed ? trimmed.slice(0, 160) : 'Empty response';
    return { error: `OraBooks returned an invalid AJAX response: ${preview}` };
  }
}

let refreshInFlight: Promise<boolean> | null = null;

async function tryRefreshSession(): Promise<boolean> {
  if (refreshInFlight) {
    return refreshInFlight;
  }

  refreshInFlight = (async () => {
    const cfg = getAjaxConfig();
    const body = new URLSearchParams();
    body.set('action', 'orabooks_refresh_token');
    body.set('_ajax_nonce', cfg.nonce);

    try {
      const res = await fetch(cfg.ajax_url, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8' },
        body,
      });
      const parsed = await parseResponse(res, { clearAuthOnFailure: false });
      return !parsed.error;
    } catch {
      return false;
    } finally {
      refreshInFlight = null;
    }
  })();

  return refreshInFlight;
}

async function establishSession(token: string, refreshToken = '') {
  const cfg = getAjaxConfig();
  const body = new URLSearchParams();
  body.set('action', 'orabooks_establish_session');
  body.set('_ajax_nonce', cfg.nonce);
  body.set('orabooks_token', token);
  if (refreshToken) {
    body.set('refresh_token', refreshToken);
  }

  const res = await fetch(cfg.ajax_url, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8' },
    body,
  });

  return parseResponse(res, { clearAuthOnFailure: false });
}

async function request<T = any>(
  payload: Record<string, any>,
  method = 'POST',
  options: RequestOptions = {},
  allowAuthRetry = true
): Promise<ApiResult<T>> {
  const cfg = getAjaxConfig();
  const body = new URLSearchParams();
  const token = getStoredToken();
  body.set('action', payload.action);
  body.set('_ajax_nonce', cfg.nonce);
  if (token) body.set('orabooks_token', token);
  if (cfg.current_user_id) body.set('current_user_id', String(cfg.current_user_id));
  Object.entries(payload).forEach(([k, v]) => {
    if (k === 'action' || k === '_ajax_nonce' || k === 'current_user_id') return;
    if (Array.isArray(v)) {
      v.forEach((item) => body.append(`${k}[]`, String(item)));
      return;
    }
    if (typeof v === 'object') body.set(k, JSON.stringify(v));
    else body.set(k, String(v ?? ''));
  });

  try {
    const res = await fetch(cfg.ajax_url, {
      method,
      credentials: 'include',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8',
        ...authRequestHeaders(token),
      },
      body,
    });

    const parsed = await parseResponse<T>(res, options);
    if (parsed.error && res.status === 401 && allowAuthRetry) {
      const refreshed = await tryRefreshSession();
      if (refreshed) {
        return request<T>(payload, method, options, false);
      }

      const storedToken = getStoredToken();
      if (storedToken) {
        const established = await establishSession(storedToken);
        if (!established.error) {
          return request<T>(payload, method, options, false);
        }
      }
    }

    return parsed;
  } catch (error) {
    return { error: error instanceof Error ? error.message : 'OraBooks request failed.' };
  }
}

async function uploadRequest<T = any>(
  action: string,
  formData: FormData,
  options: RequestOptions = {}
): Promise<ApiResult<T>> {
  const cfg = getAjaxConfig();
  const token = getStoredToken();
  formData.set('action', action);
  formData.set('_ajax_nonce', cfg.nonce);
  if (token) formData.set('orabooks_token', token);
  if (cfg.current_user_id) formData.set('current_user_id', String(cfg.current_user_id));

  try {
    const res = await fetch(cfg.ajax_url, {
      method: 'POST',
      credentials: 'include',
      headers: token ? authRequestHeaders(token) : {},
      body: formData,
    });

    return parseResponse<T>(res, options);
  } catch (error) {
    return { error: error instanceof Error ? error.message : 'OraBooks upload failed.' };
  }
}

function encodeOidcStateData(data: Record<string, unknown>): string {
  const json = JSON.stringify(data);
  const bytes = new TextEncoder().encode(json);
  let binary = '';
  bytes.forEach((byte) => {
    binary += String.fromCharCode(byte);
  });
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

export const api = {
  get<T = any>(
    action: string,
    params: Record<string, any> = {},
    options: RequestOptions = {}
  ): Promise<ApiResult<T>> {
    const cfg = getAjaxConfig();
    const qs = new URLSearchParams();
    const token = getStoredToken();
    qs.set('action', action);
    qs.set('_ajax_nonce', cfg.nonce);
    if (token) qs.set('orabooks_token', token);
    if (cfg.current_user_id) qs.set('current_user_id', String(cfg.current_user_id));
    Object.entries(params).forEach(([k, v]) => {
      if (typeof v === 'object') qs.set(k, JSON.stringify(v));
      else if (v !== undefined && v !== null && v !== '') qs.set(k, String(v));
    });

    return fetch(`${cfg.ajax_url}?${qs.toString()}`, {
      credentials: 'include',
      headers: token ? authRequestHeaders(token) : {},
    })
      .then((r) => parseResponse<T>(r, options))
      .catch((error) => ({
        error: error instanceof Error ? error.message : 'OraBooks request failed.',
      }));
  },

  post<T = any>(
    action: string,
    params: Record<string, any> = {},
    options: RequestOptions = {}
  ): Promise<ApiResult<T>> {
    return request<T>({ action, ...params }, 'POST', options);
  },

  async verifySession(): Promise<ApiResult<any>> {
    if (!hasStoredAuthToken()) {
      await tryRefreshSession();
    }

    let res = await api.get('orabooks_frontend_context', {}, { clearAuthOnFailure: false });
    if (!res.error) {
      return res;
    }

    const refreshed = await tryRefreshSession();
    if (refreshed) {
      res = await api.get('orabooks_frontend_context', {}, { clearAuthOnFailure: false });
      if (!res.error) {
        return res;
      }
    }

    clearPersistedAuthTokens();
    return res;
  },

  refreshToken: () => api.post('orabooks_refresh_token'),

  establishSession: (token: string, refreshToken = '') => establishSession(token, refreshToken),

  // Auth
  login: (email: string, password: string) =>
    api.post('orabooks_login', { email, password }),
  register: (data: Record<string, any>) =>
    api.post('orabooks_register', data),
  oidcInitiate: (stateData: Record<string, unknown> = {}) => {
    const payload: Record<string, unknown> = {};
    if (Object.keys(stateData).length > 0) {
      payload.state_data = encodeOidcStateData(stateData);
    }
    return api.post('orabooks_oidc_initiate', payload);
  },
  oidcCallback: (code: string, state: string) =>
    api.post('orabooks_oidc_callback', { code, state }),
  twoFactorChallenge: (tempToken: string, otp: string, backup = '') =>
    api.post('orabooks_2fa_challenge', { temp_token: tempToken, otp_code: otp, backup_code: backup }),
  logout: () => api.post('orabooks_logout'),
  forgotPassword: (email: string) =>
    api.post('orabooks_forgot_password', { email }),
  resetPassword: (token: string, password: string) =>
    api.post('orabooks_reset_password', { token, password }),
  verifyEmailToken: (token: string) =>
    api.post('orabooks_verify_email_token', { token }),
  resendVerification: (email: string) =>
    api.post('orabooks_resend_verification', { email }),
  acceptInvite: (token: string) =>
    api.post('orabooks_accept_invite', { token }),
  previewInvite: (token: string) =>
    api.get('orabooks_preview_invite', { token }),
  setup2fa: () => api.post('orabooks_setup_2fa'),
  verify2faSetup: (otpCode: string) =>
    api.post('orabooks_verify_2fa_setup', { otp_code: otpCode }),
  disable2fa: (otpCode: string) =>
    api.post('orabooks_disable_2fa', { otp_code: otpCode }),
  regenerate2faBackupCodes: (otpCode: string) =>
    api.post('orabooks_regenerate_2fa_backup_codes', { otp_code: otpCode }),
  reveal2faBackupCodes: (otpCode: string) =>
    api.post('orabooks_reveal_2fa_backup_codes', { otp_code: otpCode }),
  twoFactorStatus: () => api.post('orabooks_2fa_status'),
  adminRecover2fa: (targetUserId: number, justification: string) =>
    api.post('orabooks_admin_2fa_recover', { target_user_id: targetUserId, justification }),
  getOrg2faPolicy: (orgId: number) =>
    api.post('orabooks_org_2fa_policy_get', { org_id: orgId }),
  setOrg2faPolicy: (orgId: number, require2fa: boolean) =>
    api.post('orabooks_org_2fa_policy_set', { org_id: orgId, require_2fa: require2fa ? '1' : '0' }),
  getPartnerInfo: () =>
    api.post('orabooks_get_partner_info'),
  partnerOnboarding: () =>
    api.get('orabooks_partner_onboarding'),
  partnerOnboardingComplete: () =>
    api.post('orabooks_partner_onboarding_complete'),
  requestReactivation: (orgId: number, reason: string) =>
    api.post('orabooks_request_reactivation', { org_id: orgId, reason }),
  partnerCodeCopied: (source = 'dashboard') =>
    api.post('orabooks_partner_code_copied', { source }),

  // Dashboard / Stats
  frontendContext: () => api.verifySession(),
  customerDashboard: () =>
    api.get('orabooks_customer_dashboard'),
  vendorDashboard: () =>
    api.get('orabooks_vendor_dashboard'),
  vendorsList: (orgId: number, filters: Record<string, unknown> = {}) =>
    api.get('orabooks_vendors_list', { org_id: orgId, ...filters }),
  vendorCreate: (orgId: number, data: Record<string, unknown>) =>
    api.post('orabooks_vendor_create', { org_id: orgId, ...data }),
  vendorUpdate: (orgId: number, vendorId: number, data: Record<string, unknown>) =>
    api.post('orabooks_vendor_update', { org_id: orgId, vendor_id: vendorId, ...data }),
  vendorCreditNoteCreate: (orgId: number, data: Record<string, unknown>) =>
    api.post('orabooks_vendor_credit_note_create', { org_id: orgId, ...data }),
  billsList: (orgId: number, filters: Record<string, unknown> = {}) =>
    api.get('orabooks_bills_list', { org_id: orgId, ...filters }),
  billCreate: (orgId: number, data: Record<string, unknown>) =>
    api.post('orabooks_bill_create', { org_id: orgId, ...data }),
  billSubmit: (orgId: number, billId: number) =>
    api.post('orabooks_bill_submit', { org_id: orgId, bill_id: billId }),
  billApprove: (orgId: number, billId: number) =>
    api.post('orabooks_bill_approve', { org_id: orgId, bill_id: billId }),
  billPost: (orgId: number, billId: number) =>
    api.post('orabooks_bill_post', { org_id: orgId, bill_id: billId }),
  billVoid: (orgId: number, billId: number, reason = '') =>
    api.post('orabooks_bill_void', { org_id: orgId, bill_id: billId, reason }),
  vendorPaymentRecord: (orgId: number, data: Record<string, unknown>) =>
    api.post('orabooks_vendor_payment_record', { org_id: orgId, ...data }),
  apAging: (orgId: number, asOfDate?: string) =>
    api.get('orabooks_ap_aging', { org_id: orgId, ...(asOfDate ? { as_of_date: asOfDate } : {}) }),
  inventoryDashboard: () =>
    api.get('orabooks_inventory_dashboard'),
  inventoryProductsList: (orgId: number, filters: Record<string, unknown> = {}) =>
    api.get('orabooks_inventory_products_list', { org_id: orgId, ...filters }),
  inventoryProductCreate: (orgId: number, data: Record<string, unknown>) =>
    api.post('orabooks_inventory_product_create', { org_id: orgId, ...data }),
  inventoryProductCreateUpload: (orgId: number, formData: FormData) => {
    formData.set('org_id', String(orgId));
    return uploadRequest('orabooks_inventory_product_create', formData);
  },
  inventoryAdjustStock: (
    orgId: number,
    productId: number,
    quantityChange: number,
    reason: string,
    note = ''
  ) =>
    api.post('orabooks_inventory_product_adjust', {
      org_id: orgId,
      product_id: productId,
      quantity_change: quantityChange,
      reason,
      note,
    }),
  inventoryMovements: (orgId: number, productId = 0, filters: Record<string, unknown> = {}) =>
    api.get('orabooks_inventory_movements', {
      org_id: orgId,
      ...(productId ? { product_id: productId } : {}),
      ...filters,
    }),
  inventoryLookupsList: (orgId: number, lookupType?: string) =>
    api.get('orabooks_inventory_lookups_list', {
      org_id: orgId,
      ...(lookupType ? { lookup_type: lookupType } : {}),
    }),
  inventoryLookupCreate: (orgId: number, lookupType: string, data: Record<string, unknown>) =>
    api.post('orabooks_inventory_lookup_create', {
      org_id: orgId,
      lookup_type: lookupType,
      ...data,
    }),
  inventoryLookupCode: (orgId: number, lookupType: string) =>
    api.get('orabooks_inventory_lookup_code', {
      org_id: orgId,
      lookup_type: lookupType,
    }),
  bankDashboard: () =>
    api.get('orabooks_bank_dashboard'),
  bankAccountsList: (orgId: number) =>
    api.get('orabooks_bank_accounts_list', { org_id: orgId }),
  bankAccountCreate: (orgId: number, data: Record<string, unknown>) =>
    api.post('orabooks_bank_account_create', { org_id: orgId, ...data }),
  bankTransactionsList: (
    orgId: number,
    bankAccountId = 0,
    filters: Record<string, unknown> = {}
  ) =>
    api.get('orabooks_bank_transactions_list', {
      org_id: orgId,
      ...(bankAccountId ? { bank_account_id: bankAccountId } : {}),
      ...filters,
    }),
  bankImportRows: (orgId: number, bankAccountId: number, rows: Array<Record<string, unknown>>) =>
    api.post('orabooks_bank_import_rows', {
      org_id: orgId,
      bank_account_id: bankAccountId,
      rows_json: JSON.stringify(rows),
    }),
  bankManualMatch: (
    orgId: number,
    bankTransactionId: number,
    transactionType: 'payment' | 'expense' | 'journal',
    transactionId: number
  ) =>
    api.post('orabooks_bank_match', {
      org_id: orgId,
      bank_transaction_id: bankTransactionId,
      transaction_type: transactionType,
      transaction_id: transactionId,
    }),
  bankSkip: (orgId: number, bankTransactionId: number, reason: string) =>
    api.post('orabooks_bank_skip', {
      org_id: orgId,
      bank_transaction_id: bankTransactionId,
      reason,
    }),
  bankReconcile: (
    orgId: number,
    bankAccountId: number,
    statementDate: string,
    endingBalance: number,
    force = false,
    note = ''
  ) =>
    api.post('orabooks_bank_reconcile', {
      org_id: orgId,
      bank_account_id: bankAccountId,
      statement_date: statementDate,
      ending_balance: endingBalance,
      force: force ? 1 : 0,
      note,
    }),
  reportsDashboard: () =>
    api.get('orabooks_reports_dashboard'),
  csvImportsDashboard: () =>
    api.get('orabooks_csv_imports_dashboard'),
  uploadCsv: (orgId: number, resourceType: string, file: File, idempotencyKey = '') => {
    const formData = new FormData();
    formData.set('org_id', String(orgId));
    formData.set('resource_type', resourceType);
    formData.set('csv_file', file);
    if (idempotencyKey) formData.set('idempotency_key', idempotencyKey);
    return uploadRequest('orabooks_csv_upload', formData);
  },
  csvImportGet: (orgId: number, importId: number) =>
    api.get('orabooks_csv_import_get', { org_id: orgId, import_id: importId }),
  csvImportConfirm: (
    orgId: number,
    importId: number,
    idempotencyKey: string,
    overrides?: { header_mapping?: Record<string, string>; rows?: Record<number, Record<string, unknown>> }
  ) =>
    api.post('orabooks_csv_import_confirm', {
      org_id: orgId,
      import_id: importId,
      idempotency_key: idempotencyKey,
      ...(overrides?.header_mapping ? { header_mapping: JSON.stringify(overrides.header_mapping) } : {}),
      ...(overrides?.rows ? { row_overrides: JSON.stringify(overrides.rows) } : {}),
    }),
  csvImportsList: (orgId: number) =>
    api.get('orabooks_csv_imports_list', { org_id: orgId }),
  teamDashboard: () =>
    api.get('orabooks_team_dashboard'),
  teamAccessSettingsGet: (orgId: number) =>
    api.get('orabooks_team_access_settings_get', { org_id: orgId }),
  teamAccessSettingsSave: (orgId: number, partnerCommissionForStaffViewer: boolean) =>
    api.post('orabooks_team_access_settings_save', {
      org_id: orgId,
      partner_commission_for_staff_viewer: partnerCommissionForStaffViewer ? 1 : 0,
    }),
  inviteTeamUser: (orgId: number, email: string, role: string) =>
    api.post('orabooks_invite_user', { org_id: orgId, email, role }),
  updateTeamRole: (orgId: number, userId: number, role: string) =>
    api.post('orabooks_update_role', { org_id: orgId, user_id: userId, role }),
  removeTeamUser: (orgId: number, userId: number) =>
    api.post('orabooks_remove_user', { org_id: orgId, user_id: userId }),
  resendTeamInvite: (orgId: number, inviteId: number) =>
    api.post('orabooks_resend_invite', { org_id: orgId, invite_id: inviteId }),
  cancelTeamInvite: (orgId: number, inviteId: number) =>
    api.post('orabooks_cancel_invite', { org_id: orgId, invite_id: inviteId }),
  attachmentsDashboard: () =>
    api.get('orabooks_attachments_dashboard'),
  uploadAttachment: (
    orgId: number,
    resourceType: string,
    resourceId: number,
    file: File,
    attachmentId = 0,
    idempotencyKey = ''
  ) => {
    const formData = new FormData();
    formData.set('org_id', String(orgId));
    formData.set('resource_type', resourceType);
    formData.set('resource_id', String(resourceId));
    formData.set('attachment_file', file);
    if (attachmentId) formData.set('attachment_id', String(attachmentId));
    if (idempotencyKey) formData.set('idempotency_key', idempotencyKey);
    return uploadRequest('orabooks_attachment_upload', formData);
  },
  attachmentGet: (orgId: number, attachmentId: number) =>
    api.get('orabooks_attachment_get', { org_id: orgId, attachment_id: attachmentId }),
  attachmentDelete: (orgId: number, attachmentId: number) =>
    api.post('orabooks_attachment_delete', { org_id: orgId, attachment_id: attachmentId }),
  attachmentsList: (orgId: number, resourceType = '', resourceId = 0) =>
    api.get('orabooks_attachments_list', {
      org_id: orgId,
      ...(resourceType ? { resource_type: resourceType } : {}),
      ...(resourceId ? { resource_id: resourceId } : {}),
    }),
  attachmentDownloadUrl: (orgId: number, attachmentId: number, versionId = 0) => {
    const cfg = getAjaxConfig();
    const token = getStoredToken();
    const qs = new URLSearchParams();
    qs.set('action', 'orabooks_attachment_download');
    qs.set('_ajax_nonce', cfg.nonce);
    qs.set('org_id', String(orgId));
    qs.set('attachment_id', String(attachmentId));
    if (versionId) qs.set('version_id', String(versionId));
    if (token) qs.set('orabooks_token', token);
    if (cfg.current_user_id) qs.set('current_user_id', String(cfg.current_user_id));
    return `${cfg.ajax_url}?${qs.toString()}`;
  },
  generateFinancialReport: (orgId: number, reportType: string, periodStart: string, periodEnd: string) =>
    api.get('orabooks_financial_report_generate', {
      org_id: orgId,
      report_type: reportType,
      period_start: periodStart,
      period_end: periodEnd,
    }),
  generateOperationalReport: (orgId: number, reportType: string, params: Record<string, string> = {}) =>
    api.get('orabooks_operational_report', {
      org_id: orgId,
      report_type: reportType,
      ...params,
    }),
  financialReportExport: (
    orgId: number,
    reportType: string,
    format: 'csv' | 'pdf',
    periodStart: string,
    periodEnd: string,
  ) =>
    api.post('orabooks_financial_report_export', {
      org_id: orgId,
      report_type: reportType,
      format,
      period_start: periodStart,
      period_end: periodEnd,
    }),
  operationalReportExport: (
    orgId: number,
    reportType: string,
    format: 'csv' | 'pdf',
    params: Record<string, string> = {},
  ) =>
    api.post('orabooks_operational_export', {
      org_id: orgId,
      report_type: reportType,
      format,
      ...params,
    }),
  financialReportSign: (orgId: number, snapshotId: number, boardApprovalReference = '') =>
    api.post('orabooks_financial_report_sign', {
      org_id: orgId,
      snapshot_id: snapshotId,
      board_approval_reference: boardApprovalReference,
    }),
  dashboardStats: () =>
    api.get('orabooks_dashboard_stats'),
  platformSettingsGet: () =>
    api.get('orabooks_platform_settings_get'),
  platformSettingsSave: (data: {
    block_same_email_domain: boolean;
    partner_commission_for_staff_viewer: boolean;
    audit_retention_days: number;
    jwt_expiry: number;
    refresh_token_expiry: number;
  }) =>
    api.post('orabooks_platform_settings_save', {
      block_same_email_domain: data.block_same_email_domain ? 1 : 0,
      partner_commission_for_staff_viewer: data.partner_commission_for_staff_viewer ? 1 : 0,
      audit_retention_days: data.audit_retention_days,
      jwt_expiry: data.jwt_expiry,
      refresh_token_expiry: data.refresh_token_expiry,
    }),
  deployChecks: () => api.get('orabooks_deploy_checks'),
  deployRepair: () => api.post('orabooks_deploy_repair', {}),
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
  commissionByCustomer: (partnerUserId?: number) =>
    api.get('orabooks_commission_by_customer', { partner_user_id: partnerUserId ?? 0 }),
  commissionReleaseHistory: (escrowId = 0, partnerUserId?: number) =>
    api.get('orabooks_commission_release_history', {
      partner_user_id: partnerUserId ?? 0,
      escrow_id: escrowId,
    }),

  // Org / Users
  listOrgs: (type = '', status = '') =>
    api.get('orabooks_list_orgs', { type, status }),
  suspendOrg: (orgId: number) =>
    api.post('orabooks_suspend_org', { org_id: orgId }),
  activateOrg: (orgId: number) =>
    api.post('orabooks_activate_org', { org_id: orgId }),
  changeOrgRegion: (orgId: number, region: string) =>
    api.post('orabooks_change_org_region', { org_id: orgId, region }),
  getOrgBySubdomain: (subdomain: string) =>
    api.get('orabooks_get_org_by_subdomain', { subdomain }),
  listUsers: () =>
    api.get('orabooks_list_users'),

  // Customers / Invoices
  customersList: (orgId = 0, filters = {}) =>
    api.get('orabooks_customers_list', { org_id: orgId, ...filters }),
  customerCreate: (orgId: number, data: Record<string, unknown>) =>
    api.post('orabooks_customer_create', { org_id: orgId, ...data }),
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
  invoiceSend: (orgId: number, invoiceId: number) =>
    api.post('orabooks_invoice_send', { org_id: orgId, invoice_id: invoiceId }),
  invoicePost: (orgId: number, invoiceId: number) =>
    api.post('orabooks_invoice_post', { org_id: orgId, invoice_id: invoiceId }),
  invoiceCancel: (orgId: number, invoiceId: number, reason = '') =>
    api.post('orabooks_invoice_cancel', { org_id: orgId, invoice_id: invoiceId, reason }),
  invoiceOverrideTax: (
    orgId: number,
    invoiceId: number,
    newTaxRate: number,
    reasonCode: string,
    jurisdiction = 'US'
  ) =>
    api.post('orabooks_invoice_override_tax', {
      org_id: orgId,
      invoice_id: invoiceId,
      new_tax_rate: newTaxRate,
      reason_code: reasonCode,
      jurisdiction,
    }, { clearAuthOnFailure: false }),
  invoiceClearTaxOverride: (orgId: number, invoiceId: number, jurisdiction = '') =>
    api.post('orabooks_invoice_clear_tax_override', {
      org_id: orgId,
      invoice_id: invoiceId,
      ...(jurisdiction ? { jurisdiction } : {}),
    }, { clearAuthOnFailure: false }),
  recordPayment: (data: Record<string, any>) =>
    api.post('orabooks_invoice_record_payment', data),

  // CoA / Audit
  coaGet: (orgId: number) =>
    api.get('orabooks_get_coa', { org_id: orgId }),
  coaExport: (orgId: number) => {
    const cfg = getAjaxConfig();
    const qs = new URLSearchParams();
    const token = getStoredToken();
    qs.set('action', 'orabooks_export_coa');
    qs.set('_ajax_nonce', cfg.nonce);
    qs.set('org_id', String(orgId));
    if (token) qs.set('orabooks_token', token);
    if (cfg.current_user_id) qs.set('current_user_id', String(cfg.current_user_id));
    window.location.href = `${cfg.ajax_url}?${qs.toString()}`;
  },
  coaCreate: (
    orgId: number,
    data: { code: string; name: string; type: string; normal_balance?: string }
  ) =>
    api.post('orabooks_coa_create', {
      org_id: orgId,
      code: data.code,
      name: data.name,
      type: data.type,
      ...(data.normal_balance ? { normal_balance: data.normal_balance } : {}),
    }),
  coaUpdate: (
    orgId: number,
    accountId: number,
    data: { code?: string; name?: string; type?: string; normal_balance?: string }
  ) =>
    api.post('orabooks_coa_update', {
      org_id: orgId,
      account_id: accountId,
      ...data,
    }),
  fiscalPeriodsList: (orgId: number) =>
    api.get('orabooks_fiscal_periods_list', { org_id: orgId }),
  fiscalPeriodClose: (orgId: number, periodId: number, closeType: 'soft' | 'hard', note = '', hardConfirm = false) =>
    api.post('orabooks_fiscal_period_close', {
      org_id: orgId,
      period_id: periodId,
      close_type: closeType,
      note,
      hard_confirm: hardConfirm ? 1 : 0,
    }),
  fiscalPeriodReopen: (orgId: number, periodId: number, reason: string) =>
    api.post('orabooks_fiscal_period_reopen', {
      org_id: orgId,
      period_id: periodId,
      reason,
    }),
  fiscalPeriodOverrideReopen: (orgId: number, periodId: number, justification: string) =>
    api.post('orabooks_fiscal_period_override_reopen', {
      org_id: orgId,
      period_id: periodId,
      justification,
    }),
  fiscalPeriodCreate: (orgId: number, data: { period_start: string; period_end: string }) =>
    api.post('orabooks_fiscal_period_create', {
      org_id: orgId,
      period_start: data.period_start,
      period_end: data.period_end,
    }),
  fiscalPeriodUpdate: (orgId: number, periodId: number, data: { period_start: string; period_end: string }) =>
    api.post('orabooks_fiscal_period_update', {
      org_id: orgId,
      period_id: periodId,
      period_start: data.period_start,
      period_end: data.period_end,
    }),
  taxListConfigs: (orgId: number) =>
    api.get('orabooks_tax_configs_list', { org_id: orgId }, { clearAuthOnFailure: false }),
  taxListJurisdictions: (orgId: number) =>
    api.get('orabooks_tax_jurisdictions_list', { org_id: orgId }, { clearAuthOnFailure: false }),
  taxLockStatus: (orgId: number, transactionDate?: string) =>
    api.get(
      'orabooks_tax_lock_status',
      {
        org_id: orgId,
        ...(transactionDate ? { transaction_date: transactionDate } : {}),
      },
      { clearAuthOnFailure: false }
    ),
  taxCalculate: (payload: Record<string, unknown>) =>
    api.post('orabooks_tax_calculate', payload, { clearAuthOnFailure: false }),
  taxSaveConfig: (orgId: number, data: Record<string, unknown>) =>
    api.post('orabooks_tax_save_config', { org_id: orgId, ...data }, { clearAuthOnFailure: false }),
  taxCreateSnapshot: (payload: Record<string, unknown>) =>
    api.post('orabooks_tax_snapshot', payload, { clearAuthOnFailure: false }),
  taxGetSnapshot: (orgId: number, transactionType: string, transactionId: number) =>
    api.get(
      'orabooks_tax_get_snapshot',
      {
        org_id: orgId,
        transaction_type: transactionType,
        transaction_id: transactionId,
      },
      { clearAuthOnFailure: false }
    ),
  taxListSnapshots: (orgId: number, limit = 25) =>
    api.get('orabooks_tax_snapshots_list', { org_id: orgId, limit }, { clearAuthOnFailure: false }),
  journalsList: (orgId: number, filters = {}) =>
    api.get('orabooks_get_journals', { org_id: orgId, ...filters }),
  journalGet: (orgId: number, journalId: number) =>
    api.get('orabooks_get_journal', { org_id: orgId, journal_id: journalId }),
  journalCreate: (
    orgId: number,
    data: {
      transaction_date?: string;
      source_type?: string;
      description?: string;
      lines?: { account_code: string; debit_amount?: number; credit_amount?: number; description?: string }[];
    }
  ) =>
    api.post('orabooks_create_journal', {
      org_id: orgId,
      transaction_date: data.transaction_date,
      source_type: data.source_type || 'manual',
      description: data.description || '',
      lines: data.lines ? JSON.stringify(data.lines) : '',
    }),
  approvalDashboard: (sort = 'age', order = 'ASC') =>
    api.get('orabooks_approval_dashboard', { sort, order }),
  approvalPolicyGet: (orgId: number) =>
    api.get('orabooks_approval_policy_get', { org_id: orgId }),
  approvalPolicySave: (orgId: number, data: Record<string, unknown>) =>
    api.post('orabooks_approval_policy_save', { org_id: orgId, ...data }),
  approvalDelegationsList: (orgId: number) =>
    api.get('orabooks_approval_delegations_list', { org_id: orgId }),
  approvalDelegationCreate: (
    orgId: number,
    data: { delegate_user_id: number; delegator_user_id?: number; starts_at: string; ends_at: string }
  ) => api.post('orabooks_approval_delegation_create', { org_id: orgId, ...data }),
  approvalDelegationRevoke: (orgId: number, delegationId: number) =>
    api.post('orabooks_approval_delegation_revoke', { org_id: orgId, delegation_id: delegationId }),
  aiReviewDashboard: () =>
    api.get('orabooks_ai_review_dashboard'),
  aiReviewList: (orgId: number, filters: { status?: string; limit?: number } = {}) =>
    api.get('orabooks_ai_review_list', { org_id: orgId, ...filters }),
  aiReviewResolve: (
    orgId: number,
    params: { queue_id?: number; journal_id?: number; resource_type?: string; resource_id?: number },
  ) => api.post('orabooks_ai_review_resolve', { org_id: orgId, ...params }),
  expensesDashboard: () =>
    api.get('orabooks_expenses_dashboard'),
  uploadExpenseReceipt: (orgId: number, file: File, idempotencyKey = '') => {
    const formData = new FormData();
    formData.set('org_id', String(orgId));
    formData.set('receipt_file', file);
    if (idempotencyKey) formData.set('idempotency_key', idempotencyKey);
    return uploadRequest('orabooks_expense_upload_receipt', formData);
  },
  expenseGet: (orgId: number, expenseId: number) =>
    api.get('orabooks_expense_get', { org_id: orgId, expense_id: expenseId }),
  expenseConfirm: (orgId: number, expenseId: number, idempotencyKey: string, editedFields: Record<string, unknown>) =>
    api.post('orabooks_expense_confirm', {
      org_id: orgId,
      expense_id: expenseId,
      idempotency_key: idempotencyKey,
      edited_fields: editedFields,
    }),
  expenseApprove: (orgId: number, expenseId: number) =>
    api.post('orabooks_expense_approve', { org_id: orgId, expense_id: expenseId }),
  expenseReject: (orgId: number, expenseId: number, reason: string) =>
    api.post('orabooks_expense_reject', { org_id: orgId, expense_id: expenseId, reason }),
  expensePost: (orgId: number, expenseId: number) =>
    api.post('orabooks_expense_post', { org_id: orgId, expense_id: expenseId }),
  expenseOverrideTax: (
    orgId: number,
    expenseId: number,
    newTaxRate: number,
    reasonCode: string,
    jurisdiction = 'US'
  ) =>
    api.post('orabooks_expense_override_tax', {
      org_id: orgId,
      expense_id: expenseId,
      new_tax_rate: newTaxRate,
      reason_code: reasonCode,
      jurisdiction,
    }),
  expenseClearTaxOverride: (orgId: number, expenseId: number, jurisdiction = 'US') =>
    api.post('orabooks_expense_clear_tax_override', {
      org_id: orgId,
      expense_id: expenseId,
      jurisdiction,
    }),
  classificationRun: (recordType: string, recordId: number, async = true) =>
    api.post('orabooks_classification_run', { record_type: recordType, record_id: recordId, async: async ? 1 : 0 }),
  classificationApply: (recordType: string, recordId: number) =>
    api.post('orabooks_classification_apply', { record_type: recordType, record_id: recordId }),
  classificationOverride: (recordType: string, recordId: number, accountCode: string, taxRate?: number) =>
    api.post('orabooks_classification_override', {
      record_type: recordType,
      record_id: recordId,
      account_code: accountCode,
      ...(taxRate != null ? { tax_rate: taxRate } : {}),
    }),
  voiceDashboard: () =>
    api.get('orabooks_voice_dashboard'),
  uploadVoice: (orgId: number, file: File, idempotencyKey = '') => {
    const formData = new FormData();
    formData.set('org_id', String(orgId));
    formData.set('voice_file', file);
    if (idempotencyKey) formData.set('idempotency_key', idempotencyKey);
    return uploadRequest('orabooks_voice_upload', formData);
  },
  voiceGet: (orgId: number, voiceId: number) =>
    api.get('orabooks_voice_get', { org_id: orgId, voice_id: voiceId }),
  voiceConfirm: (orgId: number, voiceId: number, idempotencyKey: string, editedFields: Record<string, unknown>) =>
    api.post('orabooks_voice_confirm', {
      org_id: orgId,
      voice_id: voiceId,
      idempotency_key: idempotencyKey,
      edited_fields: editedFields,
    }),
  submitJournal: (journalId: number) =>
    api.post('orabooks_submit_journal', { journal_id: journalId }),
  approveJournal: (journalId: number, mfaOtp?: string) =>
    api.post('orabooks_approve_journal', {
      journal_id: journalId,
      ...(mfaOtp ? { mfa_otp: mfaOtp } : {}),
    }),
  resubmitJournal: (journalId: number) =>
    api.post('orabooks_resubmit_journal', { journal_id: journalId }),
  rejectJournal: (journalId: number, reason: string) =>
    api.post('orabooks_reject_journal', { journal_id: journalId, reason }),
  postJournal: (journalId: number) =>
    api.post('orabooks_post_journal', { journal_id: journalId }),
  reverseJournal: (orgId: number, journalId: number, reason: string) =>
    api.post('orabooks_reverse_journal', { org_id: orgId, journal_id: journalId, reason }),
  auditLogs: (filters = {}) =>
    api.get('orabooks_get_audit_logs', filters),
  exportAuditLogs: (filters = {}) => {
    const cfg = getAjaxConfig();
    const qs = new URLSearchParams();
    const token = getStoredToken();
    qs.set('action', 'orabooks_export_audit_logs');
    qs.set('_ajax_nonce', cfg.nonce);
    if (token) qs.set('orabooks_token', token);
    if (cfg.current_user_id) qs.set('current_user_id', String(cfg.current_user_id));
    Object.entries(filters).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') qs.set(k, String(v));
    });
    window.location.href = `${cfg.ajax_url}?${qs.toString()}`;
  },
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
  notificationsList: (params: Record<string, string | number | boolean> = {}) =>
    api.get('orabooks_notifications_list', params),
  notificationsUnreadCount: () =>
    api.get('orabooks_notifications_unread_count'),
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
  notificationPolicySave: (orgId: number, data: Record<string, any>) =>
    api.post('orabooks_notification_admin_policy_save', {
      org_id: orgId,
      monthly_budget: data.monthly_budget,
      mandatory_event_types: data.mandatory_event_types,
      prohibited_channels: data.prohibited_channels,
      retention_override_days: data.retention_override_days,
      max_escalation_attempts: data.max_escalation_attempts,
      escalation_fallback_chain: data.escalation_fallback_chain,
    }),
  notificationProviderHealth: (orgId: number) =>
    api.get('orabooks_notification_admin_provider_health', { org_id: orgId }),
  notificationAuditExport: async (orgId: number, startDate: string, endDate: string) => {
    const cfg = getAjaxConfig();
    const qs = new URLSearchParams();
    const token = getStoredToken();
    qs.set('action', 'orabooks_notification_admin_audit_export');
    qs.set('_ajax_nonce', cfg.nonce);
    qs.set('org_id', String(orgId));
    qs.set('start_date', startDate);
    qs.set('end_date', endDate);
    if (token) qs.set('orabooks_token', token);
    if (cfg.current_user_id) qs.set('current_user_id', String(cfg.current_user_id));

    try {
      const res = await fetch(`${cfg.ajax_url}?${qs.toString()}`);
      const json = await res.json();
      if (!res.ok || json?.success === false) {
        return { error: extractError(json, 'Audit export failed.') };
      }
      return { data: json?.data ?? json };
    } catch (error) {
      return { error: error instanceof Error ? error.message : 'Audit export failed.' };
    }
  },

  // Exports
  exportRequest: (exportType: string, format: 'csv' | 'pdf', parameters?: Record<string, any>) =>
    api.post('orabooks_export_request', {
      export_type: exportType,
      format,
      parameters: parameters ? JSON.stringify(parameters) : '',
    }),

  // Async queue / observability (admin)
  asyncQueueStats: (filters?: Record<string, any>) => api.get('orabooks_async_queue_stats', filters),
  asyncQueueReplay: (jobId: number) => api.post('orabooks_async_queue_replay', { job_id: jobId }),
  asyncQueueDiscard: (jobId: number) => api.post('orabooks_async_queue_discard', { job_id: jobId }),
  asyncQueueCancel: (jobId: number) => api.post('orabooks_async_queue_cancel', { job_id: jobId }),
  asyncQueuePollNow: () => api.post('orabooks_async_queue_poll_now'),
  webhookSettingsGet: (filters?: Record<string, any>) => api.get('orabooks_webhook_settings_get', filters || {}),
  webhookSettingsSave: (urls: string, extra: Record<string, any> = {}) =>
    api.post('orabooks_webhook_settings_save', { urls, ...extra }),
  observabilityDashboard: (hours = 24) =>
    api.post('orabooks_observability_dashboard', { hours }),
  eventBusDeadLetters: () =>
    api.post('orabooks_eventbus_dead_letters'),
  eventBusPollNow: () =>
    api.post('orabooks_eventbus_poll_now'),
  eventBusReplay: (deadLetterId: number) =>
    api.post('orabooks_eventbus_replay', { dead_letter_id: deadLetterId }),
  eventBusReplayAll: () =>
    api.post('orabooks_eventbus_replay_all'),
  eventBusDiscard: (deadLetterId: number) =>
    api.post('orabooks_eventbus_discard', { dead_letter_id: deadLetterId }),
  securityDashboard: (hours = 24) =>
    api.post('orabooks_security_dashboard', { hours }),
  securityVerifyControls: () =>
    api.post('orabooks_security_verify_controls'),
  exportsList: (page = 1) => api.get('orabooks_exports_list', { page }),
  exportCancel: (exportId: number) => api.post('orabooks_export_cancel', { export_id: exportId }),
  exportDownload: (exportId: number) => api.get('orabooks_export_download', { export_id: exportId }),
  csvImportsDashboard: () => api.get('orabooks_csv_imports_dashboard'),

  commissionConfigGet: () => api.get('orabooks_commission_config'),
  commissionConfigUpdate: (data: Record<string, any>) =>
    api.post('orabooks_commission_update_config', {
      ...data,
      yearly_percentages:
        typeof data.yearly_percentages === 'string'
          ? data.yearly_percentages
          : JSON.stringify(data.yearly_percentages ?? []),
    }),
};
