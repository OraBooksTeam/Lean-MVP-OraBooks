<?php
define('ABSPATH', __DIR__ . '/../');
require __DIR__ . '/bootstrap.php';

$h = 'Product SKU';
$n = strtolower(preg_replace('/[^a-z0-9]+/', '_', trim($h)));
echo "normalized={$n}\n";

$r = new ReflectionMethod('OraBooks_Csv_Imports', 'get_field_aliases');
$r->setAccessible(true);
$aliases = $r->invoke(null, 'inventory_item');
echo "alias keys: " . implode(', ', array_keys($aliases)) . "\n";
echo "sku patterns: " . implode(', ', $aliases['sku'] ?? []) . "\n";

var_export(OraBooks_Csv_Imports::suggest_header_mapping(['Product SKU', 'Product Name', 'Quantity'], 'inventory_item'));
