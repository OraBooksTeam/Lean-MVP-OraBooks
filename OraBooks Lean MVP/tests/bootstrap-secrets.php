<?php
/**
 * PHPUnit bootstrap for SL-008 Secrets/TLS tests (loads real OraBooks_Secrets).
 */

$GLOBALS['ORABOOKS_LOAD_REAL_SECRETS'] = true;
require __DIR__ . '/bootstrap.php';
