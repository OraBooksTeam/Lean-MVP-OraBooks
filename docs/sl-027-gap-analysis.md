# SL-027 Gap Analysis: Vendors / Bills / Accounts Payable

**Generated:** June 14, 2026  
**Status:** No dedicated implementation exists — 0% complete. Inventory module has legacy purchase tracking.  
**Implementation file:** None (`class-orabooks-vendors.php` does not exist)  
**Database tables:** 0 of 7+ spec tables created  

---

## 1. Scope Overview

SL-027 is the **Accounts Payable / Vendor Management** module. It manages:

| Domain | Responsibility |
|---|---|
| **Vendors** | Vendor master database, profiles, payment terms, credit balance tracking |
| **Bills** | Bill lifecycle (Draft → Submitted → Approved → Posted), AP sub-ledger |
| **Vendor Credit Notes** | Vendor credit note lifecycle + adjustment routing |
| **AP Payments** | Payment recording, FIFO allocation, overpayments → vendor credit balance |
| **AP Aging** | Aging buckets (Current, 1–30, 31–60, 61–90, Over 90) |
| **Reports** | AP aging reports, monthly vendor statement snapshots |

### Dependencies

| Dependency | Type | Status |
|---|---|---|
| **SL-017 (Chart of Accounts)** | Required — CoA codes for AP (2100), Expense accounts, adjustment accounts | ✅ Implemented |
| **SL-004 (Organizations)** | Required — Org ID scoping for multi-tenant | ✅ Implemented |
| **SL-013 (JWT/Auth)** | Required — Authentication for REST endpoints | ✅ Implemented |
| **SL-001 (Core/Event Bus)** | Required — Event publishing for bill lifecycle | ⚠️ Exists but not wired for SL-027 |
| **SL-003 (Security/Audit)** | Required — Audit logging for all AP operations | ✅ `orabooks_security_event` available |
| **SL-021 (Invoicing/AR)** | Mirror pattern — Same state machine, allocation, wallet patterns to follow | ✅ SL-021 class exists as reference |

### Consumers / Dependent Modules

| Consumer | Dependency On SL-027 | Status |
|---|---|---|
| **SL-052 (Voice-to-Text)** | Creates expense/bill resources consumed by SL-027 | ❌ Not integrated |
| **SL-034 (Inventory)** | Stock increase on bill posting (inventory items) | 🟡 Legacy purchase system exists |
| **SL-031 (Banking)** | Payment reconciliation with bank transactions | ❌ Not implemented |
| **SL-074/SL-075 (Reports)** | AP aging data for financial/operational reports | ❌ Not implemented |

---

## 2. Current Implementation Status

### Legacy Inventory Purchase System

The project has an **existing but non-conforming** purchase system in the Inventory module:

| File | Purpose | SL-027 Compliance |
|---|---|---|
| `OraBooks - WPMU Frontend Basic Inventory/includes/class-purchases.php` | Purchase CRUD, status updates, journal entries | ❌ Custom table, no state machine, no AP tracking |
| `OraBooks - WPMU Frontend Basic Inventory/includes/class-purchasereturn.php` | Purchase return handling | ❌ No credit note linkage |
| `OraBooks - WPMU Frontend Basic Inventory/templates/contact/suppliers.php` | Supplier list UI | 🟡 Supplier master exists but not integrated |
| `OraBooks - WPMU Frontend Basic Inventory/templates/contact/supplier-pay.php` | Supplier payment UI | 🟡 Payment recording exists but no FIFO allocation |
| `OraBooks - WPMU Frontend Basic Inventory/templates/reports/supplier-due-report.php` | Due payment report | 🟡 Basic due reporting exists |
| `OraBooks - WPMU Frontend Basic Inventory/templates/reports/supplier-payment-report.php` | Payment history report | 🟡 Basic payment reporting exists |

### Key Gaps in the Legacy System

| Gap | Legacy Behavior | SL-027 Spec Requirement |
|---|---|---|
| **State machine** | Custom statuses (`pending`, `processing`, `completed`, `cancelled`) | Fixed: `draft` → `submitted` → `approved` → `posted` |
| **AP sub-ledger** | No AP tracking at all | Dedicated AP balance tracking per vendor |
| **Journal entries** | `create_purchase_journal_entry()` exists but may not use standard CoA | Must use SL-017 CoA codes (AP 2100, etc.) |
| **Payment allocation** | Simple payment recording | FIFO allocation with `payment_allocations` table |
| **Vendor credit balance** | Not tracked | Dedicated `vendor_credit_balance` with auto-apply toggle |
| **Vendor credit notes** | No credit note system | Full `vendor_credit_notes` lifecycle |
| **AP aging** | Supplier due report exists | Standardized aging buckets |
| **Bill numbering** | Purchase code generation exists | `BILL-{YYYY}-{seq}` format required |
| **Idempotency** | Not tracked | `idempotency_key` on bills table |

### Membership Plugin Permissions

The **TaxOra Membership** plugin already has permission scaffolding for SL-027:

| File | Capability | Status |
|---|---|---|
| `class-orabooks-membership-permissions.php` | `bills_payments` → `create_transaction` | ✅ Permissions defined |
| `class-orabooks-acl-endpoints.php` | `/api/bill/` route listed | ✅ API route slot reserved |
| `class-orabooks-subscription-plans.php` | `bills_payments` in feature limits | ✅ Feature limit defined |
| `class-orabooks-addon-system.php` | Supplier/purchase monthly limits | ✅ Limits configured |

---

## 3. Gap Analysis

### 🔴 Critical Gaps (8 items)

| # | Gap | Spec Requirement | Current State | Impact | Effort |
|---|---|---|---|---|---|
| **A** | **No vendor/bill class** | Dedicated `class-orabooks-vendors.php` with singleton pattern, table creation, state machine | **Nothing exists** — no class, no methods, no hooks | Entire module must be built from scratch | **5-7 days** |
| **B** | **No vendors table** | `orabooks_vendors` with: org_id, vendor_name, contact, payment_terms, auto_apply_credit, credit_balance, audit columns | **Legacy `suppliers`** in Inventory module, not compatible | No standardized vendor master | **1 day** |
| **C** | **No bills table** | `orabooks_bills` with: org_id, bill_number, vendor_id, status, payment_status, lock_status, line_items, subtotal/discount/tax/total, paid_amount, balance_due, due_date, je_id, snapshot, idempotency_key, audit columns | **Legacy `purchases`** table in Inventory module, different schema | No AP sub-ledger possible | **1 day** |
| **D** | **No bill state machine** | Draft → Submitted → Approved → Posted with VALID_TRANSITIONS enforcement, payment status tracking, lock status | **Custom statuses** in purchases class | Cannot implement approval workflows | **1 day** |
| **E** | **No AP aging** | Standardized aging buckets (Current, 1-30, 31-60, 61-90, Over 90) with per-vendor breakdown | **Legacy supplier-due-report** exists but not standardized | No reliable AP aging for financial reports | **1-2 days** |
| **F** | **No vendor credit notes** | `orabooks_vendor_credit_notes` table with lifecycle, adjustment routing to `vendor_adjustment_account` | **Not implemented** — no credit note system for purchases | Cannot adjust/credit vendor bills | **2 days** |
| **G** | **No payment allocation** | FIFO allocation via `orabooks_payment_allocations` table (shared with SL-021) | **Legacy payment recording** without FIFO | Cannot track which bills are paid | **1-2 days** |
| **H** | **No JE integration** | Standard AP JE: Dr Expense/Inventory, Cr AP (2100). Credit note: Dr AP, Cr Expense. Adjustments: Dr AP, Cr `vendor_adjustment_account` | **Legacy `create_purchase_journal_entry()`** exists but may use wrong CoA codes | Accounting will be inconsistent with GL | **1-2 days** |

### 🟡 Medium Gaps (5 items)

| # | Gap | Spec Requirement | Current State | Effort |
|---|---|---|---|---|
| **I** | **No vendor payment reversal** | `reverse_payment()` creates reversal entry, restores bill balance | **Not implemented** | **1 day** |
| **J** | **No auto-post configuration** | `auto_post_bill_on_approve` company setting; if true, Approved → Posted automatically | **Not implemented** | **0.5 day** |
| **K** | **No adjustment threshold** | `adjustment_threshold` config; adjustments above threshold require secondary approval | **Not implemented** | **1 day** |
| **L** | **No vendor statement snapshots** | Monthly statement generation per vendor with snapshot storage | **Not implemented** | **1-2 days** |
| **M** | **No bill numbering** | `BILL-{YYYY}-{seq}` sequential per org, gaps logged | **Legacy purchase code generation** exists but format differs | **0.5 day** |

### 🟢 Minor Gaps (4 items)

| # | Gap | Spec Requirement | Current State | Effort |
|---|---|---|---|---|
| **N** | **No vendor credit limit/hold** | Future: vendor credit limits, credit holds on vendors | **Not implemented** | **1 day** |
| **O** | **No purchase order linkage** | Future: link bills to purchase orders for PO-based billing | **Not implemented** | **2-3 days** |
| **P** | **No early payment discounts** | Future: discount terms for early payment | **Not implemented** | **1 day** |
| **Q** | **No ACH automation** | Future: automated ACH payments | **Not implemented** | **2-3 days** |

---

## 4. State Machine Analysis

### Bill Status Transitions (Spec)

```
                    ┌──────────┐
                    │  DRAFT   │
                    └────┬─────┘
                         │ submit
                    ┌────▼─────┐
               ┌───│ SUBMITTED │◄──── return_to_draft
               │   └────┬─────┘
               │        │ approve
          ┌────▼───┐   ┌▼────────┐
          │ VOIDED │   │ APPROVED│◄──── return_to_draft
          └────────┘   └────┬────┘
                            │ auto-post (configurable)
                      ┌─────▼──────┐
                      │   POSTED   │  Terminal — immutable
                      └────────────┘
```

**Implementation status:** ❌ **0% complete**. The legacy purchase system uses custom statuses.

### Payment Status Transitions

```
Unpaid → Partial → Paid → Credited (after vendor credit note)
```

**Implementation status:** ❌ **0% complete**.

### Lock Status

```
Unlocked → Locked (after posting or full payment)
```

**Implementation status:** ❌ **0% complete**.

---

## 5. Journal Entry Specifications

### Bill Posting (Approved → Posted)

| Account | Debit | Credit |
|---|---|---|
| Expense Account (configurable per line item) | line item amount | — |
| Inventory (CoA 1200) — if inventory item | item cost | — |
| AP (CoA 2100) | — | `total` |

### Vendor Credit Note Posting (Draft → Posted)

| Account | Debit | Credit |
|---|---|---|
| AP (CoA 2100) | `amount` | — |
| Expense/Inventory (original account) | — | `amount` |

### Vendor Adjustment Posting

| Account | Debit | Credit |
|---|---|---|
| AP (CoA 2100) | `amount` | — |
| `vendor_adjustment_account` (configurable) | — | `amount` |

### Payment Recording

| Account | Debit | Credit |
|---|---|---|
| AP (CoA 2100) | `payment_amount` | — |
| Cash/Bank Account | — | `payment_amount` |

**Implementation status:** ❌ **0% complete**. Legacy `create_purchase_journal_entry()` exists but needs verification against SL-017 CoA codes.

---

## 6. Database Schema Specification

### Required Tables

| # | Table | Status | Spec Columns |
|---|---|---|---|
| 1 | `orabooks_vendors` | ❌ Not created | org_id, vendor_name, contact_name, email, phone, address, payment_terms, auto_apply_credit, credit_balance, is_active, audit columns |
| 2 | `orabooks_bills` | ❌ Not created | org_id, bill_number (UNIQUE), vendor_id, status, payment_status, lock_status, line_items (JSON), subtotal/discount/tax/total, paid_amount, balance_due, due_date, je_id, snapshot, idempotency_key, source/audit columns |
| 3 | `orabooks_vendor_credit_notes` | ❌ Not created | org_id, credit_note_number (UNIQUE), bill_id, vendor_id, status, amount, remaining_credit, is_adjustment, adjustment_account_code, reason, je_id, snapshot, audit columns |
| 4 | `orabooks_vendor_wallet` | ❌ Not created | org_id, vendor_id, current_balance, credit_balance, auto_apply_credit, last_activity_at, audit columns |
| 5 | `orabooks_payment_allocations` | ✅ Shared with SL-021 | Already exists — can be reused with `allocation_type='ap_payment'` |
| 6 | `orabooks_ap_aging` | ❌ Not created | org_id, vendor_id, aging_bucket, amount, as_of_date, snapshot |
| 7 | `orabooks_vendor_statements` | ❌ Not created | org_id, vendor_id, statement_date, snapshot (JSON), generated_at |

---

## 7. Architecture Comparison: SL-021 vs SL-027

SL-027 should closely mirror the SL-021 (Invoices/AR) architecture, with analogous components:

| Component | SL-021 (Implemented) | SL-027 (Needed) | Reuse Potential |
|---|---|---|---|
| **State machine** | `STATUS_DRAFT`, `STATUS_SUBMITTED`, `STATUS_APPROVED`, `STATUS_POSTED`, `STATUS_VOID` | Identical pattern | 🔄 Copy pattern |
| **Payment status** | `UNPAID`, `PARTIAL`, `PAID`, `OVERPAID`, `WRITTEN_OFF` | Same + `CREDITED` | 🔄 Copy + add CREDITED |
| **Payment allocation** | `orabooks_payment_allocations` table | Same table with `allocation_type='ap_payment'` | ✅ Reuse |
| **Credit notes** | `orabooks_credit_notes` | `orabooks_vendor_credit_notes` | 🔄 Copy + add adjustment fields |
| **Wallet** | `orabooks_customer_wallet` | `orabooks_vendor_wallet` | 🔄 Copy pattern |
| **Aging** | `get_ar_aging()` | `get_ap_aging()` | 🔄 Copy pattern |
| **JE posting** | Dr AR, Cr Revenue | Dr Expense, Cr AP | 🔄 Reverse pattern |
| **Number generation** | `INV-YYYYMMDD-XXXX` | `BILL-YYYY-XXXX` | 🔄 Copy + change format |
| **REST API** | ❌ Not implemented | ❌ Not implemented | Both need it |

---

## 8. Recommendations

### Priority Order

| Priority | Gap | Rationale | Estimated Effort |
|---|---|---|---|
| **P0** | **A+B+C: Core class + tables** | Foundation — everything depends on this | 2-3 days |
| **P0** | **D+H: State machine + JE** | Core business logic — bill lifecycle + accounting | 2 days |
| **P1** | **F+G: Credit notes + allocation** | Essential for adjustments and payment tracking | 2 days |
| **P1** | **E: AP aging** | Required for financial reporting | 1 day |
| **P2** | **J+K: Configuration** | Auto-post, adjustment thresholds | 0.5 day |
| **P2** | **I: Payment reversal** | Completes payment lifecycle | 1 day |
| **P3** | **L+M: Statements + numbering** | Nice-to-have reporting | 1 day |

### Build Strategy

**Option 1: Greenfield in Membership Plugin (Recommended)**

Build `class-orabooks-vendors.php` in `TaxOra - WPMU Membership Subscription Panel/includes/` following the exact same architecture as `class-orabooks-invoices.php`:

```
class-orabooks-vendors.php     ← NEW (mirrors class-orabooks-invoices.php)
├── instance/init/hooks           ← Copy singleton pattern
├── create_vendor_tables()        ← 7 new tables
├── register_table_names()        ← Multisite compatibility
├── Vendor CRUD                   ← create/get/update vendor
├── Bill CRUD + state machine     ← Draft→Submitted→Approved→Posted
├── Vendor credit notes           ← Draft→Posted→Void + adjustments
├── Vendor wallet                 ← credit_balance, auto-apply
├── Payment recording + reversal  ← FIFO allocation
├── AP aging                      ← 5 aging buckets
└── Number generation             ← BILL-YYYY-XXXX
```

**Effort estimate:** 5-7 days for complete implementation.

**Option 2: Migrate Legacy Inventory Purchase System**

Refactor `class-purchases.php` to use SL-027 tables and state machine. Higher risk due to schema changes but reuses existing UI templates.

**Effort estimate:** 4-6 days (riskier due to migration complexity).

### Quick Wins (≤1 day)

| Gap | Effort | Value |
|---|---|---|
| **J: Auto-post config** | 0.5 day | Complements approval workflow |
| **M: Bill numbering** | 0.5 day | Standardizes document IDs |
| **N: Vendor credit limit** | 1 day | Risk management |

---

## 9. Technical Debt & Risks

| Risk | Description | Mitigation |
|---|---|---|
| **Legacy purchase system** | Inventory module has its own purchases/suppliers with custom schema | Decision needed: migrate or coexist? |
| **No event bus integration** | SL-027 needs to publish bill lifecycle events | Follow SL-021 pattern using `do_action()` |
| **Table naming collision** | Legacy `purchases` table in Inventory module may conflict | Use `orabooks_bills` prefix per spec |
| **Permissions already defined** | `bills_payments` permission exists but no code implements it | Wire permission checks into new class |
| **API route reserved** | `/api/bill/` route already in ACL list | Implement REST endpoints to match |
| **Zero test coverage** | No SL-027 tests exist anywhere | Add tests during implementation |

---

## 10. Key Differences from SL-021

While SL-027 mirrors SL-021, there are important differences:

| Aspect | SL-021 (AR) | SL-027 (AP) | Why |
|---|---|---|---|
| **JE direction** | Dr Asset (AR↑), Cr Revenue | Dr Expense, Cr Liability (AP↑) | Accounting — opposite sides |
| **State machine** | Draft→Submitted→Approved→Posted | Same + configurable auto-post | AP may auto-post on approval |
| **Credit notes** | Standard reversal | Standard reversal + **adjustments** with configurable account | Vendor adjustments may route to different GL |
| **Wallet** | Customer owes us (asset) | We owe vendor (liability) | Opposite balance direction |
| **Lock status** | Posted = locked automatically | Posted = locked + fully paid = locked | Additional lock on full payment |
| **Bill numbering** | `INV-YYYYMMDD-XXXX` | `BILL-YYYY-XXXX` | Fiscal year-based, not date-based |
| **Idempotency** | Not required | `idempotency_key` column needed | Prevents duplicate bill creation |
| **Adjustment threshold** | N/A | Configurable approval threshold | Controls small vs large adjustments |
