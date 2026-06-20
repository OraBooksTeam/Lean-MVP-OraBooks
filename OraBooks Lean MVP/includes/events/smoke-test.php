<?php
if (!defined('ABSPATH')) {
    exit;
}

function orabooks_events_smoke_test() {
    return [
        'tables' => array_map(['OraBooks_Event_Module', 'table'], [
            'event_outbox',
            'event_consumer_log',
            'event_dead_letter',
            'event_notifications',
            'event_notification_reads',
        ]),
        'events' => OraBooks_Event_Module::canonical_event_types(),
        'health' => OraBooks_Event_Module::get_health(),
    ];
}
