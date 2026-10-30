<?php
require_once 'config.php';

echo "=== Debugging Payment Query Issue ===\n\n";

$customer_id = 3;
$from_date = '2024-01-01';
$to_date = date('Y-m-d');

// First, let's check what's in the service_invoice_payments table directly
echo "1. Raw service_invoice_payments data:\n";
try {
    $result = $conn->query('SELECT * FROM service_invoice_payments WHERE customer_id = ' . $customer_id);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "   ID: {$row['id']}, Invoice: {$row['invoice_id']}, Customer: {$row['customer_id']}, Amount: {$row['payment_amount']}, Method: {$row['payment_method']}, Created: '{$row['created_at']}'\n";
            echo "   DATE(created_at): " . date('Y-m-d', strtotime($row['created_at'])) . "\n";
            echo "   From Date: {$from_date}, To Date: {$to_date}\n";
            echo "   Is between dates: " . ((date('Y-m-d', strtotime($row['created_at'])) >= $from_date && date('Y-m-d', strtotime($row['created_at'])) <= $to_date) ? 'YES' : 'NO') . "\n\n";
        }
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// Now test the payment query step by step
echo "2. Testing payment query:\n";
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
    foreach ($payments as $i => $payment) {
        echo "     " . ($i + 1) . ". Type: '{$payment['type']}', Ref: '{$payment['reference_no']}', Date: '{$payment['transaction_date']}', Debit: {$payment['debit_amount']}, Credit: {$payment['credit_amount']}, Description: '{$payment['description']}', Created: '{$payment['created_at']}'\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// Test the invoice query too
echo "\n3. Testing invoice query:\n";
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
        ORDER BY invoice_date ASC, created_at ASC
    ");
    $stmt->bind_param("iss", $customer_id, $from_date, $to_date);
    $stmt->execute();
    $invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo "   Found " . count($invoices) . " invoices:\n";
    foreach ($invoices as $i => $invoice) {
        echo "     " . ($i + 1) . ". Type: '{$invoice['type']}', Ref: '{$invoice['reference_no']}', Date: '{$invoice['transaction_date']}', Debit: {$invoice['debit_amount']}, Credit: {$invoice['credit_amount']}, Description: '{$invoice['description']}', Created: '{$invoice['created_at']}'\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// Now test the merge
echo "\n4. Testing merge:\n";
$all_entries = array_merge($invoices, $payments);
echo "   After merge: " . count($all_entries) . " entries\n";
foreach ($all_entries as $i => $entry) {
    echo "     " . ($i + 1) . ". Type: '{$entry['type']}', Ref: '{$entry['reference_no']}', Date: '{$entry['transaction_date']}', Debit: {$entry['debit_amount']}, Credit: {$entry['credit_amount']}\n";
}

// Test sorting
echo "\n5. Testing sorting:\n";
usort($all_entries, function($a, $b) {
    $dateCompare = strcmp($a['transaction_date'], $b['transaction_date']);
    if ($dateCompare === 0) {
        return strcmp($a['created_at'], $b['created_at']);
    }
    return $dateCompare;
});

echo "   After sorting: " . count($all_entries) . " entries\n";
foreach ($all_entries as $i => $entry) {
    echo "     " . ($i + 1) . ". Type: '{$entry['type']}', Ref: '{$entry['reference_no']}', Date: '{$entry['transaction_date']}', Debit: {$entry['debit_amount']}, Credit: {$entry['credit_amount']}\n";
}

echo "\n=== Debug Complete ===\n";
?>
