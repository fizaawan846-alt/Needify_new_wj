<?php
/**
 * NEEDIFY - GOOGLE AUTHENTICATION CALLBACK
 * Supports Google Identity Services (credential token) and OAuth 2.0 Code flow.
 * Validates token signature, audience, issuer, and email_verified claim.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/google.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

function google_auth_error(string $message): void {
    error_log("[Needify Google Auth Error] " . $message);
    $_SESSION['facebook_login_error'] = $message; // Reuses display alert banner on login page
    header("Location: login.php");
    exit();
}

$idToken = null;

// Case A: Google Identity Services POST credential
if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["credential"])) {
    $idToken = (string)$_POST["credential"];
}
// Case B: OAuth 2.0 Code Grant via GET
elseif (isset($_GET["code"])) {
    // Validate state
    $state = $_GET["state"] ?? "";
    $savedState = $_SESSION["google_oauth_state"] ?? "";
    unset($_SESSION["google_oauth_state"]);

    if (empty($state) || empty($savedState) || !hash_equals($savedState, $state)) {
        google_auth_error("Invalid security state during Google authentication.");
    }

    $code = $_GET["code"];
    $tokenUrl = "https://oauth2.googleapis.com/token";
    $tokenParams = [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code'
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($tokenParams),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $tokenResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($tokenResponse === false || $httpCode !== 200) {
        google_auth_error("Unable to exchange Google authorization code.");
    }

    $tokenData = json_decode($tokenResponse, true);
    if (!empty($tokenData['id_token'])) {
        $idToken = $tokenData['id_token'];
    } else {
        google_auth_error("Google did not provide an ID token.");
    }
} else {
    google_auth_error("Google authorization was cancelled or invalid.");
}

// -------------------------------------------------------------
// Verify Google ID Token Server-Side
// -------------------------------------------------------------
$payload = needify_verify_google_id_token($idToken);
if (!$payload) {
    google_auth_error("Google identity verification failed. Token could not be validated.");
}

$googleId = (string)($payload["sub"] ?? "");
$email = normalize_email($payload["email"] ?? "");
$fullName = trim((string)($payload["name"] ?? "Google User"));

if (empty($googleId) || empty($email) || !validate_email_address($email)) {
    google_auth_error("Incomplete or invalid identity information returned by Google.");
}

// -------------------------------------------------------------
// Check User In Database (Safe Account Resolution)
// -------------------------------------------------------------
$stmt = $conn->prepare("
    SELECT id, full_name, username, email, google_id
    FROM users
    WHERE google_id = ? OR email = ?
    LIMIT 1
");

if (!$stmt) {
    google_auth_error("Database service is temporarily unavailable.");
}

$stmt->bind_param("ss", $googleId, $email);
$stmt->execute();
$existingUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existingUser) {
    // Existing user found - Link Google ID if not yet attached
    if (empty($existingUser['google_id'])) {
        $updateStmt = $conn->prepare("UPDATE users SET google_id = ? WHERE id = ?");
        if ($updateStmt) {
            $updateStmt->bind_param("si", $googleId, $existingUser['id']);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }

    // Reset failed logins and authenticate session
    reset_failed_logins($conn, $email);
    login_user_session($existingUser);

    header("Location: dashboard.php");
    exit();
} else {
    // -------------------------------------------------------------
    // Register New Google User
    // -------------------------------------------------------------
    // Generate valid 1-24 alphanumeric username
    $cleanBase = clean_username($fullName);
    if (empty($cleanBase)) {
        $cleanBase = "User" . random_int(100, 999);
    }
    $cleanBase = substr($cleanBase, 0, 18);
    $candidateUsername = $cleanBase;
    $counter = 1;

    // Ensure username is unique
    while (true) {
        $uStmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $uStmt->bind_param("s", $candidateUsername);
        $uStmt->execute();
        $uExists = $uStmt->get_result()->num_rows > 0;
        $uStmt->close();

        if (!$uExists) {
            break;
        }
        $suffix = (string)$counter;
        $candidateUsername = substr($cleanBase, 0, 24 - strlen($suffix)) . $suffix;
        $counter++;
    }

    // Generate random secure hash
    $randomPwdHash = password_hash(bin2hex(random_bytes(20)), PASSWORD_DEFAULT);

    $insertStmt = $conn->prepare("
        INSERT INTO users (full_name, username, email, google_id, password)
        VALUES (?, ?, ?, ?, ?)
    ");

    if (!$insertStmt) {
        google_auth_error("Unable to create account for Google user.");
    }

    $insertStmt->bind_param("sssss", $fullName, $candidateUsername, $email, $googleId, $randomPwdHash);

    if ($insertStmt->execute()) {
        $newUserId = $insertStmt->insert_id;
        $insertStmt->close();

        login_user_session([
            'id'        => $newUserId,
            'full_name' => $fullName,
            'username'  => $candidateUsername,
            'email'     => $email
        ]);

        header("Location: dashboard.php?welcome=1");
        exit();
    } else {
        $insertStmt->close();
        google_auth_error("Registration with Google failed. Please try again.");
    }
}
