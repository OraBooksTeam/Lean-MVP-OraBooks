#!/usr/bin/env node
/**
 * Generate OraBooks Stack Plan Word Document (Current Lean MVP + Future Enterprise).
 * Run: node generate_stack_plan_doc.mjs
 */

import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import {
  AlignmentType,
  Document,
  HeadingLevel,
  Packer,
  Paragraph,
  ShadingType,
  Table,
  TableCell,
  TableRow,
  TextRun,
  WidthType,
} from "docx";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUTPUT = path.join(__dirname, "OraBooks_Stack_Plan_Current_and_Future.docx");

const ACCENT = "1A568E";
const FONT = "Nirmala UI";

function p(text, opts = {}) {
  const {
    bold = false,
    size = 22,
    heading,
    bullet,
    numbering,
    spacingAfter = 120,
    alignment,
    font = FONT,
    color,
    breakBefore = false,
  } = opts;

  const runOpts = { text, font, size, bold };
  if (color) runOpts.color = color;

  const paraOpts = {
    spacing: { after: spacingAfter },
    children: [new TextRun(runOpts)],
  };
  if (heading) paraOpts.heading = heading;
  if (bullet) paraOpts.bullet = { level: 0 };
  if (numbering) paraOpts.numbering = numbering;
  if (alignment) paraOpts.alignment = alignment;
  if (breakBefore) paraOpts.pageBreakBefore = true;

  return new Paragraph(paraOpts);
}

function bullets(items, size = 20) {
  return items.map((text) => p(text, { bullet: true, size, spacingAfter: 60 }));
}

function diagram(lines) {
  return new Paragraph({
    spacing: { after: 200 },
    indent: { left: 400 },
    children: [
      new TextRun({
        text: lines.join("\n"),
        font: "Consolas",
        size: 16,
      }),
    ],
  });
}

function table(headers, rows, colWidths) {
  const headerRow = new TableRow({
    tableHeader: true,
    children: headers.map(
      (h, i) =>
        new TableCell({
          width: { size: colWidths[i], type: WidthType.PERCENTAGE },
          shading: { type: ShadingType.CLEAR, fill: ACCENT, color: "auto" },
          children: [
            new Paragraph({
              alignment: AlignmentType.CENTER,
              children: [new TextRun({ text: h, bold: true, color: "FFFFFF", size: 18, font: FONT })],
            }),
          ],
        })
    ),
  });

  const dataRows = rows.map(
    (row) =>
      new TableRow({
        children: row.map(
          (cell, i) =>
            new TableCell({
              width: { size: colWidths[i], type: WidthType.PERCENTAGE },
              children: [new Paragraph({ children: [new TextRun({ text: String(cell), size: 18, font: FONT })] })],
            })
        ),
      })
  );

  return new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    rows: [headerRow, ...dataRows],
  });
}

const currentLayers = [
  ["Platform — WordPress 6.x Multisite", "WordPress হলো hosting platform। Multisite দিয়ে subdomain-based multi-tenant routing করা যায়। Lean MVP-তে দ্রুত page, shortcode, cron এবং admin shell পাওয়ার জন্য এটি বেছে নেওয়া হয়েছে।"],
  ["Backend — PHP 8.x Plugin", "সব accounting business logic PHP class-এ লেখা (includes/class-orabooks-*.php)। Journal posting, workflow, RBAC, partner commission—সব domain logic এখানে। WordPress hook-এর বাইরে plain PHP service pattern follow করা হয়েছে যাতে future-তে migrate করা সহজ হয়।"],
  ["Frontend — React 19 + Vite 8 + Tailwind CSS 4", "orabooks-ui folder-এ React app build হয় এবং assets/react/ folder-এ WordPress plugin-এ enqueue হয়। Admin ও tenant frontend আলাদা bundle (admin.js, frontend.js)। Modern, fast UI Lean MVP-তে দ্রুত deliver করার জন্য।"],
  ["Database — MySQL 8", "Accounting table গুলো {prefix}orabooks_* naming convention follow করে। Event table: {prefix}gob_*_tob (SL-302)। ACID transaction, FOR UPDATE lock (SL-301 workflow) এবং WordPress $wpdb integration-এর জন্য MySQL।"],
  ["Events — SL-302 Transactional Outbox", "Business change-এর সাথে same transaction-এ event outbox-এ write হয়। Worker পরে consumer-দের deliver করে। Idempotency consumer_log দিয়ে ensure করা হয়।"],
  ["Queue — SL-303 Async Jobs + WordPress Cron", "Background job (OCR, export, webhook, email) orabooks_async_jobs table-এ queue হয়। WP Cron every_minute schedule দিয়ে worker process করে। MVP-তে Redis ছাড়াই async processing সম্ভব।"],
  ["Workflow — SL-301 State Engine", "Journal, Invoice, Bill, Expense, Commission—সব record-এর status change একটিমাত্র entry point: OraBooks_Workflow::transition()। DB lock, audit, event publish built-in।"],
  ["API — WordPress REST + Guarded AJAX + OpenAPI", "REST endpoint: /wp-json/api/*। OpenAPI spec: docs/openapi/openapi.json। JWT + org_id header দিয়ে tenant context pass হয়।"],
  ["AI — Azure Document Intelligence + OpenAI/Azure OpenAI", "Receipt OCR (SL-028), Voice (SL-052), Classification (SL-022)। API key না থাকলে MVP stub fallback deterministic result দেয়।"],
  ["PWA — Service Worker + IndexedDB", "Mobile expense receipt offline queue (SL-028)। Service worker shell cache করে।"],
  ["Testing — PHPUnit 11 + Jest", "PHP unit test প্রতিটি SL module cover করে। JavaScript test Jest + jsdom।"],
  ["Deploy — Manual PowerShell Build", "orabooks-ui/build-live.ps1 → npm run build → assets/react/। OraBooks_DeployChecks production gate verify করে।"],
  ["Cloud (Current) — Self-hosted / Shared Hosting", "MVP stage-এ low cost hosting (FTP-style deploy)। Docker/CI/CD এখনো নেই।"],
];

const domainModules = [
  "SL-004: Organization, subdomain, tier, data residency, partner/customer org types",
  "SL-013: Auth — register, login, Google OIDC, JWT, refresh token, email verify",
  "SL-003: RBAC/ABAC — deny-by-default permissions, cross-tenant guard",
  "SL-014: Team invites, role assignment",
  "SL-001: Journal posting engine — canonical ledger, hash chain",
  "SL-002: Approval gate — journal review workflow",
  "SL-301: Workflow state engine — invoice, bill, expense, commission states",
  "SL-017: Chart of Accounts",
  "SL-021: Customers & Invoices (AR)",
  "SL-027: Vendors & Bills (AP)",
  "SL-028: Expenses OCR + PWA offline receipts",
  "SL-031: Bank reconciliation",
  "SL-034: Inventory lite",
  "SL-052: Voice-to-text input",
  "SL-022: Smart classification + tax hints",
  "SL-076: AI review queue",
  "SL-068: Partner commission engine",
  "SL-139: Partner dashboard & onboarding",
  "SL-074/075: Financial & operational reports",
  "SL-114: PDF/CSV exports",
  "SL-113: CSV imports",
  "SL-203: Attachments & versioning",
  "SL-250: Notification center (email, push, in-app)",
  "SL-304: Fiscal period governance",
  "SL-305: Tax engine",
  "SL-302: Domain event bus",
  "SL-303: Async job queue",
  "SL-093: Observability & monitoring",
  "SL-008: Secrets & TLS",
  "SL-009: Audit logging",
  "SL-099: OWASP security controls",
];

const securitySections = [
  {
    title: "SL-008 — Secrets & TLS Management",
    bullets: [
      "Secret storage: Environment variable (ORABOOKS_*), JSON file, বা encrypted WordPress options",
      "AES-256-CBC encryption at rest — 2FA secret, backup codes, sensitive data",
      "JWT HS256 signing — 15 minute access token, grace period secret rotation (24h)",
      "bcrypt password hashing (cost 10)",
      "Production HTTPS redirect",
      "Database TLS detection",
      "TLS certificate expiry monitoring",
      "Secret redaction in logs",
      "TOTP (RFC 6238) — authenticator app support",
      "Google OIDC id_token verification — RS256 + JWKS",
    ],
    why: "Accounting software-এ secret leak মানে financial data compromise। SL-008 সব key encrypt করে এবং production-এ TLS enforce করে।",
  },
  {
    title: "SL-013 — Authentication & Session Security",
    bullets: [
      "Register/Login — email validation, subdomain binding, rate limit",
      "Refresh token rotation — SHA-256 hashed in DB, single-use",
      "Email verification — JWT issue হয় না verify না হওয়া পর্যন্ত",
      "Password reset — 1 hour token, reset-এ সব refresh token revoke",
      "Google OIDC — OAuth state CSRF protection",
      "Partner accounting isolation — partner org accounting page/API block (403)",
      "HTTP-only secure cookies — JWT + refresh token cookie",
    ],
    why: "Unauthorized access accounting data-এ direct financial loss। Multi-layer auth tenant isolation ensure করে।",
  },
  {
    title: "SL-013 — Two-Factor Authentication (2FA)",
    bullets: [
      "TOTP setup — QR code, encrypted secret storage",
      "Backup codes — bcrypt hashed, OTP-gated reveal/regenerate",
      "Org-wide 2FA policy — require_2fa config",
      "Admin 2FA recovery with audit log",
    ],
    why: "Password leak হলেও 2FA second barrier দেয়। Enterprise customer-দের জন্য org-wide 2FA mandatory করা যায়।",
  },
  {
    title: "SL-003 — RBAC / ABAC Access Control",
    bullets: [
      "Deny-by-default permission matrix — ~30 permissions",
      "require_permission() middleware — org-scoped",
      "Cross-tenant guard — target_org_id mismatch = block + audit",
      "Permission denied audit — permission_audit_log table",
    ],
    why: "Multi-tenant SaaS-এ এক org-এর user অন্য org-এর data দেখতে পারবে না।",
  },
  {
    title: "SL-009 — Audit Logging",
    bullets: [
      "Immutable audit_logs table — severity, IP, correlation_id",
      "Metadata sanitization — secret redaction, email masking",
      "365-day retention → archive table",
      "Correlation ID — request-scoped tracing",
    ],
    why: "Accounting-এ 'কে কখন কী করল' prove করতে audit log mandatory।",
  },
  {
    title: "SL-099 — OWASP Top-10 Security Controls",
    bullets: [
      "Security headers — HSTS, CSP, X-Frame-Options, nosniff",
      "Centralized rate-limit config",
      "SSRF allowlist — outbound URL validation",
      "Input schema validation",
      "Security incident tracking",
      "Weekly dependency scan",
      "Ledger integrity check — hash chain validation cron",
    ],
    why: "OWASP standard follow করে common web attack prevent করা হয়।",
  },
  {
    title: "SL-301 — Workflow Safety",
    bullets: [
      "Single write path — status field সরাসরি update করা যায় না",
      "DB transaction + SELECT FOR UPDATE lock",
      "Fail-closed — invalid transition = 409 error + audit",
    ],
    why: "Concurrent request-এ duplicate posting prevent করে—ledger integrity রক্ষা।",
  },
  {
    title: "SL-304 — Fiscal Period Guards",
    bullets: [
      "OPEN / SOFT_CLOSED / HARD_CLOSED period states",
      "Closed period-এ posting block",
    ],
    why: "Month-end close-এর পর accidental posting block—financial reporting accuracy।",
  },
  {
    title: "Deploy Checks — Production Gates",
    bullets: [
      "JWT secret + encryption key validation",
      "HTTPS enabled in production",
      "Required tables + cron schedules + async handlers verify",
    ],
    why: "Deploy-এর পর automatic verify—production-এ misconfiguration catch করে।",
  },
];

const futureLayers = [
  ["Frontend — Next.js + TypeScript", "Next.js-এর ভিতরেই React থাকে। SEO, Server Components, routing, PWA support built-in। Enterprise SaaS UI-এর industry standard।"],
  ["UI Design — Tailwind CSS + shadcn/ui", "Professional, accessible component library। Fast development with reusable components।"],
  ["Backend — NestJS", "Structured module architecture—RBAC, Audit, AI, Event Bus, Multi-Tenant সব module-wise organize।"],
  ["ORM — Prisma", "TypeScript code দিয়ে database access। Type safety, auto migration, fewer bugs।"],
  ["Database — PostgreSQL", "ACID transaction—accounting-এর জন্য best choice।"],
  ["AI Database — pgvector", "Vector search—AI Copilot, RAG, Smart Search, Natural Language Query।"],
  ["Cache — Redis", "Session, OTP, Permission Cache, Rate Limiting—PostgreSQL load কমায়।"],
  ["Queue — BullMQ", "Email, OCR, AI Analysis, Report—background-এ process, UI smooth।"],
  ["Storage — Cloudflare R2", "Receipt, PDF, Attachment—low cost scalable file storage + CDN।"],
  ["Search — Meilisearch", "Customer, Invoice, Vendor—instant search experience।"],
  ["AI Layer — OpenAI, Gemini, Claude", "Classification, OCR Review, Tax Hints, AI Copilot—abstraction layer দিয়ে provider change easy।"],
  ["Automation — n8n", "Invoice Paid → WhatsApp, Approval → Email, CRM Sync, AI Trigger।"],
  ["Monitoring — Grafana + Prometheus + Loki", "Production error, slow request, CPU, memory monitor।"],
  ["Tracing — OpenTelemetry", "Request-এ কোন service slow তা track করে।"],
  ["Deployment — Docker", "Dev ও production same environment—portable containers।"],
  ["CI/CD — GitHub Actions", "Push → automatic test, build, deploy।"],
];

const futureSecurityRows = [
  ["Authentication", "JWT + Google OIDC + 2FA", "Auth0/Clerk বা Keycloak; Enterprise SSO/SAML; Passkeys/WebAuthn"],
  ["Secrets Management", "Env + encrypted WP options", "HashiCorp Vault / AWS Secrets Manager; automated rotation"],
  ["Rate Limiting", "WordPress transient-based", "Redis-backed distributed rate limits"],
  ["WAF / DDoS", "Security headers (CSP, HSTS)", "Cloudflare WAF + AWS Shield Advanced"],
  ["Encryption", "AES-256-CBC plugin-level", "Field-level encryption; AWS KMS-managed keys"],
  ["Audit Log", "MySQL audit_logs table", "Immutable S3 + WORM storage; SIEM integration"],
  ["Compliance", "OWASP self-check matrix", "SOC 2 Type II; GDPR tooling; data residency"],
  ["Network Security", "HTTPS + DB TLS", "Private VPC; mTLS between microservices"],
  ["Dependency Security", "Weekly inventory cron", "Snyk/Dependabot in GitHub Actions"],
  ["Penetration Testing", "Not in MVP scope", "Annual third-party pentest; bug bounty"],
  ["Backup & Recovery", "Hosting-provider dependent", "PostgreSQL PITR; R2 versioning; DR runbook"],
  ["Multi-Tenant Isolation", "org_id query scoping", "PostgreSQL RLS; automated tenant isolation tests"],
  ["AI Security", "Provider stubs + rate limits", "Prompt injection guards; PII redaction; audit AI decisions"],
];

const currentTableRows = [
  ["Frontend", "React 19 + Vite + Tailwind 4", "UI", "Fast MVP UI inside WordPress"],
  ["Backend", "PHP + WordPress Multisite", "Business Logic", "Rapid multi-tenant host + SL spec delivery"],
  ["Database", "MySQL 8", "Data Storage", "WP-native, FOR UPDATE workflow locks"],
  ["Events", "SL-302 Outbox (MySQL)", "Domain Events", "Transactional consistency"],
  ["Queue", "SL-303 + WP Cron", "Background Jobs", "MVP async without Redis infra"],
  ["Workflow", "SL-301 State Engine", "Status Management", "Single write path, audit, locks"],
  ["AI", "Azure DI + OpenAI", "Intelligence", "Spec-aligned OCR/voice/classification"],
  ["Storage", "Local / WP Uploads", "Files", "MVP simplicity"],
  ["Auth", "JWT + OIDC + 2FA", "Security", "SL-013 compliant"],
  ["API", "REST + AJAX + OpenAPI", "Integration", "WordPress-native API surface"],
  ["PWA", "Service Worker + IndexedDB", "Mobile/Offline", "Offline receipt queue"],
  ["Testing", "PHPUnit + Jest", "Quality", "SL test coverage per module"],
  ["Deploy", "Manual PowerShell", "Release", "Current FTP-style workflow"],
  ["Monitoring", "SL-093 Internal Metrics", "Observability", "MVP health dashboard"],
  ["Cloud", "Self-hosted / Shared", "Hosting", "Low cost MVP stage"],
];

const futureTableRows = [
  ["Frontend", "Next.js", "UI", "Modern SaaS frontend"],
  ["Language", "TypeScript", "Safety", "Type-safe code"],
  ["UI", "Tailwind + shadcn/ui", "Design", "Fast professional UI"],
  ["Backend", "NestJS", "Business Logic", "Enterprise architecture"],
  ["ORM", "Prisma", "Database Access", "Easy and safe DB operations"],
  ["Database", "PostgreSQL", "Data Storage", "ACID transactions"],
  ["AI DB", "pgvector", "Vector Search", "Future AI features"],
  ["Cache", "Redis", "Performance", "Fast caching"],
  ["Queue", "BullMQ", "Background Jobs", "Smooth UX"],
  ["Storage", "Cloudflare R2", "Files", "Scalable storage"],
  ["Search", "Meilisearch", "Search", "Instant search"],
  ["AI", "OpenAI / Gemini / Claude", "Intelligence", "AI features"],
  ["Automation", "n8n", "Workflow", "Process automation"],
  ["Monitoring", "Grafana / Prometheus / Loki", "Observability", "System health"],
  ["Tracing", "OpenTelemetry", "Tracing", "Performance debugging"],
  ["Deployment", "Docker", "Containers", "Portable apps"],
  ["CI/CD", "GitHub Actions", "Automation", "Auto deployment"],
  ["Cloud", "Hetzner → DigitalOcean → AWS", "Hosting", "Growth path"],
];

function buildChildren() {
  const children = [];

  // Cover
  children.push(
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 200 },
      children: [
        new TextRun({ text: "OraBooks Stack Plan", bold: true, size: 48, font: FONT, color: ACCENT }),
      ],
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 200 },
      children: [
        new TextRun({ text: "Current (Lean MVP) ও Future (Enterprise)", bold: true, size: 32, font: FONT }),
      ],
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 400 },
      children: [
        new TextRun({ text: "TaxOra LLC · OraBooks Accounting SaaS", size: 24, font: FONT }),
      ],
    }),
    p(
      "এই document-টি OraBooks Accounting SaaS-এর technology stack plan ব্যাখ্যা করে। প্রথমে দেখানো হয়েছে আজ Lean MVP-তে কী কী technology ব্যবহার হচ্ছে এবং কী security আছে। তারপর future Enterprise stack (NestJS, Next.js, PostgreSQL ইত্যাদি) কীভাবে integrate হবে তা বর্ণনা করা হয়েছে। শেষে Current ও Future stack-এর comparison table দেওয়া আছে।",
      { size: 22 }
    ),
    p("Document Version: 1.0  |  Date: June 2026  |  Project: Lean MVP OraBooks", { size: 18, spacingAfter: 400 }),
    new Paragraph({ pageBreakBefore: true, children: [] })
  );

  // Section 1
  children.push(
    p("১. Current Stack Plan — Lean MVP (আজ যা আছে)", { heading: HeadingLevel.HEADING_1, size: 28, bold: true, color: ACCENT, spacingAfter: 200 }),
    p(
      "OraBooks Lean MVP বর্তমানে WordPress Multisite-এর উপর একটি PHP plugin হিসেবে চলছে। UI-র জন্য React 19 + Vite + Tailwind 4 ব্যবহার করা হয়। Accounting data MySQL database-এ সংরক্ষিত হয়। SL-004 থেকে SL-139 পর্যন্ত specification অনুযায়ী module implement করা হয়েছে।",
      { size: 22 }
    ),
    p("১.১ Current Technology Layers", { heading: HeadingLevel.HEADING_2, size: 24, bold: true, color: ACCENT, spacingAfter: 160 })
  );

  for (const [title, body] of currentLayers) {
    children.push(p(title, { heading: HeadingLevel.HEADING_3, size: 22, bold: true, spacingAfter: 80 }));
    children.push(p(body, { size: 20 }));
  }

  children.push(
    p("১.২ Current Architecture (Diagram)", { heading: HeadingLevel.HEADING_2, size: 24, bold: true, color: ACCENT }),
    p("Data Flow — Lean MVP:", { bold: true, size: 20 }),
    diagram([
      "  [Browser: React 19 + Vite + Tailwind UI]",
      "           |",
      "           v",
      "  [WordPress Multisite — Pages, Shortcodes, REST/AJAX]",
      "           |",
      "           v",
      "  [PHP Plugin — Posting, Workflow, RBAC, Partner, AI]",
      "       |         |              |",
      "       v         v              v",
      "  [MySQL]  [SL-302 Outbox]  [SL-303 Jobs + WP Cron]",
    ]),
    p("১.৩ Domain Modules (SL-004 → SL-139)", { heading: HeadingLevel.HEADING_2, size: 24, bold: true, color: ACCENT }),
    ...bullets(domainModules),
    new Paragraph({ pageBreakBefore: true, children: [] })
  );

  // Section 2 Security
  children.push(
    p("২. Current Security Plan (আজ যা Implement করা আছে)", { heading: HeadingLevel.HEADING_1, size: 28, bold: true, color: ACCENT, spacingAfter: 200 }),
    p(
      "OraBooks Lean MVP-তে security specification-driven design follow করা হয়েছে। নিচে প্রতিটি security layer বর্ণনা করা হয়েছে—কী আছে এবং কেন গুরুত্বপূর্ণ।",
      { size: 22 }
    )
  );

  for (const sec of securitySections) {
    children.push(p(sec.title, { heading: HeadingLevel.HEADING_2, size: 24, bold: true, color: ACCENT, spacingAfter: 100 }));
    children.push(...bullets(sec.bullets));
    children.push(p(`কেন গুরুত্বপূর্ণ: ${sec.why}`, { size: 18, spacingAfter: 160 }));
  }

  children.push(new Paragraph({ pageBreakBefore: true, children: [] }));

  // Section 3 Future
  children.push(
    p("৩. Future Stack Plan — Enterprise (Expanded Edition)", { heading: HeadingLevel.HEADING_1, size: 28, bold: true, color: ACCENT, spacingAfter: 200 }),
    p(
      "Lean MVP stable হওয়ার পর OraBooks Enterprise SaaS-এ upgrade হবে। নিচের technology stack modern, scalable এবং AI/automation-ready।",
      { size: 22 }
    )
  );

  for (const [title, body] of futureLayers) {
    children.push(p(title, { heading: HeadingLevel.HEADING_3, size: 22, bold: true, spacingAfter: 80 }));
    children.push(p(body, { size: 20 }));
  }

  children.push(
    p("৩.১ Why NestJS?", { heading: HeadingLevel.HEADING_2, size: 24, bold: true, color: ACCENT }),
    p(
      "NestJS নির্বাচন করা হয়েছে কারণ OraBooks শুধুমাত্র Accounting App নয়। এতে RBAC, Audit, Inventory, CRM, AI, Approval Workflow, Event Bus, Multi-Tenant Architecture, API Layer এবং Automation থাকবে। NestJS বড় SaaS Application-এর জন্য structured architecture দেয় এবং code maintain করা সহজ করে।",
      { size: 20 }
    ),
    p("৩.২ Why Next.js?", { heading: HeadingLevel.HEADING_2, size: 24, bold: true, color: ACCENT }),
    p(
      "Next.js-এর ভিতরেই React থাকে। Next.js SEO, Performance, Routing, Server Components, API integration, PWA support এবং enterprise SaaS UI-এর জন্য industry standard।",
      { size: 20 }
    ),
    p("৩.৩ Cloud Growth Path", { heading: HeadingLevel.HEADING_2, size: 24, bold: true, color: ACCENT }),
    ...bullets([
      "MVP / Early Stage — Hetzner: কম খরচে ভালো performance",
      "1,000 → 10,000 Users — DigitalOcean: Managed PostgreSQL, Redis, Kubernetes, Object Storage",
      "10,000+ Users — AWS: AI, Compliance, Enterprise customers—AWS ecosystem best fit",
    ]),
    p("৩.৪ Future Architecture (Diagram)", { heading: HeadingLevel.HEADING_2, size: 24, bold: true, color: ACCENT }),
    diagram([
      "  [Browser: Next.js + TypeScript + Tailwind + shadcn/ui]",
      "           |",
      "           v",
      "  [NestJS Backend — Auth, RBAC, Posting, Workflow, AI]",
      "     |    |    |    |    |    |    |",
      "     v    v    v    v    v    v    v",
      "  [Prisma] [Redis] [BullMQ] [R2] [Meili] [AI] [n8n]",
      "     |",
      "     v",
      "  [PostgreSQL + pgvector]",
      "           |",
      "           v",
      "  [OpenTelemetry → Grafana / Prometheus / Loki]",
    ]),
    new Paragraph({ pageBreakBefore: true, children: [] })
  );

  // Section 4 Future Security
  children.push(
    p("৪. Future Security Plan (Enterprise-তে আরও কী যোগ হবে)", { heading: HeadingLevel.HEADING_1, size: 28, bold: true, color: ACCENT, spacingAfter: 200 }),
    p("Current Lean MVP security solid foundation দিয়েছে। Enterprise stage-এ নিচের enhancement যোগ হবে।", { size: 22 }),
    table(["Security Area", "Current (Lean MVP)", "Future (Enterprise)"], futureSecurityRows, [20, 35, 45]),
    new Paragraph({ pageBreakBefore: true, children: [] })
  );

  // Section 5 Migration
  children.push(
    p("৫. Migration Roadmap (Current → Future)", { heading: HeadingLevel.HEADING_1, size: 28, bold: true, color: ACCENT, spacingAfter: 200 }),
    p("Phase 1 — Lean MVP (এখন)", { heading: HeadingLevel.HEADING_2, size: 24, bold: true, color: ACCENT }),
    ...bullets([
      "WordPress plugin continue — SL-001, SL-301, SL-302, SL-303 stabilize",
      "React UI inside WordPress pages",
      "MySQL database + WP Cron jobs",
      "Manual PowerShell deploy (build-live.ps1)",
      "Self-hosted / shared hosting",
    ]),
    p("Phase 2 — Hybrid (৬–১২ মাস)", { heading: HeadingLevel.HEADING_2, size: 24, bold: true, color: ACCENT }),
    ...bullets([
      "Strangler pattern: NestJS API layer extract",
      "Next.js frontend replace React-in-WP pages gradually",
      "PostgreSQL + Prisma; MySQL migration scripts",
      "Redis + BullMQ replace WP Cron workers",
      "Docker + GitHub Actions CI/CD",
      "Cloudflare R2 for file uploads",
      "DigitalOcean managed PostgreSQL + Redis",
    ]),
    p("Phase 3 — Full Enterprise (১২–২৪ মাস)", { heading: HeadingLevel.HEADING_2, size: 24, bold: true, color: ACCENT }),
    ...bullets([
      "WordPress retired or admin-only legacy shell",
      "Full NestJS + Next.js + PostgreSQL",
      "Meilisearch, pgvector, n8n, OpenTelemetry",
      "DigitalOcean → AWS migration",
      "SOC 2 compliance program",
    ]),
    p("৫.১ What Stays the Same", { heading: HeadingLevel.HEADING_2, size: 24, bold: true, color: ACCENT }),
    p(
      "Technology stack change হলেও business rules same থাকবে: posting engine logic, workflow state machines, event types, RBAC permission matrix, fiscal period rules, tax calculation rules। শুধু runtime platform change—domain knowledge preserve হয়।",
      { size: 20 }
    ),
    new Paragraph({ pageBreakBefore: true, children: [] })
  );

  // Section 6 Tables
  children.push(
    p("৬. Stack Comparison Tables", { heading: HeadingLevel.HEADING_1, size: 28, bold: true, color: ACCENT, spacingAfter: 200 }),
    p("Table A — Current Lean MVP Stack", { heading: HeadingLevel.HEADING_2, size: 24, bold: true, color: ACCENT }),
    table(["Layer", "Technology", "Purpose", "Why Selected"], currentTableRows, [15, 25, 20, 40]),
    p("Table B — Future Enterprise Stack", { heading: HeadingLevel.HEADING_2, size: 24, bold: true, color: ACCENT, spacingAfter: 160 }),
    table(["Layer", "Technology", "Purpose", "Why Selected"], futureTableRows, [15, 25, 20, 40]),
    p("৬.১ Quick Comparison Summary", { heading: HeadingLevel.HEADING_2, size: 24, bold: true, color: ACCENT }),
    p(
      "Lean MVP stack দ্রুত deliver এবং low cost-এর জন্য optimize। Future Enterprise stack scale, AI, automation এবং compliance-এর জন্য optimize। Migration gradual (strangler pattern)—business logic preserve, runtime upgrade।",
      { size: 20 }
    ),
    p("— End of Document —\nOraBooks Stack Plan v1.0 | TaxOra LLC", { size: 16, spacingAfter: 0 })
  );

  return children;
}

const doc = new Document({
  sections: [{ properties: {}, children: buildChildren() }],
});

const buffer = await Packer.toBuffer(doc);
fs.writeFileSync(OUTPUT, buffer);
console.log(`Created: ${OUTPUT}`);
console.log(`Size: ${buffer.length.toLocaleString()} bytes`);
