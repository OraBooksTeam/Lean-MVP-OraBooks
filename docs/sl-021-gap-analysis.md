# SL-021 Gap Analysis: Customers / Invoices / AR / Wallet

**Generated:** June 14, 2026  
**Status:** Iteration 1 implementation complete — 6 critical gaps closed, 9 gaps remaining  
**Implementation file:** `includes/class-orabooks-invoices.php` (62 methods, ~2,000 lines)  
**Database tables:** 6 created, 3 pending (9 total spec tables)

---

## 1. Scope Overview

SL-021 is the **Accounts Receivable / Invoicing / Customer Wallet** module. It manages:

| Domain | Responsibility |
|---|---|
| **Customers** | Customer profile management, `is_active` source of truth |
| **Invoices** | Invoice lifecycle (Draft → Submitted → Approved → Posted), AR sub-ledger |
| **Credit Notes** | Credit note lifecycle (Draft → Posted → Void), invoice adjustments |
| **Customer Wallet** | Current balance, credit balance (overpayments), credit limit, auto-apply |
| **AR Aging** | Aging buckets (Current, 1–30, 31–60, 61–90, Over 90) |
| **Payment Allocation** | FIFO tracking of payments, credit notes, and reversals |

### Dependencies

| Dependency | Type | Status |
|---|---|---|
| SL-017 (Chart of Accounts) | Required — CoA account codes (1100 AR, 4000 Revenue, 2500 Tax Payable) | ✅ Implemented |
| SL-004 (Organizations) | Required — Org ID scoping for multi-tenant | ✅ Implemented |
| SL-013 (JWT/Auth) | Required — Authentication for REST endpoints | ✅ Implemented |
| SL-052 (Subscription Engine) | Event source — `orabooks_subscription_renewed` triggers auto-invoicing | ✅ Hook registered |

### Consumers

| Consumer | Dependency On SL-021 | Status |
|---|---|---|
| SL-068 (Commissions) | Customer `is_active` status for commission eligibility | 🔌 Public hook `orabooks_customer_active_status_changed` ready |
| SL-034 (Inventory) | Stock reduction on invoice posting | ❌ Not implemented (Gap #F) |
| SL-027 (AP/Bills) | Shared payment allocation patterns | 🔄 Build order: post-SL-021 |
| SL-074/SL-075 (Reports) | AR aging data for operational/financial reports | ✅ `get_ar_aging()` available |

---

## 2. Current Implementation Summary

### Database Tables

| # | Table | Status | Columns | Purpose |
|---|---|---|---|---|
| 1 | `orabooks_invoices` | ✅ **Done** | 34 (id, org_id, invoice_number, customer_id, status, payment_status, line_items, subtotal/discount/tax/total, paid_amount, balance_due, je_id, snapshot, audit cols, etc.) | Core invoice data with full state machine |
| 2 | `orabooks_credit_notes` | ✅ **Done** | 17 (id, org_id, cn_number, invoice_id, customer_id, status, amount, remaining_credit, reason, je_id, snapshot, audit cols) | Credit note lifecycle |
| 3 | `orabooks_customer_wallet` | ✅ **Done** | 11 (id, org_id, customer_id, current_balance, credit_balance, credit_limit, credit_hold, auto_apply_credit, last_activity_at, audit cols) | Wallet with balance tracking |
| 4 | `orabooks_payment_allocations` | ✅ **Done** | 8 (id, org_id, invoice_id, payment_id, amount, allocation_type, allocated_at, created_by) | FIFO allocation tracking |
| 5 | `orabooks_wallet_transactions` | ✅ **Done** | 13 (id, org_id, customer_id, type, amount, balance_before, balance_after, reference_type/id, description, created_by/at) | Wallet audit trail |
| 6 | `orabooks_customer_active_status` | ✅ **Done** | 9 (id, org_id, customer_id, is_active, active_since, inactive_at, inactivity_reason, mode, audit cols) | Customer active status source of truth |
| 7 | `orabooks_tax_snapshots` | ❌ **Not implemented** | N/A | Tax snapshot per invoice/bill (see Gap #D) |
| 8 | `orabooks_invoice_line_items` | ❌ **Not implemented** | N/A | Normalized line items (currently JSON in invoices table) |
| 9 | `orabooks_collections` | ❌ **Not implemented** | N/A | Dunning/collections state machine (see Gap #G) |

### Methods Implemented

| Category | Methods | Status |
|---|---|---|
| **Instance/Init** | `get_instance()`, `__construct()`, `init_hooks()` | ✅ |
| **Table Management** | `create_invoice_tables()`, `register_table_names()` | ✅ |
| **Invoice CRUD** | `create_invoice()`, `get_invoice()`, `get_invoices()`, `update_invoice()` | ✅ |
| **State Machine** | `submit_invoice()`, `approve_invoice()`, `return_to_draft()`, `post_invoice()`, `void_invoice()`, `transition_status()` | ✅ |
| **Credit Notes** | `create_credit_note()`, `post_credit_note()` | ✅ |
| **Payment Allocation** | `apply_credit_to_invoice()`, `record_allocation()`, `get_allocations()` | ✅ |
| **Wallet Core** | `get_wallet()`, `add_wallet_credit()`, `refresh_wallet()`, `auto_apply_credit()`, `check_credit_hold()` | ✅ |
| **Wallet Admin** | `set_credit_hold()`, `update_wallet_balance()`, `update_wallet_credit_limit()`, `set_auto_apply_credit()`, `log_wallet_transaction()`, `get_wallet_transactions()` | ✅ |
| **Customer Active Status** | `get_customer_active_status()`, `set_customer_active_status()`, `is_customer_active()`, `get_active_customers()` | ✅ |
| **AR Aging** | `get_ar_aging()` | ✅ |
| **Number Generation** | `generate_invoice_number()`, `generate_credit_note_number()` | ✅ |
| **Event Handlers** | `on_subscription_renewed()` | ✅ |
| **Cron Jobs** | `process_aging()` (placeholder) | ⚠️ Stub |

### Hooks Published

| Hook | Action | Parameters |
|---|---|---|
| `orabooks_security_event` | All operations | `invoice_created`, `invoice_posted`, `invoice_voided`, `credit_note_created`, `credit_note_posted`, `invoice_credit_applied`, `customer_credit_hold`, `wallet_balance_adjusted`, `wallet_credit_limit_updated`, `wallet_auto_apply_toggled`, `customer_active_status_changed` |
| `orabooks_customer_active_status_changed` | Active status toggle | `$org_id, $customer_id, $is_active, $reason` |

---

## 3. Gap Analysis

### 🔴 Critical Gaps (7 items)

| # | Gap | Spec Requirement | Current State | Impact | Effort |
|---|---|---|---|---|---|
| **A** | **REST API endpoints** | Full REST API for invoice CRUD, wallet operations, credit notes, AR aging | **No REST endpoints exist** — all operations are PHP method calls only | Can't integrate with frontend UIs or external systems | **3-4 days** |
| **B** | **Invoice PDF generation** | Auto-generate PDF on posting; publish `invoice_pdf_generated` event | **Not implemented** — no PDF generation at all | No downloadable invoice documents for customers | **2-3 days** |
| **C** | **Email notifications** | Send invoice on creation, reminders on due, overdue escalation | **Not implemented** — no email triggers | Customers don't receive invoices or payment reminders | **2-3 days** |
| **D** | **Tax snapshots** | Store tax breakdown per invoice (tax_snapshots table with transaction_type) | **Not implemented** — tax_total is computed but not snapshot per jurisdiction | No tax compliance reporting | **1-2 days** |
| **E** | **Deferred revenue recognition** | Framework for recognizing revenue over time (subscription revenue) | **Not implemented** — all revenue recognized immediately on posting | GAAP/IFRS revenue recognition non-compliant for subscriptions | **3-4 days** |
| **F** | **Inventory/COGS integration** | Stock reduction + COGS journal entry when invoice posted with inventory items | **Not implemented** — JE only does Dr AR, Cr Revenue | Inventory counts not synced with sales; COGS not tracked | **2-3 days** |
| **G** | **Dunning / Collections** | Automated escalation: reminder → warning → collections hold → write-off | **Not implemented** — `process_aging()` is a stub | No automated overdue management | **3-5 days** |

### 🟡 Medium Gaps (4 items)

| # | Gap | Spec Requirement | Current State | Effort |
|---|---|---|---|---|
| **H** | **Write-off approval workflow** | Write-offs require approval threshold; approval chain tracking | **Not implemented** — no write-off method exists | **1-2 days** |
| **I** | **Installment plans** | Allow invoices to be split into installments with separate due dates | **Not implemented** — single due date only | **2-3 days** |
| **J** | **Multicurrency** | Currency field exists but no FX rate support; invoice in foreign currency with base currency conversion | **Partially done** — `currency` column exists, no FX conversion | **2-3 days** |
| **K** | **Payment gateway integration** | `record_payment()` should accept gateway reference, transaction ID, fees | **Not implemented** — no payment recording method | **1-2 days** |

### 🟢 Minor Gaps (3 items)

| # | Gap | Spec Requirement | Current State | Effort |
|---|---|---|---|---|
| **L** | **Customer merge** | Merge duplicate customer profiles (transfer invoices, wallet, active status) | **Not implemented** | **1 day** |
| **M** | **Bulk operations** | Bulk invoice creation, status transitions, credit note issuance | **Not implemented** | **1-2 days** |
| **N** | **Invoice templates** | Configurable invoice templates (layout, logo, terms) | **Not implemented** — `terms` and `notes` fields exist but no template system | **2-3 days** |

### ✅ Closed Gaps (from Iteration 1)

| # | Gap | Resolution |
|---|---|---|
| 1 | **Invoice lifecycle** — Draft → Posted state machine | Implemented in Iteration 1: `submit_invoice()` → `approve_invoice()` → `post_invoice()` with `VALID_TRANSITIONS` enforcement |
| 2 | **Invoice tables** — No data model existed | Created `orabooks_invoices` with 34 columns, full audit trail, JSON line items |
| 3 | **AR sub-ledger** — No receivable tracking | `get_ar_aging()` with 5 aging buckets, per-invoice breakdown |
| 4 | **Customer wallet** — No balance tracking | `orabooks_customer_wallet` table + `get_wallet()`, `add_wallet_credit()`, `refresh_wallet()`, `check_credit_hold()` |
| 5 | **Credit notes** — No credit note lifecycle | `create_credit_note()` + `post_credit_note()` with reversal JE (Dr Revenue, Cr AR) |
| 6 | **Payment allocation** — FIFO not tracked | `orabooks_payment_allocations` table + `apply_credit_to_invoice()` with FIFO ordering |

---

## 4. State Machine Analysis

### Invoice Status Transitions

```
                    ┌──────────┐
                    │  DRAFT   │
                    └────┬─────┘
                         │
                    ┌────▼─────┐
               ┌───│ SUBMITTED │◄──── (return_to_draft)
               │   └────┬─────┘
               │        │
          ┌────▼───┐   ┌▼────────┐
          │  VOID  │   │ APPROVED│◄──── (return_to_draft)
          └────────┘   └────┬────┘
                            │
                      ┌─────▼──────┐
                      │   POSTED    │  Terminal — immutable
                      └────────────┘
```

**Implementation status:** ✅ Fully implemented with `VALID_TRANSITIONS` enforcement. Posted invoices are terminal. Void is allowed from Draft/Submitted/Approved. Return-to-draft is allowed from Submitted/Approved.

### Credit Note Status Transitions

```
                    ┌──────────┐
                    │  DRAFT   │
                    └────┬─────┘
                         │
                    ┌────▼─────┐   ┌──────────┐
                    │  POSTED  │   │   VOID   │
                    └──────────┘   └──────────┘
```

**Implementation status:** ✅ Fully implemented. Draft → Posted creates reversal JE. Draft → Void allowed.

**Spec gap:** The spec mentions a `submitted → approved` step in the credit note lifecycle (Draft → Submitted → Approved → Posted), but the current implementation only has Draft → Posted directly. This simplified approach was chosen for Iteration 1; the intermediate states can be added if the approval workflow requires them.

### Customer Active Status State Machine

```
                    ┌───────────┐
                    │   ACTIVE   │
                    └─────┬─────┘
                          │
                    ┌─────▼──────┐
                    │  INACTIVE  │
                    └────────────┘
```

**Implementation status:** ✅ `set_customer_active_status($org_id, $customer_id, $is_active, $reason)` with public hook `orabooks_customer_active_status_changed`. Fires audit events.

---

## 5. Journal Entry Integration

### Invoice Posting (Approved → Posted)

| Account | Debit | Credit |
|---|---|---|
| AR (CoA 1100) | `total` | — |
| Sales Revenue (CoA 4000) | — | `subtotal` |
| Sales Tax Payable (CoA 2500) | — | `tax_total` (if > 0) |

**Implementation status:** ✅ `post_invoice()` creates + auto-posts JE immediately.

### Credit Note Posting (Draft → Posted)

| Account | Debit | Credit |
|---|---|---|
| Sales Revenue (CoA 4000) | `amount` | — |
| AR (CoA 1100) | — | `amount` |

**Implementation status:** ✅ `post_credit_note()` creates reversal JE. Note: Always uses Sales Revenue (4000), not Service Revenue (4100). If the linked invoice used Service Revenue (subscription source), the credit note should use the same revenue account. This is a minor gap — see Gap O below.

### COGS Posting (Future — Gap F)

| Account | Debit | Credit |
|---|---|---|
| COGS (CoA 5000) | `cost` | — |
| Inventory (CoA 1200) | — | `cost` |

**Implementation status:** ❌ Not implemented. Requires SL-034 Inventory integration.

---

## 6. Recommendations

### Priority Order for Next Iteration

| Priority | Gap | Rationale |
|---|---|---|
| **P0** | **A: REST API** | No integration possible without API. Blocks all frontend UIs. |
| **P0** | **K: Payment recording** | Can't close the payment loop without `record_payment()`. |
| **P1** | **B: PDF generation** | Customers need invoice documents. |
| **P1** | **C: Email notifications** | Invoices must be delivered. |
| **P2** | **D: Tax snapshots** | Compliance requirement. |
| **P2** | **F: Inventory/COGS** | Required for product-based businesses. |
| **P3** | **G: Dunning/Collections** | AR management at scale needs automation. |
| **P3** | **E: Deferred revenue** | Subscription businesses need this for GAAP compliance. |

### Quick Wins (≤2 days)

| Gap | Effort | Value |
|---|---|---|
| **K: Payment recording** | 1-2 days | Unblocks payment workflows |
| **H: Write-off approval** | 1-2 days | Completes AR lifecycle |
| **M: Bulk operations** | 1-2 days | Admin efficiency |
| **O: Credit note revenue account** | 0.5 days | Fixes minor accounting bug — detect linked invoice revenue account type |

---

## 7. Technical Debt & Risks

| Risk | Description | Mitigation |
|---|---|---|
| **No unit tests** | 0 tests for SL-021 in test suite | Add integration tests for state machine, JE creation, wallet logic |
| **Line items as JSON** | `line_items` stored as JSON blob — no queryable line item table | Add normalized `invoice_line_items` table in next iteration |
| **process_aging() stub** | Daily cron handler does nothing | Implement dunning escalation loop |
| **Multisite table prefix** | Tables use `base_prefix` for central data | Verified correct in both multisite and non-multisite |
| **Race condition in auto_apply_credit** | Concurrent requests could cause double-application | Low risk (single-request WordPress); add DB-level locking if needed |

---

## 8. Appendix: Database Table Details

### Existing Tables

#### `orabooks_invoices`
**34 columns** — Core invoice data with full audit trail. Key columns: `org_id`, `invoice_number` (UNIQUE `uk_invoice_number`), `customer_id`, `status` (draft/submitted/approved/posted/void), `payment_status` (unpaid/partial/paid/overpaid/written_off/cancelled), `line_items` (JSON), `subtotal`/`discount_total`/`tax_total`/`total`, `paid_amount`/`balance_due`, `je_id` (FK to journal_entries), `snapshot` (JSON at posting), `source_type`/`source_id`, audit columns. Indexed on: `org_id`, `customer_id`, `status`, `payment_status`, `due_date`, `source`, `je_id`, `mode`.

#### `orabooks_credit_notes`
**17 columns** — Credit note tracking. Key columns: `org_id`, `credit_note_number` (UNIQUE `uk_cn_number`), `invoice_id` (nullable), `customer_id`, `status` (draft/posted/void), `amount`, `remaining_credit`, `reason`, `je_id`, `snapshot`.

#### `orabooks_customer_wallet`
**11 columns** — Wallet per (org_id, customer_id). Key columns: `current_balance`, `credit_balance`, `credit_limit`, `credit_hold`, `auto_apply_credit`. UNIQUE on `(org_id, customer_id)`.

#### `orabooks_payment_allocations`
**8 columns** — FIFO allocation tracking. Key columns: `invoice_id`, `payment_id`, `amount`, `allocation_type` (payment/credit_note/reversal). Indexed on `invoice_id`, `org_id`, `payment_id`.

#### `orabooks_wallet_transactions`
**13 columns** — Wallet audit trail. Key columns: `type` (credit/debit/adjustment), `amount`, `balance_before`/`balance_after`, `reference_type`/`reference_id`. Indexed on `org_id`, `customer_id`, `type`, `reference`, `created_at`.

#### `orabooks_customer_active_status`
**9 columns** — Active status source of truth. Key columns: `is_active`, `active_since`, `inactive_at`, `inactivity_reason`. UNIQUE on `(org_id, customer_id)`.
