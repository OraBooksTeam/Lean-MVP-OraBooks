<?php
require __DIR__ . '/bootstrap.php';

global $wpdb;
$import = (object) [
    'id' => 10,
    'org_id' => 1,
    'user_id' => 1,
    'resource_type' => 'inventory_item',
    'status' => 'pending_confirm',
    'confirm_idempotency_key' => null,
    'header_mapping' => '{}',
    'total_rows' => 1,
];

$wpdb->test_get_row_callback = function ($query) use ($import) {
    echo "GET_ROW: $query\n";
    if (stripos($query, 'csv_imports') !== false) {
        return $import;
    }
    return (object) [
        'id' => 1,
        'status' => 'active',
        'organization_type' => 'customer',
        'owner_id' => 1,
    ];
};

$wpdb->test_get_var_callback = function ($query) {
    echo "GET_VAR: $query\n";
    if (stripos($query, 'confirm_idempotency_key') !== false) {
        return 99;
    }
    if (stripos($query, 'user_org') !== false) {
        return 'owner';
    }
    return null;
};

$perm = OraBooks_RBAC::require_permission(1, 1, 'submit_transaction');
echo "PERM: " . var_export($perm, true) . "\n";

$result = OraBooks_Csv_Imports::confirm_import(10, 1, 1, 'dup-key-123');
echo "RESULT: " . var_export($result, true) . "\n";
