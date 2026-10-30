<?php
require_once 'config.php';

try {
    // Create delivery_challans table
    $sql1 = "CREATE TABLE IF NOT EXISTS `delivery_challans` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `supplier_name` varchar(255) NOT NULL,
        `challan_no` varchar(100) NOT NULL,
        `challan_date` date NOT NULL,
        `invoice_no` varchar(100) DEFAULT NULL,
        `party_no` varchar(100) DEFAULT NULL,
        `additional_notes` text DEFAULT NULL,
        `created_by` int(11) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `challan_no` (`challan_no`),
        KEY `created_by` (`created_by`),
        KEY `challan_date` (`challan_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql1) === TRUE) {
        echo "Table 'delivery_challans' created successfully.\n";
    } else {
        echo "Error creating delivery_challans table: " . $conn->error . "\n";
    }
    
    // Create delivery_challan_items table
    $sql2 = "CREATE TABLE IF NOT EXISTS `delivery_challan_items` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `challan_id` int(11) NOT NULL,
        `product_name` varchar(255) NOT NULL,
        `batch_no` varchar(100) DEFAULT NULL,
        `expiry_date` date DEFAULT NULL,
        `quantity` int(11) NOT NULL,
        `unit_price` decimal(10,2) NOT NULL,
        `gst_rate` decimal(5,2) DEFAULT 0.00,
        `amount` decimal(10,2) NOT NULL,
        `gst_amount` decimal(10,2) DEFAULT 0.00,
        `total_amount` decimal(10,2) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `challan_id` (`challan_id`),
        FOREIGN KEY (`challan_id`) REFERENCES `delivery_challans`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql2) === TRUE) {
        echo "Table 'delivery_challan_items' created successfully.\n";
    } else {
        echo "Error creating delivery_challan_items table: " . $conn->error . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
