
<?php
// =====================================================
// NEEDIFY - PLACE ORDER
// CASH ON DELIVERY ONLY
// Original Needify Theme Preserved
// =====================================================

// CENTRAL SESSION CONFIGURATION
require_once __DIR__ . "/config/session.php";
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/csrf.php";

// SECURITY HEADERS
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// CENTRAL INACTIVITY CHECK - 30 MINUTES
needify_check_inactivity(1800, "login.php?timeout=1");

// USER LOGIN CHECK
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$error = "";

// DELIVERY INFORMATION
$customer_name = trim($_POST["customer_name"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$delivery_address = trim($_POST["delivery_address"] ?? "");
$city = trim($_POST["city"] ?? "");
$delivery_instructions = trim($_POST["delivery_instructions"] ?? "");

// CSRF TOKEN
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// =====================================================
// SUCCESS SCREEN CHECK
// =====================================================

$success_order_id = null;

if (isset($_GET["success"]) && $_GET["success"] === "1") {

    $success_order_id = $_SESSION["order_success_id"] ?? null;

    unset($_SESSION["order_success_id"]);

    if (!is_numeric($success_order_id) || (int)$success_order_id < 1) {
        $success_order_id = null;
    } else {
        $success_order_id = (int)$success_order_id;
    }
}

// CART CHECK
if (
    $success_order_id === null &&
    (empty($_SESSION["cart"]) || !is_array($_SESSION["cart"]))
) {
    header("Location: cart.php");
    exit();
}

// =====================================================
// CASH ON DELIVERY ORDER PROCESSING
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    $success_order_id === null
) {

    // VERIFY CSRF TOKEN
    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)
    ) {
        http_response_code(403);
        exit("Invalid request. Please refresh the page and try again.");
    }

    // COD ONLY
    $payment_method = $_POST["payment_method"] ?? "";

    if (!is_string($payment_method) || $payment_method !== "cod") {
        http_response_code(400);
        exit("Invalid payment method.");
    }

    $transactionStarted = false;

    try {

        // =====================================================
        // VALIDATE DELIVERY INFORMATION
        // =====================================================

        if (
            $customer_name === "" ||
            $phone === "" ||
            $delivery_address === "" ||
            $city === ""
        ) {
            throw new Exception(
                "Please fill in all required delivery details."
            );
        }

        if (
            strlen($customer_name) > 100 ||
            strlen($phone) > 25 ||
            strlen($city) > 100
        ) {
            throw new Exception(
                "One or more delivery fields are too long."
            );
        }

        if (!preg_match('/^[0-9+\-\s()]{7,25}$/', $phone)) {
            throw new Exception(
                "Please enter a valid phone number."
            );
        }

        // ADDRESS LENGTH CHECK
        if (strlen($delivery_address) > 1000) {
            throw new Exception(
                "Delivery address is too long."
            );
        }

        if (strlen($delivery_instructions) > 1000) {
            throw new Exception(
                "Delivery instructions are too long."
            );
        }

        // =====================================================
        // CHECK CART
        // =====================================================

        $cart = $_SESSION["cart"];

        if (!is_array($cart) || empty($cart)) {
            throw new Exception("Your cart is empty.");
        }

        // =====================================================
        // START DATABASE TRANSACTION
        // =====================================================

        $conn->begin_transaction();
        $transactionStarted = true;

        $total = 0;
        $items = [];

        // LOCK PRODUCTS AND CHECK STOCK
        $product_stmt = $conn->prepare(
            "SELECT id, name, price, stock
             FROM products
             WHERE id = ? AND is_active = 1
             FOR UPDATE"
        );

        if (!$product_stmt) {
            throw new Exception(
                "Unable to check product availability."
            );
        }

        foreach ($cart as $product_id => $quantity) {

            $product_id = filter_var(
                $product_id,
                FILTER_VALIDATE_INT
            );

            $quantity = filter_var(
                $quantity,
                FILTER_VALIDATE_INT
            );

            if (
                $product_id === false ||
                $product_id < 1 ||
                $quantity === false ||
                $quantity < 1
            ) {
                throw new Exception(
                    "Invalid product or quantity."
                );
            }

            $product_stmt->bind_param("i", $product_id);

            if (!$product_stmt->execute()) {
                throw new Exception(
                    "Unable to check product stock."
                );
            }

            $result = $product_stmt->get_result();
            $product = $result->fetch_assoc();

            if (!$product) {
                throw new Exception(
                    "A product in your cart is no longer available."
                );
            }

            if ($quantity > (int)$product["stock"]) {
                throw new Exception(
                    "Not enough stock for " . $product["name"] . "."
                );
            }

            $price = (float)$product["price"];

            if (!is_finite($price) || $price < 0) {
                throw new Exception("Invalid product price.");
            }

            $subtotal = $price * $quantity;

            if (!is_finite($subtotal)) {
                throw new Exception("Invalid order amount.");
            }

            $total += $subtotal;

            $items[] = [
                "product_id" => (int)$product["id"],
                "name" => $product["name"],
                "quantity" => $quantity,
                "price" => $price
            ];
        }

        $product_stmt->close();

        if (empty($items) || $total <= 0) {
            throw new Exception("Your cart is empty.");
        }

        // =====================================================
        // CREATE ORDER
        // =====================================================

        $payment_method = "cod";
        $payment_status = "pending";

        // GENERATE UNIQUE ORDER REFERENCE
        $order_ref = "NDF-" . date("Ymd") . "-" .
                     strtoupper(bin2hex(random_bytes(4)));

        $order_stmt = $conn->prepare(
            "INSERT INTO orders
            (
                user_id,
                order_ref,
                customer_name,
                phone,
                delivery_address,
                city,
                delivery_instructions,
                total_amount,
                status,
                payment_method,
                payment_status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'placed', ?, ?)"
        );

        if (!$order_stmt) {
            throw new Exception(
                "Unable to create your order."
            );
        }

        $order_stmt->bind_param(
            "issssssdss",
            $user_id,
            $order_ref,
            $customer_name,
            $phone,
            $delivery_address,
            $city,
            $delivery_instructions,
            $total,
            $payment_method,
            $payment_status
        );

        if (!$order_stmt->execute()) {
            throw new Exception(
                "Unable to save your order."
            );
        }

        $order_id = (int)$conn->insert_id;

        $order_stmt->close();

        // =====================================================
        // INSERT ORDER ITEMS
        // =====================================================

        $item_stmt = $conn->prepare(
            "INSERT INTO order_items
            (order_id, product_id, quantity, unit_price)
            VALUES (?, ?, ?, ?)"
        );

        if (!$item_stmt) {
            throw new Exception(
                "Unable to save order items."
            );
        }

        // UPDATE PRODUCT STOCK
        $stock_stmt = $conn->prepare(
            "UPDATE products
             SET stock = stock - ?
             WHERE id = ? AND stock >= ? AND is_active = 1"
        );

        if (!$stock_stmt) {
            throw new Exception(
                "Unable to update product stock."
            );
        }

        foreach ($items as $item) {

            $product_id = $item["product_id"];
            $quantity = $item["quantity"];
            $price = $item["price"];

            $item_stmt->bind_param(
                "iiid",
                $order_id,
                $product_id,
                $quantity,
                $price
            );

            if (!$item_stmt->execute()) {
                throw new Exception(
                    "Unable to save an order item."
                );
            }

            $stock_stmt->bind_param(
                "iii",
                $quantity,
                $product_id,
                $quantity
            );

            if (!$stock_stmt->execute()) {
                throw new Exception(
                    "Unable to update product stock."
                );
            }

            if ($stock_stmt->affected_rows !== 1) {
                throw new Exception(
                    "Stock changed during checkout. Please try again."
                );
            }
        }

        $item_stmt->close();
        $stock_stmt->close();

        // =====================================================
        // COMMIT TRANSACTION
        // =====================================================

        $conn->commit();
        $transactionStarted = false;

        // CLEAR CART AFTER SUCCESS
        unset($_SESSION["cart"]);

        // SAVE ORDER SUCCESS ID
        $_SESSION["order_success_id"] = $order_id;

        // REDIRECT TO SUCCESS SCREEN
        header("Location: place-order.php?success=1");
        exit();

    } catch (Throwable $e) {

        if ($transactionStarted) {
            $conn->rollback();
        }

        error_log(
            "Needify order error: " . $e->getMessage()
        );

        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Needify | Confirm Your Order</title>

    <link rel="icon" type="image/png" href="favicon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>

        :root {
            --primary: #7956C8;
            --primary-dark: #6241AD;
            --primary-light: #F0EAFE;
            --pink: #FFE5EF;
            --pink-dark: #F8D1E0;
            --cream: #FFFAF5;
            --white: #FFFFFF;
            --text: #302747;
            --muted: #766D82;
            --border: #EDE5F5;
            --success-bg: #F0F8F2;
            --success-text: #276341;
            --error-bg: #FFF0F1;
            --error-text: #A52D43;
            --shadow: 0 12px 35px rgba(48, 39, 71, 0.08);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: "Poppins", sans-serif;
            background: var(--cream);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.6;
        }

        a {
            text-decoration: none;
        }

        button {
            font-family: inherit;
        }

        /* NAVIGATION */

        nav {
            width: 100%;
            background: var(--white);
            padding: 17px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            box-shadow: 0 3px 18px rgba(48, 39, 71, 0.06);
            position: relative;
            z-index: 10;
        }

        .logo {
            color: var(--primary);
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -1px;
            white-space: nowrap;
            transition: 0.2s ease;
        }

        .logo:hover {
            color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .nav-links {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 9px;
        }

        .nav-links a {
            color: var(--text);
            font-size: 13px;
            font-weight: 500;
            padding: 9px 13px;
            border-radius: 10px;
            transition: 0.2s ease;
        }

        .nav-links a:hover {
            background: var(--pink);
            color: var(--primary-dark);
        }

        .site-tools {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            flex-shrink: 0;
            margin-left: auto;
        }

        .site-tools button {
            width: 36px;
            height: 36px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--primary-light);
            color: var(--primary-dark);
            cursor: pointer;
            font-size: 13px;
            transition: 0.2s ease;
        }

        .site-tools button:hover {
            background: var(--pink);
            transform: translateY(-2px);
        }

        .site-tools button[aria-pressed="true"] {
            background: var(--primary);
            color: var(--white);
        }

        /* MAIN CONTENT */

        main.container {
            width: 92%;
            max-width: 850px;
            margin: 50px auto;
            flex: 1;
        }

        .box {
            background: var(--white);
            padding: 36px;
            border-radius: 23px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(121, 86, 200, 0.08);
        }

        .page-heading {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 8px;
        }

        .heading-icon {
            width: 50px;
            height: 50px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 15px;
            background: var(--pink);
            color: var(--primary);
            font-size: 21px;
        }

        h1 {
            color: var(--text);
            font-size: 26px;
            font-weight: 700;
        }

        .subtitle {
            color: var(--muted);
            line-height: 1.8;
            margin-bottom: 27px;
            font-size: 14px;
        }

        /* ERROR MESSAGE */

        .error {
            padding: 15px 18px;
            margin-bottom: 22px;
            background: var(--error-bg);
            color: var(--error-text);
            border: 1px solid #F8D3D9;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
        }

        /* ORDER ROWS */

        .order-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 18px;
            margin-bottom: 12px;
            background: #FFFCFE;
            border: 1px solid var(--border);
            border-radius: 13px;
        }

        .order-product {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .product-thumb {
            width: 76px;
            height: 76px;
            flex-shrink: 0;
            border-radius: 12px;
            overflow: hidden;
            background: var(--primary-light);
            display: grid;
            place-items: center;
        }

        .product-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .product-thumb .image-fallback {
            display: none;
            width: 100%;
            height: 100%;
            place-items: center;
            color: var(--primary);
            font-size: 25px;
        }

        .order-product-name {
            color: var(--text);
            font-size: 14px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .order-quantity {
            display: inline-block;
            margin-top: 5px;
            color: var(--muted);
            font-size: 12px;
        }

        .order-price {
            color: var(--primary-dark);
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
        }

        .total {
            font-size: 22px;
            font-weight: bold;
            margin-top: 22px;
            padding: 19px;
            background: var(--primary-light);
            border: 1px solid #E2D6FA;
            border-radius: 14px;
            color: var(--primary-dark);
        }

        /* COD PAYMENT INFORMATION */

        .payment-method-box {
            margin-top: 28px;
        }

        .payment-method-box h3 {
            margin-bottom: 14px;
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
        }

        .payment-option {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 17px;
            margin-bottom: 12px;
            border: 2px solid var(--primary);
            border-radius: 13px;
            background: var(--primary-light);
        }

        .payment-icon {
            width: 43px;
            height: 43px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 12px;
            background: var(--pink);
            color: var(--primary-dark);
            font-size: 19px;
        }

        .payment-info {
            flex: 1;
        }

        .payment-info strong {
            display: block;
            font-size: 13px;
        }

        .payment-info small {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 11px;
        }

        .payment-badge {
            display: inline-block;
            margin-left: auto;
            padding: 5px 9px;
            border-radius: 20px;
            background: #E8F6EC;
            color: #276341;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        /* BUTTONS */

        .form-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-top: 24px;
        }

        .button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 9px;
            min-height: 47px;
            padding: 12px 21px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(
                135deg,
                var(--primary),
                #9274D5
            );
            color: var(--white);
            text-decoration: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: 0.25s ease;
            box-shadow: 0 6px 15px rgba(121, 86, 200, 0.19);
        }

        .button:hover {
            background: linear-gradient(
                135deg,
                var(--primary-dark),
                var(--primary)
            );
            transform: translateY(-2px);
            box-shadow: 0 9px 20px rgba(121, 86, 200, 0.25);
        }

        .button.secondary {
            background: var(--pink);
            color: var(--primary-dark);
            border: 1px solid var(--pink-dark);
            box-shadow: none;
        }

        .button.secondary:hover {
            background: var(--pink-dark);
            color: var(--text);
        }

        .empty-message {
            color: var(--muted);
            padding: 15px 0;
            font-size: 14px;
        }

        /* SUCCESS SCREEN */

        .success {
            text-align: center;
            background: var(--success-bg);
            padding: 30px;
            border-radius: 12px;
            border: 1px solid #D8EBDD;
        }

        .success h2 {
            color: var(--success-text);
            margin-bottom: 15px;
        }

        .success p {
            color: var(--text);
            line-height: 1.8;
            margin-bottom: 12px;
        }

        .success-icon {
            width: 76px;
            height: 76px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: var(--white);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 33px;
            box-shadow: 0 5px 16px rgba(48, 39, 71, 0.08);
        }

        .success .order-number {
            display: inline-block;
            padding: 9px 18px;
            margin: 8px 0 15px;
            border-radius: 10px;
            background: var(--white);
            color: var(--primary-dark);
            font-size: 19px;
            font-weight: 700;
        }

        .payment-success {
            margin: 15px auto;
            max-width: 450px;
            padding: 13px;
            border-radius: 12px;
            background: var(--white);
            color: var(--primary-dark);
            font-size: 12px;
            font-weight: 600;
            line-height: 1.9;
        }

        .success-actions {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 23px;
        }

        /* FOOTER */

        footer {
            text-align: center;
            background: var(--text);
            color: #F6F0FF;
            padding: 22px;
            margin-top: auto;
            font-size: 12px;
        }

        footer strong {
            color: #D6C7FF;
        }

        /* PAGE CONTROLS */

        .page-controls {
            position: fixed;
            right: 22px;
            bottom: 22px;
            z-index: 999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .page-controls button {
            width: 46px;
            height: 46px;
            border: 0;
            border-radius: 15px;
            background: linear-gradient(135deg,#6548bd,#a48ae7);
            color: white;
            box-shadow: 0 7px 20px rgba(101,72,189,.25);
            cursor: pointer;
            font-size: 16px;
            transition: .2s ease;
        }

        .page-controls button:hover {
            transform: translateY(-3px);
        }

        /* CENTER TEXT */

        body.center-text .container,
        body.center-text footer {
            text-align: center;
        }

        body.center-text .page-heading {
            justify-content: center;
        }

        body.center-text .form-actions,
        body.center-text .success-actions {
            justify-content: center;
        }

        /* DARK MODE */

        body.dark-mode {
            background: #171421;
            color: #EEE8F7;
        }

        body.dark-mode nav {
            background: #242031;
            box-shadow: 0 2px 12px #00000030;
        }

        body.dark-mode .logo {
            color: #C9BAFF;
        }

        body.dark-mode .nav-links a {
            color: #D2C8DF;
        }

        body.dark-mode .nav-links a:hover {
            color: #FFFFFF;
            background: #393047;
        }

        body.dark-mode .box {
            background: #242031;
            color: #EEE8F7;
            box-shadow: 0 5px 22px #00000030;
        }

        body.dark-mode .heading-icon {
            background: #393047;
            color: #D6C7FF;
        }

        body.dark-mode h1,
        body.dark-mode .order-product-name {
            color: #EEE8F7;
        }

        body.dark-mode .subtitle,
        body.dark-mode .order-quantity {
            color: #BDB2CE;
        }

        body.dark-mode .order-row {
            background: #2C263B;
            border-color: #493D5C;
        }

        body.dark-mode .product-thumb {
            background: #393047;
        }

        body.dark-mode .order-price {
            color: #D6C7FF;
        }

        body.dark-mode .total {
            background: #393047;
            border-color: #514565;
            color: #D6C7FF;
        }

        body.dark-mode .payment-method-box h3 {
            color: #EEE8F7;
        }

        body.dark-mode .payment-option {
            background: #393047;
            border-color: #9274D5;
        }

        body.dark-mode .payment-info small {
            color: #BDB2CE;
        }

        body.dark-mode .success {
            background: #203D32;
            border-color: #315843;
        }

        body.dark-mode .success h2 {
            color: #A5E5C1;
        }

        body.dark-mode .success p {
            color: #E0F2E5;
        }

        body.dark-mode .success-icon,
        body.dark-mode .success .order-number,
        body.dark-mode .payment-success {
            background: #2C5140;
            color: #D6C7FF;
        }

        body.dark-mode .error {
            background: #44232E;
            color: #FFB7C4;
            border-color: #6C3545;
        }

        body.dark-mode footer {
            background: #1b1726;
            color: #D2C8DF;
        }

        body.dark-mode .site-tools button {
            background: #393047;
            border-color: #514565;
            color: #EEE8F7;
        }

        body.dark-mode .button.secondary {
            background: #453044;
            color: #F5D9EE;
            border-color: #5A3B56;
        }

        body.dark-mode .button.secondary:hover {
            background: #5A3B56;
        }

        body.dark-mode .empty-message {
            color: #BDB2CE;
        }

        /* RESPONSIVE DESIGN */

        @media (max-width: 850px) {
            nav {
                padding: 16px 4%;
            }

            .nav-links {
                gap: 4px;
            }

            .nav-links a {
                padding: 8px 10px;
            }
        }

        @media (max-width: 700px) {
            nav {
                flex-direction: column;
                gap: 13px;
            }

            .nav-links {
                justify-content: center;
                gap: 6px;
            }

            .site-tools {
                margin: 0 auto;
            }

            main.container {
                width: 94%;
                margin: 30px auto;
            }

            .box {
                padding: 20px;
            }

            h1 {
                font-size: 22px;
            }
        }

        @media (max-width: 480px) {
            .nav-links {
                gap: 3px;
            }

            .nav-links a {
                font-size: 11px;
                padding: 7px 8px;
            }

            .order-row {
                flex-wrap: wrap;
            }

            .order-product {
                width: 100%;
            }

            .order-price {
                margin-left: 90px;
            }

            .total {
                font-size: 18px;
            }

            .form-actions,
            .success-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .form-actions .button,
            .success-actions .button {
                width: 100%;
            }

            .page-controls {
                right: 12px;
                bottom: 14px;
            }

            .page-controls button {
                width: 42px;
                height: 42px;
            }

            .success {
                padding: 25px 15px;
            }

            .success h2 {
                font-size: 21px;
            }

            .payment-badge {
                font-size: 9px;
                padding: 4px 7px;
            }
        }

        /* ACCESSIBILITY */

        button:focus-visible,
        a:focus-visible {
            outline: 3px solid #BDA8F2;
            outline-offset: 3px;
        }
/* DELIVERY FORM */

.delivery-form {
    margin-top: 28px;
    padding: 22px;
    background: #FFFCFE;
    border: 1px solid var(--border);
    border-radius: 15px;
}

.delivery-form h3 {
    margin-bottom: 20px;
    color: var(--primary-dark);
    font-size: 17px;
}

.delivery-field {
    margin-bottom: 16px;
}

.delivery-field label {
    display: block;
    margin-bottom: 7px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
}

.delivery-field input,
.delivery-field textarea {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: white;
    color: var(--text);
    font-family: inherit;
    font-size: 13px;
    outline: none;
}

.delivery-field input:focus,
.delivery-field textarea:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(121, 86, 200, 0.10);
}

.delivery-field textarea {
    resize: vertical;
}

body.dark-mode .delivery-form {
    background: #2C263B;
    border-color: #493D5C;
}

body.dark-mode .delivery-form h3,
body.dark-mode .delivery-field label {
    color: #D6C7FF;
}

body.dark-mode .delivery-field input,
body.dark-mode .delivery-field textarea {
    background: #242031;
    color: #EEE8F7;
    border-color: #493D5C;
}
    </style>

</head>

<body>

<nav>

    <a href="index.php" class="logo">Needify.</a>

    <div class="nav-links">

        <a href="index.php">
            <i class="fa-solid fa-house"></i> Home
        </a>

        <a href="products.php">
            <i class="fa-solid fa-bag-shopping"></i> Products
        </a>

        <a href="cart.php">
            <i class="fa-solid fa-cart-shopping"></i> Cart
        </a>

        <a href="my-orders.php">
            <i class="fa-solid fa-box"></i> My Orders
        </a>

        <a href="logout.php">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>

    </div>

    <div class="site-tools" aria-label="Display settings">

        <button type="button"
                id="fontDecrease"
                title="Decrease font size"
                aria-label="Decrease font size">
            <i class="fa-solid fa-minus"></i>
        </button>

        <button type="button"
                id="fontReset"
                title="Reset font size"
                aria-label="Reset font size">
            <i class="fa-solid fa-rotate-left"></i>
        </button>

        <button type="button"
                id="fontIncrease"
                title="Increase font size"
                aria-label="Increase font size">
            <i class="fa-solid fa-plus"></i>
        </button>

        <button type="button"
                id="centerTextToggle"
                title="Center text"
                aria-label="Center text"
                aria-pressed="false">
            <i class="fa-solid fa-align-center"></i>
        </button>

        <button type="button"
                id="themeToggle"
                title="Switch to dark mode"
                aria-label="Switch to dark mode">
            <i class="fa-solid fa-moon"></i>
        </button>

    </div>

</nav>

<main class="container">

    <div class="box">

        <?php if ($success_order_id !== null): ?>

            <div class="success">

                <div class="success-icon">
                    <i class="fa-solid fa-check"></i>
                </div>

                <h2>Order Placed Successfully!</h2>

                <p>
                    Thank you for shopping with Needify.
                </p>

                <p>
                    Your Order ID is:
                </p>

                <div class="order-number">
                    #<?php echo (int)$success_order_id; ?>
                </div>

                <p>
                    Your order status is
                    <strong>Placed</strong>.
                </p>

                <div class="payment-success">

                    <i class="fa-solid fa-money-bill-wave"></i>

                    Payment Method:
                    <strong>Cash on Delivery</strong>

                    <br>

                    Payment Status:
                    <strong>Pending — Pay when your order arrives.</strong>

                </div>

                <div class="success-actions">

                    <a href="my-orders.php" class="button">
                        <i class="fa-solid fa-box"></i>
                        View My Orders
                    </a>

                    <a href="products.php" class="button secondary">
                        <i class="fa-solid fa-bag-shopping"></i>
                        Continue Shopping
                    </a>

                </div>

            </div>

        <?php else: ?>

            <div class="page-heading">

                <div class="heading-icon">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>

                <h1>Confirm Your Order</h1>

            </div>

            <p class="subtitle">
                Review your products and confirm your order.
                Pay in cash when your order is delivered.
                Thank you for choosing Needify!
            </p>

            <?php if ($error !== ""): ?>

                <div class="error">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <?php
                    echo htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>

                </div>

            <?php endif; ?>

            <?php

            $preview_items = [];
            $preview_total = 0;

            // FETCH PRODUCT INFORMATION INCLUDING IMAGE PATH
            foreach ($_SESSION["cart"] as $product_id => $quantity) {

                $product_id = filter_var(
                    $product_id,
                    FILTER_VALIDATE_INT
                );

                $quantity = filter_var(
                    $quantity,
                    FILTER_VALIDATE_INT
                );

                if (
                    $product_id === false ||
                    $product_id < 1 ||
                    $quantity === false ||
                    $quantity < 1
                ) {
                    continue;
                }

                $stmt = $conn->prepare(
                    "SELECT id, name, price, stock, image
                     FROM products
                     WHERE id = ? AND is_active = 1"
                );

                if (!$stmt) {
                    continue;
                }

                $stmt->bind_param("i", $product_id);

                $stmt->execute();

                $result = $stmt->get_result();

                $product = $result->fetch_assoc();

                $stmt->close();

                if (!$product) {
                    continue;
                }

                $product["quantity"] = $quantity;

                $product["subtotal"] =
                    (float)$product["price"] * $quantity;

                $preview_total += $product["subtotal"];

                $preview_items[] = $product;
            }

            ?>

            <?php if (count($preview_items) > 0): ?>

                <?php foreach ($preview_items as $item): ?>

                    <div class="order-row">

                        <div class="order-product">

                            <div class="product-thumb">

                                <?php if (!empty($item["image"])): ?>

                                    <img
                                        src="<?php
                                            echo htmlspecialchars(
                                                $item["image"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            );
                                        ?>"
                                        alt="<?php
                                            echo htmlspecialchars(
                                                $item["name"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            );
                                        ?>"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';"
                                    >

                                    <div class="image-fallback">
                                        <i class="fa-solid fa-bag-shopping"></i>
                                    </div>

                                <?php else: ?>

                                    <div class="image-fallback" style="display:grid;">
                                        <i class="fa-solid fa-bag-shopping"></i>
                                    </div>

                                <?php endif; ?>

                            </div>

                            <div>

                                <div class="order-product-name">

                                    <?php
                                    echo htmlspecialchars(
                                        $item["name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </div>

                                <span class="order-quantity">

                                    <i class="fa-solid fa-box"></i>

                                    Quantity:
                                    <?php echo (int)$item["quantity"]; ?>

                                </span>

                            </div>

                        </div>

                        <div class="order-price">

                            Rs.
                            <?php
                            echo number_format(
                                $item["subtotal"],
                                2
                            );
                            ?>

                        </div>

                    </div>

                <?php endforeach; ?>

                <div class="order-row total">

                    <span>
                        <i class="fa-solid fa-receipt"></i>
                        Total Amount
                    </span>

                    <span>
                        Rs.
                        <?php
                        echo number_format(
                            $preview_total,
                            2
                        );
                        ?>
                    </span>

                </div>

                <!-- CASH ON DELIVERY ONLY -->

                <div class="payment-method-box">

                    <h3>
                        <i class="fa-solid fa-wallet"></i>
                        Payment Method
                    </h3>

                    <div class="payment-option">

                        <span class="payment-icon">
                            <i class="fa-solid fa-money-bill-wave"></i>
                        </span>

                        <span class="payment-info">

                            <strong>Cash on Delivery</strong>

                            <small>
                                Pay in cash when your order is delivered.
                            </small>

                        </span>

                        <span class="payment-badge">
                            Available
                        </span>

                    </div>

                    <form
                        method="POST"
                        action="place-order.php"
                        id="checkoutForm"
                    ><!-- DELIVERY INFORMATION -->

<div class="delivery-form">

    <h3>
        <i class="fa-solid fa-truck"></i>
        Delivery Information
    </h3>

    <div class="delivery-field">
        <label for="customer_name">Full Name *</label>
        <input
            type="text"
            id="customer_name"
            name="customer_name"
            maxlength="100"
            value="<?php echo htmlspecialchars($customer_name, ENT_QUOTES, 'UTF-8'); ?>"
            placeholder="Enter your full name"
            required
        >
    </div>

    <div class="delivery-field">
        <label for="phone">Phone Number *</label>
        <input
            type="tel"
            id="phone"
            name="phone"
            maxlength="25"
            value="<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>"
            placeholder="03XX XXXXXXX"
            required
        >
    </div>

    <div class="delivery-field">
        <label for="delivery_address">Complete Address *</label>
        <textarea
            id="delivery_address"
            name="delivery_address"
            rows="3"
            placeholder="Enter your complete delivery address"
            required
        ><?php echo htmlspecialchars($delivery_address, ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>

    <div class="delivery-field">
        <label for="city">City *</label>
        <input
            type="text"
            id="city"
            name="city"
            maxlength="100"
            value="<?php echo htmlspecialchars($city, ENT_QUOTES, 'UTF-8'); ?>"
            placeholder="Enter your city"
            required
        >
    </div>

    <div class="delivery-field">
        <label for="delivery_instructions">
            Additional Instructions (Optional)
        </label>
        <textarea
            id="delivery_instructions"
            name="delivery_instructions"
            rows="2"
            placeholder="Any special delivery instructions?"
        ><?php echo htmlspecialchars($delivery_instructions, ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>

</div>

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php
                                echo htmlspecialchars(
                                    $_SESSION["csrf_token"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                            ?>"
                        >

                        <input
                            type="hidden"
                            name="payment_method"
                            value="cod"
                        >

                        <div class="form-actions">

                            <button
                                type="submit"
                                class="button"
                                id="placeOrderButton"
                            >

                                <i class="fa-solid fa-check-circle"></i>

                                Confirm &amp; Place Order

                            </button>

                            <a href="cart.php" class="button secondary">

                                <i class="fa-solid fa-arrow-left"></i>

                                Back to Cart

                            </a>

                        </div>

                    </form>

                </div>

            <?php else: ?>

                <p class="empty-message">
                    <i class="fa-solid fa-circle-info"></i>
                    Your cart has no available products.
                </p>

                <a href="products.php" class="button">
                    Browse Products
                </a>

            <?php endif; ?>

        <?php endif; ?>

    </div>

</main>

<footer>

    <p>
        &copy; <?php echo date("Y"); ?>
        <strong>Needify.</strong>
        All rights reserved.
    </p>

    <p style="margin-top: 5px;">
        Smart Shopping Made Simple.
    </p>

</footer>

<div class="page-controls">

    <button type="button"
            id="pageUp"
            aria-label="Page Up"
            title="Page Up">
        <i class="fa-solid fa-arrow-up"></i>
    </button>

    <button type="button"
            id="pageDown"
            aria-label="Page Down"
            title="Page Down">
        <i class="fa-solid fa-arrow-down"></i>
    </button>

</div>

<script>

(function () {

    const body = document.body;

    const themeToggle =
        document.getElementById("themeToggle");

    const centerTextToggle =
        document.getElementById("centerTextToggle");

    const fontDecrease =
        document.getElementById("fontDecrease");

    const fontReset =
        document.getElementById("fontReset");

    const fontIncrease =
        document.getElementById("fontIncrease");

    const pageUp =
        document.getElementById("pageUp");

    const pageDown =
        document.getElementById("pageDown");


    // DARK MODE / LIGHT MODE

    function applyTheme(theme) {

        body.classList.toggle(
            "dark-mode",
            theme === "dark"
        );

        if (themeToggle) {

            themeToggle.innerHTML =
                theme === "dark"
                    ? '<i class="fa-solid fa-sun"></i>'
                    : '<i class="fa-solid fa-moon"></i>';

            themeToggle.title =
                theme === "dark"
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

        const nextTheme =
            body.classList.contains("dark-mode")
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

        const zoom =
            1 + (fontLevel * 0.05);

        body.style.zoom = String(zoom);

        localStorage.setItem(
            "needifyFontLevel",
            String(fontLevel)
        );

    }

    fontDecrease?.addEventListener("click", function () {

        fontLevel = Math.max(
            -2,
            fontLevel - 1
        );

        applyFontLevel();

    });

    fontIncrease?.addEventListener("click", function () {

        fontLevel = Math.min(
            3,
            fontLevel + 1
        );

        applyFontLevel();

    });

    fontReset?.addEventListener("click", function () {

        fontLevel = 0;

        applyFontLevel();

    });

    applyFontLevel();


    // CENTER TEXT SETTING

    function applyCenterText(enabled) {

        body.classList.toggle(
            "center-text",
            enabled
        );

        if (centerTextToggle) {

            centerTextToggle.setAttribute(
                "aria-pressed",
                String(enabled)
            );

            centerTextToggle.title =
                enabled
                    ? "Disable center text"
                    : "Center text";

        }

    }

    const savedCenterText =
        localStorage.getItem("needifyCenterText") === "true";

    applyCenterText(savedCenterText);

    centerTextToggle?.addEventListener("click", function () {

        const enabled =
            !body.classList.contains("center-text");

        localStorage.setItem(
            "needifyCenterText",
            String(enabled)
        );

        applyCenterText(enabled);

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
            top: document.documentElement.scrollHeight,
            behavior: "smooth"
        });

    });


    // PREVENT DOUBLE SUBMISSION

    const checkoutForm =
        document.getElementById("checkoutForm");

    const placeOrderButton =
        document.getElementById("placeOrderButton");

    checkoutForm?.addEventListener("submit", function () {

        if (placeOrderButton) {
            placeOrderButton.disabled = true;
            placeOrderButton.style.opacity = "0.7";
            placeOrderButton.innerHTML =
                '<i class="fa-solid fa-spinner fa-spin"></i> Processing Order...';
        }

    });

})();

</script>

</body>
</html>
