<?php
if (!defined('ABSPATH')) {
    exit;
}

function orabooks_events_table($name) {
    return OraBooks_Event_Module::table($name);
}
