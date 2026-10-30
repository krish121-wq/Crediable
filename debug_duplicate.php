<?php
require_once 'config.php';

echo "=== Debugging Duplicate Issue ===\n\n";

// Check all service_invoice_payments data
echo "1. All service_invoice_payments data:\n";
try {
    $result = $conn->query('SELECT * FROM service_invoice_payments');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "   ID: {$row['id']}, Invoice: {$row['invoice_id']}, Customer: {$row['customer_id']}, Amount: {$row['payment_amount']}, Method: {$row['payment_method']}, Created: {$row['created_at']}\n";
        }
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// Check all service_invoices data
echo "\n2. All service_invoices data:\n";
try {
    $result = $conn->query('SELECT * FROM service_invoices');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "   ID: {$row['id']}, Invoice: {$row['invoice_no']}, Customer: {$row['customer_id']}, Amount: {$row['total_amount']}, Created: {$row['created_at']}\n";
        }
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// Test payment query specifically
echo "\n3. Testing payment query for customer 3:\n";
try {
    $customer_id = 3;
    $from_date = '2024-01-01';
    $to_date = date('Y-m-d');
    
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

// Test the exact query from customer_ledger.php
echo "\n4. Testing exact customer_ledger.php query:\n";
try {
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
    
    echo "   Invoices found: " . count($invoices) . "\n";
    
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
    
    echo "   Payments found: " . count($payments) . "\n";
    
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
    
    echo "   Total entries after merge: " . count($all_entries) . "\n";
    foreach ($all_entries as $entry) {
        echo "     {$entry['transaction_date']} - {$entry['type']} - {$entry['reference_no']} - {$entry['debit_amount']} - {$entry['credit_amount']} - {$entry['description']}\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n=== Debug Complete ===\n";
?>
