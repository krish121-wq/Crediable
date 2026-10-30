<?php
require_once 'config.php';

echo "=== Debugging Invoice Date Issue ===\n\n";

// Check the specific invoice data
echo "1. Detailed invoice data:\n";
try {
    $result = $conn->query('SELECT id, invoice_no, invoice_date, customer_id, customer_name, total_amount, created_at FROM service_invoices WHERE customer_id = 3');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "   ID: {$row['id']}\n";
            echo "   Invoice No: '{$row['invoice_no']}'\n";
            echo "   Invoice Date: '{$row['invoice_date']}'\n";
            echo "   Customer ID: {$row['customer_id']}\n";
            echo "   Customer Name: '{$row['customer_name']}'\n";
            echo "   Total Amount: {$row['total_amount']}\n";
            echo "   Created At: '{$row['created_at']}'\n";
            echo "   Invoice Date is NULL: " . (is_null($row['invoice_date']) ? 'YES' : 'NO') . "\n";
            echo "   Invoice Date is empty: " . (empty($row['invoice_date']) ? 'YES' : 'NO') . "\n";
            echo "\n";
        }
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// Test the invoice query directly
echo "2. Testing invoice query directly:\n";
try {
    $customer_id = 3;
    $from_date = '2024-01-01';
    $to_date = date('Y-m-d');
    
    echo "   Query conditions: customer_id = {$customer_id}, invoice_date BETWEEN '{$from_date}' AND '{$to_date}'\n";
    
    $stmt = $conn->prepare("
        SELECT 
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
        echo "     {$invoice['transaction_date']} - {$invoice['reference_no']} - {$invoice['debit_amount']} - {$invoice['description']}\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// Test without date filter
echo "\n3. Testing without date filter:\n";
try {
    $customer_id = 3;
    
    $stmt = $conn->prepare("
        SELECT 
            id,
            invoice_no as reference_no,
            invoice_date as transaction_date,
            total_amount as debit_amount,
            0 as credit_amount,
            CONCAT('Invoice #', invoice_no) as description,
            created_at
        FROM service_invoices 
        WHERE customer_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo "   Found " . count($invoices) . " invoices (no date filter):\n";
    foreach ($invoices as $invoice) {
        echo "     Created: {$invoice['created_at']}, Invoice Date: '{$invoice['transaction_date']}', Ref: {$invoice['reference_no']}, Amount: {$invoice['debit_amount']}\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// Update invoice_date if it's NULL
echo "\n4. Updating invoice_date if needed:\n";
try {
    $update_sql = "UPDATE service_invoices SET 
        invoice_date = DATE(created_at)
    WHERE invoice_date IS NULL OR invoice_date = '' OR invoice_date = '0000-00-00'";
    
    if ($conn->query($update_sql)) {
        $affected_rows = $conn->affected_rows;
        echo "   Updated {$affected_rows} invoices with invoice_date from created_at\n";
    } else {
        echo "   Error updating invoice_date: " . $conn->error . "\n";
    }
} catch (Exception $e) {
    echo "   Exception: " . $e->getMessage() . "\n";
}

echo "\n=== Debug Complete ===\n";
?>
