<?php
/**
 * STAGE 4 VERIFICATION TEST SUITE
 * Tests products catalog, search, category filters, price sorting,
 * product-details.php view, stock validation, and admin guards.
 */

echo "=====================================================\n";
echo "NEEDIFY - STAGE 4 AUTOMATED VERIFICATION SUITE\n";
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

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// ---------------------------------------------------------------------
// 1. Files Verification
// ---------------------------------------------------------------------
assert_test("products.php exists", file_exists(__DIR__ . '/../products.php'));
assert_test("product-details.php exists", file_exists(__DIR__ . '/../product-details.php'));
assert_test("admin-products.php exists", file_exists(__DIR__ . '/../admin-products.php'));
assert_test("admin-category.php exists", file_exists(__DIR__ . '/../admin-category.php'));

// ---------------------------------------------------------------------
// 2. Database Products Query Verification
// ---------------------------------------------------------------------
$dbProducts = [];
$res = $conn->query("SELECT id, name, category, price, stock, is_active FROM products WHERE is_active = 1");
while ($r = $res->fetch_assoc()) {
    $dbProducts[] = $r;
}
assert_test("Active products exist in database", count($dbProducts) >= 7);
$firstProduct = $dbProducts[0];
assert_test("Product has valid price > 0", (float)$firstProduct['price'] > 0);
assert_test("Product price formats to PKR", str_starts_with(format_price($firstProduct['price']), 'Rs. '));

// ---------------------------------------------------------------------
// 3. Products Catalog Default Render
// ---------------------------------------------------------------------
$_SERVER["REQUEST_METHOD"] = "GET";
$_SERVER["HTTP_HOST"] = "localhost";
$_SERVER["SERVER_NAME"] = "localhost";
$_SERVER["REQUEST_URI"] = "/Needify/products.php";
$_SERVER["PHP_SELF"] = "/Needify/products.php";
$_GET = [];

ob_start();
include __DIR__ . "/../products.php";
$catalogHtml = ob_get_clean();

assert_test("products.php renders page title", str_contains($catalogHtml, "Explore Our Products"));
assert_test("products.php renders search input", str_contains($catalogHtml, 'name="search"'));
assert_test("products.php renders category filter", str_contains($catalogHtml, 'name="category"'));
assert_test("products.php renders sort dropdown", str_contains($catalogHtml, 'name="sort"'));
assert_test("products.php renders in-stock checkbox", str_contains($catalogHtml, 'name="in_stock"'));
assert_test("products.php renders product cards", str_contains($catalogHtml, 'class="product-card"'));
assert_test("products.php links to product-details.php", str_contains($catalogHtml, 'product-details.php?id='));
assert_test("products.php renders Add to Cart forms", str_contains($catalogHtml, 'action="cart.php"'));

// ---------------------------------------------------------------------
// 4. Products Catalog Search Filtering
// ---------------------------------------------------------------------
$_GET = ['search' => 'Balloons'];
$_SERVER["REQUEST_URI"] = "/Needify/products.php?search=Balloons";

ob_start();
include __DIR__ . "/../products.php";
$searchHtml = ob_get_clean();

assert_test("Search for 'Balloons' matches Party Balloons", str_contains($searchHtml, "Party Balloons"));
assert_test("Search query is echoed in filter input", str_contains($searchHtml, 'value="Balloons"'));

// ---------------------------------------------------------------------
// 5. Products Catalog Category Filtering
// ---------------------------------------------------------------------
$_GET = ['category' => 'Food & Snacks'];
$_SERVER["REQUEST_URI"] = "/Needify/products.php?category=Food+%26+Snacks";

ob_start();
include __DIR__ . "/../products.php";
$catHtml = ob_get_clean();

assert_test("Category filter 'Food & Snacks' matches Snack Pack", str_contains($catHtml, "Snack Pack"));

// ---------------------------------------------------------------------
// 6. Products Catalog Empty Search State
// ---------------------------------------------------------------------
$_GET = ['search' => 'NonExistentItem999XYZ'];
$_SERVER["REQUEST_URI"] = "/Needify/products.php?search=NonExistentItem999XYZ";

ob_start();
include __DIR__ . "/../products.php";
$emptyHtml = ob_get_clean();

assert_test("Empty search shows 'No Products Found'", str_contains($emptyHtml, "No Products Found"));
assert_test("Empty state includes reset button", str_contains($emptyHtml, 'href="products.php"'));

// ---------------------------------------------------------------------
// 7. Product Details Page Tests (product-details.php)
// ---------------------------------------------------------------------
// Test invalid ID returns 404
$_GET = ['id' => 'invalid_id'];
$_SERVER["REQUEST_URI"] = "/Needify/product-details.php?id=invalid_id";

ob_start();
include __DIR__ . "/../product-details.php";
$invalidHtml = ob_get_clean();
assert_test("Invalid ID returns Product Not Found", str_contains($invalidHtml, "Product Not Found"));

// Test non-existent ID
$_GET = ['id' => 999999];
$_SERVER["REQUEST_URI"] = "/Needify/product-details.php?id=999999";

ob_start();
include __DIR__ . "/../product-details.php";
$missingHtml = ob_get_clean();
assert_test("Non-existent ID returns Product Unavailable", str_contains($missingHtml, "Product Unavailable"));

// Test valid product ID (ID 1: Paper Plates)
$_GET = ['id' => 1];
$_SERVER["REQUEST_URI"] = "/Needify/product-details.php?id=1";

ob_start();
include __DIR__ . "/../product-details.php";
$detailHtml = ob_get_clean();

assert_test("Valid product-details.php renders product name", str_contains($detailHtml, "Paper Plates"));
assert_test("Valid product-details.php renders category", str_contains($detailHtml, "Party Supplies"));
assert_test("Valid product-details.php renders PKR price", str_contains($detailHtml, "Rs. 500.00"));
assert_test("Valid product-details.php renders stock availability", str_contains($detailHtml, "In Stock"));
assert_test("Valid product-details.php renders quantity selector", str_contains($detailHtml, 'id="quantityInput"'));
assert_test("Valid product-details.php renders Add to Cart form", str_contains($detailHtml, 'action="cart.php"'));
assert_test("Valid product-details.php renders CSRF field", str_contains($detailHtml, 'name="csrf_token"'));
assert_test("Valid product-details.php renders WhatsApp inquiry", str_contains($detailHtml, 'wa.me/923350093455'));

// ---------------------------------------------------------------------
// 8. Admin Products & Categories Access Guards
// ---------------------------------------------------------------------
unset($_SESSION['admin_id'], $_SESSION['admin_username']);
assert_test("Guest is not admin logged in", is_admin_logged_in() === false);

$_SESSION['admin_id'] = 1;
$_SESSION['admin_username'] = 'admin';
assert_test("Admin session validates is_admin_logged_in()", is_admin_logged_in() === true);
unset($_SESSION['admin_id'], $_SESSION['admin_username']);

$adminProductsCode = file_get_contents(__DIR__ . "/../admin-products.php");
assert_test("admin-products.php enforces require_admin_login()", str_contains($adminProductsCode, 'require_admin_login();'));

$adminCategoryCode = file_get_contents(__DIR__ . "/../admin-category.php");
assert_test("admin-category.php enforces require_admin_login()", str_contains($adminCategoryCode, 'require_admin_login();'));

echo "\n=====================================================\n";
echo "STAGE 4 SUMMARY: Passed: $passCount, Failed: $failCount\n";
echo "=====================================================\n";

if ($failCount > 0) {
    exit(1);
}
