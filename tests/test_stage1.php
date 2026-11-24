<?php
/**
 * STAGE 1 VERIFICATION TEST SUITE
 */

echo "=====================================================\n";
echo "NEEDIFY - STAGE 1 AUTOMATED VERIFICATION SUITE\n";
echo "=====================================================\n\n";

$passCount = 0;
$failCount = 0;

function assert_test(string $name, bool $condition, string $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] $name\n";
        $passCount++;
    } else {
        echo "[FAIL] $name" . ($details ? " ($details)" : "") . "\n";
        $failCount++;
    }
}

// 1. Test database connection
require_once __DIR__ . '/../config/database.php';
assert_test("MySQLi Connection", isset($conn) && $conn instanceof mysqli && $conn->ping(), "MySQL ping");
assert_test("MySQLi Charset utf8mb4", $conn->character_set_name() === 'utf8mb4', "Charset: " . $conn->character_set_name());
assert_test("PDO Connection", isset($pdo) && $pdo instanceof PDO, "PDO instance");

// 2. Test database tables exist
$expectedTables = ['users', 'admins', 'categories', 'products', 'needs', 'messages', 'orders', 'order_items', 'contact_messages', 'login_attempts', 'password_reset_tokens'];
$liveTables = [];
$res = $conn->query("SHOW TABLES");
while ($r = $res->fetch_row()) {
    $liveTables[] = $r[0];
}
foreach ($expectedTables as $table) {
    assert_test("Table '$table' exists", in_array($table, $liveTables, true));
}

// 3. Test users table columns (google_id, facebook_id, failed_login_attempts, locked_until)
$userCols = [];
$res = $conn->query("SHOW COLUMNS FROM users");
while ($r = $res->fetch_assoc()) {
    $userCols[] = $r['Field'];
}
assert_test("Users table has google_id", in_array('google_id', $userCols, true));
assert_test("Users table has facebook_id", in_array('facebook_id', $userCols, true));
assert_test("Users table has failed_login_attempts", in_array('failed_login_attempts', $userCols, true));
assert_test("Users table has locked_until", in_array('locked_until', $userCols, true));

// 4. Test needs table status and updated_at
$needCols = [];
$res = $conn->query("SHOW COLUMNS FROM needs");
while ($r = $res->fetch_assoc()) {
    $needCols[] = $r['Field'];
}
assert_test("Needs table has status column", in_array('status', $needCols, true));
assert_test("Needs table has updated_at column", in_array('updated_at', $needCols, true));

// 5. Test orders table columns (order_ref, customer_name, phone, delivery_address, city)
$orderCols = [];
$res = $conn->query("SHOW COLUMNS FROM orders");
while ($r = $res->fetch_assoc()) {
    $orderCols[] = $r['Field'];
}
assert_test("Orders table has order_ref", in_array('order_ref', $orderCols, true));
assert_test("Orders table has customer_name", in_array('customer_name', $orderCols, true));
assert_test("Orders table has delivery_address", in_array('delivery_address', $orderCols, true));

// 6. Test functions & security helpers
require_once __DIR__ . '/../includes/functions.php';

// Test XSS escaping
$rawXss = '<script>alert("xss")</script>&"\'';
$escaped = e($rawXss);
assert_test("XSS Escaping e()", !str_contains($escaped, '<script>') && str_contains($escaped, '&quot;') && str_contains($escaped, '&#039;'));

// Test username validation (1-24 alphanumeric only)
assert_test("Username 'fiza123' valid", validate_username('fiza123') === true);
assert_test("Username with space invalid", validate_username('fiza 123') === false);
assert_test("Username with @ invalid", validate_username('fiza@123') === false);
assert_test("Username empty invalid", validate_username('') === false);
assert_test("Username 25 chars invalid", validate_username(str_repeat('a', 25)) === false);
assert_test("Username 24 chars valid", validate_username(str_repeat('a', 24)) === true);
assert_test("Clean username removes symbols", clean_username('fiza#@! 99') === 'fiza99');

// Test email validation
assert_test("Email 'test@example.com' valid", validate_email_address('test@example.com') === true);
assert_test("Email invalid format rejected", validate_email_address('not-an-email') === false);
assert_test("Email > 254 chars rejected", validate_email_address(str_repeat('a', 250) . '@test.com') === false);

// Test password strength validation (8-35 chars, upper, lower, digit, special)
$validPass = validate_password_strength('StrongPass123#');
assert_test("Strong password passes all checks", $validPass['is_valid'] === true);

$noUpper = validate_password_strength('strongpass123#');
assert_test("Password without uppercase fails", $noUpper['is_valid'] === false && $noUpper['upper'] === false);

$noLower = validate_password_strength('STRONGPASS123#');
assert_test("Password without lowercase fails", $noLower['is_valid'] === false && $noLower['lower'] === false);

$noDigit = validate_password_strength('StrongPassword#');
assert_test("Password without digit fails", $noDigit['is_valid'] === false && $noDigit['digit'] === false);

$noSpecial = validate_password_strength('StrongPass1234');
assert_test("Password without special fails", $noSpecial['is_valid'] === false && $noSpecial['special'] === false);

$shortPass = validate_password_strength('P@1a');
assert_test("Short password (<8) fails", $shortPass['is_valid'] === false && $shortPass['length'] === false);

$longPass = validate_password_strength('Ab1#' . str_repeat('x', 35));
assert_test("Long password (>35) fails", $longPass['is_valid'] === false && $longPass['length'] === false);

// Test currency formatting
assert_test("Format PKR 500", format_price(500) === 'Rs. 500.00');

// Test CSRF
require_once __DIR__ . '/../includes/csrf.php';
$token = csrf_token();
assert_test("CSRF Token generated (64 hex chars)", strlen($token) === 64);
assert_test("CSRF Field renders HTML input", str_contains(csrf_field(), 'name="csrf_token"'));
assert_test("CSRF Meta tag renders", str_contains(csrf_meta(), 'name="csrf-token"'));
assert_test("CSRF Valid Token matches", verify_csrf_token($token) === true);
assert_test("CSRF Invalid Token rejected", verify_csrf_token('invalid_token') === false);

// Test Auth helpers
require_once __DIR__ . '/../includes/auth.php';
assert_test("is_logged_in() false for guest", is_logged_in() === false);
assert_test("is_admin_logged_in() false for guest", is_admin_logged_in() === false);

// Test Config files existence
assert_test("config/google.php exists", file_exists(__DIR__ . '/../config/google.php'));
assert_test("config/facebook.php exists", file_exists(__DIR__ . '/../config/facebook.php'));
assert_test("config/gemini.php exists", file_exists(__DIR__ . '/../config/gemini.php'));
assert_test("database/needify.sql exists", file_exists(__DIR__ . '/../database/needify.sql'));
assert_test(".htaccess exists", file_exists(__DIR__ . '/../.htaccess'));
assert_test(".env.example exists", file_exists(__DIR__ . '/../.env.example'));

echo "\n=====================================================\n";
echo "SUMMARY: Passed: $passCount, Failed: $failCount\n";
echo "=====================================================\n";

if ($failCount > 0) {
    exit(1);
}
