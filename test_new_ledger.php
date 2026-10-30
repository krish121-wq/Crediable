<?php
// Simulate POST request for the new ledger
$_POST['customer_id'] = 3;
$_POST['from_date'] = '2024-01-01';
$_POST['to_date'] = date('Y-m-d');
$_SERVER['REQUEST_METHOD'] = 'POST';

echo "=== Testing NEW Customer Ledger ===\n\n";

// Include the new ledger file
include 'customer_ledger_new.php';

echo "\n=== Test Complete ===\n";
?>
