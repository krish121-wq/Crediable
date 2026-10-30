<?php
require_once 'config.php';

echo "=== Testing Fixed Customer Ledger ===\n\n";

// Simulate the exact same logic as the fixed customer_ledger.php
$selected_customer_id = 3; // Customer Raja who has transactions
$from_date = '2024-01-01';
$to_date = date('Y-m-d');
$ledger_entries = [];
$customer_info = null;
$opening_balance = 0;
$closing_balance = 0;

try {
    $customer_id = (int)$selected_customer_id;
    
    // Get customer information
    $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $customer_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$customer_info) {
        throw new Exception("Customer not found");
    }
    
    echo "Customer: {$customer_info['name']} (ID: {$customer_id})\n";
    
    // Calculate opening balance (transactions before from_date)
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
    
    $opening_balance = $opening_result['total_debit'] - $opening_result['total_credit'];
    echo "Opening Balance: {$opening_balance}\n";
    
    // Get invoices for the selected period
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
    
    echo "Found " . count($invoices) . " invoices\n";
    
    // Get payments for the selected period
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
    
    echo "Found " . count($payments) . " payments\n";
    
    // Merge invoices and payments
    $ledger_entries = array_merge($invoices, $payments);
    
    // Sort by transaction_date and created_at
    usort($ledger_entries, function($a, $b) {
        $dateCompare = strcmp($a['transaction_date'], $b['transaction_date']);
        if ($dateCompare === 0) {
            return strcmp($a['created_at'], $b['created_at']);
        }
        return $dateCompare;
    });
    
    // Calculate running balance and closing balance
    $running_balance = $opening_balance;
    foreach ($ledger_entries as &$entry) {
        $running_balance += $entry['debit_amount'] - $entry['credit_amount'];
        $entry['balance'] = $running_balance;
    }
    $closing_balance = $running_balance;
    
    echo "\nLedger Entries:\n";
    echo "Date       | Type    | Reference   | Debit     | Credit    | Balance   | Description\n";
    echo "-----------|---------|-------------|-----------|-----------|-----------|------------\n";
    
    foreach ($ledger_entries as $entry) {
        printf("%s | %-7s | %-11s | %-9.2f | %-9.2f | %-9.2f | %s\n",
            $entry['transaction_date'],
            $entry['type'],
            $entry['reference_no'],
            $entry['debit_amount'],
            $entry['credit_amount'],
            abs($entry['balance']),
            $entry['description']
        );
    }
    
    echo "\nSummary:\n";
    echo "Opening Balance: {$opening_balance}\n";
    echo "Closing Balance: {$closing_balance}\n";
    echo "Total Entries: " . count($ledger_entries) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
?>
