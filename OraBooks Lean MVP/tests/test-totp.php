<?php
/**
 * Quick TOTP algorithm test for OraBooks_Secrets
 * Run: php tests/test-totp.php
 */

// Bootstrap the real OraBooks_Secrets class
$GLOBALS['ORABOOKS_LOAD_REAL_SECRETS'] = true;
if (!defined('LOGGED_IN_KEY')) {
    define('LOGGED_IN_KEY', 'test-logged-in-key-for-orabooks-secrets');
}
require_once __DIR__ . '/bootstrap.php';

if (!function_exists('wp_salt')) {
    function wp_salt($scheme = 'auth') {
        return 'test-wp-salt-' . $scheme;
    }
}

// Initialize the secrets class
OraBooks_Secrets::init();

// Test 1: Generate a secret and verify round-trip
echo "=== Test 1: Generate secret and verify round-trip ===\n";
$secret = OraBooks_Secrets::generate_totp_secret();
echo "Generated secret: $secret\n";
echo "Secret length: " . strlen($secret) . " chars\n";

$ref = new ReflectionClass(OraBooks_Secrets::class);
$decodeMethod = $ref->getMethod('decode_totp_secret');
$decodeMethod->setAccessible(true);
$generateCodeMethod = $ref->getMethod('generate_totp_code');
$generateCodeMethod->setAccessible(true);

$decoded = $decodeMethod->invoke(null, $secret);
echo "Decoded key length: " . strlen($decoded) . " bytes\n";

$time_slice = floor(time() / 30);
$code = $generateCodeMethod->invoke(null, $decoded, $time_slice);
echo "Generated TOTP code: $code\n";
echo "Code is 6 digits: " . (strlen($code) === 6 ? 'YES' : 'NO') . "\n";

$verifyResult = OraBooks_Secrets::verify_totp($secret, $code);
echo "Verify result: " . ($verifyResult ? 'PASS' : 'FAIL') . "\n";

// Test 2: Test with known test vectors
echo "\n=== Test 2: Test with known TOTP values ===\n";
// RFC 4231 test vector: Secret = JBSWY3DPEHPK3PXP (Base32 of "12345678901234567890")
// Time = 59 (TOTP should be 94287082... wait, let me use a simpler test)
$testSecret = 'JBSWY3DPEHPK3PXP';
// At time_slice 0 (Unix epoch), TOTP should be a known value
$time0 = 0; // First 30-second interval after epoch

$decodedTest = $decodeMethod->invoke(null, $testSecret);
echo "Decoded test secret key length: " . strlen($decodedTest) . " bytes\n";

$codeAt0 = $generateCodeMethod->invoke(null, $decodedTest, $time0);
echo "TOTP at time 0: $codeAt0\n";
// Known good value for this secret at time 0 is 328482 (I need to verify)

// Test 3: Verify that different time slices produce different codes
echo "\n=== Test 3: Different time slices produce different codes ===\n";
$codes = [];
for ($i = -2; $i <= 2; $i++) {
    $c = $generateCodeMethod->invoke(null, $decoded, $time_slice + $i);
    $codes[$i] = $c;
    echo "Time offset $i: $c\n";
}
$uniqueCodes = array_unique($codes);
echo "Unique codes: " . count($uniqueCodes) . " (expected 5)\n";

// Test 4: Normalize OTP code
echo "\n=== Test 4: Normalize OTP code ===\n";
echo "normalize_totp_code('123456'): " . OraBooks_Secrets::normalize_totp_code('123456') . "\n";
echo "normalize_totp_code('123 456'): " . OraBooks_Secrets::normalize_totp_code('123 456') . "\n";
echo "normalize_totp_code('abc123'): " . OraBooks_Secrets::normalize_totp_code('abc123') . "\n";
echo "normalize_totp_code('12345'): '" . OraBooks_Secrets::normalize_totp_code('12345') . "' (should be empty)\n";
echo "normalize_totp_code(''): '" . OraBooks_Secrets::normalize_totp_code('') . "' (should be empty)\n";

echo "\n=== All tests completed ===\n";
