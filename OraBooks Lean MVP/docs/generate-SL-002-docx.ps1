# Generates AI Entry Approval Gate complete Word report (.docx)
$ErrorActionPreference = 'Stop'
$docName = '-AI-Entry-Approval-Gate-Complete-Report.docx'
$localPath = Join-Path $env:TEMP $docName
$outPath = Join-Path $PSScriptRoot $docName

$word = New-Object -ComObject Word.Application
$word.Visible = $false
$doc = $word.Documents.Add
$sel = $word.Selection

function Set-Style([string]$name) { $sel.Style = $doc.Styles.Item($name) }
function Add-H1([string]$text) { Set-Style 'Heading 1'; $sel.TypeText($text); $sel.TypeParagraph }
function Add-H2([string]$text) { Set-Style 'Heading 2'; $sel.TypeText($text); $sel.TypeParagraph }
function Add-P([string]$text) { Set-Style 'Normal'; $sel.TypeText($text); $sel.TypeParagraph }
function Add-Bullet([string]$text) { Set-Style 'Normal'; $sel.Range.ListFormat.ApplyBulletDefault; $sel.TypeText($text); $sel.TypeParagraph; $sel.Range.ListFormat.RemoveNumbers }

Add-H1 ' AI Entry Approval Gate'
Add-P 'OraBooks Lean MVP - Complete Implementation Report'
Add-P ('Generated: ' + (Get-Date -Format 'yyyy-MM-dd HH:mm'))

Add-H2 '1. Executive Summary'
Add-P ' implements the human journal approval workflow. AI never approves entries; it only provides confidence scores via /. This report documents completion of remaining gaps: backend hardening, UI polish, invoice journal submit chain, tests, and deploy checks.'

Add-H2 '2. Core Guarantees Delivered'
Add-Bullet 'Only approver/owner (or active delegate) can approve or reject journals.'
Add-Bullet 'Append-only journal_approval_history with MySQL triggers blocking UPDATE/DELETE.'
Add-Bullet 'No rejected status - reject returns journal to draft with immutable history record.'
Add-Bullet 'revision_number tracks content edits; approval_round tracks submit/resubmit cycles.'
Add-Bullet 'Snapshot hash on submit (history) and approve (approved_snapshot_hash).'
Add-Bullet 'Posting requires status=approved and approval_stale=false.'
Add-Bullet 'MFA for high-value approvals; maker-checker policy enforced.'
Add-Bullet 'Escalation and expiry crons with idempotency guards.'

Add-H2 '3. Backend Changes'
Add-Bullet 'promote_to_review_pending clears approved_snapshot_hash on submit (spec alignment).'
Add-Bullet 'Escalation cron skips duplicate escalate per approval_round.'
Add-Bullet 'Expiry reminder cron uses transient to prevent hourly duplicate reminders.'
Add-Bullet 'Delegation create publishes approval_delegated outbox event.'
Add-Bullet 'Expiry publishes approval_expired (spec) and journal_approval_expired (compat).'
Add-Bullet 'Invoice post now submits journal into review_pending queue.'
Add-Bullet 'Deploy checks include tables and approval crons.'

Add-H2 '4. UI Deliverables'
Add-Bullet 'JournalsPage: Approval History tab, resubmit button, MFA/reject modals, accent styling.'
Add-Bullet 'ApprovalsPage: Pending queue sort dropdown, styled modals, settings link.'
Add-Bullet 'Approval Settings page: policy CRUD and delegation management (owner).'
Add-Bullet 'UI accent color matches sidebar active menu (bg-accent).'

Add-H2 '5. Test Coverage'
Add-P 'OraBooks_Approval_Test.php - 17 tests, all passing.'
Add-Bullet 'Maker-checker, MFA, resubmit, invalidate-on-edit, delegation validation.'
Add-Bullet 'History action idempotency, expiry cron, escalation skip, outbox submit event.'
Add-Bullet 'Delegation-based user_can_approve.'

Add-H2 '6. User Journey (End-to-End)'
Add-P 'Create journal or post invoice, submit, review_pending, approver approves (MFA if high value), post to ledger, ledger/reports update.'

Add-H2 '7. How to Test (Manual)'
Add-Bullet 'Login as creator: create draft journal, submit for approval.'
Add-Bullet 'Login as approver: open Approvals, sort queue, approve (enter MFA if amount above threshold).'
Add-Bullet 'Post approved journal; verify Reports/Trial Balance reflects entry.'
Add-Bullet 'Reject a pending journal with reason; verify Draft (Rejected) hint and resubmit.'
Add-Bullet 'Owner: Approval Settings - change MFA threshold, create delegation.'
Add-Bullet 'Post an invoice; verify journal appears in Approvals queue.'

Add-H2 '8. How to Test (Automated)'
Add-P 'Run: php tests/vendor/bin/phpunit --configuration tests/phpunit.xml tests/OraBooks_Approval_Test.php'

Add-H2 '9. Dependencies'
Add-Bullet ' Posting, RBAC, Audit, Notifications, AI Review (optional routing).'

Add-H2 '10. Status'
Add-P ' Lean MVP: COMPLETE - all checklist gaps from Phase 0-4 addressed.'

$format = 16
if (Test-Path $localPath) { Remove-Item $localPath -Force }
$doc.SaveAs2($localPath, $format)
$doc.Close
$word.Quit
[System.Runtime.Interopservices.Marshal]::ReleaseComObject($word) | Out-Null

Copy-Item -Path $localPath -Destination $outPath -Force
Write-Host ('Created: ' + $outPath)
