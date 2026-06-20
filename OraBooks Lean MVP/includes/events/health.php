<?php
if (!defined('ABSPATH')) {
    exit;
}

function orabooks_events_health() {
    return OraBooks_Event_Module::get_health();
}
