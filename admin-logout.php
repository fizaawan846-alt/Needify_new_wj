Bilkul janu ❤️ Ye `admin-logout.php` ki **complete ready-to-paste replacement file** hai. Purana code completely remove karke ye paste kar do.

```

<?php
/*
|--------------------------------------------------------------------------
| NEEDIFY - SECURE ADMIN LOGOUT
|--------------------------------------------------------------------------
*/

// CENTRAL SESSION CONFIGURATION
require_once __DIR__ . "/config/session.php";

// PREVENT CACHED ADMIN PAGES AFTER LOGOUT
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// CLEAR ADMIN SESSION DATA
unset(
    $_SESSION["admin_id"],
    $_SESSION["admin_username"],
    $_SESSION["admin_last_activity"],
    $_SESSION["admin_register_csrf"],
    $_SESSION["admin_orders_csrf"]
);

// DESTROY SESSION AND DELETE SESSION COOKIE
needify_destroy_session();

// REDIRECT TO ADMIN LOGIN
header("Location: admin-login.php");
exit;

?>
