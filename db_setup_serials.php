<?php
require_once 'config.php';
$conn->query("
CREATE TABLE IF NOT EXISTS `product_serials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `serial_number` varchar(255) NOT NULL,
  `status` enum('in_stock','sold','rented','returned') DEFAULT 'in_stock',
  `purchase_id` int(11) DEFAULT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_serial_product` (`product_id`,`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$conn->query("ALTER TABLE `sales` ADD COLUMN `serial_numbers` TEXT DEFAULT NULL");

echo "Schema updated successfully!\n";
?>
