# SL-052 Phase 2 and Phase 3 Completion

## Scope

This document records implementation details for SL-052 Phase 2 and Phase 3 in Lean MVP OraBooks.

## Phase 2 - Speech Provider Extension (Complete)

Implemented:

1. Added a pluggable speech provider `speech-webhook` for self-hosted or internal STT engines.
2. Added provider routing priority for speech capability:
   - `speech-webhook` (if configured)
   - `azure-openai`
   - `openai`
   - `mvp-stub`
3. Added webhook transcription client with:
   - URL validation (`http://` or `https://`)
   - Optional bearer token auth (`ORABOOKS_SPEECH_WEBHOOK_TOKEN`)
   - SHA-256 audio hash and payload size metadata
   - robust response parsing for `text` / `transcript` fields
4. Preserved existing NLU behavior:
   - Chat NLU for structured extraction when available
   - Heuristic extraction fallback on NLU parse/provider failure
   - deterministic stub fallback when no transcript available

### New/Used Config Keys

- `ORABOOKS_SPEECH_WEBHOOK_URL`
- `ORABOOKS_SPEECH_WEBHOOK_TOKEN` (optional)
- `ORABOOKS_SPEECH_WEBHOOK_MODEL` (optional)

## Phase 3 - Observability and Runtime Visibility (Complete)

Implemented:

1. Added speech and classification model metadata in capability status.
2. Added voice dashboard AI status payload (`ai_status`) to frontend response.
3. Added Voice page runtime diagnostics panel showing active speech provider and model.
4. Persisted transcription provider metadata in voice extraction payload under `_voice_ai`.
5. Exposed formatted fields in API response:
   - `ai_provider`
   - `ai_model_version`
6. Extended voice dashboard stats baseline to include `dead_letter` key for consistency.

## Files Changed

Backend:

- `includes/class-orabooks-ai-providers.php`
- `includes/class-orabooks-voice.php`
- `includes/class-orabooks-ajax.php`

Frontend:

- `orabooks-ui/src/pages/frontend/pages/VoicePage.tsx`

Tests:

- `tests/OraBooks_Ai_Providers_Test.php`
- `tests/OraBooks_Voice_Test.php`

## Test Commands

Run from mapped drive (example `Y:`) where `OraBooks Lean MVP` exists:

```cmd
D:\xampp\php\php.exe tests\vendor\phpunit\phpunit\phpunit --configuration tests\phpunit.xml --filter OraBooks_Ai_Providers_Test
D:\xampp\php\php.exe tests\vendor\phpunit\phpunit\phpunit --configuration tests\phpunit.xml --filter OraBooks_Voice_Test
```

## Manual Verification Steps

1. Configure speech webhook env/secret values.
2. Open Voice page.
3. Confirm diagnostics panel shows:
   - Speech provider: `speech-webhook`
   - Speech model: configured model or default `webhook-v1`
4. Upload audio file from Voice page.
5. Verify transcript is not fallback text and reflects actual spoken content.
6. Verify the generated voice item contains provider metadata and can continue through confirm/escalation flow.

## Expected Fallback Behavior

If webhook returns empty/invalid transcript or provider is unavailable:

1. system logs voice provider fallback event,
2. flow falls back to deterministic stub,
3. transcript indicates fallback mode is active.
