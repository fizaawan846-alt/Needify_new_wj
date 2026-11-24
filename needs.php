<?php
/**
 * NEEDIFY - BROWSE COMMUNITY NEEDS
 * Community requests board with keyword search, category filtering,
 * status badges, budget formatting, and direct messaging links.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = "Browse Community Needs";
$pageDescription = "Explore what fellow students and Sargodha community members need. Offer help, fulfill requests, or post your own.";

$search = sanitize_text($_GET['search'] ?? '');
$selectedCategory = sanitize_text($_GET['category'] ?? '');
$selectedStatus = sanitize_text($_GET['status'] ?? 'all');

// Standard categories
$allowedCategories = [
    "Electronics",
    "Stationery & Study",
    "Party Supplies",
    "Food & Snacks",
    "Everyday Essentials",
    "Disposable Essentials",
    "Books",
    "Clothing",
    "Services",
    "Other"
];

// Build Dynamic SQL Query
$conditions = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $conditions[] = "(n.title LIKE ? OR n.description LIKE ?)";
    $term = "%" . $search . "%";
    $params[] = $term;
    $params[] = $term;
    $types .= "ss";
}

if (!empty($selectedCategory) && in_array($selectedCategory, $allowedCategories, true)) {
    $conditions[] = "n.category = ?";
    $params[] = $selectedCategory;
    $types .= "s";
}

if ($selectedStatus !== 'all' && in_array($selectedStatus, ['open', 'in_progress', 'fulfilled', 'closed'], true)) {
    $conditions[] = "n.status = ?";
    $params[] = $selectedStatus;
    $types .= "s";
}

$whereClause = implode(" AND ", $conditions);

$sql = "
    SELECT n.id, n.user_id, n.title, n.category, n.description, n.budget, n.status, n.created_at,
           u.full_name, u.username
    FROM needs n
    INNER JOIN users u ON n.user_id = u.id
    WHERE {$whereClause}
    ORDER BY n.created_at DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$needs = [];
while ($row = $result->fetch_assoc()) {
    $needs[] = $row;
}
$stmt->close();

$currentUserId = current_user_id();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Hero -->
<section style="background: var(--grad-hero); padding: 48px 0 36px; border-bottom: 1px solid var(--border-color);">
    <div class="container">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
            <div style="max-width: 650px;">
                <span class="section-pill">Community Board</span>
                <h1 style="font-size: 2.5rem; margin-bottom: 10px;">Browse Community Needs</h1>
                <p style="font-size: 1.05rem; color: var(--text-secondary);">
                    Connect with fellow students and community members across Sargodha. Help fulfill a request or post your own.
                </p>
            </div>
            <div>
                <a href="post-need.php" class="btn btn-primary" style="padding: 12px 24px; font-size: 1rem;">
                    <i class="fa-solid fa-plus"></i> Post a Need
                </a>
            </div>
        </div>

        <!-- Filter Form -->
        <form action="needs.php" method="GET" class="card" style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; margin-top: 28px; box-shadow: var(--shadow-md);">
            <div style="display: grid; grid-template-columns: 2fr 1.2fr 1fr auto auto; gap: 14px; align-items: center;">
                <!-- Search input -->
                <div class="form-input-wrap">
                    <i class="fa-solid fa-magnifying-glass form-input-icon"></i>
                    <input type="text"
                           name="search"
                           class="form-control"
                           placeholder="Search needs by keyword (e.g. laptop, books)..."
                           value="<?= e($search) ?>">
                </div>

                <!-- Category filter -->
                <div>
                    <select name="category" class="form-control" style="padding-left: 14px;">
                        <option value="">All Categories</option>
                        <?php foreach ($allowedCategories as $cat): ?>
                            <option value="<?= e($cat) ?>" <?= ($selectedCategory === $cat) ? 'selected' : '' ?>>
                                <?= e($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status filter -->
                <div>
                    <select name="status" class="form-control" style="padding-left: 14px;">
                        <option value="all" <?= ($selectedStatus === 'all') ? 'selected' : '' ?>>All Statuses</option>
                        <option value="open" <?= ($selectedStatus === 'open') ? 'selected' : '' ?>>Open Requests</option>
                        <option value="in_progress" <?= ($selectedStatus === 'in_progress') ? 'selected' : '' ?>>In Progress</option>
                        <option value="fulfilled" <?= ($selectedStatus === 'fulfilled') ? 'selected' : '' ?>>Fulfilled</option>
                    </select>
                </div>

                <!-- Action buttons -->
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn btn-primary" style="padding: 10px 18px;">
                        <i class="fa-solid fa-filter"></i> Filter
                    </button>
                    <?php if (!empty($search) || !empty($selectedCategory) || $selectedStatus !== 'all'): ?>
                        <a href="needs.php" class="btn btn-outline" style="padding: 10px 14px;" title="Reset filters">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</section>

<!-- Needs Grid Section -->
<section class="section">
    <div class="container">
        <!-- Results Status Bar -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; flex-wrap: wrap; gap: 12px;">
            <div style="font-weight: 700; color: var(--text-primary);">
                Showing <span style="color: var(--primary);"><?= count($needs) ?></span> community <?= (count($needs) === 1) ? 'request' : 'requests' ?>
            </div>
            <?php if (is_logged_in()): ?>
                <a href="my-needs.php" style="font-size: 0.9rem; font-weight: 600; color: var(--primary);">
                    <i class="fa-solid fa-clipboard-list"></i> Manage My Posted Needs
                </a>
            <?php endif; ?>
        </div>

        <?php if (!empty($needs)): ?>
            <div class="needs-grid">
                <?php foreach ($needs as $need): ?>
                    <?php
                    $isOwner = ($currentUserId !== null && (int)$need['user_id'] === $currentUserId);
                    $statusClass = $need['status'] ?? 'open';
                    ?>
                    <article class="need-card">
                        <!-- Card Header -->
                        <div class="need-card-header">
                            <span class="need-category-pill"><?= e($need['category']) ?></span>
                            <span class="need-status-pill <?= e($statusClass) ?>">
                                <?= e(str_replace('_', ' ', $need['status'])) ?>
                            </span>
                        </div>

                        <!-- Card Title & Description -->
                        <h3 class="need-card-title"><?= e($need['title']) ?></h3>
                        <p class="need-card-desc"><?= nl2br(e($need['description'])) ?></p>

                        <!-- Card Metadata -->
                        <div class="need-card-meta">
                            <div>
                                <span style="display: block; font-size: 0.75rem; color: var(--text-muted);">Budget</span>
                                <span class="need-budget-badge">
                                    <?= ($need['budget'] !== null && (float)$need['budget'] > 0) ? format_price($need['budget']) : 'Open / Negotiable' ?>
                                </span>
                            </div>
                            <div style="text-align: right;">
                                <span style="display: block; font-size: 0.75rem; color: var(--text-muted);">Posted by</span>
                                <span style="font-weight: 600; color: var(--text-primary);">
                                    <i class="fa-regular fa-user" style="font-size: 0.8rem; color: var(--primary);"></i>
                                    <?= e($need['full_name']) ?>
                                </span>
                            </div>
                        </div>

                        <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 16px;">
                            <i class="fa-regular fa-clock"></i> Posted on <?= format_date($need['created_at'], 'M d, Y') ?>
                        </div>

                        <!-- Card Actions -->
                        <div class="need-card-actions">
                            <?php if ($isOwner): ?>
                                <a href="edit-need.php?id=<?= (int)$need['id'] ?>" class="btn btn-outline" style="flex: 1; padding: 8px 14px; font-size: 0.85rem;">
                                    <i class="fa-solid fa-pen-to-square"></i> Edit My Need
                                </a>
                            <?php else: ?>
<a href="contact.php?need_id=<?= (int)$need['id'] ?>" class="btn btn-primary" style="flex: 1; padding: 8px 14px; font-size: 0.85rem;">
    <i class="fa-solid fa-paper-plane"></i> Contact Poster
</a>
                                <i class="fa-solid fa-paper-plane"></i> Contact Poster
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 60px 20px; background: var(--bg-surface); border: 1px dashed var(--border-color); border-radius: var(--radius-lg); margin-top: 20px;">
                <div style="width: 70px; height: 70px; border-radius: var(--radius-full); background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 16px;">
                    <i class="fa-solid fa-hand-holding-heart"></i>
                </div>
                <h3 style="font-size: 1.4rem; margin-bottom: 8px;">No Needs Found</h3>
                <p style="color: var(--text-secondary); max-width: 480px; margin: 0 auto 20px;">
                    There are no requests matching your search or filters at the moment.
                </p>
                <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                    <a href="needs.php" class="btn btn-outline">
                        <i class="fa-solid fa-rotate-left"></i> Clear Filters
                    </a>
                    <a href="post-need.php" class="btn btn-primary">
                        <i class="fa-solid fa-plus"></i> Post a Need
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
