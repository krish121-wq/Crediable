<?php
require_once 'config.php';

echo "=== Testing UNION Fix ===\n\n";

$customer_id = 3;
$from_date = '2024-01-01';
$to_date = date('Y-m-d');

echo "Testing with customer_id: {$customer_id}, from: {$from_date}, to: {$to_date}\n";

// Test UNION with explicit CAST to ensure data type compatibility
try {
    $stmt = $conn->prepare("
        SELECT * FROM (
            SELECT 
                'invoice' as type,
                id,
                invoice_no as reference_no,
                CAST(invoice_date AS DATE) as transaction_date,
                CAST(total_amount AS DECIMAL(12,2)) as debit_amount,
                CAST(0 AS DECIMAL(12,2)) as credit_amount,
                CONCAT('Invoice #', invoice_no) as description,
                created_at
            FROM service_invoices 
            WHERE customer_id = ? AND invoice_date BETWEEN ? AND ?
            
            UNION ALL
            
            SELECT 
                'payment' as type,
                id,
                CONCAT('PAY', LPAD(id, 6, '0')) as reference_no,
                CAST(DATE(created_at) AS DATE) as transaction_date,
                CAST(0 AS DECIMAL(12,2)) as debit_amount,
                CAST(payment_amount AS DECIMAL(12,2)) as credit_amount,
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
    
    echo "Found " . count($entries) . " total entries (with CAST):\n";
    foreach ($entries as $entry) {
        echo "  {$entry['transaction_date']} - {$entry['type']} - {$entry['reference_no']} - Debit: {$entry['debit_amount']} - Credit: {$entry['credit_amount']} - {$entry['description']}\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// Test with simpler UNION - maybe the issue is with the complex expressions
echo "\n2. Testing simpler UNION:\n";
try {
    $stmt = $conn->prepare("
        SELECT type, id, reference_no, transaction_date, debit_amount, credit_amount, description, created_at FROM (
            SELECT 
                'invoice' as type,
                id,
                invoice_no as reference_no,
                invoice_date as transaction_date,
                total_amount as debit_amount,
                0 as credit_amount,
                'Invoice' as description,
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
                'Payment' as description,
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
    
    echo "Found " . count($entries) . " total entries (simpler):\n";
    foreach ($entries as $entry) {
        echo "  {$entry['transaction_date']} - {$entry['type']} - {$entry['reference_no']} - Debit: {$entry['debit_amount']} - Credit: {$entry['credit_amount']} - {$entry['description']}\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// Test with different approach - using separate queries and then merge in PHP
echo "\n3. Testing PHP merge approach:\n";
try {
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
    
    // Merge and sort
    $all_entries = array_merge($invoices, $payments);
    
    // Sort by transaction_date and created_at
    usort($all_entries, function($a, $b) {
        $dateCompare = strcmp($a['transaction_date'], $b['transaction_date']);
        if ($dateCompare === 0) {
            return strcmp($a['created_at'], $b['created_at']);
        }
        return $dateCompare;
    });
    
    echo "Found " . count($all_entries) . " total entries (PHP merge):\n";
    foreach ($all_entries as $entry) {
        echo "  {$entry['transaction_date']} - {$entry['type']} - {$entry['reference_no']} - Debit: {$entry['debit_amount']} - Credit: {$entry['credit_amount']} - {$entry['description']}\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
?>
