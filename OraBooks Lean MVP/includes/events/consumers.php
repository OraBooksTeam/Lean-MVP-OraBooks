<?php
if (!defined('ABSPATH')) {
    exit;
}

function orabooks_events_register_consumer($event_type, $consumer_key, $handler) {
    OraBooks_Event_Module::register_consumer($event_type, $consumer_key, $handler);
}
