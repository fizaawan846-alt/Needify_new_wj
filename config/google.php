<?php
/**
 * NEEDIFY - GOOGLE AUTHENTICATION CONFIGURATION
 * Google Identity Services & OAuth 2.0
 */

if (!defined('GOOGLE_CLIENT_ID')) {
    define(
        'GOOGLE_CLIENT_ID',
        getenv('GOOGLE_CLIENT_ID') ?: '24070372064-8nt1n1rnna69gbq1qrvc9klvmh22nll9.apps.googleusercontent.com'
    );
}

if (!defined('GOOGLE_CLIENT_SECRET')) {
    define(
        'GOOGLE_CLIENT_SECRET',
        getenv('GOOGLE_CLIENT_SECRET') ?: ''
    );
}

if (!defined('GOOGLE_REDIRECT_URI')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('GOOGLE_REDIRECT_URI', getenv('GOOGLE_REDIRECT_URI') ?: ($protocol . $host . '/Needify/google-callback.php'));
}

/**
 * Verify Google ID Token securely using Google tokeninfo endpoint.
 * Validates audience, issuer, expiration, and email_verified claim.
 *
 * @param string $idToken
 * @return array|false Returns token payload array on success, false on failure.
 */
function needify_verify_google_id_token(string $idToken): array|false {
    if (empty($idToken)) {
        return false;
    }

    $verifyUrl = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($idToken);

    $ch = curl_init($verifyUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Needify-Auth/1.0',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        return false;
    }

    $payload = json_decode($response, true);
    if (!is_array($payload)) {
        return false;
    }

    // 1. Validate audience (aud) matches our Client ID
    if (!isset($payload['aud']) || $payload['aud'] !== GOOGLE_CLIENT_ID) {
        return false;
    }

    // 2. Validate issuer (iss)
    $validIssuers = ['accounts.google.com', 'https://accounts.google.com'];
    if (!isset($payload['iss']) || !in_array($payload['iss'], $validIssuers, true)) {
        return false;
    }

    // 3. Validate expiration (exp)
    if (!isset($payload['exp']) || (int)$payload['exp'] < time()) {
        return false;
    }

    // 4. Validate email and email_verified
    if (empty($payload['email'])) {
        return false;
    }
    // Google returns email_verified as string "true" or boolean true
    $emailVerified = $payload['email_verified'] ?? false;
    if ($emailVerified !== true && $emailVerified !== 'true' && $emailVerified !== 1 && $emailVerified !== '1') {
        return false;
    }

    return $payload;
}
