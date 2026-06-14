<?php
// Quick debug: test the DDL conversion with exact invoice table DDL

// Copy the exact function from the test file
function mysql_to_sqlite_ddl($sql) {
    $sql = preg_replace('#/\*.*?\*/#s', '', $sql);
    $sql = preg_replace('#-- .*$#m', '', $sql);
    $sql = preg_replace('/\s*(DEFAULT\s+)?(CHARACTER\s+SET|CHARSET|COLLATE)\s+\w+/i', '', $sql);
    $sql = preg_replace("/\s+COMMENT\s+'[^']*'/i", '', $sql);

    $sql = preg_replace(
        '/`id`\s+(?:bigint|int)(?:\(\d+\))?\s+(?:NOT\s+NULL\s+)?(?:UNIQUE\s+)?(?:PRIMARY\s+KEY\s+)?AUTO_INCREMENT/i',
        '`id` INTEGER PRIMARY KEY AUTOINCREMENT',
        $sql
    );
    $sql = preg_replace('/,\s*PRIMARY\s+KEY\s*\(\s*id\s*\)/i', '', $sql);
    $sql = preg_replace('/,\s*UNIQUE\s+(KEY\s+)?\w+\s*\(\s*id\s*\)/i', '', $sql);
    $sql = preg_replace('/\bAUTO_INCREMENT\b/i', '', $sql);
    $sql = preg_replace('/\bbigint\s*\(\d+\)\s*/i', 'INTEGER ', $sql);
    $sql = preg_replace('/\bint\s*\(\d+\)\s*/i', 'INTEGER ', $sql);
    $sql = preg_replace('/\bENUM\s*\([^)]+\)/i', 'VARCHAR(20)', $sql);
    $sql = str_replace('UTC_TIMESTAMP()', "datetime('now')", $sql);
    $sql = str_replace('CURDATE()', "date('now')", $sql);
    $sql = str_replace('NOW()', "datetime('now')", $sql);
    $sql = preg_replace('/\bON\s+UPDATE\s+CURRENT_TIMESTAMP\b/i', '', $sql);
    $sql = preg_replace('/TIMESTAMP\s+DEFAULT\s+CURRENT_TIMESTAMP/i', "DATETIME DEFAULT CURRENT_TIMESTAMP", $sql);
    $sql = preg_replace('/,\s*FOREIGN\s+KEY\s*\([^)]+\)\s*REFERENCES\s+\S+\s*\([^)]+\)/i', '', $sql);
    $sql = preg_replace('/,\s*INDEX\s+\w+\s*\([^)]+\)/i', '', $sql);
    $sql = preg_replace('/,\s*UNIQUE\s+(KEY\s+)?\w+\s*\([^)]+\)/i', '', $sql);
    $sql = preg_replace('/\bKEY\s+\w+\s*\([^)]+\)\s*,?\s*/i', '', $sql);
    $sql = preg_replace("/\s+COMMENT\s+'[^']*'/i", '', $sql);
    $sql = preg_replace('/,\s*\)/', ')', $sql);
    return $sql;
}

// Exact DDL from create_invoice_tables() - invoices table
$sql_invoices = "CREATE TABLE IF NOT EXISTS wp_test_orabooks_invoices (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    org_id bigint(20) NOT NULL,
    invoice_number varchar(50) NOT NULL,
    customer_id bigint(20) NOT NULL,
    customer_name varchar(255) DEFAULT NULL,
    customer_email varchar(255) DEFAULT NULL,
    customer_address text DEFAULT NULL,
    status varchar(20) NOT NULL DEFAULT 'draft',
    payment_status varchar(20) NOT NULL DEFAULT 'unpaid',
    invoice_date datetime DEFAULT NULL,
    due_date datetime DEFAULT NULL,
    line_items longtext DEFAULT NULL,
    subtotal decimal(20,2) DEFAULT 0.00,
    discount_total decimal(20,2) DEFAULT 0.00,
    tax_total decimal(20,2) DEFAULT 0.00,
    total decimal(20,2) DEFAULT 0.00,
    paid_amount decimal(20,2) DEFAULT 0.00,
    balance_due decimal(20,2) DEFAULT 0.00,
    currency varchar(10) DEFAULT 'USD',
    notes text DEFAULT NULL,
    terms text DEFAULT NULL,
    mode varchar(20) DEFAULT 'business',
    source_type varchar(50) DEFAULT NULL,
    source_id bigint(20) DEFAULT NULL,
    je_id bigint(20) DEFAULT NULL,
    posted_at datetime DEFAULT NULL,
    posted_by bigint(20) DEFAULT NULL,
    voided_at datetime DEFAULT NULL,
    void_reason text DEFAULT NULL,
    credit_note_applied decimal(20,2) DEFAULT 0.00,
    snapshot longtext DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by bigint(20) DEFAULT NULL,
    updated_by bigint(20) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_invoice_number (invoice_number),
    KEY org_id (org_id),
    KEY customer_id (customer_id),
    KEY status (status),
    KEY payment_status (payment_status),
    KEY due_date (due_date),
    KEY source (source_type, source_id),
    KEY je_id (je_id),
    KEY mode (mode)
)";

echo "=== ID LINE IN ORIGINAL ===\n";
preg_match('/^\s*id\s+.*/m', $sql_invoices, $matches);
echo $matches[0] . "\n\n";

$converted = mysql_to_sqlite_ddl($sql_invoices);

echo "=== ID LINE IN CONVERTED ===\n";
preg_match('/^\s*id\s+.*/m', $converted, $matches);
echo $matches[0] . "\n\n";

echo "=== FULL CONVERTED SQL ===\n";
echo $converted . "\n\n";

// Try to execute
try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec($converted);
    echo "=== SUCCESS: Table created ===\n";
    
    // Verify the id column is INTEGER PRIMARY KEY
    $info = $pdo->query("PRAGMA table_info(wp_test_orabooks_invoices)")->fetchAll(PDO::FETCH_ASSOC);
    echo "=== PRAGMA table_info ===\n";
    foreach ($info as $col) {
        echo "  {$col['name']}: {$col['type']} pk={$col['pk']} notnull={$col['notnull']}\n";
    }
} catch (Exception $e) {
    echo "=== ERROR: " . $e->getMessage() . " ===\n";
    
    // Show what the full converted SQL looks like
    echo "=== FULL SQL ===\n{$converted}\n";
}
