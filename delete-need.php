
<?php
session_start();
require_once "config/database.php";

// Prevent cached page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// User login check
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// 100 seconds inactivity timeout
$timeout_duration = 100;

if (
    !isset($_SESSION["user_last_activity"]) ||
    (time() - $_SESSION["user_last_activity"]) > $timeout_duration
) {
    unset(
        $_SESSION["user_id"],
        $_SESSION["username"],
        $_SESSION["user_last_activity"]
    );

    session_regenerate_id(true);

    header("Location: login.php?timeout=1");
    exit();
}

$_SESSION["user_last_activity"] = time();

// Only POST requests are allowed
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: my-needs.php");
    exit();
}

// Validate need ID
if (!isset($_POST["id"]) || !ctype_digit((string) $_POST["id"])) {
    header("Location: my-needs.php");
    exit();
}

// Verify CSRF token
if (
    !isset($_POST["csrf_token"], $_SESSION["delete_need_csrf"]) ||
    !hash_equals($_SESSION["delete_need_csrf"], $_POST["csrf_token"])
) {
    header("Location: my-needs.php?error=security");
    exit();
}

$need_id = (int) $_POST["id"];
$user_id = (int) $_SESSION["user_id"];

// Delete only the logged-in user's own need
$stmt = $conn->prepare(
    "DELETE FROM needs WHERE id = ? AND user_id = ?"
);

if (!$stmt) {
    header("Location: my-needs.php?error=delete");
    exit();
}

$stmt->bind_param("ii", $need_id, $user_id);

if ($stmt->execute()) {
    unset($_SESSION["delete_need_csrf"]);
    $stmt->close();

    header("Location: my-needs.php?deleted=1");
    exit();
}

$stmt->close();

header("Location: my-needs.php?error=delete");
exit();
?>
