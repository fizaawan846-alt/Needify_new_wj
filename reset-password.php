
<?php

/*
|--------------------------------------------------------------------------
| Secure Session Configuration
|--------------------------------------------------------------------------
*/

ini_set("session.use_strict_mode", "1");

session_set_cookie_params([
    "lifetime" => 0,
    "path" => "/",
    "domain" => "",
    "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
    "httponly" => true,
    "samesite" => "Lax"
]);

session_start();

require_once "config/database.php";

/*
|--------------------------------------------------------------------------
| Security Headers
|--------------------------------------------------------------------------
*/

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

$error = "";
$success = "";

/*
|--------------------------------------------------------------------------
| HTML Escape Helper
|--------------------------------------------------------------------------
*/

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

/*
|--------------------------------------------------------------------------
| Get Email From Password Reset Session
|--------------------------------------------------------------------------
*/

$email = $_SESSION["reset_email"] ?? "";

/*
|--------------------------------------------------------------------------
| If No Reset Email Exists, Go Back to Forgot Password
|--------------------------------------------------------------------------
*/

if (!is_string($email) || $email === "") {
    header("Location: forgot-password.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| Reset Password
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrf_token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrf_token) ||
        !hash_equals($_SESSION["csrf_token"], $csrf_token)
    ) {

        $error = "Invalid request. Please refresh the page and try again.";

    } else {

        $new_password = $_POST["new_password"] ?? "";
        $confirm_password = $_POST["confirm_password"] ?? "";

        if (!is_string($new_password) || $new_password === "") {

            $error = "Please enter a new password.";

        } elseif (strlen($new_password) < 8) {

            $error = "Password must be at least 8 characters long.";

        } elseif (!preg_match("/[A-Z]/", $new_password)) {

            $error = "Password must contain at least one uppercase letter.";

        } elseif (!preg_match("/[a-z]/", $new_password)) {

            $error = "Password must contain at least one lowercase letter.";

        } elseif (!preg_match("/[0-9]/", $new_password)) {

            $error = "Password must contain at least one number.";

        } elseif (!preg_match("/[^A-Za-z0-9]/", $new_password)) {

            $error = "Password must contain at least one special character.";

        } elseif ($new_password !== $confirm_password) {

            $error = "Passwords do not match.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Hash New Password
            |--------------------------------------------------------------------------
            */

            $hashed_password = password_hash(
                $new_password,
                PASSWORD_DEFAULT
            );

            if ($hashed_password === false) {

                $error = "Unable to process your password. Please try again.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | Update Password Using Prepared Statement
                |--------------------------------------------------------------------------
                */

                $stmt = $conn->prepare(
                    "UPDATE users
                     SET password = ?
                     WHERE email = ?"
                );

                if (!$stmt) {

                    $error = "Unable to reset password. Please try again.";

                } else {

                    $stmt->bind_param(
                        "ss",
                        $hashed_password,
                        $email
                    );

                    if ($stmt->execute()) {

                        if ($stmt->affected_rows > 0) {

                            /*
                            |--------------------------------------------------------------------------
                            | Clear Reset Session After Success
                            |--------------------------------------------------------------------------
                            */

                            unset($_SESSION["reset_email"]);
                            unset($_SESSION["csrf_token"]);

                            session_regenerate_id(true);

                            $success = "Password reset successfully!";

                        } else {

                            $error = "Unable to reset password. Please try again.";

                        }

                    } else {

                        $error = "Unable to reset password. Please try again.";

                    }

                    $stmt->close();
                }
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

    <title>Needify | Reset Password</title>

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
            margin-bottom: 18px;
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

        .password-box {
            position: relative;
        }

        .password-box input {
            padding-right: 48px;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 18px;
        }

        .password-hint {
            font-size: 12px;
            color: #777;
            margin-top: 6px;
            line-height: 1.4;
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

        body.dark-mode .auth-subtitle,
        body.dark-mode .password-hint {
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

        body.dark-mode .toggle-password {
            color: #d7caff;
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


    <div class="auth-container">

        <div class="auth-box">

            <h2>Reset Password</h2>

            <p class="auth-subtitle">
                Create a new secure password for your Needify account.
            </p>

            <?php if ($error !== ""): ?>

                <div class="error-message">
                    <?php echo e($error); ?>
                </div>

            <?php endif; ?>


            <?php if ($success !== ""): ?>

                <div class="success-message">
                    <?php echo e($success); ?>
                </div>

                <div class="back-login">

                    <a href="login.php">
                        Go to Login →
                    </a>

                </div>

            <?php else: ?>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo e($_SESSION["csrf_token"]); ?>"
                    >

                    <div class="form-group">

                        <label for="email">
                            Email Address
                        </label>

                        <input
                            type="email"
                            id="email"
                            value="<?php echo e($email); ?>"
                            readonly
                        >

                    </div>


                    <div class="form-group">

                        <label for="new_password">
                            New Password
                        </label>

                        <div class="password-box">

                            <input
                                type="password"
                                name="new_password"
                                id="new_password"
                                placeholder="Enter new password"
                                required
                            >

                            <button
                                type="button"
                                class="toggle-password"
                                onclick="togglePassword('new_password', this)"
                            >
                                👁️
                            </button>

                        </div>

                        <div class="password-hint">
                            Minimum 8 characters with uppercase,
                            lowercase, number and special character.
                        </div>

                    </div>


                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm Password
                        </label>

                        <div class="password-box">

                            <input
                                type="password"
                                name="confirm_password"
                                id="confirm_password"
                                placeholder="Confirm new password"
                                required
                            >

                            <button
                                type="button"
                                class="toggle-password"
                                onclick="togglePassword('confirm_password', this)"
                            >
                                👁️
                            </button>

                        </div>

                    </div>


                    <button type="submit" class="auth-button">
                        Reset Password
                    </button>

                </form>

                <div class="back-login">

                    <a href="login.php">
                        ← Back to Login
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- Page Up / Down -->

    <div class="page-controls">

        <button type="button" id="pageUp" aria-label="Page Up" title="Page Up">
            <i class="fa-solid fa-arrow-up"></i>
        </button>

        <button type="button" id="pageDown" aria-label="Page Down" title="Page Down">
            <i class="fa-solid fa-arrow-down"></i>
        </button>

    </div>


    <!-- Password Visibility Script -->

    <script>

        function togglePassword(inputId, button) {

            const input = document.getElementById(inputId);

            if (input.type === "password") {

                input.type = "text";
                button.textContent = "🙈";

            } else {

                input.type = "password";
                button.textContent = "👁️";

            }

        }

    </script>


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
