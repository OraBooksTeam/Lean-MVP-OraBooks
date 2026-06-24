# OraBooks Domain Events (SL-302)

OraBooks publishes domain events through `OraBooks_EventBus` and the SL-302 event module. Publishers write events in the same database transaction as the business change. The EventBus worker later delivers pending events to registered consumers and records successful consumption for idempotency.

The MVP final-report table convention is WordPress table prefix + `gob_` prefix + `_tob` suffix:

- `gob_event_outbox_tob`
- `gob_event_consumer_log_tob`
- `gob_event_dead_letter_tob`
- `gob_event_notifications_tob`
- `gob_event_notification_reads_tob`

## Envelope

Every event payload should include:

- `event_version`: integer schema version. Defaults to `1` when omitted.
- Domain identifiers such as `org_id`, `journal_id`, `sale_id`, `purchase_id`, `return_id`, or `reimbursement_id`.
- Domain-specific values needed by asynchronous consumers.

The `gob_event_outbox_tob` row supplies the delivery envelope:

- `id`: stable event ID used for consumer idempotency.
- `event_type`: canonical event name.
- `aggregate_id`: primary business record ID.
- `status`: `pending`, `processing`, `sent`, `failed`, `dead_letter`, or `discarded`.
- `retry_count`, `last_attempt_at`, `next_retry_at`, `last_error`: retry and failure metadata.

## Versioning Rules

- Keep changes backward compatible.
- Add new fields as optional.
- Do not remove or rename existing fields without introducing a new event type or version.
- Consumers must tolerate unknown fields.
- Consumers must remain idempotent and use the outbox event ID plus `gob_event_consumer_log_tob.consumer_key` to avoid duplicate side effects.

## Final Report Events

- `journal_posted`
- `sale_delivered`
- `purchase_received`
- `return_approved`
- `reimbursement_submitted`

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

### `sale_delivered`

Published after a sale is marked delivered.

```json
{
  "event_version": 1,
  "sale_id": 501,
  "customer_id": 44,
  "org_id": 10,
  "amount": "100.00",
  "delivered_by": 7
}
```

### `purchase_received`

Published after a purchase is marked received.

```json
{
  "event_version": 1,
  "purchase_id": 601,
  "supplier_id": 45,
  "org_id": 10,
  "amount": "75.00",
  "received_by": 7
}
```

### `return_approved`

Published after a sales or purchase return is approved.

```json
{
  "event_version": 1,
  "return_id": 701,
  "return_type": "sales",
  "org_id": 10,
  "approver_user_id": 7,
  "amount": "25.00"
}
```

### `reimbursement_submitted`

Published after a reimbursement is submitted for approval.

```json
{
  "event_version": 1,
  "reimbursement_id": 801,
  "org_id": 10,
  "submitted_by": 7,
  "amount": "40.00"
}
```

### Platform Events

The same EventBus also carries platform events used by other SLs, including `payout_batch_created`, `payout_settled`, `export_ready`, `export_failed`, `inventory_low_stock_alert`, `projection_integrity_failed`, `csv_import_completed`, `csv_import_failed`, and `csv_row_escalated`.
