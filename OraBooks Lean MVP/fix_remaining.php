<?php
/**
 * Fix remaining issues:
 * 1. Double () from get_charset_collate fix (get_charset_collate()() -> get_charset_collate())
 * 2. time -> time() in secrets.php and exports.php
 * 3. is_ssl -> is_ssl() in secrets.php
 * 4. home_url -> home_url() in secrets.php
 * 5. Remaining $e->getMessage without () 
 * 6. $e->getMessage without () in get_report_data
 * 7. self::get_jwt_secret in array without ()
 * 8. self::$bootstrap_error->get_error_message without ()
 */

$secrets_path = __DIR__ . '/includes/class-orabooks-secrets.php';
$exports_path = __DIR__ . '/includes/class-orabooks-exports.php';
$database_path = __DIR__ . '/includes/class-orabooks-database.php';

// Fix 1: Double () in all files
foreach ([$secrets_path, $exports_path, $database_path] as $path) {
    if (!file_exists($path)) continue;
    $content = file_get_contents($path);
    $original = $content;
    
    // Fix get_charset_collate()() -> get_charset_collate()
    $content = str_replace('$wpdb->get_charset_collate()()', '$wpdb->get_charset_collate()', $content);
    
    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "FIXED double () in $path\n";
    }
}

// Now fix remaining issues in secrets.php
$content = file_get_contents($secrets_path);
$original_secrets = $content;

// Fix time -> time() - be careful with current_time, strtotime etc
$content = preg_replace('/\btime\b(?=\s*[+\->)=,])/', 'time()', $content);

// But DON'T change time inside function names like current_time, strtotime, etc.
// The regex above handles this because \b requires word boundary before time
// current_time has no word boundary before time (it's current_time, not current time)

// Fix is_ssl -> is_ssl() (not inside function_exists strings)
$content = preg_replace('/\bis_ssl\b(?=\s*[\|\|&&?:;,)\]])/', 'is_ssl()', $content);

// Fix home_url -> home_url()
$content = preg_replace('/\bhome_url\b(?=\s*[,;)\]])/', 'home_url()', $content);

// Fix $e->getMessage without () - remaining instances
$content = preg_replace('/\$e->getMessage\b(?!\s*\()/', '\$e->getMessage()', $content);

// Fix self::get_jwt_secret in array without ()
// Pattern: [self::get_jwt_secret] -> [self::get_jwt_secret()]
$content = preg_replace('/\[self::get_jwt_secret\]/', '[self::get_jwt_secret()]', $content);

// Fix $secrets = [self::get_jwt_secret]; 
$content = preg_replace('/\$secrets = \[self::get_jwt_secret\]/', '\$secrets = [self::get_jwt_secret()]', $content);

// Fix remaining get_error_message without ()
$content = preg_replace('/\$bootstrap_error\s*\?\s*\$[a-zA-Z_>]+\bget_error_message\b(?!\s*\()/', '\$bootstrap_error ? \$bootstrap_error->get_error_message()', $content);

// Clean up any doubled time()()
$content = str_replace('time()()', 'time()', $content);

if ($content !== $original_secrets) {
    file_put_contents($secrets_path, $content);
    echo "FIXED remaining issues in secrets.php\n";
} else {
    echo "NO CHANGE in secrets.php\n";
}

// Fix remaining issues in exports.php
$content = file_get_contents($exports_path);
$original_exports = $content;

// Fix time -> time() in exports.php
$content = preg_replace('/\btime\b(?=\s*[+\->)=,])/', 'time()', $content);

// Fix $e->getMessage without () in get_report_data catch block
$content = preg_replace('/\$e->getMessage\b(?!\s*\()/', '\$e->getMessage()', $content);

// Fix NOW -> NOW() in SQL strings
$content = str_replace("'ready'\n    AND r.expires_at < NOW", "'ready'\n    AND r.expires_at < NOW()", $content);
$content = str_replace("DATE_SUB(NOW, INTERVAL 24 HOUR)", "DATE_SUB(NOW(), INTERVAL 24 HOUR)", $content);

// Clean up any doubled time()()
$content = str_replace('time()()', 'time()', $content);

if ($content !== $original_exports) {
    file_put_contents($exports_path, $content);
    echo "FIXED remaining issues in exports.php\n";
} else {
    echo "NO CHANGE in exports.php\n";
}

echo "\nDONE - remaining fixes applied\n";
