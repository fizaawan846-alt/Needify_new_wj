
<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

require_user_login();
// User must be logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// Check need ID
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: my-needs.php");
    exit();
}

$need_id = (int) $_GET["id"];
$user_id = (int) $_SESSION["user_id"];
$user_name = $_SESSION["full_name"] ?? "User";

$error = "";
$success = "";

// Get only this user's need
$sql = "SELECT id, title, category, description, budget
        FROM needs
        WHERE id = ? AND user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $need_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: my-needs.php");
    exit();
}

$need = $result->fetch_assoc();

// Update need
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $budget = trim($_POST["budget"] ?? "");

    $allowed_categories = [
        "Electronics",
        "Clothing",
        "Books & Education",
        "Home & Furniture",
        "Jobs & Work",
        "Services",
        "Other"
    ];

    if ($title === "" || $category === "" || $description === "") {
        $error = "Please fill in all required fields.";

    } elseif (!in_array($category, $allowed_categories, true)) {
        $error = "Please select a valid category.";

    } elseif ($budget !== "" && (!is_numeric($budget) || (float)$budget < 0)) {
        $error = "Please enter a valid budget.";

    } else {

        $budget_value = ($budget === "") ? null : (float)$budget;

        $sql = "UPDATE needs
                SET title = ?, category = ?, description = ?, budget = ?
                WHERE id = ? AND user_id = ?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "sssdii",
            $title,
            $category,
            $description,
            $budget_value,
            $need_id,
            $user_id
        );

        if ($stmt->execute()) {

            $success = "Your need has been updated successfully!";

            $need["title"] = $title;
            $need["category"] = $category;
            $need["description"] = $description;
            $need["budget"] = $budget_value;

        } else {
            $error = "Something went wrong. Please try again.";
        }
    }

    // Keep submitted values visible if validation fails
    if ($error !== "") {
        $need["title"] = $title;
        $need["category"] = $category;
        $need["description"] = $description;
        $need["budget"] = $budget;
    }
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Needify | Edit Need</title>

    <link rel="icon" type="image/png" href="favicon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>

:root {
    --purple: #8064d9;
    --purple-dark: #6347b6;
    --lavender: #f0eaff;
    --pink: #f8dfe9;
    --cream: #fffaf4;
    --ink: #302743;
    --muted: #827b90;
    --border: #eee8f5;
    --green: #26866a;
    --red: #c83f5a;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    min-height: 100vh;
    font-family: 'DM Sans', sans-serif;
    color: var(--ink);

    background:
        radial-gradient(circle at 5% 10%, rgba(222, 210, 255, .32), transparent 26%),
        radial-gradient(circle at 95% 55%, rgba(255, 221, 233, .28), transparent 27%),
        #fcfaff;
}

a {
    text-decoration: none;
}

/* Navigation */

nav {
    padding: 17px 7%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 22px;

    background: rgba(255, 255, 255, .90);

    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);

    border-bottom: 1px solid rgba(128, 100, 217, .10);

    position: sticky;
    top: 0;
    z-index: 10;
}

.logo {
    display: flex;
    align-items: center;
    gap: 10px;

    color: var(--purple-dark);
    font-size: 26px;
    font-weight: 800;
    letter-spacing: -1px;
    white-space: nowrap;
}

.logo-icon {
    width: 39px;
    height: 39px;

    display: grid;
    place-items: center;

    border-radius: 14px;

    color: white;
    background: linear-gradient(135deg, #a28aef, #7555cc);

    box-shadow: 0 7px 18px rgba(128, 100, 217, .25);
}

.nav-links {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 7px;
    flex-wrap: wrap;
}

.nav-links a {
    padding: 10px 12px;
    border-radius: 11px;

    color: #665d78;
    font-size: 12px;
    font-weight: 700;

    transition: .25s ease;
}

.nav-links a:hover,
.nav-links a.active {
    color: var(--purple-dark);
    background: var(--lavender);
}

.nav-links a.logout {
    padding: 11px 16px;
    color: white;
    background: linear-gradient(135deg, #e78da5, #d96d8d);
}

.nav-links a.logout:hover {
    transform: translateY(-2px);
}

/* Display Settings Toolbar */

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

    background: var(--lavender);
    color: var(--purple-dark);

    cursor: pointer;
    font-size: 13px;

    transition: .2s ease;
}

.site-tools button:hover {
    transform: translateY(-2px);
}

.site-tools #themeToggle {
    width: 39px;
}

/* Page layout */

.page-container {
    width: 90%;
    max-width: 1080px;
    margin: 42px auto 75px;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 9px;

    margin-bottom: 22px;

    color: #76668e;
    font-size: 13px;
    font-weight: 700;

    transition: color .2s ease;
}

.back-link:hover {
    color: var(--purple-dark);
}

.layout {
    display: grid;
    grid-template-columns: .85fr 1.35fr;
    align-items: stretch;
    gap: 22px;

    animation: fadeUp .6s ease both;
}

/* Left introduction panel */

.intro-panel {
    position: relative;
    overflow: hidden;

    padding: 36px 30px;
    border-radius: 26px;

    background: linear-gradient(145deg, #eee7ff, #fbe8f0 65%, #fff7ec);

    border: 1px solid rgba(255, 255, 255, .9);

    box-shadow: 0 14px 40px rgba(83, 61, 130, .06);
}

.intro-panel::before {
    content: "";

    position: absolute;

    width: 210px;
    height: 210px;

    border-radius: 50%;

    right: -95px;
    top: -85px;

    background: rgba(255, 255, 255, .40);
}

.intro-icon {
    width: 65px;
    height: 65px;

    display: grid;
    place-items: center;

    margin-bottom: 27px;
    border-radius: 21px;

    background: rgba(255, 255, 255, .76);

    color: var(--purple-dark);
    font-size: 26px;

    box-shadow: 0 8px 20px rgba(99, 71, 182, .08);

    position: relative;
}

.eyebrow {
    display: inline-block;
    margin-bottom: 13px;

    color: var(--purple-dark);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.6px;
    text-transform: uppercase;
}

.intro-panel h1 {
    position: relative;

    font-family: 'Playfair Display', serif;
    font-size: clamp(30px, 3vw, 40px);
    line-height: 1.2;
    letter-spacing: -.6px;

    margin-bottom: 15px;
}

.intro-panel h1 span {
    color: var(--purple);
}

.intro-panel > p {
    position: relative;

    color: #766d86;
    font-size: 13px;
    line-height: 1.9;
}

.tips {
    position: relative;

    margin-top: 30px;
    padding-top: 23px;

    border-top: 1px solid rgba(128, 100, 217, .15);
}

.tips h3 {
    font-size: 13px;
    margin-bottom: 17px;
}

.tip {
    display: flex;
    align-items: flex-start;
    gap: 11px;

    margin-bottom: 16px;

    color: #766d86;
    font-size: 12px;
    line-height: 1.7;
}

.tip i {
    color: var(--purple);
    margin-top: 3px;
    font-size: 13px;
}

.tip strong {
    color: var(--ink);
}

/* Form panel */

.form-panel {
    min-width: 0;

    padding: 35px;
    border-radius: 26px;

    background: rgba(255, 255, 255, .95);

    border: 1px solid var(--border);

    box-shadow: 0 14px 40px rgba(58, 42, 91, .06);
}

.form-heading {
    margin-bottom: 25px;
}

.form-heading h2 {
    font-family: 'Playfair Display', serif;
    font-size: 27px;
    margin-bottom: 8px;
}

.form-heading p {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.7;
}

/* Alerts */

.alert {
    display: flex;
    align-items: flex-start;
    gap: 11px;

    padding: 14px 15px;
    border-radius: 13px;

    margin-bottom: 20px;

    font-size: 12px;
    line-height: 1.7;
}

.alert i {
    margin-top: 2px;
}

.alert.error {
    color: #a72f49;
    background: #fff0f2;
    border: 1px solid #f8dce3;
}

.alert.success {
    color: #237658;
    background: #edf9f2;
    border: 1px solid #d8f0e1;
}

/* Form controls */

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 9px;

    color: #41364f;
    font-size: 12px;
    font-weight: 800;
}

.required {
    color: #d96d8d;
}

.input-wrap {
    position: relative;
}

.input-icon {
    position: absolute;
    top: 15px;
    left: 15px;

    color: #a194bd;
    font-size: 14px;

    pointer-events: none;
}

input,
select,
textarea {
    display: block;
    width: 100%;

    padding: 14px 15px;

    border: 1px solid #e9e3f0;
    border-radius: 13px;
    outline: none;

    background: #fdfcff;
    color: var(--ink);

    font-family: 'DM Sans', sans-serif;
    font-size: 13px;

    transition: border-color .22s ease, box-shadow .22s ease, background .22s ease;
}

.input-wrap input,
.input-wrap select {
    padding-left: 43px;
}

input::placeholder,
textarea::placeholder {
    color: #b1a9be;
}

input:focus,
select:focus,
textarea:focus {
    background: white;
    border-color: #a38be8;

    box-shadow: 0 0 0 4px rgba(128, 100, 217, .10);
}

select {
    cursor: pointer;
}

textarea {
    min-height: 145px;
    resize: vertical;
    line-height: 1.8;
}

.field-hint {
    margin-top: 7px;

    color: #a198ae;
    font-size: 10px;
    line-height: 1.6;
}

/* Buttons */

.buttons {
    display: grid;
    grid-template-columns: .8fr 1.2fr;
    gap: 12px;

    margin-top: 27px;
}

.btn {
    min-height: 48px;

    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 9px;

    padding: 13px 15px;

    border: none;
    border-radius: 13px;

    font-family: 'DM Sans', sans-serif;
    font-size: 12px;
    font-weight: 800;

    cursor: pointer;

    transition: all .25s ease;
}

.cancel-btn {
    color: #6f657e;
    background: #f4f0f8;
    border: 1px solid #eae3f1;
}

.cancel-btn:hover {
    background: #ebe4f4;
    transform: translateY(-2px);
}

.update-btn {
    color: white;
    background: linear-gradient(135deg, #967be8, #7555cc);

    box-shadow: 0 8px 20px rgba(117, 85, 204, .20);
}

.update-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 25px rgba(117, 85, 204, .29);
}

.form-note {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;

    margin-top: 20px;

    color: #9a91a7;
    font-size: 10px;
    text-align: center;
}

.form-note i {
    color: var(--purple);
}

/* Footer */

footer {
    padding: 25px 7%;

    text-align: center;

    border-top: 1px solid #eee7f5;

    background: rgba(255, 255, 255, .70);

    color: #968da4;
    font-size: 12px;
}

footer strong {
    color: var(--purple-dark);
}

/* Page Up and Page Down */

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

    box-shadow: 0 7px 20px rgba(101, 72, 189, .25);

    cursor: pointer;
    font-size: 16px;

    transition: .2s ease;
}

.page-controls button:hover {
    transform: translateY(-3px);
}

/* Dark Mode */

body.dark-mode {
    --cream: #171421;
    --ink: #eee8f7;
    --muted: #b8adc9;
    --white: #242031;
    --border: #393047;

    background: #171421;
    color: var(--ink);
}

body.dark-mode nav {
    background: rgba(36, 32, 49, .96);
    border-color: #393047;
}

body.dark-mode .logo {
    color: #d8caff;
}

body.dark-mode .nav-links a {
    color: #d0c5df;
}

body.dark-mode .nav-links a:hover,
body.dark-mode .nav-links a.active {
    color: #eee8f7;
    background: #393047;
}

body.dark-mode .site-tools button {
    background: #393047;
    border-color: #514565;
    color: #eee8f7;
}

body.dark-mode .back-link {
    color: #c0b6cf;
}

body.dark-mode .back-link:hover {
    color: #d8caff;
}

body.dark-mode .intro-panel {
    background: linear-gradient(145deg, #29223c, #302437 65%, #34262f);
    border-color: #393047;
}

body.dark-mode .intro-panel h1 {
    color: #eee8f7;
}

body.dark-mode .intro-panel h1 span {
    color: #b8a2ff;
}

body.dark-mode .intro-panel > p,
body.dark-mode .tip {
    color: #c0b6cf;
}

body.dark-mode .intro-icon {
    background: #393047;
    color: #d8caff;
}

body.dark-mode .eyebrow {
    color: #d8caff;
}

body.dark-mode .tips {
    border-color: #514565;
}

body.dark-mode .tips h3 {
    color: #eee8f7;
}

body.dark-mode .tip strong {
    color: #eee8f7;
}

body.dark-mode .form-panel {
    background: #242031;
    border-color: #393047;
}

body.dark-mode .form-heading h2 {
    color: #eee8f7;
}

body.dark-mode .form-heading p {
    color: #b8adc9;
}

body.dark-mode label {
    color: #e5dcef;
}

body.dark-mode input,
body.dark-mode select,
body.dark-mode textarea {
    background: #1c1928;
    color: #eee8f7;
    border-color: #514565;
}

body.dark-mode input::placeholder,
body.dark-mode textarea::placeholder {
    color: #8f849f;
}

body.dark-mode input:focus,
body.dark-mode select:focus,
body.dark-mode textarea:focus {
    background: #211d2e;
    border-color: #a38be8;
}

body.dark-mode select option {
    background: #242031;
    color: #eee8f7;
}

body.dark-mode .field-hint {
    color: #a99dbb;
}

body.dark-mode .alert.error {
    color: #ffb5c5;
    background: #442b37;
    border-color: #624050;
}

body.dark-mode .alert.success {
    color: #a8e0c5;
    background: #20352e;
    border-color: #315044;
}

body.dark-mode .cancel-btn {
    color: #e0d6ed;
    background: #393047;
    border-color: #514565;
}

body.dark-mode .cancel-btn:hover {
    background: #493d5c;
}

body.dark-mode .form-note {
    color: #b8adc9;
}

body.dark-mode .form-note i {
    color: #b8a2ff;
}

body.dark-mode footer {
    background: #100d18;
    border-color: #393047;
    color: #b8adc9;
}

body.dark-mode footer strong {
    color: #d8caff;
}

/* Animation */

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

/* Responsive */

@media (max-width: 1100px) {

    nav {
        padding: 16px 5%;
        flex-wrap: wrap;
    }

    .nav-links {
        flex: 1;
    }
}

@media (max-width: 900px) {

    .page-container {
        width: 92%;
    }

    .layout {
        grid-template-columns: 1fr;
    }

    .intro-panel {
        padding: 30px;
    }

    .intro-icon {
        margin-bottom: 18px;
    }

    .intro-panel h1 {
        font-size: 35px;
    }

    .tips {
        margin-top: 22px;
    }

    .tip {
        margin-bottom: 10px;
    }
}

@media (max-width: 650px) {

    nav {
        flex-direction: column;
        gap: 13px;
    }

    .nav-links {
        justify-content: center;
        gap: 4px;
    }

    .nav-links a {
        padding: 9px 10px;
        font-size: 11px;
    }

    .page-container {
        margin-top: 27px;
    }

    .intro-panel,
    .form-panel {
        padding: 25px 21px;
        border-radius: 21px;
    }

    .intro-panel h1 {
        font-size: 32px;
    }

    .form-heading h2 {
        font-size: 25px;
    }

    .buttons {
        grid-template-columns: 1fr;
    }

    .site-tools {
        justify-content: center;
    }

    .page-controls {
        right: 14px;
        bottom: 14px;
    }

    .page-controls button {
        width: 42px;
        height: 42px;
    }
}

@media (prefers-reduced-motion: reduce) {

    *,
    *::before,
    *::after {
        animation-duration: .01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: .01ms !important;
    }
}

</style>

</head>

<body>

<!-- Navigation -->

<nav>

    <a href="index.php" class="logo">
        <span class="logo-icon">
            <i class="fa-solid fa-bag-shopping"></i>
        </span>
        Needify
    </a>

    <div class="nav-links">

        <a href="index.php">
            <i class="fa-solid fa-house"></i> Home
        </a>

        <a href="dashboard.php">
            <i class="fa-solid fa-grip"></i> Dashboard
        </a>

        <a href="needs.php">
            <i class="fa-solid fa-magnifying-glass"></i> Browse Needs
        </a>

        <a href="my-needs.php" class="active">
            <i class="fa-solid fa-clipboard-list"></i> My Needs
        </a>

        <a href="logout.php" class="logout">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
        </a>

    </div>

    <!-- Display Settings -->

    <div class="site-tools" aria-label="Display settings">

        <button type="button" id="fontDecrease"
            title="Decrease font size" aria-label="Decrease font size">
            <i class="fa-solid fa-minus"></i>
        </button>

        <button type="button" id="fontReset"
            title="Reset font size" aria-label="Reset font size">
            <i class="fa-solid fa-rotate-left"></i>
        </button>

        <button type="button" id="fontIncrease"
            title="Increase font size" aria-label="Increase font size">
            <i class="fa-solid fa-plus"></i>
        </button>

        <button type="button" id="themeToggle"
            title="Switch to dark mode" aria-label="Switch to dark mode">
            <i class="fa-solid fa-moon"></i>
        </button>

    </div>

</nav>

<main class="page-container">

    <a href="my-needs.php" class="back-link">
        <i class="fa-solid fa-arrow-left"></i>
        Back to My Needs
    </a>

    <div class="layout">

        <!-- Introduction Panel -->

        <section class="intro-panel">

            <div class="intro-icon">
                <i class="fa-solid fa-pen-to-square"></i>
            </div>

            <span class="eyebrow">MAKE IT JUST RIGHT</span>

            <h1>Edit Your <span>Need.</span></h1>

            <p>
                Hey, <?php echo e($user_name); ?>! Plans can change, and that's okay.
                Update your need details to make sure the Needify community
                understands exactly what you're looking for.
            </p>

            <div class="tips">

                <h3>
                    <i class="fa-regular fa-lightbulb"></i>
                    A few helpful tips
                </h3>

                <div class="tip">

                    <i class="fa-solid fa-check-circle"></i>

                    <div>
                        <strong>Use a clear title</strong><br>
                        Make your requirement easy to understand.
                    </div>

                </div>

                <div class="tip">

                    <i class="fa-solid fa-check-circle"></i>

                    <div>
                        <strong>Choose the right category</strong><br>
                        This helps people find your need.
                    </div>

                </div>

                <div class="tip">

                    <i class="fa-solid fa-check-circle"></i>

                    <div>
                        <strong>Set a realistic budget</strong><br>
                        You can leave it blank if you prefer.
                    </div>

                </div>

            </div>

        </section>

        <!-- Form Panel -->

        <section class="form-panel">

            <div class="form-heading">

                <h2>Update Details ✨</h2>

                <p>
                    Make your changes below and save when you're ready.
                </p>

            </div>

            <?php if ($error !== ""): ?>

                <div class="alert error" role="alert">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <span><?php echo e($error); ?></span>

                </div>

            <?php endif; ?>

            <?php if ($success !== ""): ?>

                <div class="alert success" role="status">

                    <i class="fa-solid fa-circle-check"></i>

                    <span><?php echo e($success); ?></span>

                </div>

            <?php endif; ?>

            <form method="POST" action="edit-need.php?id=<?php echo $need_id; ?>">

                <!-- Need Title -->

                <div class="form-group">

                    <label for="title">
                        Need Title <span class="required">*</span>
                    </label>

                    <div class="input-wrap">

                        <i class="fa-solid fa-pen input-icon"></i>

                        <input
                            type="text"
                            id="title"
                            name="title"
                            value="<?php echo e($need["title"]); ?>"
                            placeholder="What are you looking for?"
                            maxlength="255"
                            required
                        >

                    </div>

                </div>

                <!-- Category -->

                <div class="form-group">

                    <label for="category">
                        Category <span class="required">*</span>
                    </label>

                    <div class="input-wrap">

                        <i class="fa-solid fa-layer-group input-icon"></i>

                        <select id="category" name="category" required>

                            <option value="">Select a category</option>

                            <option value="Electronics"
                                <?php if ($need["category"] === "Electronics") echo "selected"; ?>>
                                Electronics
                            </option>

                            <option value="Clothing"
                                <?php if ($need["category"] === "Clothing") echo "selected"; ?>>
                                Clothing
                            </option>

                            <option value="Books & Education"
                                <?php if ($need["category"] === "Books & Education") echo "selected"; ?>>
                                Books &amp; Education
                            </option>

                            <option value="Home & Furniture"
                                <?php if ($need["category"] === "Home & Furniture") echo "selected"; ?>>
                                Home &amp; Furniture
                            </option>

                            <option value="Jobs & Work"
                                <?php if ($need["category"] === "Jobs & Work") echo "selected"; ?>>
                                Jobs &amp; Work
                            </option>

                            <option value="Services"
                                <?php if ($need["category"] === "Services") echo "selected"; ?>>
                                Services
                            </option>

                            <option value="Other"
                                <?php if ($need["category"] === "Other") echo "selected"; ?>>
                                Other
                            </option>

                        </select>

                    </div>

                </div>

                <!-- Description -->

                <div class="form-group">

                    <label for="description">
                        Description <span class="required">*</span>
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        placeholder="Describe your need in detail..."
                        required
                    ><?php echo e($need["description"]); ?></textarea>

                    <p class="field-hint">
                        Add useful details so people can better understand your requirement.
                    </p>

                </div>

                <!-- Budget -->

                <div class="form-group">

                    <label for="budget">Budget (Rs.)</label>

                    <div class="input-wrap">

                        <i class="fa-solid fa-wallet input-icon"></i>

                        <input
                            type="number"
                            id="budget"
                            name="budget"
                            min="0"
                            step="0.01"
                            value="<?php echo e($need["budget"] ?? ""); ?>"
                            placeholder="Enter your estimated budget"
                        >

                    </div>

                    <p class="field-hint">
                        Budget is optional. Leave this field blank if you don't want to specify it.
                    </p>

                </div>

                <!-- Form Buttons -->

                <div class="buttons">

                    <a href="my-needs.php" class="btn cancel-btn">

                        <i class="fa-solid fa-arrow-left"></i>
                        Cancel

                    </a>

                    <button type="submit" class="btn update-btn">

                        <i class="fa-solid fa-check"></i>
                        Save Changes

                    </button>

                </div>

                <p class="form-note">

                    <i class="fa-solid fa-shield-halved"></i>

                    You can edit only the needs posted from your own account.

                </p>

            </form>

        </section>

    </div>

</main>

<!-- Footer -->

<footer>

    Made with <span style="color:#df7d9b;">♥</span> by <strong>Needify</strong>
    — Making your everyday needs easier.

</footer>

<!-- Page Up / Page Down -->

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

<!-- Theme, Font Size and Scroll JavaScript -->

<script>

document.addEventListener("DOMContentLoaded", function () {

    const body = document.body;

    const themeToggle = document.getElementById("themeToggle");

    const fontDecrease = document.getElementById("fontDecrease");
    const fontReset = document.getElementById("fontReset");
    const fontIncrease = document.getElementById("fontIncrease");

    const pageUp = document.getElementById("pageUp");
    const pageDown = document.getElementById("pageDown");

    // Dark Mode / Light Mode

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

    applyTheme(localStorage.getItem("needifyTheme") || "light");

    themeToggle?.addEventListener("click", function () {

        const nextTheme = body.classList.contains("dark-mode")
            ? "light"
            : "dark";

        localStorage.setItem("needifyTheme", nextTheme);

        applyTheme(nextTheme);

    });

    // Font Size Controls

    let fontLevel = Number(localStorage.getItem("needifyFontLevel") || 0);

    if (!Number.isFinite(fontLevel)) {
        fontLevel = 0;
    }

    fontLevel = Math.max(-2, Math.min(3, fontLevel));

    function applyFontLevel() {

        const zoom = 1 + (fontLevel * 0.05);

        body.style.zoom = String(zoom);

        localStorage.setItem("needifyFontLevel", String(fontLevel));

    }

    fontDecrease?.addEventListener("click", function () {

        fontLevel = Math.max(-2, fontLevel - 1);

        applyFontLevel();

    });

    fontIncrease?.addEventListener("click", function () {

        fontLevel = Math.min(3, fontLevel + 1);

        applyFontLevel();

    });

    fontReset?.addEventListener("click", function () {

        fontLevel = 0;

        applyFontLevel();

    });

    applyFontLevel();

    // Page Up

    pageUp?.addEventListener("click", function () {

        window.scrollTo({
            top: 0,
            behavior: "smooth"
        });

    });

    // Page Down

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
