# SaaS-01 Local Business Management Blueprint

Version: 1.0  
Date: 2026-07-01  
Product Type: Multi-tenant B2B SaaS  
Primary Goal: 10+ year recurring revenue from local SMB operations

## Product Thesis
Build a daily-usage system for small businesses where leaving the platform is costly because invoices, stock history, customer dues, branch performance, and accounting workflows are deeply integrated.

## Stage 1 - Business Planning
- Problem validation: interview at least 50 shop owners, accountants, and supervisors.
- Market research: segment by retail, pharmacy, wholesale, and service businesses.
- Competitor analysis: evaluate local POS/accounting tools on onboarding speed, branch support, and reporting depth.
- Target audience: owner, manager, cashier, accountant, warehouse operator.
- Pricing model: BDT 499/1499/3999 per month by features and branch count.
- Subscription plans: Starter, Growth, Pro, Enterprise.
- Revenue model: subscription + onboarding fee + premium report add-ons.
- Business goals: ARR milestone by quarter, churn less than 4 percent monthly.
- KPIs: active orgs, retained orgs, paid conversion, DSO improvement, NRR.
- Product vision: become operating system for local business finance and stock.
- Product roadmap: MVP 6 months, growth 18 months, scale 36 months.
- MVP scope: inventory, invoicing, dues tracking, daily reports.
- Future scope: AI demand forecast, procurement intelligence, partner marketplace.

## Stage 2 - Requirements
### Functional
- Login and role-based dashboard.
- Sales invoice, returns, customer due, payment collection.
- Purchases, supplier payable, stock movement.
- Expense entry and approval.
- Reports: profit summary, stock, receivable aging.
### Non-functional
- Performance: P95 API less than 300ms on common reads.
- Security: strong RBAC, MFA for high-risk actions.
- Availability: 99.9 percent target.
- Backup: daily full + point-in-time.
- Scalability: horizontal worker and cache scaling.
- Multi tenancy: strict org isolation.
- Accessibility: WCAG 2.1 AA for admin UI.

## Stage 3 - Product Design
- User flow by role: owner, manager, cashier, accountant, warehouse.
- Wireframe and mockups in Figma.
- Mobile-first operator screens and desktop analytics screens.
- Responsive design and keyboard-friendly data tables.
- Optional dark mode.
- Accessibility checks in design QA.

## Stage 4 - System Architecture
- Architecture: modular monolith (NestJS) with extraction boundaries.
- Event-driven workflow using outbox and domain events.
- DDD-inspired modules: sales, purchase, inventory, finance, reporting.
- Clean architecture for testability.
- CQRS-lite for read-heavy dashboards.
- Hexagonal adapters for payment, SMS, storage, and export services.

## Stage 5 - Multi Tenant Design
- Initial model: shared database with mandatory org_id scoping.
- Isolation:
  - Data: row-level tenant scope.
  - Storage: tenant folder/prefix.
  - Cache: tenant key prefix.
  - Queue: tenant partition key.
- Future option: schema per tenant for enterprise tier.

## Stage 6 - Database Design
- ERD: organizations, users, customers, suppliers, products, stock_ledgers, invoices, payments, expenses.
- Constraints: unique invoice number per org, immutable posted ledgers.
- Indexes: org_id plus date/status composite indexes.
- Partition: event and audit tables by month.
- Migrations: forward-only with rollback script.
- Soft delete for non-financial master data.
- Audit tables for approvals and critical changes.

## Stage 7 - Authentication
- Email/password login with optional OTP.
- JWT access token + rotating refresh token.
- MFA for admin and payout/write-off operations.
- Password reset and email verification.
- Session/device management with forced logout.

## Stage 8 - Authorization
- RBAC roles: owner, admin, manager, staff, viewer.
- Permission matrix: read, write, delete, export, approve.
- Least privilege defaults and quarterly access review.

## Stage 9 - Backend Modules
- User and organization management.
- Billing/subscription module.
- Accounting-lite ledger module.
- Sales and purchase modules.
- Inventory and warehouse module.
- Expense and approval workflow.
- Reports, notifications, audit, settings, API gateway.

## Stage 10 - API
- REST v1 with OpenAPI.
- Validation and standardized error envelopes.
- Rate limits by tenant and user.
- Pagination/filter/sort/search conventions.
- Idempotency keys for payment and posting endpoints.

## Stage 11 - Frontend
- Role-aware dashboard.
- Fast forms with autosave and validation.
- Table-heavy workflows and chart views.
- Wizard for onboarding and initial stock setup.
- Toasts, loading skeletons, and error boundaries.

## Stage 12 - File Storage
- Invoice PDFs, receipts, and business documents.
- Object storage abstraction for S3/R2/GCS.
- Signed URL and malware scan hooks.

## Stage 13 - Search
- Global full-text search for customers, invoices, products.
- Advanced filters by branch, date, status, amount.

## Stage 14 - Background Processing
- Queue jobs: invoice PDF, exports, reminders, OCR import.
- Scheduler: daily close checklist, due reminders, stock alerts.
- Retry with exponential backoff and dead-letter queue.

## Stage 15 - Notifications
- Email and WhatsApp reminder flows.
- SMS for payment due and stock threshold.
- Webhooks for integrations.

## Stage 16 - Security
- TLS, secure headers, CSP, and strict cookie policy.
- CSRF, XSS, SQLi defenses.
- Secrets vault and rotation policy.
- WAF, rate limits, bot defense.
- Audit logs for posting, refund, and write-off actions.
- Incident response and breach communication runbook.

## Stage 17 - Logging
- Request logs with trace_id.
- Error logs with stack and correlation.
- Security logs for login anomaly and permission denial.
- Business logs for invoice lifecycle and collections.

## Stage 18 - Monitoring
- Health checks for app, DB, Redis, queue, storage.
- Metrics: latency, error rate, queue lag, failed jobs.
- SLO dashboard and alerting policy.

## Stage 19 - Testing
- Unit tests for business rules.
- Integration tests for posting and stock consistency.
- API tests for contracts.
- E2E tests for invoice-to-payment workflows.
- Load tests on billing period peaks.
- Security and accessibility tests.

## Stage 20 - DevOps
- GitHub flow, PR review, code owners.
- Docker and compose for local and staging parity.
- CI/CD with test, lint, scan, deploy, rollback.
- Environment and secret separation.

## Stage 21 - Cloud Infrastructure
- Start: single VPS with managed Postgres backup.
- Growth: separate worker node and Redis node.
- Scale: load balancer, read replicas, CDN.

## Stage 22 - Billing
- Free trial and coupon support.
- Monthly/yearly subscriptions.
- Invoices, payment retries, refund flow.
- Usage billing for extra branches/users/storage.

## Stage 23 - Compliance
- GDPR-ready data export/delete process.
- SOC2 control roadmap.
- Privacy policy, terms, retention policy.

## Stage 24 - Documentation
- Developer: API docs, architecture, DB schema, deploy guide.
- User: owner guide, cashier guide, accountant guide, FAQ.

## Stage 25 - Maintenance
- Weekly bug and support triage.
- Monthly security patch window.
- Quarterly performance and cost optimization.
- Annual architecture review.

## Business Modules Coverage
- CRM, Sales, Purchase, Inventory, Payroll-lite integration, HRM-lite integration, POS-ready connectors, Banking, Budget, Tax/VAT, OCR, AI Assistant roadmap.

## Enterprise Features Coverage
- Multi organization, multi currency, multi branch, multi warehouse, multi language.
- Approval workflows, audit trail, event bus, domain events, webhooks.
- Public API, SDK roadmap, plugin system roadmap.
- White-label and partner portal for enterprise plan.
- Feature flags, backup and restore, disaster recovery drills.

## Superadmin Management Model
- Tenant lifecycle: active, suspended, read-only, archived.
- Plan entitlement and quota controls.
- Global security center: anomalous login, API abuse, policy drift.
- Support impersonation only with consent token + immutable audit.

## Event Bus and Async Governance
- Event envelope: event_id, tenant_id, aggregate_id, type, version, trace_id, occurred_at.
- Outbox pattern guarantees no lost business events.
- Consumers must be idempotent.
- Retry/backoff, DLQ, replay approval workflow.
- Operations dashboard for lag, dead letters, and failed handlers.

## Implementation Timeline
- Phase 0 (0-4 weeks): research, architecture decisions, UX validation.
- Phase 1 (0-6 months): MVP build and paid pilot.
- Phase 2 (6-18 months): growth modules, analytics, hardening.
- Phase 3 (18-36 months): enterprise expansion and ecosystem.

## Final KPI Targets
- MRR growth >= 12 percent month-over-month in first 12 months.
- Logo churn <= 4 percent monthly, NRR >= 105 percent by month 18.
- Uptime >= 99.9 percent, critical incident rate <= 1 per quarter.
