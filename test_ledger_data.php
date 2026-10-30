<?php
require_once 'config.php';

echo "=== Testing Ledger Data ===\n\n";

// Check existing service invoices
echo "1. Current service invoices:\n";
try {
    $result = $conn->query('SELECT id, invoice_no, invoice_date, customer_id, customer_name, total_amount, created_at FROM service_invoices');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "   ID: {$row['id']}, Invoice: {$row['invoice_no']}, Date: {$row['invoice_date']}, Customer: {$row['customer_id']}, Name: {$row['customer_name']}, Amount: {$row['total_amount']}, Created: {$row['created_at']}\n";
        }
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// Check existing payments
echo "\n2. Current service invoice payments:\n";
try {
    $result = $conn->query('SELECT id, invoice_id, customer_id, payment_amount, payment_method, created_at FROM service_invoice_payments');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "   ID: {$row['id']}, Invoice: {$row['invoice_id']}, Customer: {$row['customer_id']}, Amount: {$row['payment_amount']}, Method: {$row['payment_method']}, Created: {$row['created_at']}\n";
        }
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// Check customers
echo "\n3. Available customers:\n";
try {
    $result = $conn->query('SELECT id, name, phone FROM customers');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "   ID: {$row['id']}, Name: {$row['name']}, Phone: {$row['phone']}\n";
        }
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// Test the ledger query with actual data
echo "\n4. Testing ledger query:\n";
try {
    // Get first customer for testing
    $result = $conn->query('SELECT id FROM customers LIMIT 1');
    if ($result && $row = $result->fetch_assoc()) {
        $customer_id = $row['id'];
        $from_date = '2024-01-01';  // Use a wider date range
        $to_date = date('Y-m-d');
        
        echo "   Testing with customer_id: {$customer_id}, from: {$from_date}, to: {$to_date}\n";
        
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
        
        echo "   Opening balance - Debit: {$opening_result['total_debit']}, Credit: {$opening_result['total_credit']}\n";
        
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
        
        echo "   Found " . count($entries) . " ledger entries:\n";
        foreach ($entries as $entry) {
            echo "     {$entry['transaction_date']} - {$entry['type']} - {$entry['reference_no']} - Debit: {$entry['debit_amount']} - Credit: {$entry['credit_amount']} - {$entry['description']}\n";
        }
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
?>
