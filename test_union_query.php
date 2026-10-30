<?php
require_once 'config.php';

echo "=== Testing Complete UNION Query ===\n\n";

$customer_id = 3;
$from_date = '2024-01-01';
$to_date = date('Y-m-d');

echo "Testing with customer_id: {$customer_id}, from: {$from_date}, to: {$to_date}\n";

// Test the complete UNION query
try {
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
    
    echo "Found " . count($entries) . " total entries:\n";
    foreach ($entries as $entry) {
        echo "  {$entry['transaction_date']} - {$entry['type']} - {$entry['reference_no']} - Debit: {$entry['debit_amount']} - Credit: {$entry['credit_amount']} - {$entry['description']}\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// Test each part separately
echo "\n2. Testing invoice part separately:\n";
try {
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
        ORDER BY invoice_date ASC
    ");
    $stmt->bind_param("iss", $customer_id, $from_date, $to_date);
    $stmt->execute();
    $invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo "   Found " . count($invoices) . " invoices:\n";
    foreach ($invoices as $invoice) {
        echo "     {$invoice['transaction_date']} - {$invoice['type']} - {$invoice['reference_no']} - {$invoice['debit_amount']} - {$invoice['description']}\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n3. Testing payment part separately:\n";
try {
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
    foreach ($payments as $payment) {
        echo "     {$payment['transaction_date']} - {$payment['type']} - {$payment['reference_no']} - {$payment['credit_amount']} - {$payment['description']}\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
?>
