<?php
session_start();

// Simulate a logged-in admin user
$_SESSION['user_id'] = 1;
$_SESSION['user_type'] = 'admin';

// Test the fetch_bill_details.php
$_POST['bill_id'] = 'BILL-20250905-101';
$_SERVER['REQUEST_METHOD'] = 'POST';

echo "Testing session and bill search...\n";
echo "Session user_id: " . ($_SESSION['user_id'] ?? 'Not set') . "\n";
echo "Session user_type: " . ($_SESSION['user_type'] ?? 'Not set') . "\n";
echo "Bill ID: " . $_POST['bill_id'] . "\n\n";

// Capture output
ob_start();
include 'fetch_bill_details.php';
$output = ob_get_clean();

echo "Response from fetch_bill_details.php:\n";
echo $output;
?>
