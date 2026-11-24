


<?php
/*
|--------------------------------------------------------------------------
| CENTRAL SESSION, AUTHENTICATION AND DATABASE
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

/*
|--------------------------------------------------------------------------
| USER LOGIN CHECK
|--------------------------------------------------------------------------
*/

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
header("Referrer-Policy: strict-origin-when-cross-origin");

/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$user_id = (int) $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| GET RECEIVED MESSAGES
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            messages.id,
            messages.need_id,
            messages.message,
            messages.created_at,
            needs.title AS need_title,
            users.full_name AS sender_name,
            users.email AS sender_email
        FROM messages
        INNER JOIN needs
            ON messages.need_id = needs.id
        INNER JOIN users
            ON messages.sender_id = users.id
        WHERE messages.receiver_id = ?
        ORDER BY messages.created_at DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Unable to load messages. Please try again later.");
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$total_messages = $result->num_rows;

/*
|--------------------------------------------------------------------------
| HTML ESCAPE HELPER
|--------------------------------------------------------------------------
*/

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Needify | My Messages</title>
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

    html {
        scroll-behavior: smooth;
    }

    body {
        min-height: 100vh;
        font-family: 'DM Sans', sans-serif;
        color: var(--ink);
        background:
            radial-gradient(circle at 5% 12%, rgba(222, 210, 255, .32), transparent 25%),
            radial-gradient(circle at 95% 48%, rgba(255, 221, 233, .28), transparent 26%),
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
        background: linear-gradient(135deg, #e78da5, #d96d8d);
    }

    .nav-links a.logout:hover {
        transform: translateY(-2px);
        box-shadow: 0 7px 17px rgba(217, 109, 141, .20);
    }

    /* Main layout */
    .container {
        width: 89%;
        max-width: 1050px;
        margin: 0 auto;
        padding: 48px 0 75px;
    }

    /* Hero */
    .hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 25px;
        padding: 38px 40px;
        margin-bottom: 28px;
        position: relative;
        overflow: hidden;
        border-radius: 28px;
        background: linear-gradient(120deg, #f0eaff, #fff0f5 65%, #fffaf0);
        border: 1px solid rgba(255, 255, 255, .9);
        box-shadow: 0 15px 40px rgba(83, 61, 130, .07);
        animation: fadeUp .6s ease both;
    }

    .hero::after {
        content: "";
        width: 230px;
        height: 230px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .36);
        position: absolute;
        right: 70px;
        top: -145px;
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
        margin-bottom: 15px;
        border: 1px solid rgba(128, 100, 217, .10);
        border-radius: 50px;
        background: rgba(255, 255, 255, .75);
        color: var(--purple-dark);
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 1.3px;
    }

    .hero h1 {
        font-family: 'Playfair Display', serif;
        font-size: clamp(33px, 4vw, 46px);
        line-height: 1.2;
        letter-spacing: -.7px;
        margin-bottom: 12px;
    }

    .hero h1 span {
        color: var(--purple);
    }

    .hero p {
        max-width: 560px;
        color: #766d86;
        font-size: 14px;
        line-height: 1.8;
    }

    .hero-icon {
        width: 105px;
        height: 105px;
        flex-shrink: 0;
        display: grid;
        place-items: center;
        position: relative;
        z-index: 1;
        border-radius: 30px;
        background: rgba(255, 255, 255, .72);
        color: var(--purple);
        font-size: 40px;
        transform: rotate(5deg);
        box-shadow: 0 12px 30px rgba(99, 71, 182, .10);
    }

    /* Message count */
    .section-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
        margin: 32px 0 20px;
        animation: fadeUp .65s ease both;
    }

    .heading-left {
        display: flex;
        align-items: center;
        gap: 13px;
    }

    .heading-icon {
        width: 47px;
        height: 47px;
        display: grid;
        place-items: center;
        border-radius: 15px;
        background: var(--lavender);
        color: var(--purple-dark);
        font-size: 18px;
    }

    .section-heading h2 {
        font-size: 18px;
        font-weight: 800;
        margin-bottom: 4px;
    }

    .section-heading p {
        color: var(--muted);
        font-size: 12px;
    }

    .count-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 27px;
        height: 27px;
        padding: 0 8px;
        margin-left: 5px;
        border-radius: 9px;
        background: var(--pink);
        color: #a74769;
        font-size: 12px;
        font-weight: 800;
    }

    /* Message cards */
    .messages-list {
        display: grid;
        gap: 19px;
    }

    .message-card {
        position: relative;
        padding: 25px;
        border: 1px solid var(--border);
        border-left: 4px solid #b29be9;
        border-radius: 21px;
        background: rgba(255, 255, 255, .95);
        box-shadow: 0 8px 25px rgba(58, 42, 91, .045);
        transition: transform .25s ease, box-shadow .25s ease;
        animation: fadeUp .6s ease both;
    }

    .message-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 32px rgba(83, 61, 130, .09);
    }

    .message-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 18px;
        margin-bottom: 20px;
    }

    .sender-info {
        display: flex;
        align-items: center;
        gap: 13px;
        min-width: 0;
    }

    .avatar {
        width: 48px;
        height: 48px;
        flex-shrink: 0;
        display: grid;
        place-items: center;
        border-radius: 16px;
        background: linear-gradient(135deg, #e9e0ff, #fbe2ec);
        color: var(--purple-dark);
        font-size: 19px;
        font-weight: 800;
    }

    .sender-details {
        min-width: 0;
    }

    .sender-name {
        color: var(--ink);
        font-size: 15px;
        font-weight: 800;
        overflow-wrap: anywhere;
        margin-bottom: 5px;
    }

    .sender-caption {
        color: #9b92a8;
        font-size: 11px;
    }

    .date {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        flex-shrink: 0;
        padding: 9px 11px;
        border-radius: 10px;
        background: #faf7ff;
        color: #827594;
        font-size: 11px;
        font-weight: 700;
    }

    .date i {
        color: var(--purple);
    }

    /* Need title */
    .need-title {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 13px 15px;
        margin-bottom: 16px;
        border: 1px solid #eee6fc;
        border-radius: 13px;
        background: #f8f4ff;
        color: #6d52b7;
        font-size: 12px;
        line-height: 1.7;
    }

    .need-title i {
        margin-top: 3px;
        flex-shrink: 0;
    }

    .need-title strong {
        color: #8e7cb2;
        font-weight: 700;
    }

    .need-title span {
        color: #59418f;
        font-weight: 800;
        overflow-wrap: anywhere;
    }

    /* Message body */
    .message-label {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #6f657f;
        font-size: 11px;
        font-weight: 800;
        margin-bottom: 10px;
    }

    .message-label i {
        color: #ce83a0;
    }

    .message-text {
        padding: 18px;
        border: 1px solid #f1edf7;
        border-radius: 14px;
        background: #fdfbff;
        color: #665d76;
        font-size: 13px;
        line-height: 1.9;
        overflow-wrap: anywhere;
        white-space: normal;
    }

    /* Sender email */
    .message-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 15px;
        padding-top: 18px;
        margin-top: 18px;
        border-top: 1px solid #f0ebf5;
    }

    .email {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        min-width: 0;
        color: #837991;
        font-size: 12px;
        overflow-wrap: anywhere;
    }

    .email i {
        color: var(--purple);
    }

    .reply-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        padding: 12px 19px;
        border-radius: 12px;
        background: linear-gradient(135deg, #967be8, #7555cc);
        color: white;
        font-size: 12px;
        font-weight: 800;
        box-shadow: 0 7px 17px rgba(117, 85, 204, .18);
        transition: transform .25s ease, box-shadow .25s ease;
    }

    .reply-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 11px 23px rgba(117, 85, 204, .27);
    }

    /* Empty state */
    .empty {
        padding: 65px 25px;
        border: 1px solid var(--border);
        border-radius: 25px;
        background: rgba(255, 255, 255, .93);
        text-align: center;
        box-shadow: 0 12px 35px rgba(58, 42, 91, .05);
        animation: fadeUp .6s ease both;
    }

    .empty-icon {
        width: 100px;
        height: 100px;
        display: grid;
        place-items: center;
        margin: 0 auto 23px;
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
        max-width: 420px;
        margin: 0 auto 23px;
        color: var(--muted);
        font-size: 13px;
        line-height: 1.8;
    }

    .browse-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        padding: 13px 20px;
        border-radius: 13px;
        background: linear-gradient(135deg, #967be8, #7555cc);
        color: white;
        font-size: 12px;
        font-weight: 800;
        box-shadow: 0 8px 20px rgba(117, 85, 204, .19);
        transition: .25s ease;
    }

    .browse-btn:hover {
        transform: translateY(-2px);
    }

    /* Footer */
    footer {
        padding: 26px 7%;
        text-align: center;
        border-top: 1px solid #eee7f5;
        background: rgba(255, 255, 255, .70);
        color: #968da4;
        font-size: 12px;
    }

    footer strong {
        color: var(--purple-dark);
    }

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
    @media (max-width: 900px) {
        nav {
            padding: 16px 5%;
        }

        .container {
            width: 92%;
        }

        .hero {
            padding: 32px;
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

        .container {
            padding-top: 28px;
        }

        .hero {
            padding: 27px 22px;
            border-radius: 22px;
        }

        .hero h1 {
            font-size: 34px;
        }

        .hero p {
            font-size: 13px;
        }

        .hero-icon {
            width: 72px;
            height: 72px;
            border-radius: 22px;
            font-size: 29px;
        }

        .message-card {
            padding: 19px;
        }

        .message-header {
            flex-direction: column;
            gap: 13px;
        }

        .date {
            align-self: flex-start;
        }

        .message-bottom {
            align-items: stretch;
            flex-direction: column;
        }

        .reply-btn {
            width: 100%;
        }

        .empty h2 {
            font-size: 25px;
        }
    }

    @media (max-width: 420px) {
        .hero-icon {
            display: none;
        }

        .hero h1 {
            font-size: 31px;
        }

        .section-heading h2 {
            font-size: 16px;
        }

        .email {
            font-size: 11px;
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

        <a href="needs.php">
            <i class="fa-solid fa-magnifying-glass"></i> Browse Needs
        </a>

        <a href="post-need.php">
            <i class="fa-solid fa-plus"></i> Post a Need
        </a>

        <a href="my-needs.php">
            <i class="fa-solid fa-clipboard-list"></i> My Needs
        </a>

        <a href="messages.php" class="active">
            <i class="fa-regular fa-envelope"></i> Messages
        </a>

        <a href="logout.php" class="logout">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
        </a>
    </div>
</nav>

<main class="container">

    <section class="hero">
        <div class="hero-content">
            <div class="eyebrow">
                <i class="fa-regular fa-comments"></i>
                YOUR CONVERSATIONS
            </div>

            <h1>Your Messages, <span>All Here.</span></h1>

            <p>
                Stay connected with the Needify community.
                Read responses to your posted needs, check sender details,
                and continue the conversation when you need to.
            </p>
        </div>

        <div class="hero-icon">
            <i class="fa-regular fa-envelope-open"></i>
        </div>
    </section>

    <section class="section-heading">
        <div class="heading-left">
            <div class="heading-icon">
                <i class="fa-solid fa-inbox"></i>
            </div>

            <div>
                <h2>
                    Received Messages
                    <span class="count-badge">
                        <?php echo (int)$total_messages; ?>
                    </span>
                </h2>

                <p>Messages sent to you by other Needify users.</p>
            </div>
        </div>
    </section>

    <?php if ($total_messages > 0): ?>

        <section class="messages-list">

            <?php while ($message = $result->fetch_assoc()): ?>

                <?php
                    $sender_name = $message["sender_name"] ?? "User";

                    $first_letter = function_exists("mb_substr")
                        ? mb_substr($sender_name, 0, 1, "UTF-8")
                        : substr($sender_name, 0, 1);
                ?>

                <article class="message-card">

                    <div class="message-header">

                        <div class="sender-info">

                            <div class="avatar">
                                <?php echo e($first_letter); ?>
                            </div>

                            <div class="sender-details">

                                <div class="sender-name">
                                    <?php echo e($sender_name); ?>
                                </div>

                                <div class="sender-caption">
                                    <i class="fa-solid fa-user-check"></i>
                                    Needify community member
                                </div>

                            </div>
                        </div>

                        <div class="date">
                            <i class="fa-regular fa-clock"></i>

                            <?php
                                $timestamp = strtotime($message["created_at"]);

                                echo $timestamp !== false
                                    ? e(date("d M Y, h:i A", $timestamp))
                                    : e($message["created_at"]);
                            ?>
                        </div>

                    </div>

                    <div class="need-title">
                        <i class="fa-solid fa-thumbtack"></i>

                        <div>
                            <strong>Regarding your need:</strong>

                            <span>
                                <?php echo e($message["need_title"]); ?>
                            </span>
                        </div>
                    </div>

                    <div class="message-label">
                        <i class="fa-regular fa-message"></i>
                        MESSAGE
                    </div>

                    <div class="message-text">
                        <?php echo nl2br(e($message["message"])); ?>
                    </div>

                    <div class="message-bottom">

                        <div class="email">
                            <i class="fa-regular fa-envelope"></i>

                            <span>
                                <?php echo e($message["sender_email"]); ?>
                            </span>
                        </div>

                        <a
href="contact.php?reply_to=<?php echo (int)$message["id"]; ?>"                           
 class="reply-btn"
                        >
                            <i class="fa-solid fa-reply"></i>
                            Reply / Contact
                        </a>

                    </div>

                </article>

            <?php endwhile; ?>

        </section>

    <?php else: ?>

        <section class="empty">

            <div class="empty-icon">
                <i class="fa-regular fa-envelope-open"></i>
            </div>

            <h2>Your inbox is taking a little break!</h2>

            <p>
                You haven't received any messages yet.
                When someone responds to one of your needs, their message
                will appear here.
            </p>

            <a href="needs.php" class="browse-btn">
                <i class="fa-solid fa-magnifying-glass"></i>
                Explore Community Needs
            </a>

        </section>

    <?php endif; ?>

</main>

<footer>
    Made with <span style="color:#df7d9b;">♥</span> by <strong>Needify</strong>
    — Making your everyday needs easier.
</footer>

</body>
</html>
