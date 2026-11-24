
<?php

/*
|--------------------------------------------------------------------------
| NEEDIFY - ADMIN MESSAGES
| CENTRAL SESSION + 100 SECONDS INACTIVITY TIMEOUT
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/config/session.php";
require_once __DIR__ . "/config/database.php";

/*
|--------------------------------------------------------------------------
| PREVENT PAGE CACHING
|--------------------------------------------------------------------------
*/

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

/*
|--------------------------------------------------------------------------
| ADMIN ACCESS CHECK + 100 SECONDS INACTIVITY TIMEOUT
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["admin_id"])) {
    header("Location: admin-login.php");
    exit;
}

$timeout_duration = 100;

if (
    !isset($_SESSION["admin_last_activity"]) ||
    (time() - (int) $_SESSION["admin_last_activity"]) > $timeout_duration
) {
    unset(
        $_SESSION["admin_id"],
        $_SESSION["admin_username"],
        $_SESSION["admin_last_activity"],
        $_SESSION["csrf_token"]
    );

    needify_regenerate_session();

    header("Location: admin-login.php?timeout=1");
    exit;
}

$_SESSION["admin_last_activity"] = time();

/*
|--------------------------------------------------------------------------
| SAFE OUTPUT
|--------------------------------------------------------------------------
*/

function escapeMessageOutput($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| DELETE MESSAGE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_message"])
) {
    $message_id = filter_input(
        INPUT_POST,
        "message_id",
        FILTER_VALIDATE_INT
    );

    $csrf_token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrf_token) ||
        !hash_equals($_SESSION["csrf_token"], $csrf_token)
    ) {
        $_SESSION["message_alert"] = [
            "type" => "error",
            "text" => "Invalid request. Please refresh the page and try again."
        ];
    } elseif (!$message_id || $message_id < 1) {
        $_SESSION["message_alert"] = [
            "type" => "error",
            "text" => "Invalid message ID."
        ];
    } else {
        $delete_stmt = $conn->prepare(
            "DELETE FROM messages WHERE id = ?"
        );

        if ($delete_stmt) {
            $delete_stmt->bind_param("i", $message_id);

            if ($delete_stmt->execute()) {
                if ($delete_stmt->affected_rows > 0) {
                    $_SESSION["message_alert"] = [
                        "type" => "success",
                        "text" => "Message deleted successfully."
                    ];
                } else {
                    $_SESSION["message_alert"] = [
                        "type" => "error",
                        "text" => "Message not found or already deleted."
                    ];
                }
            } else {
                $_SESSION["message_alert"] = [
                    "type" => "error",
                    "text" => "Unable to delete this message."
                ];
            }

            $delete_stmt->close();
        } else {
            $_SESSION["message_alert"] = [
                "type" => "error",
                "text" => "Database error. Please try again."
            ];
        }
    }

    header("Location: admin-messages.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| DISPLAY DELETE RESULT
|--------------------------------------------------------------------------
*/

$alert = $_SESSION["message_alert"] ?? null;
unset($_SESSION["message_alert"]);

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$search = trim((string) ($_GET["search"] ?? ""));

/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$per_page = 10;

$page = filter_input(
    INPUT_GET,
    "page",
    FILTER_VALIDATE_INT
);

if (!$page || $page < 1) {
    $page = 1;
}

/*
|--------------------------------------------------------------------------
| TOTAL MESSAGES
|--------------------------------------------------------------------------
*/

$count_sql = "
    SELECT COUNT(*) AS total
    FROM messages AS m
    LEFT JOIN needs AS n ON n.id = m.need_id
    LEFT JOIN users AS s ON s.id = m.sender_id
    LEFT JOIN users AS r ON r.id = m.receiver_id
";

if ($search !== "") {
    $count_sql .= "
        WHERE
            m.message LIKE ?
            OR n.title LIKE ?
            OR s.full_name LIKE ?
            OR s.username LIKE ?
            OR s.email LIKE ?
            OR r.full_name LIKE ?
            OR r.username LIKE ?
            OR r.email LIKE ?
    ";
}

$count_stmt = $conn->prepare($count_sql);

$total_messages = 0;

if ($count_stmt) {
    if ($search !== "") {
        $search_param = "%" . $search . "%";

        $count_stmt->bind_param(
            "ssssssss",
            $search_param,
            $search_param,
            $search_param,
            $search_param,
            $search_param,
            $search_param,
            $search_param,
            $search_param
        );
    }

    if ($count_stmt->execute()) {
        $count_result = $count_stmt->get_result();
        $count_data = $count_result->fetch_assoc();

        $total_messages = (int) ($count_data["total"] ?? 0);
    }

    $count_stmt->close();
}

/*
|--------------------------------------------------------------------------
| TOTAL PAGES
|--------------------------------------------------------------------------
*/

$total_pages = max(
    1,
    (int) ceil($total_messages / $per_page)
);

if ($page > $total_pages) {
    $page = $total_pages;
}

$offset = ($page - 1) * $per_page;

/*
|--------------------------------------------------------------------------
| GET MESSAGES
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        m.id,
        m.message,
        m.created_at,

        n.title AS need_title,

        s.full_name AS sender_name,
        s.username AS sender_username,
        s.email AS sender_email,

        r.full_name AS receiver_name,
        r.username AS receiver_username,
        r.email AS receiver_email

    FROM messages AS m

    LEFT JOIN needs AS n
        ON n.id = m.need_id

    LEFT JOIN users AS s
        ON s.id = m.sender_id

    LEFT JOIN users AS r
        ON r.id = m.receiver_id
";

if ($search !== "") {
    $sql .= "
        WHERE
            m.message LIKE ?
            OR n.title LIKE ?
            OR s.full_name LIKE ?
            OR s.username LIKE ?
            OR s.email LIKE ?
            OR r.full_name LIKE ?
            OR r.username LIKE ?
            OR r.email LIKE ?
    ";
}

$sql .= " ORDER BY m.id DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

$result = false;

if ($stmt) {
    if ($search !== "") {
        $search_param = "%" . $search . "%";

        $stmt->bind_param(
            "ssssssssii",
            $search_param,
            $search_param,
            $search_param,
            $search_param,
            $search_param,
            $search_param,
            $search_param,
            $search_param,
            $per_page,
            $offset
        );
    } else {
        $stmt->bind_param(
            "ii",
            $per_page,
            $offset
        );
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
    }
}

/*
|--------------------------------------------------------------------------
| PAGINATION LINK
|--------------------------------------------------------------------------
*/

function messagePageLink($pageNumber, $searchValue): string
{
    return "admin-messages.php?" . http_build_query([
        "search" => $searchValue,
        "page" => $pageNumber
    ]);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Needify | Manage Messages</title>

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
            --message-bg: #FFFAFD;
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

        /* DARK MODE */

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
            --message-bg: #30263E;
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
            box-shadow: 0 5px 15px rgba(48,39,71,0.08);
        }

        .logout-link {
            margin-top: 30px;
            background: rgba(255,255,255,0.13);
        }

        .logout-link:hover {
            background: #FFE5EF !important;
            color: #302747 !important;
        }

        /* MAIN CONTENT */

        .main {
            margin-left: 250px;
            padding: 35px;
            min-height: 100vh;
        }

        /* DISPLAY SETTINGS */

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

        .message-count {
            display: inline-block;
            margin-top: 15px;
            padding: 8px 14px;
            background: var(--pink);
            color: var(--purple-dark);
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
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

        /* TABLE CARD */

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
        }

        .table-heading h2 {
            font-size: 18px;
            color: var(--dark);
            font-weight: 700;
        }

        .table-heading span {
            background: var(--table-heading);
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

        .clear-search {
            background: var(--pink);
            color: var(--purple-dark);
        }

        /* TABLE */

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1150px;
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
            vertical-align: top;
            color: var(--dark);
            font-size: 13px;
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

        .message-id {
            color: var(--purple);
            font-weight: 700;
        }

        .message-content {
            max-width: 280px;
            min-width: 180px;
            line-height: 1.7;
            color: var(--muted);
            word-break: break-word;
            background: var(--message-bg);
            padding: 12px;
            border-radius: 10px;
        }

        .user-name {
            font-weight: 700;
            color: var(--dark);
        }

        .username {
            color: var(--purple);
            font-size: 12px;
            margin-top: 5px;
        }

        .email {
            color: var(--muted);
            font-size: 11px;
            margin-top: 5px;
        }

        .need-title {
            display: inline-block;
            background: var(--pink);
            color: var(--purple-dark);
            padding: 7px 10px;
            border-radius: 8px;
            font-weight: 700;
            line-height: 1.5;
        }

        .deleted-need {
            color: var(--muted);
            font-style: italic;
            font-size: 12px;
        }

        .message-date {
            color: var(--muted);
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

        /* PAGINATION */

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 9px;
            margin-top: 28px;
        }

        .pagination a {
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 9px;
            background: var(--table-heading);
            color: var(--purple-dark);
            font-size: 13px;
            font-weight: 700;
            transition: 0.3s ease;
        }

        .pagination a:hover {
            background: var(--pink);
            transform: translateY(-2px);
        }

        .pagination .current {
            padding: 10px 15px;
            border-radius: 9px;
            background: var(--purple);
            color: white;
            font-size: 13px;
            font-weight: 700;
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

        footer {
            text-align: center;
            color: var(--muted);
            font-size: 12px;
            padding: 25px 10px 10px;
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

        <div class="logo">
            Needify
        </div>

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
                <a href="admin-user.php">
                    👤 Users
                </a>
            </li>

            <li>
                <a href="admin-needs.php">
                    📋 Needs
                </a>
            </li>

            <li>
                <a href="admin-messages.php" class="active">
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

            <button type="button" id="fontDecrease" title="Decrease font size" aria-label="Decrease font size">
                <i class="fa-solid fa-minus"></i>
            </button>

            <button type="button" id="fontReset" title="Reset font size" aria-label="Reset font size">
                <i class="fa-solid fa-rotate-left"></i>
            </button>

            <button type="button" id="fontIncrease" title="Increase font size" aria-label="Increase font size">
                <i class="fa-solid fa-plus"></i>
            </button>

            <button type="button" id="themeToggle" title="Switch to dark mode" aria-label="Switch to dark mode">
                <i class="fa-solid fa-moon"></i>
            </button>

        </div>

        <!-- TOPBAR -->

        <div class="topbar">

            <span class="heading-tag">
                💬 MESSAGE MANAGEMENT
            </span>

            <h1>Manage Messages</h1>

            <p>
                View and manage messages exchanged between Needify users.
                Stay connected with your shopping community.
            </p>

            <span class="message-count">
                Total Messages: <?php echo $total_messages; ?>
            </span>

        </div>

        <!-- ALERT -->

        <?php if ($alert): ?>

            <div class="alert <?php echo escapeMessageOutput($alert["type"] ?? "error"); ?>">
                <?php echo escapeMessageOutput($alert["text"] ?? ""); ?>
            </div>

        <?php endif; ?>

        <!-- MESSAGES TABLE -->

        <div class="table-container">

            <div class="table-heading">

                <h2>All Messages</h2>

                <span>
                    Showing <?php echo $result ? (int) $result->num_rows : 0; ?> messages
                </span>

            </div>

            <!-- SEARCH FORM -->

            <form method="GET" action="admin-messages.php" class="search-form">

                <input
                    type="text"
                    name="search"
                    placeholder="Search message, need, sender or receiver..."
                    value="<?php echo escapeMessageOutput($search); ?>"
                >

                <button type="submit">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Search
                </button>

                <?php if ($search !== ""): ?>

                    <a href="admin-messages.php" class="clear-search">
                        Clear
                    </a>

                <?php endif; ?>

            </form>

            <?php if ($result && $result->num_rows > 0): ?>

                <table>

                    <thead>

                        <tr>
                            <th>ID</th>
                            <th>Need</th>
                            <th>Sender</th>
                            <th>Receiver</th>
                            <th>Message</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php while ($message = $result->fetch_assoc()): ?>

                            <tr>

                                <td class="message-id">
                                    <?php echo (int) $message["id"]; ?>
                                </td>

                                <!-- NEED -->

                                <td>

                                    <?php if (!empty($message["need_title"])): ?>

                                        <span class="need-title">
                                            <?php echo escapeMessageOutput($message["need_title"]); ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="deleted-need">
                                            Deleted Need
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <!-- SENDER -->

                                <td>

                                    <div class="user-name">
                                        <?php
                                        echo escapeMessageOutput(
                                            $message["sender_name"] ?? "Unknown User"
                                        );
                                        ?>
                                    </div>

                                    <div class="username">
                                        @<?php
                                        echo escapeMessageOutput(
                                            $message["sender_username"] ?? "unknown"
                                        );
                                        ?>
                                    </div>

                                    <div class="email">
                                        <?php
                                        echo escapeMessageOutput(
                                            $message["sender_email"] ?? ""
                                        );
                                        ?>
                                    </div>

                                </td>

                                <!-- RECEIVER -->

                                <td>

                                    <div class="user-name">
                                        <?php
                                        echo escapeMessageOutput(
                                            $message["receiver_name"] ?? "Unknown User"
                                        );
                                        ?>
                                    </div>

                                    <div class="username">
                                        @<?php
                                        echo escapeMessageOutput(
                                            $message["receiver_username"] ?? "unknown"
                                        );
                                        ?>
                                    </div>

                                    <div class="email">
                                        <?php
                                        echo escapeMessageOutput(
                                            $message["receiver_email"] ?? ""
                                        );
                                        ?>
                                    </div>

                                </td>

                                <!-- MESSAGE -->

                                <td>

                                    <div class="message-content">
                                        <?php
                                        echo nl2br(
                                            escapeMessageOutput(
                                                $message["message"] ?? ""
                                            )
                                        );
                                        ?>
                                    </div>

                                </td>

                                <!-- DATE -->

                                <td class="message-date">
                                    <?php
                                    echo escapeMessageOutput(
                                        $message["created_at"] ?? ""
                                    );
                                    ?>
                                </td>

                                <!-- ACTION -->

                                <td>

                                    <form
                                        method="POST"
                                        action="admin-messages.php"
                                        onsubmit="return confirm('Are you sure you want to delete this message?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="message_id"
                                            value="<?php echo (int) $message["id"]; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo escapeMessageOutput($_SESSION["csrf_token"]); ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="delete_message"
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

                <!-- PAGINATION -->

                <?php if ($total_pages > 1): ?>

                    <div class="pagination">

                        <?php if ($page > 1): ?>

                            <a href="<?php echo escapeMessageOutput(messagePageLink($page - 1, $search)); ?>">
                                ← Previous
                            </a>

                        <?php endif; ?>

                        <span class="current">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        </span>

                        <?php if ($page < $total_pages): ?>

                            <a href="<?php echo escapeMessageOutput(messagePageLink($page + 1, $search)); ?>">
                                Next →
                            </a>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

            <?php else: ?>

                <div class="empty">

                    <div class="empty-icon">💬</div>

                    <h3>No Messages Found</h3>

                    <p>
                        No messages match your search or there are currently no messages.
                    </p>

                </div>

            <?php endif; ?>

        </div>

        <footer>
            © <?php echo date("Y"); ?> Needify. All Rights Reserved.
        </footer>

    </main>

    <!-- PAGE UP / DOWN BUTTONS -->

    <div class="page-controls">

        <button type="button" id="pageUp" title="Page Up" aria-label="Page Up">
            <i class="fa-solid fa-arrow-up"></i>
        </button>

        <button type="button" id="pageDown" title="Page Down" aria-label="Page Down">
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

                    themeToggle.setAttribute("aria-label", themeToggle.title);
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

<?php

if ($stmt) {
    $stmt->close();
}

?>
