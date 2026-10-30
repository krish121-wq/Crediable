<?php
require_once 'config.php';

echo "=== Customer Ledger Debug ===\n\n";

// Check service_invoices table structure
echo "1. service_invoices table structure:\n";
try {
    $result = $conn->query('DESCRIBE service_invoices');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "   {$row['Field']} - {$row['Type']}\n";
        }
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n2. service_invoice_payments table structure:\n";
try {
    $result = $conn->query('DESCRIBE service_invoice_payments');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "   {$row['Field']} - {$row['Type']}\n";
        }
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// Check for sample data
echo "\n3. Sample data check:\n";
try {
    $result = $conn->query('SELECT COUNT(*) as count FROM service_invoices');
    $count = $result->fetch_assoc()['count'];
    echo "   Total service_invoices: {$count}\n";
    
    if ($count > 0) {
        $result = $conn->query('SELECT id, invoice_no, invoice_date, customer_id, customer_name, total_amount FROM service_invoices LIMIT 3');
        echo "   Sample invoices:\n";
        while ($row = $result->fetch_assoc()) {
            echo "     ID: {$row['id']}, Invoice: {$row['invoice_no']}, Date: {$row['invoice_date']}, Customer: {$row['customer_id']}, Amount: {$row['total_amount']}\n";
        }
    }
    
    $result = $conn->query('SELECT COUNT(*) as count FROM service_invoice_payments');
    $count = $result->fetch_assoc()['count'];
    echo "   Total service_invoice_payments: {$count}\n";
    
    if ($count > 0) {
        $result = $conn->query('SELECT id, invoice_id, customer_id, payment_amount, payment_method, created_at FROM service_invoice_payments LIMIT 3');
        echo "   Sample payments:\n";
        while ($row = $result->fetch_assoc()) {
            echo "     ID: {$row['id']}, Invoice: {$row['invoice_id']}, Customer: {$row['customer_id']}, Amount: {$row['payment_amount']}, Method: {$row['payment_method']}\n";
        }
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// Check customers table
echo "\n4. Customers check:\n";
try {
    $result = $conn->query('SELECT COUNT(*) as count FROM customers');
    $count = $result->fetch_assoc()['count'];
    echo "   Total customers: {$count}\n";
    
    if ($count > 0) {
        $result = $conn->query('SELECT id, name, phone FROM customers LIMIT 3');
        echo "   Sample customers:\n";
        while ($row = $result->fetch_assoc()) {
            echo "     ID: {$row['id']}, Name: {$row['name']}, Phone: {$row['phone']}\n";
        }
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// Test the actual query
echo "\n5. Testing ledger query with sample data:\n";
try {
    // Get a sample customer
    $result = $conn->query('SELECT id FROM customers LIMIT 1');
    if ($result && $row = $result->fetch_assoc()) {
        $customer_id = $row['id'];
        $from_date = date('Y-m-01');
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
            echo "     {$entry['transaction_date']} - {$entry['type']} - {$entry['reference_no']} - Debit: {$entry['debit_amount']} - Credit: {$entry['credit_amount']}\n";
        }
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n=== Debug Complete ===\n";
?>
