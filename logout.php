<?php
/**
 * NEEDIFY - USER LOGOUT
 * Securely terminates user session, unsets cookies, and redirects to login.
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/auth.php';

logout_user_session();

header("Location: login.php?logged_out=1");
exit();
