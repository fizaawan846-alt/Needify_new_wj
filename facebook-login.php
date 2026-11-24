
<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/config/facebook.php";

// Security headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");

// Validate Facebook configuration
if (
    !defined("FACEBOOK_APP_ID") ||
    !defined("FACEBOOK_APP_SECRET") ||
    !defined("FACEBOOK_REDIRECT_URI") ||
    !defined("FACEBOOK_GRAPH_VERSION") ||
    trim(FACEBOOK_APP_ID) === "" ||
    trim(FACEBOOK_APP_SECRET) === "" ||
    FACEBOOK_APP_SECRET === "PASTE_YOUR_FACEBOOK_APP_SECRET_HERE"
) {
    $_SESSION["facebook_login_error"] =
        "Facebook login configuration is incomplete.";

    header("Location: login.php");
    exit;
}

// Generate CSRF state
$state = bin2hex(random_bytes(32));

$_SESSION["facebook_oauth_state"] = $state;

// Build Facebook OAuth URL
$facebookLoginUrl = "https://www.facebook.com/"
    . FACEBOOK_GRAPH_VERSION
    . "/dialog/oauth?"
    . http_build_query([
        "client_id" => FACEBOOK_APP_ID,
        "redirect_uri" => FACEBOOK_REDIRECT_URI,
        "state" => $state,
        "scope" => "email,public_profile",
        "response_type" => "code"
    ]);

header("Location: " . $facebookLoginUrl);
exit;
