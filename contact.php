
<?php
/*
|--------------------------------------------------------------------------
| NEEDIFY - CONTACT AND REPLY SYSTEM
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

require_user_login();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$sender_id = (int) $_SESSION["user_id"];

$error = "";
$success = "";
$message = "";

if (empty($_SESSION["contact_user_csrf"])) {
    $_SESSION["contact_user_csrf"] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

/*
|--------------------------------------------------------------------------
| GET REPLY ID OR NEED ID
|--------------------------------------------------------------------------
*/

$reply_to_raw = $_GET["reply_to"] ?? $_POST["reply_to"] ?? "0";
$need_id_raw = $_GET["need_id"] ?? $_POST["need_id"] ?? "0";

if (
    !is_scalar($reply_to_raw) ||
    !ctype_digit((string)$reply_to_raw) ||
    !is_scalar($need_id_raw) ||
    !ctype_digit((string)$need_id_raw)
) {
    header("Location: needs.php");
    exit();
}

$reply_to = (int)$reply_to_raw;
$need_id = (int)$need_id_raw;

/*
|--------------------------------------------------------------------------
| LOAD NEED INFORMATION
|--------------------------------------------------------------------------
*/

if ($reply_to > 0) {

    // Reply to the original message sender.
    $sql = "SELECT
                needs.id,
                needs.user_id,
                needs.title,
                needs.category,
                needs.description,
                needs.budget,
                users.full_name,
                messages.sender_id AS reply_receiver_id
            FROM messages
            INNER JOIN needs
                ON messages.need_id = needs.id
            INNER JOIN users
                ON messages.sender_id = users.id
            WHERE messages.id = ?
            AND messages.receiver_id = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Unable to load reply. Please try again later.");
    }

    $stmt->bind_param("ii", $reply_to, $sender_id);

} else {

    // Start a new message about a need.
    if ($need_id < 1) {
        header("Location: needs.php");
        exit();
    }

    $sql = "SELECT
                needs.id,
                needs.user_id,
                needs.title,
                needs.category,
                needs.description,
                needs.budget,
                users.full_name
            FROM needs
            INNER JOIN users
                ON needs.user_id = users.id
            WHERE needs.id = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Unable to load this need. Please try again later.");
    }

    $stmt->bind_param("i", $need_id);
}

$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    $stmt->close();
    header("Location: needs.php");
    exit();
}

$need = $result->fetch_assoc();
$stmt->close();

$need_id = (int)$need["id"];

if ($reply_to > 0) {
    $receiver_id = (int)$need["reply_receiver_id"];
} else {
    $receiver_id = (int)$need["user_id"];
}

/*
|--------------------------------------------------------------------------
| PREVENT SENDING MESSAGE TO YOURSELF
|--------------------------------------------------------------------------
*/

if ($sender_id === $receiver_id) {
    header("Location: my-needs.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| SEND MESSAGE
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $message = isset($_POST["message"]) && is_string($_POST["message"])
        ? trim($_POST["message"])
        : "";

    $submitted_token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submitted_token) ||
        !hash_equals($_SESSION["contact_user_csrf"], $submitted_token)
    ) {
        $error = "Invalid form request. Please refresh and try again.";

    } elseif ($message === "") {

        $error = "Please write a message before sending.";

    } elseif (
        (function_exists("mb_strlen")
            ? mb_strlen($message, "UTF-8")
            : strlen($message)) > 5000
    ) {

        $error = "Your message must not exceed 5000 characters.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | VERIFY RECIPIENT AGAIN
        |--------------------------------------------------------------------------
        */

        if ($reply_to > 0) {

            // Only the receiver of the original message can reply.
            $check_sql = "SELECT need_id, sender_id
                          FROM messages
                          WHERE id = ?
                          AND receiver_id = ?";

            $check_stmt = $conn->prepare($check_sql);

            if (!$check_stmt) {
                $error = "Unable to verify this reply.";
            } else {

                $check_stmt->bind_param("ii", $reply_to, $sender_id);
                $check_stmt->execute();

                $check_result = $check_stmt->get_result();

                if (!$check_result || $check_result->num_rows === 0) {
                    $error = "This message is no longer available.";
                } else {

                    $original = $check_result->fetch_assoc();

                    $need_id = (int)$original["need_id"];
                    $receiver_id = (int)$original["sender_id"];

                }

                $check_stmt->close();
            }

        } else {

            $check_sql = "SELECT user_id FROM needs WHERE id = ?";

            $check_stmt = $conn->prepare($check_sql);

            if (!$check_stmt) {
                $error = "Unable to verify this need.";
            } else {

                $check_stmt->bind_param("i", $need_id);
                $check_stmt->execute();

                $check_result = $check_stmt->get_result();

                if (!$check_result || $check_result->num_rows === 0) {
                    $error = "This need is no longer available.";
                } else {

                    $current_need = $check_result->fetch_assoc();
                    $receiver_id = (int)$current_need["user_id"];

                    if ($receiver_id === $sender_id) {
                        $error = "You cannot message your own need.";
                    }
                }

                $check_stmt->close();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | INSERT MESSAGE
        |--------------------------------------------------------------------------
        */

        if ($error === "") {

            $insert_sql = "INSERT INTO messages
                (need_id, sender_id, receiver_id, message)
                VALUES (?, ?, ?, ?)";

            $insert_stmt = $conn->prepare($insert_sql);

            if (!$insert_stmt) {

                $error = "Unable to send message. Please try again.";

            } else {

                $insert_stmt->bind_param(
                    "iiis",
                    $need_id,
                    $sender_id,
                    $receiver_id,
                    $message
                );

                if ($insert_stmt->execute()) {

                    $success = "Your message has been sent successfully!";
                    $message = "";

                    $_SESSION["contact_user_csrf"] =
                        bin2hex(random_bytes(32));

                } else {

                    $error = "Message could not be sent. Please try again.";

                }

                $insert_stmt->close();
            }
        }
    }
}
?>  
             
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Needify | Contact User</title>

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
    --ink: #302743;
    --muted: #827b90;
    --border: #eee8f5;
    --white: #ffffff;
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
        radial-gradient(circle at 5% 10%, rgba(222,210,255,.32), transparent 26%),
        radial-gradient(circle at 95% 60%, rgba(255,221,233,.28), transparent 26%),
        #fcfaff;
}

a {
    text-decoration: none;
}

/* Navbar */
nav {
    padding: 17px 7%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
    background: rgba(255,255,255,.90);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border-bottom: 1px solid rgba(128,100,217,.10);
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
    background: linear-gradient(135deg,#a28aef,#7555cc);
    box-shadow: 0 7px 18px rgba(128,100,217,.25);
}

.nav-links {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 5px;
    flex-wrap: wrap;
}

.nav-links a {
    padding: 10px 11px;
    border-radius: 11px;
    color: #665d78;
    font-size: 12px;
    font-weight: 700;
    transition: all .25s ease;
}

.nav-links a:hover,
.nav-links a.active {
    color: var(--purple-dark);
    background: var(--lavender);
}

.nav-links a.logout {
    padding: 11px 15px;
    color: white;
    background: linear-gradient(135deg,#e78da5,#d96d8d);
}

.nav-links a.logout:hover {
    transform: translateY(-2px);
    box-shadow: 0 7px 17px rgba(217,109,141,.20);
}

/* Display Settings */
.site-tools {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    flex-shrink: 0;
    margin-left: auto;
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

/* Main */
.container {
    width: 90%;
    max-width: 850px;
    margin: 0 auto;
    padding: 42px 0 75px;
}

/* Hero */
.hero {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 22px;
    padding: 32px 36px;
    margin-bottom: 25px;
    border-radius: 26px;
    background: linear-gradient(120deg,#f0eaff,#fff0f5 65%,#fffaf0);
    border: 1px solid rgba(255,255,255,.9);
    box-shadow: 0 14px 38px rgba(83,61,130,.07);
    animation: fadeUp .6s ease both;
}

.hero::after {
    content: "";
    position: absolute;
    width: 200px;
    height: 200px;
    right: 50px;
    top: -130px;
    border-radius: 50%;
    background: rgba(255,255,255,.40);
}

.hero-content {
    position: relative;
    z-index: 1;
}

.eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 13px;
    margin-bottom: 13px;
    border: 1px solid rgba(128,100,217,.10);
    border-radius: 50px;
    background: rgba(255,255,255,.75);
    color: var(--purple-dark);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.3px;
}

.hero h1 {
    font-family: 'Playfair Display',serif;
    font-size: clamp(30px,4vw,42px);
    line-height: 1.2;
    margin-bottom: 10px;
}

.hero h1 span {
    color: var(--purple);
}

.hero p {
    max-width: 530px;
    color: #766d86;
    font-size: 13px;
    line-height: 1.8;
}

.hero-icon {
    width: 90px;
    height: 90px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    position: relative;
    z-index: 1;
    border-radius: 27px;
    background: rgba(255,255,255,.75);
    color: var(--purple);
    font-size: 35px;
    transform: rotate(5deg);
    box-shadow: 0 12px 30px rgba(99,71,182,.10);
}

/* Need Details */
.need-card {
    padding: 27px;
    margin-bottom: 22px;
    border: 1px solid var(--border);
    border-radius: 22px;
    background: rgba(255,255,255,.95);
    box-shadow: 0 9px 28px rgba(58,42,91,.045);
    animation: fadeUp .65s ease both;
}

.card-label {
    display: flex;
    align-items: center;
    gap: 9px;
    color: #9588ad;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.1px;
    margin-bottom: 17px;
}

.card-label i {
    color: var(--purple);
    font-size: 14px;
}

.category {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 12px;
    margin-bottom: 13px;
    border-radius: 50px;
    background: var(--lavender);
    color: var(--purple-dark);
    font-size: 11px;
    font-weight: 800;
}

.need-card h2 {
    font-family: 'Playfair Display',serif;
    font-size: 27px;
    line-height: 1.35;
    margin-bottom: 12px;
    overflow-wrap: anywhere;
}

.description {
    color: #71677f;
    font-size: 13px;
    line-height: 1.9;
    overflow-wrap: anywhere;
    margin-bottom: 19px;
}

.need-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    padding-top: 18px;
    border-top: 1px solid #f0ebf5;
}

.meta-item {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 11px 13px;
    border-radius: 12px;
    background: #faf7ff;
    color: #766b88;
    font-size: 11px;
}

.meta-item i {
    color: var(--purple);
}

.budget-value {
    color: #438d72;
    font-weight: 800;
}

/* Contact Form */
.form-box {
    padding: 30px;
    border: 1px solid var(--border);
    border-radius: 23px;
    background: rgba(255,255,255,.97);
    box-shadow: 0 12px 35px rgba(58,42,91,.055);
    animation: fadeUp .7s ease both;
}

.form-heading {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 9px;
}

.form-icon {
    width: 49px;
    height: 49px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    border-radius: 16px;
    background: linear-gradient(135deg,#eee6ff,#fbe4ed);
    color: var(--purple-dark);
    font-size: 19px;
}

.form-heading h2 {
    font-family: 'Playfair Display',serif;
    font-size: 25px;
}

.subtitle {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.8;
    margin: 0 0 25px 63px;
}

/* Alerts */
.alert {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 14px 16px;
    margin-bottom: 22px;
    border-radius: 12px;
    font-size: 12px;
    line-height: 1.7;
}

.alert i {
    margin-top: 2px;
}

.error {
    background: #fff0f1;
    color: #b64458;
    border: 1px solid #f8d8de;
}

.success {
    background: #edfaf3;
    color: #287b55;
    border: 1px solid #d0efdc;
}

/* Form Fields */
label {
    display: block;
    margin-bottom: 9px;
    color: #4a405d;
    font-size: 12px;
    font-weight: 800;
}

.required {
    color: #d96d8d;
}

.textarea-wrap {
    position: relative;
}

textarea {
    display: block;
    width: 100%;
    min-height: 175px;
    resize: vertical;
    padding: 17px;
    border: 1px solid #e7dfef;
    border-radius: 14px;
    background: #fdfbff;
    color: var(--ink);
    font-family: 'DM Sans',sans-serif;
    font-size: 13px;
    line-height: 1.8;
    transition: border-color .25s ease,box-shadow .25s ease;
}

textarea::placeholder {
    color: #b1a8be;
}

textarea:focus {
    outline: none;
    border-color: #a28aef;
    box-shadow: 0 0 0 4px rgba(128,100,217,.10);
}

.field-footer {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 9px;
    margin-bottom: 24px;
    color: #a198b0;
    font-size: 10px;
}

.field-footer i {
    color: var(--purple);
    margin-right: 5px;
}

/* Buttons */
.buttons {
    display: flex;
    gap: 12px;
}

.btn {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    min-height: 47px;
    padding: 13px 18px;
    border: none;
    border-radius: 12px;
    font-family: 'DM Sans',sans-serif;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    transition: transform .25s ease,box-shadow .25s ease;
}

.send-btn {
    color: white;
    background: linear-gradient(135deg,#967be8,#7555cc);
    box-shadow: 0 8px 20px rgba(117,85,204,.19);
}

.send-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 25px rgba(117,85,204,.27);
}

.cancel-btn {
    color: #6d637d;
    background: #f3eff9;
    border: 1px solid #eae2f4;
}

.cancel-btn:hover {
    transform: translateY(-2px);
    background: #ece5f7;
}

.security-note {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 7px;
    margin-top: 20px;
    color: #a198b0;
    font-size: 10px;
    text-align: center;
}

.security-note i {
    color: #6ba88a;
}

/* Footer */
footer {
    padding: 26px 7%;
    text-align: center;
    border-top: 1px solid #eee7f5;
    background: rgba(255,255,255,.70);
    color: #968da4;
    font-size: 12px;
}

footer strong {
    color: var(--purple-dark);
}

/* Page Up / Down */
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
    background: linear-gradient(135deg,#6548bd,#a48ae7);
    color: #fff;
    box-shadow: 0 7px 20px rgba(101,72,189,.25);
    cursor: pointer;
    font-size: 16px;
    transition: .2s ease;
}

.page-controls button:hover {
    transform: translateY(-3px);
}

/* Dark Mode */
body.dark-mode {
    color: #eee8f7;
    background: #171421;
}

body.dark-mode nav {
    background: rgba(36,32,49,.97);
    border-color: #443951;
}

body.dark-mode .logo {
    color: #d3c3ff;
}

body.dark-mode .nav-links a {
    color: #c7bdd5;
}

body.dark-mode .nav-links a:hover,
body.dark-mode .nav-links a.active {
    background: #393047;
    color: #d9caff;
}

body.dark-mode .hero {
    background: linear-gradient(120deg,#30283e,#352936 65%,#302b25);
    border-color: #493d5c;
}

body.dark-mode .hero h1,
body.dark-mode .need-card h2,
body.dark-mode .form-heading h2 {
    color: #f0e9fa;
}

body.dark-mode .hero p,
body.dark-mode .description {
    color: #c0b5cf;
}

body.dark-mode .eyebrow {
    background: #393047;
    color: #d9caff;
    border-color: #514565;
}

body.dark-mode .hero-icon {
    background: #393047;
    color: #d9caff;
}

body.dark-mode .need-card,
body.dark-mode .form-box {
    background: #242031;
    border-color: #493d5c;
}

body.dark-mode .card-label {
    color: #b8adc9;
}

body.dark-mode .category {
    background: #393047;
    color: #d9caff;
}

body.dark-mode .need-meta {
    border-color: #493d5c;
}

body.dark-mode .meta-item {
    background: #1d1928;
    color: #d0c5df;
}

body.dark-mode .budget-value {
    color: #83c9a8;
}

body.dark-mode .subtitle,
body.dark-mode .field-footer,
body.dark-mode .security-note {
    color: #b8adc9;
}

body.dark-mode label {
    color: #e5dcef;
}

body.dark-mode textarea {
    background: #1d1928;
    color: #eee8f7;
    border-color: #514565;
}

body.dark-mode textarea::placeholder {
    color: #9386a5;
}

body.dark-mode .cancel-btn {
    background: #393047;
    color: #e2d7f0;
    border-color: #514565;
}

body.dark-mode .cancel-btn:hover {
    background: #493d5c;
}

body.dark-mode .error {
    background: #44232e;
    color: #ffb7c4;
    border-color: #70404d;
}

body.dark-mode .success {
    background: #203d32;
    color: #a5e5c1;
    border-color: #365e4c;
}

body.dark-mode footer {
    background: #1b1726;
    border-color: #443951;
    color: #b8adc9;
}

body.dark-mode footer strong {
    color: #d9caff;
}

body.dark-mode .site-tools button {
    background: #393047;
    border-color: #514565;
    color: #eee8f7;
}

/* Responsive */
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

@media (max-width: 900px) {
    nav {
        padding: 16px 5%;
    }

    .container {
        width: 92%;
    }
}

@media (max-width: 700px) {
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

    .site-tools {
        margin: 0 auto;
    }

    .container {
        padding-top: 28px;
    }

    .hero {
        padding: 26px 22px;
        border-radius: 22px;
    }

    .hero h1 {
        font-size: 32px;
    }

    .hero p {
        font-size: 12px;
    }

    .hero-icon {
        width: 70px;
        height: 70px;
        border-radius: 21px;
        font-size: 27px;
    }

    .need-card,
    .form-box {
        padding: 22px;
    }

    .need-card h2 {
        font-size: 24px;
    }

    .form-heading h2 {
        font-size: 22px;
    }

    .subtitle {
        margin-left: 0;
    }

    .buttons {
        flex-direction: column;
    }
}

@media (max-width: 420px) {
    .hero-icon {
        display: none;
    }

    .hero h1 {
        font-size: 29px;
    }

    .need-meta {
        flex-direction: column;
        gap: 8px;
    }

    .meta-item {
        width: 100%;
    }

    .page-controls {
        right: 12px;
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

        <a href="needs.php" class="active">
            <i class="fa-solid fa-magnifying-glass"></i> Browse Needs
        </a>

        <a href="my-needs.php">
            <i class="fa-solid fa-clipboard-list"></i> My Needs
        </a>

        <a href="messages.php">
            <i class="fa-regular fa-envelope"></i> Messages
        </a>

        <a href="logout.php" class="logout">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
        </a>
    </div>

    <!-- Theme and Font Controls -->
    <div class="site-tools" aria-label="Display settings">
        <button type="button" id="fontDecrease" title="Decrease font size" aria-label="Decrease font size">
            <i class="fa-solid fa-minus"></i>
        </button>

        <button type="button" id="fontReset" title="Reset font size" aria-label="Reset font size">
            <i class="fa-solid fa-rotate-left"></i>
        </button>

        <button type="button" id="fontIncrease" title="Increase font size" aria-label="Increase font size">
            <i class="fa-solid fa-plus"></i>
        </button>

        <button type="button" id="themeToggle" title="Switch to dark mode" aria-label="Switch to dark mode">
            <i class="fa-solid fa-moon"></i>
        </button>
    </div>
</nav>

<main class="container">

    <section class="hero">
        <div class="hero-content">
            <div class="eyebrow">
                <i class="fa-regular fa-paper-plane"></i>
                CONNECT WITH THE COMMUNITY
            </div>

            <h1>Let's Start a <span>Conversation.</span></h1>

            <p>
                Have something helpful to offer? Send a thoughtful response
                to the person who posted this need and start connecting
                through Needify.
            </p>
        </div>

        <div class="hero-icon">
            <i class="fa-regular fa-comments"></i>
        </div>
    </section>

    <!-- Need Information -->
    <section class="need-card">
        <div class="card-label">
            <i class="fa-solid fa-thumbtack"></i>
            NEED DETAILS
        </div>

        <span class="category">
            <i class="fa-solid fa-tag"></i>
            <?php echo e($need["category"]); ?>
        </span>

        <h2><?php echo e($need["title"]); ?></h2>

        <p class="description">
            <?php echo nl2br(e($need["description"])); ?>
        </p>

        <div class="need-meta">
            <div class="meta-item">
                <i class="fa-solid fa-user"></i>
                <span>
                    Posted by <strong><?php echo e($need["full_name"]); ?></strong>
                </span>
            </div>

            <div class="meta-item">
                <i class="fa-solid fa-wallet"></i>
                <span>
                    Budget:
                    <strong class="budget-value">
                        <?php if ($need["budget"] !== null && $need["budget"] !== ""): ?>
                            Rs. <?php echo number_format((float)$need["budget"], 2); ?>
                        <?php else: ?>
                            Not specified
                        <?php endif; ?>
                    </strong>
                </span>
            </div>
        </div>
    </section>

    <!-- Contact Form -->
    <section class="form-box">

        <div class="form-heading">
            <div class="form-icon">
                <i class="fa-solid fa-pen-to-square"></i>
            </div>

            <h2>Send a Response</h2>
        </div>

        <p class="subtitle">
            Introduce yourself, explain how you can help, or ask a question.
        </p>

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

        <form method="POST" id="contactForm">
<input type="hidden" name="need_id" value="<?php echo (int)$need_id; ?>">

<input type="hidden" name="reply_to" value="<?php echo (int)$reply_to; ?>">
            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e($_SESSION["contact_user_csrf"]); ?>"
            >

            <label for="message">
                Your Message <span class="required">*</span>
            </label>

            <div class="textarea-wrap">
                <textarea
                    id="message"
                    name="message"
                    maxlength="5000"
                    placeholder="Hi! I saw your post and I may be able to help..."
                    required
                ><?php echo e($message); ?></textarea>
            </div>

            <div class="field-footer">
                <span>
                    <i class="fa-solid fa-heart"></i>
                    Keep your message clear and respectful.
                </span>

                <span id="characterCount">0 / 5000 characters</span>
            </div>

            <div class="buttons">
                <a href="needs.php" class="btn cancel-btn">
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Browse Needs
                </a>

                <button type="submit" class="btn send-btn">
                    <i class="fa-regular fa-paper-plane"></i>
                    Send Response
                </button>
            </div>

            <div class="security-note">
                <i class="fa-solid fa-shield-halved"></i>
                Be respectful and avoid sharing sensitive personal information.
            </div>

        </form>
    </section>

</main>

<footer>
    Made with <span style="color:#df7d9b;">♥</span> by <strong>Needify</strong>
    — Making your everyday needs easier.
</footer>

<!-- Page Up / Down Buttons -->
<div class="page-controls">
    <button type="button" id="pageUp" aria-label="Page Up" title="Page Up">
        <i class="fa-solid fa-arrow-up"></i>
    </button>

    <button type="button" id="pageDown" aria-label="Page Down" title="Page Down">
        <i class="fa-solid fa-arrow-down"></i>
    </button>
</div>

<!-- Character Counter -->
<script>
const messageField = document.getElementById("message");
const characterCount = document.getElementById("characterCount");

function updateCharacterCount() {
    const count = messageField.value.length;
    characterCount.textContent = count + " / 5000 characters";
}

messageField.addEventListener("input", updateCharacterCount);
updateCharacterCount();
</script>

<!-- Theme, Font Size and Page Navigation -->
<script>
(function () {

    const body = document.body;

    const themeToggle = document.getElementById("themeToggle");
    const fontDecrease = document.getElementById("fontDecrease");
    const fontReset = document.getElementById("fontReset");
    const fontIncrease = document.getElementById("fontIncrease");

    const pageUp = document.getElementById("pageUp");
    const pageDown = document.getElementById("pageDown");

    // Theme Settings
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

    // Font Size Settings
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

})();
</script>

</body>
</html>
