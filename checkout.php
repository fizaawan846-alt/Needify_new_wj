<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

// User must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// CSRF token generation for checkout form
if (empty($_SESSION['checkout_csrf'])) {
    $_SESSION['checkout_csrf'] = bin2hex(random_bytes(32));
}

// Retrieve cart from session (assumed format: ['product_id' => qty, ...])
$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    // No items – redirect to cart page
    header('Location: cart.php?msg=empty');
    exit();
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($_SESSION['checkout_csrf'], $submittedToken)) {
        $message = 'Invalid request. Please refresh and try again.';
        $messageType = 'error';
    } else {
        // Sanitize inputs
        $customer_name = trim($_POST['customer_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');

        // Simple validation
        if ($customer_name === '' || $phone === '' || $address === '' || $city === '') {
            $message = 'All fields except instructions are required.';
            $messageType = 'error';
        } else {
            // Begin transaction
            $conn->begin_transaction();
            try {
                // Generate unique order reference
                $order_ref = generate_order_reference();
                $user_id = (int) $_SESSION['user_id'];
                $status = 'placed'; // initial status
                $total = 0.0;

                // First, calculate total and verify stock
                $order_items = [];
                $product_stmt = $conn->prepare('SELECT id, price, stock FROM products WHERE id = ? FOR UPDATE');
                foreach ($cart as $product_id => $qty) {
                    $product_id = (int) $product_id;
                    $qty = (int) $qty;
                    $product_stmt->bind_param('i', $product_id);
                    $product_stmt->execute();
                    $p_res = $product_stmt->get_result();
                    if ($p_res->num_rows === 0) {
                        throw new Exception('Product not found.');
                    }
                    $product = $p_res->fetch_assoc();
                    if ($product['stock'] < $qty) {
                        throw new Exception('Insufficient stock for product ID ' . $product_id);
                    }
                    $line_total = $product['price'] * $qty;
                    $total += $line_total;
                    $order_items[] = [
                        'product_id' => $product_id,
                        'quantity' => $qty,
                        'price' => $product['price'],
                    ];
                }
                $product_stmt->close();

                // Insert into orders table
                $order_stmt = $conn->prepare('INSERT INTO orders (user_id, reference, total_amount, status, customer_name, phone, address, city, instructions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $order_stmt->bind_param('isdsssss', $user_id, $order_ref, $total, $status, $customer_name, $phone, $address, $city, $instructions);
                $order_stmt->execute();
                $order_id = $order_stmt->insert_id;
                $order_stmt->close();

                // Insert order items and deduct stock
                $item_stmt = $conn->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
                $stock_stmt = $conn->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
                foreach ($order_items as $item) {
                    $item_stmt->bind_param('iiid', $order_id, $item['product_id'], $item['quantity'], $item['price']);
                    $item_stmt->execute();
                    $stock_stmt->bind_param('ii', $item['quantity'], $item['product_id']);
                    $stock_stmt->execute();
                }
                $item_stmt->close();
                $stock_stmt->close();

                // Commit transaction
                $conn->commit();

                // Clear cart
                unset($_SESSION['cart']);
                // Regenerate CSRF token to avoid reuse
                $_SESSION['checkout_csrf'] = bin2hex(random_bytes(32));

                // Redirect to order details page with success flag
                header('Location: order-details.php?id=' . $order_id . '&success=1');
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $message = 'Checkout failed: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Render checkout form
require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <h2>Checkout – Cash on Delivery</h2>
        <?php if ($message !== ''): ?>
            <div class="flash <?= e($messageType) ?>"><?php echo e($message); ?></div>
        <?php endif; ?>
        <form method="POST" action="checkout.php" style="max-width: 600px;">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['checkout_csrf']) ?>">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="customer_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="tel" name="phone" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Address</label>
                <textarea name="address" class="form-control" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label>City</label>
                <input type="text" name="city" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Additional Instructions (optional)</label>
                <textarea name="instructions" class="form-control" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 12px 24px;">Place Order (COD)</button>
        </form>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
