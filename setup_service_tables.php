<?php
require_once 'config.php';

// SQL statements to create tables
$sql_statements = [
    // Service Invoices Main Table
    "CREATE TABLE IF NOT EXISTS service_invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT,
        customer_name VARCHAR(255) NOT NULL,
        customer_phone VARCHAR(50) NOT NULL,
        customer_address TEXT NOT NULL,
        shipping_address TEXT NOT NULL,
        subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
        discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        gst_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        payment_method VARCHAR(50),
        paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        created_by INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_customer_id (customer_id),
        INDEX idx_created_by (created_by),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Service Invoice Items Table
    "CREATE TABLE IF NOT EXISTS service_invoice_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        service_name VARCHAR(255) NOT NULL,
        sac_code VARCHAR(50),
        quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
        unit VARCHAR(50),
        rate DECIMAL(12,2) NOT NULL DEFAULT 0,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        discount_type ENUM('amount', 'percentage') DEFAULT 'amount',
        discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
        customer_state VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (invoice_id) REFERENCES service_invoices(id) ON DELETE CASCADE,
        INDEX idx_invoice_id (invoice_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Add state column to company_details if it doesn't exist
    "ALTER TABLE company_details ADD COLUMN IF NOT EXISTS state VARCHAR(100) AFTER company_gst",
    
    // Create customers table if it doesn't exist
    "CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(255),
        address TEXT,
        state VARCHAR(100),
        city VARCHAR(100),
        pincode VARCHAR(10),
        gstin VARCHAR(15),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_name (name),
        INDEX idx_phone (phone)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

echo "Setting up service invoice tables...\n";

foreach ($sql_statements as $sql) {
    try {
        if ($conn->query($sql)) {
            echo "✓ Query executed successfully\n";
        } else {
            echo "✗ Error: " . $conn->error . "\n";
        }
    } catch (Exception $e) {
        echo "✗ Exception: " . $e->getMessage() . "\n";
    }
}

echo "\nSetup completed!\n";
echo "You can now access the service invoice functionality at: service_invoice.php\n";
?>
