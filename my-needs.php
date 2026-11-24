
<?php
// Secure session configuration
ini_set("session.use_strict_mode", "1");
ini_set("session.use_only_cookies", "1");
ini_set("session.use_trans_sid", "0");

$isHttps = (
    isset($_SERVER["HTTPS"]) &&
    $_SERVER["HTTPS"] !== "off"
);

session_set_cookie_params([
    "lifetime" => 0,
    "path" => "/",
    "domain" => "",
    "secure" => $isHttps,
    "httponly" => true,
    "samesite" => "Lax"
]);

session_start();

// Prevent cached private information
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");

// User must be logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// Automatic logout after 100 seconds of inactivity
$lastActivity = $_SESSION["user_last_activity"] ?? time();

if ((time() - $lastActivity) >= 100) {
    unset(
        $_SESSION["user_id"],
        $_SESSION["full_name"],
        $_SESSION["user_last_activity"]
    );

    session_regenerate_id(true);

    header("Location: login.php?timeout=1");
    exit();
}

$_SESSION["user_last_activity"] = time();

require_once "config/database.php";

$user_id = (int) $_SESSION["user_id"];
$user_name = $_SESSION["full_name"] ?? "User";

// Get only this user's needs
$sql = "SELECT id, title, category, description, budget, created_at
        FROM needs
        WHERE user_id = ?
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Unable to load your needs. Please try again later.");
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$total_needs = $result->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Needify | My Needs</title>
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
        --white: #ffffff;
        --border: #eee8f5;
        --green: #26866a;
        --red: #d94b65;
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
        font-family: 'DM Sans', sans-serif;
        background:
            radial-gradient(circle at 5% 15%, rgba(222, 210, 255, 0.30), transparent 25%),
            radial-gradient(circle at 95% 45%, rgba(255, 221, 233, 0.27), transparent 25%),
            #fcfaff;
        color: var(--ink);
        min-height: 100vh;
    }

    a {
        text-decoration: none;
    }

    button,
    a {
        -webkit-tap-highlight-color: transparent;
    }

    /* NAVIGATION */
    nav {
        width: 100%;
        padding: 18px 5%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        background: rgba(255, 255, 255, 0.88);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
        border-bottom: 1px solid rgba(128, 100, 217, 0.09);
        position: sticky;
        top: 0;
        z-index: 100;
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 26px;
        font-weight: 800;
        letter-spacing: -1px;
        color: var(--purple-dark);
        white-space: nowrap;
    }

    .logo-icon {
        width: 39px;
        height: 39px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        color: white;
        background: linear-gradient(135deg, #a28aef, #7555cc);
        box-shadow: 0 7px 18px rgba(128, 100, 217, 0.25);
    }

    .nav-links {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        flex-wrap: wrap;
    }

    .nav-links a {
        color: #665d78;
        font-size: 13px;
        font-weight: 700;
        padding: 11px 13px;
        border-radius: 12px;
        transition: all 0.25s ease;
    }

    .nav-links a:hover,
    .nav-links a.active {
        color: var(--purple-dark);
        background: var(--lavender);
    }

    .nav-links a.logout {
        color: #fff;
        background: linear-gradient(135deg, #e78da5, #d96d8d);
        padding: 11px 18px;
        box-shadow: 0 5px 14px rgba(217, 109, 141, 0.16);
    }

    .nav-links a.logout:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px rgba(217, 109, 141, 0.25);
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
        background: var(--lavender);
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

    /* MAIN CONTENT */
    .container {
        width: 88%;
        max-width: 1160px;
        margin: 0 auto;
        padding: 55px 0 85px;
    }

    .hero {
        position: relative;
        overflow: hidden;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 25px;
        padding: 42px 45px;
        margin-bottom: 28px;
        border-radius: 30px;
        background: linear-gradient(120deg, #f0eaff 0%, #fff0f5 58%, #fffaf0 100%);
        border: 1px solid rgba(255, 255, 255, 0.9);
        box-shadow: 0 15px 45px rgba(83, 61, 130, 0.07);
        animation: fadeUp 0.65s ease both;
    }

    .hero::before {
        content: "";
        position: absolute;
        width: 260px;
        height: 260px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.38);
        right: 100px;
        top: -160px;
    }

    .hero-content {
        position: relative;
        z-index: 1;
        max-width: 680px;
    }

    .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 13px;
        margin-bottom: 16px;
        background: rgba(255, 255, 255, 0.78);
        border: 1px solid rgba(128, 100, 217, 0.10);
        border-radius: 50px;
        color: var(--purple-dark);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 1.3px;
        text-transform: uppercase;
    }

    .hero h1 {
        font-family: 'Playfair Display', serif;
        font-size: clamp(34px, 4vw, 48px);
        line-height: 1.15;
        letter-spacing: -1px;
        margin-bottom: 13px;
    }

    .hero h1 span {
        color: var(--purple);
    }

    .hero p {
        color: #746a85;
        font-size: 15px;
        line-height: 1.8;
        max-width: 570px;
    }

    .hero-icon {
        width: 110px;
        height: 110px;
        flex-shrink: 0;
        display: grid;
        place-items: center;
        border-radius: 32px;
        color: var(--purple-dark);
        background: rgba(255, 255, 255, 0.68);
        font-size: 43px;
        transform: rotate(5deg);
        box-shadow: 0 14px 32px rgba(105, 79, 168, 0.10);
        position: relative;
        z-index: 1;
    }

    /* SUMMARY AND ACTIONS */
    .toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 18px;
        flex-wrap: wrap;
        margin-bottom: 30px;
        animation: fadeUp 0.7s ease both;
    }

    .summary {
        display: flex;
        align-items: center;
        gap: 13px;
    }

    .summary-icon {
        width: 49px;
        height: 49px;
        display: grid;
        place-items: center;
        border-radius: 16px;
        background: var(--lavender);
        color: var(--purple-dark);
        font-size: 19px;
    }

    .summary h2 {
        font-size: 18px;
        font-weight: 800;
        margin-bottom: 4px;
    }

    .summary p {
        font-size: 12px;
        color: var(--muted);
    }

    .count-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 28px;
        height: 27px;
        padding: 0 9px;
        margin-left: 5px;
        border-radius: 9px;
        background: var(--pink);
        color: #a74769;
        font-size: 12px;
        font-weight: 800;
    }

    .post-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        padding: 14px 21px;
        border-radius: 14px;
        background: linear-gradient(135deg, #967be8, #7555cc);
        color: white;
        font-size: 13px;
        font-weight: 800;
        box-shadow: 0 9px 22px rgba(117, 85, 204, 0.20);
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }

    .post-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 13px 27px rgba(117, 85, 204, 0.29);
    }

    /* NEED CARDS */
    .needs-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 23px;
    }

    .need-card {
        position: relative;
        display: flex;
        flex-direction: column;
        min-width: 0;
        padding: 25px;
        border-radius: 23px;
        background: rgba(255, 255, 255, 0.94);
        border: 1px solid var(--border);
        box-shadow: 0 8px 25px rgba(58, 42, 91, 0.045);
        transition: transform 0.28s ease, box-shadow 0.28s ease, border-color 0.28s ease;
        animation: fadeUp 0.65s ease both;
    }

    .need-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 18px 38px rgba(83, 61, 130, 0.11);
        border-color: #ded2fa;
    }

    .card-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 20px;
    }

    .category {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        max-width: 75%;
        padding: 8px 11px;
        border-radius: 10px;
        background: #f0eaff;
        color: #7054bd;
        font-size: 11px;
        font-weight: 800;
        line-height: 1.4;
        overflow-wrap: anywhere;
    }

    .category i {
        font-size: 11px;
    }

    .card-number {
        color: #c5b9da;
        font-family: 'Playfair Display', serif;
        font-size: 19px;
        font-weight: 700;
    }

    .need-card h2 {
        font-family: 'Playfair Display', serif;
        font-size: 23px;
        line-height: 1.35;
        overflow-wrap: anywhere;
        margin-bottom: 12px;
    }

    .description {
        color: #7b7388;
        font-size: 13px;
        line-height: 1.85;
        overflow-wrap: anywhere;
        margin-bottom: 20px;
        flex-grow: 1;
    }

    .budget-box {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 13px;
        margin-bottom: 17px;
        border-radius: 14px;
        background: #f0faf5;
        border: 1px solid #e2f3e9;
    }

    .budget-icon {
        width: 36px;
        height: 36px;
        display: grid;
        place-items: center;
        border-radius: 11px;
        background: #dcf4e7;
        color: var(--green);
        flex-shrink: 0;
    }

    .budget-label {
        display: block;
        color: #7c9789;
        font-size: 10px;
        font-weight: 700;
        margin-bottom: 3px;
    }

    .budget-value {
        display: block;
        color: #237658;
        font-size: 14px;
        font-weight: 800;
        overflow-wrap: anywhere;
    }

    .card-footer {
        display: flex;
        align-items: center;
        gap: 9px;
        padding-top: 15px;
        border-top: 1px solid #f1edf6;
        color: var(--muted);
        font-size: 11px;
    }

    .date-icon {
        width: 32px;
        height: 32px;
        display: grid;
        place-items: center;
        border-radius: 11px;
        background: #fff0f5;
        color: #cc7190;
        flex-shrink: 0;
    }

    .date-label {
        display: block;
        font-size: 10px;
        margin-bottom: 3px;
        color: #a39aad;
    }

    .date-value {
        color: #665c78;
        font-size: 12px;
        font-weight: 700;
    }

    /* EDIT AND DELETE ACTIONS */
    .actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 19px;
    }

    .edit-btn,
    .delete-btn {
        display: inline-flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        min-height: 43px;
        padding: 11px 10px;
        border-radius: 12px;
        font-family: 'DM Sans', sans-serif;
        font-size: 12px;
        font-weight: 800;
        transition: all 0.23s ease;
    }

    .edit-btn {
        color: var(--purple-dark);
        background: var(--lavender);
        border: 1px solid #e8defd;
    }

    .edit-btn:hover {
        color: white;
        background: var(--purple);
        transform: translateY(-2px);
    }

    .delete-btn {
        color: #c74c67;
        background: #fff0f3;
        border: 1px solid #f8dce4;
    }

    .delete-btn:hover {
        color: white;
        background: #dc637e;
        transform: translateY(-2px);
    }

    /* EMPTY STATE */
    .empty {
        padding: 65px 25px;
        text-align: center;
        border-radius: 27px;
        background: rgba(255, 255, 255, 0.90);
        border: 1px solid var(--border);
        box-shadow: 0 12px 35px rgba(58, 42, 91, 0.05);
        animation: fadeUp 0.65s ease both;
    }

    .empty-icon {
        width: 100px;
        height: 100px;
        display: grid;
        place-items: center;
        margin: 0 auto 24px;
        border-radius: 31px;
        background: linear-gradient(135deg, #f0eaff, #fff0f5);
        color: var(--purple);
        font-size: 39px;
        transform: rotate(-5deg);
    }

    .empty h2 {
        font-family: 'Playfair Display', serif;
        font-size: 29px;
        margin-bottom: 12px;
    }

    .empty p {
        color: var(--muted);
        font-size: 14px;
        line-height: 1.8;
        margin: 0 auto 25px;
        max-width: 420px;
    }

    /* FOOTER */
    footer {
        padding: 26px 7%;
        text-align: center;
        border-top: 1px solid #eee7f5;
        background: rgba(255, 255, 255, 0.70);
        color: #968da4;
        font-size: 12px;
    }

    footer strong {
        color: var(--purple-dark);
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
        --ink: #eee8f7;
        --muted: #b8adc9;
        --white: #242031;
        --border: #393047;
        background: #171421;
        color: var(--ink);
    }

    body.dark-mode nav {
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
        color: #c4afff;
        background: #393047;
    }

    body.dark-mode .nav-links a.logout {
        color: #fff;
        background: linear-gradient(135deg, #bd6682, #a94f70);
    }

    body.dark-mode .site-tools button {
        background: #393047;
        border-color: #514565;
        color: #eee8f7;
    }

    body.dark-mode .hero {
        background: linear-gradient(120deg, #302744, #38283b 58%, #302c3d);
        border-color: #493b58;
    }

    body.dark-mode .hero p {
        color: #c0b5d0;
    }

    body.dark-mode .eyebrow {
        background: #403550;
        border-color: #554568;
        color: #d0bfff;
    }

    body.dark-mode .hero-icon {
        background: #403550;
        color: #d0bfff;
    }

    body.dark-mode .summary-icon {
        background: #393047;
        color: #d0bfff;
    }

    body.dark-mode .count-badge {
        background: #503347;
        color: #f2b7ce;
    }

    body.dark-mode .need-card,
    body.dark-mode .empty {
        background: #242031;
        border-color: #393047;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.16);
    }

    body.dark-mode .need-card:hover {
        border-color: #69558c;
    }

    body.dark-mode .category {
        background: #393047;
        color: #d0bfff;
    }

    body.dark-mode .description {
        color: #b8adc9;
    }

    body.dark-mode .card-number {
        color: #9585ae;
    }

    body.dark-mode .budget-box {
        background: #20372f;
        border-color: #315442;
    }

    body.dark-mode .budget-icon {
        background: #315442;
        color: #a8e2c2;
    }

    body.dark-mode .budget-label {
        color: #a4c5b3;
    }

    body.dark-mode .budget-value {
        color: #a8e2c2;
    }

    body.dark-mode .card-footer {
        border-color: #393047;
    }

    body.dark-mode .date-icon {
        background: #453044;
        color: #efacc5;
    }

    body.dark-mode .date-label {
        color: #b8adc9;
    }

    body.dark-mode .date-value {
        color: #ddd3e9;
    }

    body.dark-mode .edit-btn {
        background: #393047;
        border-color: #514565;
        color: #d0bfff;
    }

    body.dark-mode .delete-btn {
        background: #452c39;
        border-color: #61394b;
        color: #f2a8bd;
    }

    body.dark-mode .empty-icon {
        background: linear-gradient(135deg, #393047, #453044);
        color: #c4afff;
    }

    body.dark-mode footer {
        background: #100d18;
        border-color: #393047;
        color: #b8adc9;
    }

    body.dark-mode footer strong {
        color: #c4afff;
    }

    /* ANIMATIONS */
    @keyframes fadeUp {
        from {
            opacity: 0;
            transform: translateY(18px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* RESPONSIVE LAYOUT */
    @media (max-width: 1000px) {
        nav {
            padding: 16px 5%;
        }

        .container {
            width: 90%;
        }

        .needs-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .hero {
            padding: 35px;
        }
    }

    @media (max-width: 700px) {
        nav {
            flex-direction: column;
            gap: 15px;
            padding: 17px 5%;
        }

        .nav-links {
            justify-content: center;
            gap: 5px;
        }

        .nav-links a {
            padding: 9px 10px;
            font-size: 12px;
        }

        .site-tools {
            width: 100%;
        }

        .container {
            width: 91%;
            padding-top: 30px;
        }

        .hero {
            padding: 28px 23px;
            border-radius: 23px;
        }

        .hero h1 {
            font-size: 34px;
        }

        .hero p {
            font-size: 13px;
        }

        .hero-icon {
            width: 70px;
            height: 70px;
            border-radius: 22px;
            font-size: 29px;
        }

        .needs-grid {
            grid-template-columns: 1fr;
            gap: 17px;
        }

        .need-card {
            padding: 22px;
        }

        .toolbar {
            align-items: flex-start;
        }

        .post-btn {
            width: 100%;
        }

        .toolbar > .post-btn {
            width: auto;
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

    @media (max-width: 420px) {
        .hero {
            padding: 25px 19px;
        }

        .hero-icon {
            display: none;
        }

        .hero h1 {
            font-size: 31px;
        }

        .summary h2 {
            font-size: 16px;
        }

        .empty h2 {
            font-size: 24px;
        }

        .toolbar {
            flex-direction: column;
        }

        .toolbar > .post-btn {
            width: 100%;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            scroll-behavior: auto !important;
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
    }
</style>
</head>

<body>

<!-- NAVIGATION -->
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

        <a href="post-need.php">
            <i class="fa-solid fa-plus"></i> Post a Need
        </a>

        <a href="logout.php" class="logout">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
        </a>
    </div>

    <!-- FONT SIZE AND THEME CONTROLS -->
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

<!-- MAIN CONTENT -->
<main class="container">

    <section class="hero">

        <div class="hero-content">

            <div class="eyebrow">
                <i class="fa-solid fa-heart"></i>
                YOUR PERSONAL SPACE
            </div>

            <h1>My Needs, <span>My Way.</span></h1>

            <p>
                Welcome back,
                <?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?>!
                Keep track of everything you've posted, update your requirements,
                and manage your needs in one beautiful place.
            </p>

        </div>

        <div class="hero-icon">
            <i class="fa-solid fa-clipboard-list"></i>
        </div>

    </section>

    <section class="toolbar">

        <div class="summary">

            <div class="summary-icon">
                <i class="fa-solid fa-layer-group"></i>
            </div>

            <div>
                <h2>
                    Your Posted Needs
                    <span class="count-badge"><?php echo (int)$total_needs; ?></span>
                </h2>

                <p>Everything you've shared with the Needify community.</p>
            </div>

        </div>

        <a href="post-need.php" class="post-btn">
            <i class="fa-solid fa-plus"></i>
            Post a New Need
        </a>

    </section>

    <?php if ($total_needs > 0): ?>

        <section class="needs-grid">

            <?php $card_number = 0; ?>

            <?php while ($need = $result->fetch_assoc()): ?>

                <?php
                    $card_number++;

                    $category_icons = [
                        "Electronics" => "fa-laptop",
                        "Clothing" => "fa-shirt",
                        "Books & Education" => "fa-book-open",
                        "Home & Furniture" => "fa-couch",
                        "Jobs & Work" => "fa-briefcase",
                        "Services" => "fa-handshake",
                        "Other" => "fa-box-open"
                    ];

                    $category_name = $need["category"];
                    $category_icon = $category_icons[$category_name] ?? "fa-tag";
                ?>

                <article class="need-card">

                    <div class="card-top">

                        <span class="category">
                            <i class="fa-solid <?php echo $category_icon; ?>"></i>

                            <?php
                                echo htmlspecialchars(
                                    $category_name,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>
                        </span>

                        <span class="card-number">
                            #<?php
                                echo str_pad(
                                    (string)$card_number,
                                    2,
                                    "0",
                                    STR_PAD_LEFT
                                );
                            ?>
                        </span>

                    </div>

                    <h2>
                        <?php
                            echo htmlspecialchars(
                                $need["title"],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                        ?>
                    </h2>

                    <p class="description">
                        <?php
                            echo nl2br(
                                htmlspecialchars(
                                    $need["description"],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            );
                        ?>
                    </p>

                    <div class="budget-box">

                        <div class="budget-icon">
                            <i class="fa-solid fa-wallet"></i>
                        </div>

                        <div>

                            <span class="budget-label">YOUR BUDGET</span>

                            <span class="budget-value">

                                <?php if ($need["budget"] !== null && $need["budget"] !== ""): ?>

                                    Rs. <?php
                                        echo number_format(
                                            (float)$need["budget"],
                                            2
                                        );
                                    ?>

                                <?php else: ?>

                                    Not specified

                                <?php endif; ?>

                            </span>

                        </div>

                    </div>

                    <div class="card-footer">

                        <div class="date-icon">
                            <i class="fa-regular fa-calendar"></i>
                        </div>

                        <div>

                            <span class="date-label">POSTED ON</span>

                            <span class="date-value">
                                <?php
                                    $created_timestamp = strtotime($need["created_at"]);

                                    echo $created_timestamp !== false
                                        ? date("d M Y", $created_timestamp)
                                        : "Date unavailable";
                                ?>
                            </span>

                        </div>

                    </div>

                    <div class="actions">

                        <a
                            href="edit-need.php?id=<?php echo (int)$need['id']; ?>"
                            class="edit-btn"
                        >
                            <i class="fa-solid fa-pen-to-square"></i>
                            Edit Need
                        </a>

                        <a
                            href="delete-need.php?id=<?php echo (int)$need['id']; ?>"
                            class="delete-btn"
                            onclick="return confirm('Are you sure you want to delete this need?');"
                        >
                            <i class="fa-regular fa-trash-can"></i>
                            Delete
                        </a>

                    </div>

                </article>

            <?php endwhile; ?>

        </section>

    <?php else: ?>

        <section class="empty">

            <div class="empty-icon">
                <i class="fa-solid fa-clipboard-list"></i>
            </div>

            <h2>Your story starts here!</h2>

            <p>
                You haven't posted any needs yet. Tell the Needify community
                what you're looking for and keep everything organized here.
            </p>

            <a href="post-need.php" class="post-btn">
                <i class="fa-solid fa-plus"></i>
                Post Your First Need
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
    Made with <span style="color:#df7d9b;">♥</span> by
    <strong>Needify</strong>
    — Making your everyday needs easier.
</footer>

<!-- DISPLAY SETTINGS JAVASCRIPT -->
<script>
document.addEventListener("DOMContentLoaded", function () {

    const body = document.body;

    const themeToggle = document.getElementById("themeToggle");
    const fontDecrease = document.getElementById("fontDecrease");
    const fontReset = document.getElementById("fontReset");
    const fontIncrease = document.getElementById("fontIncrease");

    const pageUp = document.getElementById("pageUp");
    const pageDown = document.getElementById("pageDown");

    // APPLY THEME
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

    // LOAD SAVED THEME
    applyTheme(localStorage.getItem("needifyTheme") || "light");

    // TOGGLE DARK / LIGHT MODE
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

    // DECREASE FONT SIZE
    fontDecrease?.addEventListener("click", function () {

        fontLevel = Math.max(-2, fontLevel - 1);
        applyFontLevel();
    });

    // INCREASE FONT SIZE
    fontIncrease?.addEventListener("click", function () {

        fontLevel = Math.min(3, fontLevel + 1);
        applyFontLevel();
    });

    // RESET FONT SIZE
    fontReset?.addEventListener("click", function () {

        fontLevel = 0;
        applyFontLevel();
    });

    // LOAD SAVED FONT SIZE
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
