# SaaS-02 School and Coaching Management Blueprint

Version: 1.0  
Date: 2026-07-01

## Product Thesis
Create a sticky school operating platform where attendance, fees, results, guardian communication, and institution compliance are unified. High retention comes from deep historical student records and recurring monthly operations.

## Stage 1 - Business Planning
- Validate problems with principals, admin officers, accountants, teachers, and guardians.
- Segment market: school, madrasa, coaching, and multi-campus institutions.
- Competitor benchmarking: fee collection, communication, attendance quality, reporting depth.
- Pricing: per campus base + per student slab + communication packs.
- Subscription plans: Campus Lite, Campus Pro, Campus Enterprise.
- Revenue model: subscription + setup/training + notification pack overages.
- KPIs: fee collection efficiency, guardian engagement, institution retention, support SLA.
- MVP scope: student records, attendance, fee billing, guardian alerts.
- Future scope: exam engine, LMS connectors, transport and hostel modules.

## Stage 2 - Requirements
### Functional
- Admissions, student profile, class/section management.
- Attendance with teacher and admin workflows.
- Fee invoicing, due tracking, and collection receipts.
- Result publication and guardian portal.
- Dashboard and reporting by campus/class.
### Non-functional
- Performance: teacher attendance entry under 2 seconds.
- Security: strong student data access controls.
- Availability: 99.9 percent on school days.
- Backup: daily full plus hourly transaction snapshots.
- Scalability: handles admission season spikes.
- Multi tenancy and accessibility as first-class requirements.

## Stage 3 - Product Design
- Persona flows: principal, teacher, accountant, guardian, student.
- Figma artifacts: admin console, teacher mobile, guardian portal.
- Responsive and low-bandwidth friendly UX.
- Accessibility and readability priority for parents.

## Stage 4 - System Architecture
- Modular monolith with bounded modules: academics, finance, communication.
- Event-driven actions for attendance alerts, fee reminders, result publish.
- CQRS read model for dashboards and parent notifications.

## Stage 5 - Multi Tenant Design
- Tenant = institution/campus group.
- Isolation: student data, files, cache keys, queues by tenant.
- Optional enterprise path: schema per institution.

## Stage 6 - Database Design
- Core entities: students, guardians, classes, attendance, fees, invoices, exams, results.
- Constraints: student roll uniqueness per class/session.
- Partition large attendance and message logs.
- Audit history for marks/fee modifications.

## Stage 7 - Authentication
- Admin and teacher login with MFA option.
- Guardian auth with OTP and device trust controls.
- Session and device revocation panel.

## Stage 8 - Authorization
- Roles: owner, admin, principal, teacher, accountant, staff, viewer.
- Permission matrix for attendance edit, fee write, result publish, export, approve.

## Stage 9 - Backend Modules
- User and org modules.
- Admissions, attendance, fee management, exam/result modules.
- Notification, audit, reporting, settings, API.

## Stage 10 - API
- REST v1 and webhook events for external integrations.
- Idempotency for fee payment and result publish endpoints.
- Rate limiting for public guardian APIs.

## Stage 11 - Frontend
- Role-based dashboards.
- Teacher quick attendance UI.
- Guardian portal with dues and notices.
- Robust table/report interfaces.

## Stage 12 - File Storage
- Student photos, documents, report cards, circulars.
- Signed URLs and retention policy.

## Stage 13 - Search
- Global search by student, guardian, ID, class, invoice number.
- Advanced filtering for attendance and fee recovery campaigns.

## Stage 14 - Background Processing
- Jobs: attendance anomaly check, fee reminder batch, report generation.
- Scheduler: due reminders, daily attendance summary, result release queue.
- DLQ and replay governance.

## Stage 15 - Notifications
- Email/SMS/WhatsApp for due and attendance alerts.
- In-app notifications for teachers and admins.

## Stage 16 - Security
- Child/minor data protection baseline.
- Encrypted sensitive fields and strict access controls.
- CSP, CSRF, XSS and SQL injection defenses.
- Secrets management and quarterly rotation.

## Stage 17 - Logging
- Request, error, audit, and guardian communication logs.

## Stage 18 - Monitoring
- Uptime, API latency, queue lag, notification failures.
- SLO dashboard and automated alert routing.

## Stage 19 - Testing
- Unit/integration/API/E2E tests for admissions, attendance, fees, results.
- Load test during admission and billing peak.
- Security and accessibility tests.

## Stage 20 - DevOps
- GitHub Actions pipeline with quality gates.
- Docker-based environments and blue/green deployment strategy.

## Stage 21 - Cloud Infrastructure
- VPS start, then split DB and worker nodes.
- Scale path to LB + read replicas + CDN.

## Stage 22 - Billing
- Monthly/yearly campus billing.
- Student slab billing and messaging pack overage.
- Coupon and annual contract support.

## Stage 23 - Compliance
- Privacy policy, terms, data retention and consent policy.
- GDPR/SOC2 readiness roadmap.

## Stage 24 - Documentation
- Developer API docs and deployment runbooks.
- User manuals for principal, teacher, accountant, guardian.

## Stage 25 - Maintenance
- Monthly release cycle and emergency patch lane.
- Data quality audits and support metrics review.

## Business Modules
- CRM-lite, Sales/Billing, HRM-lite, Payroll-lite integration, Budget, Tax-ready exports, OCR roadmap, AI assistant roadmap.

## Enterprise Features
- Multi organization, multi campus/branch, multi language, approval workflow, audit trail, event bus, domain events, webhooks, public API, feature flags, backup and DR.

## Superadmin and Governance
- Tenant policy controls, communication budget controls, impersonation with consent and immutable logs.

## Event Bus and Async
- Outbox pattern, idempotent consumers, retry with jitter, DLQ triage, replay authorization.

## KPI Targets
- Fee collection rate >= 92 percent.
- Guardian app/portal adoption >= 60 percent.
- Institution churn <= 3 percent monthly.
- Uptime >= 99.9 percent.
