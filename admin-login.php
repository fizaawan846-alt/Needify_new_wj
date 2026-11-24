
<?php
/*
|--------------------------------------------------------------------------
| NEEDIFY - SECURE ADMIN LOGIN
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set("display_errors", "0");
ini_set("log_errors", "1");

// CENTRAL SESSION CONFIGURATION
require_once __DIR__ . "/config/session.php";

// DATABASE CONNECTION
require_once __DIR__ . "/config/database.php";

// NO CACHE
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$error = "";

// TIMEOUT
$timeout_duration = 100;

// LOGIN ATTEMPTS
$max_attempts = 5;
$lockout_duration = 900;

// EXISTING ADMIN SESSION
if (isset($_SESSION["admin_id"])) {

    if (
        !isset($_SESSION["admin_last_activity"]) ||
        (time() - (int) $_SESSION["admin_last_activity"]) > $timeout_duration
    ) {
        unset(
            $_SESSION["admin_id"],
            $_SESSION["admin_username"],
            $_SESSION["admin_last_activity"]
        );

        needify_regenerate_session();

        $error = "Your session expired. Please login again.";

    } else {
        header("Location: admin-dashboard.php");
        exit;
    }
}

// LOGIN ATTEMPTS INITIALIZATION
if (!isset($_SESSION["admin_failed_attempts"])) {
    $_SESSION["admin_failed_attempts"] = 0;
}

if (!isset($_SESSION["admin_lockout_until"])) {
    $_SESSION["admin_lockout_until"] = 0;
}

// RESET LOCKOUT
if (
    $_SESSION["admin_lockout_until"] > 0 &&
    time() >= $_SESSION["admin_lockout_until"]
) {
    $_SESSION["admin_failed_attempts"] = 0;
    $_SESSION["admin_lockout_until"] = 0;
}

// CSRF TOKEN
if (empty($_SESSION["admin_login_csrf"])) {
    $_SESSION["admin_login_csrf"] = bin2hex(random_bytes(32));
}

// LOGIN PROCESS
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";
    $csrf_token = $_POST["csrf_token"] ?? "";

    if (!is_string($username)) {
        $username = "";
    } else {
        $username = trim($username);
    }

    if (
        !is_string($csrf_token) ||
        !isset($_SESSION["admin_login_csrf"]) ||
        !hash_equals($_SESSION["admin_login_csrf"], $csrf_token)
    ) {
        $error = "Invalid security request. Please refresh the page.";
        $_SESSION["admin_login_csrf"] = bin2hex(random_bytes(32));

    } elseif (time() < $_SESSION["admin_lockout_until"]) {

        $remaining = $_SESSION["admin_lockout_until"] - time();
        $minutes = (int) ceil($remaining / 60);

        $error = "Too many failed attempts. Try again after " . $minutes . " minute(s).";

    } elseif (
        $username === "" ||
        !is_string($password) ||
        $password === ""
    ) {
        $error = "Please enter username and password.";

    } elseif (strlen($username) > 50 || strlen($password) > 4096) {
        $error = "Invalid username or password.";

    } else {

        $stmt = $conn->prepare(
            "SELECT id, username, password
             FROM admins
             WHERE username = ?
             LIMIT 1"
        );

        if ($stmt) {

            $stmt->bind_param("s", $username);
            $stmt->execute();

            $result = $stmt->get_result();

            $admin = ($result && $result->num_rows === 1)
                ? $result->fetch_assoc()
                : null;

            if ($admin && password_verify($password, $admin["password"])) {

                // REGENERATE CENTRAL SESSION ID
                needify_regenerate_session();

                $_SESSION["admin_id"] = (int) $admin["id"];
                $_SESSION["admin_username"] = $admin["username"];
                $_SESSION["admin_last_activity"] = time();

                unset(
                    $_SESSION["admin_failed_attempts"],
                    $_SESSION["admin_lockout_until"],
                    $_SESSION["admin_login_csrf"]
                );

                $stmt->close();

                header("Location: admin-dashboard.php");
                exit;

            } else {

                $_SESSION["admin_failed_attempts"]++;

                $remaining_attempts = $max_attempts -
                    $_SESSION["admin_failed_attempts"];

                if ($_SESSION["admin_failed_attempts"] >= $max_attempts) {

                    $_SESSION["admin_lockout_until"] =
                        time() + $lockout_duration;

                    $error = "Too many failed attempts. Login blocked for 15 minutes.";

                } else {

                    $error = "Invalid username or password. " .
                        $remaining_attempts . " attempt(s) remaining.";
                }
            }

            $stmt->close();

        } else {
            $error = "Login could not be processed. Please try again.";
            error_log("Needify admin login error: " . $conn->error);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin Login | Needify</title>

<link rel="icon" type="image/png" href="favicon.png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --primary: #7956C8;
    --primary-dark: #6240AD;
    --pink: #FFE5EF;
    --cream: #FFFAF5;
    --white: #FFFFFF;
    --text: #302747;
    --muted: #81778F;
    --border: #E9E0F0;
    --input: #FFFFFF;
    --card: #FFFFFF;
    --shadow: 0 15px 45px rgba(48, 39, 71, 0.10);
}

body.dark-mode {
    --primary: #A98BEA;
    --primary-dark: #8C6CD0;
    --pink: #49354C;
    --cream: #211C2B;
    --white: #302A3C;
    --text: #F8F2FF;
    --muted: #C1B6D0;
    --border: #4B405B;
    --input: #393146;
    --card: #302A3C;
    --shadow: 0 15px 45px rgba(0,0,0,0.25);
}

body {
    font-family: 'Poppins', sans-serif;
    background:
        radial-gradient(circle at top left, rgba(255,229,239,0.95), transparent 38%),
        radial-gradient(circle at bottom right, rgba(121,86,200,0.12), transparent 38%),
        var(--cream);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 25px;
    transition: background 0.3s, color 0.3s;
}

.login-container {
    width: 100%;
    max-width: 445px;
}

.top-controls {
    display: flex;
    justify-content: flex-end;
    gap: 9px;
    margin-bottom: 15px;
}

.control-btn {
    border: 1px solid var(--border);
    background: var(--card);
    color: var(--text);
    padding: 8px 13px;
    border-radius: 10px;
    cursor: pointer;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    transition: 0.25s;
}

.control-btn:hover {
    background: var(--pink);
    border-color: var(--primary);
    color: var(--primary);
}

.login-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 40px;
    box-shadow: var(--shadow);
    position: relative;
    overflow: hidden;
}

.login-card::before {
    content: "";
    position: absolute;
    height: 6px;
    top: 0;
    left: 0;
    width: 100%;
    background: linear-gradient(90deg, #7956C8, #FFE5EF, #7956C8);
}

.logo {
    text-align: center;
    margin-bottom: 29px;
}

.logo a {
    color: var(--primary);
    text-decoration: none;
    font-size: 36px;
    font-weight: 800;
    letter-spacing: 1px;
}

.logo p {
    color: var(--muted);
    font-size: 12px;
    margin-top: 3px;
}

.logo-tag {
    display: inline-block;
    margin-top: 12px;
    padding: 6px 15px;
    background: var(--pink);
    color: var(--primary);
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
}

h2 {
    text-align: center;
    font-size: 24px;
    color: var(--text);
    margin-bottom: 7px;
}

.subtitle {
    text-align: center;
    color: var(--muted);
    font-size: 12px;
    margin-bottom: 28px;
}

.form-group {
    margin-bottom: 19px;
}

label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 8px;
}

input {
    width: 100%;
    padding: 14px;
    background: var(--input);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 11px;
    outline: none;
    font-family: inherit;
    font-size: 13px;
    transition: 0.25s;
}

input::placeholder {
    color: var(--muted);
    opacity: 0.8;
}

input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(121,86,200,0.12);
}

.password-box {
    position: relative;
}

.password-box input {
    padding-right: 65px;
}

.show-password {
    position: absolute;
    right: 13px;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    color: var(--primary);
    cursor: pointer;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
}

.login-btn {
    width: 100%;
    padding: 15px;
    margin-top: 5px;
    background: linear-gradient(135deg, #7956C8, #9474D7);
    color: white;
    border: none;
    border-radius: 12px;
    font-family: inherit;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 7px 18px rgba(121,86,200,0.25);
    transition: 0.25s;
}

.login-btn:hover {
    background: linear-gradient(135deg, #6240AD, #7956C8);
    transform: translateY(-2px);
    box-shadow: 0 10px 22px rgba(121,86,200,0.32);
}

.message {
    padding: 12px 14px;
    border-radius: 10px;
    margin-bottom: 18px;
    font-size: 12px;
    text-align: center;
}

.error {
    background: #FFE5E5;
    color: #B42318;
    border: 1px solid #F6CACA;
}

.back-home {
    display: block;
    text-align: center;
    margin-top: 22px;
    text-decoration: none;
    color: var(--primary);
    font-size: 12px;
    font-weight: 600;
    transition: 0.2s;
}

.back-home:hover {
    color: var(--primary-dark);
}

.security-note {
    text-align: center;
    color: var(--muted);
    font-size: 11px;
    margin-top: 23px;
}

footer {
    text-align: center;
    color: var(--muted);
    font-size: 11px;
    margin-top: 20px;
}

@media(max-width: 480px) {
    body {
        padding: 16px;
    }

    .login-card {
        padding: 32px 23px;
    }

    .logo a {
        font-size: 31px;
    }

    h2 {
        font-size: 21px;
    }
}
</style>
</head>

<body>

<div class="login-container">

    <div class="top-controls">
        <button class="control-btn" type="button" onclick="changeFont(-1)">A-</button>
        <button class="control-btn" type="button" onclick="changeFont(1)">A+</button>
        <button class="control-btn" type="button" onclick="toggleTheme()">◐ Theme</button>
    </div>

    <div class="login-card">

        <div class="logo">
            <a href="index.php">NEEDIFY</a>
            <p>Smart Shopping Made Simple.</p>
            <span class="logo-tag">ADMIN CONTROL PANEL</span>
        </div>

        <h2>Welcome Back!</h2>
        <p class="subtitle">Login to manage your Needify website</p>

        <?php if ($error !== ""): ?>
            <div class="message error">
                <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="admin-login.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION["admin_login_csrf"], ENT_QUOTES, "UTF-8") ?>"
            >

            <div class="form-group">
                <label for="username">Admin Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter your admin username"
                    maxlength="50"
                    autocomplete="username"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>

                <div class="password-box">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        maxlength="4096"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="show-password"
                        onclick="togglePassword()"
                    >Show</button>
                </div>
            </div>

            <button type="submit" class="login-btn">
                Login to Dashboard →
            </button>

        </form>

        <a href="index.php" class="back-home">← Back to Home</a>

        <p class="security-note">
            🔒 Secure Admin Authentication
        </p>

    </div>

    <footer>
        © <?= date("Y") ?> Needify. All Rights Reserved.
    </footer>

</div>

<script>
function togglePassword() {
    const password = document.getElementById("password");
    const button = document.querySelector(".show-password");

    if (password.type === "password") {
        password.type = "text";
        button.textContent = "Hide";
    } else {
        password.type = "password";
        button.textContent = "Show";
    }
}

function toggleTheme() {
    document.body.classList.toggle("dark-mode");

    localStorage.setItem(
        "needifyTheme",
        document.body.classList.contains("dark-mode") ? "dark" : "light"
    );
}

function changeFont(amount) {
    let current = parseInt(localStorage.getItem("adminLoginFont") || "0");

    current = Math.max(-2, Math.min(3, current + amount));

    localStorage.setItem("adminLoginFont", current);

    document.body.style.zoom = (100 + current * 10) + "%";
}

document.addEventListener("DOMContentLoaded", function() {

    if (localStorage.getItem("needifyTheme") === "dark") {
        document.body.classList.add("dark-mode");
    }

    const fontLevel = parseInt(
        localStorage.getItem("adminLoginFont") || "0"
    );

    document.body.style.zoom = (100 + fontLevel * 10) + "%";

});
</script>

</body>
</html>
