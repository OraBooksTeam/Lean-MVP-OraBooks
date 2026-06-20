<?php
if (!defined('ABSPATH')) {
    exit;
}

function orabooks_events_notification_health() {
    return OraBooks_Event_Module::get_health();
}
