<?php
/**
 * STAGE 3 VERIFICATION TEST SUITE
 * Comprehensive automated tests for Signup, Login, Lockout, and Social Auth.
 */

echo "=====================================================\n";
echo "NEEDIFY - STAGE 3 AUTOMATED VERIFICATION SUITE\n";
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

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/google.php';
require_once __DIR__ . '/../config/facebook.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// ---------------------------------------------------------------------
// 1. Files Verification
// ---------------------------------------------------------------------
assert_test("signup.php exists", file_exists(__DIR__ . '/../signup.php'));
assert_test("login.php exists", file_exists(__DIR__ . '/../login.php'));
assert_test("logout.php exists", file_exists(__DIR__ . '/../logout.php'));
assert_test("google-login.php exists", file_exists(__DIR__ . '/../google-login.php'));
assert_test("google-callback.php exists", file_exists(__DIR__ . '/../google-callback.php'));
assert_test("facebook-login.php exists", file_exists(__DIR__ . '/../facebook-login.php'));
assert_test("facebook-callback.php exists", file_exists(__DIR__ . '/../facebook-callback.php'));
assert_test("facebook-complete.php exists", file_exists(__DIR__ . '/../facebook-complete.php'));

// ---------------------------------------------------------------------
// 2. Strict Username Validation Tests (Section 4)
// ---------------------------------------------------------------------
assert_test("Username 'a' (1 char) is valid", validate_username('a'));
assert_test("Username 'user123' is valid", validate_username('user123'));
assert_test("Username with 24 chars is valid", validate_username(str_repeat('x', 24)));
assert_test("Username with 25 chars rejected", !validate_username(str_repeat('x', 25)));
assert_test("Username with special char '@' rejected", !validate_username('user@name'));
assert_test("Username with underscore '_' rejected", !validate_username('user_name'));
assert_test("Username with hyphen '-' rejected", !validate_username('user-name'));
assert_test("Username with space rejected", !validate_username('user name'));
assert_test("Username empty rejected", !validate_username(''));
assert_test("clean_username() strips special chars and spaces", clean_username('  User!@#_99  ') === 'User99');

// ---------------------------------------------------------------------
// 3. Strict Email Validation Tests
// ---------------------------------------------------------------------
assert_test("normalize_email trims and lowercases", normalize_email('  USER@Domain.COM  ') === 'user@domain.com');
assert_test("Valid email passes", validate_email_address('student@uos.edu.pk'));
assert_test("Invalid email without @ fails", !validate_email_address('student.uos.edu.pk'));
assert_test("Email > 254 characters fails", !validate_email_address(str_repeat('a', 250) . '@uos.pk'));

// ---------------------------------------------------------------------
// 4. Strict Password Validation Tests (Section 4)
// ---------------------------------------------------------------------
$validStrength = validate_password_strength('TestPass123#');
assert_test("Password 'TestPass123#' passes all rules", $validStrength['is_valid'] === true);

$short = validate_password_strength('T1#a');
assert_test("Password < 8 chars fails", $short['is_valid'] === false && $short['length'] === false);

$long = validate_password_strength('A1#' . str_repeat('b', 33));
assert_test("Password > 35 chars fails", $long['is_valid'] === false && $long['length'] === false);

$noUpper = validate_password_strength('testpass123#');
assert_test("Password without uppercase fails", $noUpper['is_valid'] === false && $noUpper['upper'] === false);

$noLower = validate_password_strength('TESTPASS123#');
assert_test("Password without lowercase fails", $noLower['is_valid'] === false && $noLower['lower'] === false);

$noDigit = validate_password_strength('TestPassword#');
assert_test("Password without digit fails", $noDigit['is_valid'] === false && $noDigit['digit'] === false);

$noSpecial = validate_password_strength('TestPassword123');
assert_test("Password without special character fails", $noSpecial['is_valid'] === false && $noSpecial['special'] === false);

// ---------------------------------------------------------------------
// 5. User Creation & Registration in Database
// ---------------------------------------------------------------------
$testEmail = 'stagethree_test_' . time() . '@needify.local';
$testUsername = 'testuser' . substr((string)time(), -6);
$rawPassword = 'Password123#';
$hashed = password_hash($rawPassword, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    INSERT INTO users (full_name, username, email, password)
    VALUES (?, ?, ?, ?)
");
$testFullName = 'Test Stage Three';
$stmt->bind_param("ssss", $testFullName, $testUsername, $testEmail, $hashed);
$inserted = $stmt->execute();
$newId = $stmt->insert_id;
$stmt->close();

assert_test("Insert test user into database", $inserted && $newId > 0);

// Verify password verification works and password is not plain text
$chkStmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$chkStmt->bind_param("i", $newId);
$chkStmt->execute();
$dbPassword = $chkStmt->get_result()->fetch_assoc()['password'];
$chkStmt->close();

assert_test("Database password is hashed (starts with $2y$)", str_starts_with($dbPassword, '$2y$'));
assert_test("password_verify succeeds for correct password", password_verify($rawPassword, $dbPassword));
assert_test("password_verify fails for incorrect password", !password_verify('WrongPassword123#', $dbPassword));

// ---------------------------------------------------------------------
// 6. Login Attempt Limitations & 15-Minute Lockout (Section 5)
// ---------------------------------------------------------------------
// Initially should not be locked
$lockCheck = check_user_lockout($conn, $testEmail);
assert_test("Fresh account is not locked", $lockCheck['locked'] === false);

// Simulate failed login attempts 1 through 4
for ($i = 1; $i <= 4; $i++) {
    $attempts = record_failed_login($conn, $testEmail);
    assert_test("Failed attempt $i recorded", $attempts === $i);
}
$lockCheckBefore5 = check_user_lockout($conn, $testEmail);
assert_test("Account still not locked at 4 attempts", $lockCheckBefore5['locked'] === false);

// 5th failed attempt must trigger 15-minute lockout
$attempts5 = record_failed_login($conn, $testEmail);
assert_test("5th failed attempt recorded", $attempts5 === 5);

$lockCheckAt5 = check_user_lockout($conn, $testEmail);
assert_test("Account is locked after 5 failed attempts", $lockCheckAt5['locked'] === true);
assert_test("Lockout remaining minutes is approx 15", $lockCheckAt5['remaining_minutes'] >= 14 && $lockCheckAt5['remaining_minutes'] <= 15);

// Test reset on successful login
reset_failed_logins($conn, $testEmail);
$lockCheckAfterReset = check_user_lockout($conn, $testEmail);
assert_test("Successful login resets attempts and unlocks account", $lockCheckAfterReset['locked'] === false);

// Clean up test user
$delStmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$delStmt->bind_param("i", $newId);
$delStmt->execute();
$delStmt->close();

// ---------------------------------------------------------------------
// 7. Signup UI & Checklist Render Check
// ---------------------------------------------------------------------
$_SERVER["REQUEST_METHOD"] = "GET";
$_SERVER["HTTP_HOST"] = "localhost";
$_SERVER["SERVER_NAME"] = "localhost";
$_SERVER["REQUEST_URI"] = "/Needify/signup.php";
$_SERVER["PHP_SELF"] = "/Needify/signup.php";

ob_start();
include __DIR__ . "/../signup.php";
$signupHtml = ob_get_clean();

assert_test("signup.php renders Full Name input", str_contains($signupHtml, 'name="full_name"'));
assert_test("signup.php renders Username input", str_contains($signupHtml, 'name="username"'));
assert_test("signup.php renders Email input", str_contains($signupHtml, 'name="email"'));
assert_test("signup.php renders Password input", str_contains($signupHtml, 'name="password"'));
assert_test("signup.php renders Confirm Password input", str_contains($signupHtml, 'name="confirm_password"'));
assert_test("signup.php renders live password checklist container", str_contains($signupHtml, 'id="pwdChecklist"'));
assert_test("signup.php renders 8-35 chars rule item", str_contains($signupHtml, 'id="checkLength"'));
assert_test("signup.php renders uppercase rule item", str_contains($signupHtml, 'id="checkUpper"'));
assert_test("signup.php renders lowercase rule item", str_contains($signupHtml, 'id="checkLower"'));
assert_test("signup.php renders digit rule item", str_contains($signupHtml, 'id="checkDigit"'));
assert_test("signup.php renders special char rule item", str_contains($signupHtml, 'id="checkSpecial"'));
assert_test("signup.php renders password visibility toggle", str_contains($signupHtml, 'id="togglePasswordBtn"'));
assert_test("signup.php renders CSRF hidden field", str_contains($signupHtml, 'name="csrf_token"'));
assert_test("signup.php renders Google One-Tap/Sign-In container", str_contains($signupHtml, 'g_id_onload'));
assert_test("signup.php renders Facebook OAuth button", str_contains($signupHtml, 'facebook-login.php'));

// ---------------------------------------------------------------------
// 8. Login UI & Rate Limit UX Render Check
// ---------------------------------------------------------------------
$_SERVER["REQUEST_METHOD"] = "GET";
$_SERVER["HTTP_HOST"] = "localhost";
$_SERVER["SERVER_NAME"] = "localhost";
$_SERVER["REQUEST_URI"] = "/Needify/login.php";
$_SERVER["PHP_SELF"] = "/Needify/login.php";

ob_start();
include __DIR__ . "/../login.php";
$loginHtml = ob_get_clean();

assert_test("login.php renders Email input", str_contains($loginHtml, 'name="email"'));
assert_test("login.php renders Password input", str_contains($loginHtml, 'name="password"'));
assert_test("login.php renders CSRF token", str_contains($loginHtml, 'name="csrf_token"'));
assert_test("login.php renders password visibility toggle", str_contains($loginHtml, 'id="toggleLoginPasswordBtn"'));
assert_test("login.php renders Google OAuth container", str_contains($loginHtml, 'g_id_onload'));
assert_test("login.php renders Facebook OAuth button", str_contains($loginHtml, 'facebook-login.php'));
assert_test("login.php renders Forgot Password link", str_contains($loginHtml, 'forgot-password.php'));

echo "\n=====================================================\n";
echo "STAGE 3 SUMMARY: Passed: $passCount, Failed: $failCount\n";
echo "=====================================================\n";

if ($failCount > 0) {
    exit(1);
}
