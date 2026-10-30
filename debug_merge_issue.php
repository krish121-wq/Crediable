<?php
require_once 'config.php';

echo "=== Debugging Merge Issue ===\n\n";

$customer_id = 3;
$from_date = '2024-01-01';
$to_date = date('Y-m-d');

// Get invoices
echo "1. Getting invoices:\n";
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

echo "   Found " . count($invoices) . " invoices:\n";
foreach ($invoices as $i => $invoice) {
    echo "     " . ($i + 1) . ". Type: '{$invoice['type']}', Ref: '{$invoice['reference_no']}', Date: '{$invoice['transaction_date']}', Debit: {$invoice['debit_amount']}, Credit: {$invoice['credit_amount']}, Created: '{$invoice['created_at']}'\n";
}

// Get payments
echo "\n2. Getting payments:\n";
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

echo "   Found " . count($payments) . " payments:\n";
foreach ($payments as $i => $payment) {
    echo "     " . ($i + 1) . ". Type: '{$payment['type']}', Ref: '{$payment['reference_no']}', Date: '{$payment['transaction_date']}', Debit: {$payment['debit_amount']}, Credit: {$payment['credit_amount']}, Created: '{$payment['created_at']}'\n";
}

// Merge invoices and payments
echo "\n3. Merging arrays:\n";
$ledger_entries = array_merge($invoices, $payments);
echo "   After merge: " . count($ledger_entries) . " total entries\n";

foreach ($ledger_entries as $i => $entry) {
    echo "     " . ($i + 1) . ". Type: '{$entry['type']}', Ref: '{$entry['reference_no']}', Date: '{$entry['transaction_date']}', Debit: {$entry['debit_amount']}, Credit: {$entry['credit_amount']}\n";
}

// Sort by transaction_date and created_at
echo "\n4. Sorting entries:\n";
usort($ledger_entries, function($a, $b) {
    $dateCompare = strcmp($a['transaction_date'], $b['transaction_date']);
    if ($dateCompare === 0) {
        return strcmp($a['created_at'], $b['created_at']);
    }
    return $dateCompare;
});

echo "   After sorting: " . count($ledger_entries) . " entries\n";
foreach ($ledger_entries as $i => $entry) {
    echo "     " . ($i + 1) . ". Type: '{$entry['type']}', Ref: '{$entry['reference_no']}', Date: '{$entry['transaction_date']}', Debit: {$entry['debit_amount']}, Credit: {$entry['credit_amount']}\n";
}

// Calculate running balance
echo "\n5. Calculating balances:\n";
$running_balance = 0;
foreach ($ledger_entries as &$entry) {
    $running_balance += $entry['debit_amount'] - $entry['credit_amount'];
    $entry['balance'] = $running_balance;
    echo "     After {$entry['type']} {$entry['reference_no']}: Balance = {$running_balance}\n";
}

echo "\n=== Debug Complete ===\n";
?>
