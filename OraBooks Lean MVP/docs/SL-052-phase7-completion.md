# SL-052 Phase 7 Completion

## Goal

Phase 7 focuses on operator-friendly production verification from the Settings screen by adding a one-click speech webhook live check.

## Completed Work

1. Added a new admin AJAX endpoint for speech webhook checks:
   - `orabooks_speech_webhook_check`
   - Validates admin capability (`manage_options`)
   - Returns provider, model, webhook configured flag, health payload, and check timestamp
2. Extended admin API client:
   - Added `speechWebhookCheck()` helper in frontend API module
3. Upgraded Admin Settings UI:
   - Added `Test speech webhook` button inside Speech Webhook Configuration card
   - Added inline result panel for:
     - provider
     - model
     - health status
     - health version/message (when available)
     - checked timestamp
   - Added inline error display on check failure
4. Preserved prior settings behavior:
   - Existing save/get flow remains unchanged
   - Existing deploy checks section remains unchanged

## Files Updated

- `includes/class-orabooks-ajax.php`
- `orabooks-ui/src/pages/frontend/api.ts`
- `orabooks-ui/src/pages/admin/AdminSettings.tsx`
- `tests/OraBooks_Platform_Settings_Test.php`

## Automated Validation

Run from project root (`OraBooks Lean MVP`):

```cmd
D:\xampp\php\php.exe tests\vendor\phpunit\phpunit\phpunit --configuration tests\phpunit.xml tests\OraBooks_Platform_Settings_Test.php
D:\xampp\php\php.exe tests\vendor\phpunit\phpunit\phpunit --configuration tests\phpunit.xml --filter OraBooks_Ai_Providers_Test
```

Latest results during implementation:

- `OraBooks_Platform_Settings_Test`: 4 tests, 21 assertions, passed
- `OraBooks_Ai_Providers_Test`: 17 tests, 66 assertions, passed

## Live Verification Steps

1. Open WordPress admin: `admin.php?page=orabooks-settings`
2. In Speech Webhook Configuration, set:
   - Speech webhook URL
   - Speech webhook token (if required)
   - Speech webhook model
   - Speech webhook health URL (optional)
3. Save settings.
4. Click `Test speech webhook`.
5. Confirm inline result shows expected provider/model and a healthy status (`up` or provider-specific healthy state).
6. Open Voice page and verify Speech Diagnostics aligns with the same provider/model/health outcome.

## Expected Failure Modes (Handled)

1. Not configured: `status = not_configured`
2. Health checks disabled: `status = disabled`
3. Invalid health URL: `status = invalid_url`
4. Endpoint unreachable: `status = down` with message

This completes Phase 7 for settings-driven speech webhook live verification.
