
<?php
/**
 * NEEDIFY - USER DASHBOARD
 * Centralized secure session handling.
 */

require_once __DIR__ . "/config/session.php";

// PREVENT CACHED DASHBOARD
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// SECURITY HEADERS
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");

// USER LOGIN CHECK
if (
    !isset($_SESSION["user_id"]) ||
    (int) $_SESSION["user_id"] <= 0
) {
    header("Location: login.php");
    exit();
}

// 100 SECONDS INACTIVITY TIMEOUT
$timeout_duration = 100;

if (
    !isset($_SESSION["user_last_activity"]) ||
    (time() - (int) $_SESSION["user_last_activity"]) > $timeout_duration
) {
    unset(
        $_SESSION["user_id"],
        $_SESSION["full_name"],
        $_SESSION["username"],
        $_SESSION["email"],
        $_SESSION["user_last_activity"],
        $_SESSION["user_login_csrf"]
    );

    session_regenerate_id(true);

    header("Location: login.php?timeout=1");
    exit();
}

// UPDATE LAST ACTIVITY TIME
$_SESSION["user_last_activity"] = time();

// SAFELY GET USER INFORMATION
$user_name = $_SESSION["full_name"] ?? "User";
$user_email = $_SESSION["email"] ?? "";
?>    
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard | Needify</title>

    <link rel="icon" type="image/png" href="favicon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <script>
        (function () {
            try {
                const savedTheme = localStorage.getItem("needifyTheme") || "light";
                const savedFont = parseFloat(localStorage.getItem("needifyFontLevel") || "1");

                const validFont = Math.min(1.3, Math.max(0.8,
                    Number.isFinite(savedFont) ? savedFont : 1
                ));

                document.documentElement.dataset.theme = savedTheme === "dark" ? "dark" : "light";
                document.documentElement.style.setProperty("--font-scale", validFont);
            } catch (error) {
                document.documentElement.dataset.theme = "light";
                document.documentElement.style.setProperty("--font-scale", "1");
            }
        })();
    </script>

    <style>
        :root {
            --font-scale: 1;
            --purple: #8063d8;
            --purple-dark: #6548bd;
            --purple-light: #eee8ff;
            --pink: #f4b8d1;
            --pink-light: #fff0f6;
            --cream: #fffaf6;
            --text: #332b46;
            --muted: #898397;
            --white: #ffffff;
            --border: #eee8f4;
            --card-bg: rgba(255,255,255,0.93);
            --nav-bg: rgba(255,255,255,0.90);
            --footer-bg: rgba(255,255,255,0.85);
            --shadow: 0 12px 35px rgba(91,67,139,0.08);
        }

        html[data-theme="dark"] {
            --cream: #171421;
            --text: #f1ecfb;
            --muted: #b7aec9;
            --white: #252033;
            --border: #393149;
            --card-bg: rgba(38,32,52,0.96);
            --nav-bg: rgba(29,25,41,0.96);
            --footer-bg: rgba(29,25,41,0.96);
            --purple-light: #382d51;
            --pink-light: #442c3c;
            --shadow: 0 12px 35px rgba(0,0,0,0.22);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
            font-size: calc(16px * var(--font-scale));
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background:
                radial-gradient(circle at 5% 10%, #f3eaff 0, transparent 25%),
                radial-gradient(circle at 95% 40%, #fff0f5 0, transparent 25%),
                var(--cream);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: background .25s ease, color .25s ease;
        }

        html[data-theme="dark"] body {
            background:
                radial-gradient(circle at 5% 10%, #29213b 0, transparent 27%),
                radial-gradient(circle at 95% 40%, #382331 0, transparent 27%),
                var(--cream);
        }

        a {
            text-decoration: none;
        }

        button, a {
            -webkit-tap-highlight-color: transparent;
        }

        button {
            font-family: inherit;
        }

        /* NAVBAR */
        .navbar {
            width: 100%;
            background: var(--nav-bg);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
            transition: background .25s ease;
        }

        .nav-inner {
            max-width: 1350px;
            min-height: 82px;
            padding: 14px 5%;
            margin: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--text);
            font-size: 1.65rem;
            font-weight: 800;
            letter-spacing: -1.2px;
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
            box-shadow: 0 6px 15px rgba(128,99,216,.22);
        }

        .logo span:last-child {
            color: var(--purple);
        }

        .nav-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 12px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 16px 20px;
        }

        .nav-links a {
            color: var(--muted);
            font-size: .82rem;
            font-weight: 700;
            transition: color .25s, transform .25s;
            white-space: nowrap;
        }

        .nav-links a:hover {
            color: var(--purple);
        }

        .nav-links .logout {
            color: #fff;
            background: linear-gradient(135deg, #e98daf, #d875a0);
            padding: 11px 16px;
            border-radius: 13px;
            box-shadow: 0 5px 15px rgba(217,117,160,.18);
        }

        .nav-links .logout:hover {
            color: #fff;
            transform: translateY(-2px);
        }

        /* ACCESSIBILITY CONTROLS */
        .display-controls {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 8px;
        }

        .control-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 34px;
            padding: 7px 11px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--card-bg);
            color: var(--text);
            font-size: .75rem;
            font-weight: 700;
            cursor: pointer;
            transition: .2s ease;
        }

        .control-button:hover {
            color: var(--purple);
            border-color: var(--purple);
            transform: translateY(-1px);
        }

        .control-button:focus-visible {
            outline: 3px solid var(--purple);
            outline-offset: 3px;
        }

        .font-label {
            color: var(--muted);
            font-size: .72rem;
            font-weight: 700;
            margin-right: 3px;
        }

        /* MAIN */
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 42px auto;
            flex: 1;
        }

        /* WELCOME HERO */
        .welcome {
            position: relative;
            overflow: hidden;
            padding: 45px;
            margin-bottom: 38px;
            border-radius: 30px;
            background: linear-gradient(120deg,#eee7ff 0%,#f8e7f5 53%,#fff0f5 100%);
            border: 1px solid rgba(255,255,255,.9);
            box-shadow: var(--shadow);
            animation: fadeUp .65s ease both;
        }

        html[data-theme="dark"] .welcome {
            background: linear-gradient(120deg,#34294b 0%,#402c43 53%,#472b3b 100%);
            border-color: #4a3b59;
        }

        .welcome::before {
            content: "";
            position: absolute;
            width: 270px;
            height: 270px;
            border-radius: 50%;
            right: 8%;
            top: -135px;
            background: rgba(255,255,255,.32);
        }

        .welcome::after {
            content: "";
            position: absolute;
            width: 190px;
            height: 190px;
            border-radius: 50%;
            right: -35px;
            bottom: -115px;
            background: rgba(255,255,255,.34);
        }

        .welcome-content {
            position: relative;
            z-index: 2;
            max-width: 720px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,.7);
            color: #6548bd;
            border: 1px solid rgba(255,255,255,.85);
            padding: 9px 14px;
            border-radius: 50px;
            font-size: .69rem;
            font-weight: 800;
            letter-spacing: 1.2px;
            margin-bottom: 20px;
        }

        .welcome h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2rem,4vw,3rem);
            line-height: 1.2;
            font-weight: 700;
            letter-spacing: -1px;
            margin-bottom: 15px;
            overflow-wrap: anywhere;
        }

        .welcome h1 span {
            color: var(--purple-dark);
            overflow-wrap: anywhere;
        }

        html[data-theme="dark"] .welcome h1 span {
            color: #c6b3ff;
        }

        .welcome-description {
            color: #746a87;
            font-size: .94rem;
            line-height: 1.8;
            max-width: 570px;
            margin-bottom: 24px;
        }

        html[data-theme="dark"] .welcome-description {
            color: #d0c4df;
        }

        .welcome-bottom {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
        }

        .email-pill {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 12px 16px;
            background: rgba(255,255,255,.72);
            border: 1px solid rgba(255,255,255,.9);
            border-radius: 13px;
            color: #716881;
            font-size: .75rem;
            font-weight: 600;
            overflow-wrap: anywhere;
            max-width: 100%;
        }

        .email-pill i {
            color: #8063d8;
        }

        .welcome-decoration {
            position: absolute;
            right: 7%;
            bottom: 28px;
            z-index: 1;
            width: 125px;
            height: 125px;
            border-radius: 35px;
            display: grid;
            place-items: center;
            background: rgba(255,255,255,.48);
            border: 1px solid rgba(255,255,255,.85);
            transform: rotate(-10deg);
            box-shadow: 0 15px 35px rgba(104,78,155,.09);
        }

        .welcome-decoration i {
            font-size: 3.5rem;
            color: #8063d8;
            transform: rotate(10deg);
        }

        /* SECTION HEADING */
        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: end;
            gap: 20px;
            margin-bottom: 23px;
        }

        .section-heading h2 {
            font-family: 'Playfair Display',serif;
            font-size: 1.9rem;
            letter-spacing: -.5px;
            margin-bottom: 7px;
        }

        .section-heading p {
            font-size: .82rem;
            color: var(--muted);
            line-height: 1.7;
        }

        .section-label {
            color: var(--purple);
            font-size: .65rem;
            font-weight: 800;
            letter-spacing: 2px;
            margin-bottom: 8px;
        }

        .section-count {
            background: var(--purple-light);
            color: #8063d8;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: .75rem;
            font-weight: 800;
            white-space: nowrap;
        }

        /* DASHBOARD GRID */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(3,minmax(0,1fr));
            gap: 22px;
        }

        .dashboard-card {
            --card-accent: #8063d8;
            --card-soft: #eee8ff;
            position: relative;
            overflow: hidden;
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            background: var(--card-bg);
            padding: 28px 25px;
            border: 1px solid var(--border);
            border-radius: 23px;
            box-shadow: 0 7px 25px rgba(91,67,139,.045);
            transition: transform .3s ease,box-shadow .3s ease,border-color .3s ease,background .25s ease;
            animation: fadeUp .6s ease both;
        }

        .dashboard-card:nth-child(2) { animation-delay: .06s; }
        .dashboard-card:nth-child(3) { animation-delay: .12s; }
        .dashboard-card:nth-child(4) { animation-delay: .18s; }
        .dashboard-card:nth-child(5) { animation-delay: .24s; }
        .dashboard-card:nth-child(6) { animation-delay: .30s; }
        .dashboard-card:nth-child(7) { animation-delay: .36s; }

        .dashboard-card:hover {
            transform: translateY(-7px);
            box-shadow: 0 18px 38px rgba(91,67,139,.11);
            border-color: #b9a5e7;
        }

        .dashboard-card.pink {
            --card-accent: #d878a0;
            --card-soft: #ffedf4;
        }

        .dashboard-card.blue {
            --card-accent: #648acb;
            --card-soft: #eaf2ff;
        }

        .dashboard-card.mint {
            --card-accent: #479c8a;
            --card-soft: #e6f7f1;
        }

        .dashboard-card.peach {
            --card-accent: #d28a62;
            --card-soft: #fff0e6;
        }

        .dashboard-card.lilac {
            --card-accent: #9270cc;
            --card-soft: #f1eaff;
        }

        html[data-theme="dark"] .dashboard-card {
            --card-soft: #383047;
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            gap: 12px;
            margin-bottom: 24px;
        }

        .card-icon {
            width: 59px;
            height: 59px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border-radius: 19px;
            color: var(--card-accent);
            background: var(--card-soft);
            font-size: 1.45rem;
            transition: transform .3s ease;
        }

        .dashboard-card:hover .card-icon {
            transform: rotate(-7deg) scale(1.06);
        }

        .card-number {
            font-size: .69rem;
            color: var(--muted);
            font-weight: 800;
            letter-spacing: 1px;
        }

        .dashboard-card h3 {
            font-family: 'Playfair Display',serif;
            font-size: 1.4rem;
            line-height: 1.35;
            margin-bottom: 10px;
        }

        .dashboard-card p {
            color: var(--muted);
            font-size: .82rem;
            line-height: 1.8;
            margin-bottom: 25px;
            flex: 1;
        }

        .card-link {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            color: var(--card-accent);
            background: var(--card-soft);
            border-radius: 13px;
            padding: 13px 15px;
            font-size: .75rem;
            font-weight: 800;
            transition: filter .25s,transform .25s;
        }

        .card-link:hover {
            filter: saturate(1.15);
            transform: translateX(2px);
        }

        .card-link i {
            transition: transform .25s;
        }

        .card-link:hover i {
            transform: translateX(4px);
        }

        /* BOTTOM BANNER */
        .bottom-banner {
            margin-top: 34px;
            padding: 26px 30px;
            border-radius: 22px;
            background: linear-gradient(110deg,#fff0f5,#f0eaff);
            border: 1px solid #f0e6f4;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        html[data-theme="dark"] .bottom-banner {
            background: linear-gradient(110deg,#402b3a,#332b49);
            border-color: var(--border);
        }

        .bottom-banner-content {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .banner-icon {
            width: 50px;
            height: 50px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            background: var(--white);
            color: var(--purple);
            border-radius: 16px;
            font-size: 1.3rem;
            box-shadow: 0 5px 15px rgba(91,67,139,.06);
        }

        .bottom-banner h3 {
            font-size: .95rem;
            margin-bottom: 5px;
        }

        .bottom-banner p {
            color: var(--muted);
            font-size: .75rem;
            line-height: 1.7;
        }

        .banner-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            flex-shrink: 0;
            color: #fff;
            background: linear-gradient(135deg,#9276e1,#7557ca);
            border-radius: 13px;
            padding: 14px 19px;
            font-size: .75rem;
            font-weight: 800;
            box-shadow: 0 7px 17px rgba(117,87,202,.2);
            transition: transform .25s,box-shadow .25s;
        }

        .banner-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 23px rgba(117,87,202,.28);
        }

        /* FOOTER */
        footer {
            margin-top: 30px;
            padding: 25px 5%;
            background: var(--footer-bg);
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
            font-size: .95rem;
            font-weight: 800;
        }

        .footer-text {
            color: var(--muted);
            font-size: .69rem;
            line-height: 1.7;
        }

        .footer-heart {
            color: #e58bad;
        }

        /* PAGE UP AND PAGE DOWN BUTTONS */
        .page-scroll-controls {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 999;
            display: flex;
            flex-direction: column;
            gap: 11px;
        }

        .scroll-button {
            width: 48px;
            height: 48px;
            border: none;
            border-radius: 16px;
            display: grid;
            place-items: center;
            color: #ffffff;
            background: linear-gradient(135deg, #9276e1, #7557ca);
            box-shadow: 0 7px 20px rgba(101,72,189,.28);
            font-size: 1.1rem;
            cursor: pointer;
            transition: transform .25s ease, box-shadow .25s ease;
        }

        .scroll-button:hover {
            transform: translateY(-4px);
            box-shadow: 0 11px 25px rgba(101,72,189,.38);
        }

        .scroll-button:active {
            transform: scale(.94);
        }

        .scroll-button:focus-visible {
            outline: 3px solid var(--pink);
            outline-offset: 4px;
        }

        html[data-theme="dark"] .scroll-button {
            background: linear-gradient(135deg, #a18ae9, #765ac7);
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

        /* TABLET */
        @media (max-width: 1100px) {
            .nav-inner {
                flex-direction: column;
                gap: 15px;
            }

            .nav-right {
                align-items: center;
                width: 100%;
            }

            .nav-links {
                justify-content: center;
            }

            .display-controls {
                justify-content: center;
            }

            .dashboard-grid {
                grid-template-columns: repeat(2,minmax(0,1fr));
            }

            .welcome-decoration {
                right: 5%;
                opacity: .65;
            }
        }

        /* MOBILE */
        @media (max-width: 650px) {
            .nav-inner {
                padding: 16px 5%;
            }

            .logo {
                font-size: 1.55rem;
            }

            .nav-links {
                gap: 12px 16px;
            }

            .nav-links a {
                font-size: .75rem;
            }

            .nav-links .logout {
                padding: 10px 14px;
            }

            .container {
                width: 91%;
                margin: 26px auto;
            }

            .welcome {
                padding: 29px 23px;
                border-radius: 24px;
            }

            .welcome h1 {
                font-size: 2.05rem;
            }

            .welcome-description {
                font-size: .82rem;
            }

            .welcome-decoration {
                width: 75px;
                height: 75px;
                right: 15px;
                bottom: 15px;
                opacity: .35;
                border-radius: 23px;
            }

            .welcome-decoration i {
                font-size: 2.1rem;
            }

            .email-pill {
                font-size: .7rem;
            }

            .section-heading {
                align-items: flex-start;
            }

            .section-heading h2 {
                font-size: 1.65rem;
            }

            .section-heading p {
                font-size: .75rem;
            }

            .section-count {
                font-size: .65rem;
                padding: 9px 10px;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .dashboard-card {
                padding: 24px 22px;
            }

            .card-top {
                margin-bottom: 19px;
            }

            .dashboard-card h3 {
                font-size: 1.3rem;
            }

            .bottom-banner {
                padding: 23px 20px;
                align-items: flex-start;
                flex-direction: column;
            }

            .bottom-banner-content {
                align-items: flex-start;
            }

            .banner-button {
                width: 100%;
            }

            .footer-inner {
                justify-content: center;
                text-align: center;
                flex-direction: column;
            }

            .display-controls {
                gap: 6px;
            }

            .control-button {
                font-size: .7rem;
                padding: 7px 9px;
            }

            .page-scroll-controls {
                right: 14px;
                bottom: 16px;
                gap: 9px;
            }

            .scroll-button {
                width: 44px;
                height: 44px;
                border-radius: 14px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>

<body>

<!-- NAVIGATION -->
<nav class="navbar">
    <div class="nav-inner">

        <a href="dashboard.php" class="logo">
            <span class="logo-icon">
                <i class="fa-solid fa-bag-shopping"></i>
            </span>
            <span>Need<span>ify</span></span>
        </a>

        <div class="nav-right">

            <div class="nav-links">
                <a href="index.php">
                    <i class="fa-solid fa-house"></i> Home
                </a>

                <a href="needs.php">
                    <i class="fa-solid fa-magnifying-glass"></i> Browse Needs
                </a>

                <a href="post-need.php">
                    <i class="fa-solid fa-plus"></i> Post a Need
                </a>

                <a href="products.php">
                    <i class="fa-solid fa-store"></i> Products
                </a>

                <a href="cart.php">
                    <i class="fa-solid fa-cart-shopping"></i> Cart
                </a>

                <a href="my-orders.php">
                    <i class="fa-solid fa-box"></i> My Orders
                </a>

                <a href="logout.php" class="logout">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
                </a>
            </div>

            <!-- THEME AND FONT CONTROLS -->
            <div class="display-controls">

                <span class="font-label">FONT SIZE:</span>

                <button type="button" class="control-button" id="decreaseFont"
                    aria-label="Decrease font size" title="Decrease font size">
                    <i class="fa-solid fa-minus"></i> A
                </button>

                <button type="button" class="control-button" id="resetFont"
                    aria-label="Reset font size" title="Reset font size">
                    A
                </button>

                <button type="button" class="control-button" id="increaseFont"
                    aria-label="Increase font size" title="Increase font size">
                    <i class="fa-solid fa-plus"></i> A
                </button>

                <button type="button" class="control-button" id="themeToggle"
                    aria-label="Toggle dark and light mode">
                    <i class="fa-solid fa-moon" id="themeIcon"></i>
                    <span id="themeText">Dark Mode</span>
                </button>

            </div>
        </div>
    </div>
</nav>

<!-- MAIN DASHBOARD -->
<main class="container">

    <section class="welcome">

        <div class="welcome-content">

            <div class="eyebrow">
                <i class="fa-solid fa-sparkles"></i>
                YOUR PERSONAL SPACE
            </div>

            <h1>
                Welcome back,<br>
                <span><?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?></span>!
                <span aria-hidden="true">✦</span>
            </h1>

            <p class="welcome-description">
                Everything you need, all in one place. Manage your requests,
                discover useful products, and keep track of your shopping
                with your personal Needify dashboard.
            </p>

            <div class="welcome-bottom">

                <div class="email-pill">
                    <i class="fa-regular fa-envelope"></i>
                    <?php echo htmlspecialchars($user_email, ENT_QUOTES, 'UTF-8'); ?>
                </div>

                <div class="email-pill">
                    <i class="fa-solid fa-shield-heart"></i>
                    Your personal dashboard
                </div>

            </div>
        </div>

        <div class="welcome-decoration" aria-hidden="true">
            <i class="fa-solid fa-bag-shopping"></i>
        </div>

    </section>

    <section class="section-heading">

        <div>
            <div class="section-label">YOUR WORKSPACE</div>
            <h2>What would you like to do?</h2>
            <p>Choose a section and get started with your next task.</p>
        </div>

        <div class="section-count">
            <i class="fa-solid fa-grip"></i>
            &nbsp; 7 FEATURES
        </div>

    </section>

    <section class="dashboard-grid">

        <article class="dashboard-card">
            <div class="card-top">
                <div class="card-icon">
                    <i class="fa-solid fa-pen-to-square"></i>
                </div>
                <span class="card-number">01 / 07</span>
            </div>
            <h3>Post a Need</h3>
            <p>
                Looking for something specific? Create a request and let
                other Needify users know what you need.
            </p>
            <a href="post-need.php" class="card-link">
                Create a Request
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </article>

        <article class="dashboard-card pink">
            <div class="card-top">
                <div class="card-icon">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <span class="card-number">02 / 07</span>
            </div>
            <h3>Browse Needs</h3>
            <p>
                Explore requests shared by other users and discover
                opportunities to help them find what they need.
            </p>
            <a href="needs.php" class="card-link">
                Explore Requests
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </article>

        <article class="dashboard-card blue">
            <div class="card-top">
                <div class="card-icon">
                    <i class="fa-solid fa-clipboard-list"></i>
                </div>
                <span class="card-number">03 / 07</span>
            </div>
            <h3>My Needs</h3>
            <p>
                Keep your requests organized. View your posts, make edits,
                or remove requests you no longer need.
            </p>
            <a href="my-needs.php" class="card-link">
                Manage My Needs
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </article>

        <article class="dashboard-card mint">
            <div class="card-top">
                <div class="card-icon">
                    <i class="fa-solid fa-comments"></i>
                </div>
                <span class="card-number">04 / 07</span>
            </div>
            <h3>Messages</h3>
            <p>
                Visit your messages area to check responses and
                conversations related to your needs.
            </p>
            <a href="messages.php" class="card-link">
                Open Messages
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </article>

        <article class="dashboard-card peach">
            <div class="card-top">
                <div class="card-icon">
                    <i class="fa-solid fa-bag-shopping"></i>
                </div>
                <span class="card-number">05 / 07</span>
            </div>
            <h3>Explore Products</h3>
            <p>
                Browse the available products, compare your options,
                and discover useful items for your shopping list.
            </p>
            <a href="products.php" class="card-link">
                Browse Products
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </article>

        <article class="dashboard-card lilac">
            <div class="card-top">
                <div class="card-icon">
                    <i class="fa-solid fa-cart-shopping"></i>
                </div>
                <span class="card-number">06 / 07</span>
            </div>
            <h3>Shopping Cart</h3>
            <p>
                Review the products you have selected and continue
                to checkout whenever you are ready.
            </p>
            <a href="cart.php" class="card-link">
                View My Cart
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </article>

        <article class="dashboard-card pink">
            <div class="card-top">
                <div class="card-icon">
                    <i class="fa-solid fa-box-open"></i>
                </div>
                <span class="card-number">07 / 07</span>
            </div>
            <h3>My Orders</h3>
            <p>
                Review your order history and check the status of
                your purchases from one convenient place.
            </p>
            <a href="my-orders.php" class="card-link">
                View My Orders
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </article>

    </section>

    <section class="bottom-banner">

        <div class="bottom-banner-content">

            <div class="banner-icon">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
            </div>

            <div>
                <h3>Have something in mind?</h3>
                <p>Turn your idea into a request and let Needify help you get started.</p>
            </div>

        </div>

        <a href="post-need.php" class="banner-button">
            Create a Need
            <i class="fa-solid fa-arrow-right"></i>
        </a>

    </section>

</main>

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

<!-- PAGE UP AND PAGE DOWN BUTTONS -->
<div class="page-scroll-controls">

    <button type="button"
            class="scroll-button"
            id="pageUpButton"
            aria-label="Page Up - Scroll to top"
            title="Page Up - Go to top">
        <i class="fa-solid fa-arrow-up"></i>
    </button>

    <button type="button"
            class="scroll-button"
            id="pageDownButton"
            aria-label="Page Down - Scroll to bottom"
            title="Page Down - Go to bottom">
        <i class="fa-solid fa-arrow-down"></i>
    </button>

</div>

<!-- THEME, FONT SIZE AND PAGE SCROLL FUNCTIONALITY -->
<script>
document.addEventListener("DOMContentLoaded", function () {

    const themeToggle = document.getElementById("themeToggle");
    const themeIcon = document.getElementById("themeIcon");
    const themeText = document.getElementById("themeText");

    const increaseFont = document.getElementById("increaseFont");
    const decreaseFont = document.getElementById("decreaseFont");
    const resetFont = document.getElementById("resetFont");

    const pageUpButton = document.getElementById("pageUpButton");
    const pageDownButton = document.getElementById("pageDownButton");

    const root = document.documentElement;

    const THEME_KEY = "needifyTheme";
    const FONT_KEY = "needifyFontLevel";

    let currentTheme = "light";
    let fontScale = 1;

    // Load saved settings
    function loadSettings() {
        try {
            const savedTheme = localStorage.getItem(THEME_KEY);
            const savedFont = parseFloat(localStorage.getItem(FONT_KEY) || "1");

            currentTheme = savedTheme === "dark" ? "dark" : "light";

            fontScale = Number.isFinite(savedFont) ? savedFont : 1;
            fontScale = Math.min(1.3, Math.max(0.8, fontScale));

        } catch (error) {
            currentTheme = "light";
            fontScale = 1;
        }
    }

    // Apply theme
    function applyTheme(theme, save = true) {

        currentTheme = theme === "dark" ? "dark" : "light";

        root.dataset.theme = currentTheme;

        if (save) {
            try {
                localStorage.setItem(THEME_KEY, currentTheme);
            } catch (error) {
                console.warn("Theme preference could not be saved.");
            }
        }

        if (currentTheme === "dark") {
            themeIcon.className = "fa-solid fa-sun";
            themeText.textContent = "Light Mode";
            themeToggle.setAttribute("aria-label", "Switch to light mode");
        } else {
            themeIcon.className = "fa-solid fa-moon";
            themeText.textContent = "Dark Mode";
            themeToggle.setAttribute("aria-label", "Switch to dark mode");
        }
    }

    // Apply font size
    function applyFontSize(save = true) {

        fontScale = Math.min(1.3, Math.max(0.8, fontScale));

        root.style.setProperty("--font-scale", fontScale);

        if (save) {
            try {
                localStorage.setItem(FONT_KEY, fontScale.toString());
            } catch (error) {
                console.warn("Font preference could not be saved.");
            }
        }
    }

    // Dark / Light mode
    themeToggle.addEventListener("click", function () {
        const newTheme = currentTheme === "light" ? "dark" : "light";
        applyTheme(newTheme);
    });

    // Increase font
    increaseFont.addEventListener("click", function () {
        if (fontScale < 1.3) {
            fontScale = Math.min(1.3,
                Math.round((fontScale + 0.1) * 10) / 10
            );
            applyFontSize();
        }
    });

    // Decrease font
    decreaseFont.addEventListener("click", function () {
        if (fontScale > 0.8) {
            fontScale = Math.max(0.8,
                Math.round((fontScale - 0.1) * 10) / 10
            );
            applyFontSize();
        }
    });

    // Reset font size
    resetFont.addEventListener("click", function () {
        fontScale = 1;
        applyFontSize();
    });

    // PAGE UP: Scroll to the top
    pageUpButton.addEventListener("click", function () {
        window.scrollTo({
            top: 0,
            behavior: "smooth"
        });
    });

    // PAGE DOWN: Scroll to the bottom
    pageDownButton.addEventListener("click", function () {
        window.scrollTo({
            top: document.documentElement.scrollHeight,
            behavior: "smooth"
        });
    });

    // Initialize saved preferences
    loadSettings();
    applyTheme(currentTheme, false);
    applyFontSize(false);

    // Update settings if another Needify page changes them
    window.addEventListener("storage", function (event) {

        if (event.key === THEME_KEY) {
            applyTheme(event.newValue || "light", false);
        }

        if (event.key === FONT_KEY) {
            const newValue = parseFloat(event.newValue || "1");
            fontScale = Number.isFinite(newValue) ? newValue : 1;
            applyFontSize(false);
        }
    });

});
</script>

</body>
</html>
