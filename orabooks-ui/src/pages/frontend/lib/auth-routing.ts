import { getTenantDomainSuffix } from '@/lib/utils';
import { clearPersistedAuthTokens } from '../api';
import { normalizeAppRoute, toWpUrl } from './wp-routing';

const PENDING_INVITE_TOKEN_KEY = 'orabooks_pending_invite_token';

export function storePendingInviteToken(token: string) {
  if (token) {
    window.sessionStorage.setItem(PENDING_INVITE_TOKEN_KEY, token);
  }
}

export function consumePendingInviteToken() {
  const token = window.sessionStorage.getItem(PENDING_INVITE_TOKEN_KEY) || '';
  window.sessionStorage.removeItem(PENDING_INVITE_TOKEN_KEY);
  return token;
}

export function getAcceptInviteUrl(token: string) {
  const cfg = (window as any).orabooks_ajax || {};
  const base = typeof cfg.accept_invite_url === 'string' && cfg.accept_invite_url.trim() !== ''
    ? cfg.accept_invite_url
    : toWpUrl('/accept-invite/');
  const target = new URL(base, window.location.href);
  target.searchParams.set('token', token);
  target.hash = '';
  return `${target.origin}${target.pathname}${target.search}`;
}
const TIER_SELECTION_TOKEN_KEY = 'orabooks_tier_selection_token';
const REDIRECT_GUARD_KEY = 'orabooks_auth_redirect_ts';
const LOGOUT_QUERY_FLAG = 'logged_out';
const AUTH_RESET_QUERY_FLAG = 'auth_reset';
const SESSION_EXPIRED_QUERY_FLAG = 'session_expired';
const LOGOUT_SESSION_FLAG = 'orabooks_logged_out';
const REDIRECT_COOLDOWN_MS = 4000;

export function clearStoredAuthTokens() {
  clearPersistedAuthTokens();
}

export function storeTierSelectionToken(token: string) {
  window.sessionStorage.setItem(TIER_SELECTION_TOKEN_KEY, token);
}

export function getTierSelectionToken() {
  return window.sessionStorage.getItem(TIER_SELECTION_TOKEN_KEY) || '';
}

export function clearTierSelectionToken() {
  window.sessionStorage.removeItem(TIER_SELECTION_TOKEN_KEY);
}

export function normalizeWpAppPath(path: string, fallback = '/dashboard/') {
  return toWpUrl(path || fallback);
}

/** Full-page navigation to a WordPress route. */
export function replaceAppLocation(wpPath: string) {
  window.location.replace(normalizeWpAppPath(wpPath));
}

export async function absorbAuthTokensFromUrl() {
  if (isLogoutLanding()) {
    return false;
  }

  const params = new URLSearchParams(window.location.search);
  const token = params.get('ob_t');
  const refresh = params.get('ob_rt');
  if (!token) {
    return false;
  }

  window.localStorage.setItem(TOKEN_KEY, token);

  params.delete('ob_t');
  params.delete('ob_rt');
  const qs = params.toString();
  const next = `${window.location.pathname}${qs ? `?${qs}` : ''}`;
  window.history.replaceState(null, '', next);
  clearRedirectGuard();

  const { api } = await import('../api');
  try {
    await api.establishSession(token, refresh || '');
  } catch {
    // Token is already in localStorage; session cookies may still be set on retry.
  }

  return true;
}

function appendCrossOriginAuthParams(url: string) {
  if (isLogoutLanding()) {
    return url;
  }

  try {
    const target = new URL(url, window.location.href);
    if (target.searchParams.get(LOGOUT_QUERY_FLAG) === '1'
      || target.searchParams.get(AUTH_RESET_QUERY_FLAG) === '1'
      || target.searchParams.get(SESSION_EXPIRED_QUERY_FLAG) === '1') {
      return url;
    }
  } catch {
    // Fall through to token handling below.
  }

  const token = window.localStorage.getItem(TOKEN_KEY);
  if (!token) {
    return url;
  }

  const target = new URL(url, window.location.href);
  if (target.origin === window.location.origin) {
    return url;
  }

  target.searchParams.set('ob_t', token);
  target.hash = '';
  return `${target.origin}${target.pathname}${target.search}`;
}

export function redirectToOrgSubdomain(subdomain: string, wpPath = '/dashboard/') {
  const suffix = getTenantDomainSuffix();
  const path = normalizeWpAppPath(wpPath);
  const destination = appendCrossOriginAuthParams(
    `${window.location.protocol}//${subdomain}${suffix}${path}`
  );
  window.location.replace(destination);
}

export function redirectAfterAuth(data: {
  needs_tier_selection?: boolean;
  redirect_to?: string;
  subdomain?: string;
  is_partner?: boolean;
  is_platform_admin?: boolean;
}) {
  clearRedirectGuard();
  clearLogoutSessionFlag();
  clearTierSelectionToken();

  if (data?.needs_tier_selection) {
    if (data?.tier_selection_token) {
      storeTierSelectionToken(String(data.tier_selection_token));
    }
    window.location.replace(normalizeWpAppPath(getNetworkAuthUrl('tier-selection')));
    return;
  }

  if (data?.is_platform_admin) {
    const adminUrl =
      (window as any).orabooks_ajax?.platform_admin_url
      || String(data?.redirect_to || '').trim();
    if (adminUrl) {
      window.location.replace(adminUrl);
      return;
    }
  }

  const redirectTo = String(data?.redirect_to || '').trim();
  if (redirectTo.startsWith('http')) {
    const target = new URL(redirectTo);
    target.hash = '';
    window.location.replace(appendCrossOriginAuthParams(target.toString()));
    return;
  }

  if (redirectTo.startsWith('#/')) {
    replaceAppLocation(redirectTo.slice(1));
    return;
  }

  if (redirectTo.startsWith('/')) {
    const wpPath = normalizeWpAppPath(redirectTo);
    if (data?.subdomain) {
      redirectToOrgSubdomain(data.subdomain, wpPath);
      return;
    }
    window.location.replace(wpPath);
    return;
  }

  if (data?.subdomain) {
    const wpPath = data.is_partner ? '/partner/onboarding/' : '/dashboard/';
    redirectToOrgSubdomain(data.subdomain, wpPath);
    return;
  }

  replaceAppLocation('/dashboard/');
}

export function redirectToLogin(force = false, resetAuth = false, reason: 'session_expired' | 'auth_reset' = 'session_expired') {
  if (!force) {
    const last = Number(window.sessionStorage.getItem(REDIRECT_GUARD_KEY) || 0);
    const now = Date.now();
    if (now - last < REDIRECT_COOLDOWN_MS) {
      return false;
    }
  }
  window.sessionStorage.setItem(REDIRECT_GUARD_KEY, String(Date.now()));

  if (resetAuth) {
    markLogoutLanding();
  }

  const loginUrl = (window as any).orabooks_ajax?.login_url;
  let destination = loginUrl ? normalizeWpAppPath(loginUrl) : normalizeWpAppPath(getNetworkLoginUrl());
  if (resetAuth) {
    destination = reason === 'auth_reset'
      ? appendAuthResetFlag(destination)
      : appendSessionExpiredFlag(destination);
  }

  window.location.replace(destination);
  return true;
}

export function getNetworkLoginUrl() {
  return (window as any).orabooks_ajax?.login_url || '/login/';
}

export function getAuthResetLoginUrl() {
  return normalizeWpAppPath(appendAuthResetFlag(getNetworkLoginUrl()));
}

type NetworkAuthPage = 'login' | 'register' | 'reset-password' | 'verify-email' | 'tier-selection' | 'accept-invite';

export function getNetworkAuthUrl(page: NetworkAuthPage) {
  const cfg = (window as any).orabooks_ajax || {};
  const configured =
    page === 'login'
      ? cfg.login_url
      : page === 'register'
        ? cfg.register_url
        : page === 'reset-password'
          ? cfg.reset_password_url
          : page === 'verify-email'
            ? cfg.verify_email_url
            : page === 'accept-invite'
              ? cfg.accept_invite_url
            : cfg.tier_selection_url;

  if (typeof configured === 'string' && configured.trim() !== '') {
    return configured;
  }

  return toWpUrl(`/${page}/`);
}

export function isLogoutLanding() {
  const params = new URLSearchParams(window.location.search);
  return (
    params.get(LOGOUT_QUERY_FLAG) === '1'
    || params.get(AUTH_RESET_QUERY_FLAG) === '1'
    || params.get(SESSION_EXPIRED_QUERY_FLAG) === '1'
    || window.sessionStorage.getItem(LOGOUT_SESSION_FLAG) === '1'
  );
}

export function markLogoutLanding() {
  window.sessionStorage.setItem(LOGOUT_SESSION_FLAG, '1');
}

export function clearLogoutSessionFlag() {
  window.sessionStorage.removeItem(LOGOUT_SESSION_FLAG);
}

function appendSessionExpiredFlag(url: string) {
  try {
    const target = new URL(url, window.location.href);
    target.searchParams.set(SESSION_EXPIRED_QUERY_FLAG, '1');
    target.hash = '';
    return `${target.origin}${target.pathname}${target.search}`;
  } catch {
    const separator = url.includes('?') ? '&' : '?';
    return `${url}${separator}${SESSION_EXPIRED_QUERY_FLAG}=1`;
  }
}

function appendAuthResetFlag(url: string) {
  try {
    const target = new URL(url, window.location.href);
    target.searchParams.set(AUTH_RESET_QUERY_FLAG, '1');
    target.hash = '';
    return `${target.origin}${target.pathname}${target.search}`;
  } catch {
    const separator = url.includes('?') ? '&' : '?';
    return `${url}${separator}${AUTH_RESET_QUERY_FLAG}=1`;
  }
}

function appendLogoutFlag(url: string) {
  try {
    const target = new URL(url, window.location.href);
    target.searchParams.set(LOGOUT_QUERY_FLAG, '1');
    target.hash = '';
    return `${target.origin}${target.pathname}${target.search}`;
  } catch {
    const separator = url.includes('?') ? '&' : '?';
    return `${url}${separator}${LOGOUT_QUERY_FLAG}=1`;
  }
}

export async function performLogout(logoutRequest: () => Promise<{ data?: { redirect_to?: string }; error?: string }>) {
  clearRedirectGuard();
  markLogoutLanding();

  let redirectTo = appendLogoutFlag(getNetworkLoginUrl());
  try {
    const res = await logoutRequest();
    if (!res.error && res.data?.redirect_to) {
      redirectTo = appendLogoutFlag(res.data.redirect_to);
    }
  } catch {
    // Still redirect to login even if the AJAX call fails.
  }

  clearStoredAuthTokens();
  window.location.replace(normalizeWpAppPath(redirectTo));
}

export function clearRedirectGuard() {
  window.sessionStorage.removeItem(REDIRECT_GUARD_KEY);
}

/** @deprecated Hash routes removed — no-op kept for backwards compatibility. */
export function syncInitialHashRoute(_route: string) {
  // Clean URLs only: WordPress path is the route.
}

export { normalizeAppRoute };
