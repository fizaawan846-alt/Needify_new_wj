
<?php
/*
|--------------------------------------------------------------------------
| NEEDIFY - ADMIN REGISTRATION
|--------------------------------------------------------------------------
*/

ini_set("log_errors", "1");
error_reporting(E_ALL);

// CENTRAL SESSION CONFIGURATION
require_once __DIR__ . "/config/session.php";
require_once __DIR__ . "/config/database.php";

// NO CACHE
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// ADMIN LOGIN CHECK
if (!isset($_SESSION["admin_id"])) {
    header("Location: admin-login.php");
    exit;
}

// 100 SECONDS INACTIVITY TIMEOUT
$inactive_limit = 100;

if (
    !isset($_SESSION["admin_last_activity"]) ||
    (time() - (int) $_SESSION["admin_last_activity"]) >= $inactive_limit
) {
    unset(
        $_SESSION["admin_id"],
        $_SESSION["admin_username"],
        $_SESSION["admin_last_activity"]
    );

    needify_regenerate_session();

    header("Location: admin-login.php?timeout=1");
    exit;
}

$_SESSION["admin_last_activity"] = time();

$error = "";
$success = "";
$total_admins = 0;

// CSRF TOKEN
if (
    !isset($_SESSION["admin_register_csrf"]) ||
    !is_string($_SESSION["admin_register_csrf"])
) {
    $_SESSION["admin_register_csrf"] = bin2hex(random_bytes(32));
}

// DATABASE OPERATIONS
try {

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $token = $_POST["csrf_token"] ?? "";

        if (
            !is_string($token) ||
            !hash_equals($_SESSION["admin_register_csrf"], $token)
        ) {

            $error = "Invalid request. Please refresh the page.";

            $_SESSION["admin_register_csrf"] =
                bin2hex(random_bytes(32));

        } else {

            $username = $_POST["username"] ?? "";
            $password = $_POST["password"] ?? "";
            $confirm_password = $_POST["confirm_password"] ?? "";

            if (
                !is_string($username) ||
                !is_string($password) ||
                !is_string($confirm_password)
            ) {

                $error = "Invalid form data.";

            } else {

                $username = trim($username);

                if (
                    $username === "" ||
                    $password === "" ||
                    $confirm_password === ""
                ) {

                    $error = "Please fill in all fields.";

                } elseif (
                    strlen($username) < 3 ||
                    strlen($username) > 50
                ) {

                    $error = "Username must be between 3 and 50 characters.";

                } elseif (
                    !preg_match('/^[a-zA-Z0-9_.-]+$/', $username)
                ) {

                    $error = "Username can contain letters, numbers, dots, underscores and hyphens only.";

                } elseif (strlen($password) < 8) {

                    $error = "Password must contain at least 8 characters.";

                } elseif (strlen($password) > 4096) {

                    $error = "Password is too long.";

                } elseif ($password !== $confirm_password) {

                    $error = "Passwords do not match.";

                } else {

                    // DATABASE REGISTRATION LOCK
                    $lock_result = $conn->query(
                        "SELECT GET_LOCK('needify_admin_registration', 10) AS lock_status"
                    );

                    if (!$lock_result) {
                        throw new Exception(
                            "Unable to acquire registration lock."
                        );
                    }

                    $lock_data = $lock_result->fetch_assoc();

                    if (
                        !$lock_data ||
                        (int) $lock_data["lock_status"] !== 1
                    ) {

                        $error = "Registration is busy. Please try again.";

                    } else {

                        try {

                            // CHECK ADMIN COUNT
                            $count_result = $conn->query(
                                "SELECT COUNT(*) AS total_admins FROM admins"
                            );

                            if (!$count_result) {
                                throw new Exception(
                                    "Unable to count admins."
                                );
                            }

                            $count_data = $count_result->fetch_assoc();

                            $total_admins =
                                (int) $count_data["total_admins"];

                            if ($total_admins >= 5) {

                                $error =
                                    "Maximum limit of 5 admins has been reached.";

                            } else {

                                // CHECK DUPLICATE USERNAME
                                $check = $conn->prepare(
                                    "SELECT id FROM admins
                                     WHERE username = ?
                                     LIMIT 1"
                                );

                                if (!$check) {
                                    throw new Exception(
                                        "Username check failed."
                                    );
                                }

                                $check->bind_param("s", $username);
                                $check->execute();

                                $duplicate_result = $check->get_result();
                                $duplicate =
                                    $duplicate_result->num_rows > 0;

                                $check->close();

                                if ($duplicate) {

                                    $error =
                                        "This admin username already exists.";

                                } else {

                                    // PASSWORD HASHING
                                    $hashed_password = password_hash(
                                        $password,
                                        PASSWORD_DEFAULT
                                    );

                                    if ($hashed_password === false) {
                                        throw new Exception(
                                            "Password hashing failed."
                                        );
                                    }

                                    // INSERT ADMIN
                                    $stmt = $conn->prepare(
                                        "INSERT INTO admins (username, password)
                                         VALUES (?, ?)"
                                    );

                                    if (!$stmt) {
                                        throw new Exception(
                                            "Admin registration query failed."
                                        );
                                    }

                                    $stmt->bind_param(
                                        "ss",
                                        $username,
                                        $hashed_password
                                    );

                                    $stmt->execute();
                                    $stmt->close();

                                    $success =
                                        "New admin registered successfully!";

                                    $_POST = [];

                                    // REFRESH CSRF TOKEN
                                    $_SESSION["admin_register_csrf"] =
                                        bin2hex(random_bytes(32));
                                }
                            }

                        } finally {

                            // RELEASE DATABASE LOCK
                            $release_result = $conn->query(
                                "SELECT RELEASE_LOCK('needify_admin_registration')"
                            );

                            if (!$release_result) {
                                error_log(
                                    "Unable to release admin registration lock."
                                );
                            }
                        }
                    }
                }
            }
        }
    }

    // CURRENT ADMIN COUNT
    $count_result = $conn->query(
        "SELECT COUNT(*) AS total_admins FROM admins"
    );

    if (!$count_result) {
        throw new Exception("Unable to load admin count.");
    }

    $count_data = $count_result->fetch_assoc();

    $total_admins = (int) $count_data["total_admins"];

} catch (Throwable $e) {

    error_log(
        "Needify Admin Registration Error: " .
        $e->getMessage()
    );

    $error = "Something went wrong. Please try again.";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Needify | Admin Registration</title>

<link rel="icon" type="image/png" href="favicon.png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
rel="stylesheet">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {
    --primary: #7956C8;
    --primary-dark: #6240AD;
    --pink: #FFE5EF;
    --cream: #FFFAF5;
    --white: #FFFFFF;
    --text: #302747;
    --muted: #81798F;
    --border: #E9E0F0;
    --card: #FFFFFF;
    --input: #FFFCFF;
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
    --card: #302A3C;
    --input: #393146;
    --shadow: 0 15px 45px rgba(0,0,0,0.25);
}

body {
    font-family: 'Poppins', sans-serif;
    background: var(--cream);
    color: var(--text);
    min-height: 100vh;
    transition: background 0.3s, color 0.3s;
}

.admin-page {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 100px 20px;
    background:
        radial-gradient(circle at top left, rgba(255,229,239,0.95), transparent 38%),
        radial-gradient(circle at bottom right, rgba(121,86,200,0.13), transparent 38%),
        var(--cream);
}

.admin-card {
    width: 100%;
    max-width: 475px;
    background: var(--card);
    padding: 40px;
    border-radius: 25px;
    box-shadow: var(--shadow);
    text-align: center;
    border: 1px solid var(--border);
    position: relative;
    overflow: hidden;
}

.admin-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    height: 6px;
    width: 100%;
    background: linear-gradient(90deg, #7956C8, #FFE5EF, #7956C8);
}

.brand-name {
    font-size: 30px;
    font-weight: 800;
    color: var(--primary);
    margin-bottom: 5px;
    letter-spacing: 1px;
}

.brand-tagline {
    color: var(--muted);
    font-size: 11px;
    margin-bottom: 24px;
}

.admin-icon {
    width: 76px;
    height: 76px;
    margin: 0 auto 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--pink);
    color: var(--primary);
    border-radius: 23px;
    font-size: 31px;
}

h1 {
    font-size: 26px;
    color: var(--text);
}

.subtitle {
    margin: 9px 0 25px;
    color: var(--muted);
    font-size: 12px;
}

.admin-count {
    background: var(--pink);
    color: var(--primary-dark);
    padding: 13px;
    border-radius: 11px;
    margin-bottom: 23px;
    font-size: 13px;
    font-weight: 600;
}

.form-group {
    text-align: left;
    margin-bottom: 19px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
}

.form-group input {
    width: 100%;
    height: 51px;
    padding: 0 14px;
    border: 1px solid var(--border);
    border-radius: 11px;
    font-family: inherit;
    font-size: 13px;
    color: var(--text);
    background: var(--input);
    outline: none;
    transition: 0.25s;
}

.form-group input::placeholder {
    color: var(--muted);
    opacity: 0.8;
}

.form-group input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(121,86,200,0.12);
}

.password-box {
    position: relative;
}

.password-box input {
    padding-right: 50px;
}

.eye-btn {
    position: absolute;
    right: 7px;
    top: 50%;
    transform: translateY(-50%);
    width: 36px;
    height: 36px;
    border: none;
    background: transparent;
    color: var(--primary);
    cursor: pointer;
    font-size: 16px;
}

.register-btn {
    width: 100%;
    height: 52px;
    border: none;
    border-radius: 12px;
    background: linear-gradient(135deg, #7956C8, #9474D7);
    color: white;
    font-family: inherit;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    margin-top: 5px;
    box-shadow: 0 7px 18px rgba(121,86,200,0.25);
    transition: 0.25s;
}

.register-btn:hover {
    background: linear-gradient(135deg, #6240AD, #7956C8);
    transform: translateY(-2px);
    box-shadow: 0 10px 22px rgba(121,86,200,0.30);
}

.error,
.success {
    padding: 13px;
    border-radius: 10px;
    margin-bottom: 18px;
    font-size: 12px;
}

.error {
    background: #FFF0F0;
    border: 1px solid #FFD5D5;
    color: #B42335;
}

.success {
    background: #E9F9EF;
    border: 1px solid #B8E8C7;
    color: #18723A;
}

.back-link {
    display: inline-block;
    margin-top: 23px;
    color: var(--primary);
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.back-link:hover {
    color: var(--primary-dark);
}

.footer {
    margin-top: 22px;
    padding-top: 17px;
    border-top: 1px solid var(--border);
    color: var(--muted);
    font-size: 11px;
}

.site-tools {
    position: fixed;
    top: 15px;
    right: 15px;
    z-index: 9999;
    display: flex;
    gap: 7px;
    padding: 8px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: var(--shadow);
}

.site-tools button,
.page-controls button {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 9px;
    background: var(--primary);
    color: white;
    cursor: pointer;
    transition: 0.2s;
}

.site-tools button:hover,
.page-controls button:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

.page-controls {
    position: fixed;
    right: 18px;
    bottom: 20px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

body.dark-mode .admin-page {
    background:
        radial-gradient(circle at top left, #39243F 0%, transparent 38%),
        radial-gradient(circle at bottom right, #292044 0%, transparent 38%),
        var(--cream);
}

body.dark-mode .admin-count {
    color: #E9D8FF;
}

@media (max-width: 500px) {

    .admin-page {
        padding: 95px 15px 40px;
    }

    .admin-card {
        padding: 33px 22px;
    }

    .site-tools {
        top: 10px;
        right: 10px;
        gap: 4px;
        padding: 6px;
    }

    .site-tools button {
        width: 32px;
        height: 32px;
    }

    h1 {
        font-size: 23px;
    }
}

</style>

</head>

<body>

<div class="site-tools">

    <button type="button" id="fontDecrease" title="Decrease font">
        <i class="fa-solid fa-minus"></i>
    </button>

    <button type="button" id="fontReset" title="Reset font">
        <i class="fa-solid fa-rotate-left"></i>
    </button>

    <button type="button" id="fontIncrease" title="Increase font">
        <i class="fa-solid fa-plus"></i>
    </button>

    <button type="button" id="themeToggle" title="Change theme">
        <i class="fa-solid fa-moon"></i>
    </button>

</div>

<div class="page-controls">

    <button type="button" id="pageUp" title="Page Up">
        <i class="fa-solid fa-arrow-up"></i>
    </button>

    <button type="button" id="pageDown" title="Page Down">
        <i class="fa-solid fa-arrow-down"></i>
    </button>

</div>

<div class="admin-page">

    <div class="admin-card">

        <div class="brand-name">Needify.</div>

        <div class="brand-tagline">
            Smart Shopping Made Simple.
        </div>

        <div class="admin-icon">
            <i class="fa-solid fa-user-shield"></i>
        </div>

        <h1>Register Admin</h1>

        <p class="subtitle">
            Create a new administrator account.
        </p>

        <div class="admin-count">
            <i class="fa-solid fa-users-gear"></i>
            Admin Accounts:
            <?php echo (int) $total_admins; ?> / 5
        </div>

        <?php if ($error !== ""): ?>

            <div class="error">
                <?php
                echo htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>
            </div>

        <?php endif; ?>

        <?php if ($success !== ""): ?>

            <div class="success">
                <?php
                echo htmlspecialchars(
                    $success,
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>
            </div>

        <?php endif; ?>

        <?php if ($total_admins < 5): ?>

            <form method="POST" action="">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php
                    echo htmlspecialchars(
                        $_SESSION["admin_register_csrf"],
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>"
                >

                <div class="form-group">

                    <label for="username">Admin Username</label>

                    <input
                        type="text"
                        name="username"
                        id="username"
                        maxlength="50"
                        minlength="3"
                        placeholder="Enter admin username"
                        autocomplete="off"
                        pattern="[a-zA-Z0-9_.-]+"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="password">Password</label>

                    <div class="password-box">

                        <input
                            type="password"
                            name="password"
                            id="password"
                            minlength="8"
                            maxlength="4096"
                            placeholder="Enter password"
                            autocomplete="new-password"
                            required
                        >

                        <button
                            type="button"
                            class="eye-btn"
                            onclick="togglePassword('password', this)"
                            aria-label="Toggle password visibility"
                        >
                            <i class="fa-solid fa-eye"></i>
                        </button>

                    </div>

                </div>

                <div class="form-group">

                    <label for="confirm_password">Confirm Password</label>

                    <div class="password-box">

                        <input
                            type="password"
                            name="confirm_password"
                            id="confirm_password"
                            minlength="8"
                            maxlength="4096"
                            placeholder="Confirm password"
                            autocomplete="new-password"
                            required
                        >

                        <button
                            type="button"
                            class="eye-btn"
                            onclick="togglePassword('confirm_password', this)"
                            aria-label="Toggle password visibility"
                        >
                            <i class="fa-solid fa-eye"></i>
                        </button>

                    </div>

                </div>

                <button type="submit" class="register-btn">
                    Register New Admin →
                </button>

            </form>

        <?php else: ?>

            <div class="error">
                Maximum 5 admin accounts already exist.
                Remove an existing admin before registering another.
            </div>

        <?php endif; ?>

        <a href="admin-management.php" class="back-link">
            ← Back to Admin Management
        </a>

        <div class="footer">
            © <?php echo date("Y"); ?> Needify. All Rights Reserved.
        </div>

    </div>

</div>

<script>

// PASSWORD VISIBILITY
function togglePassword(id, button) {

    const input = document.getElementById(id);
    const icon = button.querySelector("i");

    if (input.type === "password") {

        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");

    } else {

        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");

    }
}

// DARK / LIGHT MODE
const body = document.body;
const themeToggle = document.getElementById("themeToggle");

function applyTheme(theme) {

    body.classList.toggle("dark-mode", theme === "dark");

    themeToggle.innerHTML = theme === "dark"
        ? '<i class="fa-solid fa-sun"></i>'
        : '<i class="fa-solid fa-moon"></i>';
}

applyTheme(localStorage.getItem("needifyTheme") || "light");

themeToggle.addEventListener("click", function() {

    const nextTheme = body.classList.contains("dark-mode")
        ? "light"
        : "dark";

    localStorage.setItem("needifyTheme", nextTheme);
    applyTheme(nextTheme);

});

// FONT SIZE CONTROLS
let fontLevel = Number(
    localStorage.getItem("needifyFontLevel") || 0
);

if (!Number.isFinite(fontLevel)) {
    fontLevel = 0;
}

fontLevel = Math.max(-2, Math.min(3, fontLevel));

function applyFontLevel() {

    body.style.zoom = String(1 + fontLevel * 0.05);

    localStorage.setItem(
        "needifyFontLevel",
        String(fontLevel)
    );
}

document.getElementById("fontDecrease").addEventListener("click", function() {
    fontLevel = Math.max(-2, fontLevel - 1);
    applyFontLevel();
});

document.getElementById("fontIncrease").addEventListener("click", function() {
    fontLevel = Math.min(3, fontLevel + 1);
    applyFontLevel();
});

document.getElementById("fontReset").addEventListener("click", function() {
    fontLevel = 0;
    applyFontLevel();
});

// PAGE UP
document.getElementById("pageUp").addEventListener("click", function() {

    window.scrollTo({
        top: 0,
        behavior: "smooth"
    });

});

// PAGE DOWN
document.getElementById("pageDown").addEventListener("click", function() {

    window.scrollBy({
        top: window.innerHeight * 0.8,
        behavior: "smooth"
    });

});

// KEYBOARD PAGE CONTROLS
document.addEventListener("keydown", function(event) {

    const tag = event.target.tagName;

    if (
        tag === "INPUT" ||
        tag === "TEXTAREA" ||
        tag === "SELECT" ||
        event.target.isContentEditable
    ) {
        return;
    }

    if (event.key === "PageUp") {

        event.preventDefault();

        window.scrollBy({
            top: -window.innerHeight,
            behavior: "smooth"
        });
    }

    if (event.key === "PageDown") {

        event.preventDefault();

        window.scrollBy({
            top: window.innerHeight,
            behavior: "smooth"
        });
    }

});

applyFontLevel();

</script>

</body>
</html>
