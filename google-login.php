<?php
/**
 * NEEDIFY - GOOGLE OAUTH INITIATOR
 * Directs user to Google OAuth 2.0 authorization screen with state protection.
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/google.php';

// Generate OAuth CSRF state
$state = bin2hex(random_bytes(32));
$_SESSION['google_oauth_state'] = $state;

$googleAuthUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
    'client_id'             => GOOGLE_CLIENT_ID,
    'redirect_uri'          => GOOGLE_REDIRECT_URI,
    'response_type'         => 'code',
    'scope'                 => 'openid email profile',
    'state'                 => $state,
    'access_type'           => 'online',
    'include_granted_scopes'=> 'true'
]);

header("Location: " . $googleAuthUrl);
exit();
