<?php
/**
 * NEEDIFY - CSRF PROTECTION MODULE
 * Cryptographically secure CSRF token generation, form injection, and verification.
 */

require_once __DIR__ . '/../config/session.php';

if (!function_exists('csrf_token')) {
    /**
     * Get or generate the current session CSRF token
     */
    function csrf_token(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            needify_start_session();
        }

        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Render hidden HTML input field with CSRF token
     */
    function csrf_field(): string {
        $token = csrf_token();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrf_meta')) {
    /**
     * Render HTML meta tag for JavaScript fetch / AJAX headers
     */
    function csrf_meta(): string {
        $token = csrf_token();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('verify_csrf_token')) {
    /**
     * Verify submitted CSRF token from POST body or HTTP header
     */
    function verify_csrf_token(?string $token = null): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            needify_start_session();
        }

        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (empty($sessionToken) || !is_string($sessionToken)) {
            return false;
        }

        if ($token === null) {
            // Check POST parameter first
            if (isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])) {
                $token = $_POST['csrf_token'];
            }
            // Fallback to custom HTTP header (X-CSRF-Token)
            elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN']) && is_string($_SERVER['HTTP_X_CSRF_TOKEN'])) {
                $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
            }
            // Check JSON payload if applicable
            elseif (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json')) {
                $raw = file_get_contents('php://input');
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['csrf_token']) && is_string($decoded['csrf_token'])) {
                    $token = $decoded['csrf_token'];
                }
            }
        }

        if (empty($token) || !is_string($token)) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }
}

if (!function_exists('require_csrf')) {
    /**
     * Enforce CSRF token verification; sends 403 Forbidden on failure
     */
    function require_csrf(?string $token = null): void {
        if (!verify_csrf_token($token)) {
            http_response_code(403);
            if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'success' => false,
                    'error'   => 'Security verification failed (Invalid CSRF token). Please refresh and try again.'
                ]);
            } else {
                die('Security verification failed. Invalid or expired security token. Please go back, refresh the page, and try again.');
            }
            exit();
        }
    }
}
