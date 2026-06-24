<?php
/**
 * Fix remaining issues the reviewer flagged:
 * 1. time; (without ()) in secrets.php - e.g. $payload['iat'] = time;
 * 2. self::$bootstrap_error->get_error_message: without () in block_orabooks_ajax_when_not_ready
 */

$secrets_path = __DIR__ . '/includes/class-orabooks-secrets.php';
$content = file_get_contents($secrets_path);
$original = $content;

// Fix time at end of line or followed by ;
$content = preg_replace('/\btime\s*;/', 'time();', $content);

// Fix self::$bootstrap_error->get_error_message without ()
// Pattern: self::$bootstrap_error ? self::$bootstrap_error->get_error_message:
$content = preg_replace(
    '/self::\$bootstrap_error \? self::\$bootstrap_error->get_error_message\b(?!\s*\()/',
    'self::$bootstrap_error ? self::$bootstrap_error->get_error_message()',
    $content
);

// Also fix any remaining self::$bootstrap_error->get_error_message patterns
$content = preg_replace(
    '/self::\$bootstrap_error->get_error_message\b(?!\s*\()/',
    'self::$bootstrap_error->get_error_message()',
    $content
);

if ($content !== $original) {
    file_put_contents($secrets_path, $content);
    echo "FIXED remaining issues in secrets.php\n";
} else {
    echo "NO CHANGE in secrets.php\n";
}

// Clean up temp fix scripts
$temp_files = [
    __DIR__ . '/fix_method_calls.php',
    __DIR__ . '/fix_remaining.php',
    __DIR__ . '/fix_last.php',
];
foreach ($temp_files as $f) {
    if (file_exists($f)) {
        unlink($f);
        echo "DELETED $f\n";
    }
}
