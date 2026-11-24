
<?php
require_once __DIR__ . '/includes/gemini_client.php';

echo "<h2>Needify AI Response Test</h2>";

$response = gemini_query(
    "Reply with exactly: Needify AI is working!"
);

if ($response !== false) {
    echo "<p style='color:green;'>SUCCESS!</p>";
    echo "<p>" . htmlspecialchars($response) . "</p>";
} else {
    echo "<p style='color:red;'>AI response failed.</p>";
    echo "<p>Please check Apache error.log for details.</p>";
}
