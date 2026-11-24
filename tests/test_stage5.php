<?php
// Stage 5 test suite – syntax checks for Needs Management pages
$files = [
    __DIR__ . '/../needs.php',
    __DIR__ . '/../post-need.php',
    __DIR__ . '/../edit-need.php',
    __DIR__ . '/../delete-need.php',
    __DIR__ . '/../my-needs.php',
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
    echo "All Stage 5 files passed syntax checks.\n";
    exit(0);
} else {
    exit(1);
}
?>