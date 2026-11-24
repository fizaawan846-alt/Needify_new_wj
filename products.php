<?php
/**
 * NEEDIFY - PRODUCTS CATALOG
 * Real database-driven product listings, live search, category filtering,
 * price sorting, stock validation, and Add to Cart integration.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = "Explore Products";
$pageDescription = "Browse authentic everyday essentials, party supplies, study accessories, and food items on Needify with Cash on Delivery.";

// -------------------------------------------------------------
// Read Filter & Search Query Parameters
// -------------------------------------------------------------
$search = sanitize_text($_GET['search'] ?? '');
$selectedCategory = sanitize_text($_GET['category'] ?? '');
$sort = sanitize_text($_GET['sort'] ?? 'newest');
$inStockOnly = isset($_GET['in_stock']) && $_GET['in_stock'] === '1';

// -------------------------------------------------------------
// Fetch All Categories for Filter Dropdown / Tabs
// -------------------------------------------------------------
$categories = [];
$catQuery = "
    SELECT c.id, c.name, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON p.category = c.name AND p.is_active = 1
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
";
$catResult = $conn->query($catQuery);
if ($catResult) {
    while ($row = $catResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

// -------------------------------------------------------------
// Build Dynamic SQL Query with Prepared Statement
// -------------------------------------------------------------
$conditions = ["is_active = 1"];
$params = [];
$types = "";

if (!empty($search)) {
    $conditions[] = "(name LIKE ? OR description LIKE ?)";
    $searchWildcard = "%" . $search . "%";
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $types .= "ss";
}

if (!empty($selectedCategory)) {
    $conditions[] = "category = ?";
    $params[] = $selectedCategory;
    $types .= "s";
}

if ($inStockOnly) {
    $conditions[] = "stock > 0";
}

$orderBy = "id DESC";
if ($sort === 'price_asc') {
    $orderBy = "price ASC";
} elseif ($sort === 'price_desc') {
    $orderBy = "price DESC";
} elseif ($sort === 'name_asc') {
    $orderBy = "name ASC";
}

$whereClause = implode(" AND ", $conditions);
$sql = "SELECT id, name, category, description, price, image, stock, created_at FROM products WHERE {$whereClause} ORDER BY {$orderBy}";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();

$totalCount = count($products);

require_once __DIR__ . '/includes/header.php';
?>

<!-- =====================================================
     PRODUCTS CATALOG HERO & FILTER BAR
===================================================== -->
<section style="background: var(--grad-hero); padding: 48px 0 36px; border-bottom: 1px solid var(--border-color);">
    <div class="container">
        <div style="max-width: 700px; margin-bottom: 28px;">
            <span class="section-pill">The Needify Catalog</span>
            <h1 style="font-size: 2.6rem; margin-bottom: 10px;">Explore Our Products</h1>
            <p style="font-size: 1.05rem; color: var(--text-secondary);">
                Browse authentic everyday essentials, party supplies, and student items available for fast campus delivery.
            </p>
        </div>

        <!-- Filter & Search Bar Form -->
        <form action="products.php" method="GET" class="card" style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; box-shadow: var(--shadow-md);">
            <div style="display: grid; grid-template-columns: 2fr 1.2fr 1fr auto auto; gap: 14px; align-items: center;">
                <!-- Search Keyword -->
                <div class="form-input-wrap">
                    <i class="fa-solid fa-magnifying-glass form-input-icon"></i>
                    <input type="text"
                           name="search"
                           class="form-control"
                           placeholder="Search products by name or keyword..."
                           value="<?= e($search) ?>">
                </div>

                <!-- Category Filter -->
                <div>
                    <select name="category" class="form-control" style="padding-left: 14px;">
                        <option value="">All Categories (<?= array_sum(array_column($categories, 'product_count')) ?>)</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat['name']) ?>" <?= ($selectedCategory === $cat['name']) ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?> (<?= (int)$cat['product_count'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Price / Name Sorting -->
                <div>
                    <select name="sort" class="form-control" style="padding-left: 14px;">
                        <option value="newest" <?= ($sort === 'newest') ? 'selected' : '' ?>>Newest Arrivals</option>
                        <option value="price_asc" <?= ($sort === 'price_asc') ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= ($sort === 'price_desc') ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="name_asc" <?= ($sort === 'name_asc') ? 'selected' : '' ?>>Name: A to Z</option>
                    </select>
                </div>

                <!-- In Stock Toggle -->
                <label style="display: flex; align-items: center; gap: 8px; font-size: 0.88rem; font-weight: 600; cursor: pointer; white-space: nowrap; padding: 0 4px;">
                    <input type="checkbox" name="in_stock" value="1" <?= $inStockOnly ? 'checked' : '' ?> style="accent-color: var(--primary); width: 17px; height: 17px;">
                    <span>In Stock Only</span>
                </label>

                <!-- Filter Actions -->
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn btn-primary" style="padding: 10px 18px;">
                        <i class="fa-solid fa-filter"></i> Apply
                    </button>
                    <?php if (!empty($search) || !empty($selectedCategory) || $inStockOnly || $sort !== 'newest'): ?>
                        <a href="products.php" class="btn btn-outline" style="padding: 10px 14px;" title="Reset filters">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</section>

<!-- =====================================================
     PRODUCTS GRID SECTION
===================================================== -->
<section class="section">
    <div class="container">
        <!-- Result Bar Status -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; flex-wrap: wrap; gap: 12px;">
            <div style="font-weight: 700; color: var(--text-primary);">
                Showing <span style="color: var(--primary);"><?= $totalCount ?></span> <?= ($totalCount === 1) ? 'product' : 'products' ?>
                <?php if (!empty($selectedCategory)): ?>
                    in <span class="section-pill" style="margin-left: 6px;"><?= e($selectedCategory) ?></span>
                <?php endif; ?>
                <?php if (!empty($search)): ?>
                    matching "<span style="color: var(--accent-pink);"><?= e($search) ?></span>"
                <?php endif; ?>
            </div>

            <div style="font-size: 0.88rem; color: var(--text-muted);">
                <i class="fa-solid fa-truck"></i> Fast Cash on Delivery across UOS Campus
            </div>
        </div>

        <?php if ($totalCount > 0): ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <!-- Image Container -->
                        <div class="product-image-wrap">
                            <span class="product-category-tag"><?= e($product['category']) ?></span>
                            <a href="product-details.php?id=<?= (int)$product['id'] ?>" aria-label="View details for <?= e($product['name']) ?>">
                                <img src="<?= e(product_image_url($product['image'])) ?>"
                                     alt="<?= e($product['name']) ?>"
                                     class="product-image"
                                     loading="lazy"
                                     onerror="this.onerror=null; this.src='assets/images/product-placeholder.svg';">
                            </a>
                        </div>

                        <!-- Card Body -->
                        <div class="product-body">
                            <h3 class="product-title">
                                <a href="product-details.php?id=<?= (int)$product['id'] ?>" style="color: inherit;">
                                    <?= e($product['name']) ?>
                                </a>
                            </h3>
                            <p class="product-desc"><?= e($product['description'] ?? 'Verified campus product available on Needify.') ?></p>

                            <div class="product-footer">
                                <div>
                                    <div class="product-price"><?= format_price($product['price']) ?></div>
                                    <span class="stock-indicator <?= ((int)$product['stock'] > 0) ? 'stock-in' : 'stock-out' ?>">
                                        <?= ((int)$product['stock'] > 0) ? '<i class="fa-solid fa-circle-check"></i> ' . (int)$product['stock'] . ' in stock' : '<i class="fa-solid fa-circle-xmark"></i> Out of stock' ?>
                                    </span>
                                </div>

                                <div style="display: flex; gap: 8px;">
                                    <a href="product-details.php?id=<?= (int)$product['id'] ?>"
                                       class="btn btn-outline"
                                       style="padding: 8px 12px; font-size: 0.85rem;"
                                       title="View product details">
                                        <i class="fa-solid fa-circle-info"></i>
                                    </a>

                                    <?php if ((int)$product['stock'] > 0): ?>
                                        <form action="cart.php" method="POST" style="display: inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <button type="submit" class="btn btn-primary" style="padding: 8px 14px; font-size: 0.85rem;" title="Add to Cart">
                                                <i class="fa-solid fa-cart-plus"></i> Add
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-outline" disabled style="padding: 8px 12px; font-size: 0.82rem; opacity: 0.5;">
                                            Sold Out
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Empty Filter State -->
            <div style="text-align: center; padding: 60px 20px; background: var(--bg-surface); border: 1px dashed var(--border-color); border-radius: var(--radius-lg); margin-top: 20px;">
                <div style="width: 70px; height: 70px; border-radius: var(--radius-full); background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 16px;">
                    <i class="fa-solid fa-box-open"></i>
                </div>
                <h3 style="font-size: 1.4rem; margin-bottom: 8px;">No Products Found</h3>
                <p style="color: var(--text-secondary); max-width: 480px; margin: 0 auto 20px;">
                    We couldn't find any items matching your active search or filters. Try adjusting your query or resetting filters.
                </p>
                <a href="products.php" class="btn btn-primary">
                    <i class="fa-solid fa-rotate-left"></i> View All Products
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
