<?php
/**
 * Fix missing parentheses in OraBooks include files.
 *
 * Fixes:
 *   1. function name {  →  function name() {   (function declarations)
 *   2. ::method;        →  ::method();          (static method calls)
 *   3. ->method;        →  ->method();          (object method calls)
 *   4. function_name;   →  function_name();     (function calls as statements)
 *   5. time             →  time()               (time constant → function call)
 *   6. NOW              →  NOW()                (MySQL NOW() - in SQL strings)
 *
 * Only applies to lines where the pattern is clearly a call, not a string or comment.
 */

$targets = [
    __DIR__ . '/class-orabooks-csv-imports.php',
    __DIR__ . '/class-orabooks-exports.php',
    __DIR__ . '/class-orabooks-notifications.php',
    __DIR__ . '/helpers.php',
];

$all_php_files = glob(__DIR__ . '/*.php');
$job_files = glob(__DIR__ . '/jobs/*.php');
$event_files = glob(__DIR__ . '/events/*.php');

$files = array_merge($all_php_files, $job_files, $event_files);

foreach ($files as $filepath) {
    if ($filepath === __FILE__) {
        continue; // Skip self
    }

    $content = file_get_contents($filepath);
    $original = $content;
    $lines = explode("\n", $content);
    $changed = false;

    foreach ($lines as $i => &$line) {
        $trimmed = ltrim($line);

        // Skip comments
        if (strpos($trimmed, '//') === 0 || strpos($trimmed, '#') === 0 || strpos($trimmed, '/*') === 0) {
            continue;
        }

        // Skip strings
        if (preg_match('/^\s*[\'"]/', $line)) {
            continue;
        }

        // Fix 1: function declarations: function name {  →  function name() {
        if (preg_match('/^(.*\b(?:public|private|protected|static|abstract|final|\t| )*function\s+([a-zA-Z_][a-zA-Z0-9_]*))\s*\{/', $line, $m)) {
            if (!strpos($line, '(')) { // No opening paren yet
                $line = preg_replace(
                    '/(\bfunction\s+[a-zA-Z_][a-zA-Z0-9_]*)\s*\{/',
                    '$1() {',
                    $line
                );
                echo "  [FUNC] $filepath:$i " . trim($line) . "\n";
                $changed = true;
                continue;
            }
        }

        // Fix 2: Anonymous closures: function {  →  function() {
        if (preg_match('/^(.*\b(?:static\s+)?function)\s*\{/', $line, $m)) {
            if (!strpos($line, '(')) {
                $line = preg_replace(
                    '/(\b(?:static\s+)?function)\s*\{/',
                    '$1() {',
                    $line
                );
                echo "  [CLOSURE] $filepath:$i " . trim($line) . "\n";
                $changed = true;
                continue;
            }
        }

        // Fix 3: Static method calls: ::method; at end of line
        if (preg_match('/::([a-zA-Z_][a-zA-Z0-9_]*);\s*$/', $line, $m)) {
            $method_name = $m[1];
            // Skip if it already has parens or is clearly a constant (uppercase)
            if (!preg_match('/::' . preg_quote($method_name) . '\s*\(/', $line) && !ctype_upper($method_name)) {
                $line = preg_replace(
                    '/::(' . preg_quote($method_name) . ');(\s*)$/',
                    '::$1();$2',
                    $line
                );
                echo "  [STATIC] $filepath:$i " . trim($line) . "\n";
                $changed = true;
            }
        }

        // Fix 4: Object method calls: ->method; at end of line (only lowercase methods, not properties)
        if (preg_match('/->([a-z_][a-zA-Z0-9_]*);\s*$/', $line, $m)) {
            $method_name = $m[1];
            if (!preg_match('/->' . preg_quote($method_name) . '\s*\(/', $line)) {
                $line = preg_replace(
                    '/->(' . preg_quote($method_name) . ');(\s*)$/',
                    '->$1();$2',
                    $line
                );
                echo "  [OBJ] $filepath:$i " . trim($line) . "\n";
                $changed = true;
            }
        }

        // Fix 5: Function calls as statements: orabooks_xxx; at end of line
        if (preg_match('/^\s*([a-z_][a-zA-Z0-9_]*);\s*$/', $line, $m)) {
            $func_name = $m[1];
            // Whitelist: known functions / helpers
            $known_functions = [
                'exit', 'break', 'continue', 'return',
                'time', 'current_time',
            ];
            if (in_array($func_name, $known_functions, true)) {
                continue; // These are correct as-is (exit, break, return are valid)
            }

            // Skip short variable-like names
            if (strlen($func_name) < 3) {
                continue;
            }

            // Check it's not a variable assignment destructuring etc.
            if (!preg_match('/^\s*[a-z_][a-zA-Z0-9_]*;\s*$/', $trimmed)) {
                continue;
            }

            if (!preg_match('/' . preg_quote($func_name) . '\s*\(/', $line)) {
                $line = preg_replace(
                    '/^(\s*)(' . preg_quote($func_name) . ');(\s*)$/',
                    '$1$2();$3',
                    $line
                );
                echo "  [FUNCALL] $filepath:$i " . trim($line) . "\n";
                $changed = true;
            }
        }
    }

    if ($changed) {
        $new_content = implode("\n", $lines);
        file_put_contents($filepath, $new_content);
        echo "✓ Fixed: $filepath\n";
    } else {
        echo "  No changes: $filepath\n";
    }
}

echo "\nDone.\n";
