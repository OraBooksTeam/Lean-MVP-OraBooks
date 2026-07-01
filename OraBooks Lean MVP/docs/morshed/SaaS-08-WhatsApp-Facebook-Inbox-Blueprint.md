# SaaS-08 WhatsApp and Facebook Inbox Blueprint

Version: 1.0  
Date: 2026-07-01

## Product Thesis
Provide an omnichannel conversation operations platform that improves response speed, enforces SLA, and converts social conversations into trackable revenue opportunities.

## Stage 1 - Business Planning
- Validate with e-commerce teams, service desks, and agencies.
- Quantify response delays and lead leakage from unmanaged inboxes.
- Pricing: per-agent seat + automation usage + channel packs.
- Revenue model: recurring seats + overages + premium analytics.
- KPIs: first response time, SLA compliance, conversion rate, churn.
- MVP: unified inbox, assignment, templates, SLA alerts, lead tagging.
- Future: AI co-pilot, quality scoring, workforce optimization.

## Stage 2 - Requirements
### Functional
- Unified inbox across WhatsApp/Facebook channels.
- Auto assignment and manual routing.
- Tagging, notes, canned replies, and conversation status.
- SLA policies and escalation actions.
- Lead management and reporting.
### Non-functional
- Fast chat sync and low-latency updates.
- Token security and channel reliability.
- High availability and queue resilience.

## Stage 3 - Product Design
- Flows: agent, supervisor, admin, QA manager.
- Responsive inbox experience with productivity shortcuts.

## Stage 4 - System Architecture
- Modular monolith with inbox core, routing engine, SLA module, analytics.
- Event-driven conversation lifecycle and escalation events.

## Stage 5 - Multi Tenant Design
- Tenant-isolated channels, conversations, templates, and automations.
- Queue/cache/storage isolation by tenant.

## Stage 6 - Database Design
- Entities: channels, conversations, messages, assignments, tags, SLAs, leads, campaigns.
- Indexes for message retrieval and SLA checks.
- Immutable audit trail for assignment and template changes.

## Stage 7 - Authentication
- MFA for supervisors and admins.
- Session controls and suspicious activity detection.

## Stage 8 - Authorization
- Roles: owner, admin, supervisor, agent, QA, viewer.
- Permissions for templates, routing rules, exports, policy edits.

## Stage 9 - Backend Modules
- Channel connectors, routing, SLA, templates, lead board, analytics, notifications, audit, API.

## Stage 10 - API
- REST webhook endpoints and internal APIs.
- Signature validation, idempotency, and replay protection.

## Stage 11 - Frontend
- Real-time inbox, queue views, agent performance, SLA dashboards.

## Stage 12 - File Storage
- Media attachments and conversation exports.

## Stage 13 - Search
- Search by customer, message content, tag, agent, SLA status.

## Stage 14 - Background Processing
- Jobs: message sync, auto-routing, SLA checks, campaign triggers, summaries.
- Retry, DLQ, and safe replay governance.

## Stage 15 - Notifications
- SLA breach alerts, assignment notifications, escalation alerts.

## Stage 16 - Security
- Channel token vault and rotation controls.
- Anti-abuse controls for spam, bot attacks, and API flooding.
- OWASP-aligned app security controls.

## Stage 17 - Logging
- Request, error, security, SLA, and conversation event logs.

## Stage 18 - Monitoring
- Sync latency, queue depth, SLA breach rates, uptime and error rates.

## Stage 19 - Testing
- Unit/integration/API/E2E tests for routing and SLA flows.
- Load tests for campaign bursts.
- Security and accessibility testing.

## Stage 20 - DevOps
- CI/CD with connector contract tests and rollback strategy.

## Stage 21 - Cloud Infrastructure
- Start on VPS, scale workers and websocket layer.
- Later add LB and regional delivery optimization.

## Stage 22 - Billing
- Agent-seat subscriptions with automation overage billing.
- Add-ons for advanced SLA analytics and AI assistant.

## Stage 23 - Compliance
- Data retention and deletion policy for conversations.
- Privacy, terms, and processing policy controls.

## Stage 24 - Documentation
- Connector setup docs, supervisor playbooks, API references.

## Stage 25 - Maintenance
- Weekly connector health review.
- Monthly policy and quality optimization.

## Business Modules
- CRM-lite, Sales lead conversion, Notification orchestration, Audit, Analytics, AI assistant roadmap.

## Enterprise Features
- Multi organization, multi agent teams, approval workflow, audit trail, event bus, domain events, webhooks, public API, white-label roadmap, feature flags, backup/DR.

## Superadmin and Event Governance
- Tenant-level policy packs, token governance, SLA controls.
- Domain events for message_received, conversation_assigned, sla_breached, lead_converted.

## KPI Targets
- First response time <= 5 minutes.
- SLA compliance >= 95 percent.
- Chat-to-lead conversion >= 12 percent.
- Monthly churn <= 4 percent.
