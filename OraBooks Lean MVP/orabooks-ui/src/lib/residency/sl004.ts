/** — multi-tenant subdomain, org isolation, and data residency (mirrors backend helpers). */

export const RESIDENCY_REGIONS = [
 { id: 'us-east', label: 'US East' },
 { id: 'eu-west-1', label: 'EU West' },
 { id: 'ap-southeast-1', label: 'Asia Pacific' },
] as const;

export type ResidencyRegionId = (typeof RESIDENCY_REGIONS)[number]['id'];

export const RESERVED_SUBDOMAINS = new Set([
 'admin',
 'api',
 'app',
 'support',
 'billing',
 'partner',
 'orabooks',
 'www',
 'root',
]);

export const NETWORK_AUTH_ROUTES = new Set([
 '/',
 '/login',
 '/register',
 '/reset-password',
 '/verify-email',
 '/accept-invite',
 '/tier-selection',
]);

const TENANT_SUBDOMAIN_PREFIXES = new Set(['www', 'mail', 'admin']);

function normalizeHostname(hostname: string) {
 return hostname.trim.toLowerCase.split(':')[0];
}

/** Extract tenant subdomain from the current host (mirrors OraBooks_Auth::detect_subdomain_from_host). */
export function parseSubdomainFromHost(hostname = window.location.hostname): string {
 const host = normalizeHostname(hostname);
 const parts = host.split('.');

 if (parts.length >= 3) {
 const candidate = parts[0];
 if (!TENANT_SUBDOMAIN_PREFIXES.has(candidate)) {
 return candidate;
 }
 return '';
 }

 // Local dev: mycompany.localhost, mycompany.test
 if (parts.length === 2) {
 const tld = parts[1];
 const isDevTld = tld === 'localhost' || tld.endsWith('local') || tld.endsWith('test');
 if (isDevTld && !TENANT_SUBDOMAIN_PREFIXES.has(parts[0])) {
 return parts[0];
 }
 }

 return '';
}

/** Base domain without tenant prefix (mirrors orabooks_get_tenant_base_domain). */
export function getTenantBaseDomain(hostname = window.location.hostname): string {
 const host = normalizeHostname(hostname);
 const parts = host.split('.');

 if (parts.length >= 3 && !TENANT_SUBDOMAIN_PREFIXES.has(parts[0])) {
 return parts.slice(1).join('.');
 }

 return host;
}

/** Suffix shown after the chosen subdomain (e.g. ".orabooks.app"). */
export function getTenantDomainSuffix(hostname = window.location.hostname): string {
 return `.${getTenantBaseDomain(hostname)}`;
}

export function isNetworkAuthHost(hostname = window.location.hostname): boolean {
 return parseSubdomainFromHost(hostname) === '';
}

export function normalizeSubdomain(value: string): string {
 return value.trim.toLowerCase;
}

/** Client-side subdomain format check (mirrors orabooks_validate_subdomain). */
export function validateSubdomainFormat(subdomain: string): string | null {
 const normalized = normalizeSubdomain(subdomain);
 if (!normalized) {
 return 'Subdomain is required.';
 }
 if (RESERVED_SUBDOMAINS.has(normalized)) {
 return 'This subdomain is reserved.';
 }
 if (!/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/.test(normalized)) {
 return 'Subdomain must be 3–63 characters, lowercase letters, numbers, and hyphens (no leading/trailing hyphen).';
 }
 return null;
}

export function formatRegionLabel(regionId?: string | null): string {
 const id = (regionId || 'us-east').trim.toLowerCase;
 return RESIDENCY_REGIONS.find((region) => region.id === id)?.label ?? id;
}

export function canSelectResidencyRegion(tier?: string | null): boolean {
 return normalizeSubdomain(tier || '') === 'enterprise';
}

export function buildOrgUrl(subdomain: string, path = '/dashboard/'): string {
 const slug = normalizeSubdomain(subdomain);
 let normalizedPath = path.startsWith('/') ? path: `/${path}`;
 if (!normalizedPath.endsWith('/')) {
 normalizedPath = `${normalizedPath}/`;
 }

 const base = getTenantBaseDomain;
 const protocol = window.location.protocol;

 if (!slug) {
 return `${protocol}//${window.location.host}${normalizedPath}`;
 }

 return `${protocol}//${slug}.${base}${normalizedPath}`;
}

export function buildSubdomainPreview(subdomain: string): string {
 const slug = normalizeSubdomain(subdomain);
 if (!slug) {
 return `https://your-org${getTenantDomainSuffix}`;
 }
 return buildOrgUrl(slug, '/dashboard/');
}

/**
 * Redirect authenticated users to their org tenant host when on the wrong subdomain
 * (mirrors orabooks_maybe_redirect_to_org_subdomain for React workspace routes).
 */
export function resolveTenantWorkspaceRedirect(
 orgSubdomain: string | undefined | null,
 route: string
): string | null {
 const org = normalizeSubdomain(orgSubdomain || '');
 if (!org) {
 return null;
 }

 const normalizedRoute = route.replace(/\/$/, '') || '/dashboard';
 if (NETWORK_AUTH_ROUTES.has(normalizedRoute)) {
 return null;
 }

 const current = parseSubdomainFromHost;

 // Profile and 2FA setup remain reachable on the network host during onboarding.
 if (current === '' && (normalizedRoute === '/profile' || normalizedRoute === '/security/2fa')) {
 return null;
 }

 if (current === org) {
 return null;
 }

 const path = normalizedRoute.startsWith('/') ? normalizedRoute: `/${normalizedRoute}`;
 return buildOrgUrl(org, `${path}/`);
}
