
<?php
/**
 * NEEDIFY - USER REGISTRATION
 * Strict username & password validation, live checklist,
 * CSRF protection, Google & Facebook social authentication.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/google.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already authenticated
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit();
}

$pageTitle = "Create an Account";
$pageDescription = "Join Needify today. Browse products, share campus needs, and connect with the University of Sargodha community.";

$message = "";
$messageType = "";

$fullName = "";
$username = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // 1. Verify CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        $message = "Security verification failed. Please refresh the page and try again.";
        $messageType = "error";

    } else {

        $fullName = trim(sanitize_text($_POST["full_name"] ?? ""));
        $username = clean_username($_POST["username"] ?? "");
        $email = normalize_email($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";

        // 2. Validate Full Name: English alphabets and spaces only
        if ($fullName === "") {

            $message = "Please enter your full name.";
            $messageType = "error";

        } elseif (
            strlen($fullName) > 100 ||
            !preg_match("/^(?=.*[A-Za-z])[A-Za-z ]+$/", $fullName)
        ) {

            $message = "Full name must contain English letters and spaces only.";
            $messageType = "error";

        }

        // 3. Strict Username Validation
        elseif (!validate_username($username)) {

            $message = "Username must be 1 to 24 characters and contain only English letters and numbers (no spaces or special characters).";
            $messageType = "error";

        }

        // 4. Strict Email Validation
        elseif (!validate_email_address($email)) {

            $message = "Please enter a valid email address (maximum 254 characters).";
            $messageType = "error";

        }

        // 5. Strict Password Strength Validation
        elseif (!validate_password_strength($password)['is_valid']) {

            $message = "Password must be 8-35 characters and include at least one uppercase letter, one lowercase letter, one number, and one special character.";
            $messageType = "error";

        }

        // 6. Confirm Password Validation
        elseif (strlen($confirmPassword) > 35 || $password !== $confirmPassword) {

            $message = "Confirm password does not match the password (maximum 35 characters).";
            $messageType = "error";

        }

        // 7. Check Duplicate Username and Email
        else {

            $checkStmt = $conn->prepare("
                SELECT id, username, email
                FROM users
                WHERE username = ? OR email = ?
                LIMIT 1
            ");

            if (!$checkStmt) {

                $message = "Service is temporarily busy. Please try again.";
                $messageType = "error";

            } else {

                $checkStmt->bind_param("ss", $username, $email);
                $checkStmt->execute();

                $existing = $checkStmt->get_result()->fetch_assoc();

                $checkStmt->close();

                if ($existing) {

                    if (strcasecmp($existing['email'], $email) === 0) {

                        $message = "An account with this email address already exists. Please log in.";

                    } else {

                        $message = "This username is already taken. Please choose another username.";

                    }

                    $messageType = "error";

                } else {

                    // 8. Hash Password and Create Account
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    $insertStmt = $conn->prepare("
                        INSERT INTO users (full_name, username, email, password)
                        VALUES (?, ?, ?, ?)
                    ");

                    if ($insertStmt) {

                        $insertStmt->bind_param(
                            "ssss",
                            $fullName,
                            $username,
                            $email,
                            $hashedPassword
                        );

                        if ($insertStmt->execute()) {

                            $newUserId = $insertStmt->insert_id;

                            $insertStmt->close();

                            // Automatically log user in
                            login_user_session([
                                'id'        => $newUserId,
                                'full_name' => $fullName,
                                'username'  => $username,
                                'email'     => $email
                            ]);

                            header("Location: dashboard.php?welcome=1");
                            exit();

                        } else {

                            $message = "Unable to create your account at this time. Please try again.";
                            $messageType = "error";

                            $insertStmt->close();

                        }

                    } else {

                        $message = "System error. Please try again later.";
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

            <!-- REGISTRATION HEADER -->
            <div class="auth-header">

                <span class="section-pill">Join The Community</span>

                <h1>Create Account</h1>

                <p>Sign up to shop campus products and share community needs</p>

            </div>


            <!-- NOTIFICATION MESSAGES -->
            <?php if (!empty($message)): ?>

                <div class="alert alert-<?= ($messageType === 'error') ? 'error' : 'success' ?>" role="alert">

                    <i class="fa-solid <?= ($messageType === 'error') ? 'fa-circle-exclamation' : 'fa-circle-check' ?>"></i>

                    <div><?= e($message) ?></div>

                </div>

            <?php endif; ?>


            <!-- REGISTRATION FORM -->
            <form action="signup.php" method="POST" id="signupForm" novalidate>

                <?= csrf_field() ?>


                <!-- FULL NAME -->
                <div class="form-group">

                    <label for="fullNameInput" class="form-label">
                        Full Name
                    </label>

                    <div class="form-input-wrap">

                        <i class="fa-regular fa-user form-input-icon"></i>

                        <input
                            type="text"
                            id="fullNameInput"
                            name="full_name"
                            class="form-control"
                            placeholder="e.g. Fatima Ali"
                            value="<?= e($fullName) ?>"
                            maxlength="100"
                            required
                            autocomplete="name"
                        >

                    </div>

                </div>


                <!-- USERNAME -->
                <div class="form-group">

                    <label for="usernameInput" class="form-label">

                        <span>Username</span>

                        <span style="font-size: 0.76rem; color: var(--text-muted);">
                            1-24 letters & numbers only
                        </span>

                    </label>

                    <div class="form-input-wrap">

                        <i class="fa-solid fa-at form-input-icon"></i>

                        <input
                            type="text"
                            id="usernameInput"
                            name="username"
                            class="form-control"
                            placeholder="e.g. fatima123"
                            value="<?= e($username) ?>"
                            maxlength="24"
                            required
                            autocomplete="username"
                        >

                    </div>

                </div>


                <!-- EMAIL ADDRESS -->
                <div class="form-group">

                    <label for="emailInput" class="form-label">
                        Email Address
                    </label>

                    <div class="form-input-wrap">

                        <i class="fa-regular fa-envelope form-input-icon"></i>

                        <input
                            type="email"
                            id="emailInput"
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


                <!-- PASSWORD -->
                <div class="form-group">

                    <label for="passwordInput" class="form-label">
                        Password
                    </label>

                    <div class="form-input-wrap">

                        <i class="fa-solid fa-lock form-input-icon"></i>

                        <input
                            type="password"
                            id="passwordInput"
                            name="password"
                            class="form-control"
                            placeholder="8 to 35 characters"
                            maxlength="35"
                            required
                            autocomplete="new-password"
                        >

                        <button
                            type="button"
                            class="password-toggle-btn"
                            id="togglePasswordBtn"
                            aria-label="Toggle password visibility"
                        >

                            <i
                                class="fa-regular fa-eye"
                                id="togglePasswordIcon"
                            ></i>

                        </button>

                    </div>


                    <!-- LIVE PASSWORD REQUIREMENTS -->
                    <div class="password-checklist" id="pwdChecklist" aria-live="polite">

                        <div class="checklist-item invalid" id="checkLength">
                            <i class="fa-solid fa-circle-xmark"></i>
                            <span>8 to 35 characters long</span>
                        </div>

                        <div class="checklist-item invalid" id="checkUpper">
                            <i class="fa-solid fa-circle-xmark"></i>
                            <span>At least one uppercase letter (A-Z)</span>
                        </div>

                        <div class="checklist-item invalid" id="checkLower">
                            <i class="fa-solid fa-circle-xmark"></i>
                            <span>At least one lowercase letter (a-z)</span>
                        </div>

                        <div class="checklist-item invalid" id="checkDigit">
                            <i class="fa-solid fa-circle-xmark"></i>
                            <span>At least one number (0-9)</span>
                        </div>

                        <div class="checklist-item invalid" id="checkSpecial">
                            <i class="fa-solid fa-circle-xmark"></i>
                            <span>At least one special character (@, #, $, !, %, etc.)</span>
                        </div>

                    </div>

                </div>


                <!-- CONFIRM PASSWORD -->
                <div class="form-group">

                    <label for="confirmPasswordInput" class="form-label">
                        Confirm Password
                    </label>

                    <div class="form-input-wrap">

                        <i class="fa-solid fa-shield-halved form-input-icon"></i>

                        <input
                            type="password"
                            id="confirmPasswordInput"
                            name="confirm_password"
                            class="form-control"
                            placeholder="Re-enter your password"
                            maxlength="35"
                            required
                            autocomplete="new-password"
                        >

                    </div>

                    <div
                        id="matchNotice"
                        style="font-size: 0.78rem; margin-top: 6px; display: none;"
                    ></div>

                </div>


                <!-- CREATE ACCOUNT BUTTON -->
                <button
                    type="submit"
                    class="btn btn-primary btn-submit"
                    id="submitBtn"
                    style="width: 100%; padding: 13px; font-size: 1rem; margin-top: 8px;"
                >

                    <span id="btnText">Create My Account</span>

                    <i class="fa-solid fa-arrow-right" id="btnIcon"></i>

                </button>

            </form>


            <!-- SOCIAL AUTHENTICATION DIVIDER -->
            <div class="auth-divider">

                <span>Or register with</span>

            </div>


            <!-- GOOGLE & FACEBOOK BUTTONS -->
            <div class="social-auth-group">

                <!-- GOOGLE IDENTITY SERVICES -->
                <div
                    id="g_id_onload"
                    data-client_id="<?= htmlspecialchars(GOOGLE_CLIENT_ID, ENT_QUOTES, 'UTF-8') ?>"
                    data-context="signup"
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


                <!-- FACEBOOK OAUTH -->
                <a
                    href="facebook-login.php"
                    class="btn-social facebook-btn"
                    title="Continue with Facebook"
                >

                    <i class="fa-brands fa-facebook"></i>

                    <span>Continue with Facebook</span>

                </a>

            </div>


            <!-- LOGIN LINK -->
            <div class="auth-footer-link">

                Already have an account?

                <a href="login.php">Log In</a>

            </div>

        </div>

    </div>

</section>


<!-- GOOGLE IDENTITY SERVICES LIBRARY -->
<script src="https://accounts.google.com/gsi/client" async defer></script>


<!-- REGISTRATION JAVASCRIPT -->
<script>

document.addEventListener('DOMContentLoaded', function () {

    const fullNameInput = document.getElementById('fullNameInput');

    const usernameInput = document.getElementById('usernameInput');

    const pwdInput = document.getElementById('passwordInput');

    const confirmInput = document.getElementById('confirmPasswordInput');

    const toggleBtn = document.getElementById('togglePasswordBtn');

    const toggleIcon = document.getElementById('togglePasswordIcon');

    const matchNotice = document.getElementById('matchNotice');

    const signupForm = document.getElementById('signupForm');

    const submitBtn = document.getElementById('submitBtn');

    const btnText = document.getElementById('btnText');

    const btnIcon = document.getElementById('btnIcon');


    // FULL NAME: Allow English alphabets and spaces only

    // Block invalid characters while typing
    fullNameInput.addEventListener('beforeinput', function (event) {

        if (
            event.data !== null &&
            event.inputType !== 'insertCompositionText' &&
            !/^[A-Za-z ]+$/.test(event.data)
        ) {
            event.preventDefault();
        }

    });


    // Allow only valid characters when pasting
    fullNameInput.addEventListener('paste', function (event) {

        event.preventDefault();

        const pastedText = (event.clipboardData || window.clipboardData)
            .getData('text');

        const cleanedText = pastedText.replace(/[^A-Za-z ]/g, '');

        const start = this.selectionStart;
        const end = this.selectionEnd;

        const newValue =
            this.value.substring(0, start) +
            cleanedText +
            this.value.substring(end);

        this.value = newValue.substring(0, 100);

        const newCursorPosition = Math.min(
            start + cleanedText.length,
            100
        );

        this.setSelectionRange(newCursorPosition, newCursorPosition);

        this.dispatchEvent(new Event('input', { bubbles: true }));

    });


    // Final safeguard for typing, autofill and other input methods
    fullNameInput.addEventListener('input', function () {

        const cleaned = this.value.replace(/[^A-Za-z ]/g, '');

        if (this.value !== cleaned) {

            const cursorPosition = this.selectionStart;

            this.value = cleaned.substring(0, 100);

            this.setSelectionRange(
                Math.min(cursorPosition, this.value.length),
                Math.min(cursorPosition, this.value.length)
            );

        }

    });


    // 1. Automatically remove invalid username characters
    usernameInput.addEventListener('input', function () {

        const cleaned = this.value.replace(/[^A-Za-z0-9]/g, '');

        if (this.value !== cleaned) {
            this.value = cleaned;
        }

        if (this.value.length > 24) {
            this.value = this.value.substring(0, 24);
        }

    });


    // 2. Toggle password visibility
    toggleBtn.addEventListener('click', function () {

        const isPassword = pwdInput.type === 'password';

        pwdInput.type = isPassword ? 'text' : 'password';

        confirmInput.type = isPassword ? 'text' : 'password';

        toggleIcon.className = isPassword
            ? 'fa-regular fa-eye-slash'
            : 'fa-regular fa-eye';

    });


    // 3. Live password validation checklist
    const checkLength = document.getElementById('checkLength');

    const checkUpper = document.getElementById('checkUpper');

    const checkLower = document.getElementById('checkLower');

    const checkDigit = document.getElementById('checkDigit');

    const checkSpecial = document.getElementById('checkSpecial');


    function updateRule(element, isValid) {

        const icon = element.querySelector('i');

        if (isValid) {

            element.classList.remove('invalid');

            element.classList.add('valid');

            icon.className = 'fa-solid fa-circle-check';

        } else {

            element.classList.remove('valid');

            element.classList.add('invalid');

            icon.className = 'fa-solid fa-circle-xmark';

        }

    }


    pwdInput.addEventListener('input', function () {

        const val = this.value;

        updateRule(checkLength, val.length >= 8 && val.length <= 35);

        updateRule(checkUpper, /[A-Z]/.test(val));

        updateRule(checkLower, /[a-z]/.test(val));

        updateRule(checkDigit, /[0-9]/.test(val));

        updateRule(checkSpecial, /[^A-Za-z0-9]/.test(val));

        checkPasswordMatch();

    });


    // 4. Confirm password matching
    function checkPasswordMatch() {

        if (!confirmInput.value) {

            matchNotice.style.display = 'none';

            return;

        }

        matchNotice.style.display = 'block';

        if (confirmInput.value === pwdInput.value) {

            matchNotice.style.color = '#2E9E65';

            matchNotice.innerHTML =
                '<i class="fa-solid fa-circle-check"></i> Passwords match';

        } else {

            matchNotice.style.color = '#C53030';

            matchNotice.innerHTML =
                '<i class="fa-solid fa-circle-xmark"></i> Passwords do not match';

        }

    }


    confirmInput.addEventListener('input', checkPasswordMatch);


    // 5. Prevent duplicate submission and show loading state
    signupForm.addEventListener('submit', function () {

        submitBtn.disabled = true;

        btnText.textContent = 'Creating Account...';

        btnIcon.className = 'fa-solid fa-spinner fa-spin';

    });

});

</script>


<?php require_once __DIR__ . '/includes/footer.php'; ?>
