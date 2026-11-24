
<?php
/*
|--------------------------------------------------------------------------
| NEEDIFY - SECURE ADMIN DASHBOARD
|--------------------------------------------------------------------------
*/

// CENTRAL SESSION CONFIGURATION
require_once __DIR__ . "/config/session.php";

// DATABASE CONNECTION
require_once __DIR__ . "/config/database.php";

// PREVENT CACHED ADMIN DASHBOARD
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// ADMIN ACCESS CHECK
if (
    session_status() !== PHP_SESSION_ACTIVE ||
    !isset($_SESSION["admin_id"])
) {
    header("Location: admin-login.php");
    exit;
}

// 100 SECONDS INACTIVITY TIMEOUT
$timeout_duration = 100;

$last_activity = (int) (
    $_SESSION["admin_last_activity"] ?? 0
);

if (
    $last_activity === 0 ||
    (time() - $last_activity) > $timeout_duration
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

// UPDATE LAST ACTIVITY
$_SESSION["admin_last_activity"] = time();

// ADMIN INFORMATION
$admin_username = $_SESSION["admin_username"] ?? "Admin";

// TOTAL USERS
$user_query = $conn->query(
    "SELECT COUNT(*) AS total_users FROM users"
);

$total_users = $user_query
    ? ($user_query->fetch_assoc()["total_users"] ?? 0)
    : 0;

// TOTAL NEEDS
$need_query = $conn->query(
    "SELECT COUNT(*) AS total_needs FROM needs"
);

$total_needs = $need_query
    ? ($need_query->fetch_assoc()["total_needs"] ?? 0)
    : 0;

// TOTAL MESSAGES
$message_query = $conn->query(
    "SELECT COUNT(*) AS total_messages FROM messages"
);

$total_messages = $message_query
    ? ($message_query->fetch_assoc()["total_messages"] ?? 0)
    : 0;

// TOTAL ORDERS
$order_query = $conn->query(
    "SELECT COUNT(*) AS total_orders FROM orders"
);

$total_orders = $order_query
    ? ($order_query->fetch_assoc()["total_orders"] ?? 0)
    : 0;

// TOTAL PRODUCTS
$product_query = $conn->query(
    "SELECT COUNT(*) AS total_products FROM products"
);

$total_products = $product_query
    ? ($product_query->fetch_assoc()["total_products"] ?? 0)
    : 0;

// TOTAL ADMINS
$admin_query = $conn->query(
    "SELECT COUNT(*) AS total_admins FROM admins"
);

$total_admins = $admin_query
    ? ($admin_query->fetch_assoc()["total_admins"] ?? 0)
    : 0;

// RECENT USERS
$recent_users = $conn->query(
    "SELECT id, full_name, username, email, created_at
     FROM users
     ORDER BY id DESC
     LIMIT 5"
);

// RECENT NEEDS
$recent_needs = $conn->query(
    "SELECT needs.id, needs.title, needs.category, needs.created_at,
            users.full_name
     FROM needs
     LEFT JOIN users ON needs.user_id = users.id
     ORDER BY needs.id DESC
     LIMIT 5"
);

// RECENT MESSAGES
$recent_messages = $conn->query(
    "SELECT messages.id, messages.message, messages.created_at,
            sender.full_name AS sender_name,
            receiver.full_name AS receiver_name
     FROM messages
     LEFT JOIN users AS sender
        ON messages.sender_id = sender.id
     LEFT JOIN users AS receiver
        ON messages.receiver_id = receiver.id
     ORDER BY messages.id DESC
     LIMIT 5"
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Needify | Admin Dashboard</title>

    <link rel="icon" type="image/png" href="favicon.png">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --primary: #7956C8;
            --primary-dark: #6844B5;
            --pink: #FFE5EF;
            --cream: #FFFAF5;
            --white: #FFFFFF;
            --dark: #302747;
            --gray: #746B83;
            --border: #F0E6EF;
            --light-pink: #FFF4F7;
            --table-heading: #F8F1FA;
            --hover-bg: #FFFAF5;
            --shadow: rgba(48, 39, 71, 0.06);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: var(--cream);
            color: var(--dark);
            transition: background 0.3s, color 0.3s;
        }

        body.dark-mode {
            --cream: #171421;
            --white: #231D30;
            --dark: #F4EFFB;
            --gray: #C0B5D0;
            --border: #40364F;
            --light-pink: #30263E;
            --table-heading: #352A45;
            --hover-bg: #30263E;
            --pink: #49344F;
            --shadow: rgba(0, 0, 0, 0.20);
        }

        /* SIDEBAR */

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            overflow-y: auto;
            background: linear-gradient(180deg, #7956C8, #6844B5);
            color: #FFFFFF;
            padding: 25px 18px;
            box-shadow: 4px 0 20px rgba(48, 39, 71, 0.10);
            z-index: 100;
        }

        .logo {
            text-align: center;
            font-size: 30px;
            font-weight: bold;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }

        .logo a {
            color: #FFFFFF;
            text-decoration: none;
        }

        .logo a:hover {
            opacity: 0.85;
        }

        .admin-label {
            text-align: center;
            color: #FFE5EF;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 9px;
        }

        .sidebar-menu a {
            display: block;
            padding: 13px 15px;
            color: #FFFFFF;
            text-decoration: none;
            border-radius: 10px;
            transition: 0.3s;
            font-size: 15px;
        }

        .sidebar-menu a:hover {
            background: rgba(255, 229, 239, 0.25);
            transform: translateX(4px);
        }

        .active-link {
            background: #FFE5EF;
            color: #302747 !important;
            font-weight: bold;
        }

        .logout-link {
            margin-top: 20px;
            background: #FFE5EF;
            color: #302747 !important;
            font-weight: bold;
        }

        .logout-link:hover {
            background: #F8D2E1 !important;
        }

        /* MAIN CONTENT */

        .main {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }

        /* DISPLAY TOOLS */

        .site-tools {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            margin-bottom: 18px;
        }

        .site-tools button {
            width: 38px;
            height: 38px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--white);
            color: var(--primary);
            cursor: pointer;
            font-size: 15px;
            transition: 0.3s;
        }

        .site-tools button:hover {
            background: var(--primary);
            color: #FFFFFF;
        }

        /* TOPBAR */

        .topbar {
            background: var(--white);
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 5px 20px var(--shadow);
            margin-bottom: 25px;
            border: 1px solid var(--border);
        }

        .topbar h1 {
            font-size: 28px;
            margin-bottom: 8px;
            color: var(--primary);
        }

        .topbar p {
            color: var(--gray);
        }

        .topbar strong {
            color: var(--primary);
        }

        /* STATISTICS */

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--white);
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 5px 20px var(--shadow);
            transition: 0.3s;
            border: 1px solid var(--border);
            border-bottom: 4px solid var(--primary);
        }

        .stat-card:nth-child(2) {
            border-bottom-color: #EBAAC5;
        }

        .stat-card:nth-child(3) {
            border-bottom-color: #B49AE5;
        }

        .stat-card:nth-child(4) {
            border-bottom-color: #65A8D8;
        }

        .stat-card:nth-child(5) {
            border-bottom-color: #70B99A;
        }

        .stat-card:nth-child(6) {
            border-bottom-color: #E6A85C;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(121, 86, 200, 0.12);
        }

        .stat-icon {
            font-size: 32px;
            margin-bottom: 15px;
        }

        .stat-card h3 {
            font-size: 15px;
            color: var(--gray);
            margin-bottom: 8px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: var(--primary);
        }

        /* SECTIONS */

        .section {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 5px 20px var(--shadow);
            margin-bottom: 30px;
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid var(--border);
            background: var(--light-pink);
        }

        .section-header h2 {
            color: var(--dark);
            font-size: 20px;
        }

        .view-all {
            color: var(--primary);
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
        }

        .view-all:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* TABLE */

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: var(--table-heading);
            color: var(--dark);
            font-size: 13px;
            text-align: left;
            padding: 15px 20px;
            white-space: nowrap;
        }

        td {
            padding: 15px 20px;
            border-top: 1px solid var(--border);
            font-size: 14px;
            vertical-align: middle;
            color: var(--dark);
        }

        tr:hover td {
            background: var(--hover-bg);
        }

        .username {
            color: var(--primary);
            font-weight: bold;
        }

        .category {
            display: inline-block;
            background: var(--pink);
            color: var(--primary);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .message-preview {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .empty {
            padding: 30px;
            text-align: center;
            color: var(--gray);
        }

        /* FOOTER */

        .admin-footer {
            margin-top: 35px;
            padding: 22px;
            text-align: center;
            background: var(--white);
            color: var(--gray);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 5px 20px var(--shadow);
            font-size: 14px;
        }

        .admin-footer strong {
            color: var(--primary);
        }

        .admin-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: bold;
        }

        .admin-footer a:hover {
            text-decoration: underline;
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
            background: var(--primary);
            color: #FFFFFF;
            cursor: pointer;
            font-size: 17px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.20);
            transition: 0.3s;
        }

        .page-controls button:hover {
            background: var(--primary-dark);
            transform: scale(1.08);
        }

        /* RESPONSIVE DESIGN */

        @media (max-width: 1000px) {
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 900px) {
            .sidebar {
                width: 210px;
            }

            .main {
                margin-left: 210px;
            }
        }

        @media (max-width: 650px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .main {
                margin-left: 0;
                padding: 20px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .section-header {
                align-items: flex-start;
                gap: 10px;
                flex-direction: column;
            }

            .topbar h1 {
                font-size: 23px;
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
            <a href="index.php" title="Go to Needify Home">Needify</a>
        </div>

        <div class="admin-label">
            🛡️ Admin Panel
        </div>

        <ul class="sidebar-menu">

            <li>
                <a href="admin-dashboard.php" class="active-link">
                    🏠 Dashboard
                </a>
            </li>

            <li>
                <a href="admin-user.php">👤 Users</a>
            </li>

            <li>
                <a href="admin-needs.php">📋 Needs</a>
            </li>

            <li>
                <a href="admin-messages.php">💬 Messages</a>
            </li>

            <li>
                <a href="admin-orders.php">🛒 Orders</a>
            </li>

            <li>
                <a href="admin-category.php">🗂️ Categories</a>
            </li>

            <li>
                <a href="admin-products.php">📦 Products</a>
            </li>

            <li>
                <a href="admin-reports.php">📊 Reports & Analytics</a>
            </li>

            <li>
                <a href="admin-management.php">🛡️ Admin Management</a>
            </li>

            <li>
                <a href="admin-register.php">➕ Add New Admin</a>
            </li>

            <li>
                <a href="admin-logout.php" class="logout-link">🚪 Logout</a>
            </li>

        </ul>

    </aside>

    <!-- MAIN CONTENT -->

    <main class="main">

        <!-- DISPLAY SETTINGS -->

        <div class="site-tools" aria-label="Display settings">

            <button type="button" id="fontDecrease"
                    title="Decrease font size" aria-label="Decrease font size">
                <i class="fa-solid fa-minus"></i>
            </button>

            <button type="button" id="fontReset"
                    title="Reset font size" aria-label="Reset font size">
                <i class="fa-solid fa-rotate-left"></i>
            </button>

            <button type="button" id="fontIncrease"
                    title="Increase font size" aria-label="Increase font size">
                <i class="fa-solid fa-plus"></i>
            </button>

            <button type="button" id="themeToggle"
                    title="Switch to dark mode" aria-label="Switch to dark mode">
                <i class="fa-solid fa-moon"></i>
            </button>

        </div>

        <!-- TOPBAR -->

        <div class="topbar">

            <h1>Admin Dashboard</h1>

            <p>
                Welcome back,
                <strong><?php echo htmlspecialchars($admin_username, ENT_QUOTES, "UTF-8"); ?></strong>
                👋
            </p>

        </div>

        <!-- STATISTICS -->

        <div class="stats">

            <div class="stat-card">
                <div class="stat-icon">👤</div>
                <h3>Total Users</h3>
                <div class="stat-number">
                    <?php echo htmlspecialchars((string)$total_users, ENT_QUOTES, "UTF-8"); ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <h3>Total Needs</h3>
                <div class="stat-number">
                    <?php echo htmlspecialchars((string)$total_needs, ENT_QUOTES, "UTF-8"); ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">💬</div>
                <h3>Total Messages</h3>
                <div class="stat-number">
                    <?php echo htmlspecialchars((string)$total_messages, ENT_QUOTES, "UTF-8"); ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">🛒</div>
                <h3>Total Orders</h3>
                <div class="stat-number">
                    <?php echo htmlspecialchars((string)$total_orders, ENT_QUOTES, "UTF-8"); ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <h3>Total Products</h3>
                <div class="stat-number">
                    <?php echo htmlspecialchars((string)$total_products, ENT_QUOTES, "UTF-8"); ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">🛡️</div>
                <h3>Total Admins</h3>
                <div class="stat-number">
                    <?php echo htmlspecialchars((string)$total_admins, ENT_QUOTES, "UTF-8"); ?>/5
                </div>
            </div>

        </div>

        <!-- RECENT USERS -->

        <div class="section">

            <div class="section-header">
                <h2>👥 Recent Users</h2>
                <a href="admin-user.php" class="view-all">View All →</a>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Registered</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if ($recent_users && $recent_users->num_rows > 0): ?>

                            <?php while ($user = $recent_users->fetch_assoc()): ?>

                                <tr>
                                    <td><?php echo htmlspecialchars((string)$user["id"], ENT_QUOTES, "UTF-8"); ?></td>
                                    <td><?php echo htmlspecialchars($user["full_name"], ENT_QUOTES, "UTF-8"); ?></td>
                                    <td class="username">@<?php echo htmlspecialchars($user["username"], ENT_QUOTES, "UTF-8"); ?></td>
                                    <td><?php echo htmlspecialchars($user["email"], ENT_QUOTES, "UTF-8"); ?></td>
                                    <td><?php echo htmlspecialchars($user["created_at"], ENT_QUOTES, "UTF-8"); ?></td>
                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5" class="empty">No users found.</td>
                            </tr>

                        <?php endif; ?>

                    </tbody>
                </table>
            </div>

        </div>

        <!-- RECENT NEEDS -->

        <div class="section">

            <div class="section-header">
                <h2>📋 Recent Needs</h2>
                <a href="admin-needs.php" class="view-all">View All →</a>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Posted By</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if ($recent_needs && $recent_needs->num_rows > 0): ?>

                            <?php while ($need = $recent_needs->fetch_assoc()): ?>

                                <tr>
                                    <td><?php echo htmlspecialchars((string)$need["id"], ENT_QUOTES, "UTF-8"); ?></td>
                                    <td><?php echo htmlspecialchars($need["title"], ENT_QUOTES, "UTF-8"); ?></td>
                                    <td>
                                        <span class="category">
                                            <?php echo htmlspecialchars($need["category"], ENT_QUOTES, "UTF-8"); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($need["full_name"] ?? "Unknown", ENT_QUOTES, "UTF-8"); ?></td>
                                    <td><?php echo htmlspecialchars($need["created_at"], ENT_QUOTES, "UTF-8"); ?></td>
                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5" class="empty">No needs found.</td>
                            </tr>

                        <?php endif; ?>

                    </tbody>
                </table>
            </div>

        </div>

        <!-- RECENT MESSAGES -->

        <div class="section">

            <div class="section-header">
                <h2>💬 Recent Messages</h2>
                <a href="admin-messages.php" class="view-all">View All →</a>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Sender</th>
                            <th>Receiver</th>
                            <th>Message</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if ($recent_messages && $recent_messages->num_rows > 0): ?>

                            <?php while ($message = $recent_messages->fetch_assoc()): ?>

                                <tr>
                                    <td><?php echo htmlspecialchars((string)$message["id"], ENT_QUOTES, "UTF-8"); ?></td>
                                    <td><?php echo htmlspecialchars($message["sender_name"] ?? "Unknown", ENT_QUOTES, "UTF-8"); ?></td>
                                    <td><?php echo htmlspecialchars($message["receiver_name"] ?? "Unknown", ENT_QUOTES, "UTF-8"); ?></td>
                                    <td>
                                        <div class="message-preview">
                                            <?php echo htmlspecialchars($message["message"], ENT_QUOTES, "UTF-8"); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($message["created_at"], ENT_QUOTES, "UTF-8"); ?></td>
                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5" class="empty">No messages found.</td>
                            </tr>

                        <?php endif; ?>

                    </tbody>
                </table>
            </div>

        </div>

        <!-- FOOTER -->

        <footer class="admin-footer">

            <p>
                &copy; <?php echo date("Y"); ?>
                <strong>Needify</strong>.
                All Rights Reserved.
            </p>

            <p style="margin-top: 8px;">
                Admin Dashboard |
                <a href="index.php">Visit Website</a>
            </p>

        </footer>

    </main>

    <!-- PAGE UP / DOWN BUTTONS -->

    <div class="page-controls">

        <button type="button" id="pageUp"
                title="Page Up" aria-label="Page Up">
            <i class="fa-solid fa-arrow-up"></i>
        </button>

        <button type="button" id="pageDown"
                title="Page Down" aria-label="Page Down">
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

            // KEYBOARD PAGE UP / PAGE DOWN

            document.addEventListener("keydown", function (event) {

                if (event.key === "PageUp") {
                    window.scrollBy({
                        top: -window.innerHeight * 0.8,
                        behavior: "smooth"
                    });
                }

                if (event.key === "PageDown") {
                    window.scrollBy({
                        top: window.innerHeight * 0.8,
                        behavior: "smooth"
                    });
                }

            });

        })();
    </script>

</body>
</html>
