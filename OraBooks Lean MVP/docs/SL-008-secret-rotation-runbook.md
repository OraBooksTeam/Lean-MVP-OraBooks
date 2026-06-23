# Secrets & TLS — Operations Runbook

This runbook covers secret rotation, TLS provisioning, and database encryption for OraBooks Lean MVP production deployments.

## Secret sources (priority order)

1. **`orabooks_load_secret` filter** — integrate HashiCorp Vault, AWS Secrets Manager, or GCP Secret Manager.
2. **`ORABOOKS_SECRETS_FILE`** — path to a JSON file (e.g. sidecar-mounted secrets):

```json
{
 "jwt_secret": "64+ character random string",
 "encryption_key": "32+ character random string",
 "google_oauth_client_id": "...",
 "google_oauth_client_secret": "..."
}
```

3. **Environment variables** — `ORABOOKS_JWT_SECRET`, `ORABOOKS_ENCRYPTION_KEY`, etc.
4. **Encrypted WordPress options** — auto-generated on first use if nothing else is set (development only).

Never commit secrets to source control.

## Rotation schedule (recommended)

| Secret | Interval | Grace / notes |
|--------|----------|----------------|
| JWT signing key | 30 days | 24h grace via `OraBooks_Secrets::rotate_secret('jwt_secret', $new)` |
| Encryption key | 90 days | Re-encrypt at-rest fields during maintenance window |
| Database password | 90 days | Coordinate with hosting / RDS |
| Google OAuth client secret | On compromise | Update env + `OraBooks_Secrets::clear_secrets_cache` |

Monthly cron `orabooks_security_secret_rotation_reminder` notifies platform admins when JWT/encryption rotation is overdue (90-day policy in `OraBooks_Security`).

### JWT rotation procedure

```php
$new = bin2hex(random_bytes(32));
OraBooks_Secrets::rotate_secret('jwt_secret', $new);
```

- Old tokens remain valid until `orabooks_jwt_secret_grace_until` (default 24 hours).
- Audit event: `secret_rotated`.
- Users must refresh sessions after grace period ends.

### Encryption key rotation

1. Generate a new key: `ORABOOKS_ENCRYPTION_KEY` or `rotate_secret('encryption_key', $new)`.
2. Run a maintenance script to re-encrypt 2FA secrets and other `enc:` fields (planned tooling).
3. Keep the previous key available until re-encryption completes.

## TLS (HTTPS) provisioning

OraBooks enforces HTTPS in production via:

- `OraBooks_Secrets::maybe_enforce_https` — HTTP → HTTPS 301 redirect
- `Strict-Transport-Security` header when SSL is active
- Weekly `orabooks_security_header_check` cron — certificate expiry warnings

### Infrastructure checklist

- [ ] Terminate TLS 1.2+ at load balancer or web server (nginx, Apache, Cloudflare, Azure Front Door).
- [ ] Use **Let's Encrypt** (certbot / ACME) or enterprise CA certificates.
- [ ] Auto-renew certificates before expiry (30-day warning logged as `tls_certificate_expiring`).
- [ ] Ensure `home_url` and `siteurl` use `https://`.
- [ ] Run deploy checks: `OraBooks_DeployChecks::run` — `tls_certificate` row must pass.

## Database TLS

Production bootstrap **blocks OraBooks** if database TLS is not detected.

Configure at least one of:

```php
// wp-config.php
define('MYSQL_CLIENT_FLAGS', MYSQLI_CLIENT_SSL);
define('MYSQL_SSL_CA', '/path/to/ca.pem');
// optional client cert:
define('MYSQL_SSL_CERT', '/path/to/client-cert.pem');
define('MYSQL_SSL_KEY', '/path/to/client-key.pem');
```

Or set environment variable `ORABOOKS_DB_SSL=1` and verify with `mysqli` `Ssl_cipher` session status.

Custom hosts may use:

```php
add_filter('orabooks_database_tls_verified', function ($verified, $indicators) {
 return true; // only if your platform provides TLS another way
}, 10, 2);
```

## Bootstrap failure behavior

If required secrets or DB TLS fail validation in production:

- `OraBooks_Secrets::is_ready` returns `false`
- `orabooks_init` does not load auth, accounting, or other modules
- Admin notice shown to `manage_options` users
- All `orabooks_*` AJAX actions return HTTP 503

Fix configuration and reload PHP / clear opcode cache.

## Logging rules

- Secrets are never logged in plaintext — use `OraBooks_Secrets::mask_value` / `redact_sensitive`.
- Events: `secret_accessed`, `secret_rotated`, `secrets_bootstrap_failed`, `tls_certificate_expiring`, `database_tls_not_configured`.

## Tests

```bash
cd "OraBooks Lean MVP/tests"
php vendor/bin/phpunit --configuration phpunit-secrets.xml
```
