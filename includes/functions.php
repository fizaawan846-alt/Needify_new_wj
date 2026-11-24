<?php
/**
 * NEEDIFY - CORE UTILITY AND VALIDATION FUNCTIONS
 * Sanitization, output escaping, validation rules, currency formatting,
 * and security helper functions.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/csrf.php';

// ---------------------------------------------------------------------
// 1. Output Escaping and Sanitization
// ---------------------------------------------------------------------

if (!function_exists('e')) {
    /**
     * Escape HTML output safely (XSS protection)
     */
    function e(?string $value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitize_text')) {
    /**
     * Trim and strip control characters from text inputs
     */
    function sanitize_text(?string $value): string {
        if ($value === null) return '';
        $clean = trim($value);
        // Remove null bytes and invisible control characters except standard whitespace
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean);
    }
}

// ---------------------------------------------------------------------
// 2. Strict Input Validation (Needify Platform Rules)
// ---------------------------------------------------------------------

if (!function_exists('clean_username')) {
    /**
     * Clean username: keep only English letters (A-Z, a-z) and digits (0-9)
     * Automatically removes any special character or spaces.
     */
    function clean_username(?string $username): string {
        if ($username === null) return '';
        return preg_replace('/[^a-zA-Z0-9]/', '', trim($username));
    }
}

if (!function_exists('validate_username')) {
    /**
     * Strict username validation:
     * - Minimum 1 character
     * - Maximum 24 characters
     * - Only English letters (A-Z, a-z) and digits (0-9)
     * - No special characters, no spaces
     */
    function validate_username(?string $username): bool {
        if ($username === null) return false;
        $len = strlen($username);
        if ($len < 1 || $len > 24) {
            return false;
        }
        return (bool)preg_match('/^[a-zA-Z0-9]{1,24}$/', $username);
    }
}

if (!function_exists('normalize_email')) {
    /**
     * Normalize email: trim and lowercase
     */
    function normalize_email(?string $email): string {
        return strtolower(trim((string)($email ?? '')));
    }
}

if (!function_exists('validate_email_address')) {
    /**
     * Strict email validation:
     * - Maximum 254 characters (RFC 5321)
     * - Proper email format
     */
    function validate_email_address(?string $email): bool {
        if ($email === null) return false;
        $normalized = normalize_email($email);
        $len = strlen($normalized);
        if ($len < 3 || $len > 254) {
            return false;
        }
        return (bool)filter_var($normalized, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('validate_password_strength')) {
    /**
     * Strict password validation:
     * - Minimum 8 characters
     * - Maximum 35 characters
     * - At least one uppercase letter (A-Z)
     * - At least one lowercase letter (a-z)
     * - At least one numerical digit (0-9)
     * - At least one special character (@, #, $, !, %, etc.)
     *
     * @return array [
     *   'is_valid' => bool,
     *   'length'   => bool,
     *   'upper'    => bool,
     *   'lower'    => bool,
     *   'digit'    => bool,
     *   'special'  => bool
     * ]
     */
    function validate_password_strength(?string $password): array {
        if ($password === null) {
            return [
                'is_valid' => false,
                'length'   => false,
                'upper'    => false,
                'lower'    => false,
                'digit'    => false,
                'special'  => false
            ];
        }

        $length = (strlen($password) >= 8 && strlen($password) <= 35);
        $upper   = (bool)preg_match('/[A-Z]/', $password);
        $lower   = (bool)preg_match('/[a-z]/', $password);
        $digit   = (bool)preg_match('/[0-9]/', $password);
        $special = (bool)preg_match('/[^a-zA-Z0-9]/', $password);

        $isValid = ($length && $upper && $lower && $digit && $special);

        return [
            'is_valid' => $isValid,
            'length'   => $length,
            'upper'    => $upper,
            'lower'    => $lower,
            'digit'    => $digit,
            'special'  => $special
        ];
    }
}

// ---------------------------------------------------------------------
// 3. Currency and Formatting
// ---------------------------------------------------------------------

if (!function_exists('generate_order_reference')) {
    /**
     * Generate a unique order reference string.
     * Example: ORD20231123-XYZ123
     */
    function generate_order_reference(): string {
        $date = date('Ymd');
        $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        return "ORD{$date}-{$rand}";
    }
}

if (!function_exists('format_price')) {
    /**
     * Format PKR currency consistently
     */
    function format_price(float|int|string $amount): string {
        $num = (float)$amount;
        return 'Rs. ' . number_format($num, 2);
    }
}

if (!function_exists('format_date')) {
    /**
     * Format timestamp to readable date/time
     */
    function format_date(?string $datetime, string $format = 'M d, Y h:i A'): string {
        if (empty($datetime)) return '—';
        $time = strtotime($datetime);
        return $time ? date($format, $time) : '—';
    }
}

if (!function_exists('product_image_url')) {
    /**
     * Safe product image with fallback placeholder
     */
    function product_image_url(?string $imagePath): string {
        if (!empty($imagePath)) {
            $trimmed = ltrim($imagePath, '/');
            if (file_exists(__DIR__ . '/../' . $trimmed)) {
                return $trimmed;
            }
        }
        return 'assets/images/product-placeholder.svg';
    }
}

// ---------------------------------------------------------------------
// 4. Rate Limiting and Login Lockout
// ---------------------------------------------------------------------

if (!function_exists('check_user_lockout')) {
    /**
     * Check if user account is currently locked out.
     * Returns array ['locked' => bool, 'remaining_minutes' => int]
     */
    function check_user_lockout(mysqli $conn, string $email): array {
        $normalized = normalize_email($email);
        $stmt = $conn->prepare("SELECT failed_login_attempts, locked_until FROM users WHERE email = ? LIMIT 1");
        if (!$stmt) {
            return ['locked' => false, 'remaining_minutes' => 0];
        }

        $stmt->bind_param("s", $normalized);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$user) {
            return ['locked' => false, 'remaining_minutes' => 0];
        }

        if (!empty($user['locked_until'])) {
            $lockTime = strtotime($user['locked_until']);
            if ($lockTime > time()) {
                $remainingMinutes = (int)ceil(($lockTime - time()) / 60);
                return ['locked' => true, 'remaining_minutes' => $remainingMinutes];
            } else {
                // Lock has expired, reset counter
                $resetStmt = $conn->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE email = ?");
                if ($resetStmt) {
                    $resetStmt->bind_param("s", $normalized);
                    $resetStmt->execute();
                    $resetStmt->close();
                }
            }
        }

        return ['locked' => false, 'remaining_minutes' => 0];
    }
}

if (!function_exists('record_failed_login')) {
    /**
     * Record a failed login attempt for an email.
     * Locks account for 15 minutes if attempts reach 5.
     * Returns current failed attempts count.
     */
    function record_failed_login(mysqli $conn, string $email): int {
        $normalized = normalize_email($email);

        // Record in login_attempts audit table
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $auditStmt = $conn->prepare("INSERT INTO login_attempts (identifier, ip_address) VALUES (?, ?)");
        if ($auditStmt) {
            $auditStmt->bind_param("ss", $normalized, $ip);
            $auditStmt->execute();
            $auditStmt->close();
        }

        // Fetch current user status
        $stmt = $conn->prepare("SELECT id, failed_login_attempts FROM users WHERE email = ? LIMIT 1");
        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param("s", $normalized);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$user) {
            return 0;
        }

        $newAttempts = (int)$user['failed_login_attempts'] + 1;

        if ($newAttempts >= 5) {
            // Lock account for 15 minutes (900 seconds)
            $lockedUntil = date('Y-m-d H:i:s', time() + 900);
            $updateStmt = $conn->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?");
            if ($updateStmt) {
                $updateStmt->bind_param("isi", $newAttempts, $lockedUntil, $user['id']);
                $updateStmt->execute();
                $updateStmt->close();
            }
        } else {
            $updateStmt = $conn->prepare("UPDATE users SET failed_login_attempts = ? WHERE id = ?");
            if ($updateStmt) {
                $updateStmt->bind_param("ii", $newAttempts, $user['id']);
                $updateStmt->execute();
                $updateStmt->close();
            }
        }

        return $newAttempts;
    }
}

if (!function_exists('reset_failed_logins')) {
    /**
     * Reset failed login counter and unlock account on successful authentication
     */
    function reset_failed_logins(mysqli $conn, string $email): void {
        $normalized = normalize_email($email);
        $stmt = $conn->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $normalized);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// ---------------------------------------------------------------------
// 5. Flash Messages
// ---------------------------------------------------------------------

if (!function_exists('set_flash')) {
    function set_flash(string $type, string $message): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            needify_start_session();
        }
        $_SESSION['needify_flash'] = [
            'type'    => $type,
            'message' => $message
        ];
    }
}

if (!function_exists('get_flash')) {
    function get_flash(): ?array {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            needify_start_session();
        }
        if (isset($_SESSION['needify_flash'])) {
            $flash = $_SESSION['needify_flash'];
            unset($_SESSION['needify_flash']);
            return $flash;
        }
        return null;
    }
}
