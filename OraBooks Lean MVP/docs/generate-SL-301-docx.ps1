# Generates SL-301 complete Word report (.docx)
$ErrorActionPreference = 'Stop'
$outPath = Join-Path $PSScriptRoot 'SL-301-Workflow-State-Engine-Complete-Report.docx'

$word = New-Object -ComObject Word.Application
$word.Visible = $false
$doc = $word.Documents.Add()
$sel = $word.Selection

function Set-Style([string]$name) { $sel.Style = $doc.Styles.Item($name) }

function Add-H1([string]$text) {
    Set-Style 'Heading 1'
    $sel.TypeText($text)
    $sel.TypeParagraph()
}

function Add-H2([string]$text) {
    Set-Style 'Heading 2'
    $sel.TypeText($text)
    $sel.TypeParagraph()
}

function Add-H3([string]$text) {
    Set-Style 'Heading 3'
    $sel.TypeText($text)
    $sel.TypeParagraph()
}

function Add-P([string]$text) {
    Set-Style 'Normal'
    $sel.TypeText($text)
    $sel.TypeParagraph()
}

function Add-Bullet([string]$text) {
    Set-Style 'Normal'
    $sel.Range.ListFormat.ApplyBulletDefault()
    $sel.TypeText($text)
    $sel.TypeParagraph()
    $sel.Range.ListFormat.RemoveNumbers()
}

function Add-Table([string[]]$headers, [string[][]]$rows) {
    Set-Style 'Normal'
    $cols = $headers.Count
    $table = $doc.Tables.Add($sel.Range, $rows.Count + 1, $cols)
    $table.Style = 'Grid Table 4 - Accent 1'
    for ($c = 0; $c -lt $cols; $c++) {
        $table.Cell(1, $c + 1).Range.Text = $headers[$c]
        $table.Cell(1, $c + 1).Range.Font.Bold = $true
    }
    for ($r = 0; $r -lt $rows.Count; $r++) {
        for ($c = 0; $c -lt $cols; $c++) {
            $val = if ($c -lt $rows[$r].Count) { $rows[$r][$c] } else { '' }
            $table.Cell($r + 2, $c + 1).Range.Text = $val
        }
    }
    $sel.Start = $doc.Content.End
    $sel.TypeParagraph()
}

# ===== TITLE =====
Set-Style 'Title'
$sel.TypeText('SL-301 — Workflow State Engine')
$sel.TypeParagraph()
Set-Style 'Subtitle'
$sel.TypeText('Complete Delivery & Testing Report | OraBooks Lean MVP')
$sel.TypeParagraph()
Set-Style 'Normal'
$sel.TypeText('Status: COMPLETE — MVP Sign-Off Ready')
$sel.TypeParagraph()
$sel.TypeText('Report Date: 20 June 2026')
$sel.TypeParagraph()
$sel.TypeText('Document Version: 1.0')
$sel.TypeParagraph()
$sel.TypeParagraph()

# ===== 1 EXECUTIVE SUMMARY =====
Add-H1 '1. Executive Summary'
Add-P 'SL-301 delivers a central Workflow State Engine for OraBooks. Every business-record status change flows through a single entry point: OraBooks_Workflow::transition(). The engine validates transitions, locks database rows (FOR UPDATE), runs inside a DB transaction, writes audit logs, and publishes state_transition events.'
Add-P 'Implementation covers five record types (journal, invoice, bill, expense, commission), three API surfaces (PHP, guarded AJAX, REST), RBAC/fiscal/MFA preconditions, observability metrics, and React UI including invoice cancel and bill void.'
Add-P 'BOTTOM LINE: SL-301 is 100% complete for Lean MVP sign-off. All Definition of Done items are satisfied. The only intentional deferral is a dynamic state_machine_config database table (spec allows hard-coded machines for MVP).'

# ===== 2 PROBLEM =====
Add-H1 '2. Problem Statement (Before SL-301)'
Add-Bullet 'Workflow status fields updated directly in module code — no single validation path'
Add-Bullet 'Inconsistent behaviour across journal, invoice, bill, expense modules'
Add-Bullet 'No unified audit trail for state changes'
Add-Bullet 'Race conditions possible under concurrent requests'
Add-Bullet 'Difficult tenant-scoped traceability'
Add-Bullet 'RBAC and fiscal guards scattered across callers'

# ===== 3 ARCHITECTURE =====
Add-H1 '3. Solution Architecture'
Add-P 'Flow: UI/AJAX/REST  to  Module method  to  OraBooks_Workflow::transition()  to  Validate  to  Preconditions (RBAC/MFA/Fiscal)  to  DB Transaction + FOR UPDATE  to  Update status  to  Persist transition log  to  Commit  to  Audit + Event Bus  to  Observability metrics.'
Add-H2 '3.1 Core Design Principles'
Add-Bullet 'Single write path — no production code updates workflow columns without transition()'
Add-Bullet 'Atomic transitions — START TRANSACTION, SELECT FOR UPDATE, validate, update, COMMIT/ROLLBACK'
Add-Bullet 'Fail closed — invalid transitions return HTTP 409 and log invalid_state_transition'
Add-Bullet 'Tenant traceability — state_machine_transitions.org_id on every row'
Add-Bullet 'Extensibility — hooks: orabooks_workflow_preconditions, orabooks_workflow_after_transition, orabooks_workflow_state_machines'

# ===== 4 PHASES =====
Add-H1 '4. Delivery Phases (All Complete)'
Add-Table @('Phase', 'Scope', 'Status') @(
    @('Phase 0', 'Inventory, state matrices, migration backlog, DoD checklist', 'Complete'),
    @('Phase 1', 'Core engine: transactions, FOR UPDATE, hooks, AJAX, org_id column', 'Complete'),
    @('Phase 2', 'Caller migration — all 5 record types use transition()', 'Complete'),
    @('Phase 3', 'Events, RBAC/fiscal preconditions, observability, health AJAX', 'Complete'),
    @('Phase 4', 'Gap closure: cancel, void, expense lock, REST, MFA centralization', 'Complete')
)

# ===== 5 STATE MACHINES =====
Add-H1 '5. State Machines — 100% Wired'

Add-H2 '5.1 Journal (journals.status)'
Add-P 'States: draft, review_pending, approved, posted, locked, reversed'
Add-P 'Transitions: submit, approve, reject, post, lock, reverse, edit'
Add-P 'Flow: draft  to  submit  to  review_pending  to  approve  to  approved  to  post  to  posted  to  lock  to  locked. Reject returns to draft. Reverse from posted/locked.'
Add-P 'Files: class-orabooks-posting.php, class-orabooks-approval.php. Post auto-chains lock.'

Add-H2 '5.2 Invoice (invoices.workflow_status)'
Add-P 'States: draft, sent, posted, cancelled'
Add-P 'Transitions: send, post, cancel — ALL WIRED end-to-end'
Add-P 'Files: class-orabooks-customers.php | AJAX: orabooks_invoice_send, orabooks_invoice_post, orabooks_invoice_cancel'
Add-P 'UI: /invoices — Send, Post, Pay, Cancel buttons'

Add-H2 '5.3 Bill (bills.workflow_status)'
Add-P 'States: draft, submitted, approved, posted, void'
Add-P 'Transitions: submit, approve, post, void — ALL WIRED end-to-end'
Add-P 'Files: class-orabooks-vendors.php | AJAX: orabooks_bill_submit, orabooks_bill_approve, orabooks_bill_post, orabooks_bill_void'
Add-P 'UI: /vendors — Submit, Approve, Post, Void buttons'

Add-H2 '5.4 Expense (expenses.workflow_status)'
Add-P 'States: draft, submitted, ai_review, approved, posted, locked'
Add-P 'Transitions: submit, ai_review, approve, reject, post, lock — ALL WIRED'
Add-P 'Post automatically chains lock transition (journal pattern).'
Add-P 'Files: class-orabooks-expenses.php | UI: /expenses'

Add-H2 '5.5 Commission (commissions_earned.status)'
Add-P 'States: earned, paid, expired | Transitions: pay, expire — WIRED'
Add-P 'Files: class-orabooks-commission.php | UI: /commissions'

# ===== 6 FILES =====
Add-H1 '6. Key Implementation Files'
Add-Table @('Category', 'File', 'Purpose') @(
    @('Engine', 'includes/class-orabooks-workflow.php', 'State machines, transition(), AJAX, health'),
    @('Integration', 'includes/class-orabooks-workflow-integration.php', 'RBAC, fiscal, MFA, maker-checker'),
    @('Database', 'includes/class-orabooks-database.php', 'state_machine_transitions table + org_id'),
    @('REST', 'includes/class-orabooks-rest-api.php', 'POST /api/internal/state/transition'),
    @('Observability', 'includes/class-orabooks-observability.php', 'Workflow metrics + health'),
    @('Deploy', 'includes/class-orabooks-deploy-checks.php', 'Post-deploy table verification'),
    @('Events', 'includes/events/class-orabooks-event-module.php', '3 state_transition consumers'),
    @('UI', 'orabooks-ui/.../InvoicesPage.tsx', 'Cancel button + modal'),
    @('UI', 'orabooks-ui/.../VendorsPage.tsx', 'Void button + modal'),
    @('UI', 'orabooks-ui/.../api.ts', 'invoiceCancel, billVoid helpers')
)

# ===== 7 API =====
Add-H1 '7. API Reference'
Add-H2 '7.1 PHP'
Add-P 'OraBooks_Workflow::transition(record_type, record_id, event, context)'
Add-P 'OraBooks_Workflow::allowed_events(record_type, current_state)'
Add-P 'Context options: user_id, org_id, reason, row_updates, skip_transaction, skip_preconditions, mfa_otp, mfa_verified'

Add-H2 '7.2 AJAX Endpoints'
Add-Table @('Action', 'Purpose') @(
    @('orabooks_workflow_transitions', 'Transition history for a record'),
    @('orabooks_workflow_allowed_events', 'Allowed events from current state'),
    @('orabooks_workflow_transition', 'Execute transition (guarded)'),
    @('orabooks_workflow_health', 'Org workflow health snapshot'),
    @('orabooks_invoice_cancel', 'Cancel draft/sent invoice'),
    @('orabooks_bill_void', 'Void draft/submitted/approved bill')
)

Add-H2 '7.3 REST'
Add-P 'POST /wp-json/api/internal/state/transition'
Add-P 'Headers: X-OraBooks-Org-Id. Body: record_type, record_id, event, reason (optional), mfa_otp (optional).'
Add-P 'Requires: manage_settings OR submit_transaction OR approve_journal permission.'

# ===== 8 CROSS CUTTING =====
Add-H1 '8. Cross-Cutting Integration'
Add-H2 '8.1 Audit (SL-009)'
Add-Table @('Event', 'When') @(
    @('state_changed', 'Successful transition'),
    @('invalid_state_transition', 'Rejected transition (409)'),
    @('workflow_transition_failed', 'Precondition or publish failure')
)
Add-H2 '8.2 Event Bus (SL-302)'
Add-P 'Publishes state_transition with: org_id, record_type, record_id, event, from_state, to_state'
Add-Bullet 'workflow_read_model — bumps read-model dues counters'
Add-Bullet 'workflow_notifications — org admin notifications (non-journal)'
Add-Bullet 'job_enqueue_bridge — async webhook dispatch'
Add-H2 '8.3 RBAC & MFA (SL-003 / SL-002)'
Add-Bullet 'Journal: submit/approve/reject/post/reverse permissions + fiscal checks'
Add-Bullet 'Journal approve: maker-checker + MFA for high-value (centralized preconditions)'
Add-Bullet 'Expense: manage_expenses / approve_expense per event'
Add-Bullet 'Invoice/Bill: create_invoice / manage_settings; cancel and void guarded'
Add-H2 '8.4 Observability (SL-093)'
Add-Bullet 'workflow.transition_success_24h / workflow.transition_failure_24h'
Add-Bullet 'Dashboards: /observability (org) and /admin/observability (platform)'

# ===== 9 DATABASE =====
Add-H1 '9. Database'
Add-P 'Table: wp_orabooks_state_machine_transitions (prefix may vary)'
Add-Table @('Column', 'Purpose') @(
    @('org_id', 'Tenant traceability'),
    @('record_type', 'journal, invoice, bill, expense, commission'),
    @('record_id', 'Business record primary key'),
    @('from_state / to_state', 'State change'),
    @('event', 'Triggering event name'),
    @('triggered_by', 'User ID'),
    @('reason', 'Optional free text'),
    @('created_at', 'Timestamp')
)

# ===== 10 BUSINESS RULES =====
Add-H1 '10. Business Rules — Cancel & Void'
Add-H2 'Invoice Cancel'
Add-Table @('Rule', 'Detail') @(
    @('Allowed states', 'draft, sent'),
    @('Blocked if', 'paid, partial, or paid_amount > 0'),
    @('Side effect', 'Sets payment_status = cancelled'),
    @('Posted invoices', 'Cannot cancel')
)
Add-H2 'Bill Void'
Add-Table @('Rule', 'Detail') @(
    @('Allowed states', 'draft, submitted, approved'),
    @('Blocked if', 'paid, partial, or paid_amount > 0'),
    @('Posted bills', 'Cannot void')
)

# ===== 11 DOD =====
Add-H1 '11. Definition of Done — Final Status (15/15 Complete)'
Add-Table @('#', 'Criterion', 'Status') @(
    @('1', 'All workflow updates via OraBooks_Workflow::transition()', 'DONE'),
    @('2', 'Invalid transition returns 409 and audit log', 'DONE'),
    @('3', 'FOR UPDATE + DB transaction', 'DONE'),
    @('4', 'Preconditions + after_transition hooks', 'DONE'),
    @('5', 'state_machine_transitions.org_id', 'DONE'),
    @('6', 'Audit + state_transition event + consumers', 'DONE'),
    @('7', '5 record types migrated', 'DONE'),
    @('8', 'Invoice cancel end-to-end (backend + AJAX + UI)', 'DONE'),
    @('9', 'Bill void end-to-end (backend + AJAX + UI)', 'DONE'),
    @('10', 'Expense post then lock workflow transition', 'DONE'),
    @('11', 'REST POST /api/internal/state/transition', 'DONE'),
    @('12', 'Journal MFA + maker-checker in centralized preconditions', 'DONE'),
    @('13', 'Concurrency (FOR UPDATE) unit test', 'DONE'),
    @('14', 'Unit tests + observability metrics', 'DONE'),
    @('15', 'Documentation', 'DONE')
)

# ===== 12 TESTING - AUTOMATED =====
Add-H1 '12. Testing Guide — Part A: Automated (PHPUnit)'
Add-H2 '12.1 Full Test Suite'
Add-P 'Open terminal in: OraBooks Lean MVP folder'
Add-P 'Command: php tests/vendor/bin/phpunit -c tests/phpunit.xml'
Add-P 'Expected: All tests pass (563+ tests in full suite).'

Add-H2 '12.2 SL-301 Focused Tests (32 tests)'
Add-P 'Command:'
Add-P 'php tests/vendor/bin/phpunit -c tests/phpunit.xml --filter "OraBooks_Workflow|OraBooks_Customers_Test::test_cancel|OraBooks_Vendors_Test::test_void|OraBooks_Approval_Test|OraBooks_Rest_Api_Test::test_rest_state|OraBooks_Workflow_Integration"'
Add-P 'Expected: OK (32 tests, 83 assertions)'

Add-H2 '12.3 What Each Test File Covers'
Add-Table @('Test File', 'Coverage') @(
    @('OraBooks_Workflow_Test.php', 'Engine validation, rollback, FOR UPDATE lock, cancel transition'),
    @('OraBooks_Workflow_Integration_Test.php', 'RBAC, maker-checker, MFA, expense lock preconditions'),
    @('OraBooks_Customers_Test.php', 'Invoice cancel: draft, sent, posted reject, payment reject'),
    @('OraBooks_Vendors_Test.php', 'Bill void: draft success, posted reject, payment reject'),
    @('OraBooks_Observability_Test.php', 'Workflow health metrics by org'),
    @('OraBooks_Rest_Api_Test.php', 'REST state transition validation'),
    @('OraBooks_Deploy_Checks_Test.php', 'state_machine_transitions table in deploy checks'),
    @('OraBooks_Approval_Test.php', 'Journal approve MFA and maker-checker via workflow')
)

# ===== 13 TESTING - LIVE =====
Add-H1 '13. Testing Guide — Part B: Live Server (Manual Smoke Test)'
Add-P 'Bengali summary / Bangla guide: Deploy er por niche dewa step gulo follow korle SL-301 live e verify hobe.'

Add-H2 '13.1 Pre-requisites'
Add-Bullet 'Shared folder theke code auto-push/deploy complete hoyeche confirm koro'
Add-Bullet 'WordPress admin ba org page ekbar load koro (DB migration trigger hobe)'
Add-Bullet 'orabooks-ui theke npm run build koro (mapped drive theke — Cancel/Void button er jonno)'
Add-Bullet 'Test org subdomain e login koro (e.g. https://yourorg.yourdomain.com/dashboard/)'

Add-H2 '13.2 Deploy Health Check'
Add-Table @('Step', 'Action', 'Expected') @(
    @('1', 'WP Admin load koro / deploy checks run koro', 'JWT secret OK, DB version OK'),
    @('2', 'Verify table state_machine_transitions exists', 'Deploy check green'),
    @('3', 'Cron jobs scheduled', 'All MVP crons green')
)

Add-H2 '13.3 Record Type Smoke Tests'
Add-Table @('#', 'Record', 'Page URL', 'Steps', 'Expected Final State') @(
    @('1', 'Journal', '/journals and /approvals', 'Create draft, Submit, Approve, Post', 'locked'),
    @('2', 'Invoice', '/invoices', 'Create, Send, Record full payment', 'posted'),
    @('3', 'Invoice Cancel', '/invoices', 'Create draft, Click Cancel, Confirm', 'cancelled'),
    @('4', 'Bill', '/vendors', 'Create bill, Submit, Approve, Post', 'posted'),
    @('5', 'Bill Void', '/vendors', 'Create draft bill, Click Void, Confirm', 'void'),
    @('6', 'Expense', '/expenses', 'Upload, Submit, Approve, Post', 'locked'),
    @('7', 'Commission', '/commissions', 'Earned row, Pay action', 'paid')
)

Add-H2 '13.4 Audit & Observability Verification'
Add-Table @('Check', 'Where', 'What to Look For') @(
    @('Audit log', '/audit-log', 'state_changed entries after each transition'),
    @('Invalid transition', 'Try submit on posted journal', 'Error + invalid_state_transition in audit'),
    @('Observability', '/observability', 'workflow.transition_success_24h increasing'),
    @('Platform admin', '/admin/observability', 'Workflow health status: healthy')
)

Add-H2 '13.5 Database Verification (Optional — phpMyAdmin)'
Add-P 'After a journal transition, run:'
Add-P "SELECT * FROM wp_orabooks_state_machine_transitions WHERE record_type = 'journal' ORDER BY id DESC LIMIT 5;"
Add-P "SELECT action, details, created_at FROM wp_orabooks_audit_logs WHERE action IN ('state_changed','invalid_state_transition') ORDER BY id DESC LIMIT 10;"
Add-P 'Verify: from_state, to_state, event, org_id populated correctly.'

Add-H2 '13.6 REST API Test (Optional — Postman/cURL)'
Add-P 'POST https://yourdomain.com/wp-json/api/internal/state/transition'
Add-P 'Headers: Authorization (JWT), X-OraBooks-Org-Id: {org_id}, Content-Type: application/json'
Add-P 'Body: {"record_type":"bill","record_id":123,"event":"submit","org_id":1}'
Add-P 'Expected: 200 with transition result, or 409 for invalid transition.'

Add-H2 '13.7 Live Test Checklist (Print & Sign Off)'
Add-Table @('Done?', 'Test Item') @(
    @('[ ]', 'PHPUnit full suite pass (local/CI)'),
    @('[ ]', 'Deploy checks green on production'),
    @('[ ]', 'Journal: draft to review to approved to posted to locked'),
    @('[ ]', 'Invoice: draft to sent to posted (via payment)'),
    @('[ ]', 'Invoice Cancel: draft to cancelled'),
    @('[ ]', 'Bill: draft to submitted to approved to posted'),
    @('[ ]', 'Bill Void: draft to void'),
    @('[ ]', 'Expense: submit to approve to post to locked'),
    @('[ ]', 'Commission: earned to paid'),
    @('[ ]', 'Audit log shows state_changed entries'),
    @('[ ]', 'Observability workflow metrics healthy'),
    @('[ ]', 'Invalid transition blocked and audited')
)

# ===== 14 DEFERRALS =====
Add-H1 '14. Intentional MVP Deferrals (Not Blocking Sign-Off)'
Add-Table @('Item', 'Reason', 'Impact') @(
    @('state_machine_config DB table', 'Spec allows hard-coded machines for MVP', 'Low — filter hook available'),
    @('Dynamic per-org machine editing UI', 'Future admin feature', 'Low'),
    @('Reconciliation workflow', 'Out of SL-301 scope', 'N/A')
)

# ===== 15 DEPENDENCIES =====
Add-H1 '15. Dependency Map'
Add-P 'SL-301 Workflow State Engine depends on:'
Add-Bullet 'SL-003 RBAC — preconditions, AJAX guards'
Add-Bullet 'SL-009 Audit Log — state_changed, invalid_state_transition'
Add-Bullet 'SL-002 Approval — journal MFA, maker-checker'
Add-Bullet 'SL-302 Event Bus — state_transition publish + 3 consumers'
Add-Bullet 'SL-093 Observability — workflow metrics, health dashboard'
Add-Bullet 'SL-021 Invoices — send, post, cancel'
Add-Bullet 'SL-027 Vendors/AP — submit, approve, post, void'
Add-Bullet 'SL-304 REST API — internal state transition route'

# ===== 16 SIGN OFF =====
Add-H1 '16. Sign-Off Recommendation'
Add-P 'SL-301 is RECOMMENDED FOR MVP SIGN-OFF.'
Add-P 'All core engine requirements, caller migrations, event integration, observability, gap-closure items (cancel, void, expense lock, REST, MFA), automated tests, and documentation are complete.'
Add-P ''
Add-P 'Prepared by: OraBooks Development Team'
Add-P 'Document: SL-301-Workflow-State-Engine-Complete-Report.docx'
Add-P 'Location: OraBooks Lean MVP\docs\'

# Save
$format = 16  # wdFormatDocumentDefault = docx
if (Test-Path $outPath) { Remove-Item $outPath -Force }
$doc.SaveAs([ref]$outPath, [ref]$format)
$doc.Close()
$word.Quit()
[System.Runtime.Interopservices.Marshal]::ReleaseComObject($word) | Out-Null

Write-Host "Created: $outPath"
