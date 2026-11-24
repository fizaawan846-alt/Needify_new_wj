<?php
/**
 * NEEDIFY - PRODUCT DETAILS
 * Comprehensive product details view, image showcase, stock checks,
 * quantity selector, Add to Cart form, and related items.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Validate Product ID
$productId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$productId || $productId <= 0) {
    http_response_code(404);
    $pageTitle = "Product Not Found";
    require_once __DIR__ . '/includes/header.php';
    ?>
    <section class="section" style="text-align: center; padding: 100px 20px;">
        <div class="container">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--bg-muted); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 20px;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h1 style="font-size: 2.2rem; margin-bottom: 12px;">Product Not Found</h1>
            <p style="color: var(--text-secondary); max-width: 480px; margin: 0 auto 24px;">
                The product you are looking for does not exist, has been removed, or the link is invalid.
            </p>
            <a href="products.php" class="btn btn-primary">
                <i class="fa-solid fa-arrow-left"></i> Back to Products
            </a>
        </div>
    </section>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    return;
}

// Fetch Product Details from Database
$stmt = $conn->prepare("
    SELECT id, name, category, description, price, image, stock, is_active, created_at
    FROM products
    WHERE id = ? AND is_active = 1
    LIMIT 1
");
$stmt->bind_param("i", $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    http_response_code(404);
    $pageTitle = "Product Not Found";
    require_once __DIR__ . '/includes/header.php';
    ?>
    <section class="section" style="text-align: center; padding: 100px 20px;">
        <div class="container">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--bg-muted); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 20px;">
                <i class="fa-solid fa-box-open"></i>
            </div>
            <h1 style="font-size: 2.2rem; margin-bottom: 12px;">Product Unavailable</h1>
            <p style="color: var(--text-secondary); max-width: 480px; margin: 0 auto 24px;">
                This product is currently inactive or no longer available in the catalog.
            </p>
            <a href="products.php" class="btn btn-primary">
                <i class="fa-solid fa-store"></i> Browse Catalog
            </a>
        </div>
    </section>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    return;
}

$stock = (int)$product['stock'];
$inStock = $stock > 0;

// Fetch Related Products in same category
$relatedStmt = $conn->prepare("
    SELECT id, name, category, description, price, image, stock
    FROM products
    WHERE category = ? AND id != ? AND is_active = 1
    ORDER BY id DESC
    LIMIT 4
");
$relatedStmt->bind_param("si", $product['category'], $productId);
$relatedStmt->execute();
$relatedResult = $relatedStmt->get_result();
$relatedProducts = [];
while ($rRow = $relatedResult->fetch_assoc()) {
    $relatedProducts[] = $rRow;
}
$relatedStmt->close();

$pageTitle = $product['name'] . " - Details";
$pageDescription = "Buy " . $product['name'] . " on Needify for " . format_price($product['price']) . ". Fast Cash on Delivery across University of Sargodha.";

require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumbs -->
<div style="background: var(--bg-surface); border-bottom: 1px solid var(--border-color); padding: 14px 0;">
    <div class="container">
        <nav aria-label="Breadcrumb" style="font-size: 0.88rem; color: var(--text-muted); display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <a href="index.php" style="color: inherit;"><i class="fa-solid fa-house"></i> Home</a>
            <span>&bull;</span>
            <a href="products.php" style="color: inherit;">Products</a>
            <span>&bull;</span>
            <a href="products.php?category=<?= urlencode($product['category']) ?>" style="color: inherit;"><?= e($product['category']) ?></a>
            <span>&bull;</span>
            <span style="color: var(--primary); font-weight: 600;"><?= e($product['name']) ?></span>
        </nav>
    </div>
</div>

<!-- =====================================================
     PRODUCT SHOWCASE
===================================================== -->
<section class="section">
    <div class="container">
        <div style="display: grid; grid-template-columns: 1fr 1.15fr; gap: 48px; align-items: start;">
            <!-- Left: Product Image -->
            <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-md); padding: 24px; text-align: center;">
                <div style="width: 100%; height: 380px; display: flex; align-items: center; justify-content: center; background: var(--bg-muted); border-radius: var(--radius-md); overflow: hidden;">
                    <img src="<?= e(product_image_url($product['image'])) ?>"
                         alt="<?= e($product['name']) ?>"
                         style="max-width: 100%; max-height: 100%; object-fit: contain; transition: transform 0.3s ease;"
                         onerror="this.onerror=null; this.src='assets/images/product-placeholder.svg';">
                </div>
                <div style="margin-top: 16px; font-size: 0.82rem; color: var(--text-muted);">
                    <i class="fa-solid fa-camera"></i> Actual item delivered via Cash on Delivery
                </div>
            </div>

            <!-- Right: Product Information & Purchase -->
            <div>
                <!-- Category Pill -->
                <div style="margin-bottom: 12px;">
                    <a href="products.php?category=<?= urlencode($product['category']) ?>" class="section-pill">
                        <?= e($product['category']) ?>
                    </a>
                </div>

                <h1 style="font-size: 2.4rem; margin-bottom: 14px; line-height: 1.2;">
                    <?= e($product['name']) ?>
                </h1>

                <!-- Price and Availability -->
                <div style="display: flex; align-items: baseline; gap: 16px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color);">
                    <div style="font-size: 2.1rem; font-weight: 800; color: var(--primary);">
                        <?= format_price($product['price']) ?>
                    </div>
                    <div>
                        <?php if ($inStock): ?>
                            <span style="display: inline-flex; align-items: center; gap: 6px; background: #E8F8F0; color: #2E9E65; font-size: 0.85rem; font-weight: 700; padding: 4px 12px; border-radius: var(--radius-full);">
                                <i class="fa-solid fa-circle-check"></i> In Stock (<?= $stock ?> available)
                            </span>
                        <?php else: ?>
                            <span style="display: inline-flex; align-items: center; gap: 6px; background: #FFF5F5; color: #C53030; font-size: 0.85rem; font-weight: 700; padding: 4px 12px; border-radius: var(--radius-full);">
                                <i class="fa-solid fa-circle-xmark"></i> Out of Stock
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Description -->
                <div style="margin-bottom: 28px;">
                    <h3 style="font-size: 1.05rem; margin-bottom: 10px; color: var(--text-primary);">Description</h3>
                    <p style="font-size: 1rem; color: var(--text-secondary); line-height: 1.8;">
                        <?= nl2br(e($product['description'] ?? 'High-quality item available for university and community order on Needify.')) ?>
                    </p>
                </div>

                <!-- Purchase / Add to Cart Form -->
                <?php if ($inStock): ?>
                    <form action="cart.php" method="POST" style="background: var(--bg-muted); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 22px; margin-bottom: 28px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?= $productId ?>">

                        <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                            <!-- Quantity Selector -->
                            <div>
                                <label for="quantityInput" style="display: block; font-size: 0.85rem; font-weight: 700; margin-bottom: 6px;">
                                    Quantity
                                </label>
                                <div style="display: flex; align-items: center; background: var(--bg-surface); border: 1.5px solid var(--border-color); border-radius: var(--radius-full); overflow: hidden; width: 130px;">
                                    <button type="button" id="qtyMinus" style="width: 38px; height: 38px; border: none; background: none; color: var(--text-primary); cursor: pointer; font-size: 1rem;">
                                        <i class="fa-solid fa-minus"></i>
                                    </button>
                                    <input type="number"
                                           id="quantityInput"
                                           name="quantity"
                                           value="1"
                                           min="1"
                                           max="<?= $stock ?>"
                                           style="width: 54px; text-align: center; border: none; background: none; font-weight: 700; font-size: 1rem; color: var(--text-primary); -moz-appearance: textfield;">
                                    <button type="button" id="qtyPlus" style="width: 38px; height: 38px; border: none; background: none; color: var(--text-primary); cursor: pointer; font-size: 1rem;">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div style="flex: 1; min-width: 200px; padding-top: 24px;">
                                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 13px 24px; font-size: 1.05rem;">
                                    <i class="fa-solid fa-cart-plus"></i> Add to Shopping Cart
                                </button>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-error" style="margin-bottom: 28px;">
                        <i class="fa-solid fa-bell"></i>
                        <div>
                            <strong>Currently Unavailable:</strong> This product is temporarily sold out. You can post a request on our <a href="post-need.php" style="text-decoration: underline; font-weight: 700;">Needs Board</a> to see if a fellow community member can provide it.
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Campus Value Propositions -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding-top: 18px; border-top: 1px solid var(--border-color);">
                    <div style="display: flex; align-items: center; gap: 10px; font-size: 0.88rem; color: var(--text-secondary);">
                        <i class="fa-solid fa-truck-ramp-box" style="color: var(--primary); font-size: 1.1rem;"></i>
                        <span>Cash on Delivery Only</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; font-size: 0.88rem; color: var(--text-secondary);">
                        <i class="fa-solid fa-building-columns" style="color: var(--accent-pink); font-size: 1.1rem;"></i>
                        <span>University of Sargodha delivery</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; font-size: 0.88rem; color: var(--text-secondary);">
                        <i class="fa-brands fa-whatsapp" style="color: #25D366; font-size: 1.1rem;"></i>
                        <a href="https://wa.me/923350093455?text=I%20am%20inquiring%20about%20<?= urlencode($product['name']) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit;">
                            Inquire via WhatsApp
                        </a>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; font-size: 0.88rem; color: var(--text-secondary);">
                        <i class="fa-solid fa-shield-halved" style="color: var(--accent-mint); font-size: 1.1rem;"></i>
                        <span>100% Quality Checked</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- =====================================================
     RELATED PRODUCTS SECTION
===================================================== -->
<?php if (!empty($relatedProducts)): ?>
<section class="section section-alt" style="border-top: 1px solid var(--border-color);">
    <div class="container">
        <div style="margin-bottom: 28px;">
            <span class="section-pill">More In This Category</span>
            <h2 style="font-size: 1.8rem;">Related Products</h2>
        </div>

        <div class="products-grid">
            <?php foreach ($relatedProducts as $relProduct): ?>
                <div class="product-card">
                    <div class="product-image-wrap">
                        <span class="product-category-tag"><?= e($relProduct['category']) ?></span>
                        <a href="product-details.php?id=<?= (int)$relProduct['id'] ?>">
                            <img src="<?= e(product_image_url($relProduct['image'])) ?>"
                                 alt="<?= e($relProduct['name']) ?>"
                                 class="product-image"
                                 loading="lazy"
                                 onerror="this.onerror=null; this.src='assets/images/product-placeholder.svg';">
                        </a>
                    </div>
                    <div class="product-body">
                        <h3 class="product-title">
                            <a href="product-details.php?id=<?= (int)$relProduct['id'] ?>" style="color: inherit;">
                                <?= e($relProduct['name']) ?>
                            </a>
                        </h3>
                        <p class="product-desc"><?= e($relProduct['description'] ?? '') ?></p>
                        <div class="product-footer">
                            <div class="product-price"><?= format_price($relProduct['price']) ?></div>
                            <a href="product-details.php?id=<?= (int)$relProduct['id'] ?>" class="btn btn-outline" style="padding: 7px 14px; font-size: 0.85rem;">
                                View Details
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const qtyInput = document.getElementById('quantityInput');
    const btnMinus = document.getElementById('qtyMinus');
    const btnPlus = document.getElementById('qtyPlus');

    if (qtyInput && btnMinus && btnPlus) {
        const maxStock = parseInt(qtyInput.getAttribute('max'), 10) || 1;

        btnMinus.addEventListener('click', function () {
            let val = parseInt(qtyInput.value, 10) || 1;
            if (val > 1) {
                qtyInput.value = val - 1;
            }
        });

        btnPlus.addEventListener('click', function () {
            let val = parseInt(qtyInput.value, 10) || 1;
            if (val < maxStock) {
                qtyInput.value = val + 1;
            }
        });

        qtyInput.addEventListener('change', function () {
            let val = parseInt(this.value, 10);
            if (isNaN(val) || val < 1) {
                this.value = 1;
            } else if (val > maxStock) {
                this.value = maxStock;
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
