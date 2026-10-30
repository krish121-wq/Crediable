<?php
require_once 'config.php';

echo "=== Setting up Customer Ledger Tables ===\n\n";

// Create service_invoice_payments table if it doesn't exist
echo "1. Creating service_invoice_payments table...\n";
$sql = "CREATE TABLE IF NOT EXISTS service_invoice_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    customer_id INT NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_notes TEXT,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_customer_id (customer_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    if ($conn->query($sql)) {
        echo "   ✓ service_invoice_payments table created successfully\n";
    } else {
        echo "   ✗ Error creating service_invoice_payments table: " . $conn->error . "\n";
    }
} catch (Exception $e) {
    echo "   ✗ Exception: " . $e->getMessage() . "\n";
}

// Add missing columns to service_invoices table
echo "\n2. Adding missing columns to service_invoices table...\n";
$alter_statements = [
    "ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS invoice_no VARCHAR(50) AFTER id",
    "ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS invoice_date DATE AFTER invoice_no",
    "ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS delivery_note TEXT AFTER invoice_date",
    "ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS payment_terms VARCHAR(255) AFTER delivery_note",
    "ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS reference_no VARCHAR(50) AFTER payment_terms",
    "ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS buyer_order_no VARCHAR(50) AFTER reference_no",
    "ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS dated DATE AFTER buyer_order_no",
    "ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS dispatch_doc_no VARCHAR(50) AFTER dated",
    "ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS delivery_note_date DATE AFTER dispatch_doc_no",
    "ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS dispatched_through VARCHAR(100) AFTER delivery_note_date",
    "ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS destination TEXT AFTER dispatched_through",
    "ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS terms_of_delivery TEXT AFTER destination"
];

foreach ($alter_statements as $sql) {
    try {
        if ($conn->query($sql)) {
            echo "   ✓ Column added successfully\n";
        } else {
            echo "   ✗ Error adding column: " . $conn->error . "\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Exception: " . $e->getMessage() . "\n";
    }
}

// Update existing invoices to have default invoice_no and invoice_date if they're missing
echo "\n3. Updating existing invoices with default values...\n";
try {
    $update_sql = "UPDATE service_invoices SET 
        invoice_no = CONCAT('INV', LPAD(id, 6, '0')),
        invoice_date = DATE(created_at)
    WHERE invoice_no IS NULL OR invoice_no = ''";
    
    if ($conn->query($update_sql)) {
        $affected_rows = $conn->affected_rows;
        echo "   ✓ Updated {$affected_rows} invoices with default invoice_no and invoice_date\n";
    } else {
        echo "   ✗ Error updating invoices: " . $conn->error . "\n";
    }
} catch (Exception $e) {
    echo "   ✗ Exception: " . $e->getMessage() . "\n";
}

// Check final table structures
echo "\n4. Final table verification:\n";
echo "   service_invoices columns:\n";
try {
    $result = $conn->query('DESCRIBE service_invoices');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "     {$row['Field']} - {$row['Type']}\n";
        }
    }
} catch (Exception $e) {
    echo "     Error: " . $e->getMessage() . "\n";
}

echo "\n   service_invoice_payments columns:\n";
try {
    $result = $conn->query('DESCRIBE service_invoice_payments');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "     {$row['Field']} - {$row['Type']}\n";
        }
    }
} catch (Exception $e) {
    echo "     Error: " . $e->getMessage() . "\n";
}

// Check sample data
echo "\n5. Sample data check:\n";
try {
    $result = $conn->query('SELECT COUNT(*) as count FROM service_invoices');
    $count = $result->fetch_assoc()['count'];
    echo "   Total service_invoices: {$count}\n";
    
    $result = $conn->query('SELECT COUNT(*) as count FROM service_invoice_payments');
    $count = $result->fetch_assoc()['count'];
    echo "   Total service_invoice_payments: {$count}\n";
    
    $result = $conn->query('SELECT COUNT(*) as count FROM customers');
    $count = $result->fetch_assoc()['count'];
    echo "   Total customers: {$count}\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n=== Setup Complete ===\n";
echo "You can now access the customer ledger at: customer_ledger.php\n";
?>
