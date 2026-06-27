# SL-052 Voice-to-Text - Completion and Test Guide

Prepared for: Morshed
Project: OraBooks Lean MVP
Date: 2026-06-27

## 1) Executive Summary
SL-052 (Voice-to-Text) is implemented and operational for Lean MVP scope.

Verification snapshot:
- Voice upload, transcription/NLU extraction, risk scoring, and confirm flow are implemented.
- Multi-provider speech strategy exists (speech webhook / OpenAI / Azure OpenAI / stub fallback).
- Retry, dead-letter, escalation, and retention governance are implemented.
- Frontend voice recording/upload review workflow is implemented.
- Fresh SL-052 related automated tests passed.

Status: READY FOR MVP SIGN-OFF (within SL-052 defined scope)

## 2) How Much Is Completed (Current Status)

### 2.1 Core Voice Engine
- Dedicated SL-052 model/table exists with statuses:
  pending, processed, failed, escalated, dead_letter.
- Voice inputs are stored as safe intermediate records.
- Module explicitly avoids direct posting into accounting tables.

Primary file:
- includes/class-orabooks-voice.php

### 2.2 Upload and Transcription Pipeline
- Upload endpoint supports voice file upload with size and type checks.
- Audio hash + idempotency controls are present.
- Attachment-backed audio retrieval is implemented.
- Transcription/NLU processing updates transcript + extracted structured data.

### 2.3 AI Provider Strategy and Fallback
- Speech provider selection implemented:
  - speech-webhook
  - azure-openai
  - openai
  - mvp-stub fallback
- If real provider unavailable/fails, safe fallback behavior continues processing.

Primary supporting file:
- includes/class-orabooks-ai-providers.php

### 2.4 Risk, Confidence, and Governance
- Confidence average and per-field risk scores are generated.
- Overall risk level is computed (low/medium/high).
- Retry scheduling and max retry -> dead_letter logic implemented.
- Escalation and notification hooks are wired for failure paths.

### 2.5 Derived Resource Confirmation Flow
- After transcription, user can edit extracted fields.
- Confirm endpoint creates/links derived resource through owning workflows.
- Low-confidence/elevated-risk can route to escalation review path.

### 2.6 Frontend UX Readiness
- Voice page includes:
  - live recording (start/stop)
  - file upload path
  - polling for pending transcription
  - extracted fields review/edit
  - retry + confirm actions
- Speech setup status is shown in UI (configured/missing/health).

Primary file:
- orabooks-ui/src/pages/frontend/pages/VoicePage.tsx

## 3) What Was Needed and What Is Still Needed

### 3.1 Needed and Already Implemented
- Voice input schema + lifecycle statuses.
- Upload and secure attachment integration.
- NLU/transcription normalization format.
- Risk/confidence scoring and escalation states.
- UI recording/upload + review workflow.
- Unit test coverage for schema, risk logic, and stub extraction.

### 3.2 Needed for Strong Production Rollout (If Not Already Configured)
- At least one real speech provider configuration:
  - speech webhook URL/token/model, or
  - OpenAI key/model, or
  - Azure OpenAI endpoint/key/deployment.
- Optional webhook healthcheck configuration for operational monitoring.
- Tenant-level operational monitoring for failed/dead-letter trends.
- Additional multilingual utterance datasets for tuning domain phrases.

## 4) Evidence Used for Completion Check

### 4.1 Core SL-052 Files
- includes/class-orabooks-voice.php
- includes/class-orabooks-ai-providers.php

### 4.2 Frontend/API Surface
- orabooks-ui/src/pages/frontend/pages/VoicePage.tsx
- orabooks-ui/src/pages/frontend/api.ts

### 4.3 Automated Test Evidence (Executed)
Command used:
D:\xampp\php\php.exe tests\vendor\phpunit\phpunit\phpunit --configuration tests\phpunit.xml tests\OraBooks_Voice_Test.php tests\OraBooks_Ai_Providers_Test.php

Result:
- 26 tests
- 107 assertions
- PASS

## 5) How to Test SL-052 (Step by Step)

## 5.1 Automated Test Run
From project root (OraBooks Lean MVP):
1. Open terminal.
2. Run:
D:\xampp\php\php.exe tests\vendor\phpunit\phpunit\phpunit --configuration tests\phpunit.xml tests\OraBooks_Voice_Test.php tests\OraBooks_Ai_Providers_Test.php
3. Confirm output shows PASS.

Expected:
- No failures
- No errors

## 5.2 Manual Functional Test

### A) Record and Transcribe
1. Open Voice page.
2. Click Start Recording, speak transaction, Stop & Transcribe.
3. Wait until status changes from pending to processed.

Expected:
- Transcript appears.
- extracted_data fields are populated (type/vendor/amount/date/tax etc).
- confidence and risk indicators appear.

### B) Upload Audio File Path
1. Upload WEBM/MP3/WAV/OGG file.
2. Observe pending -> processed flow.

Expected:
- Input is accepted within limits.
- Parsed structured data appears for review.

### C) Confirm Derived Resource
1. Edit extracted fields if needed.
2. Click confirm.

Expected:
- Voice input links to derived_resource_type and derived_resource_id.
- Success message appears.

### D) Retry / Failure Governance
1. Use a bad/empty/unsupported scenario.
2. Trigger retry action where applicable.

Expected:
- Retry increments and re-process attempts happen.
- After max retries, dead_letter state path is available.

### E) Setup/Provider Visibility Check
1. Open Voice page setup banner.
2. Validate provider readiness indicators.

Expected:
- UI shows configured/missing state for speech providers.
- If real provider missing, stub fallback notice appears.

## 6) Expected Result Pattern (What You Should See)
- Status lifecycle:
  pending -> processed, or failed/escalated/dead_letter for problem cases.
- Core payload fields:
  original_transcript, extracted_data, confidence_avg, risk_scores, overall_risk_level.
- Provider metadata:
  ai_provider and ai_model_version visible in response/view model.
- Confirm result:
  derived_resource_type + derived_resource_id after successful confirmation.

## 7) Sign-Off Checklist
Mark all as complete before release:
- SL-052 tests are PASS.
- Record + file upload transcription both verified.
- Confidence/risk output verified.
- Retry and failure/dead-letter behavior verified.
- Confirm-to-derived-resource flow verified.
- Provider setup status visibility verified.

## 8) Final Conclusion
SL-052 Voice-to-Text is complete for Lean MVP scope and is testable with both automated and manual paths above.
Recommendation: Approve SL-052 for MVP sign-off.
