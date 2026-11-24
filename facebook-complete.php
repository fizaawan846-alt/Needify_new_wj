
<?php
session_start();

require_once "config/database.php";

/*
|--------------------------------------------------------------------------
| FACEBOOK ACCOUNT COMPLETION
|--------------------------------------------------------------------------
*/

// Security headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

if (
    !isset($_SESSION["facebook_pending_user"]) ||
    !is_array($_SESSION["facebook_pending_user"])
) {
    header("Location: login.php");
    exit;
}

$facebookUser = $_SESSION["facebook_pending_user"];

$facebookId = (string) ($facebookUser["id"] ?? "");
$fullName = trim((string) ($facebookUser["name"] ?? ""));
$email = strtolower(trim((string) ($facebookUser["email"] ?? "")));

if (
    $facebookId === "" ||
    $fullName === "" ||
    !filter_var($email, FILTER_VALIDATE_EMAIL)
) {
    unset($_SESSION["facebook_pending_user"]);
    header("Location: login.php");
    exit;
}

// Generate CSRF token
if (empty($_SESSION["facebook_complete_csrf"])) {
    $_SESSION["facebook_complete_csrf"] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "";

$username = "";
$countryCode = "+92";
$phone = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $countryCode = trim($_POST["country_code"] ?? "+92");
    $phone = trim($_POST["phone"] ?? "");
    $csrfToken = $_POST["csrf_token"] ?? "";

    // CSRF validation
    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION["facebook_complete_csrf"], $csrfToken)
    ) {
        $message = "Invalid request. Please refresh the page.";
        $messageType = "error";
    }

    // Username validation
    elseif (
        $username === "" ||
        strlen($username) > 24 ||
        !preg_match('/^[a-zA-Z0-9]{3,24}$/', $username)
    ) {
        $message = "Username must contain 3 to 24 letters or numbers only.";
        $messageType = "error";
    }

    // Country code validation
    elseif (!preg_match('/^\+[0-9]{1,4}$/', $countryCode)) {
        $message = "Please enter a valid country code.";
        $messageType = "error";
    }

    // Phone validation
    elseif (!preg_match('/^[0-9]{7,15}$/', $phone)) {
        $message = "Phone number must contain 7 to 15 digits.";
        $messageType = "error";
    }

    else {

        // Check username uniqueness
        $stmt = $conn->prepare(
            "SELECT id FROM users WHERE username = ? LIMIT 1"
        );

        if (!$stmt) {
            $message = "A database error occurred.";
            $messageType = "error";
        } else {

            $stmt->bind_param("s", $username);
            $stmt->execute();

            $usernameResult = $stmt->get_result();
            $usernameExists = $usernameResult->num_rows > 0;

            $stmt->close();

            if ($usernameExists) {
                $message = "This username is already taken. Please choose another.";
                $messageType = "error";
            } else {

                // Check email and Facebook ID again
                $stmt = $conn->prepare(
                    "SELECT id FROM users
                     WHERE email = ? OR facebook_id = ?
                     LIMIT 1"
                );

                if (!$stmt) {
                    $message = "A database error occurred.";
                    $messageType = "error";
                } else {

                    $stmt->bind_param("ss", $email, $facebookId);
                    $stmt->execute();

                    $existingResult = $stmt->get_result();
                    $accountExists = $existingResult->num_rows > 0;

                    $stmt->close();

                    if ($accountExists) {
                        $message = "An account with this email or Facebook profile already exists. Please log in.";
                        $messageType = "error";
                    } else {

                        // Generate an unknown random password.
                        // User can later use the password reset feature.
                        $randomPassword = bin2hex(random_bytes(32));
                        $hashedPassword = password_hash(
                            $randomPassword,
                            PASSWORD_DEFAULT
                        );

                        if ($hashedPassword === false) {
                            $message = "Unable to create your account.";
                            $messageType = "error";
                        } else {

                            $stmt = $conn->prepare(
                                "INSERT INTO users
                                (full_name, username, email, phone,
                                 country_code, password, facebook_id)
                                VALUES (?, ?, ?, ?, ?, ?, ?)"
                            );

                            if (!$stmt) {
                                $message = "Unable to prepare account creation.";
                                $messageType = "error";
                            } else {

                                $stmt->bind_param(
                                    "sssssss",
                                    $fullName,
                                    $username,
                                    $email,
                                    $phone,
                                    $countryCode,
                                    $hashedPassword,
                                    $facebookId
                                );

                                if ($stmt->execute()) {

                                    $newUserId = (int) $conn->insert_id;

                                    $stmt->close();

                                    // Clear pending Facebook information
                                    unset($_SESSION["facebook_pending_user"]);
                                    unset($_SESSION["facebook_complete_csrf"]);

                                    // Start authenticated session
                                    require_once __DIR__ . '/includes/auth.php';
                                    login_user_session([
                                        'id'        => $newUserId,
                                        'full_name' => $fullName,
                                        'username'  => $username,
                                        'email'     => $email
                                    ]);

                                    header("Location: dashboard.php?welcome=1");
                                    exit;

                                } else {

                                    error_log(
                                        "Facebook signup database error: " .
                                        $stmt->error
                                    );

                                    $stmt->close();

                                    $message = "Account could not be created. Please try again.";
                                    $messageType = "error";
                                }
                            }
                        }
                    }
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

    <title>Complete Your Profile - Needify</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #f8e8f3, #eee7ff);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 460px;
            background: #ffffff;
            padding: 35px;
            border-radius: 18px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.10);
        }

        h1 {
            text-align: center;
            color: #713b91;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: #777;
            font-size: 14px;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .user-email {
            background: #f7f1fb;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
            color: #713b91;
            font-size: 14px;
            word-break: break-word;
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: #333;
            font-weight: bold;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 15px;
            outline: none;
        }

        input:focus {
            border-color: #9146b5;
            box-shadow: 0 0 0 2px rgba(145,70,181,0.12);
        }

        .phone-row {
            display: flex;
            gap: 10px;
        }

        .phone-row .country {
            width: 95px;
            flex-shrink: 0;
        }

        .phone-row .phone {
            flex: 1;
            min-width: 0;
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #9146b5, #c05b9b);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            opacity: 0.92;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 14px;
            text-align: center;
        }

        .error {
            background: #ffe8e8;
            color: #b42318;
        }

        .success {
            background: #e8f8ed;
            color: #18753b;
        }

        .back {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #81439f;
            text-decoration: none;
            font-size: 14px;
        }

        body.dark {
            background: #171421;
        }

        body.dark .container {
            background: #262235;
        }

        body.dark h1,
        body.dark label {
            color: #f5eaff;
        }

        body.dark .subtitle {
            color: #c5bdcf;
        }

        body.dark input {
            background: #353047;
            border-color: #514765;
            color: white;
        }

        body.dark .user-email {
            background: #393047;
            color: #e8c7ff;
        }

        body.dark .back {
            color: #e4baff;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Complete Your Profile</h1>

    <p class="subtitle">
        Welcome to Needify! Just a few more details are needed
        to finish creating your account.
    </p>

    <div class="user-email">
        <?php echo htmlspecialchars($email, ENT_QUOTES, "UTF-8"); ?>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo htmlspecialchars(
                $_SESSION["facebook_complete_csrf"],
                ENT_QUOTES,
                "UTF-8"
            ); ?>"
        >

        <label>Full Name</label>

        <input
            type="text"
            value="<?php echo htmlspecialchars($fullName, ENT_QUOTES, "UTF-8"); ?>"
            disabled
        >

        <label>Username</label>

        <input
            type="text"
            name="username"
            placeholder="Choose a username"
            maxlength="24"
            pattern="[A-Za-z0-9]{3,24}"
            value="<?php echo htmlspecialchars($username, ENT_QUOTES, "UTF-8"); ?>"
            required
        >

        <label>Phone Number</label>

        <div class="phone-row">

            <input
                type="text"
                name="country_code"
                class="country"
                placeholder="+92"
                value="<?php echo htmlspecialchars($countryCode, ENT_QUOTES, "UTF-8"); ?>"
                maxlength="5"
                required
            >

            <input
                type="tel"
                name="phone"
                class="phone"
                placeholder="3001234567"
                value="<?php echo htmlspecialchars($phone, ENT_QUOTES, "UTF-8"); ?>"
                maxlength="15"
                pattern="[0-9]{7,15}"
                required
            >

        </div>

        <button type="submit">
            Complete Registration
        </button>

    </form>

    <a href="login.php" class="back">Back to Login</a>

</div>

<script>
    if (localStorage.getItem("needify_theme") === "dark") {
        document.body.classList.add("dark");
    }
</script>

</body>
</html>
