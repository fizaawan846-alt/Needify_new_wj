<?php
/**
 * NEEDIFY - SHARED HEADER COMPONENT
 * Reusable header with navigation, branding, accessibility controls,
 * cart counter, and responsive mobile menu.
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Calculate cart count
$cartItemCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $qty) {
        $cartItemCount += (int)$qty;
    }
}

$pageTitle = isset($pageTitle) ? $pageTitle . " | Needify" : "Needify | A Little More Joy in Every Need";
$pageDescription = $pageDescription ?? "Discover everyday essentials, share your needs and enjoy a simpler shopping experience on Needify.";
$currentScript = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#FAF7FD" id="themeColorMeta">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="author" content="Needify">
    <?= csrf_meta() ?>

    <title><?= e($pageTitle) ?></title>

    <link rel="icon" type="image/png" href="favicon.png">

    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">

    <!-- Needify Master Stylesheet -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/dark-mode.css">
</head>
<body>
    <!-- Skip to main content for accessibility -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- SITE HEADER -->
    <header class="site-header">
        <div class="container nav-container">
            <!-- Brand Logo -->
            <a href="index.php" class="brand-logo" aria-label="Needify Home">
                <span class="brand-icon">
                    <i class="fa-solid fa-heart"></i>
                </span>
                <span>Need<span class="dot">ify.</span></span>
            </a>

            <!-- Navigation Links Menu -->
            <nav class="nav-menu" id="siteNavMenu" aria-label="Primary Navigation">
                <a href="index.php" class="nav-link <?= ($currentScript === 'index.php') ? 'active' : '' ?>">Home</a>
                <a href="products.php" class="nav-link <?= ($currentScript === 'products.php' || $currentScript === 'product-details.php') ? 'active' : '' ?>">Products</a>
                <a href="needs.php" class="nav-link <?= ($currentScript === 'needs.php' || $currentScript === 'post-need.php' || $currentScript === 'my-needs.php') ? 'active' : '' ?>">Browse Needs</a>
                <a href="contact-us.php" class="nav-link <?= ($currentScript === 'contact.php') ? 'active' : '' ?>">Contact</a>

                <!-- Mobile Only Auth Links -->
                <div class="mobile-only-auth" style="display: none;">
                    <?php if (is_logged_in()): ?>
                        <a href="dashboard.php" class="btn btn-outline" style="width: 100%; margin-bottom: 8px;">
                            <i class="fa-solid fa-gauge"></i> Dashboard
                        </a>
                        <a href="logout.php" class="btn btn-secondary" style="width: 100%;">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline" style="width: 100%; margin-bottom: 8px;">Log In</a>
                        <a href="signup.php" class="btn btn-primary" style="width: 100%;">Create Account</a>
                    <?php endif; ?>
                </div>
            </nav>

            <!-- Actions & Accessibility Tools -->
            <div class="nav-actions">
                <!-- Accessibility Controls (Font sizing & Theme toggle) -->
                <div class="access-tools" role="group" aria-label="Appearance & Accessibility Controls">
                    <button type="button" class="tool-btn font-btn" data-size="sm" title="Decrease font size" aria-label="Decrease font size">A-</button>
                    <button type="button" class="tool-btn font-btn active" data-size="md" title="Default font size" aria-label="Default font size">A</button>
                    <button type="button" class="tool-btn font-btn" data-size="lg" title="Increase font size" aria-label="Increase font size">A+</button>
                    <button type="button" class="tool-btn theme-toggle-btn" title="Toggle dark or light theme" aria-label="Toggle dark or light theme">
                        <i class="fa-solid fa-moon"></i>
                    </button>
                </div>

                <!-- Shopping Cart Icon with Badge -->
                <a href="cart.php" class="cart-nav-btn" aria-label="Shopping Cart (<?= $cartItemCount ?> items)" title="View Cart">
                    <i class="fa-solid fa-basket-shopping"></i>
                    <?php if ($cartItemCount > 0): ?>
                        <span class="cart-badge" id="navCartBadge"><?= $cartItemCount ?></span>
                    <?php endif; ?>
                </a>

                <!-- Desktop User Auth Buttons -->
                <?php if (is_logged_in()): ?>
                    <a href="dashboard.php" class="btn btn-outline" style="padding: 8px 16px;">
                        <i class="fa-regular fa-user"></i> Dashboard
                    </a>
                    <a href="logout.php" class="btn btn-secondary" style="padding: 8px 14px;">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline" style="padding: 8px 16px;">Log In</a>
                    <a href="signup.php" class="btn btn-primary" style="padding: 8px 18px;">Sign Up</a>
                <?php endif; ?>

                <!-- Mobile Hamburger Toggle Button -->
                <button type="button" class="mobile-toggle" id="mobileMenuToggle" aria-label="Toggle navigation menu" aria-expanded="false">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content Area Starts -->
    <main id="main-content">
