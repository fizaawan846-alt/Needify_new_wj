
<?php
/**
 * NEEDIFY - USER LOGIN
 * Strict rate limiting (5 attempts / 15-minute lockout),
 * CSRF protection, session ID regeneration, Google & Facebook OAuth.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/google.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit();
}

$pageTitle = "Welcome Back - Log In";
$pageDescription = "Log in to your Needify account to view your orders, campus needs, messages, and saved products.";

$message = "";
$messageType = "";

// Check for redirect notifications
if (isset($_GET['timeout'])) {
    $message = "Your session expired due to inactivity. Please log in again.";
    $messageType = "info";
} elseif (isset($_GET['logged_out'])) {
    $message = "You have been logged out successfully.";
    $messageType = "success";
} elseif (isset($_GET['registered'])) {
    $message = "Account created successfully! Please log in with your credentials.";
    $messageType = "success";
}

// Check Facebook session error if any
if (!empty($_SESSION["facebook_login_error"])) {
    $message = $_SESSION["facebook_login_error"];
    $messageType = "error";
    unset($_SESSION["facebook_login_error"]);
}

$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // 1. Verify CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $message = "Security verification failed. Please refresh the page and try again.";
        $messageType = "error";

    } else {

        $email = normalize_email($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";

        // 2. Validate input constraints
        if (empty($email) || empty($password)) {
            $message = "Please enter both your email address and password.";
            $messageType = "error";

        } elseif (strlen($email) > 254 || !validate_email_address($email)) {
            $message = "Please enter a valid email address (maximum 254 characters).";
            $messageType = "error";

        } elseif (strlen($password) > 35) {
            $message = "Password cannot exceed 35 characters.";
            $messageType = "error";

        } else {

            // 3. Check Account Lockout in Database
            $lockoutStatus = check_user_lockout($conn, $email);

            if ($lockoutStatus['locked']) {
                $rem = $lockoutStatus['remaining_minutes'];

                $message = "Account temporarily locked due to too many failed login attempts. Please try again in {$rem} minute(s).";
                $messageType = "error";

            } else {

                // 4. Retrieve User Credentials
                $stmt = $conn->prepare("
                    SELECT id, full_name, username, email, password, failed_login_attempts
                    FROM users
                    WHERE email = ?
                    LIMIT 1
                ");

                if (!$stmt) {
                    $message = "Service is temporarily unavailable. Please try again.";
                    $messageType = "error";

                } else {

                    $stmt->bind_param("s", $email);
                    $stmt->execute();

                    $user = $stmt->get_result()->fetch_assoc();

                    $stmt->close();

                    // 5. Verify Password
                    if ($user && password_verify($password, $user['password'])) {

                        // Reset failed login counter and unlock account
                        reset_failed_logins($conn, $email);

                        // Establish secure authenticated session
                        login_user_session($user);

                        // Redirect to intended dashboard
                        header("Location: dashboard.php");
                        exit();

                    } else {

                        // Record failed attempt
                        $attempts = record_failed_login($conn, $email);

                        if ($attempts >= 5) {

                            $message = "Account temporarily locked! 5 consecutive failed attempts detected. Login is locked for 15 minutes.";

                        } else {

                            $remainingAttempts = 5 - $attempts;

                            $message = "Incorrect email address or password. ({$remainingAttempts} attempt(s) remaining before a 15-minute lockout).";
                        }

                        $messageType = "error";
                    }
                }
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
    <div class="container auth-container">

        <div class="auth-card">

            <!-- LOGIN HEADER -->
            <div class="auth-header">
                <span class="section-pill">Welcome Back</span>

                <h1>Sign In</h1>

                <p>Access your Needify dashboard, orders, and needs</p>
            </div>

            <!-- NOTIFICATIONS -->
            <?php if (!empty($message)): ?>

                <div class="alert alert-<?= ($messageType === 'error') ? 'error' : (($messageType === 'info') ? 'info' : 'success') ?>" role="alert">

                    <i class="fa-solid <?= ($messageType === 'error') ? 'fa-triangle-exclamation' : 'fa-circle-check' ?>"></i>

                    <div><?= e($message) ?></div>

                </div>

            <?php endif; ?>


            <!-- LOGIN FORM -->
            <form action="login.php" method="POST" id="loginForm" novalidate>

                <?= csrf_field() ?>

                <!-- EMAIL FIELD -->
                <div class="form-group">

                    <label for="loginEmail" class="form-label">
                        Email Address
                    </label>

                    <div class="form-input-wrap">

                        <i class="fa-regular fa-envelope form-input-icon"></i>

                        <input
                            type="email"
                            id="loginEmail"
                            name="email"
                            class="form-control"
                            placeholder="name@example.com"
                            value="<?= e($email) ?>"
                            maxlength="254"
                            required
                            autocomplete="email"
                        >

                    </div>

                </div>


                <!-- PASSWORD FIELD -->
                <div class="form-group">

                    <div class="form-label">

                        <label for="loginPassword">
                            Password
                        </label>

                        <a
                            href="forgot-password.php"
                            style="font-size: 0.8rem; color: var(--primary);"
                        >
                            Forgot Password?
                        </a>

                    </div>

                    <div class="form-input-wrap">

                        <i class="fa-solid fa-lock form-input-icon"></i>

                        <input
                            type="password"
                            id="loginPassword"
                            name="password"
                            class="form-control"
                            placeholder="Enter your password"
                            maxlength="35"
                            required
                            autocomplete="current-password"
                        >

                        <button
                            type="button"
                            class="password-toggle-btn"
                            id="toggleLoginPasswordBtn"
                            aria-label="Toggle password visibility"
                        >
                            <i
                                class="fa-regular fa-eye"
                                id="toggleLoginPasswordIcon"
                            ></i>
                        </button>

                    </div>

                </div>


                <!-- SIGN IN BUTTON -->
                <button
                    type="submit"
                    class="btn btn-primary btn-submit"
                    id="loginSubmitBtn"
                    style="width: 100%; padding: 13px; font-size: 1rem; margin-top: 10px;"
                >

                    <span id="loginBtnText">Sign In</span>

                    <i
                        class="fa-solid fa-arrow-right-to-bracket"
                        id="loginBtnIcon"
                    ></i>

                </button>

            </form>


            <!-- SOCIAL LOGIN DIVIDER -->
            <div class="auth-divider">
                <span>Or continue with</span>
            </div>


            <!-- SOCIAL AUTHENTICATION BUTTONS -->
            <div class="social-auth-group">

                <!-- GOOGLE IDENTITY SERVICES -->
                <div
                    id="g_id_onload"
                    data-client_id="<?= htmlspecialchars(GOOGLE_CLIENT_ID, ENT_QUOTES, 'UTF-8') ?>"
                    data-context="signin"
                    data-ux_mode="redirect"
                    data-login_uri="<?= htmlspecialchars(GOOGLE_REDIRECT_URI, ENT_QUOTES, 'UTF-8') ?>"
                    data-auto_prompt="false"
                >
                </div>

                <div
                    class="g_id_signin"
                    data-type="standard"
                    data-shape="pill"
                    data-theme="outline"
                    data-text="continue_with"
                    data-size="large"
                    data-logo_alignment="left"
                    data-width="100%"
                >
                </div>


                <!-- FACEBOOK OAUTH BUTTON -->
                <a
                    href="facebook-login.php"
                    class="btn-social facebook-btn"
                    title="Continue with Facebook"
                >

                    <i class="fa-brands fa-facebook"></i>

                    <span>Continue with Facebook</span>

                </a>

            </div>


            <!-- CREATE ACCOUNT LINK -->
            <div class="auth-footer-link">

                Don't have an account yet?

                <a href="signup.php">Create Account</a>

            </div>

        </div>

    </div>
</section>


<!-- GOOGLE IDENTITY SERVICES LIBRARY -->
<script src="https://accounts.google.com/gsi/client" async defer></script>


<!-- LOGIN PAGE JAVASCRIPT -->
<script>

document.addEventListener('DOMContentLoaded', function () {

    const pwdInput = document.getElementById('loginPassword');

    const toggleBtn = document.getElementById('toggleLoginPasswordBtn');

    const toggleIcon = document.getElementById('toggleLoginPasswordIcon');

    const loginForm = document.getElementById('loginForm');

    const submitBtn = document.getElementById('loginSubmitBtn');

    const btnText = document.getElementById('loginBtnText');

    const btnIcon = document.getElementById('loginBtnIcon');


    // Password visibility toggle
    toggleBtn.addEventListener('click', function () {

        const isPassword = pwdInput.type === 'password';

        pwdInput.type = isPassword ? 'text' : 'password';

        toggleIcon.className = isPassword
            ? 'fa-regular fa-eye-slash'
            : 'fa-regular fa-eye';

    });


    // Form submission loading state
    loginForm.addEventListener('submit', function () {

        submitBtn.disabled = true;

        btnText.textContent = 'Verifying...';

        btnIcon.className = 'fa-solid fa-spinner fa-spin';

    });

});

</script>


<?php require_once __DIR__ . '/includes/footer.php'; ?>
