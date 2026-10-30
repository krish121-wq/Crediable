<?php
require_once 'config.php';

echo "=== Testing with Correct Customer ===\n\n";

// Test with customer_id: 3 (the customer who has the invoice)
$customer_id = 3;
$from_date = '2024-01-01';
$to_date = date('Y-m-d');

echo "Testing with customer_id: {$customer_id}, from: {$from_date}, to: {$to_date}\n";

// Test opening balance query
$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN type = 'invoice' THEN total_amount ELSE 0 END), 0) as total_debit,
        COALESCE(SUM(CASE WHEN type = 'payment' THEN payment_amount ELSE 0 END), 0) as total_credit
    FROM (
        SELECT 'invoice' as type, total_amount, 0 as payment_amount, invoice_date as transaction_date
        FROM service_invoices 
        WHERE customer_id = ? AND invoice_date < ?
        
        UNION ALL
        
        SELECT 'payment' as type, 0 as total_amount, payment_amount, DATE(created_at) as transaction_date
        FROM service_invoice_payments 
        WHERE customer_id = ? AND DATE(created_at) < ?
    ) as transactions
");
$stmt->bind_param("isis", $customer_id, $from_date, $customer_id, $from_date);
$stmt->execute();
$opening_result = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo "Opening balance - Debit: {$opening_result['total_debit']}, Credit: {$opening_result['total_credit']}\n";

// Test ledger entries query
$stmt = $conn->prepare("
    SELECT * FROM (
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
        
        UNION ALL
        
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
    ) as combined_entries
    ORDER BY transaction_date ASC, created_at ASC
");
$stmt->bind_param("isisis", $customer_id, $from_date, $to_date, $customer_id, $from_date, $to_date);
$stmt->execute();
$entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo "Found " . count($entries) . " ledger entries:\n";
foreach ($entries as $entry) {
    echo "  {$entry['transaction_date']} - {$entry['type']} - {$entry['reference_no']} - Debit: {$entry['debit_amount']} - Credit: {$entry['credit_amount']} - {$entry['description']}\n";
}

// Calculate running balance
$running_balance = $opening_result['total_debit'] - $opening_result['total_credit'];
echo "\nRunning balance calculation:\n";
echo "Starting balance: {$running_balance}\n";
foreach ($entries as $entry) {
    $running_balance += $entry['debit_amount'] - $entry['credit_amount'];
    echo "After {$entry['type']} {$entry['reference_no']}: {$running_balance}\n";
}

echo "\n=== Test Complete ===\n";
?>
