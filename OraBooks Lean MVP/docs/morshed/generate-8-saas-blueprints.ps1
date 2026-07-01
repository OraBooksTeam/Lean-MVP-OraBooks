$ErrorActionPreference = 'Stop'

$baseDir = $PSScriptRoot
$dateStr = Get-Date -Format 'yyyy-MM-dd HH:mm'

$projects = @(
    [pscustomobject]@{
        FileName = 'SaaS-01-Local-Business-Management-Blueprint.docx'
        ProductName = 'Local Business Management SaaS'
        Tagline = 'Inventory, sales, receivables, and branch operations for SMBs'
        ICP = 'Retail shops, distributors, pharmacies, local wholesalers, and service stores with 3-100 staff'
        PainPoints = @('Manual ledger and stock tracking', 'High receivable leakage', 'No branch-level visibility', 'Weak cashflow control')
        Differentiation = @('Offline-first workflows', 'Bangladesh-ready compliance path', 'Fast onboarding in 1-2 days', 'Deep branch and warehouse controls')
        Pricing = 'Starter BDT 499, Growth BDT 1499, Scale BDT 3999 per month + add-ons for extra users/branches'
        RevenueModel = 'Subscription + premium reports + onboarding fee + partner referrals'
        CoreModules = @('Customer ledger', 'Inventory', 'Sales and invoice', 'Purchase', 'Cash and bank', 'Expense approvals', 'Reporting')
        WorkflowExamples = @('Quote -> Invoice -> Payment -> Reconciliation', 'Purchase order -> GRN -> Bill -> Payment', 'Low-stock alert -> Reorder workflow')
        AutomationExamples = @('Daily due reminders', 'Low stock auto-alert', 'Daily cash-close checklist', 'Auto-generated management snapshot')
        SecurityFocus = @('Cash and banking actions need step-up MFA', 'Strict maker-checker for write-off approvals')
        KPI = @('D30 retention >= 70%', 'Payment collection improvement >= 20%', 'Monthly churn <= 4%', 'NRR >= 105% by month 18')
        RoadmapHighlight = @('MVP: Sales-inventory-ledger core', 'Growth: Multi-branch + mobile app', 'Scale: Forecasting + partner ecosystem')
    },
    [pscustomobject]@{
        FileName = 'SaaS-02-School-Coaching-Management-Blueprint.docx'
        ProductName = 'School and Coaching Management SaaS'
        Tagline = 'Admission to result lifecycle with fee intelligence and guardian engagement'
        ICP = 'Schools, madrasas, and coaching centers with 200-10,000 students'
        PainPoints = @('Attendance and fee tracking in spreadsheets', 'Guardian communication gaps', 'Manual exam and result operations', 'No campus analytics')
        Differentiation = @('Guardian-first communication stack', 'Fee risk scoring and auto reminders', 'Institution-grade access controls', 'Multi-campus governance')
        Pricing = 'Per-campus base fee + per-student slab pricing with annual prepay discounts'
        RevenueModel = 'Subscription + SMS/notification packs + implementation/training services'
        CoreModules = @('Admissions', 'Student profiles', 'Class and section setup', 'Attendance', 'Fee billing', 'Exam and result', 'Guardian portal')
        WorkflowExamples = @('Admission inquiry -> Application -> Enrollment', 'Attendance capture -> Parent alert', 'Fee invoice -> Reminder -> Collection')
        AutomationExamples = @('Attendance anomaly alerts', 'Fee due campaigns', 'Result publish workflow', 'Admission follow-up cadences')
        SecurityFocus = @('Minor student data protection controls', 'Campus-specific data visibility with strict segregation')
        KPI = @('Fee collection efficiency >= 92%', 'Guardian app adoption >= 60%', 'Institution churn <= 3% monthly', 'Collection delay reduced by 30%')
        RoadmapHighlight = @('MVP: Attendance + fee + communication', 'Growth: Exam workflows + analytics', 'Scale: LMS integration + district partnerships')
    },
    [pscustomobject]@{
        FileName = 'SaaS-03-HR-Attendance-Management-Blueprint.docx'
        ProductName = 'HR and Attendance Management SaaS'
        Tagline = 'Attendance, leave, payroll inputs, and compliance-ready employee operations'
        ICP = 'SMBs and growing enterprises with 20-2000 employees'
        PainPoints = @('Attendance fraud and manual corrections', 'Leave process bottlenecks', 'Payroll preparation errors', 'Scattered employee records')
        Differentiation = @('Policy engine for late/leave rules', 'Biometric and app check-in options', 'Payroll-ready exports', 'Audit-first HR operations')
        Pricing = 'Per-employee per-month pricing with volume tiers and payroll add-on'
        RevenueModel = 'Recurring subscription + integration add-ons + premium compliance reports'
        CoreModules = @('Employee directory', 'Attendance', 'Shifts', 'Leave management', 'Payroll inputs', 'Document vault', 'Approvals')
        WorkflowExamples = @('Check-in -> Policy evaluation -> Attendance finalization', 'Leave request -> Manager approve -> HR finalize', 'Payroll cycle lock -> Export -> Finance handoff')
        AutomationExamples = @('Late/absence escalation', 'Leave balance alerts', 'Payroll checklist automation', 'Probation and contract reminders')
        SecurityFocus = @('PII and payroll fields with field-level access controls', 'Device/session controls for HR admin accounts')
        KPI = @('Attendance accuracy >= 98%', 'Payroll prep time down by 40%', 'Monthly logo churn <= 2.5%', 'NPS >= 45')
        RoadmapHighlight = @('MVP: Attendance + leave + payroll export', 'Growth: Compliance packs', 'Scale: Workforce planning and AI insights')
    },
    [pscustomobject]@{
        FileName = 'SaaS-04-Appointment-Booking-Blueprint.docx'
        ProductName = 'Appointment Booking SaaS'
        Tagline = 'Online booking, reminders, deposits, and no-show reduction for service businesses'
        ICP = 'Clinics, salons, consultants, legal chambers, and diagnostics centers'
        PainPoints = @('No-show losses', 'Manual slot management', 'High call-center overhead', 'No customer history')
        Differentiation = @('Waitlist auto-fill', 'No-show risk controls', 'Deposit and cancellation policy engine', 'Cross-channel reminders')
        Pricing = 'Monthly subscription + optional transaction fee on prepaid bookings'
        RevenueModel = 'Subscription + payment processing margin + premium analytics'
        CoreModules = @('Provider calendar', 'Slot engine', 'Booking and rescheduling', 'Deposits/payments', 'Reminders', 'Customer history', 'Utilization analytics')
        WorkflowExamples = @('Search slot -> Book -> Reminder -> Visit -> Follow-up', 'Cancellation -> Waitlist auto-fill -> Confirm')
        AutomationExamples = @('Multi-step reminder journeys', 'No-show prevention nudges', 'Reactivation campaigns', 'Capacity optimization alerts')
        SecurityFocus = @('Health/legal customer data segmentation', 'Strict consent and communication preference controls')
        KPI = @('No-show reduction >= 35%', 'Repeat booking rate >= 45%', 'Monthly churn <= 4%', 'Gross margin >= 75%')
        RoadmapHighlight = @('MVP: Booking + reminders', 'Growth: Payments + waitlist intelligence', 'Scale: Marketplace integrations')
    },
    [pscustomobject]@{
        FileName = 'SaaS-05-AI-Content-Workflow-Blueprint.docx'
        ProductName = 'AI Content Workflow SaaS'
        Tagline = 'Planning, approvals, scheduling, and governance for content teams'
        ICP = 'Agencies, media teams, and in-house marketing teams with 3-200 collaborators'
        PainPoints = @('Fragmented tools for ideation and publishing', 'Approval delays', 'Weak governance and traceability', 'Inconsistent output quality')
        Differentiation = @('Workflow-first approach, not generic AI generation', 'Approval SLA and audit trails', 'Multi-channel publish orchestration', 'Brand guardrails')
        Pricing = 'Workspace subscription + per-seat pricing + automation usage tiers'
        RevenueModel = 'Subscription + seat expansion + premium governance features'
        CoreModules = @('Idea intake', 'Editorial calendar', 'Draft workflow', 'Approval chain', 'Publisher', 'Performance loop', 'Asset library')
        WorkflowExamples = @('Idea -> Brief -> Draft -> Review -> Approval -> Publish', 'Content repurpose pipeline by channel')
        AutomationExamples = @('SLA reminder escalation', 'Scheduled publishing', 'Template-driven repurposing', 'Performance digest generation')
        SecurityFocus = @('Role-based publish rights with mandatory approvals', 'Brand and compliance rule enforcement before publish')
        KPI = @('Content cycle time down by 30%', 'On-time publish rate >= 95%', 'Monthly churn <= 4%', 'Expansion revenue >= 20% yearly')
        RoadmapHighlight = @('MVP: Workflow and scheduling', 'Growth: AI assistant and governance', 'Scale: Enterprise integrations and API')
    },
    [pscustomobject]@{
        FileName = 'SaaS-06-Real-Estate-Niche-CRM-Blueprint.docx'
        ProductName = 'Real Estate Niche CRM SaaS'
        Tagline = 'Lead-to-close pipeline, property operations, and commission governance'
        ICP = 'Real estate agencies, developers, and brokerage networks'
        PainPoints = @('Lead leakage across channels', 'Manual follow-ups', 'Weak broker performance visibility', 'Commission disputes')
        Differentiation = @('Real-estate-native data model', 'Visit-to-booking workflow control', 'Commission policy engine', 'Partner portal readiness')
        Pricing = 'Per-branch subscription + agent seat pricing + premium analytics tier'
        RevenueModel = 'Subscription + onboarding + partner marketplace add-ons'
        CoreModules = @('Lead management', 'Property inventory', 'Site visits', 'Deal stages', 'Commission engine', 'Partner portal', 'Sales forecasting')
        WorkflowExamples = @('Lead capture -> Qualification -> Visit -> Offer -> Booking -> Handover', 'Commission eligibility -> Approval -> Payout')
        AutomationExamples = @('Lead scoring and routing', 'Follow-up cadences', 'Stagnant pipeline alerts', 'Commission payout scheduling')
        SecurityFocus = @('Deal and payout approvals under maker-checker', 'PII controls for buyer/seller data')
        KPI = @('Lead-to-visit conversion >= 35%', 'Visit-to-booking >= 15%', 'Agent productivity +25%', 'Monthly churn <= 2.5%')
        RoadmapHighlight = @('MVP: Lead and property pipeline', 'Growth: Commission and partner portal', 'Scale: Predictive deal intelligence')
    },
    [pscustomobject]@{
        FileName = 'SaaS-07-Subscription-Billing-Platform-Blueprint.docx'
        ProductName = 'Subscription Billing Platform SaaS'
        Tagline = 'Plans, invoicing, renewals, dunning, and revenue operations'
        ICP = 'SaaS startups, agencies, coaching businesses, and recurring service providers'
        PainPoints = @('Manual recurring invoicing', 'Failed payment recovery gaps', 'No reliable MRR/ARR visibility', 'Tax and audit complexity')
        Differentiation = @('Regional payment gateway abstraction', 'Strong dunning and retry governance', 'Metered usage support', 'Finance-grade auditability')
        Pricing = 'Platform subscription + billing volume tiers + metering add-on'
        RevenueModel = 'Subscription + volume overage + premium reconciliation tools'
        CoreModules = @('Plan catalog', 'Subscriptions', 'Invoices', 'Dunning', 'Payment reconciliation', 'Usage metering', 'Revenue analytics')
        WorkflowExamples = @('Trial -> Convert -> Renew -> Upgrade/Downgrade', 'Payment fail -> Retry ladder -> Recovery or churn')
        AutomationExamples = @('Invoice scheduling', 'Dunning ladder automation', 'Grace-period controls', 'Recovery campaign sequencing')
        SecurityFocus = @('Payment event integrity and anti-fraud checks', 'Strict finance roles and immutable audit trails')
        KPI = @('Payment recovery >= 20%', 'Involuntary churn down by 30%', 'MRR reporting accuracy >= 99.5%', 'Monthly churn <= 3%')
        RoadmapHighlight = @('MVP: Subscription lifecycle and invoices', 'Growth: Usage billing and tax engine', 'Scale: Multi-entity finance operations')
    },
    [pscustomobject]@{
        FileName = 'SaaS-08-WhatsApp-Facebook-Inbox-Blueprint.docx'
        ProductName = 'WhatsApp and Facebook Inbox SaaS'
        Tagline = 'Unified social inbox with SLA control, lead handling, and automation'
        ICP = 'SMBs, e-commerce sellers, service teams, and digital agencies handling social DMs'
        PainPoints = @('Messages spread across channels', 'Slow response SLA', 'Lead handoff failures', 'No team accountability')
        Differentiation = @('Single pane inbox with routing', 'Bot-to-human handoff governance', 'Lead pipeline integrated with chat', 'SLA analytics and workforce visibility')
        Pricing = 'Per-agent seat pricing + automation usage tiers + premium channel packs'
        RevenueModel = 'Seat subscription + automation overage + campaign add-ons'
        CoreModules = @('Unified inbox', 'Conversation routing', 'SLA engine', 'Templates and macros', 'Lead board', 'Campaign triggers', 'QA and coaching')
        WorkflowExamples = @('Incoming message -> Intent classify -> Route -> Resolve -> Convert', 'Escalation workflow for unresolved threads')
        AutomationExamples = @('Auto-replies by intent', 'SLA breach alerts', 'Lead nurturing workflows', 'Conversation quality summaries')
        SecurityFocus = @('Channel token security and rotation', 'Role-based access for agent and supervisor data')
        KPI = @('First response time <= 5 minutes', 'SLA compliance >= 95%', 'Conversion from chat >= 12%', 'Monthly churn <= 4%')
        RoadmapHighlight = @('MVP: Inbox and routing', 'Growth: SLA analytics and automation', 'Scale: AI assist and partner ecosystem')
    }
)

function Add-Heading([object]$selection, [object]$doc, [string]$style, [string]$text) {
    $selection.Style = $doc.Styles.Item($style)
    $selection.TypeText($text)
    $selection.TypeParagraph()
}

function Add-Paragraph([object]$selection, [object]$doc, [string]$text) {
    $selection.Style = $doc.Styles.Item('Normal')
    $selection.TypeText($text)
    $selection.TypeParagraph()
}

function Add-Bullet([object]$selection, [object]$doc, [string]$text) {
    $selection.Style = $doc.Styles.Item('Normal')
    $selection.Range.ListFormat.ApplyBulletDefault() | Out-Null
    $selection.TypeText($text)
    $selection.TypeParagraph()
    $selection.Range.ListFormat.RemoveNumbers()
}

function Add-Bullets([object]$selection, [object]$doc, [string[]]$items) {
    foreach ($i in $items) {
        Add-Bullet -selection $selection -doc $doc -text $i
    }
}

function Add-StageHeader([object]$selection, [object]$doc, [int]$num, [string]$title) {
    Add-Heading -selection $selection -doc $doc -style 'Heading 1' -text ("Stage {0} - {1}" -f $num, $title)
}

function Add-CommonStages([object]$selection, [object]$doc, [object]$p) {
    Add-StageHeader $selection $doc 1 'Business Planning'
    Add-Paragraph $selection $doc 'Objective: Validate a recurring problem with strong retention potential and define profitable subscription strategy before engineering starts.'
    Add-Bullets $selection $doc @(
        'Problem validation: run 30-50 structured interviews and gather current process pain by role.',
        'Market research: estimate TAM/SAM/SOM and identify segment entry sequence.',
        'Competitor analysis: benchmark pricing, onboarding time, core gaps, lock-in mechanisms.',
        'Target audience: define ICP, buyer persona, user persona, and decision-maker path.',
        ('Pricing and subscription plan: ' + $p.Pricing),
        ('Revenue model: ' + $p.RevenueModel),
        'Business goals: yearly ARR milestones, CAC payback targets, and cash runway policy.',
        ('KPI framework: ' + ($p.KPI -join '; ')),
        'Product vision: workflow depth over feature breadth; high switching cost with ethical portability.',
        ('Roadmap highlights: ' + ($p.RoadmapHighlight -join '; ')),
        'MVP scope freeze with strict exclusions to protect launch speed.',
        'Future scope sequencing for years 2-5 and year 5+ expansion.'
    )

    Add-StageHeader $selection $doc 2 'Requirements'
    Add-Paragraph $selection $doc 'Objective: Build requirements baseline where functional and non-functional items are equally explicit from day one.'
    Add-Bullets $selection $doc @(
        'Functional requirements: login, dashboard, workflows, approvals, reports, exports, notification center, and audit views.',
        'NFR-performance: P95 API under 300ms for critical read operations under target load.',
        'NFR-security: least privilege, secure defaults, no insecure direct object references.',
        'NFR-availability: minimum 99.9 percent for paid plans with incident playbook.',
        'NFR-backup and restore: daily full backup + point-in-time recovery target.',
        'NFR-scalability: horizontal worker scale for queues and read path caching.',
        'NFR-multi-tenancy: tenant-safe design with strict isolation controls.',
        'NFR-accessibility: WCAG 2.1 AA checklist built into definition of done.'
    )

    Add-StageHeader $selection $doc 3 'Product Design'
    Add-Bullets $selection $doc @(
        'User flow maps for core jobs-to-be-done by persona with edge-case branches.',
        'Wireframe to high-fidelity mockup process with approval gates in Figma/FigJam.',
        'Design system: typography, spacing, color tokens, input states, error states, and content guidelines.',
        'UX pattern library: table heavy workflows, wizard flows, and mobile-first interactions.',
        'Responsive plan: desktop control center + mobile operator workflows.',
        'Dark mode policy where business users request low-light support.',
        'Accessibility checks: keyboard nav, focus order, contrast, and screen-reader labels.'
    )

    Add-StageHeader $selection $doc 4 'System Architecture'
    Add-Bullets $selection $doc @(
        'Architecture choice: modular monolith first for speed; extraction boundaries documented for future services.',
        'Event-driven internal integration using outbox pattern and domain events.',
        'DDD-inspired module boundaries with explicit ownership per bounded context.',
        'Clean architecture rules for dependency direction and testability.',
        'CQRS-lite for high-read dashboards and reporting workloads.',
        'Hexagonal adapters for storage, queue, and external provider integrations.',
        'Layered architecture for controllers, application services, domain services, and repositories.'
    )

    Add-StageHeader $selection $doc 5 'Multi-Tenant Design'
    Add-Bullets $selection $doc @(
        'Tenant strategy: shared database with strict org_id isolation for MVP; migration path to schema or DB per tenant for enterprise.',
        'Tenant isolation controls: data isolation, storage namespace isolation, cache key scoping, and queue partitioning by tenant.',
        'Tenant onboarding workflow: provisioning, policy bootstrap, admin invite, and initial data import.',
        'Tenant offboarding: export package, retention timer, soft lock, and hard delete policy.',
        'Cross-tenant guardrails: mandatory tenant context in every write path and event payload.'
    )

    Add-StageHeader $selection $doc 6 'Database Design'
    Add-Bullets $selection $doc @(
        'ERD per domain module with normalized core entities and selective denormalized read models.',
        'Tables, relations, constraints, and uniqueness guards for business-critical records.',
        'Index strategy for tenant_id plus high-frequency filters and reporting keys.',
        'Partition and archive policy for logs, events, and history-heavy tables.',
        'Migration and seed strategy with forward-only migrations and rollback playbooks.',
        'Transaction policy with idempotent command handlers and optimistic concurrency where needed.',
        'Soft delete policy plus immutable audit tables for regulated flows.'
    )

    Add-StageHeader $selection $doc 7 'Authentication'
    Add-Bullets $selection $doc @(
        'Auth flows: register, login, logout, password reset, email verification, and session controls.',
        'JWT access token + rotating refresh token with reuse detection.',
        'MFA options: TOTP and WebAuthn for admin and high-risk actions.',
        'Social login optional for low-risk personas; enterprise SSO roadmap via OIDC/SAML.',
        'Session management: device list, revoke sessions, suspicious login alerting.'
    )

    Add-StageHeader $selection $doc 8 'Authorization'
    Add-Bullets $selection $doc @(
        'RBAC baseline roles: Owner, Admin, Manager, Staff, Viewer plus Auditor and Finance where needed.',
        'Permission matrix includes read, write, delete, export, approve, billing-admin, and policy-admin scopes.',
        'Least privilege defaults; all privileged permissions granted by explicit policy only.',
        'Row and field-level controls for sensitive records and payouts.',
        'Access review cadence and automatic stale-role cleanup.'
    )

    Add-StageHeader $selection $doc 9 'Backend Modules'
    Add-Bullets $selection $doc @(
        'Core platform modules: User, Organization, Billing, Notification, Audit, Settings, API gateway.',
        ('Domain modules: ' + ($p.CoreModules -join '; ')),
        'Integration module for webhooks and third-party provider connectors.',
        'Feature flag service for safe rollout and plan entitlements.'
    )

    Add-StageHeader $selection $doc 10 'API Strategy'
    Add-Bullets $selection $doc @(
        'REST-first API with versioning from day one and OpenAPI contract publishing.',
        'Input validation, sanitization, and typed error envelope standards.',
        'Rate limit and abuse controls by IP, user, tenant, and API key.',
        'Pagination/filtering/sorting/search conventions for all collection endpoints.',
        'Idempotency keys required for financial or duplicate-sensitive writes.'
    )

    Add-StageHeader $selection $doc 11 'Frontend Architecture'
    Add-Bullets $selection $doc @(
        'Dashboard with role-aware widgets and drill-down workflows.',
        'High-performance forms with autosave and unsaved-change protection.',
        'Data tables with column presets, filters, export, and keyboard shortcuts.',
        'Charts and trend surfaces for operators and managers.',
        'Robust loading, skeleton states, error boundaries, and retry UX patterns.'
    )

    Add-StageHeader $selection $doc 12 'File Storage'
    Add-Bullets $selection $doc @(
        'Object storage policy for avatars, documents, PDFs, and evidence artifacts.',
        'Signed URL model, malware scan hooks, and retention labels.',
        'Storage abstraction supporting S3, Cloudflare R2, and GCS-compatible backends.',
        'Tenant-scoped buckets/prefixes with access policy enforcement.'
    )

    Add-StageHeader $selection $doc 13 'Search'
    Add-Bullets $selection $doc @(
        'Global full-text search across major modules with tenant-safe indexing.',
        'Advanced filtering and saved views for operational users.',
        'Relevance tuning and typo-tolerant search for productivity workflows.'
    )

    Add-StageHeader $selection $doc 14 'Background Processing'
    Add-Bullets $selection $doc @(
        'Queue workloads: email, report generation, exports, imports, OCR, AI tasks, and webhooks.',
        'Scheduler design: cron orchestration, retry with exponential backoff and jitter.',
        'Dead-letter queue with replay authorization workflow.',
        'Idempotent handlers and timeout policy to avoid duplicate side effects.'
    )

    Add-StageHeader $selection $doc 15 'Notifications'
    Add-Bullets $selection $doc @(
        'Channel strategy: email, SMS, push, WhatsApp, Slack/Teams, and webhook callbacks.',
        'Template and localization controls with tenant-level branding rules.',
        'Escalation policies for critical alerts and unread events.',
        'Cost-aware notification routing and budget guardrails.'
    )

    Add-StageHeader $selection $doc 16 'Security'
    Add-Bullets $selection $doc @(
        'HTTPS and TLS hardening, HSTS, secure cookies, and strict transport guarantees.',
        'Encryption in transit and at rest with documented key hierarchy.',
        'JWT security, refresh rotation, and anti-replay controls.',
        'CSRF, XSS, SQL injection, and SSRF prevention via layered defenses.',
        'CSP and security headers with strict default policy.',
        'Secrets management and rotation runbook with ownership and cadence.',
        'Password policy, session timeout, device management, and optional IP restrictions.',
        'WAF policy and bot/credential-stuffing detection.',
        ('Project-specific security priorities: ' + ($p.SecurityFocus -join '; ')),
        'Threat modeling cadence and incident response communication matrix.'
    )

    Add-StageHeader $selection $doc 17 'Logging'
    Add-Bullets $selection $doc @(
        'Request logs with trace_id propagation end-to-end.',
        'Error logs with structured context and release correlation.',
        'Immutable audit logs for security and financial actions.',
        'Business event logs for funnel and workflow analytics.'
    )

    Add-StageHeader $selection $doc 18 'Monitoring'
    Add-Bullets $selection $doc @(
        'Health endpoints for app, database, queue, cache, and storage dependencies.',
        'Metrics coverage: CPU, RAM, API latency, DB health, Redis, queue depth, and error rates.',
        'SLO/SLI framework with error budget policy and paging thresholds.',
        'Uptime and synthetic checks from external probes.'
    )

    Add-StageHeader $selection $doc 19 'Testing Strategy'
    Add-Bullets $selection $doc @(
        'Unit, integration, API, and end-to-end tests in CI gates.',
        'Performance, load, and stress test profiles for peak scenarios.',
        'Security testing: SAST, dependency scanning, penetration test cadence.',
        'Regression suite for billing, approval, and data integrity paths.',
        'Accessibility test baseline in release checklist.'
    )

    Add-StageHeader $selection $doc 20 'DevOps'
    Add-Bullets $selection $doc @(
        'Git workflow: trunk-based or short-lived branch strategy with protected main branch.',
        'PR templates, mandatory review policy, and quality gates.',
        'Containerization with Docker and Docker Compose for environment parity.',
        'CI/CD via GitHub Actions with test, scan, deploy, and rollback jobs.',
        'Secrets injection strategy and environment segregation (dev, staging, prod).',
        'Infrastructure as Code path and release runbook.'
    )

    Add-StageHeader $selection $doc 21 'Cloud Infrastructure'
    Add-Bullets $selection $doc @(
        'Provider strategy starts on cost-efficient VM (DigitalOcean/Hetzner) and evolves to managed cloud where needed.',
        'Core components: VM/Kubernetes path, load balancer, CDN, Redis, PostgreSQL, object storage.',
        'Scaling triggers and migration criteria documented before each infra step-up.',
        'Cost governance dashboard with monthly budget caps and anomaly alerts.'
    )

    Add-StageHeader $selection $doc 22 'Billing and Monetization Operations'
    Add-Bullets $selection $doc @(
        'Subscription lifecycle: trial, activation, renewal, upgrade/downgrade, cancellation, win-back.',
        'Coupon and discount governance with abuse prevention.',
        'Invoice, payment gateway abstraction, refund workflows, and tax handling.',
        'Usage billing support where applicable and transparent metering trails.'
    )

    Add-StageHeader $selection $doc 23 'Compliance and Policy Baseline'
    Add-Bullets $selection $doc @(
        'Roadmap toward GDPR, SOC2 controls, and ISO 27001-aligned operating practices.',
        'Privacy policy, terms of service, cookie policy, and data retention policy from launch.',
        'Data subject request workflows and legal hold handling.',
        'Backup policy with tested restore objectives (RPO/RTO).'
    )

    Add-StageHeader $selection $doc 24 'Documentation'
    Add-Bullets $selection $doc @(
        'Developer docs: API reference, architecture decisions, database and deployment guides.',
        'User docs: role-based manuals, onboarding checklists, FAQ, and tutorial assets.',
        'Operational docs: incident runbooks, escalation trees, and postmortem templates.'
    )

    Add-StageHeader $selection $doc 25 'Maintenance and Scale Operations'
    Add-Bullets $selection $doc @(
        'Post-launch loop: bug triage, security patching, telemetry review, and roadmap reprioritization.',
        'Customer support model with SLA tiers and success playbooks.',
        'Scalability and cost optimization rhythm every quarter.',
        'Database optimization, archival housekeeping, and index tuning cadence.',
        'Annual architecture review for resilience and strategic moat strength.'
    )

    Add-Heading $selection $doc 'Heading 1' 'Business Modules Map'
    Add-Bullets $selection $doc @(
        'Core list for planning: CRM, Sales, Purchase, Inventory, Payroll, HRM, POS, Manufacturing (where relevant), Banking, Fixed Asset, Budget, Tax, VAT, OCR, AI Assistant.',
        'Module inclusion is prioritized by project fit and phased roadmap, not all-at-once implementation.',
        'Every module requires role-aware workflow and event emission points for observability and automation.'
    )

    Add-Heading $selection $doc 'Heading 1' 'Enterprise Features Map'
    Add-Bullets $selection $doc @(
        'Multi-organization, multi-currency, multi-branch, multi-warehouse, and multi-language path.',
        'Approval workflow, audit trail, event bus, domain events, and webhook framework.',
        'Public API, SDK, plugin system, white-label options, and partner portal design.',
        'Feature flags, backup and restore, and disaster recovery governance.'
    )

    Add-Heading $selection $doc 'Heading 1' 'Project-Specific Deep Plan'
    Add-Heading $selection $doc 'Heading 2' 'Product Profile'
    Add-Paragraph $selection $doc ('Product: ' + $p.ProductName)
    Add-Paragraph $selection $doc ('Positioning: ' + $p.Tagline)
    Add-Paragraph $selection $doc ('ICP: ' + $p.ICP)

    Add-Heading $selection $doc 'Heading 2' 'Top Pain Points'
    Add-Bullets $selection $doc $p.PainPoints

    Add-Heading $selection $doc 'Heading 2' 'Differentiation Strategy'
    Add-Bullets $selection $doc $p.Differentiation

    Add-Heading $selection $doc 'Heading 2' 'Core Workflow Examples'
    Add-Bullets $selection $doc $p.WorkflowExamples

    Add-Heading $selection $doc 'Heading 2' 'Automation Catalog (Free-First)'
    Add-Bullets $selection $doc $p.AutomationExamples
    Add-Bullets $selection $doc @(
        'n8n OSS workflows for trigger-based operations and incident-safe retries.',
        'Fallback manual SOP for every automation to avoid business stoppage.',
        'Queue and workflow ownership matrix with backup owner policy.'
    )

    Add-Heading $selection $doc 'Heading 2' 'Superadmin Management Model'
    Add-Bullets $selection $doc @(
        'Global tenant registry and lifecycle controls (active, suspended, read-only, archived).',
        'Plan entitlements and feature flags by package, addon, and region.',
        'Quota controls for users, API calls, storage, queue jobs, and integrations.',
        'Support impersonation only with user consent token and immutable audit trail.',
        'Security center: risky login flags, API key activity, policy drift alerts, and forced re-auth controls.'
    )

    Add-Heading $selection $doc 'Heading 2' 'Event Bus and Async Governance'
    Add-Bullets $selection $doc @(
        'Canonical domain event format with event_id, tenant_id, aggregate_id, version, and trace metadata.',
        'Outbox publisher to guarantee event creation in same transaction as state change.',
        'Consumer idempotency keys and dedupe store for exactly-once-effect behavior.',
        'Retry strategy: exponential backoff + jitter + DLQ + replay policy.',
        'Operational controls: backlog dashboards, poison message quarantine, replay approvals, and incident runbook.'
    )

    Add-Heading $selection $doc 'Heading 2' 'Financial and Growth Model'
    Add-Paragraph $selection $doc ('Pricing model: ' + $p.Pricing)
    Add-Paragraph $selection $doc ('Revenue model: ' + $p.RevenueModel)
    Add-Bullets $selection $doc @(
        'Acquisition channels: content + partner referrals + outbound demos + local community programs.',
        'Activation playbook: first-value in 24-72 hours with guided setup.',
        'Retention playbook: monthly business review, usage nudges, and risk scoring.',
        'Expansion playbook: feature add-ons, seat growth, multi-branch upgrades, and premium analytics.'
    )

    Add-Heading $selection $doc 'Heading 2' 'Execution Roadmap and KPIs'
    Add-Bullets $selection $doc @(
        'Phase 0 (0-4 weeks): discovery, validation, and architecture decision records.',
        'Phase 1 (0-6 months): MVP launch with core workflows and basic subscriptions.',
        'Phase 2 (6-18 months): reliability hardening, integrations, analytics, and growth loops.',
        'Phase 3 (18-36 months): enterprise packages, ecosystem APIs, and regional expansion.',
        ('KPI targets: ' + ($p.KPI -join '; '))
    )

    Add-Heading $selection $doc 'Heading 2' 'Risk Register and Mitigation'
    Add-Bullets $selection $doc @(
        'Risk: low adoption due to workflow complexity; mitigation: guided onboarding and template packs.',
        'Risk: support overload; mitigation: role-based help center, in-app hints, and automation of repetitive support tasks.',
        'Risk: security incident; mitigation: layered controls, drills, and clear incident communication matrix.',
        'Risk: infra cost spikes; mitigation: budget alerts, queue shaping, and cost-per-tenant observability.',
        'Risk: competitor pricing pressure; mitigation: vertical depth, partner distribution, and retention-focused product value.'
    )

    Add-Heading $selection $doc 'Heading 1' 'Acceptance Checklist'
    Add-Bullets $selection $doc @(
        'Stage 1-25 covered with actionable depth.',
        'Functional and non-functional requirements documented and testable.',
        'Superadmin, RBAC, and multi-tenant isolation policies defined.',
        'Event bus and async governance with DLQ/replay controls included.',
        'Security, compliance, and incident readiness documented.',
        'Business model, pricing, KPI tree, and roadmap completed.'
    )
}

$word = New-Object -ComObject Word.Application
$word.Visible = $false

try {
    foreach ($p in $projects) {
        $doc = $word.Documents.Add()
        $sel = $word.Selection

        Add-Heading $sel $doc 'Title' $p.ProductName
        Add-Paragraph $sel $doc 'Enterprise SaaS Detailed Master Blueprint (10+ Year Plan)'
        Add-Paragraph $sel $doc ('Generated: ' + $dateStr)
        Add-Paragraph $sel $doc 'Language: English | Format: Detailed execution plan | Scope: Strategy + Architecture + Security + Operations'
        Add-Paragraph $sel $doc ''

        Add-Heading $sel $doc 'Heading 1' 'Document Purpose'
        Add-Paragraph $sel $doc 'This document is a full execution blueprint to build, operate, scale, and monetize a subscription SaaS product with enterprise-grade governance and reliability.'
        Add-Paragraph $sel $doc 'It includes business planning, product design, architecture decisions, multi-tenant controls, event-driven operations, deep security, compliance, DevOps, and long-term maintenance frameworks.'

        Add-CommonStages -selection $sel -doc $doc -p $p

        $outPath = Join-Path $baseDir $p.FileName
        if (Test-Path $outPath) { Remove-Item $outPath -Force }

        # 16 => wdFormatXMLDocument (.docx)
        $doc.SaveAs2($outPath, 16)
        $doc.Close()
        Write-Host ('Created: ' + $outPath)
    }
}
finally {
    $word.Quit()
    [System.Runtime.InteropServices.Marshal]::ReleaseComObject($word) | Out-Null
}

Write-Host 'All 8 blueprint files generated successfully.'
