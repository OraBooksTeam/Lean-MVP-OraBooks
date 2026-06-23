<?php
/**
 * Final fix: catch ALL function declarations still missing ().
 *
 * Pattern: function NAME {  →  function NAME() {
 * Where NAME is [a-zA-Z_][a-zA-Z0-9_]*
 */
$dirs = [
    __DIR__,
    __DIR__ . '/jobs',
    __DIR__ . '/events',
];

$fixed_count = 0;
$file_count = 0;

foreach ($dirs as $dir) {
    foreach (glob($dir . '/*.php') as $file) {
        if (basename($file) === 'fix_func_decls.php') continue;

        $content = file_get_contents($file);
        $original = $content;
        $in_heredoc = false;

        // Fix: function name {  →  function name() {
        // Very broad regex: "function" + whitespace + identifier + whitespace + "{"
        $content = preg_replace(
            '/\bfunction\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\{/',
            'function $1() {',
            $content
        );

        // Also fix anonymous closures where function { without name
        $content = preg_replace(
            '/\bfunction\s*\{/',
            'function() {',
            $content
        );

        if ($content !== $original) {
            file_put_contents($file, $content);
            $fixed_count++;
            $file_count++;
            echo "Fixed: $file\n";
        }
    }
}

echo "\nDone. Fixed function declarations in $file_count files, $fixed_count total replacements.\n";
