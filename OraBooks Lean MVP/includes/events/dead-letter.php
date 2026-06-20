<?php
if (!defined('ABSPATH')) {
    exit;
}

function orabooks_events_replay_dead_letter($dead_letter_id, $user_id = 0) {
    return OraBooks_Event_Module::replay_dead_letter($dead_letter_id, $user_id);
}
