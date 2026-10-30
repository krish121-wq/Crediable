<?php
require_once 'config.php';

echo "=== Debugging Reference Issue ===\n\n";

$customer_id = 3;
$from_date = '2024-01-01';
$to_date = date('Y-m-d');

// Get invoices
$stmt = $conn->prepare("
    SELECT 
        'invoice' as type,
        id,
        invoice_no as reference_no,
        invoice_date as transaction_date,
        total_amount as debit_amount,
        0 as credit_amount,
        CONCAT('Invoice #', invoice_no) as description,
        created_at
    FROM service_invoices 
    WHERE customer_id = ? AND invoice_date BETWEEN ? AND ?
    ORDER BY invoice_date ASC, created_at ASC
");
$stmt->bind_param("iss", $customer_id, $from_date, $to_date);
$stmt->execute();
$invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get payments
$stmt = $conn->prepare("
    SELECT 
        'payment' as type,
        id,
        CONCAT('PAY', LPAD(id, 6, '0')) as reference_no,
        DATE(created_at) as transaction_date,
        0 as debit_amount,
        payment_amount as credit_amount,
        CONCAT('Payment - ', payment_method) as description,
        created_at
    FROM service_invoice_payments 
    WHERE customer_id = ? AND DATE(created_at) BETWEEN ? AND ?
    ORDER BY created_at ASC
");
$stmt->bind_param("iss", $customer_id, $from_date, $to_date);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo "Original arrays:\n";
echo "Invoices: " . count($invoices) . "\n";
echo "Payments: " . count($payments) . "\n\n";

// Test merge without reference
$ledger_entries_no_ref = array_merge($invoices, $payments);

echo "After merge (no reference):\n";
foreach ($ledger_entries_no_ref as $i => $entry) {
    echo "  " . ($i + 1) . ". {$entry['type']} - {$entry['reference_no']} - Debit: {$entry['debit_amount']} - Credit: {$entry['credit_amount']}\n";
}

// Test sort without reference
usort($ledger_entries_no_ref, function($a, $b) {
    $dateCompare = strcmp($a['transaction_date'], $b['transaction_date']);
    if ($dateCompare === 0) {
        return strcmp($a['created_at'], $b['created_at']);
    }
    return $dateCompare;
});

echo "\nAfter sort (no reference):\n";
foreach ($ledger_entries_no_ref as $i => $entry) {
    echo "  " . ($i + 1) . ". {$entry['type']} - {$entry['reference_no']} - Debit: {$entry['debit_amount']} - Credit: {$entry['credit_amount']}\n";
}

// Test balance calculation WITHOUT reference
$running_balance = 0;
$ledger_entries_with_balance = [];
foreach ($ledger_entries_no_ref as $entry) {
    $running_balance += $entry['debit_amount'] - $entry['credit_amount'];
    $new_entry = $entry;
    $new_entry['balance'] = $running_balance;
    $ledger_entries_with_balance[] = $new_entry;
}

echo "\nAfter balance calculation (no reference):\n";
foreach ($ledger_entries_with_balance as $i => $entry) {
    echo "  " . ($i + 1) . ". {$entry['type']} - {$entry['reference_no']} - Debit: {$entry['debit_amount']} - Credit: {$entry['credit_amount']} - Balance: {$entry['balance']}\n";
}

// Now test with reference (the problematic way)
echo "\n=== Testing WITH reference (original way) ===\n";
$ledger_entries_with_ref = array_merge($invoices, $payments);
usort($ledger_entries_with_ref, function($a, $b) {
    $dateCompare = strcmp($a['transaction_date'], $b['transaction_date']);
    if ($dateCompare === 0) {
        return strcmp($a['created_at'], $b['created_at']);
    }
    return $dateCompare;
});

$running_balance = 0;
foreach ($ledger_entries_with_ref as &$entry) {  // This is the problematic line
    $running_balance += $entry['debit_amount'] - $entry['credit_amount'];
    $entry['balance'] = $running_balance;
}

echo "After balance calculation (WITH reference):\n";
foreach ($ledger_entries_with_ref as $i => $entry) {
    echo "  " . ($i + 1) . ". {$entry['type']} - {$entry['reference_no']} - Debit: {$entry['debit_amount']} - Credit: {$entry['credit_amount']} - Balance: {$entry['balance']}\n";
}

echo "\n=== Debug Complete ===\n";
?>
