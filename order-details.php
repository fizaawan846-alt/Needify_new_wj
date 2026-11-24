
<?php
// =====================================================
// NEEDIFY - ORDER DETAILS
// DELIVERY INFORMATION FIXED
// =====================================================

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

// USER LOGIN CHECK
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// GET ORDER ID
$order_id = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$order_id || $order_id < 1) {
    header('Location: my-orders.php?error=invalid');
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// =====================================================
// FETCH ORDER
// =====================================================

$order_stmt = $conn->prepare(
    'SELECT *
     FROM orders
     WHERE id = ? AND user_id = ?'
);

if (!$order_stmt) {
    error_log('Order query error: ' . $conn->error);
    die('Unable to load order details. Please try again.');
}

$order_stmt->bind_param('ii', $order_id, $user_id);

if (!$order_stmt->execute()) {
    error_log('Order execution error: ' . $order_stmt->error);
    die('Unable to load order details. Please try again.');
}

$order_res = $order_stmt->get_result();

if ($order_res->num_rows === 0) {
    $order_stmt->close();
    header('Location: my-orders.php?error=notfound');
    exit();
}

$order = $order_res->fetch_assoc();
$order_stmt->close();

// =====================================================
// FETCH ORDER ITEMS
// =====================================================

$item_stmt = $conn->prepare(
    'SELECT
        oi.product_id,
        oi.quantity,
        oi.unit_price,
        p.name AS product_name
     FROM order_items oi
     LEFT JOIN products p ON oi.product_id = p.id
     WHERE oi.order_id = ?'
);

if (!$item_stmt) {
    error_log('Order items query error: ' . $conn->error);
    die('Unable to load order items. Please try again.');
}

$item_stmt->bind_param('i', $order_id);

if (!$item_stmt->execute()) {
    error_log('Order items execution error: ' . $item_stmt->error);
    die('Unable to load order items. Please try again.');
}

$item_res = $item_stmt->get_result();

$items = [];

while ($row = $item_res->fetch_assoc()) {
    $items[] = $row;
}

$item_stmt->close();

// ORDER DISPLAY REFERENCE
$order_reference = !empty($order['order_ref'])
    ? $order['order_ref']
    : '#' . $order['id'];

// DELIVERY DETAILS
$customer_name = $order['customer_name'] ?? '';
$phone = $order['phone'] ?? '';
$delivery_address = $order['delivery_address'] ?? '';
$city = $order['city'] ?? '';
$delivery_instructions = $order['delivery_instructions'] ?? '';

// LOAD HEADER
require_once __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">

        <h2>Order Details</h2>

        <?php if (isset($_GET['success'])): ?>
            <div class="flash success">
                Your order has been placed successfully!
            </div>
        <?php endif; ?>

        <!-- ORDER INFORMATION -->

        <div class="order-details-box">

            <h3>Order Information</h3>

            <p>
                <strong>Order Reference:</strong>
                <?= e($order_reference) ?>
            </p>

            <p>
                <strong>Order ID:</strong>
                #<?= (int)$order['id'] ?>
            </p>

            <p>
                <strong>Date:</strong>
                <?= e(date('M d, Y', strtotime($order['created_at']))) ?>
            </p>

            <p>
                <strong>Status:</strong>
                <?= e(ucfirst($order['status'] ?? 'Pending')) ?>
            </p>

            <p>
                <strong>Payment Method:</strong>
                <?= e(strtoupper($order['payment_method'] ?? 'COD')) ?>
            </p>

            <p>
                <strong>Payment Status:</strong>
                <?= e(ucfirst($order['payment_status'] ?? 'Pending')) ?>
            </p>

            <p>
                <strong>Total:</strong>
                <?= e(format_price($order['total_amount'])) ?>
            </p>

        </div>

        <!-- ORDER ITEMS -->

        <h3>Order Items</h3>

        <?php if (empty($items)): ?>

            <p>No items found for this order.</p>

        <?php else: ?>

            <table class="needs-table">

                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($items as $it): ?>

                        <tr>

                            <td>
                                <?= e($it['product_name'] ?? 'Product unavailable') ?>
                            </td>

                            <td>
                                <?= (int)$it['quantity'] ?>
                            </td>

                            <td>
                                <?= e(format_price($it['unit_price'])) ?>
                            </td>

                            <td>
                                <?= e(format_price(
                                    (float)$it['unit_price'] * (int)$it['quantity']
                                )) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

        <!-- DELIVERY DETAILS -->

        <h3>Delivery Details</h3>

        <div class="order-details-box">

            <p>
                <strong>Name:</strong>
                <?= e($customer_name !== '' ? $customer_name : 'Not provided') ?>
            </p>

            <p>
                <strong>Phone:</strong>
                <?= e($phone !== '' ? $phone : 'Not provided') ?>
            </p>

            <p>
                <strong>Address:</strong>
                <?= e($delivery_address !== '' ? $delivery_address : 'Not provided') ?>
            </p>

            <p>
                <strong>City:</strong>
                <?= e($city !== '' ? $city : 'Not provided') ?>
            </p>

            <p>
                <strong>Additional Instructions:</strong><br>
                <?= $delivery_instructions !== ''
                    ? nl2br(e($delivery_instructions))
                    : 'No additional instructions' ?>
            </p>

        </div>

        <!-- BACK BUTTON -->

        <div style="margin-top: 25px;">

            <a href="my-orders.php" class="action-btn">
                Back to My Orders
            </a>

        </div>

    </div>
</section>

<style>

.order-details-box {
    background: #fff;
    border: 1px solid #EDE5F5;
    border-radius: 14px;
    padding: 20px;
    margin: 18px 0 25px;
    box-shadow: 0 5px 18px rgba(48,39,71,0.05);
}

.order-details-box h3 {
    margin-bottom: 15px;
}

.order-details-box p {
    margin: 10px 0;
    line-height: 1.8;
    overflow-wrap: anywhere;
}

body.dark-mode .order-details-box {
    background: #242031;
    color: #EEE8F7;
    border-color: #493D5C;
}

</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
