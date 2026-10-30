<?php
require_once 'config.php';

try {
    // Create debit_notes table
    $sql1 = "CREATE TABLE IF NOT EXISTS `debit_notes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `debit_note_no` varchar(100) NOT NULL,
        `return_id` int(11) DEFAULT NULL,
        `original_bill_no` varchar(50) NOT NULL,
        `supplier_name` varchar(255) NOT NULL,
        `invoice_no` varchar(100) DEFAULT NULL,
        `return_date` date NOT NULL,
        `refund_type` varchar(50) DEFAULT 'Product Return',
        `return_status` varchar(50) DEFAULT 'Processed',
        `notes` text DEFAULT NULL,
        `total_amount` decimal(10,2) DEFAULT 0.00,
        `created_by` int(11) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `debit_note_no` (`debit_note_no`),
        KEY `original_bill_no` (`original_bill_no`),
        KEY `created_by` (`created_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql1) === TRUE) {
        echo "Table 'debit_notes' created successfully.\n";
    } else {
        echo "Error creating debit_notes table: " . $conn->error . "\n";
    }
    
    // Create debit_note_items table
    $sql2 = "CREATE TABLE IF NOT EXISTS `debit_note_items` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `debit_note_id` int(11) NOT NULL,
        `product_id` int(11) NOT NULL,
        `product_name` varchar(255) NOT NULL,
        `batch_no` varchar(100) DEFAULT NULL,
        `expiry_date` date DEFAULT NULL,
        `quantity_purchased` int(11) NOT NULL,
        `unit_price` decimal(10,2) NOT NULL,
        `gst_rate` decimal(5,2) DEFAULT 0.00,
        `return_quantity` int(11) NOT NULL,
        `reason` varchar(255) DEFAULT NULL,
        `amount` decimal(10,2) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `debit_note_id` (`debit_note_id`),
        KEY `product_id` (`product_id`),
        FOREIGN KEY (`debit_note_id`) REFERENCES `debit_notes`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql2) === TRUE) {
        echo "Table 'debit_note_items' created successfully.\n";
    } else {
        echo "Error creating debit_note_items table: " . $conn->error . "\n";
    }
    
    echo "Debit note tables setup completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
