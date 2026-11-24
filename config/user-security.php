<?php
/**
 * NEEDIFY - USER SESSION SECURITY GUARD
 * Protects authenticated user pages from unauthorized access and session inactivity.
 */

require_once __DIR__ . '/session.php';

// Inactivity timeout in seconds (Default: 900s = 15 minutes)
$timeout_duration = defined('USER_TIMEOUT_DURATION') ? USER_TIMEOUT_DURATION : 900;

if (!isset($_SESSION["user_id"]) || (int)$_SESSION["user_id"] <= 0) {
    header("Location: login.php");
    exit();
}

if (
    !isset($_SESSION["user_last_activity"]) ||
    (time() - (int)$_SESSION["user_last_activity"]) > $timeout_duration
) {
    unset(
        $_SESSION["user_id"],
        $_SESSION["full_name"],
        $_SESSION["username"],
        $_SESSION["email"],
        $_SESSION["user_last_activity"]
    );

    needify_regenerate_session();

    header("Location: login.php?timeout=1");
    exit();
}

// Update last activity timestamp
$_SESSION["user_last_activity"] = time();
