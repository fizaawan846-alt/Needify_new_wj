
<?php

require_once __DIR__ . "/config/session.php";
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/csrf.php";

// Initialize cart
if (!isset($_SESSION["cart"]) || !is_array($_SESSION["cart"])) {
    $_SESSION["cart"] = [];
}

$error = "";
$success = "";

// Safely escape HTML output
function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

// Format prices
function formatPrice($price) {
    return number_format((float)$price, 2);
}

/*
|--------------------------------------------------------------------------
| HANDLE CART ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!verify_csrf_token()) {
        http_response_code(403);
        exit("Invalid request. Please refresh the page and try again.");
    }

    $action = $_POST["action"] ?? "";

    // ADD PRODUCT
    if ($action === "add") {

        $product_id = filter_var(
            $_POST["product_id"] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$product_id || $product_id < 1) {

            $_SESSION["cart_message"] = "Invalid product selected.";

        } else {

            $stmt = $conn->prepare(
                "SELECT id, name, price, stock
                 FROM products
                 WHERE id = ? AND is_active = 1"
            );

            if (!$stmt) {
                die("Add product query error: " . $conn->error);
            }

            $stmt->bind_param("i", $product_id);
            $stmt->execute();

            $result = $stmt->get_result();
            $product = $result->fetch_assoc();

            $stmt->close();

            if (!$product) {

                $_SESSION["cart_message"] = "Product not found.";

            } elseif ((int)$product["stock"] <= 0) {

                $_SESSION["cart_message"] = "This product is out of stock.";

            } else {

                $current_quantity = (int)(
                    $_SESSION["cart"][$product_id] ?? 0
                );

                if ($current_quantity >= (int)$product["stock"]) {

                    $_SESSION["cart_message"] =
                        "You cannot add more than the available stock.";

                } else {

                    $_SESSION["cart"][$product_id] =
                        $current_quantity + 1;

                    $_SESSION["cart_message"] =
                        $product["name"] . " added to your cart.";
                }
            }
        }
    }

    // UPDATE QUANTITIES
    elseif ($action === "update") {

        $quantities = $_POST["quantities"] ?? [];

        if (!is_array($quantities)) {

            $_SESSION["cart_message"] = "Invalid cart information.";

        } else {

            foreach ($_SESSION["cart"] as $product_id => $old_quantity) {

                $product_id = (int)$product_id;

                $raw_quantity = $quantities[$product_id] ?? "1";

                $quantity = filter_var(
                    $raw_quantity,
                    FILTER_VALIDATE_INT
                );

                if ($quantity === false || $quantity < 1) {
                    $quantity = 1;
                }

                $stmt = $conn->prepare(
                    "SELECT stock
                     FROM products
                     WHERE id = ? AND is_active = 1"
                );

                if (!$stmt) {
                    die("Update cart query error: " . $conn->error);
                }

                $stmt->bind_param("i", $product_id);
                $stmt->execute();

                $result = $stmt->get_result();
                $product = $result->fetch_assoc();

                $stmt->close();

                if (!$product || (int)$product["stock"] <= 0) {

                    unset($_SESSION["cart"][$product_id]);
                    continue;
                }

                $quantity = min(
                    $quantity,
                    (int)$product["stock"]
                );

                $_SESSION["cart"][$product_id] = $quantity;
            }

            $_SESSION["cart_message"] =
                "Your shopping cart has been updated.";
        }
    }

    // REMOVE PRODUCT
    elseif ($action === "remove") {

        $product_id = filter_var(
            $_POST["product_id"] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($product_id && $product_id > 0) {

            unset($_SESSION["cart"][$product_id]);

            $_SESSION["cart_message"] =
                "Product removed from your cart.";

        } else {

            $_SESSION["cart_message"] = "Invalid product selected.";
        }
    }

    // REDIRECT AFTER POST
    header("Location: cart.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| FLASH MESSAGE
|--------------------------------------------------------------------------
*/

if (isset($_SESSION["cart_message"])) {

    $success = (string)$_SESSION["cart_message"];

    unset($_SESSION["cart_message"]);
}

/*
|--------------------------------------------------------------------------
| FETCH CART PRODUCTS AND CALCULATE TOTAL
|--------------------------------------------------------------------------
*/

$cart_items = [];
$total_amount = 0;

foreach ($_SESSION["cart"] as $product_id => $quantity) {

    $product_id = (int)$product_id;
    $quantity = (int)$quantity;

    if ($product_id < 1 || $quantity < 1) {

        unset($_SESSION["cart"][$product_id]);
        continue;
    }

    $stmt = $conn->prepare(
        "SELECT id, name, category, price, image, stock
         FROM products
         WHERE id = ? AND is_active = 1"
    );

    if (!$stmt) {
        die("Cart products query error: " . $conn->error);
    }

    $stmt->bind_param("i", $product_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    $stmt->close();

    if (!$product || (int)$product["stock"] <= 0) {

        unset($_SESSION["cart"][$product_id]);
        continue;
    }

    $quantity = min(
        $quantity,
        (int)$product["stock"]
    );

    $_SESSION["cart"][$product_id] = $quantity;

    $product["quantity"] = $quantity;

    $product["subtotal"] =
        (float)$product["price"] * $quantity;

    $total_amount += $product["subtotal"];

    $cart_items[] = $product;
}

$item_count = count($cart_items);

$total_quantity = 0;

foreach ($cart_items as $item) {
    $total_quantity += (int)$item["quantity"];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Shopping Cart | Needify</title>

    <meta name="description" content="Review your Needify shopping cart, update quantities, and continue to checkout.">

    <link rel="icon" type="image/png" href="favicon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root {
            --purple: #8063d8;
            --purple-dark: #6548bd;
            --purple-light: #eee8ff;
            --pink: #d878a0;
            --pink-light: #fff0f6;
            --cream: #fffaf6;
            --text: #332b46;
            --muted: #898397;
            --white: #ffffff;
            --border: #eee8f4;
            --shadow: 0 12px 35px rgba(91, 67, 139, 0.08);
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
            font-family: 'DM Sans', sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 5% 10%, #f3eaff 0, transparent 24%),
                radial-gradient(circle at 95% 40%, #fff0f5 0, transparent 24%),
                var(--cream);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        a {
            text-decoration: none;
        }

        button,
        a {
            -webkit-tap-highlight-color: transparent;
        }

        /* NAVBAR */
        .navbar {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(238, 232, 244, 0.9);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-inner {
            max-width: 1350px;
            min-height: 80px;
            margin: auto;
            padding: 15px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 25px;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--text);
            font-size: 27px;
            font-weight: 800;
            letter-spacing: -1px;
            white-space: nowrap;
        }

        .logo-icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: linear-gradient(135deg, #a28af0, #edb8d3);
            color: white;
            display: grid;
            place-items: center;
            box-shadow: 0 6px 15px rgba(128, 99, 216, 0.22);
        }

        .logo-name span {
            color: var(--purple);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 22px;
            flex-wrap: wrap;
        }

        .nav-links a {
            color: #706a80;
            font-size: 13px;
            font-weight: 700;
            transition: color 0.25s, transform 0.25s;
            white-space: nowrap;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: var(--purple);
        }

        .nav-links .logout {
            color: white;
            background: linear-gradient(135deg, #e98daf, #d875a0);
            padding: 12px 17px;
            border-radius: 13px;
        }

        .nav-links .logout:hover {
            color: white;
            transform: translateY(-2px);
        }

        .nav-links .signup {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            background: linear-gradient(135deg, #9276e1, #7557ca);
            padding: 12px 17px;
            border-radius: 13px;
        }

        /* DISPLAY SETTINGS */
        .site-tools {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            flex-shrink: 0;
        }

        .site-tools button {
            width: 36px;
            height: 36px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--purple-light);
            color: var(--purple-dark);
            cursor: pointer;
            font-size: 13px;
            transition: 0.2s ease;
        }

        .site-tools button:hover {
            transform: translateY(-2px);
        }

        .site-tools #themeToggle {
            width: 39px;
        }

        /* MAIN */
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 42px auto 65px;
            flex: 1;
        }

        /* PAGE HEADER */
        .page-header {
            position: relative;
            overflow: hidden;
            padding: 38px 40px;
            margin-bottom: 30px;
            border-radius: 27px;
            background: linear-gradient(120deg, #eee7ff, #f8e7f5 55%, #fff0f5);
            border: 1px solid rgba(255, 255, 255, 0.9);
            box-shadow: var(--shadow);
            animation: fadeUp 0.55s ease both;
        }

        .page-header::after {
            content: "";
            position: absolute;
            width: 210px;
            height: 210px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.36);
            right: -40px;
            top: -100px;
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 13px;
            margin-bottom: 17px;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.7);
            color: var(--purple-dark);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(32px, 4vw, 44px);
            line-height: 1.2;
            letter-spacing: -0.8px;
            margin-bottom: 12px;
        }

        .page-header h1 span {
            color: var(--purple-dark);
        }

        .page-header p {
            max-width: 650px;
            color: #746a87;
            font-size: 13px;
            line-height: 1.9;
        }

        /* ALERT */
        .message {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 15px 18px;
            border-radius: 15px;
            margin-bottom: 22px;
            font-size: 13px;
            line-height: 1.7;
            animation: fadeUp 0.3s ease both;
        }

        .message i {
            margin-top: 2px;
        }

        .message.success {
            background: #eaf8ef;
            border: 1px solid #d7f0df;
            color: #28784c;
        }

        .message.error {
            background: #fff0f3;
            border: 1px solid #f9dce5;
            color: #bd4866;
        }

        /* CART LAYOUT */
        .cart-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 350px;
            align-items: start;
            gap: 25px;
        }

        .cart-panel,
        .summary-card {
            min-width: 0;
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid var(--border);
            border-radius: 23px;
            box-shadow: 0 8px 28px rgba(91, 67, 139, 0.055);
            overflow: hidden;
            animation: fadeUp 0.55s ease both;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 24px 25px;
            border-bottom: 1px solid var(--border);
        }

        .panel-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 23px;
        }

        .panel-header p {
            margin-top: 5px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.7;
        }

        .item-badge {
            padding: 9px 12px;
            border-radius: 11px;
            background: var(--purple-light);
            color: var(--purple-dark);
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        /* PRODUCT ROW */
        .cart-items {
            padding: 0 24px;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 88px minmax(0, 1fr);
            gap: 17px;
            padding: 23px 0;
            border-bottom: 1px solid #f1edf5;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 88px;
            height: 95px;
            border-radius: 17px;
            overflow: hidden;
            background: linear-gradient(135deg, #eee8ff, #fff0f6);
            display: grid;
            place-items: center;
            color: var(--purple);
            font-size: 29px;
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-details {
            min-width: 0;
        }

        .item-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 7px;
        }

        .item-name {
            font-family: 'Playfair Display', serif;
            font-size: 18px;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .item-category {
            display: inline-block;
            margin-top: 6px;
            color: var(--purple-dark);
            background: var(--purple-light);
            padding: 5px 9px;
            border-radius: 50px;
            font-size: 10px;
            font-weight: 700;
        }

        .item-unit-price {
            color: var(--muted);
            font-size: 11px;
            margin-top: 9px;
        }

        .item-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 15px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-control label {
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
        }

        .quantity-input {
            width: 75px;
            padding: 10px 9px;
            border: 1px solid #e6dfef;
            border-radius: 11px;
            background: #fff;
            color: var(--text);
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .quantity-input:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(128, 99, 216, 0.1);
        }

        .item-subtotal {
            text-align: right;
        }

        .subtotal-label {
            color: var(--muted);
            font-size: 10px;
            margin-bottom: 4px;
        }

        .subtotal-price {
            color: var(--purple-dark);
            font-size: 17px;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .remove-button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 7px;
            border: 1px solid #f5dfe7;
            border-radius: 10px;
            padding: 9px 11px;
            background: #fff5f7;
            color: #c15b78;
            font-family: inherit;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }

        .remove-button:hover {
            background: #ffe8ef;
            transform: translateY(-1px);
        }

        /* CART ACTIONS */
        .cart-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding: 20px 24px 24px;
            border-top: 1px solid var(--border);
            background: #fffdfd;
        }

        .button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 9px;
            padding: 12px 16px;
            border: none;
            border-radius: 12px;
            font-family: 'DM Sans', sans-serif;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            transition: transform 0.22s, box-shadow 0.22s, background 0.22s;
        }

        .button:hover {
            transform: translateY(-2px);
        }

        .button-primary {
            color: white;
            background: linear-gradient(135deg, #9276e1, #7557ca);
            box-shadow: 0 6px 15px rgba(117, 87, 202, 0.17);
        }

        .button-primary:hover {
            box-shadow: 0 9px 20px rgba(117, 87, 202, 0.24);
        }

        .button-secondary {
            color: var(--purple-dark);
            background: var(--purple-light);
        }

        /* SUMMARY */
        .summary-card {
            position: sticky;
            top: 105px;
            animation-delay: 0.1s;
        }

        .summary-header {
            padding: 24px 24px 19px;
            border-bottom: 1px solid var(--border);
        }

        .summary-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 23px;
            margin-bottom: 7px;
        }

        .summary-header p {
            color: var(--muted);
            font-size: 11px;
            line-height: 1.7;
        }

        .summary-body {
            padding: 22px 24px 25px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 17px;
            color: #777084;
            font-size: 12px;
        }

        .summary-row strong {
            color: var(--text);
            font-size: 12px;
            text-align: right;
            overflow-wrap: anywhere;
        }

        .summary-total {
            padding-top: 19px;
            margin-top: 7px;
            margin-bottom: 19px;
            border-top: 1px dashed #e7dfef;
            align-items: center;
        }

        .summary-total span {
            color: var(--text);
            font-size: 13px;
            font-weight: 800;
        }

        .summary-total strong {
            color: var(--purple-dark);
            font-size: 23px;
            font-weight: 800;
        }

        .checkout-button,
        .continue-button {
            width: 100%;
            min-height: 46px;
            margin-top: 10px;
        }

        .secure-note {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 7px;
            margin-top: 18px;
            color: var(--muted);
            font-size: 10px;
            text-align: center;
            line-height: 1.7;
        }

        .secure-note i {
            color: #479c78;
        }

        /* EMPTY CART */
        .empty-cart {
            max-width: 700px;
            margin: 0 auto;
            padding: 60px 30px;
            text-align: center;
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid var(--border);
            border-radius: 27px;
            box-shadow: var(--shadow);
            animation: fadeUp 0.55s ease both;
        }

        .empty-icon {
            width: 105px;
            height: 105px;
            margin: 0 auto 24px;
            display: grid;
            place-items: center;
            border-radius: 34px;
            background: linear-gradient(135deg, #eee8ff, #fff0f6);
            color: var(--purple);
            font-size: 42px;
        }

        .empty-cart h2 {
            font-family: 'Playfair Display', serif;
            font-size: 30px;
            margin-bottom: 12px;
        }

        .empty-cart p {
            max-width: 420px;
            margin: 0 auto 25px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.9;
        }

        /* FOOTER */
        footer {
            margin-top: auto;
            padding: 26px 5%;
            background: rgba(255, 255, 255, 0.82);
            border-top: 1px solid var(--border);
        }

        .footer-inner {
            max-width: 1200px;
            margin: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .footer-brand {
            color: var(--purple);
            font-size: 15px;
            font-weight: 800;
        }

        .footer-text {
            color: var(--muted);
            font-size: 11px;
            line-height: 1.7;
        }

        .footer-heart {
            color: #e58bad;
        }

        /* PAGE UP / PAGE DOWN */
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
            background: linear-gradient(135deg, var(--purple), #a48ae7);
            color: #fff;
            box-shadow: 0 7px 20px rgba(101, 72, 189, 0.25);
            cursor: pointer;
            font-size: 16px;
            transition: 0.2s ease;
        }

        .page-controls button:hover {
            transform: translateY(-3px);
        }

        /* DARK MODE */
        body.dark-mode {
            --cream: #171421;
            --text: #eee8f7;
            --muted: #b8adc9;
            --white: #242031;
            --border: #393047;
            --purple-light: #393047;
            background: #171421;
            color: var(--text);
        }

        body.dark-mode .navbar {
            background: rgba(36, 32, 49, 0.96);
            border-color: #393047;
        }

        body.dark-mode .logo {
            color: #eee8f7;
        }

        body.dark-mode .nav-links a {
            color: #d0c5df;
        }

        body.dark-mode .nav-links a:hover,
        body.dark-mode .nav-links a.active {
            color: #b9a4ff;
        }

        body.dark-mode .site-tools button {
            background: #393047;
            border-color: #514565;
            color: #eee8f7;
        }

        body.dark-mode .page-header {
            background: linear-gradient(120deg, #302741, #3a293c 55%, #392a36);
            border-color: #514565;
        }

        body.dark-mode .page-header p {
            color: #d0c5df;
        }

        body.dark-mode .page-header h1 span {
            color: #c4b0ff;
        }

        body.dark-mode .eyebrow {
            background: #47385c;
            color: #d4c5ff;
        }

        body.dark-mode .cart-panel,
        body.dark-mode .summary-card,
        body.dark-mode .empty-cart {
            background: #242031;
            border-color: #393047;
        }

        body.dark-mode .panel-header,
        body.dark-mode .summary-header {
            border-color: #393047;
        }

        body.dark-mode .cart-item {
            border-color: #393047;
        }

        body.dark-mode .item-badge,
        body.dark-mode .item-category,
        body.dark-mode .button-secondary {
            background: #393047;
            color: #d4c5ff;
        }

        body.dark-mode .quantity-input {
            background: #171421;
            color: #eee8f7;
            border-color: #514565;
        }

        body.dark-mode .remove-button {
            background: #422b3a;
            border-color: #684052;
            color: #ffb6cb;
        }

        body.dark-mode .remove-button:hover {
            background: #563348;
        }

        body.dark-mode .cart-actions {
            background: #211d2c;
            border-color: #393047;
        }

        body.dark-mode .summary-row {
            color: #c0b5ce;
        }

        body.dark-mode .summary-row strong,
        body.dark-mode .summary-total span {
            color: #eee8f7;
        }

        body.dark-mode .summary-total {
            border-color: #514565;
        }

        body.dark-mode .summary-total strong,
        body.dark-mode .subtotal-price {
            color: #c4b0ff;
        }

        body.dark-mode .empty-icon {
            background: linear-gradient(135deg, #393047, #422b3a);
            color: #c4b0ff;
        }

        body.dark-mode footer {
            background: #100d18;
            border-color: #393047;
        }

        body.dark-mode .message.success {
            background: #20392e;
            border-color: #315b43;
            color: #a9e4bd;
        }

        body.dark-mode .message.error {
            background: #422b3a;
            border-color: #684052;
            color: #ffb6cb;
        }

        /* ANIMATIONS */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(16px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* TABLET */
        @media (max-width: 1000px) {
            .nav-inner {
                flex-direction: column;
                gap: 17px;
                padding: 16px 4%;
            }

            .nav-links {
                justify-content: center;
                gap: 15px 21px;
            }

            .cart-layout {
                grid-template-columns: minmax(0, 1fr) 300px;
                gap: 18px;
            }

            .cart-items {
                padding: 0 18px;
            }

            .cart-item {
                grid-template-columns: 72px minmax(0, 1fr);
                gap: 13px;
            }

            .item-image {
                width: 72px;
                height: 82px;
            }
        }

        /* MOBILE */
        @media (max-width: 760px) {
            .container {
                width: 92%;
                margin: 28px auto 45px;
            }

            .page-header {
                padding: 29px 23px;
                border-radius: 23px;
            }

            .page-header h1 {
                font-size: 34px;
            }

            .cart-layout {
                grid-template-columns: 1fr;
            }

            .summary-card {
                position: static;
            }

            .panel-header {
                padding: 21px 19px;
            }

            .cart-items {
                padding: 0 17px;
            }

            .cart-item {
                grid-template-columns: 75px minmax(0, 1fr);
                gap: 13px;
            }

            .item-image {
                width: 75px;
                height: 83px;
            }

            .item-name {
                font-size: 17px;
            }

            .cart-actions {
                padding: 18px 17px 20px;
            }

            .empty-cart {
                padding: 48px 21px;
            }

            .empty-cart h2 {
                font-size: 26px;
            }

            .footer-inner {
                justify-content: center;
                text-align: center;
                flex-direction: column;
            }

            .site-tools {
                width: 100%;
            }

            .page-controls {
                right: 14px;
                bottom: 16px;
                gap: 8px;
            }

            .page-controls button {
                width: 42px;
                height: 42px;
                border-radius: 13px;
            }
        }

        @media (max-width: 430px) {
            .nav-links {
                gap: 13px;
            }

            .nav-links a {
                font-size: 11px;
            }

            .page-header h1 {
                font-size: 30px;
            }

            .item-top {
                flex-direction: column;
                gap: 4px;
            }

            .item-bottom {
                align-items: flex-start;
                flex-direction: column;
            }

            .item-subtotal {
                text-align: left;
            }

            .cart-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .cart-actions .button {
                width: 100%;
            }

            .item-badge {
                font-size: 10px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body>

<!-- NAVIGATION -->
<nav class="navbar">
    <div class="nav-inner">

        <a href="index.php" class="logo">
            <span class="logo-icon">
                <i class="fa-solid fa-bag-shopping"></i>
            </span>

            <span class="logo-name">Need<span>ify</span></span>
        </a>

        <div class="nav-links">
            <a href="index.php">
                <i class="fa-solid fa-house"></i> Home
            </a>

            <a href="products.php">
                <i class="fa-solid fa-store"></i> Products
            </a>

            <a href="cart.php" class="active">
                <i class="fa-solid fa-cart-shopping"></i> Cart
            </a>

            <?php if (isset($_SESSION["user_id"])): ?>

                <a href="dashboard.php">
                    <i class="fa-solid fa-grid-2"></i> Dashboard
                </a>

                <a href="my-needs.php">
                    <i class="fa-solid fa-clipboard-list"></i> My Needs
                </a>

                <a href="logout.php" class="logout">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    Logout
                </a>

            <?php else: ?>

                <a href="login.php">Login</a>

                <a href="signup.php" class="signup">
                    <i class="fa-solid fa-user-plus"></i> Sign Up
                </a>

            <?php endif; ?>
        </div>

        <!-- FONT SIZE AND THEME CONTROLS -->
        <div class="site-tools" aria-label="Display settings">

            <button type="button" id="fontDecrease"
                    title="Decrease font size"
                    aria-label="Decrease font size">
                <i class="fa-solid fa-minus"></i>
            </button>

            <button type="button" id="fontReset"
                    title="Reset font size"
                    aria-label="Reset font size">
                <i class="fa-solid fa-rotate-left"></i>
            </button>

            <button type="button" id="fontIncrease"
                    title="Increase font size"
                    aria-label="Increase font size">
                <i class="fa-solid fa-plus"></i>
            </button>

            <button type="button" id="themeToggle"
                    title="Switch to dark mode"
                    aria-label="Switch to dark mode">
                <i class="fa-solid fa-moon"></i>
            </button>

        </div>

    </div>
</nav>

<!-- MAIN CONTENT -->
<main class="container">

    <section class="page-header">
        <div class="header-content">

            <div class="eyebrow">
                <i class="fa-solid fa-heart"></i>
                YOUR SHOPPING SPACE
            </div>

            <h1>
                My Shopping <span>Cart.</span>
            </h1>

            <p>
                Your favourite finds, all in one place. Review your products,
                adjust quantities, and make sure everything is ready before checkout.
            </p>

        </div>
    </section>

    <!-- FLASH MESSAGE -->
    <?php if ($success !== ""): ?>

        <div class="message success" role="status">
            <i class="fa-solid fa-circle-check"></i>
            <div><?php echo e($success); ?></div>
        </div>

    <?php endif; ?>

    <?php if ($error !== ""): ?>

        <div class="message error" role="alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div><?php echo e($error); ?></div>
        </div>

    <?php endif; ?>

    <?php if ($item_count > 0): ?>

        <div class="cart-layout">

            <!-- CART ITEMS -->
            <section class="cart-panel">

                <div class="panel-header">
                    <div>
                        <h2>Your Items</h2>
                        <p>Review and update your selected products.</p>
                    </div>

                    <span class="item-badge">
                        <?php echo $total_quantity; ?>
                        <?php echo $total_quantity === 1 ? "ITEM" : "ITEMS"; ?>
                    </span>
                </div>

                <!-- UPDATE CART FORM -->
                <form action="cart.php" method="POST" id="update-cart-form">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo e(csrf_token()); ?>"
                    >

                    <input type="hidden" name="action" value="update">

                    <div class="cart-items">

                        <?php foreach ($cart_items as $item): ?>

                            <?php
                                $image_path = trim((string)($item["image"] ?? ""));
                            ?>

                            <article class="cart-item">

                                <!-- PRODUCT IMAGE -->
                                <div class="item-image">

                                    <?php if ($image_path !== ""): ?>

                                        <img
                                            src="<?php echo e($image_path); ?>"
                                            alt="<?php echo e($item["name"]); ?>"
                                            loading="lazy"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';"
                                        >

                                        <div style="display:none; width:100%; height:100%; place-items:center;">
                                            <i class="fa-solid fa-bag-shopping"></i>
                                        </div>

                                    <?php else: ?>

                                        <i class="fa-solid fa-bag-shopping"></i>

                                    <?php endif; ?>

                                </div>

                                <!-- PRODUCT DETAILS -->
                                <div class="item-details">

                                    <div class="item-top">

                                        <div>

                                            <h3 class="item-name">
                                                <?php echo e($item["name"]); ?>
                                            </h3>

                                            <span class="item-category">
                                                <?php echo e($item["category"] ?: "Essentials"); ?>
                                            </span>

                                            <p class="item-unit-price">
                                                Rs. <?php echo formatPrice($item["price"]); ?>
                                                per item
                                            </p>

                                        </div>

                                        <div class="item-subtotal">

                                            <div class="subtotal-label">SUBTOTAL</div>

                                            <div class="subtotal-price">
                                                Rs. <?php echo formatPrice($item["subtotal"]); ?>
                                            </div>

                                        </div>

                                    </div>

                                    <div class="item-bottom">

                                        <div class="quantity-control">

                                            <label for="quantity-<?php echo (int)$item["id"]; ?>">
                                                Qty.
                                            </label>

                                            <input
                                                type="number"
                                                class="quantity-input"
                                                id="quantity-<?php echo (int)$item["id"]; ?>"
                                                name="quantities[<?php echo (int)$item["id"]; ?>]"
                                                value="<?php echo (int)$item["quantity"]; ?>"
                                                min="1"
                                                max="<?php echo (int)$item["stock"]; ?>"
                                                required
                                            >

                                        </div>

                                        <!-- REMOVE PRODUCT -->
                                        <button
                                            type="submit"
                                            form="remove-<?php echo (int)$item["id"]; ?>"
                                            class="remove-button"
                                        >
                                            <i class="fa-regular fa-trash-can"></i>
                                            Remove
                                        </button>

                                    </div>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                    <div class="cart-actions">

                        <a href="products.php" class="button button-secondary">
                            <i class="fa-solid fa-arrow-left"></i>
                            Continue Shopping
                        </a>

                        <button type="submit" class="button button-primary">
                            <i class="fa-solid fa-rotate"></i>
                            Update Cart
                        </button>

                    </div>

                </form>

                <!-- SEPARATE REMOVE FORMS -->
                <?php foreach ($cart_items as $item): ?>

                    <form
                        id="remove-<?php echo (int)$item["id"]; ?>"
                        action="cart.php"
                        method="POST"
                        hidden
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo e(csrf_token()); ?>"
                        >

                        <input type="hidden" name="action" value="remove">

                        <input
                            type="hidden"
                            name="product_id"
                            value="<?php echo (int)$item["id"]; ?>"
                        >

                    </form>

                <?php endforeach; ?>

            </section>

            <!-- ORDER SUMMARY -->
            <aside class="summary-card">

                <div class="summary-header">
                    <h2>Order Summary</h2>
                    <p>A quick overview of your shopping cart.</p>
                </div>

                <div class="summary-body">

                    <div class="summary-row">
                        <span>Unique products</span>
                        <strong><?php echo $item_count; ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Total quantity</span>
                        <strong><?php echo $total_quantity; ?> items</strong>
                    </div>

                    <div class="summary-row">
                        <span>Subtotal</span>
                        <strong>Rs. <?php echo formatPrice($total_amount); ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Delivery</span>
                        <strong>Calculated at checkout</strong>
                    </div>

                    <div class="summary-row summary-total">
                        <span>Estimated Total</span>
                        <strong>Rs. <?php echo formatPrice($total_amount); ?></strong>
                    </div>

                    <a href="place-order.php"
                       class="button button-primary checkout-button">
                        Proceed to Checkout
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>

                    <a href="products.php"
                       class="button button-secondary continue-button">
                        <i class="fa-solid fa-plus"></i>
                        Add More Products
                    </a>

                    <div class="secure-note">
                        <i class="fa-solid fa-shield-halved"></i>
                        Review your order before confirming your purchase.
                    </div>

                </div>

            </aside>

        </div>

    <?php else: ?>

        <!-- EMPTY CART -->
        <section class="empty-cart">

            <div class="empty-icon">
                <i class="fa-solid fa-basket-shopping"></i>
            </div>

            <h2>Your cart is waiting for a little love!</h2>

            <p>
                Looks like you haven't added anything yet.
                Explore the Needify collection and find something
                that belongs in your shopping cart.
            </p>

            <a href="products.php" class="button button-primary">
                <i class="fa-solid fa-store"></i>
                Explore Products
                <i class="fa-solid fa-arrow-right"></i>
            </a>

        </section>

    <?php endif; ?>

</main>

<!-- PAGE UP / PAGE DOWN BUTTONS -->
<div class="page-controls">

    <button type="button" id="pageUp"
            aria-label="Page Up" title="Page Up">
        <i class="fa-solid fa-arrow-up"></i>
    </button>

    <button type="button" id="pageDown"
            aria-label="Page Down" title="Page Down">
        <i class="fa-solid fa-arrow-down"></i>
    </button>

</div>

<!-- FOOTER -->
<footer>

    <div class="footer-inner">

        <div class="footer-brand">
            <i class="fa-solid fa-bag-shopping"></i>
            Needify
        </div>

        <p class="footer-text">
            &copy; <?php echo date("Y"); ?> Needify. All rights reserved.
            Made with <span class="footer-heart">♥</span> for smarter shopping.
        </p>

    </div>

</footer>

<!-- DISPLAY SETTINGS SCRIPT -->
<script>
document.addEventListener("DOMContentLoaded", function () {

    const body = document.body;

    const themeToggle = document.getElementById("themeToggle");
    const fontDecrease = document.getElementById("fontDecrease");
    const fontReset = document.getElementById("fontReset");
    const fontIncrease = document.getElementById("fontIncrease");

    const pageUp = document.getElementById("pageUp");
    const pageDown = document.getElementById("pageDown");

    // DARK MODE / LIGHT MODE
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

    // Load saved theme
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

    // Decrease font size
    fontDecrease?.addEventListener("click", function () {

        fontLevel = Math.max(-2, fontLevel - 1);

        applyFontLevel();
    });

    // Increase font size
    fontIncrease?.addEventListener("click", function () {

        fontLevel = Math.min(3, fontLevel + 1);

        applyFontLevel();
    });

    // Reset font size
    fontReset?.addEventListener("click", function () {

        fontLevel = 0;

        applyFontLevel();
    });

    // Load saved font size
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

});
</script>

</body>
</html>
