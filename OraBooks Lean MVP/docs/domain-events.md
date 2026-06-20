# OraBooks Domain Events (SL-302)

OraBooks publishes domain events through `OraBooks_EventBus` using the transactional outbox table `orabooks_outbox_messages`. Publishers write events in the same database transaction as the business change. The EventBus cron worker later delivers pending events to registered consumers and records successful consumption in `orabooks_consumer_event_tracking`.

## Envelope

Every event payload should include:

- `event_version`: integer schema version. Defaults to `1` when omitted.
- Domain identifiers such as `org_id`, `journal_id`, `invoice_id`, `payment_id`, or `reconciliation_id`.
- Domain-specific values needed by asynchronous consumers.

The outbox row supplies the delivery envelope:

- `id`: stable event ID used for consumer idempotency.
- `event_type`: canonical event name.
- `aggregate_id`: primary business record ID.
- `status`: `pending`, `sent`, `failed`, or `dead_letter`.
- `retry_count`, `last_attempt_at`, `next_retry_at`, `last_error`: retry and failure metadata.

## Versioning Rules

- Keep changes backward compatible.
- Add new fields as optional.
- Do not remove or rename existing fields without introducing a new event type or version.
- Consumers must tolerate unknown fields.
- Consumers must remain idempotent and use the outbox event ID or `consumer_event_tracking` record to avoid duplicate side effects.

## Canonical MVP Events

### `journal_posted`

Published after an approved journal is posted and locked.

```json
{
  "event_version": 1,
  "journal_id": 55,
  "org_id": 10,
  "journal_number": "JE-2026-000001",
  "total_amount": "100.00",
  "created_by": 7
}
```

### `journal_reversed`

Published after a reversing journal is created for a posted or locked journal.

```json
{
  "event_version": 1,
  "original_journal_id": 55,
  "reversal_journal_id": 56,
  "org_id": 10,
  "reason": "Correction needed",
  "created_by": 7
}
```

### `invoice_approved`

Published by approval flows when invoice or journal approval reaches an approved state.

```json
{
  "event_version": 1,
  "invoice_id": 210,
  "org_id": 10,
  "approved_by": 7,
  "approved_at": "2026-06-20 08:43:00"
}
```

### `payment_recorded`

Published when customer payment is recorded.

```json
{
  "event_version": 1,
  "payment_id": 901,
  "invoice_id": 210,
  "customer_id": 44,
  "org_id": 10,
  "amount": "75.00",
  "currency": "USD"
}
```

### `reconciliation_completed`

Published when a bank reconciliation run completes.

```json
{
  "event_version": 1,
  "reconciliation_id": 3001,
  "bank_account_id": 12,
  "org_id": 10,
  "completed_by": 7,
  "completed_at": "2026-06-20 08:43:00"
}
```

### Platform Events

The same EventBus also carries platform events used by other SLs, including `payout_batch_created`, `payout_settled`, `export_ready`, `export_failed`, `inventory_low_stock_alert`, `projection_integrity_failed`, `csv_import_completed`, `csv_import_failed`, and `csv_row_escalated`.
