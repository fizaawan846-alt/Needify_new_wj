<?php
/**
 * STAGE 2 VERIFICATION TEST SUITE
 * Tests public homepage rendering, shared header, shared footer,
 * accessibility controls, links, categories, products, WhatsApp, and scripts.
 */

echo "=====================================================\n";
echo "NEEDIFY - STAGE 2 AUTOMATED VERIFICATION SUITE\n";
echo "=====================================================\n\n";

$passCount = 0;
$failCount = 0;

function assert_test(string $name, bool $condition, string $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] $name\n";
        $passCount++;
    } else {
        echo "[FAIL] $name" . ($details ? " ($details)" : "") . "\n";
        $failCount++;
    }
}

// 1. Check physical existence of assets and templates
assert_test("assets/css/main.css exists", file_exists(__DIR__ . '/../assets/css/main.css'));
assert_test("assets/js/main.js exists", file_exists(__DIR__ . '/../assets/js/main.js'));
assert_test("includes/header.php exists", file_exists(__DIR__ . '/../includes/header.php'));
assert_test("includes/footer.php exists", file_exists(__DIR__ . '/../includes/footer.php'));
assert_test("index.php exists", file_exists(__DIR__ . '/../index.php'));

// 2. Render index.php in guest mode
$_SERVER["REQUEST_METHOD"] = "GET";
$_SERVER["HTTP_HOST"] = "localhost";
$_SERVER["SERVER_NAME"] = "localhost";
$_SERVER["REQUEST_URI"] = "/Needify/index.php";
$_SERVER["PHP_SELF"] = "/Needify/index.php";

ob_start();
include __DIR__ . "/../index.php";
$html = ob_get_clean();

// Check Core Components
assert_test("HTML5 Doctype present", str_contains($html, "<!DOCTYPE html>"));
assert_test("Meta viewport present", str_contains($html, 'name="viewport"'));
assert_test("Brand name present", str_contains($html, 'Need<span class="dot">ify.</span>'));
assert_test("Tagline present in footer", str_contains($html, 'A Little More Joy in Every Need'));

// Navigation Links
assert_test("Home link in nav", str_contains($html, 'href="index.php"'));
assert_test("Products link in nav", str_contains($html, 'href="products.php"'));
assert_test("Browse Needs link in nav", str_contains($html, 'href="needs.php"'));
assert_test("Contact link in nav", str_contains($html, 'href="contact.php"'));

// Accessibility Controls
assert_test("Skip to content link present", str_contains($html, 'class="skip-link"'));
assert_test("A- Font Size Control", str_contains($html, 'data-size="sm"'));
assert_test("A Font Size Control", str_contains($html, 'data-size="md"'));
assert_test("A+ Font Size Control", str_contains($html, 'data-size="lg"'));
assert_test("Theme Toggle Button present", str_contains($html, 'theme-toggle-btn'));

// Cart Link with counter element
assert_test("Cart nav button present", str_contains($html, 'cart-nav-btn'));

// Sections
assert_test("Hero section present", str_contains($html, 'class="hero-section"'));
assert_test("Trust & Value Props Strip present", str_contains($html, 'class="trust-strip"'));
assert_test("Categories section with dynamic items", str_contains($html, 'category-grid'));
assert_test("Featured products section with DB items", str_contains($html, 'products-grid'));
assert_test("Product prices in PKR format", str_contains($html, 'Rs. '));
assert_test("How It Works section present", str_contains($html, 'How Needify Works'));
assert_test("University of Sargodha Map iframe present", str_contains($html, 'google.com/maps'));
assert_test("Call-to-Action section present", str_contains($html, 'class="cta-section"'));

// Footer & Integrations
assert_test("Floating WhatsApp button (+92 335 0093455)", str_contains($html, 'https://wa.me/923350093455'));
assert_test("WhatsApp link has rel=noopener noreferrer", str_contains($html, 'rel="noopener noreferrer"'));
assert_test("WhatsApp link has target=_blank", str_contains($html, 'target="_blank"'));
assert_test("Page scroll controls present", str_contains($html, 'pageScrollUp') && str_contains($html, 'pageScrollDown'));
assert_test("Needify AI chatbot widget present", str_contains($html, 'needify-ai'));
assert_test("Master stylesheet linked", str_contains($html, 'assets/css/main.css'));
assert_test("Master javascript linked", str_contains($html, 'assets/js/main.js'));

echo "\n=====================================================\n";
echo "STAGE 2 SUMMARY: Passed: $passCount, Failed: $failCount\n";
echo "=====================================================\n";

if ($failCount > 0) {
    exit(1);
}
