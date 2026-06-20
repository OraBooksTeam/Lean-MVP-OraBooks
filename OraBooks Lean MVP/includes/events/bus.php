<?php
if (!defined('ABSPATH')) {
    exit;
}

function orabooks_events_process_outbox($limit = 25) {
    return OraBooks_Event_Module::process_outbox($limit);
}
