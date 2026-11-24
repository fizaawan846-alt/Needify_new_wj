
<?php

/*
|--------------------------------------------------------------------------
| FACEBOOK CALLBACK
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/facebook.php";

// Security headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Redirect with error message
function facebookError($message)
{
    unset($_SESSION["facebook_oauth_state"]);

    $_SESSION["facebook_login_error"] = $message;

    header("Location: login.php");
    exit;
}

// Log detailed technical error without exposing secrets
function facebookLogError($stage, $details)
{
    error_log(
        "[Needify Facebook][" . $stage . "] " . $details
    );
}

// Extract safe error details from Facebook response
function facebookApiError($response, $httpStatus)
{
    $data = json_decode($response, true);

    if (isset($data["error"]) && is_array($data["error"])) {

        $code = $data["error"]["code"] ?? "Unknown";
        $message = $data["error"]["message"] ?? "Unknown Facebook API error";

        return "HTTP " . $httpStatus .
            ", Facebook error " . $code . ": " . $message;
    }

    return "HTTP " . $httpStatus .
        ". Facebook returned an invalid response.";
}

// Check configuration
if (
    !defined("FACEBOOK_APP_ID") ||
    !defined("FACEBOOK_APP_SECRET") ||
    !defined("FACEBOOK_REDIRECT_URI") ||
    !defined("FACEBOOK_GRAPH_VERSION") ||
    trim(FACEBOOK_APP_ID) === "" ||
    trim(FACEBOOK_APP_SECRET) === "" ||
    FACEBOOK_APP_SECRET === "PASTE_YOUR_FACEBOOK_APP_SECRET_HERE" ||
    FACEBOOK_APP_SECRET === "PASTE_YOUR_APP_SECRET_HERE"
) {
    facebookLogError("Configuration", "Facebook configuration is incomplete.");

    facebookError("Facebook configuration is incomplete. Please check settings.");
}

// Check cURL
if (!function_exists("curl_init")) {
    facebookLogError("cURL", "PHP cURL extension is not enabled.");

    facebookError("Facebook connection requires PHP cURL. Please enable it.");
}

// Check database connection
if (!isset($conn) || !($conn instanceof mysqli)) {
    facebookLogError("Database", "Database connection is unavailable.");

    facebookError("Database connection failed.");
}

// Check Facebook OAuth error
if (isset($_GET["error"])) {

    $errorDescription = isset($_GET["error_description"])
        ? substr((string) $_GET["error_description"], 0, 300)
        : "Login cancelled or denied.";

    facebookLogError("OAuth", $errorDescription);

    facebookError(
        "Facebook login was cancelled or denied: " . $errorDescription
    );
}

// Validate authorization code
if (
    !isset($_GET["code"]) ||
    !is_string($_GET["code"]) ||
    $_GET["code"] === ""
) {
    facebookError("Facebook authorization code is missing.");
}

// Validate CSRF state
if (
    !isset($_GET["state"]) ||
    !isset($_SESSION["facebook_oauth_state"]) ||
    !is_string($_GET["state"]) ||
    !hash_equals(
        $_SESSION["facebook_oauth_state"],
        $_GET["state"]
    )
) {
    facebookError("Invalid Facebook login request. Please try again.");
}

unset($_SESSION["facebook_oauth_state"]);

$code = $_GET["code"];

/*
|--------------------------------------------------------------------------
| EXCHANGE AUTHORIZATION CODE FOR ACCESS TOKEN
|--------------------------------------------------------------------------
*/

$tokenUrl = "https://graph.facebook.com/"
    . FACEBOOK_GRAPH_VERSION
    . "/oauth/access_token";

$tokenParams = [
    "client_id" => FACEBOOK_APP_ID,
    "client_secret" => FACEBOOK_APP_SECRET,
    "redirect_uri" => FACEBOOK_REDIRECT_URI,
    "code" => $code
];

$ch = curl_init(
    $tokenUrl . "?" . http_build_query($tokenParams)
);

if ($ch === false) {
    facebookLogError("Token", "Unable to initialize cURL.");

    facebookError("Unable to connect to Facebook. Please try again.");
}

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);

$tokenResponse = curl_exec($ch);
$tokenStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$tokenError = curl_error($ch);

curl_close($ch);

if ($tokenResponse === false) {

    facebookLogError("Token cURL", $tokenError);

    facebookError(
        "Facebook connection failed. Network or SSL error: " . $tokenError
    );
}

if ($tokenStatus !== 200) {

    $details = facebookApiError($tokenResponse, $tokenStatus);

    facebookLogError("Token API", $details);

    facebookError(
        "Facebook token request failed: " . $details
    );
}

$tokenData = json_decode($tokenResponse, true);

if (
    !is_array($tokenData) ||
    empty($tokenData["access_token"])
) {
    facebookLogError(
        "Token Response",
        "Access token was missing from Facebook response."
    );

    facebookError("Facebook did not provide an access token.");
}

$accessToken = $tokenData["access_token"];

/*
|--------------------------------------------------------------------------
| FETCH FACEBOOK USER PROFILE
|--------------------------------------------------------------------------
*/

$profileUrl = "https://graph.facebook.com/"
    . FACEBOOK_GRAPH_VERSION
    . "/me";

$profileParams = [
    "fields" => "id,name,email",
    "access_token" => $accessToken
];

$ch = curl_init(
    $profileUrl . "?" . http_build_query($profileParams)
);

if ($ch === false) {
    facebookLogError("Profile", "Unable to initialize cURL.");

    facebookError("Unable to retrieve your Facebook profile.");
}

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);

$profileResponse = curl_exec($ch);
$profileStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$profileError = curl_error($ch);

curl_close($ch);

if ($profileResponse === false) {

    facebookLogError("Profile cURL", $profileError);

    facebookError(
        "Facebook profile connection failed: " . $profileError
    );
}

if ($profileStatus !== 200) {

    $details = facebookApiError($profileResponse, $profileStatus);

    facebookLogError("Profile API", $details);

    facebookError(
        "Facebook profile request failed: " . $details
    );
}

$facebookUser = json_decode($profileResponse, true);

if (
    !is_array($facebookUser) ||
    empty($facebookUser["id"]) ||
    empty($facebookUser["name"]) ||
    empty($facebookUser["email"])
) {
    facebookLogError(
        "Profile Data",
        "Facebook did not return all required profile fields."
    );

    facebookError(
        "Facebook did not provide your email address. Please use email login."
    );
}

$facebookId = (string) $facebookUser["id"];
$fullName = trim($facebookUser["name"]);
$email = strtolower(trim($facebookUser["email"]));

if (
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($email) > 254 ||
    strlen($fullName) > 24
) {
    facebookError("Facebook returned invalid account information.");
}

/*
|--------------------------------------------------------------------------
| CHECK EXISTING FACEBOOK ACCOUNT
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT id, full_name, username, email
     FROM users
     WHERE facebook_id = ?
     LIMIT 1"
);

if (!$stmt) {
    facebookLogError("Database", $conn->error);

    facebookError("Unable to check your Facebook account.");
}

$stmt->bind_param("s", $facebookId);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

// Existing Facebook account
if ($user) {

    require_once __DIR__ . "/includes/auth.php";
    require_once __DIR__ . "/includes/functions.php";
    reset_failed_logins($conn, $user["email"]);
    login_user_session($user);

    header("Location: dashboard.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| CHECK EXISTING EMAIL ACCOUNT
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT id, full_name, username, email, facebook_id
     FROM users
     WHERE email = ?
     LIMIT 1"
);

if (!$stmt) {
    facebookLogError("Database", $conn->error);

    facebookError("Unable to check your email account.");
}

$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();
$existingUser = $result->fetch_assoc();

$stmt->close();

// Existing email account
if ($existingUser) {

    if (!empty($existingUser["facebook_id"])) {

        facebookError(
            "This email is already connected to another Facebook account."
        );
    }

    $stmt = $conn->prepare(
        "UPDATE users
         SET facebook_id = ?
         WHERE id = ? AND facebook_id IS NULL"
    );

    if (!$stmt) {
        facebookLogError("Database", $conn->error);

        facebookError("Unable to link your Facebook account.");
    }

    $userId = (int) $existingUser["id"];

    $stmt->bind_param("si", $facebookId, $userId);

    if (!$stmt->execute()) {
        facebookLogError("Database", $stmt->error);
        $stmt->close();

        facebookError("Unable to link your Facebook account.");
    }

    $stmt->close();

    require_once __DIR__ . "/includes/auth.php";
    require_once __DIR__ . "/includes/functions.php";
    reset_failed_logins($conn, $existingUser["email"]);
    login_user_session($existingUser);

    header("Location: dashboard.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| NEW FACEBOOK USER
|--------------------------------------------------------------------------
*/

$_SESSION["facebook_pending_user"] = [
    "id" => $facebookId,
    "name" => $fullName,
    "email" => $email
];

header("Location: facebook-complete.php");
exit;
