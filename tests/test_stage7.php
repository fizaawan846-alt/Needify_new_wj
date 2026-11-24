<?php
// Stage 7 test suite – syntax checks for AI assistant pages
$files = [
    __DIR__ . '/../config/gemini.php',
    __DIR__ . '/../includes/gemini_client.php',
    __DIR__ . '/../ai_chat.php',
    __DIR__ . '/../ai_chat_endpoint.php',
];
$all_pass = true;
foreach ($files as $file) {
    $output = [];
    $returnVar = 0;
    exec('php -l ' . escapeshellarg($file), $output, $returnVar);
    if ($returnVar !== 0) {
        echo "Syntax error in $file: " . implode(' ', $output) . "\n";
        $all_pass = false;
    } else {
        echo "OK: $file\n";
    }
}
if ($all_pass) {
    echo "All Stage 7 files passed syntax checks.\n";
    exit(0);
} else {
    exit(1);
}
?>