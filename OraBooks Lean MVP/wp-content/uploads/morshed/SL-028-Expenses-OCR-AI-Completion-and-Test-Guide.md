# SL-028 Expenses OCR [AI] - Completion and Test Guide

Prepared for: Morshed
Project: OraBooks Lean MVP
Date: 2026-06-27

## 1) Executive Summary
SL-028 (Expenses OCR [AI]) implementation is complete and active for Lean MVP scope.

Verification snapshot:
- Receipt upload -> OCR extraction -> draft hydration flow exists.
- Provider routing supports real OCR providers + deterministic fallback.
- OCR queue + async processing hooks are integrated.
- Frontend UX supports upload, polling hydration, confidence/risk display, and offline queue.
- Fresh SL-028 related automated tests passed.

Status: READY FOR MVP SIGN-OFF (within SL-028 defined scope)

## 2) How It Has Been Implemented

### 2.1 Core Expense OCR Module
- Expense module has OCR-specific tables and queue support.
- Main flow:
  1. Receipt upload
  2. Draft expense create
  3. OCR extraction
  4. Expense fields + line items hydrate
  5. Confirm/submit into approval workflow
- Workflow statuses support draft -> submitted -> ai_review -> approved -> posted -> locked.

Primary file:
- includes/class-orabooks-expenses.php

### 2.2 OCR Provider Strategy
- OCR provider capability is resolved dynamically.
- If Azure Document Intelligence is configured, it is used.
- For image receipts, vision chat OCR path is supported (OpenAI/Azure OpenAI).
- If provider fails or not configured, safe MVP stub fallback is used.
- Partial fallback merge logic exists when provider returns weak signal.

Primary file:
- includes/class-orabooks-ai-providers.php

### 2.3 Data Quality & Risk Signals
- OCR output includes:
  - vendor/invoice/date/amount/tax/category/line_items
  - confidence averages
  - risk levels (low/medium/high)
  - provider/model version traceability
- Salary voucher/document-style text extraction heuristics are covered.
- Noise/binary-content anti-fabrication behavior is covered by tests.

### 2.4 Security/Control and Reliability
- Upload size validation (10MB) and MIME checks.
- Upload rate limiting is enforced.
- Idempotency key support exists.
- Async queue integration for OCR job type is registered.
- Confirm/apply flow tied with downstream workflow/approval modules.

### 2.5 Frontend UX (What User Sees)
- Expense page supports file upload and camera capture (mobile/PWA).
- Offline receipt queue is supported and syncs on reconnect.
- OCR hydration polling is done after upload.
- User sees success state:
  - "OCR fields extracted" when hydrated
  - "processing in background" when delayed
- Classification/tax override controls appear after OCR draft hydration.

Primary file:
- orabooks-ui/src/pages/frontend/pages/ExpensesPage.tsx

## 3) What Was Needed / What Is Needed

### 3.1 Already Needed and Implemented
- Expense/queue/line-item database schema.
- AI provider abstraction and credential-based provider selection.
- OCR parsing and normalization layer.
- Frontend upload + status UX.
- Test coverage for stub, provider, voucher, and risk handling.

### 3.2 Needed for Strong Production-Grade Accuracy (Optional Enhancements)
- Real provider credentials in secure secrets storage:
  - azure_document_intelligence_endpoint
  - azure_document_intelligence_key
  - optional OpenAI/Azure OpenAI configs for vision path
- More tenant-specific OCR templates/rules by document type.
- OCR quality dashboard/analytics thresholds per org.
- Expanded multilingual document extraction tuning.

## 4) Evidence Used for Completion Check

### 4.1 Core Files
- includes/class-orabooks-expenses.php
- includes/class-orabooks-ai-providers.php

### 4.2 UI/API Evidence
- orabooks-ui/src/pages/frontend/pages/ExpensesPage.tsx

### 4.3 Automated Test Evidence (Executed)
Command used:
D:\xampp\php\php.exe tests\vendor\phpunit\phpunit\phpunit --configuration tests\phpunit.xml tests\OraBooks_Expenses_Test.php tests\OraBooks_Ai_Providers_Test.php

Result:
- 26 tests
- 105 assertions
- PASS

## 5) How to Test SL-028 (Step by Step)

## 5.1 Automated Test Run
From project root (OraBooks Lean MVP):
1. Open terminal.
2. Run:
D:\xampp\php\php.exe tests\vendor\phpunit\phpunit\phpunit --configuration tests\phpunit.xml tests\OraBooks_Expenses_Test.php tests\OraBooks_Ai_Providers_Test.php
3. Confirm output shows PASS.

Expected:
- No failures
- No errors

## 5.2 Manual Functional Test

### A) Basic Receipt Upload + OCR Hydration
1. Open Expenses page.
2. Upload PDF/JPG/PNG receipt.
3. Wait for hydration/polling.

Expected:
- Draft expense is created.
- OCR fields (vendor/amount/date/category) appear.
- Line items and confidence/risk metadata appear.

### B) Provider Fallback Test
1. Run upload without OCR credentials configured.
2. Run upload with Azure OCR credentials configured.

Expected:
- Without credentials: stub fallback still returns structured output.
- With credentials: provider output is used; model/provider fields reflect real provider.

### C) Voucher/Document Pattern Test
1. Upload salary voucher-style image/PDF.

Expected:
- Voucher fields are parsed (vendor/invoice/date/amount/currency/category).
- prebuilt-document route behavior works for voucher-like filenames.

### D) Validation/Guardrails
1. Upload unsupported MIME file.
2. Upload oversize file (>10MB).
3. Burst upload to exceed rate limit.

Expected:
- Unsupported file rejected.
- Oversize rejected.
- Rate-limit error is returned when threshold exceeded.

### E) Offline/PWA Test
1. Go offline in browser.
2. Upload/capture receipt.
3. Reconnect network.

Expected:
- Receipt is queued offline.
- On reconnect, sync happens and expense appears in list.

## 6) Expected Result Pattern (What You Should See)
- `workflow_status` starts at `draft` after upload.
- OCR payload includes `ocr_confidence`, `ocr_risk_level`, `ocr_provider`, `ocr_model_version`.
- Structured amounts are consistent: subtotal/tax/total.
- `line_items` are present.
- For weak provider output, fallback/merged result still gives usable draft fields.

## 7) Sign-Off Checklist
Mark all as complete before release:
- Expense OCR tests are PASS.
- Receipt upload and OCR hydration verified manually.
- Provider fallback behavior verified.
- Validation controls (MIME/size/rate-limit) verified.
- Voucher extraction case verified.
- Offline queue sync verified (if PWA path is in scope).

## 8) Final Conclusion
SL-028 Expenses OCR [AI] is complete for Lean MVP scope and testable through automated + manual flows above.
Recommendation: Approve SL-028 for MVP sign-off.
