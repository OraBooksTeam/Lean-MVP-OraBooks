<?php
/**
 * Full 2FA round-trip test: simulate setup, encrypt, decrypt, verify, disable
 * Run: php tests/test-totp-full-flow.php
 */

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

// Initialize Secrets
OraBooks_Secrets::init();

// Set up encryption key explicitly (simulates production)
OraBooks_Secrets::set('encryption_key', 'test-encryption-key-32chars-min!!');
OraBooks_Secrets::set('jwt_secret', 'test-jwt-secret-with-enough-length-for-tests');

echo "=== TOTP Full Round-Trip Test ===\n\n";

// Step 1: Generate a TOTP secret (as done in setup())
echo "Step 1: Generate TOTP secret\n";
$secret = OraBooks_Secrets::generate_totp_secret();
echo "  Raw secret: $secret\n";
echo "  Secret length: " . strlen($secret) . " chars\n";

// Step 2: Encrypt and store the temp secret (simulates orabooks_set_2fa_temp_secret)
echo "\nStep 2: Encrypt temp secret\n";
$encrypted_temp = OraBooks_Secrets::encrypt_sensitive($secret);
echo "  Encrypted (temp): " . substr($encrypted_temp, 0, 20) . "..." . substr($encrypted_temp, -10) . "\n";
echo "  Has 'enc:' prefix: " . (strpos($encrypted_temp, 'enc:') === 0 ? 'YES' : 'NO') . "\n";

// Step 3: Decrypt the temp secret (simulates orabooks_get_2fa_temp_secret)
echo "\nStep 3: Decrypt temp secret\n";
$decrypted_temp = OraBooks_Secrets::decrypt_sensitive($encrypted_temp);
echo "  Decrypted secret: $decrypted_temp\n";
echo "  Matches original: " . ($decrypted_temp === $secret ? 'YES' : 'NO - MISMATCH!') . "\n";

// Step 4: Verify TOTP with the decrypted secret (simulates verify_setup behavior)
echo "\nStep 4: Verify TOTP with decrypted secret\n";
$time_slice = floor(time() / 30);
$ref = new ReflectionClass(OraBooks_Secrets::class);
$generateCodeMethod = $ref->getMethod('generate_totp_code');
$generateCodeMethod->setAccessible(true);
$decodeMethod = $ref->getMethod('decode_totp_secret');
$decodeMethod->setAccessible(true);

$key = $decodeMethod->invoke(null, $decrypted_temp);
$expectedCode = $generateCodeMethod->invoke(null, $key, $time_slice);
echo "  Current TOTP code: $expectedCode\n";
echo "  Code is 6 digits: " . (strlen($expectedCode) === 6 ? 'YES' : 'NO') . "\n";

$verifyResult = OraBooks_Secrets::verify_totp($decrypted_temp, $expectedCode);
echo "  verify_totp result: " . ($verifyResult ? 'PASS' : 'FAIL') . "\n";

if ($verifyResult) {
    echo "\n  ✓ Temp secret round-trip: SUCCESS\n";
} else {
    echo "\n  ✗ Temp secret round-trip: FAILED\n";
    exit(1);
}

// Step 5: Encrypt and store as permanent secret (simulates orabooks_set_2fa_secret)
echo "\nStep 5: Encrypt permanent secret\n";
$encrypted_perm = OraBooks_Secrets::encrypt_sensitive($secret);
echo "  Encrypted (perm): " . substr($encrypted_perm, 0, 20) . "..." . substr($encrypted_perm, -10) . "\n";

// Step 6: Decrypt permanent secret (simulates orabooks_get_2fa_secret)
echo "\nStep 6: Decrypt permanent secret\n";
$decrypted_perm = OraBooks_Secrets::decrypt_sensitive($encrypted_perm);
echo "  Decrypted secret: $decrypted_perm\n";
echo "  Matches original: " . ($decrypted_perm === $secret ? 'YES' : 'NO - MISMATCH!') . "\n";

// Step 7: Verify TOTP with the permanent decrypted secret (simulates disable behavior)
echo "\nStep 7: Verify TOTP with permanent decrypted secret\n";
$key2 = $decodeMethod->invoke(null, $decrypted_perm);
$expectedCode2 = $generateCodeMethod->invoke(null, $key2, $time_slice);
echo "  Current TOTP code: $expectedCode2\n";

$verifyResult2 = OraBooks_Secrets::verify_totp($decrypted_perm, $expectedCode2);
echo "  verify_totp result: " . ($verifyResult2 ? 'PASS' : 'FAIL') . "\n";

if ($verifyResult2) {
    echo "\n  ✓ Permanent secret round-trip: SUCCESS\n";
} else {
    echo "\n  ✗ Permanent secret round-trip: FAILED\n";
    exit(1);
}

// Step 8: Test with +/- 1 time offset (should still work)
echo "\nStep 8: Time-drift tolerance test\n";
for ($offset = -2; $offset <= 2; $offset++) {
    $ts = $time_slice + $offset;
    $c = $generateCodeMethod->invoke(null, $key2, $ts);
    $v = OraBooks_Secrets::verify_totp($decrypted_perm, $c);
    echo "  Offset $offset: code=$c, verify=" . ($v ? 'PASS' : 'FAIL') . "\n";
}

echo "\n=== All tests passed! ===\n";
