import { getTenantDomainSuffix } from '@/lib/utils';

const TOKEN_KEY = 'orabooks_token';
const REFRESH_TOKEN_KEY = 'orabooks_refresh_token';
const REDIRECT_GUARD_KEY = 'orabooks_auth_redirect_ts';
const REDIRECT_COOLDOWN_MS = 4000;

export function clearStoredAuthTokens() {
  window.localStorage.removeItem(TOKEN_KEY);
  window.localStorage.removeItem(REFRESH_TOKEN_KEY);
}

export function normalizeWpAppPath(path: string, fallback = '/dashboard/') {
  const trimmed = path.trim();
  if (!trimmed) {
    return fallback;
  }
  if (trimmed.startsWith('http')) {
    return trimmed;
  }
  const withLeadingSlash = trimmed.startsWith('/') ? trimmed : `/${trimmed}`;
  return withLeadingSlash.endsWith('/') ? withLeadingSlash : `${withLeadingSlash}/`;
}

/** Full-page navigation to a WP route + hash route without adding history noise. */
export function replaceAppLocation(wpPath: string, hashRoute = '') {
  const base = normalizeWpAppPath(wpPath);
  const hash = hashRoute
    ? `#${hashRoute.startsWith('/') ? hashRoute : `/${hashRoute}`}`
    : '';
  window.location.replace(`${base}${hash}`);
}

export function redirectToOrgSubdomain(
  subdomain: string,
  wpPath = '/dashboard/',
  hashRoute = '/dashboard'
) {
  const suffix = getTenantDomainSuffix();
  const path = normalizeWpAppPath(wpPath);
  const hash = hashRoute
    ? `#${hashRoute.startsWith('/') ? hashRoute : `/${hashRoute}`}`
    : '';
  window.location.replace(`${window.location.protocol}//${subdomain}${suffix}${path}${hash}`);
}

export function redirectAfterAuth(data: {
  needs_tier_selection?: boolean;
  redirect_to?: string;
  subdomain?: string;
  is_partner?: boolean;
}) {
  clearRedirectGuard();

  if (data?.needs_tier_selection) {
    replaceAppLocation('/tier-selection/', '/tier-selection');
    return;
  }

  const redirectTo = String(data?.redirect_to || '').trim();
  if (redirectTo.startsWith('http')) {
    const url = new URL(redirectTo);
    const hashRoute = url.hash?.replace(/^#/, '') || '/dashboard';
    if (url.hash) {
      window.location.replace(redirectTo);
      return;
    }
    window.location.replace(`${redirectTo.replace(/\/$/, '')}/#/dashboard`);
    return;
  }

  if (redirectTo.startsWith('#/')) {
    replaceAppLocation('/dashboard/', redirectTo.slice(1));
    return;
  }

  if (redirectTo.startsWith('/')) {
    const wpPath = normalizeWpAppPath(redirectTo);
    const hashRoute = redirectTo.replace(/\/$/, '') || '/dashboard';
    if (data?.subdomain) {
      redirectToOrgSubdomain(data.subdomain, wpPath, hashRoute);
      return;
    }
    replaceAppLocation(wpPath, hashRoute);
    return;
  }

  if (data?.subdomain) {
    const wpPath = data.is_partner ? '/partner-onboarding/' : '/dashboard/';
    const hashRoute = data.is_partner ? '/partner-onboarding' : '/dashboard';
    redirectToOrgSubdomain(data.subdomain, wpPath, hashRoute);
    return;
  }

  replaceAppLocation('/dashboard/', '/dashboard');
}

export function redirectToLogin() {
  const last = Number(window.sessionStorage.getItem(REDIRECT_GUARD_KEY) || 0);
  const now = Date.now();
  if (now - last < REDIRECT_COOLDOWN_MS) {
    return false;
  }
  window.sessionStorage.setItem(REDIRECT_GUARD_KEY, String(now));

  const loginUrl = (window as any).orabooks_ajax?.login_url;
  if (loginUrl) {
    window.location.replace(`${loginUrl}#/login`);
    return true;
  }

  replaceAppLocation('/login/', '/login');
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

  const hash = '#/login';
  if (redirectTo.includes('#')) {
    window.location.replace(redirectTo);
  } else {
    window.location.replace(`${redirectTo.replace(/\/?$/, '/')}#/login`);
  }
}

export function clearRedirectGuard() {
  window.sessionStorage.removeItem(REDIRECT_GUARD_KEY);
}

export function syncInitialHashRoute(route: string) {
  const normalized = route.startsWith('/') ? route : `/${route}`;
  const hash = window.location.hash;
  if (!hash || hash === '#') {
    const base = `${window.location.pathname}${window.location.search}`;
    window.history.replaceState(null, '', `${base}#${normalized}`);
  }
}
