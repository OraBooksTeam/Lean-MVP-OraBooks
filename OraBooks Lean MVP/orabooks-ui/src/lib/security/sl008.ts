/** — client-side TLS and outbound URL guards (mirrors backend SSRF/HTTPS policy). */

const LOCAL_HOSTS = new Set(['localhost', '127.0.0.1', '::1']);

export type DeployCheck = {
 id: string;
 label: string;
 ok: boolean;
 detail?: string;
};

export const SL008_DEPLOY_CHECK_IDS = [
 'jwt_secret',
 'encryption_key',
 'database_tls',
 'tls_certificate',
] as const;

export function isLocalDevHost(hostname: string) {
 const host = hostname.trim.toLowerCase;
 return LOCAL_HOSTS.has(host) || host.endsWith('.local') || host.endsWith('.test');
}

export function shouldEnforceTls {
 const cfg = (window as any).orabooks_ajax || {};
 if (typeof cfg.require_tls === 'boolean') {
 return cfg.require_tls;
 }

 return !isLocalDevHost(window.location.hostname);
}

export function isSecureOrigin(url: URL) {
 if (url.protocol === 'https:') {
 return true;
 }
 return url.protocol === 'http:' && isLocalDevHost(url.hostname);
}

/** Redirect HTTP → HTTPS on non-local hosts ( §5.3). */
export function enforceHttpsIfRequired {
 if (typeof window === 'undefined') {
 return;
 }

 const { protocol, hostname, href } = window.location;
 if (protocol === 'https:' || isLocalDevHost(hostname) || !shouldEnforceTls) {
 return;
 }

 window.location.replace(href.replace(/^http:/i, 'https:'));
}

export function upgradeToHttpsIfRequired(url: URL) {
 if (url.protocol === 'https:' || isLocalDevHost(url.hostname) || !shouldEnforceTls) {
 return url;
 }

 const upgraded = new URL(url.toString);
 upgraded.protocol = 'https:';
 return upgraded;
}

export function parseUrlLines(text: string) {
 return text
.split(/\r?\n/)
.map((line) => line.trim)
.filter(Boolean);
}

/** Validate webhook endpoint lines — HTTPS required except localhost dev URLs. */
export function validateHttpsWebhookLines(text: string) {
 const errors: string[] = [];

 for (const line of parseUrlLines(text)) {
 let url: URL;
 try {
 url = new URL(line);
 } catch {
 errors.push(`Invalid URL: ${line}`);
 continue;
 }

 if (!isSecureOrigin(url)) {
 errors.push(`HTTPS required ( / SSRF policy): ${line}`);
 }
 }

 return {
 valid: errors.length === 0,
 errors,
 };
}

export function filterSl008DeployChecks(checks: DeployCheck[] = []) {
 const wanted = new Set<string>(SL008_DEPLOY_CHECK_IDS);
 return checks.filter((check) => wanted.has(check.id));
}

export function sl008DeployChecksOk(checks: DeployCheck[] = []) {
 const sl008 = filterSl008DeployChecks(checks);
 return sl008.length > 0 && sl008.every((check) => check.ok);
}
