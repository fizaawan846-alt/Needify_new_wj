
<?php
// Secure session configuration
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

// Check user login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get total order count
$count_stmt = $conn->prepare(
    'SELECT COUNT(*) FROM orders WHERE user_id = ?'
);

if (!$count_stmt) {
    die("Count Query Error: " . $conn->error);
}

$count_stmt->bind_param('i', $user_id);
$count_stmt->execute();
$count_stmt->bind_result($totalOrders);
$count_stmt->fetch();
$count_stmt->close();

$totalPages = (int) ceil($totalOrders / $perPage);

// Fetch orders
$order_sql = "
    SELECT id, order_ref, total_amount, status, created_at
    FROM orders
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
";

$order_stmt = $conn->prepare($order_sql);

if (!$order_stmt) {
    die("Order Query Error: " . $conn->error);
}

$order_stmt->bind_param('iii', $user_id, $perPage, $offset);

if (!$order_stmt->execute()) {
    die("Order Execution Error: " . $order_stmt->error);
}

$order_res = $order_stmt->get_result();

$orders = [];

while ($row = $order_res->fetch_assoc()) {
    $orders[] = $row;
}

$order_stmt->close();

// Load header
require_once __DIR__ . '/includes/header.php';
?>

<style>
/* MY ORDERS PAGE */

.my-orders-section {
    padding: 45px 0 60px;
}

.my-orders-container {
    max-width: 1050px;
    margin: auto;
}

.orders-heading {
    margin-bottom: 28px;
}

.orders-heading h2 {
    color: #302747;
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 7px;
}

.orders-heading p {
    color: #766D82;
    font-size: 14px;
}

/* ORDER CARD */

.orders-list {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.order-card {
    background: #FFFFFF;
    border: 1px solid #EDE5F5;
    border-radius: 16px;
    padding: 22px 25px;
    box-shadow: 0 5px 18px rgba(48, 39, 71, 0.06);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    transition: 0.25s ease;
}

.order-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(48, 39, 71, 0.10);
    border-color: #D8C9F5;
}

.order-main {
    flex: 1;
    min-width: 0;
}

.order-top {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

.order-id {
    color: #302747;
    font-size: 16px;
    font-weight: 700;
}

.order-date {
    color: #766D82;
    font-size: 12px;
}

.order-info {
    display: flex;
    align-items: center;
    gap: 35px;
    flex-wrap: wrap;
}

.order-info-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.order-info-label {
    color: #91879E;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.order-info-value {
    color: #302747;
    font-size: 14px;
    font-weight: 600;
}

/* STATUS BADGES */

.order-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 13px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.status-placed {
    background: #F0EAFE;
    color: #6846B1;
}

.status-cancelled {
    background: #FFF0F1;
    color: #B8324D;
}

.status-delivered {
    background: #EAF7EE;
    color: #287446;
}

.status-pending {
    background: #FFF5E5;
    color: #A36A13;
}

/* VIEW BUTTON */

.order-action {
    flex-shrink: 0;
}

.order-view-btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    padding: 11px 19px;
    border-radius: 10px;
    background: linear-gradient(135deg, #7956C8, #9274D5);
    color: #FFFFFF !important;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none !important;
    transition: 0.2s ease;
}

.order-view-btn:hover {
    background: #6241AD;
    transform: translateY(-2px);
}

/* EMPTY ORDERS */

.no-orders-box {
    background: #FFFFFF;
    border: 1px solid #EDE5F5;
    border-radius: 16px;
    padding: 45px 20px;
    text-align: center;
    box-shadow: 0 5px 18px rgba(48, 39, 71, 0.05);
}

.no-orders-box .empty-icon {
    font-size: 42px;
    color: #9274D5;
    margin-bottom: 15px;
}

.no-orders-box h3 {
    color: #302747;
    margin-bottom: 8px;
}

.no-orders-box p {
    color: #766D82;
    font-size: 13px;
    margin-bottom: 18px;
}

.shop-now-btn {
    display: inline-block;
    padding: 11px 20px;
    background: #7956C8;
    color: #FFFFFF !important;
    border-radius: 10px;
    text-decoration: none !important;
    font-size: 13px;
    font-weight: 600;
}

/* PAGINATION */

.orders-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 9px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.orders-pagination a {
    min-width: 38px;
    height: 38px;
    padding: 0 12px;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    border: 1px solid #E4D9F5;
    border-radius: 9px;
    background: #FFFFFF;
    color: #6846B1;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.orders-pagination a.active,
.orders-pagination a:hover {
    background: #7956C8;
    color: #FFFFFF;
    border-color: #7956C8;
}

/* RESPONSIVE */

@media (max-width: 650px) {
    .my-orders-section {
        padding: 28px 0 40px;
    }

    .orders-heading h2 {
        font-size: 23px;
    }

    .order-card {
        padding: 18px;
        flex-direction: column;
        align-items: stretch;
        gap: 18px;
    }

    .order-info {
        gap: 18px 25px;
    }

    .order-action {
        width: 100%;
    }

    .order-view-btn {
        width: 100%;
    }
}

/* DARK MODE */

body.dark-mode .orders-heading h2,
body.dark-mode .order-id,
body.dark-mode .order-info-value,
body.dark-mode .no-orders-box h3 {
    color: #EEE8F7;
}

body.dark-mode .orders-heading p,
body.dark-mode .order-date,
body.dark-mode .order-info-label,
body.dark-mode .no-orders-box p {
    color: #BDB2CE;
}

body.dark-mode .order-card,
body.dark-mode .no-orders-box,
body.dark-mode .orders-pagination a {
    background: #242031;
    border-color: #493D5C;
}

body.dark-mode .order-card:hover {
    border-color: #9274D5;
}

body.dark-mode .orders-pagination a {
    color: #D6C7FF;
}

body.dark-mode .orders-pagination a.active,
body.dark-mode .orders-pagination a:hover {
    background: #7956C8;
    color: #FFFFFF;
}
</style>

<section class="section my-orders-section">
    <div class="container my-orders-container">

        <div class="orders-heading">
            <h2>My Orders</h2>
            <p>Track and manage all your Needify orders in one place.</p>
        </div>

        <?php if (empty($orders)): ?>

            <div class="no-orders-box">

                <div class="empty-icon">
                    <i class="fa-solid fa-box-open"></i>
                </div>

                <h3>No Orders Yet</h3>

                <p>
                    You haven't placed any orders yet.
                    Start shopping and your orders will appear here.
                </p>

                <a href="products.php" class="shop-now-btn">
                    <i class="fa-solid fa-bag-shopping"></i>
                    Shop Now
                </a>

            </div>

        <?php else: ?>

            <div class="orders-list">

                <?php foreach ($orders as $ord): ?>

                    <?php
                    $status = strtolower(trim((string)($ord['status'] ?? 'pending')));

                    $status_class = 'status-pending';

                    if ($status === 'placed') {
                        $status_class = 'status-placed';
                    } elseif ($status === 'cancelled') {
                        $status_class = 'status-cancelled';
                    } elseif ($status === 'delivered') {
                        $status_class = 'status-delivered';
                    }
                    ?>

                    <div class="order-card">

                        <div class="order-main">

                            <div class="order-top">

                                <span class="order-id">
                                    Order #<?= (int)$ord['id'] ?>
                                </span>

                                <span class="order-date">
                                    <i class="fa-regular fa-calendar"></i>
                                    <?= e(date('M d, Y', strtotime($ord['created_at']))) ?>
                                </span>

                            </div>

                            <div class="order-info">

                                <div class="order-info-item">

                                    <span class="order-info-label">
                                        Total Amount
                                    </span>

                                    <span class="order-info-value">
                                        <?= e(format_price($ord['total_amount'])) ?>
                                    </span>

                                </div>

                                <div class="order-info-item">

                                    <span class="order-info-label">
                                        Order Status
                                    </span>

                                    <span class="order-status <?= e($status_class) ?>">
                                        <?= e(ucfirst($status)) ?>
                                    </span>

                                </div>

                            </div>

                        </div>

                        <div class="order-action">

                            <a
                                href="order-details.php?id=<?= (int)$ord['id'] ?>"
                                class="order-view-btn"
                            >
                                <i class="fa-solid fa-eye"></i>
                                View Details
                            </a>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

            <?php if ($totalPages > 1): ?>

                <div class="orders-pagination">

                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>

                        <a
                            href="my-orders.php?page=<?= $p ?>"
                            class="<?= $p === $page ? 'active' : '' ?>"
                        >
                            <?= $p ?>
                        </a>

                    <?php endfor; ?>

                </div>

            <?php endif; ?>

        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
