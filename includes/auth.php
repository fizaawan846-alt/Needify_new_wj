<?php
/**
 * NEEDIFY - AUTHENTICATION GUARDS AND HELPERS
 * Session-based authentication status, role verification, and login/logout handlers.
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/functions.php';

if (!function_exists('is_logged_in')) {
    /**
     * Check if a regular user is authenticated
     */
    function is_logged_in(): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            needify_start_session();
        }
        return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
    }
}

if (!function_exists('is_admin_logged_in')) {
    /**
     * Check if an admin is authenticated
     */
    function is_admin_logged_in(): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            needify_start_session();
        }
        return isset($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] > 0;
    }
}

if (!function_exists('current_user_id')) {
    /**
     * Get logged-in user ID or null
     */
    function current_user_id(): ?int {
        return is_logged_in() ? (int)$_SESSION['user_id'] : null;
    }
}

if (!function_exists('current_user')) {
    /**
     * Get array of logged-in user session data
     */
    function current_user(): ?array {
        if (!is_logged_in()) {
            return null;
        }
        return [
            'id'        => (int)$_SESSION['user_id'],
            'full_name' => $_SESSION['full_name'] ?? '',
            'username'  => $_SESSION['username'] ?? '',
            'email'     => $_SESSION['email'] ?? '',
        ];
    }
}

if (!function_exists('require_user_login')) {
    /**
     * Protect page for authenticated users only
     */
    function require_user_login(string $redirectUrl = 'login.php'): void {
        if (!is_logged_in()) {
            header("Location: " . $redirectUrl);
            exit();
        }
        // Inactivity check (15 minutes)
        needify_check_inactivity(900, $redirectUrl . '?timeout=1');
    }
}

if (!function_exists('require_admin_login')) {
    /**
     * Protect page for authenticated admins only
     */
    function require_admin_login(string $redirectUrl = 'admin-login.php'): void {
        if (!is_admin_logged_in()) {
            header("Location: " . $redirectUrl);
            exit();
        }
        // Admin inactivity check (15 minutes)
        if (isset($_SESSION['admin_last_activity']) && (time() - (int)$_SESSION['admin_last_activity']) > 900) {
            unset(
                $_SESSION['admin_id'],
                $_SESSION['admin_username'],
                $_SESSION['admin_last_activity']
            );
            needify_regenerate_session();
            header("Location: " . $redirectUrl . "?timeout=1");
            exit();
        }
        $_SESSION['admin_last_activity'] = time();
    }
}

if (!function_exists('login_user_session')) {
    /**
     * Establish user login session with session ID regeneration
     */
    function login_user_session(array $user): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            needify_start_session();
        }

        needify_regenerate_session();

        $_SESSION['user_id']            = (int)$user['id'];
        $_SESSION['full_name']          = $user['full_name'];
        $_SESSION['username']           = $user['username'] ?? '';
        $_SESSION['email']              = $user['email'];
        $_SESSION['user_last_activity'] = time();
    }
}

if (!function_exists('logout_user_session')) {
    /**
     * Terminate user session securely
     */
    function logout_user_session(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            needify_start_session();
        }

        unset(
            $_SESSION['user_id'],
            $_SESSION['full_name'],
            $_SESSION['username'],
            $_SESSION['email'],
            $_SESSION['user_last_activity'],
            $_SESSION['cart']
        );

        needify_regenerate_session();
    }
}

if (!function_exists('logout_admin_session')) {
    /**
     * Terminate admin session securely
     */
    function logout_admin_session(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            needify_start_session();
        }

        unset(
            $_SESSION['admin_id'],
            $_SESSION['admin_username'],
            $_SESSION['admin_last_activity']
        );

        needify_regenerate_session();
    }
}
