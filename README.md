# OraBooks Lean MVP

Accounting SaaS — a brand of **TaxOra LLC (USA)**.

A modular accounting platform built as a WordPress plugin with a React PWA frontend. Supports partners, commissions, multi-tenant organizations, and GAAP-compliant double-entry accounting.

---

## Structure

```
OraBooks Lean MVP/
├── orabooks.php              # WordPress plugin entry point
├── includes/                 # PHP backend (auth, orgs, 2FA, RBAC, API, etc.)
├── orabooks-ui/              # React frontend (Vite + TypeScript + Tailwind)
│   ├── src/pages/admin/      # WordPress admin SPA
│   ├── src/pages/frontend/   # Public-facing SPA
│   └── scripts/              # Build & deploy scripts
├── assets/react/             # Built frontend (output, committed)
└── tests/                    # PHP unit tests
```

## Quick Start (Development)

### Backend

1. Install WordPress locally (Local WP, XAMPP, or Docker)
2. Copy `OraBooks Lean MVP/` into `wp-content/plugins/`
3. Activate the plugin from WordPress admin
4. Ensure required DB tables are created (activate triggers schema migration)

### Frontend

```bash
cd "OraBooks Lean MVP/orabooks-ui"
npm install
npm run dev         # Vite dev server with HMR
npm run typecheck   # TypeScript type checking
npm run build       # Production build → ../assets/react/
```

## Deployment

### Prerequisites

- Node.js 20+ and npm
- SSH/SFTP access to your managed WordPress host

### Configure

```bash
cd "OraBooks Lean MVP/orabooks-ui"
cp .env.deploy.example .env.deploy
# Edit .env.deploy with your host details
```

### Deploy

```bash
npm run deploy               # Build + confirm + deploy via SSH
npm run deploy:dry-run       # Build only, preview files
npm run deploy:copy-to-plugin # Copy build output to plugin directory (no remote deploy)
```

### GitHub Actions (CI/CD)

A workflow file is provided at `.github/workflows/deploy.yml`. To enable it:

1. Add the following secrets to your GitHub repo:
   - `DEPLOY_HOST` — SFTP hostname
   - `DEPLOY_PORT` — SFTP port (default 22)
   - `DEPLOY_USER` — SFTP username
   - `DEPLOY_KEY` — SSH private key (base64-encoded PEM)
   - `DEPLOY_TARGET_DIR` — Remote path to `wp-content/plugins/OraBooks Lean MVP/`

2. Push to `main` — the workflow will build and deploy automatically.

> **Important:** WP Engine uses Git-based deployment, not SFTP. If you use WP Engine, connect via their Git push workflow instead, or replace the deploy step in the workflow with the [wpengine-deploy action](https://github.com/marketplace/actions/wp-engine-deploy).

### Host-specific notes

| Host          | Deploy method          | Cache invalidation                        |
|---------------|------------------------|-------------------------------------------|
| WP Engine     | Git push to their repo | Automatic on deploy; or REST API PURGE    |
| Kinsta        | SFTP/SSH               | Purge via MyKinsta dashboard or API       |
| Cloudways      | SSH + rsync / SFTP     | Breeze cache: curl to purge               |
| SiteGround    | SFTP                   | SG Optimizer: purge via plugin or API     |

After deploying new assets, purge your host's cache to ensure users receive the updated JS/CSS.

### Rollback

The deploy script does not include automatic rollback. To revert:

1. Check out the previous commit: `git checkout <prior-commit-sha> -- assets/react/`
2. Re-deploy: `npm run deploy`

For CI, re-run a prior workflow run to deploy its artifact.

## Testing

```bash
php OraBooks\ Lean\ MVP/tests/vendor/bin/phpunit \
  --configuration OraBooks\ Lean\ MVP/tests/phpunit.xml \
  --testsuite="OraBooks Auth Tests"
```

## License

ISC — © TaxOra LLC. See [LICENSE](LICENSE).
