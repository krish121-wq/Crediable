<?php
$content = file_get_contents('received_payments.php');

// String replacements
$content = str_replace('received_payments', 'made_payments', $content);
$content = str_replace('payment_receipts', 'payment_out_receipts', $content);
$content = str_replace('Payment-In', 'Payment-Out', $content);
$content = str_replace('customer_name', 'supplier_name', $content);
$content = str_replace('customers', 'suppliers', $content);
$content = str_replace('invoices', 'purchases', $content);
$content = str_replace('invoice_no', 'entry_no', $content);
$content = str_replace('invoice_id', 'purchase_id', $content);
$content = str_replace("ROUND(i.total_amount", "ROUND(i.net_amount", $content);
$content = str_replace("i.total_amount", "i.net_amount", $content);
$content = str_replace('save_payment_in', 'save_payment_out', $content);
$content = str_replace('submitPaymentIn', 'submitPaymentOut', $content);
$content = str_replace('paymentInModal', 'paymentOutModal', $content);
$content = str_replace('paymentInForm', 'paymentOutForm', $content);
$content = str_replace('received_payments.php', 'payment_out.php', $content);
$content = str_replace('Received Amount', 'Paid Amount', $content);
$content = str_replace('totalReceived', 'totalPaid', $content);
$content = str_replace('received_amount', 'paid_amount', $content);
$content = str_replace('Received', 'Paid', $content);

// Remove the UPDATE suppliers SET current_balance... lines since suppliers table has no current_balance
$content = preg_replace('/\\/\\/ Deduct new balance.*?\\$cstmt->close\\(\\);/s', '// Supplier balance is dynamically calculated, no need to update a column here.', $content);
$content = preg_replace('/\\/\\/ Restore customer balance.*?WHERE name = \'\$cname\'\"\\);/s', '// Restore supplier balance (dynamically calculated)', $content);
$content = preg_replace('/\\/\\/ Revert balance.*?WHERE name = \'\$old_cname\'\"\\);/s', '// Revert balance (dynamically calculated)', $content);

// Fix the CREATE TABLE for made_payments
$made_payments_create = "
    \$conn->query(\"CREATE TABLE IF NOT EXISTS made_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_id INT NOT NULL,
        receipt_id INT NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        created_by INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4\");
";
$content = preg_replace('/\\$conn->query\\("ALTER TABLE made_payments ADD COLUMN IF NOT EXISTS receipt_id INT DEFAULT NULL"\\);/', $made_payments_create, $content);

file_put_contents('payment_out.php', $content);
echo "payment_out.php created successfully.\n";
