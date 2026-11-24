<?php
// Stage 6 test suite – syntax checks for Checkout & Order Management pages
$files = [
    __DIR__ . '/../checkout.php',
    __DIR__ . '/../order-details.php',
    __DIR__ . '/../my-orders.php',
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
    echo "All Stage 6 files passed syntax checks.\n";
    exit(0);
} else {
    exit(1);
}
?>