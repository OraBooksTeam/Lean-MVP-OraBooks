<?php
if (!defined('ABSPATH')) {
    exit;
}

function orabooks_events_publish_journal_posted($journal_id, array $payload = []) {
    return OraBooks_Event_Module::publish('journal_posted', $journal_id, $payload);
}
