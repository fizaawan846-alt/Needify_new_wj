
<?php
/*
|--------------------------------------------------------------------------
| NEEDIFY - ADMIN REPORTS & ANALYTICS
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/config/session.php";
require_once __DIR__ . "/config/database.php";

/*
|--------------------------------------------------------------------------
| ADMIN ACCESS CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["admin_id"])) {
    header("Location: admin-login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| 100 SECONDS INACTIVITY TIMEOUT
|--------------------------------------------------------------------------
*/

$timeout_duration = 100;

if (
    isset($_SESSION["admin_last_activity"]) &&
    (time() - (int)$_SESSION["admin_last_activity"]) >= $timeout_duration
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

/*
|--------------------------------------------------------------------------
| NO CACHE
|--------------------------------------------------------------------------
*/

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

/*
|--------------------------------------------------------------------------
| HELPER FUNCTION
|--------------------------------------------------------------------------
*/

function reportEscape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

/*
|--------------------------------------------------------------------------
| SELECT REPORT YEAR
|--------------------------------------------------------------------------
*/

$current_year = (int)date("Y");

$selected_year = isset($_GET["year"])
    ? (int)$_GET["year"]
    : $current_year;

if ($selected_year < 2000 || $selected_year > $current_year) {
    $selected_year = $current_year;
}

/*
|--------------------------------------------------------------------------
| YEARLY ORDER AND SALES SUMMARY
|--------------------------------------------------------------------------
*/

$summary_sql = "
    SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(
            CASE WHEN status = 'placed'
            THEN total_amount ELSE 0 END
        ), 0) AS total_sales,
        COALESCE(SUM(
            CASE WHEN status = 'placed'
            THEN 1 ELSE 0 END
        ), 0) AS placed_orders,
        COALESCE(SUM(
            CASE WHEN status = 'cancelled'
            THEN 1 ELSE 0 END
        ), 0) AS cancelled_orders
    FROM orders
    WHERE YEAR(created_at) = ?
";

$summary_stmt = $conn->prepare($summary_sql);

if (!$summary_stmt) {
    die("Unable to load report summary.");
}

$summary_stmt->bind_param("i", $selected_year);
$summary_stmt->execute();

$summary_result = $summary_stmt->get_result();
$summary = $summary_result->fetch_assoc();

$summary_stmt->close();

$total_orders = (int)($summary["total_orders"] ?? 0);
$total_sales = (float)($summary["total_sales"] ?? 0);
$placed_orders = (int)($summary["placed_orders"] ?? 0);
$cancelled_orders = (int)($summary["cancelled_orders"] ?? 0);

/*
|--------------------------------------------------------------------------
| TOTAL REGISTERED USERS
|--------------------------------------------------------------------------
*/

$user_result = $conn->query("
    SELECT COUNT(*) AS total_users
    FROM users
");

if (!$user_result) {
    die("Unable to load registered users report.");
}

$user_data = $user_result->fetch_assoc();
$total_users = (int)($user_data["total_users"] ?? 0);

/*
|--------------------------------------------------------------------------
| MONTHLY SALES
|--------------------------------------------------------------------------
*/

$months = [
    1 => "January",
    2 => "February",
    3 => "March",
    4 => "April",
    5 => "May",
    6 => "June",
    7 => "July",
    8 => "August",
    9 => "September",
    10 => "October",
    11 => "November",
    12 => "December"
];

$monthly_sales = array_fill(1, 12, 0);

$monthly_sql = "
    SELECT
        MONTH(created_at) AS month_number,
        COALESCE(SUM(total_amount), 0) AS monthly_total
    FROM orders
    WHERE status = 'placed'
      AND YEAR(created_at) = ?
    GROUP BY MONTH(created_at)
    ORDER BY MONTH(created_at)
";

$monthly_stmt = $conn->prepare($monthly_sql);

if (!$monthly_stmt) {
    die("Unable to load monthly sales report.");
}

$monthly_stmt->bind_param("i", $selected_year);
$monthly_stmt->execute();

$monthly_result = $monthly_stmt->get_result();

while ($row = $monthly_result->fetch_assoc()) {
    $month_number = (int)$row["month_number"];

    if ($month_number >= 1 && $month_number <= 12) {
        $monthly_sales[$month_number] = (float)$row["monthly_total"];
    }
}

$monthly_stmt->close();

$max_monthly_sales = max($monthly_sales);

if ($max_monthly_sales <= 0) {
    $max_monthly_sales = 1;
}

/*
|--------------------------------------------------------------------------
| BEST SELLING PRODUCTS
|--------------------------------------------------------------------------
*/

$products_sql = "
    SELECT
        p.id,
        p.name,
        COALESCE(SUM(oi.quantity), 0) AS total_quantity,
        COALESCE(SUM(oi.quantity * oi.unit_price), 0) AS total_revenue
    FROM order_items oi
    INNER JOIN orders o ON oi.order_id = o.id
    INNER JOIN products p ON oi.product_id = p.id
    WHERE o.status = 'placed'
      AND YEAR(o.created_at) = ?
    GROUP BY p.id, p.name
    ORDER BY total_quantity DESC
    LIMIT 5
";

$products_stmt = $conn->prepare($products_sql);

if (!$products_stmt) {
    die("Unable to load best-selling products report.");
}

$products_stmt->bind_param("i", $selected_year);
$products_stmt->execute();

$top_products = $products_stmt->get_result();
$products_stmt->close();

/*
|--------------------------------------------------------------------------
| MOST ACTIVE CUSTOMERS
|--------------------------------------------------------------------------
*/

$customers_sql = "
    SELECT
        u.id,
        u.full_name,
        u.username,
        COUNT(o.id) AS total_orders,
        COALESCE(SUM(o.total_amount), 0) AS total_spent
    FROM orders o
    INNER JOIN users u ON o.user_id = u.id
    WHERE o.status = 'placed'
      AND YEAR(o.created_at) = ?
    GROUP BY u.id, u.full_name, u.username
    ORDER BY total_orders DESC
    LIMIT 5
";

$customers_stmt = $conn->prepare($customers_sql);

if (!$customers_stmt) {
    die("Unable to load active customers report.");
}

$customers_stmt->bind_param("i", $selected_year);
$customers_stmt->execute();

$top_customers = $customers_stmt->get_result();
$customers_stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reports & Analytics | Needify Admin</title>

    <link rel="icon" type="image/png" href="favicon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>

        :root {
            --primary: #7956C8;
            --primary-dark: #6542B3;
            --pink: #FFE5EF;
            --cream: #FFFAF5;
            --white: #FFFFFF;
            --dark: #302747;
            --muted: #817991;
            --border: #EEE7F5;
            --soft-purple: #F1EBFC;
            --soft-pink: #FFF0F5;
            --success: #329B70;
            --danger: #D65D76;
            --shadow: 0 5px 22px rgba(72, 48, 105, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--cream);
            color: var(--dark);
            font-size: 15px;
            transition: background 0.3s, color 0.3s;
            min-height: 100vh;
        }

        /* DARK MODE */

        body.dark {
            --cream: #171421;
            --white: #252033;
            --dark: #F6F0FF;
            --muted: #B7ADC9;
            --border: #3B334D;
            --soft-purple: #342A49;
            --soft-pink: #3B293B;
            --shadow: 0 5px 22px rgba(0, 0, 0, 0.20);
        }

        /* SIDEBAR */

        .sidebar {
            width: 250px;
            min-height: 100vh;
            background: linear-gradient(180deg, #7956C8, #6041A8);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            padding: 28px 17px;
            z-index: 100;
            overflow-y: auto;
        }

        .brand {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 22px;
            border-bottom: 1px solid rgba(255,255,255,0.20);
        }

        .brand h2 {
            font-size: 23px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .brand p {
            font-size: 10px;
            margin-top: 5px;
            opacity: 0.85;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            text-decoration: none;
            padding: 12px 13px;
            border-radius: 10px;
            margin-bottom: 7px;
            font-size: 13px;
            transition: 0.25s;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: rgba(255,255,255,0.20);
            transform: translateX(3px);
        }

        .sidebar a.active {
            border-left: 3px solid #FFE5EF;
            font-weight: 600;
        }

        /* MAIN */

        .main {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }

        /* TOPBAR */

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 18px;
            margin-bottom: 28px;
        }

        .page-heading h1 {
            font-size: 27px;
            font-weight: 700;
            color: var(--dark);
        }

        .page-heading p {
            font-size: 13px;
            color: var(--muted);
            margin-top: 5px;
        }

        .page-heading span {
            color: var(--primary);
            font-weight: 600;
        }

        .controls {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        button,
        select {
            font-family: inherit;
        }

        .control-btn {
            padding: 9px 13px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--white);
            color: var(--dark);
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: 0.2s;
        }

        .control-btn:hover {
            background: var(--soft-purple);
            border-color: var(--primary);
            color: var(--primary);
        }

        /* YEAR FILTER */

        .year-filter {
            background: var(--white);
            padding: 20px;
            border-radius: 14px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .year-filter label {
            font-size: 13px;
            font-weight: 600;
        }

        .year-filter select {
            padding: 10px 14px;
            border: 1px solid var(--border);
            background: var(--cream);
            color: var(--dark);
            border-radius: 9px;
            outline: none;
            min-width: 120px;
        }

        .year-filter select:focus {
            border-color: var(--primary);
        }

        .primary-btn {
            padding: 10px 19px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 9px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: 0.2s;
        }

        .primary-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* STAT CARDS */

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 18px;
            margin-bottom: 27px;
        }

        .report-card {
            background: var(--white);
            padding: 23px;
            border-radius: 15px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
            transition: transform 0.25s;
        }

        .report-card:hover {
            transform: translateY(-4px);
        }

        .report-card::after {
            content: "";
            position: absolute;
            width: 75px;
            height: 75px;
            background: var(--pink);
            opacity: 0.65;
            border-radius: 50%;
            right: -22px;
            top: -25px;
        }

        .report-card h3 {
            font-size: 13px;
            color: var(--muted);
            font-weight: 500;
            margin-bottom: 12px;
            position: relative;
            z-index: 1;
        }

        .report-card p {
            font-size: 25px;
            font-weight: 700;
            color: var(--primary);
            position: relative;
            z-index: 1;
            overflow-wrap: anywhere;
        }

        .report-card:nth-child(2) {
            background: linear-gradient(135deg, var(--white), var(--soft-pink));
        }

        .report-card:nth-child(3) {
            background: linear-gradient(135deg, var(--white), var(--soft-purple));
        }

        /* REPORT SECTIONS */

        .report-section {
            background: var(--white);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .section-heading {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 23px;
            color: var(--dark);
        }

        .section-heading span {
            color: var(--primary);
        }

        /* MONTHLY SALES */

        .month-row {
            display: grid;
            grid-template-columns: 105px 1fr 145px;
            gap: 15px;
            align-items: center;
            margin: 18px 0;
            font-size: 13px;
        }

        .month-name {
            font-weight: 500;
            color: var(--dark);
        }

        .bar-background {
            height: 15px;
            background: var(--soft-purple);
            border-radius: 20px;
            overflow: hidden;
        }

        .bar {
            height: 100%;
            background: linear-gradient(90deg, #7956C8, #C18BEA, #F1A8C7);
            border-radius: 20px;
            min-width: 0;
            transition: width 0.5s ease;
        }

        .month-amount {
            text-align: right;
            font-size: 12px;
            color: var(--primary);
            font-weight: 600;
        }

        /* TABLES */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border-radius: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 550px;
        }

        th,
        td {
            padding: 14px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            font-size: 12px;
        }

        th {
            background: var(--soft-purple);
            color: var(--primary);
            font-weight: 700;
            white-space: nowrap;
        }

        td {
            color: var(--dark);
        }

        tbody tr {
            transition: background 0.2s;
        }

        tbody tr:hover {
            background: var(--soft-pink);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .empty {
            padding: 25px;
            text-align: center;
            color: var(--muted);
        }

        /* FOOTER */

        footer {
            text-align: center;
            padding: 22px 10px;
            margin-top: 25px;
            color: var(--muted);
            font-size: 12px;
            border-top: 1px solid var(--border);
        }

        footer strong {
            color: var(--primary);
        }

        /* CENTER TEXT */

        body.center-text {
            text-align: center;
        }

        body.center-text .topbar,
        body.center-text .controls,
        body.center-text .year-filter {
            justify-content: center;
        }

        body.center-text table th,
        body.center-text table td {
            text-align: center;
        }

        /* SCROLL BUTTONS */

        .scroll-controls {
            position: fixed;
            right: 22px;
            bottom: 22px;
            display: flex;
            flex-direction: column;
            gap: 9px;
            z-index: 200;
        }

        .scroll-btn {
            width: 42px;
            height: 42px;
            border: none;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            font-size: 18px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(70, 40, 120, 0.25);
            transition: 0.2s;
        }

        .scroll-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
        }

        /* RESPONSIVE */

        @media(max-width: 900px) {

            .sidebar {
                width: 100%;
                min-height: auto;
                position: relative;
                padding: 18px;
            }

            .brand {
                margin-bottom: 15px;
                padding-bottom: 15px;
            }

            .sidebar a {
                display: inline-flex;
                margin: 3px;
                padding: 9px 11px;
            }

            .main {
                margin-left: 0;
                padding: 20px;
            }
        }

        @media(max-width: 600px) {

            .main {
                padding: 14px;
            }

            .topbar {
                align-items: flex-start;
            }

            .page-heading h1 {
                font-size: 22px;
            }

            .controls {
                width: 100%;
            }

            .control-btn {
                flex: 1;
            }

            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }

            .report-card {
                padding: 16px;
            }

            .report-card p {
                font-size: 20px;
            }

            .report-section {
                padding: 17px;
            }

            .month-row {
                grid-template-columns: 75px 1fr;
                gap: 9px;
            }

            .month-amount {
                grid-column: 2;
                text-align: left;
            }

            .year-filter {
                align-items: stretch;
                flex-direction: column;
            }

            .year-filter select,
            .primary-btn {
                width: 100%;
            }
        }

    </style>

</head>

<body>

<!-- SIDEBAR -->

<aside class="sidebar">

    <div class="brand">
        <h2>Needify</h2>
        <p>SMART SHOPPING MADE SIMPLE</p>
    </div>

    <a href="admin-dashboard.php">▦ Dashboard</a>
    <a href="admin-user.php">♙ Users</a>
    <a href="admin-needs.php">♡ Needs</a>
    <a href="admin-messages.php">✉ Messages</a>
    <a href="admin-orders.php">▤ Orders</a>
    <a href="admin-category.php">▧ Categories</a>
    <a href="admin-products.php">□ Products</a>
    <a href="admin-reports.php" class="active">▥ Reports & Analytics</a>
    <a href="admin-management.php">♙ Admin Management</a>
    <a href="admin-register.php">＋ Register Admin</a>
    <a href="admin-logout.php">⇥ Logout</a>

</aside>

<!-- MAIN CONTENT -->

<main class="main">

    <!-- TOPBAR -->

    <div class="topbar">

        <div class="page-heading">
            <h1>Reports & Analytics</h1>

            <p>
                Welcome back,
                <span><?php echo reportEscape($admin_username); ?></span>
            </p>

            <p>Monitor your store performance and business growth.</p>
        </div>

        <div class="controls">

            <button type="button" class="control-btn" onclick="toggleTheme()">
                ◐ Theme
            </button>

            <button type="button" class="control-btn" onclick="changeFont(1)">
                A+
            </button>

            <button type="button" class="control-btn" onclick="changeFont(-1)">
                A-
            </button>

            <button type="button" class="control-btn" onclick="resetFont()">
                Reset
            </button>

            <button type="button" class="control-btn" onclick="toggleCenter()">
                Center Text
            </button>

        </div>

    </div>

    <!-- YEAR FILTER -->

    <form method="GET" class="year-filter">

        <label for="year">Select Report Year:</label>

        <select name="year" id="year">

            <?php for ($year = $current_year; $year >= $current_year - 5; $year--): ?>

                <option
                    value="<?php echo $year; ?>"
                    <?php echo $selected_year === $year ? "selected" : ""; ?>
                >
                    <?php echo $year; ?>
                </option>

            <?php endfor; ?>

        </select>

        <button type="submit" class="primary-btn">
            View Report
        </button>

    </form>

    <!-- STATISTICS -->

    <section class="stats">

        <div class="report-card">
            <h3>Total Orders</h3>
            <p><?php echo number_format($total_orders); ?></p>
        </div>

        <div class="report-card">
            <h3>Total Sales</h3>
            <p>Rs. <?php echo number_format($total_sales, 2); ?></p>
        </div>

        <div class="report-card">
            <h3>Placed Orders</h3>
            <p><?php echo number_format($placed_orders); ?></p>
        </div>

        <div class="report-card">
            <h3>Cancelled Orders</h3>
            <p><?php echo number_format($cancelled_orders); ?></p>
        </div>

        <div class="report-card">
            <h3>Registered Users</h3>
            <p><?php echo number_format($total_users); ?></p>
        </div>

    </section>

    <!-- MONTHLY SALES -->

    <section class="report-section">

        <h2 class="section-heading">
            Monthly Sales <span>- <?php echo $selected_year; ?></span>
        </h2>

        <?php foreach ($months as $number => $month_name): ?>

            <?php
                $amount = $monthly_sales[$number];
                $percentage = ($amount / $max_monthly_sales) * 100;
            ?>

            <div class="month-row">

                <span class="month-name">
                    <?php echo reportEscape($month_name); ?>
                </span>

                <div class="bar-background">

                    <div
                        class="bar"
                        style="width: <?php echo $percentage; ?>%;"
                    ></div>

                </div>

                <strong class="month-amount">
                    Rs. <?php echo number_format($amount, 2); ?>
                </strong>

            </div>

        <?php endforeach; ?>

    </section>

    <!-- BEST SELLING PRODUCTS -->

    <section class="report-section">

        <h2 class="section-heading">
            Top 5 <span>Best-Selling Products</span>
        </h2>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Product ID</th>
                        <th>Product Name</th>
                        <th>Quantity Sold</th>
                        <th>Revenue</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($top_products->num_rows > 0): ?>

                    <?php while ($product = $top_products->fetch_assoc()): ?>

                        <tr>
                            <td><?php echo (int)$product["id"]; ?></td>

                            <td>
                                <?php echo reportEscape($product["name"]); ?>
                            </td>

                            <td>
                                <?php echo number_format((int)$product["total_quantity"]); ?>
                            </td>

                            <td>
                                Rs. <?php echo number_format((float)$product["total_revenue"], 2); ?>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="4" class="empty">
                            No product sales found for this year.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <!-- ACTIVE CUSTOMERS -->

    <section class="report-section">

        <h2 class="section-heading">
            Top 5 <span>Active Customers</span>
        </h2>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Customer Name</th>
                        <th>Username</th>
                        <th>Orders</th>
                        <th>Total Spent</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($top_customers->num_rows > 0): ?>

                    <?php while ($customer = $top_customers->fetch_assoc()): ?>

                        <tr>
                            <td><?php echo (int)$customer["id"]; ?></td>

                            <td>
                                <?php echo reportEscape($customer["full_name"]); ?>
                            </td>

                            <td>
                                <?php echo reportEscape($customer["username"]); ?>
                            </td>

                            <td>
                                <?php echo number_format((int)$customer["total_orders"]); ?>
                            </td>

                            <td>
                                Rs. <?php echo number_format((float)$customer["total_spent"], 2); ?>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5" class="empty">
                            No customer orders found for this year.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <!-- FOOTER -->

    <footer>
        &copy; <?php echo date("Y"); ?>
        <strong>Needify</strong>. All Rights Reserved.
        <br>
        Smart Shopping Made Simple.
    </footer>

</main>

<!-- SCROLL BUTTONS -->

<div class="scroll-controls">

    <button
        type="button"
        class="scroll-btn"
        onclick="window.scrollTo({top: 0, behavior: 'smooth'})"
        title="Scroll to top"
    >
        ↑
    </button>

    <button
        type="button"
        class="scroll-btn"
        onclick="window.scrollTo({top: document.body.scrollHeight, behavior: 'smooth'})"
        title="Scroll to bottom"
    >
        ↓
    </button>

</div>

<script>

/*
|--------------------------------------------------------------------------
| DARK / LIGHT THEME
|--------------------------------------------------------------------------
*/

function toggleTheme() {

    document.body.classList.toggle("dark");

    const theme = document.body.classList.contains("dark")
        ? "dark"
        : "light";

    localStorage.setItem("needifyReportsTheme", theme);
}

/*
|--------------------------------------------------------------------------
| RESTORE THEME
|--------------------------------------------------------------------------
*/

(function() {

    const savedTheme = localStorage.getItem("needifyReportsTheme");

    if (savedTheme === "dark") {
        document.body.classList.add("dark");
    }

})();

/*
|--------------------------------------------------------------------------
| FONT SIZE CONTROLS
|--------------------------------------------------------------------------
*/

let currentFontSize = 15;

function changeFont(change) {

    currentFontSize += change;

    if (currentFontSize < 12) {
        currentFontSize = 12;
    }

    if (currentFontSize > 24) {
        currentFontSize = 24;
    }

    document.body.style.fontSize = currentFontSize + "px";

    localStorage.setItem("needifyReportsFont", currentFontSize);
}

function resetFont() {

    currentFontSize = 15;

    document.body.style.fontSize = "15px";

    localStorage.setItem("needifyReportsFont", 15);
}

/*
|--------------------------------------------------------------------------
| RESTORE FONT SIZE
|--------------------------------------------------------------------------
*/

(function() {

    const savedFont = localStorage.getItem("needifyReportsFont");

    if (savedFont !== null) {

        currentFontSize = Math.min(
            24,
            Math.max(12, parseInt(savedFont, 10) || 15)
        );

        document.body.style.fontSize = currentFontSize + "px";
    }

})();

/*
|--------------------------------------------------------------------------
| CENTER TEXT
|--------------------------------------------------------------------------
*/

function toggleCenter() {

    document.body.classList.toggle("center-text");

    localStorage.setItem(
        "needifyReportsCenter",
        document.body.classList.contains("center-text") ? "yes" : "no"
    );
}

(function() {

    if (localStorage.getItem("needifyReportsCenter") === "yes") {
        document.body.classList.add("center-text");
    }

})();

/*
|--------------------------------------------------------------------------
| PAGE UP / PAGE DOWN
|--------------------------------------------------------------------------
*/

document.addEventListener("keydown", function(event) {

    if (
        event.target.tagName === "INPUT" ||
        event.target.tagName === "SELECT" ||
        event.target.tagName === "TEXTAREA"
    ) {
        return;
    }

    if (event.key === "PageDown") {
        event.preventDefault();

        window.scrollBy({
            top: window.innerHeight * 0.8,
            behavior: "smooth"
        });
    }

    if (event.key === "PageUp") {
        event.preventDefault();

        window.scrollBy({
            top: -window.innerHeight * 0.8,
            behavior: "smooth"
        });
    }

});

</script>

</body>
</html>
