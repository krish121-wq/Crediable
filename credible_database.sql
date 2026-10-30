-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: u463638053_credible_bill
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `accounts`
--

DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounts` (
  `account_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_name` varchar(100) NOT NULL,
  `account_type` enum('Asset','Liability','Equity','Revenue','Expense') NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accounts`
--

LOCK TABLES `accounts` WRITE;
/*!40000 ALTER TABLE `accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_accounts`
--

DROP TABLE IF EXISTS `bank_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bank_name` varchar(100) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `ifsc_code` varchar(11) NOT NULL,
  `branch_name` varchar(100) DEFAULT NULL,
  `account_holder_name` varchar(100) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_accounts`
--

LOCK TABLES `bank_accounts` WRITE;
/*!40000 ALTER TABLE `bank_accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_reconciliation`
--

DROP TABLE IF EXISTS `bank_reconciliation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_reconciliation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bank_account_id` int(11) NOT NULL,
  `bank_transaction_id` int(11) NOT NULL,
  `system_reference_id` int(11) DEFAULT NULL,
  `system_reference_type` enum('sale','purchase','manual','other') DEFAULT NULL,
  `reconciled_amount` decimal(10,2) NOT NULL,
  `reconciled_date` datetime NOT NULL,
  `status` enum('matched','unmatched','discrepancy') DEFAULT 'unmatched',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `statement_date` date NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_reconciliation`
--

LOCK TABLES `bank_reconciliation` WRITE;
/*!40000 ALTER TABLE `bank_reconciliation` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_reconciliation` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_transactions`
--

DROP TABLE IF EXISTS `bank_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bank_account_id` int(11) NOT NULL,
  `transaction_type` enum('credit','debit') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_date` datetime NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` enum('sale','purchase','manual','other') DEFAULT 'manual',
  `description` varchar(255) DEFAULT NULL,
  `cheque_number` varchar(50) DEFAULT NULL,
  `e_payment_reference` varchar(100) DEFAULT NULL,
  `reconciled` tinyint(1) DEFAULT 0,
  `reconciled_date` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `payee_name` varchar(255) NOT NULL,
  `payment_type` enum('NEFT','RTGS','UPI') DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_transactions`
--

LOCK TABLES `bank_transactions` WRITE;
/*!40000 ALTER TABLE `bank_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `brands`
--

DROP TABLE IF EXISTS `brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `brands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `brand_name` varchar(255) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `image_path` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `brands`
--

LOCK TABLES `brands` WRITE;
/*!40000 ALTER TABLE `brands` DISABLE KEYS */;
/*!40000 ALTER TABLE `brands` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cash_bill_items`
--

DROP TABLE IF EXISTS `cash_bill_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_bill_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cash_bill_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cash_bill_id` (`cash_bill_id`),
  KEY `idx_product_id` (`product_id`),
  CONSTRAINT `cash_bill_items_ibfk_1` FOREIGN KEY (`cash_bill_id`) REFERENCES `cash_bills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cash_bill_items`
--

LOCK TABLES `cash_bill_items` WRITE;
/*!40000 ALTER TABLE `cash_bill_items` DISABLE KEYS */;
INSERT INTO `cash_bill_items` VALUES (1,1,2,1,7080.00,7080.00,'2026-08-11 06:28:51');
/*!40000 ALTER TABLE `cash_bill_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cash_bill_payments`
--

DROP TABLE IF EXISTS `cash_bill_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_bill_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cash_bill_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_date` date NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `cash_bill_id` (`cash_bill_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_cash_bill_payments_bill` FOREIGN KEY (`cash_bill_id`) REFERENCES `cash_bills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cash_bill_payments`
--

LOCK TABLES `cash_bill_payments` WRITE;
/*!40000 ALTER TABLE `cash_bill_payments` DISABLE KEYS */;
INSERT INTO `cash_bill_payments` VALUES (1,1,5000.00,'Cash','2026-08-11','Initial Payment','Initial payment on bill creation',2,'2026-08-11 06:28:51');
/*!40000 ALTER TABLE `cash_bill_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cash_bills`
--

DROP TABLE IF EXISTS `cash_bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_bills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_number` varchar(20) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `mobile_number` varchar(50) NOT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bill_number` (`bill_number`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cash_bills`
--

LOCK TABLES `cash_bills` WRITE;
/*!40000 ALTER TABLE `cash_bills` DISABLE KEYS */;
INSERT INTO `cash_bills` VALUES (1,'CB-202608-001','krish','9340504587',7080.00,5000.00,2,'2026-08-11 06:28:51');
/*!40000 ALTER TABLE `cash_bills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'furniture','furniture',1,2,'2026-08-12 09:53:38',NULL,NULL);
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `companies`
--

DROP TABLE IF EXISTS `companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL,
  `company_address` text NOT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `companies`
--

LOCK TABLES `companies` WRITE;
/*!40000 ALTER TABLE `companies` DISABLE KEYS */;
/*!40000 ALTER TABLE `companies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `company_details`
--

DROP TABLE IF EXISTS `company_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL,
  `business_type` varchar(50) NOT NULL,
  `company_logo` varchar(255) DEFAULT NULL,
  `company_address` text NOT NULL,
  `billing_address` text DEFAULT NULL,
  `company_gst` varchar(15) DEFAULT NULL,
  `pan_number` varchar(10) DEFAULT NULL,
  `registration_number` varchar(50) DEFAULT NULL,
  `contact_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `alternate_contact` varchar(100) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `state` varchar(20) DEFAULT NULL,
  `upi_id` varchar(100) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account` varchar(50) DEFAULT NULL,
  `bank_ifsc` varchar(20) DEFAULT NULL,
  `bank_branch` varchar(100) DEFAULT NULL,
  `website` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `company_details`
--

LOCK TABLES `company_details` WRITE;
/*!40000 ALTER TABLE `company_details` DISABLE KEYS */;
INSERT INTO `company_details` VALUES (1,'PS CREDIBLE TECHNOLOGIES','service','Uploads/1786119189_pscrecaplogo3x3in_page-0001.jpg','kolkata','','19AAZFP3317Q1Z6','AAZFP3317Q','','8777289428','pscredible@gmail.com','8777289428','9046367947',0,'West Bengal','','kotak mahendra bank','259046367947','KKBK0000958','Mumbai Branch','https://pscredible.com/');
/*!40000 ALTER TABLE `company_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `create_users`
--

DROP TABLE IF EXISTS `create_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `create_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `number` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `role` enum('staff','manager') NOT NULL DEFAULT 'staff',
  `employee_id` varchar(50) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  KEY `role` (`role`),
  KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `create_users`
--

LOCK TABLES `create_users` WRITE;
/*!40000 ALTER TABLE `create_users` DISABLE KEYS */;
INSERT INTO `create_users` VALUES (1,'krish nagar','09320394565','krishdhakad938@gmail.com','$2y$10$bySvVRfrhFa979ve00e65uIjFM41de10pZ5GahBT1cc5px6gVfhhq',1,'staff','credible@gmail.com','','Sales','2026-08-12',2,NULL,'2026-08-11 08:02:31','2026-08-11 08:02:31');
/*!40000 ALTER TABLE `create_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `current_balance` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`),
  KEY `idx_name` (`name`),
  KEY `idx_phone` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,'RAKESH RANJAN','9532632623','RTY@GMAIL.COM','HOWRAH KOLKATA','West Bengal','KOLKATA','700003',NULL,'2026-08-08 07:49:52','2026-08-08 07:49:52',0.00);
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dashboard_offer_banners`
--

DROP TABLE IF EXISTS `dashboard_offer_banners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dashboard_offer_banners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `badge_text` varchar(100) DEFAULT 'FLASH SALE',
  `main_heading` varchar(255) DEFAULT 'Flat 15% Off',
  `sub_heading` varchar(255) DEFAULT 'on All Bookings & Orders',
  `slot_1` varchar(100) DEFAULT '2-3 PM',
  `slot_2` varchar(100) DEFAULT '8-9 PM',
  `slot_3` varchar(100) DEFAULT '10 PM-12 AM',
  `target_link` varchar(255) DEFAULT 'sales.php',
  `banner_bg_image` varchar(255) DEFAULT 'Uploads/default_offer_banner.jpg',
  `is_active` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dashboard_offer_banners`
--

LOCK TABLES `dashboard_offer_banners` WRITE;
/*!40000 ALTER TABLE `dashboard_offer_banners` DISABLE KEYS */;
INSERT INTO `dashboard_offer_banners` VALUES (1,'FLASH SALE','Flat 15% Off','on All Bookings & Orders','2-3 PM','8-9 PM','10 PM-12 AM','sales.php','Uploads/default_offer_banner.jpg',1,'2026-09-06 11:17:42');
/*!40000 ALTER TABLE `dashboard_offer_banners` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `debit_note_items`
--

DROP TABLE IF EXISTS `debit_note_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `debit_note_items` (
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `debit_note_id` (`debit_note_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `debit_note_items_ibfk_1` FOREIGN KEY (`debit_note_id`) REFERENCES `debit_notes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `debit_note_items`
--

LOCK TABLES `debit_note_items` WRITE;
/*!40000 ALTER TABLE `debit_note_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `debit_note_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `debit_notes`
--

DROP TABLE IF EXISTS `debit_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `debit_notes` (
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `debit_note_no` (`debit_note_no`),
  KEY `original_bill_no` (`original_bill_no`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `debit_notes`
--

LOCK TABLES `debit_notes` WRITE;
/*!40000 ALTER TABLE `debit_notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `debit_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_challan_items`
--

DROP TABLE IF EXISTS `delivery_challan_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `delivery_challan_items` (
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `challan_id` (`challan_id`),
  CONSTRAINT `delivery_challan_items_ibfk_1` FOREIGN KEY (`challan_id`) REFERENCES `delivery_challans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_challan_items`
--

LOCK TABLES `delivery_challan_items` WRITE;
/*!40000 ALTER TABLE `delivery_challan_items` DISABLE KEYS */;
INSERT INTO `delivery_challan_items` VALUES (1,1,'nff','87889','2026-08-12',1,2.00,10.00,2.00,0.20,2.20,'2026-08-11 07:47:35');
/*!40000 ALTER TABLE `delivery_challan_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_challans`
--

DROP TABLE IF EXISTS `delivery_challans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `delivery_challans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_name` varchar(255) NOT NULL,
  `challan_no` varchar(100) NOT NULL,
  `challan_date` date NOT NULL,
  `invoice_no` varchar(100) DEFAULT NULL,
  `party_no` varchar(100) DEFAULT NULL,
  `additional_notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `challan_no` (`challan_no`),
  KEY `created_by` (`created_by`),
  KEY `challan_date` (`challan_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_challans`
--

LOCK TABLES `delivery_challans` WRITE;
/*!40000 ALTER TABLE `delivery_challans` DISABLE KEYS */;
INSERT INTO `delivery_challans` VALUES (1,'krish','7678878','2026-08-11','887989898','8787987','jhg j gyugyu',2,'2026-08-11 07:47:35','2026-08-11 07:47:35');
/*!40000 ALTER TABLE `delivery_challans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eway_bill_items`
--

DROP TABLE IF EXISTS `eway_bill_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eway_bill_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eway_bill_id` int(11) NOT NULL,
  `purchase_item_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `hsn_code` varchar(10) DEFAULT NULL,
  `per_piece_price` decimal(10,2) NOT NULL,
  `qty` int(11) NOT NULL,
  `transported_qty` int(11) NOT NULL,
  `discount` decimal(5,2) DEFAULT 0.00,
  `amount` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eway_bill_items`
--

LOCK TABLES `eway_bill_items` WRITE;
/*!40000 ALTER TABLE `eway_bill_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `eway_bill_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eway_bills`
--

DROP TABLE IF EXISTS `eway_bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eway_bills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `eway_bill_no` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `party_no` varchar(50) DEFAULT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `bill_date` date DEFAULT NULL,
  `type` enum('Cash','Credit','Online') NOT NULL,
  `supplier_gstin` varchar(15) DEFAULT NULL,
  `recipient_gstin` varchar(15) DEFAULT NULL,
  `transporter_name` varchar(255) DEFAULT NULL,
  `transporter_id` varchar(50) DEFAULT NULL,
  `vehicle_no` varchar(20) DEFAULT NULL,
  `distance` decimal(10,2) DEFAULT NULL,
  `gross_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cgst` decimal(5,2) DEFAULT 0.00,
  `sgst` decimal(5,2) DEFAULT 0.00,
  `igst` decimal(5,2) DEFAULT 0.00,
  `total_discount` decimal(10,2) DEFAULT 0.00,
  `round_off` decimal(10,2) DEFAULT 0.00,
  `net_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `added_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eway_bills`
--

LOCK TABLES `eway_bills` WRITE;
/*!40000 ALTER TABLE `eway_bills` DISABLE KEYS */;
/*!40000 ALTER TABLE `eway_bills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expense_categories`
--

DROP TABLE IF EXISTS `expense_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expense_categories`
--

LOCK TABLES `expense_categories` WRITE;
/*!40000 ALTER TABLE `expense_categories` DISABLE KEYS */;
INSERT INTO `expense_categories` VALUES (1,'RENT','',1,'2026-08-08 08:24:20');
/*!40000 ALTER TABLE `expense_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_category` varchar(100) NOT NULL,
  `expense_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` text DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `receipt_number` varchar(50) DEFAULT NULL,
  `vendor_name` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_expense_date` (`expense_date`),
  KEY `idx_category` (`expense_category`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
INSERT INTO `expenses` VALUES (1,'RENT','2026-08-08',15000.00,'SADASD','Cash','RTT','RTYY','DSFDSF',2,'2026-08-08 08:24:55','2026-08-08 08:24:55'),(2,'RENT','2026-08-11',100.00,'hii','Cash','9878798','sahil','h hghh ',2,'2026-08-11 07:26:29','2026-08-11 07:26:29');
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `general_ledger`
--

DROP TABLE IF EXISTS `general_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `general_ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_name` varchar(100) NOT NULL,
  `account_type` varchar(50) NOT NULL,
  `debit` decimal(10,2) DEFAULT 0.00,
  `credit` decimal(10,2) DEFAULT 0.00,
  `transaction_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `general_ledger`
--

LOCK TABLES `general_ledger` WRITE;
/*!40000 ALTER TABLE `general_ledger` DISABLE KEYS */;
INSERT INTO `general_ledger` VALUES (1,'testing','Asset',100.00,0.00,'2026-08-11','',2,'2026-08-11 07:49:03',NULL,NULL);
/*!40000 ALTER TABLE `general_ledger` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(255) NOT NULL,
  `customer_phone` varchar(50) NOT NULL,
  `customer_address` text DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `tds_percent` decimal(5,2) DEFAULT 0.00,
  `tds_amount` decimal(10,2) DEFAULT 0.00,
  `invoice_no` varchar(50) DEFAULT NULL,
  `terms_text` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (1,'RAKESH RANJAN','9532632623','HOWRAH KOLKATA',14160.00,2,'2026-08-08 07:51:20',0.00,0.00,NULL,NULL),(2,'RAKESH RANJAN','9532632623','HOWRAH KOLKATA',59.00,2,'2026-08-12 16:04:35',0.00,0.00,NULL,NULL);
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `made_payments`
--

DROP TABLE IF EXISTS `made_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `made_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `receipt_id` int(11) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `made_payments`
--

LOCK TABLES `made_payments` WRITE;
/*!40000 ALTER TABLE `made_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `made_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_out_receipts`
--

DROP TABLE IF EXISTS `payment_out_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_out_receipts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receipt_no` varchar(50) NOT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `unused_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reference_no` varchar(100) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_out_receipts`
--

LOCK TABLES `payment_out_receipts` WRITE;
/*!40000 ALTER TABLE `payment_out_receipts` DISABLE KEYS */;
INSERT INTO `payment_out_receipts` VALUES (1,'RCPT/26-27/0888','Yash IT traders','Cash',1000.00,1000.00,'','2026-09-21',2,'2026-09-21 12:14:09');
/*!40000 ALTER TABLE `payment_out_receipts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_receipts`
--

DROP TABLE IF EXISTS `payment_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_receipts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receipt_no` varchar(50) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `unused_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reference_no` varchar(100) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_receipts`
--

LOCK TABLES `payment_receipts` WRITE;
/*!40000 ALTER TABLE `payment_receipts` DISABLE KEYS */;
INSERT INTO `payment_receipts` VALUES (1,'RCPT/26-27/0857','RAKESH RANJAN','Kotak Mahindra',1000.00,1000.00,'','2026-09-21',2,'2026-09-21 11:25:10'),(2,'RCPT/26-27/0103','RAKESH RANJAN','Cash',100.00,100.00,'','2026-09-21',2,'2026-09-21 11:31:47');
/*!40000 ALTER TABLE `payment_receipts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_records`
--

DROP TABLE IF EXISTS `payroll_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payroll_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `salary_structure_id` int(11) NOT NULL,
  `payroll_month` date NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL,
  `hra` decimal(10,2) DEFAULT 0.00,
  `travel_allowance` decimal(10,2) DEFAULT 0.00,
  `other_allowances` decimal(10,2) DEFAULT 0.00,
  `fixed_deductions` decimal(10,2) DEFAULT 0.00,
  `gross_salary` decimal(10,2) NOT NULL,
  `net_salary` decimal(10,2) NOT NULL,
  `status` enum('processed','pending') DEFAULT 'processed',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `pf_employee_contribution` decimal(10,2) DEFAULT 0.00,
  `pf_employer_contribution` decimal(10,2) DEFAULT 0.00,
  `esi_employee_contribution` decimal(10,2) DEFAULT 0.00,
  `esi_employer_contribution` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_records`
--

LOCK TABLES `payroll_records` WRITE;
/*!40000 ALTER TABLE `payroll_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `payroll_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pf_esi_rates`
--

DROP TABLE IF EXISTS `pf_esi_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pf_esi_rates` (
  `id` int(11) NOT NULL,
  `pf_employee_rate` decimal(5,2) NOT NULL DEFAULT 12.00,
  `pf_employer_rate` decimal(5,2) NOT NULL DEFAULT 12.00,
  `pf_salary_cap` decimal(10,2) NOT NULL DEFAULT 15000.00,
  `esi_employee_rate` decimal(5,2) NOT NULL DEFAULT 0.75,
  `esi_employer_rate` decimal(5,2) NOT NULL DEFAULT 3.25,
  `esi_salary_cap` decimal(10,2) NOT NULL DEFAULT 21000.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pf_esi_rates`
--

LOCK TABLES `pf_esi_rates` WRITE;
/*!40000 ALTER TABLE `pf_esi_rates` DISABLE KEYS */;
/*!40000 ALTER TABLE `pf_esi_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_serials`
--

DROP TABLE IF EXISTS `product_serials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_serials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `serial_number` varchar(255) NOT NULL,
  `status` enum('in_stock','sold','rented','returned') DEFAULT 'in_stock',
  `purchase_id` int(11) DEFAULT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_serial_product` (`product_id`,`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_serials`
--

LOCK TABLES `product_serials` WRITE;
/*!40000 ALTER TABLE `product_serials` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_serials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_name` varchar(255) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `sub_category_id` int(11) DEFAULT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `item_barcode` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price_per_piece` decimal(10,2) DEFAULT NULL,
  `tax_rate` int(5) DEFAULT 0,
  `total_price_without_gst` decimal(12,2) DEFAULT NULL,
  `total_price_with_gst` decimal(12,2) DEFAULT NULL,
  `quantity_alert` int(11) DEFAULT 0,
  `expiring_date` date DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `product_code` varchar(50) NOT NULL DEFAULT '',
  `product_design_code` varchar(50) NOT NULL DEFAULT '',
  `product_description` text DEFAULT NULL,
  `hsn_code` varchar(50) DEFAULT NULL,
  `unit` enum('Piece','KG','Liter','Gram') DEFAULT NULL,
  `gst_percentage` decimal(5,2) DEFAULT 0.00,
  `quantity` int(255) NOT NULL DEFAULT 0,
  `purchase_price` decimal(10,2) DEFAULT 0.00,
  `sell_price` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `cgst` decimal(5,2) DEFAULT 0.00,
  `sgst` decimal(5,2) DEFAULT 0.00,
  `igst` decimal(5,2) DEFAULT 0.00,
  `tracking_type` enum('normal','serial') DEFAULT 'normal',
  PRIMARY KEY (`id`),
  KEY `fk_product_category` (`category_id`),
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'i3 full deskto sate',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,'','','(With Hardware Support Only //Intel Core i5 4th Gen 4200U // RAM-8GB // SSD-256GB // Windows 10Pro/2 GB Graphics// // @per LaptopRent 1200/month','','Piece',5.50,0,1582.50,211.00,'2026-08-07 16:16:32',2.75,2.75,0.00,'normal'),(2,'RAM DDR 4',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,'DDR-001','MODEL-01','HIGH SPEED','3215','Piece',18.00,-1,5310.00,7080.00,'2026-08-08 07:32:47',9.00,9.00,0.00,'normal'),(3,'CP PLUS URC',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,'PR-002','URDCPL3','ababsafbmn','3215','Piece',18.00,0,1180.00,1416.00,'2026-08-08 07:36:16',9.00,9.00,0.00,'serial'),(4,'Brush Set',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,'PRD-001','DES-2024','brief production','3215','KG',18.00,0,118.00,59.00,'2026-08-11 06:18:45',9.00,9.00,0.00,'serial'),(5,'product',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,'PRD-002','DES-2025','mvbjvbs dhds','3215','KG',18.00,0,118.00,236.00,'2026-08-12 04:39:11',9.00,9.00,0.00,'normal');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proforma_invoice_items`
--

DROP TABLE IF EXISTS `proforma_invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proforma_invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proforma_id` int(11) NOT NULL,
  `service_name` varchar(300) NOT NULL,
  `service_description` text DEFAULT '',
  `sac_code` varchar(20) DEFAULT '',
  `quantity` decimal(10,3) DEFAULT 1.000,
  `unit` varchar(30) DEFAULT '',
  `rate` decimal(12,2) DEFAULT 0.00,
  `gst_rate` decimal(5,2) DEFAULT 0.00,
  `amount` decimal(12,2) DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proforma_invoice_items`
--

LOCK TABLES `proforma_invoice_items` WRITE;
/*!40000 ALTER TABLE `proforma_invoice_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `proforma_invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proforma_invoices`
--

DROP TABLE IF EXISTS `proforma_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proforma_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proforma_no` varchar(50) NOT NULL,
  `proforma_date` date NOT NULL,
  `customer_id` int(11) DEFAULT 0,
  `customer_name` varchar(200) NOT NULL,
  `customer_phone` varchar(30) DEFAULT '',
  `customer_address` text DEFAULT '',
  `customer_state` varchar(100) DEFAULT '',
  `subtotal` decimal(12,2) DEFAULT 0.00,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `gst_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `created_by` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proforma_invoices`
--

LOCK TABLES `proforma_invoices` WRITE;
/*!40000 ALTER TABLE `proforma_invoices` DISABLE KEYS */;
/*!40000 ALTER TABLE `proforma_invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_items`
--

DROP TABLE IF EXISTS `purchase_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `hsn_code` varchar(50) DEFAULT NULL,
  `purchase_price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL,
  `discount` decimal(5,2) DEFAULT 0.00,
  `gst_percentage` decimal(10,2) DEFAULT 0.00,
  `gst_amount` decimal(10,2) DEFAULT 0.00,
  `amount` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_items`
--

LOCK TABLES `purchase_items` WRITE;
/*!40000 ALTER TABLE `purchase_items` DISABLE KEYS */;
INSERT INTO `purchase_items` VALUES (1,1,3,'3215',1180.00,50,0.00,18.00,9000.00,59000.00),(2,2,3,'3215',1180.00,1,10.00,18.00,162.00,1062.00);
/*!40000 ALTER TABLE `purchase_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_return_items`
--

DROP TABLE IF EXISTS `purchase_return_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_return_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_return_id` int(11) NOT NULL,
  `purchase_item_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `hsn_code` varchar(50) DEFAULT NULL,
  `per_piece_price` decimal(10,2) NOT NULL,
  `qty` int(11) NOT NULL,
  `return_qty` int(11) NOT NULL,
  `discount` decimal(5,2) DEFAULT 0.00,
  `amount` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_return_items`
--

LOCK TABLES `purchase_return_items` WRITE;
/*!40000 ALTER TABLE `purchase_return_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_return_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_return_records`
--

DROP TABLE IF EXISTS `purchase_return_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_return_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `original_bill_no` varchar(50) NOT NULL,
  `product_id` int(11) NOT NULL,
  `return_quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `return_amount` decimal(10,2) NOT NULL,
  `return_note` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `original_bill_no` (`original_bill_no`),
  KEY `product_id` (`product_id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_return_records`
--

LOCK TABLES `purchase_return_records` WRITE;
/*!40000 ALTER TABLE `purchase_return_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_return_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_returns`
--

DROP TABLE IF EXISTS `purchase_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_returns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_bill_id` int(11) NOT NULL,
  `entry_no` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `party_no` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `bill_date` date DEFAULT NULL,
  `type` enum('Cash','Credit','Online') NOT NULL,
  `supplier_gstin` varchar(50) DEFAULT NULL,
  `recipient_gstin` varchar(50) DEFAULT NULL,
  `gross_amount` decimal(10,2) DEFAULT 0.00,
  `cgst` decimal(5,2) DEFAULT 0.00,
  `sgst` decimal(5,2) DEFAULT 0.00,
  `igst` decimal(5,2) DEFAULT 0.00,
  `total_discount` decimal(10,2) DEFAULT 0.00,
  `round_off` decimal(5,2) DEFAULT 0.00,
  `net_amount` decimal(10,2) DEFAULT 0.00,
  `added_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_returns`
--

LOCK TABLES `purchase_returns` WRITE;
/*!40000 ALTER TABLE `purchase_returns` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_returns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchases`
--

DROP TABLE IF EXISTS `purchases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entry_no` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `party_no` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `bill_date` date DEFAULT NULL,
  `type` enum('Cash','Credit','Online') NOT NULL,
  `supplier_gstin` varchar(50) DEFAULT NULL,
  `recipient_gstin` varchar(50) DEFAULT NULL,
  `gross_amount` decimal(10,2) DEFAULT 0.00,
  `cgst` decimal(5,2) DEFAULT 0.00,
  `sgst` decimal(5,2) DEFAULT 0.00,
  `igst` decimal(5,2) DEFAULT 0.00,
  `total_discount` decimal(10,2) DEFAULT 0.00,
  `round_off` decimal(5,2) DEFAULT 0.00,
  `net_amount` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchases`
--

LOCK TABLES `purchases` WRITE;
/*!40000 ALTER TABLE `purchases` DISABLE KEYS */;
INSERT INTO `purchases` VALUES (1,'BILL-20260808-582','Yash IT traders','INV-001','2026-08-08','2026-08-08','Cash',NULL,NULL,50000.00,0.00,0.00,0.00,0.00,0.00,59000.00,'2026-08-08 07:42:57'),(2,'BILL-20260811-348','Yash IT traders','8787987','2026-08-11',NULL,'Cash','hgygiyhu','hbjh',900.00,0.00,0.00,0.00,118.00,0.00,1062.00,'2026-08-11 07:34:35');
/*!40000 ALTER TABLE `purchases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `receivables_payables`
--

DROP TABLE IF EXISTS `receivables_payables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `receivables_payables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('receivable','payable') NOT NULL,
  `party_name` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('pending','paid','overdue') DEFAULT 'pending',
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `receivables_payables`
--

LOCK TABLES `receivables_payables` WRITE;
/*!40000 ALTER TABLE `receivables_payables` DISABLE KEYS */;
INSERT INTO `receivables_payables` VALUES (1,'payable','krish',1000.00,'2026-08-11','pending','',2,'2026-08-11 07:49:38',NULL,NULL);
/*!40000 ALTER TABLE `receivables_payables` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `received_payments`
--

DROP TABLE IF EXISTS `received_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `received_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `receipt_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `received_payments`
--

LOCK TABLES `received_payments` WRITE;
/*!40000 ALTER TABLE `received_payments` DISABLE KEYS */;
INSERT INTO `received_payments` VALUES (1,1,'Cash',10000.00,2,'2026-08-08 07:54:07',NULL,NULL),(2,2,'Cash',59.00,2,'2026-08-12 16:04:35',NULL,NULL),(3,1,'Cash',4140.00,2,'2026-08-20 13:05:40',NULL,NULL),(4,1,'Cash',20.00,2,'2026-08-20 13:07:53',NULL,NULL);
/*!40000 ALTER TABLE `received_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salary_structures`
--

DROP TABLE IF EXISTS `salary_structures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salary_structures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `hra` decimal(10,2) DEFAULT 0.00,
  `travel_allowance` decimal(10,2) DEFAULT 0.00,
  `other_allowances` decimal(10,2) DEFAULT 0.00,
  `fixed_deductions` decimal(10,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salary_structures`
--

LOCK TABLES `salary_structures` WRITE;
/*!40000 ALTER TABLE `salary_structures` DISABLE KEYS */;
INSERT INTO `salary_structures` VALUES (1,4,8000.00,1000.00,1500.00,800.00,900.00,'active',2,'2025-12-16 13:29:29',NULL,NULL),(3,9,12000.00,0.00,100.00,0.00,1000.00,'active',2,'2026-03-08 08:53:40',2,'2026-04-16 11:09:22'),(4,7,800.00,9.00,0.00,0.00,0.00,'active',2,'2026-04-30 12:29:25',NULL,NULL),(5,10,12000.00,2600.00,1200.00,2200.00,0.00,'active',2,'2026-05-02 02:44:02',NULL,NULL);
/*!40000 ALTER TABLE `salary_structures` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_item_serials`
--

DROP TABLE IF EXISTS `sale_item_serials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_item_serials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_item_id` int(11) NOT NULL,
  `serial_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_item_serials`
--

LOCK TABLES `sale_item_serials` WRITE;
/*!40000 ALTER TABLE `sale_item_serials` DISABLE KEYS */;
/*!40000 ALTER TABLE `sale_item_serials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_return_records`
--

DROP TABLE IF EXISTS `sale_return_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_return_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `original_invoice_id` varchar(50) NOT NULL,
  `product_id` int(11) NOT NULL,
  `return_quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `gst_percentage` decimal(5,2) DEFAULT 0.00,
  `gst_amount` decimal(10,2) DEFAULT 0.00,
  `return_amount` decimal(10,2) NOT NULL,
  `return_note` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `sale_return_records_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_return_records`
--

LOCK TABLES `sale_return_records` WRITE;
/*!40000 ALTER TABLE `sale_return_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `sale_return_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales`
--

DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(15) NOT NULL,
  `customer_address` text NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'cash',
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `outstanding_amount` decimal(10,2) DEFAULT 0.00,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `price_per_piece` decimal(10,2) DEFAULT 0.00,
  `transaction_type` varchar(20) DEFAULT 'sale',
  `tds_percent` decimal(5,2) DEFAULT 0.00,
  `tds_amount` decimal(10,2) DEFAULT 0.00,
  `serial_numbers` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales`
--

LOCK TABLES `sales` WRITE;
/*!40000 ALTER TABLE `sales` DISABLE KEYS */;
INSERT INTO `sales` VALUES (1,'RAKESH RANJAN','9532632623','HOWRAH KOLKATA',3,10,14160.00,'cash',0.00,0.00,2,'2026-08-08 07:51:20',1,0.00,0.00,1416.00,'sale',0.00,0.00,NULL),(2,'RAKESH RANJAN','9532632623','HOWRAH KOLKATA',4,1,59.00,'cash',0.00,0.00,2,'2026-08-12 16:04:35',2,0.00,0.00,59.00,'rent',0.00,0.00,NULL);
/*!40000 ALTER TABLE `sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_invoice_items`
--

DROP TABLE IF EXISTS `service_invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `service_name` varchar(255) NOT NULL,
  `service_description` text DEFAULT NULL,
  `sac_code` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(50) DEFAULT NULL,
  `rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_type` enum('amount','percentage') DEFAULT 'amount',
  `discount_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `customer_state` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_invoice_id` (`invoice_id`),
  CONSTRAINT `service_invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `service_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_invoice_items`
--

LOCK TABLES `service_invoice_items` WRITE;
/*!40000 ALTER TABLE `service_invoice_items` DISABLE KEYS */;
INSERT INTO `service_invoice_items` VALUES (1,1,'car','hc dcd ciud dcd','98987',1.00,'2',100.00,100.00,'amount',0.00,'West Bengal','2026-08-11 06:46:33');
/*!40000 ALTER TABLE `service_invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_invoice_payments`
--

DROP TABLE IF EXISTS `service_invoice_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_invoice_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_amount` decimal(10,2) NOT NULL,
  `payment_notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_invoice_id` (`invoice_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `service_invoice_payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `service_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_invoice_payments`
--

LOCK TABLES `service_invoice_payments` WRITE;
/*!40000 ALTER TABLE `service_invoice_payments` DISABLE KEYS */;
INSERT INTO `service_invoice_payments` VALUES (1,1,1,'Cash',100.00,'Payment received during invoice creation',2,'2026-08-11 06:46:33');
/*!40000 ALTER TABLE `service_invoice_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_invoices`
--

DROP TABLE IF EXISTS `service_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(50) DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `delivery_note` text DEFAULT NULL,
  `payment_terms` varchar(255) DEFAULT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `buyer_order_no` varchar(50) DEFAULT NULL,
  `dated` date DEFAULT NULL,
  `dispatch_doc_no` varchar(50) DEFAULT NULL,
  `delivery_note_date` date DEFAULT NULL,
  `dispatched_through` varchar(100) DEFAULT NULL,
  `destination` text DEFAULT NULL,
  `terms_of_delivery` text DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_phone` varchar(50) DEFAULT NULL,
  `customer_address` text DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gst_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT NULL,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_invoices`
--

LOCK TABLES `service_invoices` WRITE;
/*!40000 ALTER TABLE `service_invoices` DISABLE KEYS */;
INSERT INTO `service_invoices` VALUES (1,'INV 2026','2026-08-11','','','','','0000-00-00','','0000-00-00','','','',1,'RAKESH RANJAN','9532632623','HOWRAH KOLKATA','HOWRAH KOLKATA',100.00,0.00,0.00,100.00,'Cash',100.00,2,'2026-08-11 06:46:33','2026-08-11 06:46:33');
/*!40000 ALTER TABLE `service_invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_quotation_items`
--

DROP TABLE IF EXISTS `service_quotation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_quotation_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) DEFAULT NULL,
  `service_name` varchar(255) DEFAULT NULL,
  `service_description` text DEFAULT NULL,
  `sac_code` varchar(20) DEFAULT NULL,
  `quantity` decimal(15,2) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `rate` decimal(15,2) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `gst_rate` decimal(5,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `quotation_id` (`quotation_id`),
  CONSTRAINT `service_quotation_items_ibfk_1` FOREIGN KEY (`quotation_id`) REFERENCES `service_quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_quotation_items`
--

LOCK TABLES `service_quotation_items` WRITE;
/*!40000 ALTER TABLE `service_quotation_items` DISABLE KEYS */;
INSERT INTO `service_quotation_items` VALUES (1,1,'website design','hgj g u ','99834',1.00,'0',100.00,100.00,18.00);
/*!40000 ALTER TABLE `service_quotation_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_quotations`
--

DROP TABLE IF EXISTS `service_quotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_quotations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_no` varchar(50) NOT NULL,
  `quotation_date` date NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `customer_address` text DEFAULT NULL,
  `customer_state` varchar(100) DEFAULT NULL,
  `subtotal` decimal(15,2) DEFAULT NULL,
  `discount_amount` decimal(15,2) DEFAULT NULL,
  `gst_amount` decimal(15,2) DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_quotations`
--

LOCK TABLES `service_quotations` WRITE;
/*!40000 ALTER TABLE `service_quotations` DISABLE KEYS */;
INSERT INTO `service_quotations` VALUES (1,'QT/001','2026-08-11',1,'RAKESH RANJAN','9532632623','HOWRAH KOLKATA','West Bengal',100.00,10.00,18.00,108.00,2,'2026-08-11 07:16:10');
/*!40000 ALTER TABLE `service_quotations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_adjustment_log`
--

DROP TABLE IF EXISTS `stock_adjustment_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_adjustment_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `product_code` varchar(100) DEFAULT NULL,
  `old_quantity` int(11) NOT NULL,
  `new_quantity` int(11) NOT NULL,
  `adjustment_qty` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `adjustment_type` enum('excess','shortage','no_change') DEFAULT NULL,
  `adjusted_by` int(11) NOT NULL,
  `adjusted_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_adjustment_log`
--

LOCK TABLES `stock_adjustment_log` WRITE;
/*!40000 ALTER TABLE `stock_adjustment_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `stock_adjustment_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sub_categories`
--

DROP TABLE IF EXISTS `sub_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sub_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `sub_category_name` varchar(255) NOT NULL,
  `category_code` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `image_path` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sub_categories`
--

LOCK TABLES `sub_categories` WRITE;
/*!40000 ALTER TABLE `sub_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `sub_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(20) DEFAULT NULL,
  `gstin` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` VALUES (1,'Yash IT traders','9530000000','xyz@gmail.com','Kolkata','Kolkata','West Bengal','700093','','2026-08-08 07:39:00',NULL);
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `terms_conditions`
--

DROP TABLE IF EXISTS `terms_conditions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `terms_conditions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `terms_conditions`
--

LOCK TABLES `terms_conditions` WRITE;
/*!40000 ALTER TABLE `terms_conditions` DISABLE KEYS */;
INSERT INTO `terms_conditions` VALUES (1,'Test Title','Test Content',1,'2026-09-19 15:05:30'),(2,'CURL Title','CURL Content',1,'2026-09-19 15:18:23'),(3,'jhdfkghfg','hfhdyhf fdufd',2,'2026-09-19 15:30:44'),(4,'jhdfkghfg','hfhdyhf fdufd',2,'2026-09-19 15:30:46'),(5,'jhdfkghfg','hfhdyhf fdufd',2,'2026-09-19 15:30:47'),(6,'jhdfkghfg','hfhdyhf fdufd',2,'2026-09-19 15:30:48'),(7,'jhdfkghfg','hfhdyhf fdufd',2,'2026-09-19 15:30:48'),(8,'jhdfkghfg','hfhdyhf fdufd',2,'2026-09-19 15:30:48'),(9,'jhdfkghfg','hfhdyhf fdufd',2,'2026-09-19 15:30:48'),(10,'jhdfkghfg','hfhdyhf fdufd',2,'2026-09-19 15:30:49'),(11,'jhdfkghfg','hfhdyhf fdufd',2,'2026-09-19 15:30:50'),(12,'jhdfkghfg','hfhdyhf fdufd',2,'2026-09-19 15:30:51'),(13,'jhdfkghfg','hfhdyhf fdufd',2,'2026-09-19 15:30:52'),(14,'jhdfkghfg','hfhdyhf fdufd',2,'2026-09-19 15:30:53'),(15,'jhdfkghfg','hfhdyhf fdufd',2,'2026-09-19 15:30:53'),(16,'jhdfkghfg','hfhdyhf fdufd',2,'2026-09-19 15:30:53'),(17,'jhdfkghfg','hfhdyhf fdufd',2,'2026-09-19 15:30:55'),(18,'jhdfkghfg','hfhdyhf fdufd',2,'2026-09-19 15:30:55'),(19,'jhdfkghfg','hfhdyhf fdufd',2,'2026-09-19 15:30:55'),(20,'jhdfkghfg','hfhdyhf fdufd',2,'2026-09-19 15:30:55'),(21,'jhdfkghfg','hfhdyhf fdufd',2,'2026-09-19 15:30:55'),(22,'jhdfkghfg','hfhdyhf fdufd',2,'2026-09-19 15:30:56'),(23,'fddsf sf','f ffghfggfgg g ffg',2,'2026-09-19 15:33:18'),(24,'fddsf sf','f ffghfggfgg g ffg',2,'2026-09-19 15:33:19'),(25,'fddsf sf','f ffghfggfgg g ffg',2,'2026-09-19 15:33:20'),(26,'fddsf sf','f ffghfggfgg g ffg',2,'2026-09-19 15:33:20'),(27,'fddsf sf','f ffghfggfgg g ffg',2,'2026-09-19 15:33:21'),(28,'fddsf sf','f ffghfggfgg g ffg',2,'2026-09-19 15:33:21'),(29,'fddsf sf','f ffghfggfgg g ffg',2,'2026-09-19 15:33:21'),(30,'CURL Title','CURL Content',1,'2026-09-19 15:34:16'),(31,'fddsf sf','f ffghfggfgg g ffg',2,'2026-09-19 15:38:31'),(32,'fhfd','bd fdsu',2,'2026-09-19 15:56:50');
/*!40000 ALTER TABLE `terms_conditions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_type` enum('credit','debit') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text NOT NULL,
  `transaction_date` date NOT NULL,
  `party_name` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `units`
--

DROP TABLE IF EXISTS `units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `units` (
  `id` int(11) NOT NULL,
  `unit_name` varchar(255) NOT NULL,
  `short_name` varchar(50) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `units`
--

LOCK TABLES `units` WRITE;
/*!40000 ALTER TABLE `units` DISABLE KEYS */;
INSERT INTO `units` VALUES (1,'Kilogram','kg ',1,2,'2025-06-26 13:29:04',2,'2025-06-27 12:44:15'),(2,' piece','pc',1,2,'2025-06-27 07:14:08',2,'2025-06-27 12:44:51'),(1,'Kilogram','kg ',1,2,'2025-06-26 13:29:04',2,'2025-06-27 12:44:15'),(2,' piece','pc',1,2,'2025-06-27 07:14:08',2,'2025-06-27 12:44:51');
/*!40000 ALTER TABLE `units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_company_roles`
--

DROP TABLE IF EXISTS `user_company_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_company_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `user_role` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_company_roles`
--

LOCK TABLES `user_company_roles` WRITE;
/*!40000 ALTER TABLE `user_company_roles` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_company_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mobile` varchar(10) NOT NULL,
  `password` varchar(255) NOT NULL,
  `trial_end_date` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (2,'Admin','credible@gmail.com','9875524047','$2y$10$ezMfunXWBCeZihcJwFSj8OH59D7InrYoebx1DF1g1lTiYn6wtUIHa','2027-07-09 05:34:13','2025-06-26 00:04:13');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_message_logs`
--

DROP TABLE IF EXISTS `whatsapp_message_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `whatsapp_message_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_phone` varchar(20) NOT NULL,
  `message_type` varchar(50) NOT NULL,
  `message_data` text DEFAULT NULL,
  `response` text DEFAULT NULL,
  `http_code` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_recipient` (`recipient_phone`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_message_logs`
--

LOCK TABLES `whatsapp_message_logs` WRITE;
/*!40000 ALTER TABLE `whatsapp_message_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `whatsapp_message_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'u463638053_credible_bill'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-30 11:25:51
