
<?php
/**
 * NEEDIFY - CENTRAL SESSION SECURITY CONFIGURATION
 * Secure session cookies, session lifecycle and inactivity handling.
 */

if (!function_exists('needify_is_https')) {
    function needify_is_https(): bool {
        if (
            !empty($_SERVER['HTTPS']) &&
            strtolower((string) $_SERVER['HTTPS']) !== 'off'
        ) {
            return true;
        }

        if (
            isset($_SERVER['SERVER_PORT']) &&
            (int) $_SERVER['SERVER_PORT'] === 443
        ) {
            return true;
        }

        if (
            !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
            strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https'
        ) {
            return true;
        }

        return false;
    }
}

if (!function_exists('needify_start_session')) {
    function needify_start_session(): void {

        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Session must be configured before headers are sent.
        if (headers_sent($file, $line)) {
            error_log(
                "Needify session error: Headers already sent in {$file} on line {$line}."
            );
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        $isHttps = needify_is_https();

        session_name('NEEDIFY_SESSID');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        if (!session_start()) {
            error_log('Needify session error: Unable to start session.');
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}

// Automatically start the central Needify session.
needify_start_session();

/**
 * Regenerate session ID safely.
 */
if (!function_exists('needify_regenerate_session')) {
    function needify_regenerate_session(): void {

        if (session_status() !== PHP_SESSION_ACTIVE) {
            needify_start_session();
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}

/**
 * Destroy session completely.
 */
if (!function_exists('needify_destroy_session')) {
    function needify_destroy_session(): void {

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax'
            ]);
        }

        session_destroy();
    }
}

/**
 * Check regular user inactivity.
 * Default timeout: 30 minutes.
 */
if (!function_exists('needify_check_inactivity')) {
    function needify_check_inactivity(
        int $maxIdleSeconds = 1800,
        string $redirectUrl = 'login.php?timeout=1'
    ): bool {

        if (session_status() !== PHP_SESSION_ACTIVE) {
            needify_start_session();
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        if (isset($_SESSION['user_id'])) {

            $lastActivity = (int) (
                $_SESSION['user_last_activity'] ?? time()
            );

            if ((time() - $lastActivity) > $maxIdleSeconds) {

                needify_destroy_session();

                header('Location: ' . $redirectUrl);
                exit();
            }

            $_SESSION['user_last_activity'] = time();
        }

        return false;
    }
}
