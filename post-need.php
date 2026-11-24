
<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

require_user_login();

/*
|--------------------------------------------------------------------------
| SECURITY HEADERS
|--------------------------------------------------------------------------
*/

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");

/*
|--------------------------------------------------------------------------
| USER LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| 100 SECONDS INACTIVITY TIMEOUT
|--------------------------------------------------------------------------
*/

$timeout_duration = 100;

if (
    !isset($_SESSION["user_last_activity"]) ||
    (time() - $_SESSION["user_last_activity"]) > $timeout_duration
) {
    unset(
        $_SESSION["user_id"],
        $_SESSION["full_name"],
        $_SESSION["email"],
        $_SESSION["user_last_activity"]
    );

    session_regenerate_id(true);

    header("Location: login.php?timeout=1");
    exit();
}

$_SESSION["user_last_activity"] = time();

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION["post_need_csrf"])) {
    $_SESSION["post_need_csrf"] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "";
$title = "";
$category = "";
$description = "";
$budget = "";

/*
|--------------------------------------------------------------------------
| FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $user_id = (int) $_SESSION["user_id"];

    $title = trim($_POST["title"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $budget = trim($_POST["budget"] ?? "");

    $allowed_categories = [
        "Electronics",
        "Clothing",
        "Books",
        "Home",
        "Jobs",
        "Services",
        "Other"
    ];

    /*
    |--------------------------------------------------------------------------
    | CSRF VALIDATION
    |--------------------------------------------------------------------------
    */

    $submitted_token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submitted_token) ||
        !hash_equals($_SESSION["post_need_csrf"], $submitted_token)
    ) {
        $message = "Invalid form request. Please refresh the page and try again.";
        $messageType = "error";
    }

    elseif (
        $title === "" ||
        $category === "" ||
        $description === ""
    ) {
        $message = "Please fill in all required fields.";
        $messageType = "error";
    }

    elseif (mb_strlen($title, "UTF-8") > 150) {
        $message = "Title must not exceed 150 characters.";
        $messageType = "error";
    }

    elseif (!in_array($category, $allowed_categories, true)) {
        $message = "Please select a valid category.";
        $messageType = "error";
    }

    elseif (mb_strlen($description, "UTF-8") > 5000) {
        $message = "Description must not exceed 5000 characters.";
        $messageType = "error";
    }

    else {

        /*
        |--------------------------------------------------------------------------
        | BUDGET VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($budget === "") {
            $budget = null;
        }

        elseif (
            !is_numeric($budget) ||
            !is_finite((float) $budget) ||
            (float) $budget < 0 ||
            (float) $budget > 99999999.99
        ) {
            $message = "Please enter a valid budget.";
            $messageType = "error";
        }

        else {
            $budget = (float) $budget;
        }

        /*
        |--------------------------------------------------------------------------
        | SAVE NEED
        |--------------------------------------------------------------------------
        */

        if ($message === "") {

            $stmt = $conn->prepare(
                "INSERT INTO needs
                (user_id, title, category, description, budget)
                VALUES (?, ?, ?, ?, ?)"
            );

            if ($stmt) {

                $stmt->bind_param(
                    "isssd",
                    $user_id,
                    $title,
                    $category,
                    $description,
                    $budget
                );

                if ($stmt->execute()) {

                    $message = "Your need has been posted successfully!";
                    $messageType = "success";

                    $title = "";
                    $category = "";
                    $description = "";
                    $budget = "";

                    $_SESSION["post_need_csrf"] = bin2hex(random_bytes(32));

                } else {

                    $message = "Something went wrong. Please try again.";
                    $messageType = "error";

                }

                $stmt->close();

            } else {

                $message = "Unable to process your request. Please try again.";
                $messageType = "error";

            }
        }
    }
}

$escaped_title = htmlspecialchars($title, ENT_QUOTES, "UTF-8");
$escaped_category = htmlspecialchars($category, ENT_QUOTES, "UTF-8");
$escaped_description = htmlspecialchars($description, ENT_QUOTES, "UTF-8");
$escaped_budget = htmlspecialchars((string) ($budget ?? ""), ENT_QUOTES, "UTF-8");
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Post a Need | Needify</title>

    <link rel="icon" type="image/png" href="favicon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>

        :root {
            --purple: #8064c9;
            --purple-dark: #6547ae;
            --purple-light: #f0eaff;
            --pink: #f4b7cf;
            --pink-light: #fff0f6;
            --cream: #fffaf5;
            --background: #fcf9ff;
            --text: #33294b;
            --muted: #8a819b;
            --border: #eee7f6;
            --white: #ffffff;
            --green: #24835b;
            --green-bg: #e8f8ef;
            --red: #c74b63;
            --red-bg: #fff0f3;
            --shadow: 0 18px 50px rgba(89, 64, 133, 0.09);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            min-height: 100vh;
            font-family: 'DM Sans', sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 4% 10%, rgba(244,183,207,.18), transparent 27%),
                radial-gradient(circle at 95% 45%, rgba(177,153,237,.15), transparent 28%),
                var(--background);
            line-height: 1.6;
        }

        a {
            -webkit-tap-highlight-color: transparent;
        }

        /* NAVIGATION */

        .navbar {
            min-height: 82px;
            width: 100%;
            padding: 17px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 22px;
            flex-wrap: wrap;
            background: rgba(255,255,255,.88);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(238,231,246,.9);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            color: var(--purple);
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -1.5px;
            text-decoration: none;
        }

        .logo span {
            color: #e99bbd;
        }

        .nav-links {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 7px;
        }

        .nav-links a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 12px;
            text-decoration: none;
            color: #756b89;
            font-size: 13px;
            font-weight: 700;
            transition: .25s ease;
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: var(--purple-light);
            color: var(--purple-dark);
        }

        .nav-links .logout-btn {
            color: white;
            background: linear-gradient(135deg, var(--purple), #a48ae7);
            padding: 11px 17px;
            box-shadow: 0 5px 14px rgba(128,100,201,.18);
        }

        .nav-links .logout-btn:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(128,100,201,.25);
        }

        /* MAIN PAGE */

        .page {
            width: 90%;
            max-width: 1120px;
            margin: 44px auto 75px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 24px;
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            animation: fadeUp .5s ease both;
        }

        .breadcrumb a {
            text-decoration: none;
            color: var(--purple);
        }

        .breadcrumb a:hover {
            color: var(--purple-dark);
        }

        .breadcrumb i {
            font-size: 10px;
            color: #c5b8da;
        }

        /* FORM LAYOUT */

        .content-layout {
            display: grid;
            grid-template-columns: .85fr 1.4fr;
            gap: 27px;
            align-items: start;
        }

        /* LEFT PANEL */

        .intro-panel {
            position: relative;
            overflow: hidden;
            padding: 36px 29px;
            border-radius: 26px;
            background: linear-gradient(145deg, #7860bd 0%, #9a81d7 52%, #dfa7c4 100%);
            color: white;
            box-shadow: 0 18px 42px rgba(128,100,201,.19);
            animation: fadeUp .55s ease both;
        }

        .intro-panel::before {
            content: "";
            position: absolute;
            width: 230px;
            height: 230px;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,.20);
            top: -95px;
            right: -95px;
            box-shadow:
                0 0 0 22px rgba(255,255,255,.035),
                0 0 0 46px rgba(255,255,255,.025);
        }

        .intro-panel::after {
            content: "✦";
            position: absolute;
            right: 25px;
            bottom: 7px;
            font-size: 75px;
            color: rgba(255,255,255,.14);
            transform: rotate(12deg);
        }

        .intro-icon {
            width: 61px;
            height: 61px;
            display: grid;
            place-items: center;
            border-radius: 19px;
            background: rgba(255,255,255,.18);
            border: 1px solid rgba(255,255,255,.28);
            font-size: 24px;
            margin-bottom: 25px;
            position: relative;
            z-index: 1;
        }

        .intro-panel h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(30px, 3vw, 39px);
            line-height: 1.2;
            margin-bottom: 16px;
            position: relative;
            z-index: 1;
        }

        .intro-panel h1 span {
            color: #ffe2ee;
        }

        .intro-panel > p {
            font-size: 13px;
            line-height: 1.9;
            color: rgba(255,255,255,.88);
            position: relative;
            z-index: 1;
        }

        .benefits {
            display: grid;
            gap: 19px;
            margin-top: 32px;
            position: relative;
            z-index: 1;
        }

        .benefit {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .benefit-icon {
            flex: 0 0 35px;
            width: 35px;
            height: 35px;
            display: grid;
            place-items: center;
            background: rgba(255,255,255,.16);
            border: 1px solid rgba(255,255,255,.19);
            border-radius: 11px;
            font-size: 13px;
        }

        .benefit h3 {
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 3px;
        }

        .benefit p {
            font-size: 11px;
            color: rgba(255,255,255,.78);
            line-height: 1.7;
        }

        .tip-card {
            position: relative;
            z-index: 1;
            margin-top: 31px;
            padding: 17px;
            border-radius: 16px;
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.23);
        }

        .tip-card strong {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            margin-bottom: 7px;
        }

        .tip-card p {
            font-size: 11px;
            line-height: 1.8;
            color: rgba(255,255,255,.86);
        }

        /* FORM CARD */

        .form-card {
            padding: 36px;
            border-radius: 26px;
            background: rgba(255,255,255,.95);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            animation: fadeUp .65s ease both;
        }

        .form-header {
            margin-bottom: 28px;
        }

        .form-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--purple);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.7px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .form-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 31px;
            line-height: 1.25;
            margin-bottom: 9px;
        }

        .form-header p {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.8;
        }

        .required-note {
            color: var(--muted);
            font-size: 11px;
            margin-bottom: 22px;
        }

        .required {
            color: var(--red);
        }

        /* ALERTS */

        .message {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            padding: 14px 16px;
            border-radius: 13px;
            margin-bottom: 23px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.7;
        }

        .message i {
            margin-top: 3px;
        }

        .message.success {
            color: var(--green);
            background: var(--green-bg);
            border: 1px solid #ccebd9;
        }

        .message.error {
            color: var(--red);
            background: var(--red-bg);
            border: 1px solid #f8d5de;
        }

        /* INPUT FIELDS */

        .form-group {
            margin-bottom: 23px;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 9px;
            color: #493c60;
            font-size: 12px;
            font-weight: 800;
        }

        .form-group label > i {
            color: var(--purple);
            font-size: 12px;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap > i {
            position: absolute;
            left: 16px;
            top: 17px;
            color: #a69abb;
            font-size: 14px;
            pointer-events: none;
        }

        input,
        select,
        textarea {
            width: 100%;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 500;
            color: var(--text);
            background: #fdfbff;
            border: 1px solid #e9e2f2;
            border-radius: 13px;
            outline: none;
            transition: border-color .25s ease, box-shadow .25s ease, background .25s ease;
        }

        input,
        select {
            min-height: 49px;
            padding: 13px 15px 13px 43px;
        }

        select {
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
            padding-right: 42px;
        }

        .select-arrow {
            position: absolute;
            right: 16px;
            top: 17px;
            color: #a69abb;
            font-size: 12px;
            pointer-events: none;
        }

        textarea {
            min-height: 145px;
            padding: 15px;
            line-height: 1.8;
            resize: vertical;
        }

        input::placeholder,
        textarea::placeholder {
            color: #b2a9c0;
            font-weight: 400;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--purple);
            background: white;
            box-shadow: 0 0 0 4px rgba(128,100,201,.10);
        }

        .hint {
            display: flex;
            align-items: flex-start;
            gap: 7px;
            margin-top: 8px;
            color: var(--muted);
            font-size: 10px;
            line-height: 1.7;
        }

        .hint i {
            margin-top: 3px;
            color: var(--purple);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* SUBMIT BUTTON */

        .post-btn {
            width: 100%;
            min-height: 53px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 6px;
            border: none;
            border-radius: 14px;
            color: white;
            background: linear-gradient(110deg, var(--purple), #a48ae7 65%, #d99bbd);
            box-shadow: 0 9px 21px rgba(128,100,201,.21);
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            transition: transform .25s ease, box-shadow .25s ease;
        }

        .post-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 13px 27px rgba(128,100,201,.30);
        }

        .post-btn:active {
            transform: translateY(0);
        }

        .privacy-note {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 17px;
            color: var(--muted);
            font-size: 10px;
            text-align: center;
        }

        .privacy-note i {
            color: var(--green);
        }

        /* FOOTER */

        footer {
            background: #302641;
            color: #e7dff1;
            padding: 29px 6%;
            text-align: center;
            font-size: 12px;
        }

        .footer-logo {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 6px;
        }

        .footer-logo span {
            color: #e9a4c3;
        }

        footer p {
            color: #c9bed9;
        }

        .footer-heart {
            color: #f4a8c8;
        }

        /* ACCESSIBILITY CONTROLS */

        .accessibility-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 6px;
            padding: 5px;
            border-radius: 12px;
            background: rgba(128,100,201,.08);
        }

        .accessibility-controls button {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--white);
            color: var(--purple-dark);
            cursor: pointer;
            font-family: inherit;
            font-size: 12px;
            font-weight: 800;
            transition: .2s ease;
        }

        .accessibility-controls button:hover {
            color: white;
            background: var(--purple);
            border-color: var(--purple);
            transform: translateY(-2px);
        }

        .accessibility-controls .theme-toggle {
            width: auto;
            padding: 0 11px;
            display: flex;
            gap: 7px;
        }

        /* PAGE UP AND PAGE DOWN */

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
            color: #fff;
            background: linear-gradient(135deg, #9276e1, #7557ca);
            box-shadow: 0 7px 20px rgba(101,72,189,.28);
            font-size: 1.1rem;
            cursor: pointer;
            transition: transform .25s ease, box-shadow .25s ease;
        }

        .scroll-button:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 24px rgba(101,72,189,.35);
        }

        .scroll-button:focus-visible {
            outline: 3px solid #f4b8d1;
            outline-offset: 4px;
        }

        /* DARK MODE */

        body.dark-mode {
            --background: #171321;
            --text: #eee8f8;
            --muted: #b2a8c4;
            --border: #393047;
            --white: #292235;
            --purple-light: #392d50;
            --pink-light: #402b3b;
            --green-bg: #203c30;
            --red-bg: #482a35;

            background:
                radial-gradient(circle at 4% 10%, rgba(128,100,201,.12), transparent 27%),
                radial-gradient(circle at 95% 45%, rgba(177,153,237,.09), transparent 28%),
                var(--background);

            color: var(--text);
        }

        body.dark-mode .navbar {
            background: rgba(30,25,42,.95);
            border-bottom-color: #393047;
        }

        body.dark-mode .nav-links a {
            color: #c5bad8;
        }

        body.dark-mode .nav-links a:hover,
        body.dark-mode .nav-links a.active {
            background: #392d50;
            color: #d9c9ff;
        }

        body.dark-mode .accessibility-controls {
            background: #30273f;
        }

        body.dark-mode .accessibility-controls button {
            background: #211b2e;
            color: #e0d2ff;
            border-color: #493d5c;
        }

        body.dark-mode .accessibility-controls button:hover {
            background: var(--purple);
            color: white;
        }

        body.dark-mode .form-card {
            background: #241e30;
            border-color: #393047;
        }

        body.dark-mode .form-header h2,
        body.dark-mode .form-group label {
            color: #eee8f8;
        }

        body.dark-mode .form-header p,
        body.dark-mode .required-note,
        body.dark-mode .hint,
        body.dark-mode .privacy-note {
            color: #b2a8c4;
        }

        body.dark-mode input,
        body.dark-mode select,
        body.dark-mode textarea {
            color: #eee8f8;
            background: #191523;
            border-color: #49405a;
        }

        body.dark-mode input:focus,
        body.dark-mode select:focus,
        body.dark-mode textarea:focus {
            background: #211b2e;
            border-color: #a48ae7;
        }

        body.dark-mode input::placeholder,
        body.dark-mode textarea::placeholder {
            color: #9388a8;
        }

        body.dark-mode .message.success {
            color: #a5e5bd;
            background: #203c30;
            border-color: #315b43;
        }

        body.dark-mode .message.error {
            color: #ffb3c2;
            background: #482a35;
            border-color: #673746;
        }

        body.dark-mode .breadcrumb {
            color: #b2a8c4;
        }

        body.dark-mode .breadcrumb i {
            color: #76678f;
        }

        body.dark-mode footer {
            background: #110e19;
        }

        body {
            zoom: var(--page-zoom, 1);
        }

        /* ANIMATION */

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

        /* RESPONSIVE */

        @media (max-width: 900px) {

            .navbar {
                padding: 17px 5%;
            }

            .content-layout {
                grid-template-columns: 1fr;
            }

            .intro-panel {
                padding: 30px;
            }

            .intro-panel h1 {
                max-width: 450px;
            }

            .benefits {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 15px;
            }

            .benefit {
                flex-direction: column;
            }

            .tip-card {
                max-width: 500px;
            }

            .form-card {
                padding: 32px;
            }
        }

        @media (max-width: 600px) {

            .navbar {
                position: relative;
                flex-direction: column;
                justify-content: center;
                gap: 12px;
            }

            .logo {
                text-align: center;
            }

            .nav-links {
                justify-content: center;
                gap: 4px;
            }

            .nav-links a {
                padding: 9px;
                font-size: 11px;
            }

            .accessibility-controls {
                width: 100%;
            }

            .page {
                width: 92%;
                margin: 25px auto 45px;
            }

            .intro-panel {
                padding: 27px 23px;
                border-radius: 22px;
            }

            .intro-panel h1 {
                font-size: 32px;
            }

            .benefits {
                grid-template-columns: 1fr;
                gap: 17px;
            }

            .benefit {
                flex-direction: row;
            }

            .form-card {
                padding: 25px 20px;
                border-radius: 22px;
            }

            .form-header h2 {
                font-size: 28px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
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

        @media (max-width: 360px) {

            .nav-links a {
                padding: 8px 7px;
                font-size: 10px;
            }

            .form-card {
                padding: 22px 16px;
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

<nav class="navbar">

    <a href="index.php" class="logo">
        Needify<span>.</span>
    </a>

    <div class="nav-links">

        <a href="index.php">
            <i class="fa-solid fa-house"></i>
            Home
        </a>

        <a href="dashboard.php">
            <i class="fa-solid fa-grid-2"></i>
            Dashboard
        </a>

        <a href="needs.php">
            <i class="fa-solid fa-magnifying-glass"></i>
            Browse Needs
        </a>

        <a href="post-need.php" class="active">
            <i class="fa-solid fa-plus"></i>
            Post a Need
        </a>

        <a href="logout.php" class="logout-btn">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
            Logout
        </a>

    </div>

    <div class="accessibility-controls">

        <button type="button" id="decreaseFont" title="Decrease font size" aria-label="Decrease font size">
            A-
        </button>

        <button type="button" id="resetFont" title="Reset font size" aria-label="Reset font size">
            A
        </button>

        <button type="button" id="increaseFont" title="Increase font size" aria-label="Increase font size">
            A+
        </button>

        <button type="button" class="theme-toggle" id="themeToggle" title="Change theme">
            <i class="fa-solid fa-moon" id="themeIcon"></i>
            <span id="themeText">Dark Mode</span>
        </button>

    </div>

</nav>

<main class="page">

    <div class="breadcrumb">

        <a href="dashboard.php">Dashboard</a>

        <i class="fa-solid fa-chevron-right"></i>

        <span>Post a Need</span>

    </div>

    <div class="content-layout">

        <!-- LEFT INTRODUCTION PANEL -->

        <aside class="intro-panel">

            <div class="intro-icon">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
            </div>

            <h1>
                Your need.<br>
                Your way.<br>
                <span>Your community.</span>
            </h1>

            <p>
                Looking for something specific? Share what you need
                with the Needify community and make finding it easier.
            </p>

            <div class="benefits">

                <div class="benefit">

                    <div class="benefit-icon">
                        <i class="fa-solid fa-bullseye"></i>
                    </div>

                    <div>
                        <h3>Be Specific</h3>
                        <p>A clear title helps people understand your need.</p>
                    </div>

                </div>

                <div class="benefit">

                    <div class="benefit-icon">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </div>

                    <div>
                        <h3>Add Details</h3>
                        <p>Describe what you're looking for in your own words.</p>
                    </div>

                </div>

                <div class="benefit">

                    <div class="benefit-icon">
                        <i class="fa-solid fa-wallet"></i>
                    </div>

                    <div>
                        <h3>Set a Budget</h3>
                        <p>Share your maximum budget to make expectations clear.</p>
                    </div>

                </div>

            </div>

            <div class="tip-card">

                <strong>
                    <i class="fa-regular fa-lightbulb"></i>
                    A little tip
                </strong>

                <p>
                    A helpful description can make your post easier
                    for others to understand. Include the important
                    details about the item or service you need.
                </p>

            </div>

        </aside>

        <!-- POST NEED FORM -->

        <section class="form-card">

            <div class="form-header">

                <div class="form-eyebrow">
                    <i class="fa-solid fa-sparkles"></i>
                    LET'S GET STARTED
                </div>

                <h2>Post your need</h2>

                <p>
                    Tell us what you're searching for.
                    Fill in the details below to create your post.
                </p>

            </div>

            <p class="required-note">
                Fields marked with <span class="required">*</span> are required.
            </p>

            <?php if (!empty($message)): ?>

                <div class="message <?php echo $messageType === 'success' ? 'success' : 'error'; ?>">

                    <i class="fa-solid <?php echo $messageType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>

                    <span>
                        <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
                    </span>

                </div>

            <?php endif; ?>

            <form method="POST" action="">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars($_SESSION["post_need_csrf"], ENT_QUOTES, "UTF-8"); ?>"
                >

                <!-- TITLE -->

                <div class="form-group">

                    <label for="title">
                        <i class="fa-solid fa-heading"></i>
                        What do you need?
                        <span class="required">*</span>
                    </label>

                    <div class="input-wrap">

                        <i class="fa-solid fa-pen"></i>

                        <input
                            type="text"
                            id="title"
                            name="title"
                            placeholder="e.g. Second-hand laptop"
                            value="<?php echo $escaped_title; ?>"
                            maxlength="150"
                            required
                        >

                    </div>

                    <div class="hint">
                        <i class="fa-regular fa-lightbulb"></i>
                        Write a short, clear title for your request.
                    </div>

                </div>

                <!-- CATEGORY -->

                <div class="form-group">

                    <label for="category">
                        <i class="fa-solid fa-layer-group"></i>
                        Choose a category
                        <span class="required">*</span>
                    </label>

                    <div class="input-wrap">

                        <i class="fa-solid fa-list"></i>

                        <select id="category" name="category" required>

                            <option value="">Select a category</option>

                            <option value="Electronics" <?php echo $category === "Electronics" ? "selected" : ""; ?>>
                                Electronics
                            </option>

                            <option value="Clothing" <?php echo $category === "Clothing" ? "selected" : ""; ?>>
                                Clothing
                            </option>

                            <option value="Books" <?php echo $category === "Books" ? "selected" : ""; ?>>
                                Books & Education
                            </option>

                            <option value="Home" <?php echo $category === "Home" ? "selected" : ""; ?>>
                                Home & Furniture
                            </option>

                            <option value="Jobs" <?php echo $category === "Jobs" ? "selected" : ""; ?>>
                                Jobs & Work
                            </option>

                            <option value="Services" <?php echo $category === "Services" ? "selected" : ""; ?>>
                                Services
                            </option>

                            <option value="Other" <?php echo $category === "Other" ? "selected" : ""; ?>>
                                Other
                            </option>

                        </select>

                        <i class="fa-solid fa-chevron-down select-arrow"></i>

                    </div>

                </div>

                <!-- DESCRIPTION -->

                <div class="form-group">

                    <label for="description">
                        <i class="fa-solid fa-align-left"></i>
                        Describe your need
                        <span class="required">*</span>
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        placeholder="Describe what you're looking for, including any important details..."
                        maxlength="5000"
                        required
                    ><?php echo $escaped_description; ?></textarea>

                    <div class="hint">
                        <i class="fa-solid fa-circle-info"></i>
                        Include useful details so others can understand your request.
                    </div>

                </div>

                <!-- BUDGET -->

                <div class="form-group">

                    <label for="budget">
                        <i class="fa-solid fa-wallet"></i>
                        Your maximum budget
                        <span style="color: var(--muted); font-weight: 500;">(Optional)</span>
                    </label>

                    <div class="input-wrap">

                        <i class="fa-solid fa-coins"></i>

                        <input
                            type="number"
                            id="budget"
                            name="budget"
                            placeholder="e.g. 50000"
                            min="0"
                            max="99999999.99"
                            step="0.01"
                            value="<?php echo $escaped_budget; ?>"
                        >

                    </div>

                    <div class="hint">
                        <i class="fa-solid fa-circle-info"></i>
                        Enter your maximum budget in Pakistani Rupees (PKR).
                    </div>

                </div>

                <!-- SUBMIT -->

                <button type="submit" class="post-btn">

                    <i class="fa-solid fa-paper-plane"></i>
                    Post My Need
                    <i class="fa-solid fa-arrow-right"></i>

                </button>

                <div class="privacy-note">
                    <i class="fa-solid fa-shield-halved"></i>
                    Your request will be saved to the Needify database.
                </div>

            </form>

        </section>

    </div>

</main>

<footer>

    <div class="footer-logo">
        Needify<span>.</span>
    </div>

    <p>
        Making everyday shopping simpler, one need at a time.
    </p>

    <p style="margin-top: 12px;">
        &copy; <?php echo date("Y"); ?> Needify.
        Made with <span class="footer-heart">♥</span> for smarter shopping.
    </p>

</footer>

<!-- PAGE UP AND PAGE DOWN BUTTONS -->

<div class="page-scroll-controls">

    <button
        type="button"
        class="scroll-button"
        id="pageUpButton"
        aria-label="Page Up - Scroll to top"
        title="Page Up - Go to top"
    >
        <i class="fa-solid fa-arrow-up"></i>
    </button>

    <button
        type="button"
        class="scroll-button"
        id="pageDownButton"
        aria-label="Page Down - Scroll to bottom"
        title="Page Down - Go to bottom"
    >
        <i class="fa-solid fa-arrow-down"></i>
    </button>

</div>

<script>

document.addEventListener("DOMContentLoaded", function () {

    // THEME SETTINGS

    const themeToggle = document.getElementById("themeToggle");
    const themeIcon = document.getElementById("themeIcon");
    const themeText = document.getElementById("themeText");

    function applyTheme(theme) {

        if (theme === "dark") {

            document.body.classList.add("dark-mode");
            themeIcon.className = "fa-solid fa-sun";
            themeText.textContent = "Light Mode";

        } else {

            document.body.classList.remove("dark-mode");
            themeIcon.className = "fa-solid fa-moon";
            themeText.textContent = "Dark Mode";

        }
    }

    const savedTheme = localStorage.getItem("needifyTheme") || "light";

    applyTheme(savedTheme);

    themeToggle.addEventListener("click", function () {

        const isDark = document.body.classList.contains("dark-mode");
        const newTheme = isDark ? "light" : "dark";

        localStorage.setItem("needifyTheme", newTheme);

        applyTheme(newTheme);

    });


    // FONT SIZE SETTINGS

    const decreaseFont = document.getElementById("decreaseFont");
    const resetFont = document.getElementById("resetFont");
    const increaseFont = document.getElementById("increaseFont");

    let fontLevel = parseInt(localStorage.getItem("needifyFontLevel"), 10);

    if (isNaN(fontLevel)) {
        fontLevel = 0;
    }

    function applyFontSize() {

        fontLevel = Math.max(-2, Math.min(2, fontLevel));

        const zoomLevel = 1 + (fontLevel * 0.05);

        document.body.style.setProperty("--page-zoom", zoomLevel);

        localStorage.setItem("needifyFontLevel", fontLevel);

    }

    applyFontSize();

    decreaseFont.addEventListener("click", function () {

        if (fontLevel > -2) {
            fontLevel--;
            applyFontSize();
        }

    });

    increaseFont.addEventListener("click", function () {

        if (fontLevel < 2) {
            fontLevel++;
            applyFontSize();
        }

    });

    resetFont.addEventListener("click", function () {

        fontLevel = 0;
        applyFontSize();

    });


    // PAGE UP

    const pageUpButton = document.getElementById("pageUpButton");

    pageUpButton.addEventListener("click", function () {

        window.scrollTo({
            top: 0,
            behavior: "smooth"
        });

    });


    // PAGE DOWN

    const pageDownButton = document.getElementById("pageDownButton");

    pageDownButton.addEventListener("click", function () {

        window.scrollTo({
            top: document.documentElement.scrollHeight,
            behavior: "smooth"
        });

    });

});

</script>

</body>
</html>
