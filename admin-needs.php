
<?php
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
$last_activity = (int) ($_SESSION["admin_last_activity"] ?? 0);

if (
    $last_activity === 0 ||
    (time() - $last_activity) >= $timeout_duration
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

/*
|--------------------------------------------------------------------------
| CACHE PREVENTION
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
$message_type = "success";

/*
|--------------------------------------------------------------------------
| SAFE OUTPUT
|--------------------------------------------------------------------------
*/

function escapeNeedOutput($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

/*
|--------------------------------------------------------------------------
| DELETE NEED
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_need"])
) {
    $need_id = filter_input(
        INPUT_POST,
        "need_id",
        FILTER_VALIDATE_INT
    );

    $csrf_token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrf_token) ||
        !hash_equals($_SESSION["csrf_token"], $csrf_token)
    ) {
        $message = "Invalid request. Please refresh the page and try again.";
        $message_type = "error";
    } elseif (!$need_id || $need_id < 1) {
        $message = "Invalid need ID.";
        $message_type = "error";
    } else {

        $stmt = $conn->prepare(
            "DELETE FROM needs WHERE id = ?"
        );

        if ($stmt) {

            $stmt->bind_param("i", $need_id);

            if ($stmt->execute()) {

                if ($stmt->affected_rows > 0) {
                    $message = "Need deleted successfully.";
                    $message_type = "success";
                } else {
                    $message = "Need not found or already deleted.";
                    $message_type = "error";
                }

            } else {
                $message = "Unable to delete this need. It may be linked with other records.";
                $message_type = "error";
            }

            $stmt->close();

        } else {
            $message = "Database error. Please try again.";
            $message_type = "error";
        }
    }
}

/*
|--------------------------------------------------------------------------
| SEARCH AND SORTING
|--------------------------------------------------------------------------
*/

$search = trim($_GET["search"] ?? "");

$allowed_sort_columns = [
    "id" => "needs.id",
    "title" => "needs.title",
    "category" => "needs.category",
    "budget" => "needs.budget",
    "date" => "needs.created_at"
];

$sort = $_GET["sort"] ?? "id";
$order = strtoupper($_GET["order"] ?? "DESC");

if (!isset($allowed_sort_columns[$sort])) {
    $sort = "id";
}

if (!in_array($order, ["ASC", "DESC"], true)) {
    $order = "DESC";
}

$sort_column = $allowed_sort_columns[$sort];

/*
|--------------------------------------------------------------------------
| GET NEEDS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        needs.id,
        needs.title,
        needs.category,
        needs.description,
        needs.budget,
        needs.created_at,
        users.full_name,
        users.username,
        users.email
    FROM needs
    LEFT JOIN users ON needs.user_id = users.id
";

if ($search !== "") {

    $sql .= "
        WHERE
            needs.title LIKE ?
            OR needs.category LIKE ?
            OR needs.description LIKE ?
            OR users.full_name LIKE ?
            OR users.username LIKE ?
            OR users.email LIKE ?
    ";
}

$sql .= " ORDER BY $sort_column $order";

$stmt = $conn->prepare($sql);

$result = false;

if ($stmt) {

    if ($search !== "") {

        $search_param = "%" . $search . "%";

        $stmt->bind_param(
            "ssssss",
            $search_param,
            $search_param,
            $search_param,
            $search_param,
            $search_param,
            $search_param
        );
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
    }

    if (!$result) {
        $message = "Unable to load needs. Please try again.";
        $message_type = "error";
    }

} else {
    $message = "Database error while loading needs.";
    $message_type = "error";
}

/*
|--------------------------------------------------------------------------
| SORT LINK FUNCTION
|--------------------------------------------------------------------------
*/

function sortLink(
    $column,
    $currentSort,
    $currentOrder,
    $search
) {
    $nextOrder = (
        $currentSort === $column &&
        $currentOrder === "ASC"
    ) ? "DESC" : "ASC";

    return "admin-needs.php?" . http_build_query([
        "search" => $search,
        "sort" => $column,
        "order" => $nextOrder
    ]);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Needify | Manage Needs</title>

    <link rel="icon" type="image/png" href="favicon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

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
            transition: background .3s, color .3s;
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
            box-shadow: 5px 0 25px rgba(48,39,71,.08);
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
            margin-bottom: 40px;
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
            transition: .3s;
        }

        .sidebar-menu a:hover {
            background: rgba(255,255,255,.18);
            transform: translateX(4px);
        }

        .sidebar-menu a.active {
            background: #FFE5EF;
            color: #302747;
            font-weight: 700;
        }

        .logout-link {
            margin-top: 30px;
            background: rgba(255,255,255,.13);
        }

        .logout-link:hover {
            background: #FFE5EF !important;
            color: #302747 !important;
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
        }

        .site-tools button:hover {
            background: var(--purple);
            color: white;
        }

        /* TOP BAR */

        .topbar {
            background: var(--white);
            padding: 27px 30px;
            border-radius: 18px;
            border: 1px solid var(--border);
            box-shadow: 0 8px 25px rgba(48,39,71,.04);
            margin-bottom: 28px;
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
            padding: 13px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .alert.success {
            background: #E9F8EF;
            color: #217A45;
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
            box-shadow: 0 8px 25px rgba(48,39,71,.04);
            overflow-x: auto;
        }

        .table-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 22px;
        }

        .table-heading h2 {
            font-size: 18px;
            color: var(--dark);
            font-weight: 700;
        }

        .table-count {
            background: var(--pink);
            color: var(--purple-dark);
            padding: 8px 13px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        /* SEARCH */

        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .search-form input {
            flex: 1;
            min-width: 200px;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--cream);
            color: var(--dark);
            outline: none;
            font-family: inherit;
        }

        .search-form input:focus {
            border-color: var(--purple);
        }

        .search-form button,
        .clear-search {
            padding: 12px 17px;
            border: none;
            border-radius: 9px;
            background: var(--purple);
            color: white;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .search-form button:hover {
            background: var(--purple-dark);
        }

        .clear-search {
            background: var(--pink);
            color: var(--purple-dark);
        }

        /* TABLE */

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
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
            letter-spacing: .4px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        th a {
            color: inherit;
            text-decoration: none;
        }

        th a:hover {
            text-decoration: underline;
        }

        td {
            padding: 16px 14px;
            border-bottom: 1px solid var(--border);
            color: var(--dark);
            font-size: 13px;
            vertical-align: top;
        }

        tbody tr:hover {
            background: var(--row-hover);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .need-id {
            color: var(--purple);
            font-weight: 700;
        }

        .need-title {
            font-weight: 700;
            min-width: 120px;
        }

        .category {
            display: inline-block;
            background: var(--pink);
            color: var(--purple-dark);
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .description {
            max-width: 250px;
            min-width: 180px;
            line-height: 1.6;
            color: var(--muted);
        }

        .budget {
            color: var(--purple-dark);
            font-weight: 800;
            white-space: nowrap;
        }

        .not-specified {
            color: var(--muted);
            font-size: 12px;
            font-style: italic;
        }

        .posted-name {
            font-weight: 600;
        }

        .posted-username {
            display: inline-block;
            margin-top: 5px;
            color: var(--purple);
            font-size: 11px;
        }

        .email,
        .posted-date {
            color: var(--muted);
        }

        .posted-date {
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
            white-space: nowrap;
            cursor: pointer;
            transition: .3s;
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

        footer {
            text-align: center;
            color: var(--muted);
            font-size: 12px;
            padding: 25px 10px 10px;
        }

        /* PAGE CONTROLS */

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
            box-shadow: 0 4px 12px rgba(0,0,0,.20);
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

            .logout-link {
                margin-top: 0;
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
            }
        }

    </style>

</head>

<body>

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="logo">Needify</div>

        <div class="admin-label">🛡️ Admin Panel</div>

        <ul class="sidebar-menu">

            <li>
                <a href="admin-dashboard.php">
                    🏠 Dashboard
                </a>
            </li>

            <li>
                <a href="admin-user.php">
                    👤 Users
                </a>
            </li>

            <li>
                <a href="admin-needs.php" class="active">
                    📋 Needs
                </a>
            </li>

            <li>
                <a href="admin-messages.php">
                    💬 Messages
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

            <button
                type="button"
                id="fontDecrease"
                title="Decrease font size"
                aria-label="Decrease font size"
            >
                <i class="fa-solid fa-minus"></i>
            </button>

            <button
                type="button"
                id="fontReset"
                title="Reset font size"
                aria-label="Reset font size"
            >
                <i class="fa-solid fa-rotate-left"></i>
            </button>

            <button
                type="button"
                id="fontIncrease"
                title="Increase font size"
                aria-label="Increase font size"
            >
                <i class="fa-solid fa-plus"></i>
            </button>

            <button
                type="button"
                id="themeToggle"
                title="Switch to dark mode"
                aria-label="Switch to dark mode"
            >
                <i class="fa-solid fa-moon"></i>
            </button>

        </div>

        <!-- PAGE HEADER -->

        <div class="topbar">

            <span class="heading-tag">
                📋 NEED MANAGEMENT
            </span>

            <h1>Manage Needs</h1>

            <p>
                View and manage all needs posted by Needify users.
                Keep track of requests from your community.
            </p>

        </div>

        <!-- ALERT MESSAGE -->

        <?php if ($message !== ""): ?>

            <div class="alert <?php echo escapeNeedOutput($message_type); ?>">

                <?php echo escapeNeedOutput($message); ?>

            </div>

        <?php endif; ?>

        <!-- NEEDS TABLE -->

        <div class="table-container">

            <div class="table-heading">

                <h2>All Posted Needs</h2>

                <?php if ($result): ?>

                    <span class="table-count">
                        <?php echo (int) $result->num_rows; ?> Needs
                    </span>

                <?php endif; ?>

            </div>

            <!-- SEARCH FORM -->

            <form
                method="GET"
                action="admin-needs.php"
                class="search-form"
            >

                <input
                    type="text"
                    name="search"
                    placeholder="Search title, category, user or email..."
                    value="<?php echo escapeNeedOutput($search); ?>"
                >

                <button type="submit">

                    <i class="fa-solid fa-magnifying-glass"></i>
                    Search

                </button>

                <?php if ($search !== ""): ?>

                    <a href="admin-needs.php" class="clear-search">
                        Clear
                    </a>

                <?php endif; ?>

                <input
                    type="hidden"
                    name="sort"
                    value="<?php echo escapeNeedOutput($sort); ?>"
                >

                <input
                    type="hidden"
                    name="order"
                    value="<?php echo escapeNeedOutput($order); ?>"
                >

            </form>

            <!-- TABLE DATA -->

            <?php if ($result && $result->num_rows > 0): ?>

                <table>

                    <thead>

                        <tr>

                            <th>
                                <a href="<?php echo escapeNeedOutput(sortLink("id", $sort, $order, $search)); ?>">
                                    ID ↕
                                </a>
                            </th>

                            <th>
                                <a href="<?php echo escapeNeedOutput(sortLink("title", $sort, $order, $search)); ?>">
                                    Title ↕
                                </a>
                            </th>

                            <th>
                                <a href="<?php echo escapeNeedOutput(sortLink("category", $sort, $order, $search)); ?>">
                                    Category ↕
                                </a>
                            </th>

                            <th>Description</th>

                            <th>
                                <a href="<?php echo escapeNeedOutput(sortLink("budget", $sort, $order, $search)); ?>">
                                    Budget ↕
                                </a>
                            </th>

                            <th>Posted By</th>

                            <th>Email</th>

                            <th>
                                <a href="<?php echo escapeNeedOutput(sortLink("date", $sort, $order, $search)); ?>">
                                    Date ↕
                                </a>
                            </th>

                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php while ($need = $result->fetch_assoc()): ?>

                            <tr>

                                <!-- ID -->

                                <td class="need-id">

                                    <?php echo (int) $need["id"]; ?>

                                </td>

                                <!-- TITLE -->

                                <td class="need-title">

                                    <?php echo escapeNeedOutput($need["title"]); ?>

                                </td>

                                <!-- CATEGORY -->

                                <td>

                                    <span class="category">

                                        <?php echo escapeNeedOutput($need["category"]); ?>

                                    </span>

                                </td>

                                <!-- DESCRIPTION -->

                                <td class="description">

                                    <?php echo escapeNeedOutput($need["description"]); ?>

                                </td>

                                <!-- BUDGET -->

                                <td class="budget">

                                    <?php if (
                                        $need["budget"] !== null &&
                                        $need["budget"] !== ""
                                    ): ?>

                                        Rs. <?php echo escapeNeedOutput($need["budget"]); ?>

                                    <?php else: ?>

                                        <span class="not-specified">
                                            Not specified
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <!-- USER -->

                                <td>

                                    <span class="posted-name">

                                        <?php echo escapeNeedOutput(
                                            $need["full_name"] ?? "Unknown User"
                                        ); ?>

                                    </span>

                                    <br>

                                    <span class="posted-username">

                                        @<?php echo escapeNeedOutput(
                                            $need["username"] ?? "unknown"
                                        ); ?>

                                    </span>

                                </td>

                                <!-- EMAIL -->

                                <td class="email">

                                    <?php echo escapeNeedOutput($need["email"]); ?>

                                </td>

                                <!-- DATE -->

                                <td class="posted-date">

                                    <?php echo escapeNeedOutput($need["created_at"]); ?>

                                </td>

                                <!-- DELETE -->

                                <td>

                                    <form
                                        method="POST"
                                        action="admin-needs.php"
                                        onsubmit="return confirm('Are you sure you want to delete this need?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="need_id"
                                            value="<?php echo (int) $need["id"]; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo escapeNeedOutput($_SESSION["csrf_token"]); ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="delete_need"
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

                    <div class="empty-icon">
                        📋
                    </div>

                    <h3>No Needs Found</h3>

                    <p>
                        No matching needs were found.
                        Needs posted by users will appear here.
                    </p>

                </div>

            <?php endif; ?>

        </div>

        <!-- FOOTER -->

        <footer>

            © <?php echo date("Y"); ?> Needify. All Rights Reserved.

        </footer>

    </main>

    <!-- PAGE CONTROLS -->

    <div class="page-controls">

        <button
            type="button"
            id="pageUp"
            title="Page Up"
            aria-label="Page Up"
        >
            <i class="fa-solid fa-arrow-up"></i>
        </button>

        <button
            type="button"
            id="pageDown"
            title="Page Down"
            aria-label="Page Down"
        >
            <i class="fa-solid fa-arrow-down"></i>
        </button>

    </div>

    <!-- JAVASCRIPT -->

    <script>

        (function () {

            const body = document.body;

            const themeToggle = document.getElementById("themeToggle");

            const fontDecrease = document.getElementById("fontDecrease");

            const fontReset = document.getElementById("fontReset");

            const fontIncrease = document.getElementById("fontIncrease");

            const pageUp = document.getElementById("pageUp");

            const pageDown = document.getElementById("pageDown");

            // THEME SETTINGS

            function applyTheme(theme) {

                body.classList.toggle(
                    "dark-mode",
                    theme === "dark"
                );

                if (themeToggle) {

                    themeToggle.innerHTML = theme === "dark"
                        ? '<i class="fa-solid fa-sun"></i>'
                        : '<i class="fa-solid fa-moon"></i>';

                    themeToggle.title = theme === "dark"
                        ? "Switch to light mode"
                        : "Switch to dark mode";

                    themeToggle.setAttribute(
                        "aria-label",
                        themeToggle.title
                    );
                }
            }

            applyTheme(
                localStorage.getItem("needifyTheme") || "light"
            );

            themeToggle?.addEventListener("click", function () {

                const nextTheme = body.classList.contains("dark-mode")
                    ? "light"
                    : "dark";

                localStorage.setItem(
                    "needifyTheme",
                    nextTheme
                );

                applyTheme(nextTheme);

            });

            // FONT SIZE SETTINGS

            let fontLevel = Number(
                localStorage.getItem("needifyFontLevel") || 0
            );

            if (!Number.isFinite(fontLevel)) {
                fontLevel = 0;
            }

            fontLevel = Math.max(
                -2,
                Math.min(3, fontLevel)
            );

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

            // PAGE UP

            pageUp?.addEventListener("click", function () {

                window.scrollTo({
                    top: 0,
                    behavior: "smooth"
                });

            });

            // PAGE DOWN

            pageDown?.addEventListener("click", function () {

                window.scrollBy({
                    top: window.innerHeight * 0.8,
                    behavior: "smooth"
                });

            });

        })();

    </script>

</body>

</html>
