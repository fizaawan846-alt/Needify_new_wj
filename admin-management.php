
<?php
/*
|--------------------------------------------------------------------------
| NEEDIFY - ADMIN MANAGEMENT
|--------------------------------------------------------------------------
*/

ini_set("log_errors", "1");
error_reporting(E_ALL);

// CENTRAL SESSION + DATABASE
require_once __DIR__ . "/config/session.php";
require_once __DIR__ . "/config/database.php";

// NO CACHE
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// ADMIN LOGIN CHECK
if (!isset($_SESSION["admin_id"])) {
    header("Location: admin-login.php");
    exit;
}

// 100 SECONDS INACTIVITY TIMEOUT
$inactive_limit = 100;

if (
    !isset($_SESSION["admin_last_activity"]) ||
    (time() - (int) $_SESSION["admin_last_activity"]) >= $inactive_limit
) {
    unset(
        $_SESSION["admin_id"],
        $_SESSION["admin_username"],
        $_SESSION["admin_last_activity"]
    );

    needify_regenerate_session();

    header("Location: admin-login.php?timeout=1");
    exit;
}

$_SESSION["admin_last_activity"] = time();

// CURRENT ADMIN
$current_admin_id = (int) $_SESSION["admin_id"];
$admin_username = $_SESSION["admin_username"] ?? "Admin";

// CSRF TOKEN
if (
    !isset($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"])
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// FLASH MESSAGE
$message = $_SESSION["admin_management_message"] ?? "";
unset($_SESSION["admin_management_message"]);

// DELETE ADMIN
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)
    ) {
        http_response_code(403);
        exit("Invalid request. Please refresh the page.");
    }

    $delete_id = filter_input(
        INPUT_POST,
        "delete_id",
        FILTER_VALIDATE_INT
    );

    if (!$delete_id || $delete_id < 1) {
        $_SESSION["admin_management_message"] = "Invalid admin ID.";
        header("Location: admin-management.php");
        exit;
    }

    if ($delete_id === $current_admin_id) {
        $_SESSION["admin_management_message"] =
            "You cannot delete your own admin account.";

        header("Location: admin-management.php");
        exit;
    }

    try {

        $conn->begin_transaction();

        // LOCK ADMIN RECORDS
        $lock_stmt = $conn->prepare(
            "SELECT id FROM admins FOR UPDATE"
        );

        if (!$lock_stmt) {
            throw new Exception("Unable to lock admin records.");
        }

        $lock_stmt->execute();

        $lock_result = $lock_stmt->get_result();
        $admin_records = $lock_result->fetch_all(MYSQLI_ASSOC);

        $lock_stmt->close();

        $total_admins = count($admin_records);

        // PREVENT DELETING LAST ADMIN
        if ($total_admins <= 1) {
            throw new RuntimeException(
                "The last admin account cannot be deleted."
            );
        }

        // CHECK TARGET ADMIN
        $target_exists = false;

        foreach ($admin_records as $record) {
            if ((int) $record["id"] === $delete_id) {
                $target_exists = true;
                break;
            }
        }

        if (!$target_exists) {
            throw new RuntimeException(
                "Admin account was not found."
            );
        }

        // DELETE ADMIN
        $delete_stmt = $conn->prepare(
            "DELETE FROM admins WHERE id = ?"
        );

        if (!$delete_stmt) {
            throw new Exception("Delete query could not be prepared.");
        }

        $delete_stmt->bind_param("i", $delete_id);
        $delete_stmt->execute();

        if ($delete_stmt->affected_rows !== 1) {
            $delete_stmt->close();

            throw new RuntimeException(
                "Admin account could not be deleted."
            );
        }

        $delete_stmt->close();

        $conn->commit();

        $_SESSION["admin_management_message"] =
            "Admin account deleted successfully.";

    } catch (Throwable $e) {

        try {
            $conn->rollback();
        } catch (Throwable $rollback_error) {
            error_log(
                "Admin management rollback error: " .
                $rollback_error->getMessage()
            );
        }

        error_log(
            "Admin management error: " . $e->getMessage()
        );

        $_SESSION["admin_management_message"] =
            $e instanceof RuntimeException
                ? $e->getMessage()
                : "Unable to delete admin. Please try again.";
    }

    header("Location: admin-management.php");
    exit;
}

// SEARCH ADMINS
$search = $_GET["search"] ?? "";

if (!is_string($search)) {
    $search = "";
}

$search = trim(substr($search, 0, 100));

if ($search !== "") {

    $search_value = "%" . $search . "%";

    $stmt = $conn->prepare(
        "SELECT id, username, created_at
         FROM admins
         WHERE username LIKE ?
         ORDER BY id DESC"
    );

    if (!$stmt) {
        die("Unable to prepare admin search.");
    }

    $stmt->bind_param("s", $search_value);

} else {

    $stmt = $conn->prepare(
        "SELECT id, username, created_at
         FROM admins
         ORDER BY id DESC"
    );

    if (!$stmt) {
        die("Unable to load admin records.");
    }
}

$stmt->execute();

$result = $stmt->get_result();
$admins = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();

// TOTAL ADMIN COUNT
$count_result = $conn->query(
    "SELECT COUNT(*) AS total_admins FROM admins"
);

if (!$count_result) {
    die("Unable to load admin count.");
}

$count_data = $count_result->fetch_assoc();

$total_admins = (int) $count_data["total_admins"];
$remaining_slots = max(0, 5 - $total_admins);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Needify | Admin Management</title>

<link rel="icon" type="image/png" href="favicon.png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
rel="stylesheet">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {
    --primary: #7956C8;
    --primary-dark: #6240AD;
    --pink: #FFE5EF;
    --cream: #FFFAF5;
    --white: #FFFFFF;
    --text: #302747;
    --muted: #81798F;
    --border: #E9E0F0;
    --card: #FFFFFF;
    --input: #FFFCFF;
    --shadow: 0 8px 28px rgba(48,39,71,0.08);
}

body.dark-mode {
    --primary: #A98BEA;
    --primary-dark: #8C6CD0;
    --pink: #49354C;
    --cream: #211C2B;
    --white: #302A3C;
    --text: #F8F2FF;
    --muted: #C1B6D0;
    --border: #4B405B;
    --card: #302A3C;
    --input: #393146;
    --shadow: 0 8px 28px rgba(0,0,0,0.22);
}

body {
    font-family: 'Poppins', sans-serif;
    background: var(--cream);
    color: var(--text);
    min-height: 100vh;
    transition: background 0.3s, color 0.3s;
}

/* NAVBAR */

nav {
    background: var(--card);
    padding: 17px 4%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    box-shadow: var(--shadow);
    border-bottom: 1px solid var(--border);
}

.logo {
    color: var(--primary);
    font-size: 28px;
    font-weight: 800;
    text-decoration: none;
    letter-spacing: 0.5px;
}

.nav-links {
    display: flex;
    gap: 17px;
    flex-wrap: wrap;
    align-items: center;
}

.nav-links a {
    color: var(--text);
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: 0.2s;
}

.nav-links a:hover {
    color: var(--primary);
}

/* DISPLAY CONTROLS */

.site-tools {
    display: flex;
    gap: 7px;
    align-items: center;
}

.site-tools button {
    width: 36px;
    height: 36px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--pink);
    color: var(--primary);
    cursor: pointer;
    transition: 0.2s;
}

.site-tools button:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
}

/* MAIN */

.container {
    width: 92%;
    max-width: 1200px;
    margin: 40px auto;
}

.page-heading {
    margin-bottom: 28px;
}

.page-heading h1 {
    font-size: 29px;
    margin-bottom: 9px;
    color: var(--text);
}

.page-heading h1 i {
    color: var(--primary);
    margin-right: 8px;
}

.page-heading p {
    color: var(--muted);
    line-height: 1.7;
    font-size: 13px;
}

/* STATS */

.stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    margin-bottom: 27px;
}

.stat-card {
    background: var(--card);
    padding: 25px;
    border-radius: 17px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    position: relative;
    overflow: hidden;
    transition: 0.25s;
}

.stat-card::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    width: 5px;
    height: 100%;
    background: linear-gradient(180deg, #7956C8, #FFE5EF);
}

.stat-card:hover {
    transform: translateY(-3px);
}

.stat-card h3 {
    font-size: 13px;
    color: var(--muted);
    margin-bottom: 13px;
    font-weight: 600;
}

.stat-card strong {
    font-size: 31px;
    color: var(--primary);
    font-weight: 800;
}

/* CONTENT CARD */

.card {
    background: var(--card);
    padding: 27px;
    border-radius: 18px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    margin-bottom: 25px;
}

.card h2 {
    margin-bottom: 22px;
    font-size: 19px;
    color: var(--text);
}

.card h2 i {
    color: var(--primary);
    margin-right: 7px;
}

/* MESSAGE */

.message {
    padding: 14px 17px;
    background: #E9F9EF;
    color: #18723A;
    border: 1px solid #B8E8C7;
    border-radius: 11px;
    margin-bottom: 22px;
    line-height: 1.6;
    font-size: 13px;
}

body.dark-mode .message {
    background: #263F32;
    border-color: #38664B;
    color: #B9F3CC;
}

/* SEARCH */

.search-form {
    display: flex;
    gap: 10px;
    margin: 20px 0 23px;
    flex-wrap: wrap;
}

.search-form input {
    flex: 1;
    min-width: 200px;
    padding: 13px 15px;
    border: 1px solid var(--border);
    border-radius: 11px;
    font-family: inherit;
    font-size: 13px;
    color: var(--text);
    background: var(--input);
    outline: none;
    transition: 0.2s;
}

.search-form input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(121,86,200,0.12);
}

.search-form input::placeholder {
    color: var(--muted);
}

.search-form button {
    padding: 12px 20px;
    border: none;
    border-radius: 10px;
    background: linear-gradient(135deg, #7956C8, #9474D7);
    color: white;
    cursor: pointer;
    font-family: inherit;
    font-weight: 600;
    transition: 0.2s;
}

.search-form button:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

.clear-btn {
    display: inline-flex;
    align-items: center;
    padding: 12px 17px;
    border-radius: 10px;
    background: var(--pink);
    color: var(--primary);
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

/* ADD ADMIN BUTTON */

.add-admin {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 18px;
    background: linear-gradient(135deg, #7956C8, #9474D7);
    color: white;
    text-decoration: none;
    border-radius: 11px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 5px;
    box-shadow: 0 5px 15px rgba(121,86,200,0.2);
    transition: 0.2s;
}

.add-admin:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

.add-admin.disabled {
    background: #999;
    pointer-events: none;
    box-shadow: none;
}

/* TABLE */

.table-wrapper {
    width: 100%;
    overflow-x: auto;
    border: 1px solid var(--border);
    border-radius: 12px;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

th,
td {
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid var(--border);
}

th {
    background: var(--pink);
    color: var(--primary-dark);
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

td {
    font-size: 13px;
    color: var(--text);
}

tbody tr {
    transition: background 0.2s;
}

tbody tr:hover {
    background: rgba(255,229,239,0.25);
}

tbody tr:last-child td {
    border-bottom: none;
}

body.dark-mode th {
    background: #49354C;
    color: #E9D8FF;
}

body.dark-mode tbody tr:hover {
    background: rgba(169,139,234,0.08);
}

/* BADGES */

.admin-badge {
    display: inline-block;
    padding: 7px 11px;
    background: var(--pink);
    color: var(--primary-dark);
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.self-label {
    color: #218653;
    font-weight: 700;
    font-size: 12px;
}

body.dark-mode .self-label {
    color: #8DE0B0;
}

/* DELETE BUTTON */

.delete-btn {
    padding: 9px 13px;
    border: 1px solid #FFD0D0;
    border-radius: 9px;
    background: #FFF0F0;
    color: #B42335;
    cursor: pointer;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    transition: 0.2s;
}

.delete-btn:hover {
    background: #FBDADA;
    transform: translateY(-2px);
}

body.dark-mode .delete-btn {
    background: #492B36;
    border-color: #70404E;
    color: #FFB7C4;
}

/* EMPTY */

.empty {
    text-align: center;
    padding: 35px;
    color: var(--muted);
}

.empty i {
    font-size: 30px;
    color: var(--primary);
    margin-bottom: 12px;
}

/* FOOTER */

footer {
    background: #302747;
    color: #FFFFFF;
    padding: 23px;
    text-align: center;
    margin-top: 60px;
    font-size: 12px;
}

footer strong {
    color: #FFE5EF;
}

/* PAGE CONTROLS */

.page-controls {
    position: fixed;
    right: 20px;
    bottom: 22px;
    z-index: 999;
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.page-controls button {
    width: 43px;
    height: 43px;
    border: none;
    border-radius: 13px;
    background: linear-gradient(135deg, #7956C8, #9474D7);
    color: white;
    cursor: pointer;
    font-size: 15px;
    box-shadow: 0 5px 15px rgba(121,86,200,0.25);
    transition: 0.2s;
}

.page-controls button:hover {
    transform: translateY(-3px);
    background: var(--primary-dark);
}

/* DARK MODE */

body.dark-mode {
    background: var(--cream);
    color: var(--text);
}

body.dark-mode nav,
body.dark-mode .card,
body.dark-mode .stat-card {
    background: var(--card);
    color: var(--text);
    border-color: var(--border);
}

body.dark-mode .logo {
    color: var(--primary);
}

body.dark-mode .nav-links a {
    color: var(--text);
}

body.dark-mode .nav-links a:hover {
    color: var(--primary);
}

body.dark-mode .page-heading h1,
body.dark-mode .card h2,
body.dark-mode td {
    color: var(--text);
}

body.dark-mode .stat-card h3,
body.dark-mode .page-heading p {
    color: var(--muted);
}

body.dark-mode .site-tools button {
    background: #49354C;
    border-color: var(--border);
    color: #E9D8FF;
}

body.dark-mode .admin-badge {
    background: #49354C;
    color: #E9D8FF;
}

body.dark-mode .clear-btn {
    background: #49354C;
    color: #E9D8FF;
}

body.dark-mode .table-wrapper {
    border-color: var(--border);
}

body.dark-mode td,
body.dark-mode th {
    border-color: var(--border);
}

body.dark-mode .search-form input {
    background: var(--input);
    color: var(--text);
    border-color: var(--border);
}

/* RESPONSIVE */

@media(max-width: 900px) {

    nav {
        justify-content: center;
    }

    .nav-links {
        justify-content: center;
    }

}

@media(max-width: 750px) {

    nav {
        flex-direction: column;
        padding: 18px;
    }

    .nav-links {
        gap: 13px;
    }

    .container {
        width: 94%;
        margin: 28px auto;
    }

    .card {
        padding: 20px;
    }

    .page-heading h1 {
        font-size: 24px;
    }

}

@media(max-width: 520px) {

    .stats {
        grid-template-columns: 1fr;
    }

    .nav-links {
        gap: 11px;
    }

    .nav-links a {
        font-size: 12px;
    }

    .search-form {
        flex-direction: column;
    }

    .search-form input,
    .search-form button,
    .clear-btn {
        width: 100%;
        justify-content: center;
    }

    .page-controls {
        right: 12px;
        bottom: 14px;
    }

    .page-controls button {
        width: 39px;
        height: 39px;
    }

}

</style>

</head>

<body>

<nav>

    <a href="admin-dashboard.php" class="logo">
        Needify.
    </a>

    <div class="nav-links">
        <a href="admin-dashboard.php">Dashboard</a>
        <a href="admin-user.php">Users</a>
        <a href="admin-needs.php">Needs</a>
        <a href="admin-messages.php">Messages</a>
        <a href="admin-category.php">Categories</a>
        <a href="admin-products.php">Products</a>
        <a href="admin-management.php">Admins</a>
        <a href="admin-logout.php">Logout</a>
    </div>

    <div class="site-tools">

        <button type="button" id="fontDecrease" title="Decrease font size">
            <i class="fa-solid fa-minus"></i>
        </button>

        <button type="button" id="fontReset" title="Reset font size">
            <i class="fa-solid fa-rotate-left"></i>
        </button>

        <button type="button" id="fontIncrease" title="Increase font size">
            <i class="fa-solid fa-plus"></i>
        </button>

        <button type="button" id="themeToggle" title="Toggle theme">
            <i class="fa-solid fa-moon"></i>
        </button>

    </div>

</nav>

<main class="container">

    <div class="page-heading">

        <h1>
            <i class="fa-solid fa-user-shield"></i>
            Admin Management
        </h1>

        <p>
            Manage Needify administrator accounts and control admin access.
        </p>

    </div>

    <?php if ($message !== ""): ?>

        <div class="message">
            <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
        </div>

    <?php endif; ?>

    <div class="stats">

        <div class="stat-card">
            <h3>
                <i class="fa-solid fa-users-gear"></i>
                Total Admins
            </h3>

            <strong>
                <?php echo $total_admins; ?> / 5
            </strong>
        </div>

        <div class="stat-card">
            <h3>
                <i class="fa-solid fa-user-plus"></i>
                Available Admin Slots
            </h3>

            <strong>
                <?php echo $remaining_slots; ?>
            </strong>
        </div>

    </div>

    <div class="card">

        <h2>
            <i class="fa-solid fa-users-gear"></i>
            Registered Administrators
        </h2>

        <?php if ($remaining_slots > 0): ?>

            <a href="admin-register.php" class="add-admin">
                <i class="fa-solid fa-user-plus"></i>
                Add New Admin
            </a>

        <?php else: ?>

            <span class="add-admin disabled">
                <i class="fa-solid fa-users"></i>
                Admin Limit Reached
            </span>

        <?php endif; ?>

        <form method="GET" action="admin-management.php" class="search-form">

            <input
                type="text"
                name="search"
                placeholder="Search admin username..."
                value="<?php echo htmlspecialchars($search, ENT_QUOTES, "UTF-8"); ?>"
            >

            <button type="submit">
                <i class="fa-solid fa-search"></i>
                Search
            </button>

            <?php if ($search !== ""): ?>

                <a href="admin-management.php" class="clear-btn">
                    <i class="fa-solid fa-xmark"></i>
                    Clear
                </a>

            <?php endif; ?>

        </form>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Admin ID</th>
                        <th>Username</th>
                        <th>Created At</th>
                        <th>Account</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (count($admins) > 0): ?>

                        <?php foreach ($admins as $admin): ?>

                            <tr>

                                <td>
                                    #<?php echo (int) $admin["id"]; ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $admin["username"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $admin["created_at"] ?? "",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>
                                </td>

                                <td>

                                    <?php if (
                                        (int) $admin["id"] === $current_admin_id
                                    ): ?>

                                        <span class="self-label">
                                            <i class="fa-solid fa-circle-check"></i>
                                            Current Admin
                                        </span>

                                    <?php else: ?>

                                        <span class="admin-badge">
                                            <i class="fa-solid fa-shield-halved"></i>
                                            Administrator
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php if (
                                        (int) $admin["id"] === $current_admin_id
                                    ): ?>

                                        <span class="self-label">
                                            Cannot Delete
                                        </span>

                                    <?php else: ?>

                                        <form
                                            method="POST"
                                            action="admin-management.php"
                                            onsubmit="return confirm('Are you sure you want to delete this admin account?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php
                                                echo htmlspecialchars(
                                                    $_SESSION["csrf_token"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                );
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="delete_id"
                                                value="<?php echo (int) $admin["id"]; ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="delete-btn"
                                            >
                                                <i class="fa-solid fa-trash"></i>
                                                Delete
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="5" class="empty">
                                <i class="fa-solid fa-user-slash"></i>
                                <p>No admin accounts found.</p>
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</main>

<footer>
    <p>
        &copy; <?php echo date("Y"); ?>
        <strong>Needify.</strong> All rights reserved.
    </p>
</footer>

<div class="page-controls">

    <button type="button" id="pageUp" title="Page Up">
        <i class="fa-solid fa-arrow-up"></i>
    </button>

    <button type="button" id="pageDown" title="Page Down">
        <i class="fa-solid fa-arrow-down"></i>
    </button>

</div>

<script>

(function () {

    const body = document.body;

    const themeToggle = document.getElementById("themeToggle");

    const fontDecrease = document.getElementById("fontDecrease");
    const fontReset = document.getElementById("fontReset");
    const fontIncrease = document.getElementById("fontIncrease");

    const pageUp = document.getElementById("pageUp");
    const pageDown = document.getElementById("pageDown");

    // DARK / LIGHT MODE

    function applyTheme(theme) {

        body.classList.toggle(
            "dark-mode",
            theme === "dark"
        );

        themeToggle.innerHTML = theme === "dark"
            ? '<i class="fa-solid fa-sun"></i>'
            : '<i class="fa-solid fa-moon"></i>';
    }

    applyTheme(
        localStorage.getItem("needifyTheme") || "light"
    );

    themeToggle.addEventListener("click", function () {

        const nextTheme = body.classList.contains("dark-mode")
            ? "light"
            : "dark";

        localStorage.setItem("needifyTheme", nextTheme);

        applyTheme(nextTheme);
    });

    // FONT SIZE CONTROLS

    let fontLevel = Number(
        localStorage.getItem("needifyFontLevel") || 0
    );

    if (!Number.isFinite(fontLevel)) {
        fontLevel = 0;
    }

    fontLevel = Math.max(-2, Math.min(3, fontLevel));

    function applyFontLevel() {

        body.style.zoom = String(
            1 + (fontLevel * 0.05)
        );

        localStorage.setItem(
            "needifyFontLevel",
            String(fontLevel)
        );
    }

    fontDecrease.addEventListener("click", function () {
        fontLevel = Math.max(-2, fontLevel - 1);
        applyFontLevel();
    });

    fontIncrease.addEventListener("click", function () {
        fontLevel = Math.min(3, fontLevel + 1);
        applyFontLevel();
    });

    fontReset.addEventListener("click", function () {
        fontLevel = 0;
        applyFontLevel();
    });

    applyFontLevel();

    // PAGE UP

    pageUp.addEventListener("click", function () {

        window.scrollTo({
            top: 0,
            behavior: "smooth"
        });
    });

    // PAGE DOWN

    pageDown.addEventListener("click", function () {

        window.scrollBy({
            top: window.innerHeight * 0.8,
            behavior: "smooth"
        });
    });

    // KEYBOARD PAGE CONTROLS

    document.addEventListener("keydown", function (event) {

        const tag = event.target.tagName;

        if (
            tag === "INPUT" ||
            tag === "TEXTAREA" ||
            tag === "SELECT" ||
            event.target.isContentEditable
        ) {
            return;
        }

        if (event.key === "PageUp") {
            event.preventDefault();

            window.scrollBy({
                top: -window.innerHeight,
                behavior: "smooth"
            });
        }

        if (event.key === "PageDown") {
            event.preventDefault();

            window.scrollBy({
                top: window.innerHeight,
                behavior: "smooth"
            });
        }

    });

})();

</script>

</body>
</html>
