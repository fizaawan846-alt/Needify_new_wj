
<?php
require_once __DIR__ . "/config/session.php";
require_once __DIR__ . "/config/database.php";

// PREVENT CACHED ADMIN PAGE
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ADMIN ACCESS CHECK + 100 SECONDS INACTIVITY TIMEOUT
$timeout_duration = 100;

if (!isset($_SESSION["admin_id"])) {
    header("Location: admin-login.php");
    exit;
}

if (
    !isset($_SESSION["admin_last_activity"]) ||
    (time() - (int) $_SESSION["admin_last_activity"]) >= $timeout_duration
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

$admin_username = $_SESSION["admin_username"] ?? "Admin";

// DEDICATED CSRF TOKEN
if (empty($_SESSION["admin_orders_csrf"])) {
    $_SESSION["admin_orders_csrf"] = bin2hex(random_bytes(32));
}

// ALLOWED ORDER STATUS TRANSITIONS
$allowed_transitions = [
    "placed" => ["processing", "cancelled"],
    "processing" => ["shipped", "cancelled"],
    "shipped" => ["delivered"],
    "delivered" => [],
    "cancelled" => []
];

$status_labels = [
    "placed" => "Placed",
    "processing" => "Processing",
    "shipped" => "Shipped",
    "delivered" => "Delivered",
    "cancelled" => "Cancelled"
];

// FLASH MESSAGE
$flash = $_SESSION["admin_orders_flash"] ?? null;
unset($_SESSION["admin_orders_flash"]);

// =====================================================
// UPDATE ORDER STATUS
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "update_order_status"
) {

    $csrf = $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrf) ||
        !hash_equals($_SESSION["admin_orders_csrf"], $csrf)
    ) {
        http_response_code(403);
        exit("Invalid request. Please refresh the page and try again.");
    }

    $order_id = filter_var(
        $_POST["order_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $new_status = $_POST["new_status"] ?? "";

    if (
        $order_id === false ||
        $order_id === null ||
        $order_id < 1 ||
        !is_string($new_status) ||
        !array_key_exists($new_status, $status_labels)
    ) {

        $_SESSION["admin_orders_flash"] = [
            "type" => "error",
            "message" => "Invalid order or status."
        ];

    } else {

        try {

            $conn->begin_transaction();

            // LOCK ORDER ROW
            $order_lock = $conn->prepare(
                "SELECT id, status
                 FROM orders
                 WHERE id = ?
                 FOR UPDATE"
            );

            if (!$order_lock) {
                throw new Exception("Unable to access order.");
            }

            $order_lock->bind_param("i", $order_id);

            if (!$order_lock->execute()) {
                throw new Exception("Unable to access order.");
            }

            $order_result = $order_lock->get_result();
            $order = $order_result->fetch_assoc();

            $order_lock->close();

            if (!$order) {
                throw new Exception("Order not found.");
            }

            $current_status = strtolower($order["status"]);

            // CHECK VALID NEXT STATUS
            $next_statuses = $allowed_transitions[$current_status] ?? [];

            if ($new_status === $current_status) {
                throw new Exception("This order already has that status.");
            }

            if (!in_array($new_status, $next_statuses, true)) {
                throw new Exception(
                    "This status transition is not allowed."
                );
            }

            // RESTORE STOCK WHEN AN ORDER IS CANCELLED
            if ($new_status === "cancelled") {

                $items_stmt = $conn->prepare(
                    "SELECT product_id, quantity
                     FROM order_items
                     WHERE order_id = ?
                     FOR UPDATE"
                );

                if (!$items_stmt) {
                    throw new Exception("Unable to retrieve order items.");
                }

                $items_stmt->bind_param("i", $order_id);

                if (!$items_stmt->execute()) {
                    throw new Exception("Unable to retrieve order items.");
                }

                $items_result = $items_stmt->get_result();

                $stock_stmt = $conn->prepare(
                    "UPDATE products
                     SET stock = stock + ?
                     WHERE id = ?"
                );

                if (!$stock_stmt) {
                    throw new Exception("Unable to restore product stock.");
                }

                while ($order_item = $items_result->fetch_assoc()) {

                    $product_id = (int) ($order_item["product_id"] ?? 0);
                    $quantity = (int) $order_item["quantity"];

                    if ($product_id < 1 || $quantity < 1) {
                        continue;
                    }

                    $stock_stmt->bind_param(
                        "ii",
                        $quantity,
                        $product_id
                    );

                    if (!$stock_stmt->execute()) {
                        throw new Exception("Unable to restore product stock.");
                    }
                }

                $stock_stmt->close();
                $items_stmt->close();
            }

            // UPDATE ORDER STATUS AND CANCELLATION DATE
            $cancelled_at = (
                $new_status === "cancelled"
            ) ? date("Y-m-d H:i:s") : null;

            $update_stmt = $conn->prepare(
                "UPDATE orders
                 SET status = ?, cancelled_at = ?
                 WHERE id = ?"
            );

            if (!$update_stmt) {
                throw new Exception("Unable to update order status.");
            }

            $update_stmt->bind_param(
                "ssi",
                $new_status,
                $cancelled_at,
                $order_id
            );

            if (!$update_stmt->execute()) {
                throw new Exception("Unable to update order status.");
            }

            $update_stmt->close();

            $conn->commit();

            $_SESSION["admin_orders_flash"] = [
                "type" => "success",
                "message" => "Order #" . $order_id .
                    " updated to " .
                    $status_labels[$new_status] . " successfully."
            ];

        } catch (Throwable $e) {

            $conn->rollback();

            error_log(
                "Needify order status error: " . $e->getMessage()
            );

            $_SESSION["admin_orders_flash"] = [
                "type" => "error",
                "message" => $e instanceof Exception
                    ? $e->getMessage()
                    : "Unable to update order. Please try again."
            ];
        }
    }

    // REDIRECT BACK TO SAME FILTERED PAGE
    $return_params = $_GET;

    $return_url = "admin-orders.php";

    if (!empty($return_params)) {
        $return_url .= "?" . http_build_query($return_params);
    }

    header("Location: " . $return_url);
    exit;
}

// =====================================================
// SEARCH AND FILTER
// =====================================================

$search = trim($_GET["q"] ?? "");
$status_filter = $_GET["status"] ?? "";

if (!is_string($search)) {
    $search = "";
}

if (!is_string($status_filter)) {
    $status_filter = "";
}

$valid_statuses = [
    "",
    "placed",
    "processing",
    "shipped",
    "delivered",
    "cancelled"
];

if (!in_array($status_filter, $valid_statuses, true)) {
    $status_filter = "";
}

// SORTING
$allowed_sort = [
    "id" => "o.id",
    "amount" => "o.total_amount",
    "date" => "o.created_at",
    "status" => "o.status"
];

$sort = $_GET["sort"] ?? "id";
$direction = strtoupper($_GET["direction"] ?? "DESC");

if (!is_string($sort) || !isset($allowed_sort[$sort])) {
    $sort = "id";
}

if (!is_string($direction) || !in_array($direction, ["ASC", "DESC"], true)) {
    $direction = "DESC";
}

$order_by = $allowed_sort[$sort];

// PAGINATION
$per_page = 10;
$page = max(1, (int) ($_GET["page"] ?? 1));

$search_term = "%" . $search . "%";

// SEARCH CONDITION
$where_sql = "
    (? = '' OR o.status = ?)
    AND (
        CAST(o.id AS CHAR) LIKE ?
        OR u.full_name LIKE ?
        OR u.username LIKE ?
        OR u.email LIKE ?
        OR EXISTS (
            SELECT 1
            FROM order_items oi2
            LEFT JOIN products p2 ON oi2.product_id = p2.id
            WHERE oi2.order_id = o.id
            AND p2.name LIKE ?
        )
    )
";

// COUNT ORDERS
$count_sql = "
    SELECT COUNT(*) AS total
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE $where_sql
";

$count_stmt = $conn->prepare($count_sql);

if (!$count_stmt) {
    error_log("Needify order count error: " . $conn->error);
    exit("Unable to load orders. Please try again.");
}

$count_stmt->bind_param(
    "sssssss",
    $status_filter,
    $status_filter,
    $search_term,
    $search_term,
    $search_term,
    $search_term,
    $search_term
);

if (!$count_stmt->execute()) {
    error_log("Needify order count execution error: " . $count_stmt->error);
    exit("Unable to load orders. Please try again.");
}

$count_result = $count_stmt->get_result();

$total_orders = (int) (
    $count_result->fetch_assoc()["total"] ?? 0
);

$count_stmt->close();

$total_pages = max(
    1,
    (int) ceil($total_orders / $per_page)
);

$page = min($page, $total_pages);

$offset = ($page - 1) * $per_page;

// FETCH ORDERS AND ITEMS
$sql = "
    SELECT
        o.id,
        o.user_id,
        o.total_amount,
        o.status,
        o.created_at,
        o.cancelled_at,
        u.full_name,
        u.username,
        u.email,
        COALESCE(item_summary.items, '') AS items

    FROM orders o

    LEFT JOIN users u
        ON o.user_id = u.id

    LEFT JOIN (
        SELECT
            oi.order_id,
            GROUP_CONCAT(
                CONCAT(
                    COALESCE(p.name, 'Deleted Product'),
                    ' (x',
                    oi.quantity,
                    ')'
                )
                SEPARATOR ', '
            ) AS items
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        GROUP BY oi.order_id
    ) AS item_summary
        ON item_summary.order_id = o.id

    WHERE $where_sql

    ORDER BY $order_by $direction

    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Needify orders query error: " . $conn->error);
    exit("Unable to load orders. Please try again.");
}

$stmt->bind_param(
    "sssssssii",
    $status_filter,
    $status_filter,
    $search_term,
    $search_term,
    $search_term,
    $search_term,
    $search_term,
    $per_page,
    $offset
);

if (!$stmt->execute()) {
    error_log("Needify orders query execution error: " . $stmt->error);
    exit("Unable to load orders. Please try again.");
}

$orders_result = $stmt->get_result();

// ESCAPE OUTPUT
function orderEscape($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// PAGINATION URL
function orderPageUrl(
    $pageNumber,
    $search,
    $status,
    $sort,
    $direction
) {
    return "admin-orders.php?" . http_build_query([
        "page" => $pageNumber,
        "q" => $search,
        "status" => $status,
        "sort" => $sort,
        "direction" => $direction
    ]);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Needify | Manage Orders</title>

    <link rel="icon" type="image/png" href="favicon.png">

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>

        :root {
            --primary: #7956C8;
            --primary-dark: #6844B5;
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
            transition: 0.3s;
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
            --shadow: rgba(0, 0, 0, 0.20);
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            overflow-y: auto;
            background: linear-gradient(180deg, #7956C8, #6844B5);
            color: white;
            padding: 25px 18px;
            z-index: 100;
        }

        .logo {
            text-align: center;
            font-size: 30px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .logo a {
            color: white;
            text-decoration: none;
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
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-size: 15px;
            transition: 0.3s;
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

        .main {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }

        .site-tools {
            display: flex;
            justify-content: flex-end;
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
        }

        .topbar {
            background: var(--white);
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 5px 20px var(--shadow);
            margin-bottom: 25px;
            border: 1px solid var(--border);
        }

        .topbar h1 {
            color: var(--primary);
            font-size: 28px;
            margin-bottom: 8px;
        }

        .topbar p {
            color: var(--gray);
        }

        .topbar strong {
            color: var(--primary);
        }

        .content-box {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 5px 20px var(--shadow);
            overflow: hidden;
        }

        .section-header {
            padding: 20px 25px;
            background: var(--light-pink);
            border-bottom: 1px solid var(--border);
        }

        .section-header h2 {
            font-size: 20px;
        }

        .section-header p {
            margin-top: 7px;
            color: var(--gray);
            font-size: 14px;
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 20px 25px;
            border-bottom: 1px solid var(--border);
        }

        .filter-form input,
        .filter-form select {
            padding: 11px 13px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--white);
            color: var(--dark);
            font-size: 14px;
            outline: none;
        }

        .filter-form input {
            flex: 1;
            min-width: 180px;
        }

        .filter-form button,
        .reset-btn {
            padding: 11px 17px;
            border: none;
            border-radius: 8px;
            background: var(--primary);
            color: white;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        .reset-btn {
            background: #746B83;
        }

        .summary {
            padding: 15px 25px;
            color: var(--gray);
            font-size: 14px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1150px;
        }

        th {
            background: var(--table-heading);
            color: var(--dark);
            padding: 15px;
            text-align: left;
            font-size: 13px;
            white-space: nowrap;
        }

        td {
            padding: 15px;
            border-top: 1px solid var(--border);
            font-size: 13px;
            vertical-align: middle;
            color: var(--dark);
        }

        tr:hover td {
            background: var(--hover-bg);
        }

        .order-id {
            color: var(--primary);
            font-weight: bold;
        }

        .customer-name {
            font-weight: bold;
        }

        .customer-email {
            color: var(--gray);
            margin-top: 5px;
            word-break: break-word;
        }

        .items {
            min-width: 170px;
            max-width: 260px;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .status {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .placed {
            background: #DDF5E7;
            color: #247746;
        }

        .processing {
            background: #E5E0FF;
            color: #6146AE;
        }

        .shipped {
            background: #DDEFFF;
            color: #245E9B;
        }

        .delivered {
            background: #D5F5E4;
            color: #176C3D;
        }

        .cancelled {
            background: #FFE0E0;
            color: #B52B2B;
        }

        .status-form {
            min-width: 185px;
        }

        .status-form select {
            width: 100%;
            padding: 9px;
            margin-bottom: 8px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--white);
            color: var(--dark);
            font-size: 12px;
            outline: none;
        }

        .update-btn {
            width: 100%;
            padding: 9px 12px;
            border: none;
            border-radius: 8px;
            background: var(--primary);
            color: white;
            font-weight: bold;
            font-size: 12px;
            cursor: pointer;
            transition: 0.2s;
        }

        .update-btn:hover {
            background: var(--primary-dark);
        }

        .no-update {
            color: var(--gray);
            font-size: 12px;
            line-height: 1.6;
        }

        .empty {
            padding: 35px;
            text-align: center;
            color: var(--gray);
        }

        .alert {
            padding: 14px 18px;
            margin: 20px 25px 0;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
        }

        .alert.success {
            background: #DDF5E7;
            color: #247746;
            border: 1px solid #B7E5C9;
        }

        .alert.error {
            background: #FFE0E0;
            color: #B52B2B;
            border: 1px solid #F5C2C2;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            padding: 22px;
        }

        .pagination a,
        .pagination span {
            padding: 9px 13px;
            border: 1px solid var(--border);
            border-radius: 7px;
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
        }

        .pagination .current {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination .disabled {
            opacity: 0.4;
        }

        .admin-footer {
            margin-top: 35px;
            padding: 22px;
            text-align: center;
            background: var(--white);
            color: var(--gray);
            border: 1px solid var(--border);
            border-radius: 14px;
            font-size: 14px;
        }

        .admin-footer strong,
        .admin-footer a {
            color: var(--primary);
        }

        .admin-footer a {
            text-decoration: none;
            font-weight: bold;
        }

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
            color: white;
            cursor: pointer;
            font-size: 17px;
        }

        @media(max-width: 900px) {
            .sidebar {
                width: 210px;
            }

            .main {
                margin-left: 210px;
            }
        }

        @media(max-width: 650px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .main {
                margin-left: 0;
                padding: 20px;
            }

            .filter-form {
                flex-direction: column;
            }

            .filter-form input,
            .filter-form select {
                width: 100%;
            }

            .topbar h1 {
                font-size: 23px;
            }
        }

    </style>

</head>

<body>

<aside class="sidebar">

    <div class="logo">
        <a href="index.php">Needify</a>
    </div>

    <div class="admin-label">🛡️ Admin Panel</div>

    <ul class="sidebar-menu">

        <li><a href="admin-dashboard.php">🏠 Dashboard</a></li>
        <li><a href="admin-user.php">👤 Users</a></li>
        <li><a href="admin-needs.php">📋 Needs</a></li>
        <li><a href="admin-messages.php">💬 Messages</a></li>
        <li><a href="admin-orders.php" class="active-link">🛒 Orders</a></li>
        <li><a href="admin-category.php">🗂️ Categories</a></li>
        <li><a href="admin-products.php">📦 Products</a></li>
        <li><a href="admin-management.php">🛡️ Admin Management</a></li>
        <li><a href="admin-register.php">➕ Add New Admin</a></li>
        <li><a href="admin-logout.php" class="logout-link">🚪 Logout</a></li>

    </ul>

</aside>

<main class="main">

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

        <button type="button" id="themeToggle" title="Switch theme">
            <i class="fa-solid fa-moon"></i>
        </button>

    </div>

    <div class="topbar">

        <h1>🛒 Manage Orders</h1>

        <p>
            Welcome back,
            <strong><?php echo orderEscape($admin_username); ?></strong>
            👋
        </p>

    </div>

    <div class="content-box">

        <div class="section-header">

            <h2>All Orders</h2>

            <p>
                View customer orders, products, amounts and update order status.
            </p>

        </div>

        <?php if (is_array($flash)): ?>

            <div class="alert <?php echo ($flash["type"] ?? "") === "success" ? "success" : "error"; ?>">

                <?php echo orderEscape($flash["message"] ?? ""); ?>

            </div>

        <?php endif; ?>

        <form method="GET" action="admin-orders.php" class="filter-form">

            <input
                type="search"
                name="q"
                placeholder="Search order ID, customer or product..."
                value="<?php echo orderEscape($search); ?>"
            >

            <select name="status">

                <option value="">All Statuses</option>

                <?php foreach ($status_labels as $key => $label): ?>

                    <option
                        value="<?php echo orderEscape($key); ?>"
                        <?php echo $status_filter === $key ? "selected" : ""; ?>
                    >
                        <?php echo orderEscape($label); ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <select name="sort">

                <option value="id" <?php echo $sort === "id" ? "selected" : ""; ?>>
                    Sort by Order ID
                </option>

                <option value="amount" <?php echo $sort === "amount" ? "selected" : ""; ?>>
                    Sort by Amount
                </option>

                <option value="date" <?php echo $sort === "date" ? "selected" : ""; ?>>
                    Sort by Date
                </option>

                <option value="status" <?php echo $sort === "status" ? "selected" : ""; ?>>
                    Sort by Status
                </option>

            </select>

            <select name="direction">

                <option value="DESC" <?php echo $direction === "DESC" ? "selected" : ""; ?>>
                    Descending
                </option>

                <option value="ASC" <?php echo $direction === "ASC" ? "selected" : ""; ?>>
                    Ascending
                </option>

            </select>

            <button type="submit">
                <i class="fa-solid fa-search"></i> Search
            </button>

            <a href="admin-orders.php" class="reset-btn">
                Reset
            </a>

        </form>

        <div class="summary">

            Total matching orders:
            <strong><?php echo $total_orders; ?></strong>

        </div>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Products / Quantity</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Order Date</th>
                        <th>Cancelled At</th>
                        <th>Update Status</th>
                    </tr>

                </thead>

                <tbody>

                <?php if ($orders_result && $orders_result->num_rows > 0): ?>

                    <?php while ($order = $orders_result->fetch_assoc()): ?>

                        <?php

                        $order_status = strtolower(
                            (string) $order["status"]
                        );

                        $status_class = isset($status_labels[$order_status])
                            ? $order_status
                            : "placed";

                        $next_statuses =
                            $allowed_transitions[$order_status] ?? [];

                        ?>

                        <tr>

                            <td class="order-id">
                                #<?php echo orderEscape($order["id"]); ?>
                            </td>

                            <td>

                                <div class="customer-name">
                                    <?php
                                    echo orderEscape(
                                        $order["full_name"] ?? "Unknown Customer"
                                    );
                                    ?>
                                </div>

                                <div class="customer-email">

                                    @<?php
                                    echo orderEscape(
                                        $order["username"] ?? "unknown"
                                    );
                                    ?>

                                    <br>

                                    <?php
                                    echo orderEscape(
                                        $order["email"] ?? "No email"
                                    );
                                    ?>

                                </div>

                            </td>

                            <td>

                                <div class="items">
                                    <?php
                                    echo orderEscape(
                                        $order["items"] ?: "No order items found"
                                    );
                                    ?>
                                </div>

                            </td>

                            <td>
                                <strong>
                                    Rs.
                                    <?php
                                    echo number_format(
                                        (float) $order["total_amount"],
                                        2
                                    );
                                    ?>
                                </strong>
                            </td>

                            <td>

                                <span class="status <?php echo orderEscape($status_class); ?>">

                                    <?php
                                    echo orderEscape(
                                        $status_labels[$order_status]
                                        ?? ucfirst($order_status)
                                    );
                                    ?>

                                </span>

                            </td>

                            <td>
                                <?php echo orderEscape($order["created_at"]); ?>
                            </td>

                            <td>

                                <?php
                                echo !empty($order["cancelled_at"])
                                    ? orderEscape($order["cancelled_at"])
                                    : "—";
                                ?>

                            </td>

                            <td>

                                <?php if (!empty($next_statuses)): ?>

                                    <form
                                        method="POST"
                                        action="admin-orders.php?<?php
                                            echo orderEscape(http_build_query($_GET));
                                        ?>"
                                        class="status-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="update_order_status"
                                        >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php
                                                echo orderEscape(
                                                    $_SESSION["admin_orders_csrf"]
                                                );
                                            ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="order_id"
                                            value="<?php
                                                echo (int) $order["id"];
                                            ?>"
                                        >

                                        <select name="new_status" required>

                                            <?php foreach ($next_statuses as $next_status): ?>

                                                <option value="<?php echo orderEscape($next_status); ?>">

                                                    <?php
                                                    echo orderEscape(
                                                        $status_labels[$next_status]
                                                    );
                                                    ?>

                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                        <button
                                            type="submit"
                                            class="update-btn"
                                            onclick="return confirm('Are you sure you want to update this order status?');"
                                        >

                                            <i class="fa-solid fa-rotate"></i>
                                            Update Status

                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span class="no-update">

                                        <?php
                                        echo $order_status === "delivered"
                                            ? "Order completed."
                                            : (
                                                $order_status === "cancelled"
                                                ? "Order cancelled."
                                                : "No further updates."
                                            );
                                        ?>

                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="8" class="empty">

                            <i class="fa-solid fa-box-open"></i>

                            <br><br>

                            No orders found.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <?php if ($total_pages > 1): ?>

            <div class="pagination">

                <?php if ($page > 1): ?>

                    <a href="<?php
                        echo orderEscape(orderPageUrl(
                            $page - 1,
                            $search,
                            $status_filter,
                            $sort,
                            $direction
                        ));
                    ?>">
                        Previous
                    </a>

                <?php else: ?>

                    <span class="disabled">Previous</span>

                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>

                    <?php if ($i === $page): ?>

                        <span class="current">
                            <?php echo $i; ?>
                        </span>

                    <?php else: ?>

                        <a href="<?php
                            echo orderEscape(orderPageUrl(
                                $i,
                                $search,
                                $status_filter,
                                $sort,
                                $direction
                            ));
                        ?>">
                            <?php echo $i; ?>
                        </a>

                    <?php endif; ?>

                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>

                    <a href="<?php
                        echo orderEscape(orderPageUrl(
                            $page + 1,
                            $search,
                            $status_filter,
                            $sort,
                            $direction
                        ));
                    ?>">
                        Next
                    </a>

                <?php else: ?>

                    <span class="disabled">Next</span>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>

    <footer class="admin-footer">

        <p>
            &copy; <?php echo date("Y"); ?>

            <strong>Needify</strong>. All Rights Reserved.
        </p>

        <p style="margin-top: 8px;">

            Admin Orders |

            <a href="index.php">Visit Website</a>

        </p>

    </footer>

</main>

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

    // THEME SETTINGS

    function applyTheme(theme) {

        body.classList.toggle(
            "dark-mode",
            theme === "dark"
        );

        themeToggle.innerHTML = theme === "dark"
            ? '<i class="fa-solid fa-sun"></i>'
            : '<i class="fa-solid fa-moon"></i>';

        themeToggle.title = theme === "dark"
            ? "Switch to light mode"
            : "Switch to dark mode";

    }

    applyTheme(
        localStorage.getItem("needifyTheme") || "light"
    );

    themeToggle.addEventListener("click", function () {

        const nextTheme =
            body.classList.contains("dark-mode")
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

    document.getElementById("pageUp").addEventListener("click", function () {

        window.scrollTo({
            top: 0,
            behavior: "smooth"
        });

    });

    // PAGE DOWN

    document.getElementById("pageDown").addEventListener("click", function () {

        window.scrollTo({
            top: document.documentElement.scrollHeight,
            behavior: "smooth"
        });

    });

    // KEYBOARD PAGE UP / DOWN

    document.addEventListener("keydown", function (event) {

        if (
            event.target.tagName === "INPUT" ||
            event.target.tagName === "SELECT" ||
            event.target.tagName === "TEXTAREA"
        ) {
            return;
        }

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

<?php
$stmt->close();
?>
