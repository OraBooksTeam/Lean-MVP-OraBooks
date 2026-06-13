<?php
require_once('../../wp-load.php');
$user = get_user_by('login', 'jahid');
if ($user) {
    echo "User ID: " . $user->ID . "\n";
    echo "Subdomain: " . get_user_meta($user->ID, 'orabooks_subdomain', true) . "\n";
} else {
    echo "User jahid not found.\n";
}
