<?php
/**
 * NEEDIFY - PUBLIC HOMEPAGE
 * Tagline: "A Little More Joy in Every Need"
 * Dynamic categories, featured products from database, How It Works,
 * University of Sargodha location/map, and community engagement.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = "Home - A Little More Joy in Every Need";
$pageDescription = "Needify is your trusted campus and community platform for University of Sargodha. Browse products, share community needs, and order with Cash on Delivery.";

// -------------------------------------------------------------
// Fetch Categories with Product Counts
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
// Fetch Featured Active Products
// -------------------------------------------------------------
$featuredProducts = [];
$prodQuery = "
    SELECT id, name, category, description, price, image, stock, is_active
    FROM products
    WHERE is_active = 1
    ORDER BY id DESC
    LIMIT 8
";
$prodResult = $conn->query($prodQuery);
if ($prodResult) {
    while ($row = $prodResult->fetch_assoc()) {
        $featuredProducts[] = $row;
    }
}

// Include Shared Header
require_once __DIR__ . '/includes/header.php';
?>

<!-- =====================================================
     HERO SECTION
===================================================== -->
<section class="hero-section">
    <div class="container hero-grid">
        <div class="hero-content">
            <div class="hero-badge reveal">
                <i class="fa-solid fa-sparkles"></i>
                <span>University of Sargodha Campus & Community</span>
            </div>

            <h1 class="hero-title reveal">
                A Little More <span class="gradient-text">Joy</span> in Every <span class="pink-text">Need.</span>
            </h1>

            <p class="hero-description reveal">
                Welcome to Needify — Sargodha's dedicated platform to browse everyday essentials, post what you are looking for, help fellow students, and order with easy Cash on Delivery.
            </p>

            <div class="hero-cta-group reveal">
                <a href="products.php" class="btn btn-primary">
                    <i class="fa-solid fa-bag-shopping"></i> Shop Now
                </a>
                <a href="needs.php" class="btn btn-peach">
                    <i class="fa-solid fa-hand-holding-heart"></i> Explore Needs
                </a>
                <a href="post-need.php" class="btn btn-outline">
                    <i class="fa-solid fa-plus"></i> Post a Need
                </a>
            </div>

            <div class="hero-stats reveal">
                <div class="stat-item">
                    <h4>100%</h4>
                    <p>Cash on Delivery</p>
                </div>
                <div class="stat-item">
                    <h4>UOS</h4>
                    <p>Campus Focus</p>
                </div>
                <div class="stat-item">
                    <h4>24/7</h4>
                    <p>AI Assistant</p>
                </div>
            </div>
        </div>

        <!-- Hero Visual Card Showcase -->
        <div class="hero-visual reveal">
            <div class="hero-main-card">
                <div class="hero-card-header">
                    <div class="hero-card-title">
                        <i class="fa-solid fa-fire" style="color: var(--accent-pink);"></i>
                        <span>Trending Campus Essentials</span>
                    </div>
                    <span style="font-size: 0.75rem; color: var(--primary); font-weight: 700;">Live Feed</span>
                </div>

                <div class="hero-card-items">
                    <div class="sample-item">
                        <div class="sample-item-icon">
                            <i class="fa-solid fa-box-open"></i>
                        </div>
                        <div class="sample-item-info">
                            <h5>Snack Pack Bundle</h5>
                            <span>Food & Snacks &bull; Quick Study Bite</span>
                        </div>
                        <div class="sample-item-price">Rs. 600.00</div>
                    </div>

                    <div class="sample-item">
                        <div class="sample-item-icon" style="color: var(--accent-pink);">
                            <i class="fa-solid fa-cake-candles"></i>
                        </div>
                        <div class="sample-item-info">
                            <h5>Party Balloons Pack</h5>
                            <span>Party Supplies &bull; Celebrations</span>
                        </div>
                        <div class="sample-item-price">Rs. 300.00</div>
                    </div>

                    <div class="sample-item">
                        <div class="sample-item-icon" style="color: #2E9E65;">
                            <i class="fa-solid fa-bottle-water"></i>
                        </div>
                        <div class="sample-item-info">
                            <h5>Durable Water Bottle</h5>
                            <span>Everyday Essentials &bull; Campus Use</span>
                        </div>
                        <div class="sample-item-price">Rs. 800.00</div>
                    </div>
                </div>
            </div>

            <!-- Floating Verified Campus Badge -->
            <div class="hero-floating-badge">
                <div class="floating-icon">
                    <i class="fa-solid fa-check"></i>
                </div>
                <div>
                    <h5 style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary);">Student Verified</h5>
                    <p style="font-size: 0.72rem; color: var(--text-muted);">Trusted local Sargodha network</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- =====================================================
     TRUST & VALUE PROPS STRIP
===================================================== -->
<section class="trust-strip">
    <div class="container">
        <div class="trust-grid">
            <div class="trust-item reveal">
                <div class="trust-icon">
                    <i class="fa-solid fa-truck-fast"></i>
                </div>
                <div class="trust-info">
                    <h4>Direct Campus Delivery</h4>
                    <p>Fast delivery right to your campus or hostel</p>
                </div>
            </div>

            <div class="trust-item reveal">
                <div class="trust-icon" style="background: var(--accent-pink-soft); color: var(--accent-pink);">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </div>
                <div class="trust-info">
                    <h4>Cash on Delivery (COD)</h4>
                    <p>Pay safely only when you receive your items</p>
                </div>
            </div>

            <div class="trust-item reveal">
                <div class="trust-icon" style="background: var(--accent-peach-soft); color: var(--accent-peach);">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="trust-info">
                    <h4>Community Needs Board</h4>
                    <p>Post requests and help fellow students easily</p>
                </div>
            </div>

            <div class="trust-item reveal">
                <div class="trust-icon" style="background: var(--accent-mint-soft); color: var(--accent-mint);">
                    <i class="fa-solid fa-robot"></i>
                </div>
                <div class="trust-info">
                    <h4>Needify AI Shopping Assistant</h4>
                    <p>Smart Gemini AI to find recommendations</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- =====================================================
     CATEGORIES SECTION
===================================================== -->
<section class="section" id="categories">
    <div class="container">
        <div class="section-header reveal">
            <span class="section-pill">Browse By Category</span>
            <h2 class="section-title">Explore What You Need</h2>
            <p class="section-subtitle">Find party items, study essentials, snacks, and disposables curated for you.</p>
        </div>

        <div class="category-grid">
            <?php
            $categoryIcons = [
                'Party Supplies'        => 'fa-solid fa-gifts',
                'Food & Snacks'         => 'fa-solid fa-cookie-bite',
                'Everyday Essentials'   => 'fa-solid fa-bag-shopping',
                'Disposable Essentials' => 'fa-solid fa-utensils',
                'Disposible Essentials' => 'fa-solid fa-utensils',
                'Electronics'           => 'fa-solid fa-laptop',
                'Stationery & Study'    => 'fa-solid fa-book-open',
            ];
            ?>
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $cat): ?>
                    <?php
                    $iconClass = $categoryIcons[$cat['name']] ?? 'fa-solid fa-tags';
                    ?>
                    <a href="products.php?category=<?= urlencode($cat['name']) ?>" class="category-card reveal">
                        <div class="category-icon-box">
                            <i class="<?= $iconClass ?>"></i>
                        </div>
                        <h3 class="category-name"><?= e($cat['name']) ?></h3>
                        <span class="category-count"><?= (int)$cat['product_count'] ?> Products</span>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="grid-column: 1/-1; text-align: center; color: var(--text-muted);">No categories available at the moment.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- =====================================================
     FEATURED PRODUCTS SECTION
===================================================== -->
<section class="section section-alt" id="featured-products">
    <div class="container">
        <div class="section-header reveal">
            <span class="section-pill">Handpicked For You</span>
            <h2 class="section-title">Featured Products</h2>
            <p class="section-subtitle">Real in-stock items ready for immediate order with Cash on Delivery.</p>
        </div>

        <div class="products-grid">
            <?php if (!empty($featuredProducts)): ?>
                <?php foreach ($featuredProducts as $product): ?>
                    <div class="product-card reveal">
                        <div class="product-image-wrap">
                            <span class="product-category-tag"><?= e($product['category']) ?></span>
                            <img src="<?= e(product_image_url($product['image'])) ?>"
                                 alt="<?= e($product['name']) ?>"
                                 class="product-image"
                                 loading="lazy"
                                 onerror="this.onerror=null; this.src='assets/images/product-placeholder.svg';">
                        </div>

                        <div class="product-body">
                            <h3 class="product-title"><?= e($product['name']) ?></h3>
                            <p class="product-desc"><?= e($product['description'] ?? 'High-quality item available on Needify.') ?></p>

                            <div class="product-footer">
                                <div>
                                    <div class="product-price"><?= format_price($product['price']) ?></div>
                                    <span class="stock-indicator <?= ((int)$product['stock'] > 0) ? 'stock-in' : 'stock-out' ?>">
                                        <?= ((int)$product['stock'] > 0) ? 'In Stock (' . (int)$product['stock'] . ')' : 'Out of Stock' ?>
                                    </span>
                                </div>

                                <div style="display: flex; gap: 8px;">
                                    <a href="product-details.php?id=<?= (int)$product['id'] ?>"
                                       class="btn btn-outline"
                                       style="padding: 7px 12px; font-size: 0.85rem;"
                                       title="View details">
                                        <i class="fa-solid fa-circle-info"></i>
                                    </a>

                                    <?php if ((int)$product['stock'] > 0): ?>
                                        <form action="cart.php" method="POST" style="display: inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <button type="submit" class="btn btn-primary" style="padding: 7px 14px; font-size: 0.85rem;">
                                                <i class="fa-solid fa-cart-plus"></i> Add
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-outline" disabled style="padding: 7px 12px; font-size: 0.82rem; opacity: 0.5;">
                                            Unavailable
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="grid-column: 1/-1; text-align: center; color: var(--text-muted);">No products found in the database.</p>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 40px;" class="reveal">
            <a href="products.php" class="btn btn-outline" style="padding: 12px 30px;">
                View All Products <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

<!-- =====================================================
     HOW IT WORKS SECTION
===================================================== -->
<section class="section">
    <div class="container">
        <div class="section-header reveal">
            <span class="section-pill">Simple & Fast</span>
            <h2 class="section-title">How Needify Works</h2>
            <p class="section-subtitle">Whether you are shopping or sharing a community need, here is how easy it is.</p>
        </div>

        <div class="how-grid">
            <div class="how-card reveal">
                <span class="how-step-num">1</span>
                <div class="how-icon-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <h3 class="how-title">Browse or Post Needs</h3>
                <p class="how-text">
                    Browse everyday products in the catalog or post a specific need to ask the Sargodha student community for help.
                </p>
            </div>

            <div class="how-card reveal">
                <span class="how-step-num">2</span>
                <div class="how-icon-box" style="color: var(--accent-pink);">
                    <i class="fa-solid fa-comments"></i>
                </div>
                <h3 class="how-title">Connect & Fulfill</h3>
                <p class="how-text">
                    Use direct on-platform messaging to coordinate with need posters or consult our Needify AI shopping chatbot.
                </p>
            </div>

            <div class="how-card reveal">
                <span class="how-step-num">3</span>
                <div class="how-icon-box" style="color: var(--accent-peach);">
                    <i class="fa-solid fa-cart-shopping"></i>
                </div>
                <h3 class="how-title">Add to Cart</h3>
                <p class="how-text">
                    Add verified supplies to your shopping cart and complete checkout with simple delivery information.
                </p>
            </div>

            <div class="how-card reveal">
                <span class="how-step-num">4</span>
                <div class="how-icon-box" style="color: var(--accent-mint);">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                </div>
                <h3 class="how-title">Pay on Delivery</h3>
                <p class="how-text">
                    Receive your items directly at your department, campus hostel, or doorstep and pay with zero hassle via Cash on Delivery.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- =====================================================
     UNIVERSITY OF SARGODHA LOCATION / MAP SECTION
===================================================== -->
<section class="location-section" id="location">
    <div class="container">
        <div class="location-grid">
            <div class="location-info reveal">
                <span class="section-pill">Campus Presence</span>
                <h2>Find Us at <span>University of Sargodha</span></h2>
                <p>
                    Needify is proudly rooted in the University of Sargodha community. We bridge the gap between students, staff, and local vendors, ensuring everyday study essentials, event supplies, and student needs are fulfilled right on campus.
                </p>

                <div class="location-points">
                    <div class="point-item">
                        <i class="fa-solid fa-building-columns"></i>
                        <span>Main Campus, University Road, Sargodha, Pakistan</span>
                    </div>
                    <div class="point-item">
                        <i class="fa-solid fa-clock"></i>
                        <span>Orders processed daily for hostel & department drop-offs</span>
                    </div>
                    <div class="point-item">
                        <i class="fa-solid fa-shield-heart"></i>
                        <span>Direct student-to-student verification & support</span>
                    </div>
                </div>

                <div style="display: flex; gap: 14px; flex-wrap: wrap;">
                    <a href="https://www.google.com/maps/search/?api=1&query=University+of+Sargodha+Sargodha+Pakistan"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="btn btn-primary">
                        <i class="fa-solid fa-location-arrow"></i> Open in Google Maps
                    </a>
                    <a href="https://wa.me/923350093455"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="btn btn-outline">
                        <i class="fa-brands fa-whatsapp"></i> Campus WhatsApp
                    </a>
                </div>
            </div>

            <div class="map-container reveal">
                <iframe
                    src="https://www.google.com/maps?q=University%20of%20Sargodha%2C%20Sargodha%2C%20Pakistan&output=embed"
                    title="University of Sargodha Campus Map"
                    allowfullscreen=""
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
        </div>
    </div>
</section>

<!-- =====================================================
     CALL TO ACTION SECTION
===================================================== -->
<section class="cta-section">
    <div class="container">
        <div class="cta-box reveal">
            <h2>Ready to find joy in fulfilling everyday needs?</h2>
            <p>
                Join fellow students and neighbors in Sargodha today. Explore active needs, discover essentials, and connect through Needify.
            </p>
            <div style="display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;">
                <?php if (is_logged_in()): ?>
                    <a href="post-need.php" class="btn btn-primary" style="padding: 12px 28px;">
                        <i class="fa-solid fa-plus"></i> Post Your Need
                    </a>
                    <a href="products.php" class="btn btn-outline" style="padding: 12px 28px;">
                        <i class="fa-solid fa-bag-shopping"></i> Browse Catalog
                    </a>
                <?php else: ?>
                    <a href="signup.php" class="btn btn-primary" style="padding: 12px 28px;">
                        <i class="fa-solid fa-user-plus"></i> Join Needify Free
                    </a>
                    <a href="login.php" class="btn btn-outline" style="padding: 12px 28px;">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php
// Include Shared Footer
require_once __DIR__ . '/includes/footer.php';
?>
