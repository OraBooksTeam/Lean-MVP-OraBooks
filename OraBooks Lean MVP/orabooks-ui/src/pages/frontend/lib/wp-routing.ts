/** Map React route keys to WordPress page slugs when they differ. */
const WP_PATH_ALIASES: Record<string, string> = {
  '/partner-onboarding': '/onboarding',
  '/partner/onboarding': '/onboarding',
  '/security/2fa': '/security-2fa',
};

const APP_ROUTE_ALIASES: Record<string, string> = {
  '/security-2fa': '/security/2fa',
};

export function normalizeAppRoute(route: string): string {
  let path = route.trim();
  if (path.startsWith('#')) {
    path = path.replace(/^#\/?/, '/');
  }
  if (!path.startsWith('/')) {
    path = `/${path}`;
  }
  path = path.replace(/\/$/, '') || '/dashboard';
  if (APP_ROUTE_ALIASES[path]) {
    return APP_ROUTE_ALIASES[path];
  }
  return WP_PATH_ALIASES[path] ?? path;
}

/** Build a clean WordPress URL (trailing slash, optional query string). */
export function toWpUrl(path: string): string {
  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path;
  }

  const queryIndex = path.indexOf('?');
  const pathname = queryIndex >= 0 ? path.slice(0, queryIndex) : path;
  const search = queryIndex >= 0 ? path.slice(queryIndex) : '';

  let normalized = pathname.startsWith('/') ? pathname : `/${pathname}`;
  const routeKey = normalizeAppRoute(normalized);
  normalized = WP_PATH_ALIASES[routeKey] ?? routeKey;
  if (!normalized.endsWith('/')) {
    normalized = `${normalized}/`;
  }

  return `${normalized}${search}`;
}

export function getCurrentAppRoute(): string {
  return normalizeAppRoute(window.location.pathname);
}

export function replaceSearchParams(params: URLSearchParams) {
  const qs = params.toString();
  const url = qs ? `${window.location.pathname}?${qs}` : `${window.location.pathname}`;
  window.history.replaceState(null, '', url);
}

export function getSearchParam(key: string): string {
  return new URLSearchParams(window.location.search).get(key) || '';
}

/** Redirect legacy hash URLs (#/dashboard) to clean paths (/dashboard/). */
export function migrateLegacyHashUrl() {
  const hash = window.location.hash;
  if (!hash.startsWith('#/')) {
    migrateLegacyPathAliases();
    return;
  }

  const target = toWpUrl(hash.slice(1));
  const qs = window.location.search;
  window.location.replace(`${target}${qs}`);
}

/** Redirect legacy partner onboarding paths to /onboarding/. */
export function migrateLegacyPathAliases() {
  const route = normalizeAppRoute(window.location.pathname);
  const canonical = WP_PATH_ALIASES[route];
  if (!canonical || canonical === route) {
    return;
  }

  window.location.replace(toWpUrl(canonical));
}
