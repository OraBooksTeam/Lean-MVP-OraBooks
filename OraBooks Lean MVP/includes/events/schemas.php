<?php
if (!defined('ABSPATH')) {
    exit;
}

function orabooks_events_canonical_event_types() {
    return OraBooks_Event_Module::canonical_event_types();
}
