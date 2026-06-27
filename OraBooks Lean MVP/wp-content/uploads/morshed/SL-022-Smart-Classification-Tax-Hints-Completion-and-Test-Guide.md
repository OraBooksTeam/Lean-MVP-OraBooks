# SL-022 Smart Classification & Tax Hints [AI] - Completion and Test Guide

Prepared for: Morshed
Project: OraBooks Lean MVP
Date: 2026-06-27

## 1) Executive Summary
SL-022 (Smart Classification & Tax Hints [AI]) implementation is complete for Lean MVP scope.

Verification snapshot:
- Central classification engine exists (rule-first + AI provider fallback/stub).
- Tax hint generation and confidence/risk output are implemented.
- Async trigger path and event-driven classification request path are implemented.
- Apply / rerun / override flows are available through AJAX + frontend UI.
- Fresh SL-022 related automated tests passed.

Status: READY FOR MVP SIGN-OFF (within SL-022 defined scope)

## 2) What Has Been Completed in SL-022

### 2.1 Core Classification Engine
- Central class implemented for expense, invoice, and journal_line record types.
- Rule table + default seed rules + idempotency protections implemented.
- Sync preview/run and async request modes are available.

### 2.2 AI Provider Integration
- Classification capability routing supports provider selection.
- If real AI provider is configured, chat-based account suggestion is used.
- If AI provider fails/unavailable, deterministic stub fallback is applied.

### 2.3 Tax Hints and Risk/Confidence Output
- Classification result includes tax hints payload (tax_type/tax_rate/jurisdiction pattern).
- Confidence score and low-confidence flag are generated.
- Risk score structure is produced and stored.
- Version fields are stored for model/tax engine traceability.

### 2.4 Apply / Override Governance
- Apply endpoint updates target records from processed suggestions.
- Override endpoint enforces allowed reason codes.
- Missing/invalid override reason is rejected.
- Manual override logs are captured.

### 2.5 Access Control and Safety
- View/manage checks are RBAC guarded.
- Duplicate request handling via idempotency returns conflict.
- Failed classification path marks status and stores reason.

### 2.6 Async + Event Wiring
- classification_requested event consumer is registered.
- Async queue job handler classify_transaction is registered.
- Completion event publishing path exists after successful classification.

### 2.7 Frontend UX for SL-022
- Expense page shows AI Classification panel.
- Suggested account, confidence badge, tax hint, reason are visible.
- Buttons exist for Apply AI suggestions and Rerun classification.
- Low-confidence warning is surfaced in UI.

## 3) Evidence Used for Completion Check

### 3.1 Core SL-022 Files
- includes/class-orabooks-classification.php
- includes/class-orabooks-ai-providers.php

### 3.2 Frontend/API Evidence
- orabooks-ui/src/pages/frontend/api.ts
- orabooks-ui/src/pages/frontend/pages/ExpensesPage.tsx

### 3.3 Automated Test Evidence (Executed)
Command used:
D:\xampp\php\php.exe tests\vendor\phpunit\phpunit\phpunit --configuration tests\phpunit.xml tests\OraBooks_Classification_Test.php tests\OraBooks_Ai_Providers_Test.php

Result:
- 27 tests
- 100 assertions
- PASS

## 4) How to Test SL-022 (Step by Step)

## 4.1 Automated Test Run
From project root (OraBooks Lean MVP):
1. Open terminal.
2. Run:
D:\xampp\php\php.exe tests\vendor\phpunit\phpunit\phpunit --configuration tests\phpunit.xml tests\OraBooks_Classification_Test.php tests\OraBooks_Ai_Providers_Test.php
3. Confirm output shows PASS.

Expected:
- No failures
- No errors

## 4.2 Manual Functional Verification

### A) Run Classification (Expense)
1. Open Expenses page.
2. Select one draft expense.
3. Trigger Rerun classification.

Expected:
- Classification panel updates status.
- Suggested account appears.
- Confidence value appears.
- Tax hint appears.

### B) Apply Suggestion
1. For processed classification, click Apply AI suggestions.

Expected:
- Expense fields update according to suggestion/tax hint.
- Success confirmation appears.

### C) Override Flow Validation
1. Try override without reason code.
2. Then override with allowed reason code (for example WRONG_AI_CLASSIFICATION).

Expected:
- Without reason: request rejected.
- With valid reason: classification status overridden and reason stored.

### D) Idempotency / Duplicate Request Guard
1. Request classification repeatedly with same idempotency key payload.

Expected:
- Duplicate request is blocked/conflicted (no uncontrolled duplicate processing).

### E) Low Confidence Review Signal
1. Use sample data likely to produce weak match.

Expected:
- low_confidence=true behavior appears in payload/UI.
- Warning message appears in classification panel.

### F) Provider Fallback Behavior
1. Disable AI credentials and run classification.
2. Enable provider and run again.

Expected:
- Without credentials: stub/provider fallback still returns usable suggestion.
- With credentials: configured provider path is used.

## 5) Expected Result Pattern (What You Should See)
- status: pending/processed/overridden/failed lifecycle updates properly.
- suggested_account_code: non-empty for successful suggestions.
- account_confidence: numeric score is present.
- tax_hints: structured hint payload returned.
- reason: explainability text present.
- low_confidence: true when below threshold.

## 6) Sign-Off Checklist
Mark all as complete before release:
- SL-022 automated tests are PASS.
- Expense classification panel shows suggestion/confidence/tax hint.
- Apply flow updates record correctly.
- Override reason validation is enforced.
- Duplicate/idempotency protection confirmed.
- Low-confidence warning flow verified.

## 7) Scope Note
- SL-022 delivers suggestion + governance flow.
- Final tax posting/override policy interactions depend on related tax modules, but SL-022 hint generation and apply/override pathways are active and testable.

## 8) Final Conclusion
SL-022 Smart Classification & Tax Hints [AI] is complete for Lean MVP scope and testable with both automated and manual paths listed above.
Recommendation: Approve SL-022 for MVP sign-off.
