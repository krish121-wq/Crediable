<?php
require_once 'config.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS `purchase_returns` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `original_bill_id` int(11) NOT NULL,
        `product_id` int(11) NOT NULL,
        `return_quantity` int(11) NOT NULL,
        `unit_price` decimal(10,2) NOT NULL,
        `return_amount` decimal(10,2) NOT NULL,
        `return_note` text NOT NULL,
        `created_by` int(11) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `original_bill_id` (`original_bill_id`),
        KEY `product_id` (`product_id`),
        KEY `created_by` (`created_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        echo "Table 'purchase_returns' created successfully or already exists.\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
