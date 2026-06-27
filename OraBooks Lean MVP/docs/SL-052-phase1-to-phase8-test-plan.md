# SL-052 Phase 1-8 Test Plan (Final)

## Goal
Confirm that SL-052 is fully complete and production-usable, with clear evidence that configuration-driven voice transcription works end-to-end.

## Preconditions

1. WordPress plugin active with OraBooks Lean MVP loaded.
2. Test user has permissions for voice input and settings.
3. At least one organization is selected in context.
4. API/provider config available for at least one real speech path:
- Option A: OpenAI API key
- Option B: Azure OpenAI endpoint + key + deployment
- Option C: Speech webhook URL (and optional token/model/health URL)

## Phase-by-Phase Validation

### Phase 1: Baseline voice dashboard and permissions
1. Open voice page.
2. Confirm dashboard loads without permission errors.
3. Confirm counters render and `Record` controls appear for authorized users.

Expected:
- No fatal error.
- Stats and capability controls are visible.

### Phase 2: Audio input acceptance and limits
1. Upload supported audio file (`.mp3`/`.wav`/`.m4a` etc.).
2. Try an oversized file > max limit.

Expected:
- Supported file uploads proceed.
- Oversized upload is rejected with clear error.

### Phase 3: Speech provider selection behavior
1. With no provider configured, load voice page.
2. Configure OpenAI or Azure OpenAI or webhook.
3. Reload and observe provider diagnostics.

Expected:
- No config => provider falls back to `mvp-stub`.
- Configured => provider switches to real provider.

### Phase 4: Processing states and queue flow
1. Submit multiple voice inputs.
2. Observe pending -> processed (or failed/escalated) transitions.

Expected:
- State transitions are reflected in list and stats.
- No stuck records without status updates.

### Phase 5: Confirmation and retry actions
1. Pick a processed item and confirm/post path.
2. Force a failure scenario and run retry.

Expected:
- Confirm path updates item status correctly.
- Retry path re-queues and re-processes input.

### Phase 6: Settings persistence for speech webhook
1. Open Admin Settings (React route).
2. Fill speech webhook URL/token/model/health URL.
3. Save settings and reload page.

Expected:
- Values persist and rehydrate from backend.
- No field loss after refresh.

### Phase 7: One-click webhook health check
1. In Admin Settings, click `Test speech webhook`.
2. Inspect result panel.

Expected:
- Response includes status/provider/model/checked_at.
- Failure surfaces readable error text.

### Phase 8: Setup readiness diagnostics (API key only clarity)
1. Open Voice page diagnostics panel.
2. Verify `Setup ready` and per-provider readiness lines.
3. Configure only OpenAI API key and refresh.

Expected:
- `Setup ready: yes` when any real provider is configured.
- With only OpenAI key, panel shows OpenAI configured and provider no longer `mvp-stub`.
- Missing configuration remains clearly indicated for non-configured provider paths.

## Automated Test Commands

Run from repository root with your PHP binary path:

1. `D:\xampp\php\php.exe tests\vendor\phpunit\phpunit\phpunit --configuration tests\phpunit.xml tests\OraBooks_Ai_Providers_Test.php`
2. `D:\xampp\php\php.exe tests\vendor\phpunit\phpunit\phpunit --configuration tests\phpunit.xml tests\OraBooks_Platform_Settings_Test.php`

Expected:
- Both suites PASS.

## Frontend Build Command

From `orabooks-ui`:

1. `npm run build`

Expected:
- Admin and frontend bundles build successfully.

## Final Acceptance Criteria

SL-052 can be marked complete when all are true:

1. Voice UI processes input and shows stable state transitions.
2. Real provider activation is reflected in diagnostics.
3. Admin settings persist and webhook health test works.
4. Setup readiness panel clearly says ready/not-ready and what is missing.
5. PHPUnit suites pass.
6. Frontend build passes.
