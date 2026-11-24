
<?php
/*
|--------------------------------------------------------------------------
| NEEDIFY CENTRAL SESSION + DATABASE
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/config/session.php";
require_once __DIR__ . "/config/database.php";

/*
|--------------------------------------------------------------------------
| ADMIN ACCESS CHECK + 100 SECONDS INACTIVITY TIMEOUT
|--------------------------------------------------------------------------
*/

if (
    session_status() !== PHP_SESSION_ACTIVE ||
    !isset($_SESSION["admin_id"])
) {
    header("Location: admin-login.php");
    exit;
}

$timeout_duration = 100;
$last_activity = (int)($_SESSION["admin_last_activity"] ?? 0);

if (
    $last_activity === 0 ||
    (time() - $last_activity) >= $timeout_duration
) {
    unset(
        $_SESSION["admin_id"],
        $_SESSION["admin_username"],
        $_SESSION["admin_last_activity"]
    );

    if (function_exists("needify_regenerate_session")) {
        needify_regenerate_session();
    } else {
        session_regenerate_id(true);
    }

    header("Location: admin-login.php?timeout=1");
    exit;
}

$_SESSION["admin_last_activity"] = time();

/*
|--------------------------------------------------------------------------
| PREVENT CACHED ADMIN PAGE
|--------------------------------------------------------------------------
*/

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$message_type = "";

/*
|--------------------------------------------------------------------------
| DELETE USER
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_user"])) {

    $user_id = filter_input(INPUT_POST, "user_id", FILTER_VALIDATE_INT);
    $csrf_token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrf_token) ||
        !hash_equals($_SESSION["csrf_token"], $csrf_token)
    ) {
        $message = "Invalid request. Please refresh the page and try again.";
        $message_type = "error";

    } elseif (!$user_id || $user_id <= 0) {

        $message = "Invalid user selected.";
        $message_type = "error";

    } else {

        try {

            /*
             * Protect related records before deleting a user.
             */

            $related_stmt = $conn->prepare(
                "SELECT
                    (SELECT COUNT(*) FROM needs WHERE user_id = ?) AS total_needs,
                    (SELECT COUNT(*) FROM orders WHERE user_id = ?) AS total_orders,
                    (SELECT COUNT(*) FROM messages WHERE sender_id = ? OR receiver_id = ?) AS total_messages"
            );

            if (!$related_stmt) {
                throw new Exception("Unable to check user records.");
            }

            $related_stmt->bind_param(
                "iiii",
                $user_id,
                $user_id,
                $user_id,
                $user_id
            );

            $related_stmt->execute();

            $related_result = $related_stmt->get_result();
            $related_data = $related_result->fetch_assoc();

            $related_stmt->close();

            $has_related_records =
                (int)($related_data["total_needs"] ?? 0) > 0 ||
                (int)($related_data["total_orders"] ?? 0) > 0 ||
                (int)($related_data["total_messages"] ?? 0) > 0;

            if ($has_related_records) {

                $message = "This user has linked orders, needs, or messages. Delete is blocked to protect database records.";
                $message_type = "error";

            } else {

                $delete_stmt = $conn->prepare(
                    "DELETE FROM users WHERE id = ?"
                );

                if (!$delete_stmt) {
                    throw new Exception("Unable to prepare user deletion.");
                }

                $delete_stmt->bind_param("i", $user_id);
                $delete_stmt->execute();

                if ($delete_stmt->affected_rows > 0) {
                    $message = "User deleted successfully.";
                    $message_type = "success";
                } else {
                    $message = "User was not found.";
                    $message_type = "error";
                }

                $delete_stmt->close();
            }

        } catch (Throwable $e) {

            error_log("Needify user deletion error: " . $e->getMessage());

            $message = "Unable to delete this user. The database may contain linked records.";
            $message_type = "error";
        }
    }
}

/*
|--------------------------------------------------------------------------
| SEARCH USERS
|--------------------------------------------------------------------------
*/

$search = trim($_GET["search"] ?? "");

/*
|--------------------------------------------------------------------------
| SORTING
|--------------------------------------------------------------------------
*/

$allowed_sort = [
    "id",
    "full_name",
    "username",
    "email",
    "created_at"
];

$sort = $_GET["sort"] ?? "id";

if (!in_array($sort, $allowed_sort, true)) {
    $sort = "id";
}

$order = strtoupper($_GET["order"] ?? "DESC");

if (!in_array($order, ["ASC", "DESC"], true)) {
    $order = "DESC";
}

/*
|--------------------------------------------------------------------------
| GET USERS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        full_name,
        username,
        email,
        phone,
        country_code,
        created_at
    FROM users
";

if ($search !== "") {
    $sql .= "
        WHERE
            full_name LIKE ?
            OR username LIKE ?
            OR email LIKE ?
            OR phone LIKE ?
    ";
}

$sql .= " ORDER BY `$sort` $order";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Needify users query error: " . $conn->error);
    die("Database query could not be prepared.");
}

if ($search !== "") {

    $search_value = "%" . $search . "%";

    $stmt->bind_param(
        "ssss",
        $search_value,
        $search_value,
        $search_value,
        $search_value
    );
}

$stmt->execute();
$result = $stmt->get_result();

$total_users = $result ? $result->num_rows : 0;

$current_admin = $_SESSION["admin_username"] ?? "Admin";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Needify | Manage Users</title>

    <link rel="icon" type="image/png" href="favicon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>

        :root {
            --purple: #7956C8;
            --purple-dark: #6241AE;
            --pink: #FFE5EF;
            --cream: #FFFAF5;
            --white: #FFFFFF;
            --dark: #302747;
            --muted: #82798F;
            --border: #EEE7F3;
            --danger: #D94B69;
            --danger-light: #FFF0F3;
            --table-heading: #F4EFFB;
            --row-hover: #FFFAFD;
            --success: #218653;
            --success-light: #E8F8EF;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', Arial, sans-serif;
            background: var(--cream);
            color: var(--dark);
            min-height: 100vh;
            transition: background 0.3s, color 0.3s;
        }

        body.dark-mode {
            --cream: #171421;
            --white: #231D30;
            --dark: #F4EFFB;
            --muted: #C0B5D0;
            --border: #40364F;
            --table-heading: #352A45;
            --row-hover: #30263E;
            --pink: #49344F;
            --danger-light: #482A38;
            --success-light: #193D30;
        }

        /* SIDEBAR */

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            overflow-y: auto;
            background: linear-gradient(180deg, #7956C8, #6847B3);
            color: white;
            padding: 30px 18px;
            z-index: 100;
            box-shadow: 5px 0 25px rgba(48,39,71,0.08);
        }

        .logo {
            text-align: center;
            font-size: 29px;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 8px;
        }

        .admin-label {
            text-align: center;
            color: #F3EAFE;
            font-size: 13px;
            margin-bottom: 35px;
            font-weight: 500;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 10px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 15px;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            transition: 0.3s ease;
        }

        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.18);
            transform: translateX(4px);
        }

        .sidebar-menu a.active {
            background: #FFE5EF;
            color: #302747;
            font-weight: 700;
        }

        .logout-link {
            margin-top: 25px;
            background: rgba(255,255,255,0.13);
        }

        /* MAIN */

        .main {
            margin-left: 250px;
            padding: 35px;
            min-height: 100vh;
        }

        /* DISPLAY TOOLS */

        .site-tools {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .site-tools button {
            width: 38px;
            height: 38px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--white);
            color: var(--purple);
            cursor: pointer;
            font-size: 15px;
            transition: 0.3s;
        }

        .site-tools button:hover {
            background: var(--purple);
            color: white;
        }

        /* TOPBAR */

        .topbar {
            background: var(--white);
            padding: 27px 30px;
            border-radius: 18px;
            border: 1px solid var(--border);
            box-shadow: 0 8px 25px rgba(48,39,71,0.04);
            margin-bottom: 25px;
        }

        .heading-tag {
            display: inline-block;
            background: var(--pink);
            color: var(--purple-dark);
            padding: 7px 13px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 13px;
        }

        .topbar h1 {
            color: var(--dark);
            font-size: 27px;
            font-weight: 800;
            margin-bottom: 9px;
        }

        .topbar p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        /* ALERT */

        .alert {
            padding: 14px 17px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.6;
        }

        .alert.success {
            background: var(--success-light);
            color: var(--success);
        }

        .alert.error {
            background: var(--danger-light);
            color: var(--danger);
        }

        /* TABLE CONTAINER */

        .table-container {
            background: var(--white);
            padding: 25px;
            border-radius: 18px;
            border: 1px solid var(--border);
            box-shadow: 0 8px 25px rgba(48,39,71,0.04);
            overflow-x: auto;
        }

        .table-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .table-heading h2 {
            font-size: 18px;
            color: var(--dark);
            font-weight: 700;
        }

        .table-heading span {
            background: var(--pink);
            color: var(--purple-dark);
            padding: 8px 13px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        /* SEARCH AND FILTER */

        .filter-area {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .filter-area input,
        .filter-area select {
            min-height: 42px;
            padding: 10px 13px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--cream);
            color: var(--dark);
            font-family: inherit;
            font-size: 13px;
            outline: none;
        }

        .filter-area input {
            flex: 1;
            min-width: 180px;
        }

        .filter-area input:focus,
        .filter-area select:focus {
            border-color: var(--purple);
        }

        .filter-btn {
            min-height: 42px;
            padding: 10px 17px;
            border: none;
            border-radius: 9px;
            background: var(--purple);
            color: white;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .filter-btn:hover {
            background: var(--purple-dark);
        }

        .clear-btn {
            text-decoration: none;
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
            padding: 10px;
        }

        /* TABLE */

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        thead {
            background: var(--table-heading);
        }

        th {
            color: var(--purple-dark);
            padding: 16px 14px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 16px 14px;
            border-bottom: 1px solid var(--border);
            color: var(--dark);
            font-size: 13px;
            vertical-align: middle;
        }

        tbody tr {
            transition: background 0.2s ease;
        }

        tbody tr:hover {
            background: var(--row-hover);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .user-id {
            color: var(--purple);
            font-weight: 700;
        }

        .username {
            font-weight: 600;
        }

        .email,
        .registered {
            color: var(--muted);
        }

        .registered {
            white-space: nowrap;
        }

        /* DELETE BUTTON */

        .delete-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--danger-light);
            color: var(--danger);
            padding: 9px 13px;
            border: none;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 700;
            transition: 0.3s ease;
            white-space: nowrap;
            cursor: pointer;
        }

        .delete-btn:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-2px);
        }

        /* EMPTY STATE */

        .empty {
            text-align: center;
            padding: 65px 20px;
            color: var(--muted);
        }

        .empty-icon {
            font-size: 45px;
            margin-bottom: 15px;
        }

        .empty h3 {
            color: var(--dark);
            margin-bottom: 8px;
            font-size: 18px;
        }

        .empty p {
            font-size: 13px;
        }

        /* FOOTER */

        .footer {
            margin-top: 30px;
            padding: 20px;
            text-align: center;
            color: var(--muted);
            font-size: 12px;
            border-top: 1px solid var(--border);
        }

        /* PAGE UP / DOWN */

        .page-controls {
            position: fixed;
            right: 22px;
            bottom: 22px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 999;
        }

        .page-controls button {
            width: 43px;
            height: 43px;
            border: none;
            border-radius: 50%;
            background: var(--purple);
            color: white;
            cursor: pointer;
            font-size: 17px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.20);
            transition: 0.3s;
        }

        .page-controls button:hover {
            background: var(--purple-dark);
            transform: scale(1.08);
        }

        /* RESPONSIVE */

        @media (max-width: 900px) {

            .sidebar {
                width: 210px;
            }

            .main {
                margin-left: 210px;
                padding: 22px;
            }
        }

        @media (max-width: 650px) {

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
                padding: 20px;
            }

            .logo {
                font-size: 25px;
            }

            .admin-label {
                margin-bottom: 20px;
            }

            .sidebar-menu {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 7px;
            }

            .sidebar-menu li {
                margin: 0;
            }

            .sidebar-menu a {
                justify-content: center;
                padding: 12px 8px;
                font-size: 12px;
            }

            .main {
                margin-left: 0;
                padding: 18px;
            }

            .topbar {
                padding: 22px;
            }

            .topbar h1 {
                font-size: 23px;
            }

            .table-container {
                padding: 17px;
            }

            .site-tools {
                justify-content: center;
                flex-wrap: wrap;
            }

            .filter-area {
                align-items: stretch;
                flex-direction: column;
            }

            .filter-area input,
            .filter-area select {
                width: 100%;
            }
        }

    </style>

</head>

<body>

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="logo">Needify</div>

        <div class="admin-label">
            🛡️ Admin Panel
        </div>

        <ul class="sidebar-menu">

            <li>
                <a href="admin-dashboard.php">
                    🏠 Dashboard
                </a>
            </li>

            <li>
                <a href="admin-user.php" class="active">
                    👤 Users
                </a>
            </li>

            <li>
                <a href="admin-needs.php">
                    📋 Needs
                </a>
            </li>

            <li>
                <a href="admin-messages.php">
                    💬 Messages
                </a>
            </li>

            <li>
                <a href="admin-orders.php">
                    🛒 Orders
                </a>
            </li>

            <li>
                <a href="admin-category.php">
                    📂 Categories
                </a>
            </li>

            <li>
                <a href="admin-products.php">
                    📦 Products
                </a>
            </li>

            <li>
                <a href="admin-management.php">
                    🛡️ Admin Management
                </a>
            </li>

            <li>
                <a href="admin-logout.php" class="logout-link">
                    🚪 Logout
                </a>
            </li>

        </ul>

    </aside>

    <!-- MAIN CONTENT -->

    <main class="main">

        <!-- DISPLAY SETTINGS -->

        <div class="site-tools" aria-label="Display settings">

            <button type="button" id="fontDecrease" title="Decrease font size">
                <i class="fa-solid fa-minus"></i>
            </button>

            <button type="button" id="fontReset" title="Reset font size">
                <i class="fa-solid fa-rotate-left"></i>
            </button>

            <button type="button" id="fontIncrease" title="Increase font size">
                <i class="fa-solid fa-plus"></i>
            </button>

            <button type="button" id="centerToggle" title="Toggle center alignment">
                <i class="fa-solid fa-align-center"></i>
            </button>

            <button type="button" id="themeToggle" title="Switch to dark mode">
                <i class="fa-solid fa-moon"></i>
            </button>

        </div>

        <!-- TOPBAR -->

        <div class="topbar">

            <span class="heading-tag">
                👥 USER MANAGEMENT
            </span>

            <h1>Manage Users</h1>

            <p>
                Welcome, <?php echo htmlspecialchars($current_admin, ENT_QUOTES, "UTF-8"); ?>.
                View and manage registered Needify users.
            </p>

        </div>

        <!-- ALERT MESSAGE -->

        <?php if ($message !== ""): ?>

            <div class="alert <?php echo htmlspecialchars($message_type, ENT_QUOTES, "UTF-8"); ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
            </div>

        <?php endif; ?>

        <!-- USERS TABLE -->

        <div class="table-container">

            <div class="table-heading">

                <h2>Registered Users</h2>

                <span>
                    <?php echo (int)$total_users; ?> Users Found
                </span>

            </div>

            <!-- SEARCH AND SORT -->

            <form method="GET" action="admin-user.php" class="filter-area">

                <input
                    type="search"
                    name="search"
                    placeholder="Search name, username, email or phone..."
                    value="<?php echo htmlspecialchars($search, ENT_QUOTES, "UTF-8"); ?>"
                >

                <select name="sort">

                    <option value="id" <?php echo $sort === "id" ? "selected" : ""; ?>>
                        Sort by ID
                    </option>

                    <option value="full_name" <?php echo $sort === "full_name" ? "selected" : ""; ?>>
                        Sort by Name
                    </option>

                    <option value="username" <?php echo $sort === "username" ? "selected" : ""; ?>>
                        Sort by Username
                    </option>

                    <option value="email" <?php echo $sort === "email" ? "selected" : ""; ?>>
                        Sort by Email
                    </option>

                    <option value="created_at" <?php echo $sort === "created_at" ? "selected" : ""; ?>>
                        Sort by Registration Date
                    </option>

                </select>

                <select name="order">

                    <option value="DESC" <?php echo $order === "DESC" ? "selected" : ""; ?>>
                        Newest / Z-A
                    </option>

                    <option value="ASC" <?php echo $order === "ASC" ? "selected" : ""; ?>>
                        Oldest / A-Z
                    </option>

                </select>

                <button type="submit" class="filter-btn">
                    <i class="fa-solid fa-filter"></i>
                    Apply
                </button>

                <a href="admin-user.php" class="clear-btn">
                    Clear
                </a>

            </form>

            <?php if ($result && $result->num_rows > 0): ?>

                <table>

                    <thead>

                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Registered</th>
                            <th>Action</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php while ($user = $result->fetch_assoc()): ?>

                            <tr>

                                <td class="user-id">
                                    <?php echo (int)$user["id"]; ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($user["full_name"] ?? "", ENT_QUOTES, "UTF-8"); ?>
                                </td>

                                <td class="username">
                                    <?php echo htmlspecialchars($user["username"] ?? "", ENT_QUOTES, "UTF-8"); ?>
                                </td>

                                <td class="email">
                                    <?php echo htmlspecialchars($user["email"] ?? "", ENT_QUOTES, "UTF-8"); ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        ($user["country_code"] ?? "") .
                                        ($user["phone"] ?? ""),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>
                                </td>

                                <td class="registered">
                                    <?php
                                    echo htmlspecialchars(
                                        $user["created_at"] ?? "",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>
                                </td>

                                <td>

                                    <form
                                        method="POST"
                                        action="admin-user.php"
                                        onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');"
                                    >

                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?php echo (int)$user["id"]; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="delete_user"
                                            value="1"
                                            class="delete-btn"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                            Delete
                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <div class="empty">

                    <div class="empty-icon">👥</div>

                    <h3>No Users Found</h3>

                    <p>
                        No registered users match your search.
                    </p>

                </div>

            <?php endif; ?>

        </div>

        <!-- FOOTER -->

        <footer class="footer">
            © <?php echo date("Y"); ?> Needify. All Rights Reserved.
            <br><br>
            Admin Panel | User Management
        </footer>

    </main>

    <!-- PAGE UP / DOWN BUTTONS -->

    <div class="page-controls">

        <button type="button" id="pageUp" title="Page Up">
            <i class="fa-solid fa-arrow-up"></i>
        </button>

        <button type="button" id="pageDown" title="Page Down">
            <i class="fa-solid fa-arrow-down"></i>
        </button>

    </div>

    <!-- DISPLAY SETTINGS SCRIPT -->

    <script>

        (function () {

            const body = document.body;

            const themeToggle = document.getElementById("themeToggle");
            const fontDecrease = document.getElementById("fontDecrease");
            const fontReset = document.getElementById("fontReset");
            const fontIncrease = document.getElementById("fontIncrease");
            const centerToggle = document.getElementById("centerToggle");
            const pageUp = document.getElementById("pageUp");
            const pageDown = document.getElementById("pageDown");

            // THEME SETTINGS

            function applyTheme(theme) {

                body.classList.toggle("dark-mode", theme === "dark");

                if (themeToggle) {

                    themeToggle.innerHTML = theme === "dark"
                        ? '<i class="fa-solid fa-sun"></i>'
                        : '<i class="fa-solid fa-moon"></i>';

                    themeToggle.title = theme === "dark"
                        ? "Switch to light mode"
                        : "Switch to dark mode";
                }
            }

            applyTheme(localStorage.getItem("needifyTheme") || "light");

            themeToggle?.addEventListener("click", function () {

                const nextTheme = body.classList.contains("dark-mode")
                    ? "light"
                    : "dark";

                localStorage.setItem("needifyTheme", nextTheme);

                applyTheme(nextTheme);
            });

            // FONT SIZE SETTINGS

            let fontLevel = Number(
                localStorage.getItem("needifyFontLevel") || 0
            );

            if (!Number.isFinite(fontLevel)) {
                fontLevel = 0;
            }

            fontLevel = Math.max(-2, Math.min(3, fontLevel));

            function applyFontLevel() {

                const zoom = 1 + (fontLevel * 0.05);

                body.style.zoom = String(zoom);

                localStorage.setItem(
                    "needifyFontLevel",
                    String(fontLevel)
                );
            }

            fontDecrease?.addEventListener("click", function () {
                fontLevel = Math.max(-2, fontLevel - 1);
                applyFontLevel();
            });

            fontIncrease?.addEventListener("click", function () {
                fontLevel = Math.min(3, fontLevel + 1);
                applyFontLevel();
            });

            fontReset?.addEventListener("click", function () {
                fontLevel = 0;
                applyFontLevel();
            });

            applyFontLevel();

            // CENTER ALIGNMENT

            function applyAlignment(centered) {

                body.style.textAlign = centered ? "center" : "";

                localStorage.setItem(
                    "needifyCenterAlignment",
                    centered ? "1" : "0"
                );
            }

            let centered =
                localStorage.getItem("needifyCenterAlignment") === "1";

            applyAlignment(centered);

            centerToggle?.addEventListener("click", function () {

                centered = !centered;

                applyAlignment(centered);
            });

            // PAGE UP

            pageUp?.addEventListener("click", function () {

                window.scrollTo({
                    top: 0,
                    behavior: "smooth"
                });
            });

            // PAGE DOWN

            pageDown?.addEventListener("click", function () {

                window.scrollTo({
                    top: document.body.scrollHeight,
                    behavior: "smooth"
                });
            });

        })();

    </script>

</body>

</html>
