<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// CSRF token for cancellation
if (empty($_SESSION['cancel_order_csrf'])) {
    $_SESSION['cancel_order_csrf'] = bin2hex(random_bytes(32));
}

$order_id = (int)($_GET['id'] ?? 0);
if ($order_id <= 0) {
    header('Location: my-orders.php?error=invalid');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
// Verify order belongs to user and is cancellable
$stmt = $conn->prepare('SELECT status FROM orders WHERE id = ? AND user_id = ?');
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    header('Location: my-orders.php?error=notfound');
    exit();
}
$order = $res->fetch_assoc();
$stmt->close();

if ($order['status'] !== 'placed') {
    header('Location: my-orders.php?error=notcancellable');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['cancel_order_csrf'], $token)) {
        header('Location: my-orders.php?error=security');
        exit();
    }
    // Transaction: set status to cancelled and restore stock
    $conn->begin_transaction();
    try {
        // Update order status
        $upd = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $newStatus = 'cancelled';
        $upd->bind_param('si', $newStatus, $order_id);
        $upd->execute();
        $upd->close();
        // Restore stock for each item
        $itemStmt = $conn->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
        $itemStmt->bind_param('i', $order_id);
        $itemStmt->execute();
        $itemsRes = $itemStmt->get_result();
        $stockUpd = $conn->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
        while ($row = $itemsRes->fetch_assoc()) {
            $stockUpd->bind_param('ii', $row['quantity'], $row['product_id']);
            $stockUpd->execute();
        }
        $stockUpd->close();
        $itemStmt->close();
        $conn->commit();
        // Regenerate CSRF
        $_SESSION['cancel_order_csrf'] = bin2hex(random_bytes(32));
        header('Location: my-orders.php?cancelled=1');
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        header('Location: my-orders.php?error=failed');
        exit();
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <h2>Cancel Order #<?= e($order_id) ?></h2>
        <p>Are you sure you want to cancel this order? This action cannot be undone.</p>
        <form method="POST" action="cancel-order.php?id=<?= e($order_id) ?>" style="max-width:400px;">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['cancel_order_csrf']) ?>">
            <button type="submit" class="action-btn edit" style="padding:10px 20px;">Yes, Cancel Order</button>
            <a href="my-orders.php" class="action-btn" style="margin-left:10px;">No, Go Back</a>
        </form>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
