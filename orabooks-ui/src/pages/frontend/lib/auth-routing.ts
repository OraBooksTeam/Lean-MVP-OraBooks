import { getTenantDomainSuffix } from '@/lib/utils';
import { normalizeAppRoute, toWpUrl } from './wp-routing';

const TOKEN_KEY = 'orabooks_token';
const REFRESH_TOKEN_KEY = 'orabooks_refresh_token';
const REDIRECT_GUARD_KEY = 'orabooks_auth_redirect_ts';
const LOGOUT_QUERY_FLAG = 'logged_out';
const LOGOUT_SESSION_FLAG = 'orabooks_logged_out';
const REDIRECT_COOLDOWN_MS = 4000;

export function clearStoredAuthTokens() {
  window.localStorage.removeItem(TOKEN_KEY);
  window.localStorage.removeItem(REFRESH_TOKEN_KEY);
}

export function normalizeWpAppPath(path: string, fallback = '/dashboard/') {
  return toWpUrl(path || fallback);
}

/** Full-page navigation to a WordPress route. */
export function replaceAppLocation(wpPath: string) {
  window.location.replace(normalizeWpAppPath(wpPath));
}

export function absorbAuthTokensFromUrl() {
  const params = new URLSearchParams(window.location.search);
  const token = params.get('ob_t');
  const refresh = params.get('ob_rt');
  if (!token) {
    return false;
  }

  window.localStorage.setItem(TOKEN_KEY, token);
  if (refresh) {
    window.localStorage.setItem(REFRESH_TOKEN_KEY, refresh);
  }

  params.delete('ob_t');
  params.delete('ob_rt');
  const qs = params.toString();
  const next = `${window.location.pathname}${qs ? `?${qs}` : ''}`;
  window.history.replaceState(null, '', next);
  clearRedirectGuard();
  return true;
}

function appendCrossOriginAuthParams(url: string) {
  const token = window.localStorage.getItem(TOKEN_KEY);
  if (!token) {
    return url;
  }

  const target = new URL(url, window.location.href);
  if (target.origin === window.location.origin) {
    return url;
  }

  target.searchParams.set('ob_t', token);
  const refresh = window.localStorage.getItem(REFRESH_TOKEN_KEY);
  if (refresh) {
    target.searchParams.set('ob_rt', refresh);
  }

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
}) {
  clearRedirectGuard();

  if (data?.needs_tier_selection) {
    replaceAppLocation('/tier-selection/');
    return;
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
    const wpPath = data.is_partner ? '/onboarding/' : '/dashboard/';
    redirectToOrgSubdomain(data.subdomain, wpPath);
    return;
  }

  replaceAppLocation('/dashboard/');
}

export function redirectToLogin(force = false) {
  if (!force) {
    const last = Number(window.sessionStorage.getItem(REDIRECT_GUARD_KEY) || 0);
    const now = Date.now();
    if (now - last < REDIRECT_COOLDOWN_MS) {
      return false;
    }
  }
  window.sessionStorage.setItem(REDIRECT_GUARD_KEY, String(Date.now()));

  const loginUrl = (window as any).orabooks_ajax?.login_url;
  if (loginUrl) {
    window.location.replace(normalizeWpAppPath(loginUrl));
    return true;
  }

  replaceAppLocation('/login/');
  return true;
}

export function getNetworkLoginUrl() {
  return (window as any).orabooks_ajax?.login_url || '/login/';
}

export async function performLogout(logoutRequest: () => Promise<{ data?: { redirect_to?: string }; error?: string }>) {
  clearStoredAuthTokens();
  clearRedirectGuard();

  let redirectTo = getNetworkLoginUrl();
  try {
    const res = await logoutRequest();
    if (!res.error && res.data?.redirect_to) {
      redirectTo = res.data.redirect_to;
    }
  } catch {
    // Still redirect to login even if the AJAX call fails.
  }

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
