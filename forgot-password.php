
<?php
session_start();
require_once "config/database.php";

// Security headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");

$message = "";
$error = "";
$email = "";

// Generate CSRF token
if (empty($_SESSION["forgot_password_csrf"])) {
    $_SESSION["forgot_password_csrf"] = bin2hex(random_bytes(32));
}

// Process form
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");

    // Verify CSRF token
    if (
        !isset($_POST["csrf_token"], $_SESSION["forgot_password_csrf"]) ||
        !hash_equals(
            $_SESSION["forgot_password_csrf"],
            $_POST["csrf_token"]
        )
    ) {
        $error = "Invalid request. Please refresh the page and try again.";

    } elseif ($email === "") {
        $error = "Please enter your email address.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";

    } else {

        $stmt = $conn->prepare(
            "SELECT id FROM users WHERE email = ? LIMIT 1"
        );

        if (!$stmt) {
            $error = "Something went wrong. Please try again.";
        } else {

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {

                $_SESSION["reset_email"] = $email;

                // Rotate CSRF token
                $_SESSION["forgot_password_csrf"] =
                    bin2hex(random_bytes(32));

                $stmt->close();

                header("Location: reset-password.php");
                exit();

            } else {
                $error = "No account found with this email address.";
            }

            $stmt->close();
        }
    }
}

// Escape output
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Needify | Forgot Password</title>

    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7ff, #eef2ff);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #222;
            transition: background .3s, color .3s;
        }

        .auth-container {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 90px 20px 30px;
            box-sizing: border-box;
        }

        .auth-box {
            width: 100%;
            max-width: 430px;
            background: #ffffff;
            padding: 40px;
            border-radius: 18px;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.10);
            box-sizing: border-box;
            transition: background .3s;
        }

        .auth-box h2 {
            text-align: center;
            margin: 0 0 10px;
            font-size: 28px;
            color: #222;
        }

        .auth-subtitle {
            text-align: center;
            color: #777;
            font-size: 14px;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #ddd;
            border-radius: 9px;
            font-size: 15px;
            box-sizing: border-box;
            outline: none;
        }

        .form-group input:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.10);
        }

        .auth-button {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 9px;
            background: #4f46e5;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .auth-button:hover {
            background: #3730a3;
        }

        .error-message {
            background: #ffe8e8;
            color: #c62828;
            padding: 11px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .success-message {
            background: #e8f8ed;
            color: #218838;
            padding: 11px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .back-login {
            text-align: center;
            margin-top: 22px;
        }

        .back-login a {
            color: #4f46e5;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .back-login a:hover {
            text-decoration: underline;
        }

        /* Display Settings */
        .site-tools {
            position: fixed;
            top: 18px;
            right: 20px;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .site-tools button {
            width: 36px;
            height: 36px;
            border: 1px solid #ded8ed;
            border-radius: 10px;
            background: #eee9ff;
            color: #51418b;
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
            background: linear-gradient(135deg, #6548bd, #a48ae7);
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
            background: linear-gradient(135deg, #171421, #211c2d);
            color: #eee8f7;
        }

        body.dark-mode .auth-box {
            background: #242031;
            box-shadow: 0 10px 35px rgba(0, 0, 0, .3);
        }

        body.dark-mode .auth-box h2 {
            color: #eee8f7;
        }

        body.dark-mode .auth-subtitle {
            color: #b8adc9;
        }

        body.dark-mode .form-group label {
            color: #ddd3e9;
        }

        body.dark-mode .form-group input {
            background: #1b1726;
            color: #eee8f7;
            border-color: #493d5c;
        }

        body.dark-mode .form-group input::placeholder {
            color: #8f829f;
        }

        body.dark-mode .form-group input:focus {
            background: #211c2d;
            border-color: #9b83e8;
        }

        body.dark-mode .back-login a {
            color: #b9a5ff;
        }

        body.dark-mode .site-tools button {
            background: #393047;
            border-color: #514565;
            color: #eee8f7;
        }

        body.dark-mode .error-message {
            background: #3b202b;
            color: #ffb4c2;
        }

        body.dark-mode .success-message {
            background: #193529;
            color: #9de0b9;
        }

        @media (max-width: 500px) {
            .auth-box {
                padding: 30px 22px;
            }

            .auth-box h2 {
                font-size: 24px;
            }

            .site-tools {
                right: 12px;
                top: 12px;
            }

            .page-controls {
                right: 12px;
                bottom: 12px;
            }
        }
    </style>
</head>

<body>

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

    <div class="auth-container">

        <div class="auth-box">

            <h2>Forgot Password?</h2>

            <p class="auth-subtitle">
                Enter your email address to reset your Needify password.
            </p>

            <?php if ($error !== ""): ?>
                <div class="error-message" role="alert">
                    <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($message !== ""): ?>
                <div class="success-message" role="status">
                    <?php echo e($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="forgot-password.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo e($_SESSION["forgot_password_csrf"]); ?>"
                >

                <div class="form-group">

                    <label for="email">Email Address</label>

                    <input
                        type="email"
                        name="email"
                        id="email"
                        placeholder="Enter your email"
                        value="<?php echo e($email); ?>"
                        maxlength="150"
                        autocomplete="email"
                        required
                    >

                </div>

                <button type="submit" class="auth-button">
                    Continue
                </button>

            </form>

            <div class="back-login">
                <a href="login.php">← Back to Login</a>
            </div>

        </div>

    </div>

    <!-- Page Up / Down -->
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

    <!-- Common Display Settings Script -->
    <script>
    (function () {

        const body = document.body;

        const themeToggle = document.getElementById("themeToggle");
        const fontDecrease = document.getElementById("fontDecrease");
        const fontReset = document.getElementById("fontReset");
        const fontIncrease = document.getElementById("fontIncrease");

        const pageUp = document.getElementById("pageUp");
        const pageDown = document.getElementById("pageDown");

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

        pageUp?.addEventListener("click", function () {

            window.scrollTo({
                top: 0,
                behavior: "smooth"
            });

        });

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
