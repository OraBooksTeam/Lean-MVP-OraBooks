export function cn(...classes: (string | undefined | false)[]) {
  return classes.filter(Boolean).join(' ');
}

const TENANT_SUBDOMAIN_PREFIXES = new Set(['www', 'mail', 'admin']);

/** Suffix shown after the chosen subdomain (e.g. ".example.com"). */
export function getTenantDomainSuffix(hostname = window.location.hostname): string {
  const host = hostname.toLowerCase().split(':')[0];
  const parts = host.split('.');

  if (parts.length >= 3 && !TENANT_SUBDOMAIN_PREFIXES.has(parts[0])) {
    return '.' + parts.slice(1).join('.');
  }

  return '.' + host;
}
