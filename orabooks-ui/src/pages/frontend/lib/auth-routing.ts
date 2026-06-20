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
  const next = `${window.location.pathname}${qs ? `?${qs}` : ''}${window.location.hash}`;
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

  return `${target.origin}${target.pathname}${target.search}${target.hash}`;
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
  const destination = appendCrossOriginAuthParams(
    `${window.location.protocol}//${subdomain}${suffix}${path}${hash}`
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
    replaceAppLocation('/tier-selection/', '/tier-selection');
    return;
  }

  const redirectTo = String(data?.redirect_to || '').trim();
  if (redirectTo.startsWith('http')) {
    if (redirectTo.includes('#')) {
      window.location.replace(appendCrossOriginAuthParams(redirectTo));
      return;
    }
    const target = new URL(redirectTo);
    const hashRoute = target.pathname.includes('login')
      ? '/login'
      : target.pathname.includes('tier-selection')
        ? '/tier-selection'
        : '/dashboard';
    const destination = appendCrossOriginAuthParams(
      `${target.origin}${target.pathname}${target.search}#${hashRoute}`
    );
    window.location.replace(destination);
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

const AUTH_HASH_ROUTES = new Set(['/login', '/register', '/reset-password', '/verify-email']);

export function syncInitialHashRoute(route: string) {
  const normalized = route.startsWith('/') ? route : `/${route}`;
  const currentHash = window.location.hash.replace(/^#/, '') || '';
  const isAuthWpRoute = AUTH_HASH_ROUTES.has(normalized);
  const hashIsAuthRoute = AUTH_HASH_ROUTES.has(currentHash);

  if (!isAuthWpRoute && hashIsAuthRoute) {
    // e.g. /dashboard/ + #/login → use #/dashboard
  } else if (currentHash && currentHash !== normalized) {
    return;
  } else if (currentHash === normalized) {
    return;
  }

  const base = `${window.location.pathname}${window.location.search}`;
  window.history.replaceState(null, '', `${base}#${normalized}`);
}
