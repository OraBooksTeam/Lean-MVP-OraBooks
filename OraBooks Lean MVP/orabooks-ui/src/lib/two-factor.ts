/** TOTP / backup-code normalization (matches backend OraBooks_Secrets::normalize_totp_code). */

export const TOTP_CODE_LENGTH = 6;

export function normalizeTotpCode(value: string) {
  return value.replace(/\D/g, '').slice(0, TOTP_CODE_LENGTH);
}

export function normalizeBackupCode(value: string) {
  return value.trim().replace(/\s+/g, '').toUpperCase();
}

export function isValidTotpCode(value: string) {
  return normalizeTotpCode(value).length === TOTP_CODE_LENGTH;
}

export function isValidBackupCode(value: string) {
  return normalizeBackupCode(value).length >= 8;
}

/** Routes reachable before org-mandatory 2FA setup completes. */
export const TWO_FA_SETUP_EXEMPT_ROUTES = new Set(['/security/2fa', '/profile']);

export function routeRequires2faSetupRedirect(route: string, needsSetup: boolean) {
  if (!needsSetup) {
    return false;
  }
  return !TWO_FA_SETUP_EXEMPT_ROUTES.has(route);
}
