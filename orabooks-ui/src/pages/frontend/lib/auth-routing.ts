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

export function redirectToLogin() {
  const last = Number(window.sessionStorage.getItem(REDIRECT_GUARD_KEY) || 0);
  const now = Date.now();
  if (now - last < REDIRECT_COOLDOWN_MS) {
    return false;
  }
  window.sessionStorage.setItem(REDIRECT_GUARD_KEY, String(now));
  replaceAppLocation('/login/', '/login');
  return true;
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
