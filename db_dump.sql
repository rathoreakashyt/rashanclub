-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: off_pos
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `attendances`
--

DROP TABLE IF EXISTS `attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `employee_id` bigint unsigned DEFAULT NULL,
  `in_time` time DEFAULT NULL,
  `out_time` time DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `attendances_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendances`
--

LOCK TABLES `attendances` WRITE;
/*!40000 ALTER TABLE `attendances` DISABLE KEYS */;
INSERT INTO `attendances` VALUES (1,'ATT-0001','2026-07-10',2,'09:00:00','18:00:00','Regular day',1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45'),(2,'ATT-0002','2026-07-10',3,'09:30:00','18:00:00','Regular day',1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45'),(3,'ATT-0003','2026-07-10',4,'09:00:00','17:30:00','Regular day',1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45'),(4,'ATT-0004','2026-07-11',2,'09:00:00','18:00:00','Regular day',1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45'),(5,'ATT-0005','2026-07-11',3,'09:15:00','18:00:00','Regular day',1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45'),(6,'ATT-0006','2026-07-12',2,'09:00:00','18:00:00','Regular day',1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45'),(7,'ATT-0007','2026-07-12',4,'09:00:00','17:00:00','Left early',1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45'),(8,'ATT-0008','2026-07-13',2,'09:00:00','18:00:00','Regular day',1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45'),(9,'ATT-0009','2026-07-13',3,'09:30:00','18:30:00','Overtime',1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45'),(10,'ATT-0010','2026-07-14',4,'09:00:00','18:00:00','Regular day',1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45');
/*!40000 ALTER TABLE `attendances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned DEFAULT NULL,
  `service_seller_id` bigint unsigned DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `service_note` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `service_seller_id` (`service_seller_id`),
  CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`service_seller_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookings`
--

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
INSERT INTO `bookings` VALUES (1,7,4,'2026-07-20 10:00:00','2026-07-20 11:00:00','Confirmed','Home delivery booking',1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11',NULL,NULL);
/*!40000 ALTER TABLE `bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `brands`
--

DROP TABLE IF EXISTS `brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `brands` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `company_id` bigint unsigned DEFAULT '1',
  `user_id` bigint unsigned DEFAULT NULL,
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `brands`
--

LOCK TABLES `brands` WRITE;
/*!40000 ALTER TABLE `brands` DISABLE KEYS */;
INSERT INTO `brands` VALUES (1,'Aashirvaad','ITC Aashirvaad brand products',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(2,'Tata','Tata consumer products',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(3,'Fortune','Adani Wilmar Fortune brand',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(4,'MDH','MDH spices and masalas',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(5,'Amul','Amul dairy products',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(6,'Patanjali','Patanjali Ayurved products',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(7,'Parle','Parle biscuits and snacks',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(8,'India Gate','India Gate rice products',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(9,'Surf Excel','HUL Surf Excel detergents',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(10,'No Brand','Unbranded / local products',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56');
/*!40000 ALTER TABLE `brands` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `combo_items`
--

DROP TABLE IF EXISTS `combo_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `combo_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `combo_item_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `quantity` decimal(15,3) DEFAULT '0.000',
  `amount` decimal(15,3) DEFAULT '0.000',
  `total` decimal(15,3) DEFAULT '0.000',
  `show_in_invoice` tinyint(1) DEFAULT '1',
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `combo_item_id` (`combo_item_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `combo_items_ibfk_1` FOREIGN KEY (`combo_item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `combo_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `combo_items`
--

LOCK TABLES `combo_items` WRITE;
/*!40000 ALTER TABLE `combo_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `combo_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `combo_sales`
--

DROP TABLE IF EXISTS `combo_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `combo_sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint unsigned DEFAULT NULL,
  `combo_sale_item_id` bigint unsigned DEFAULT NULL,
  `combo_item_id` bigint unsigned DEFAULT NULL,
  `combo_item_qty` decimal(15,3) DEFAULT '0.000',
  `combo_item_price` decimal(15,3) DEFAULT '0.000',
  `combo_item_seller_id` bigint unsigned DEFAULT NULL,
  `show_in_invoice` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'Yes',
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `combo_sale_item_id` (`combo_sale_item_id`),
  KEY `combo_item_id` (`combo_item_id`),
  CONSTRAINT `combo_sales_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `combo_sales_ibfk_2` FOREIGN KEY (`combo_sale_item_id`) REFERENCES `sale_details` (`id`) ON DELETE SET NULL,
  CONSTRAINT `combo_sales_ibfk_3` FOREIGN KEY (`combo_item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `combo_sales`
--

LOCK TABLES `combo_sales` WRITE;
/*!40000 ALTER TABLE `combo_sales` DISABLE KEYS */;
/*!40000 ALTER TABLE `combo_sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `companies`
--

DROP TABLE IF EXISTS `companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `companies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_symbol` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timezone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_format` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time_format` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fy_start_month` int DEFAULT '1',
  `accounting_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'fifo',
  `default_profit_percent` decimal(5,2) DEFAULT '0.00',
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `white_label` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `business_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `short_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_position` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Before Amount',
  `precision` int DEFAULT '2',
  `thousands_separator` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT ',',
  `decimals_separator` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '.',
  `default_customer` bigint unsigned DEFAULT NULL,
  `default_payment` bigint unsigned DEFAULT NULL,
  `default_cursor_position` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Barcode Box',
  `product_display` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Image View',
  `onscreen_keyboard_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Disable',
  `allow_less_sale` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `direct_cart` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `grocery_experience` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `pos_total_payable_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `register_content` json DEFAULT NULL,
  `inv_logo_is_show` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'Yes',
  `invoice_logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_footer` text COLLATE utf8mb4_unicode_ci,
  `term_conditions` text COLLATE utf8mb4_unicode_ci,
  `letter_head_gap` int DEFAULT '200',
  `letter_footer_gap` int DEFAULT '100',
  `invoice_configuration` json DEFAULT NULL,
  `collect_tax` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `tax_is_gst` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `tax_title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_registration_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_setting` json DEFAULT NULL,
  `tax_string` text COLLATE utf8mb4_unicode_ci,
  `installment_days` int DEFAULT '3',
  `minimum_point_to_redeem` decimal(15,2) DEFAULT '0.00',
  `loyalty_rate` decimal(15,2) DEFAULT '0.00',
  `is_loyalty_enable` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `e_commerce_checker` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `product_code_start_from` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '000001',
  `smtp_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_enable_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Disable',
  `smtp_details` json DEFAULT NULL,
  `smtp_default_selected_in_pos` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `sms_service_provider` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sms_enable_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Disable',
  `sms_details` json DEFAULT NULL,
  `sms_default_selected_in_pos` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `whatsapp_provider` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp_invoice_enable_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Disable',
  `whatsapp_app_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp_authkey` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp_default_selected_in_pos` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `payment_api_setting` json DEFAULT NULL,
  `payment_settings` json DEFAULT NULL,
  `zatca_configuration` json DEFAULT NULL,
  `white_label_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Disable',
  `is_rounding_enable` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `purchase_price_show_hide` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Show',
  `generic_name_search_option` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `companies`
--

LOCK TABLES `companies` WRITE;
/*!40000 ALTER TABLE `companies` DISABLE KEYS */;
INSERT INTO `companies` VALUES (1,'Rashan Ki Dukan','admin@rashankidukan.com','+91-9876543210','Shop No. 5, Main Market, Laxmi Nagar, New Delhi - 110092','INR','Rs.','Asia/Kolkata','d/m/Y','h:i A',4,'fifo',0.00,'initial-logo.png','Live','{\"site_link\": \"http://localhost:8080\", \"site_logo\": \"site_logo_1784365601_6a5b4221938a6.jpg\", \"site_name\": \"Rashan Ki Dukan\", \"site_title\": \"Rashan Ki Dukan POS\", \"site_footer\": \"Rashan Ki Dukan - Fresh Grocery\", \"site_favicon\": \"initial-fav.ico\"}','2026-07-17 10:34:25','2026-07-18 12:10:53','Rashan Ki Dukan','RKD','Asia/Kolkata',NULL,'Before Amount',2,',','.',1,1,'Barcode Box','Image View','Disable','No','No','Regular',NULL,NULL,'Yes','invoice_logo_1784376653_6a5b6d4d6e8d6.jpg','<p>Thank you for shopping at Rashan Ki Dukan!</p>','<p><br></p>',200,100,'{\"tax_label\": \"Tax\", \"inv_prefix\": \"\", \"item_label\": \"Item\", \"show_brand\": \"No\", \"price_label\": \"Price\", \"qr_code_tax\": null, \"schema_type\": \"XXXX\", \"total_label\": \"Total\", \"word_format\": \"Indian\", \"charge_label\": \"Charge\", \"show_hsn_code\": \"No\", \"customer_label\": \"Customer\", \"discount_label\": \"Discount\", \"inv_start_from\": \"1\", \"qr_code_charge\": null, \"qr_code_option\": \"ZATCA QR Code\", \"qr_code_outlet\": null, \"quantity_label\": \"Qty\", \"rounding_label\": \"Rounding\", \"subtotal_label\": \"Subtotal\", \"invoice_heading\": \"Invoice\", \"letter_head_gap\": \"200\", \"qr_code_address\": null, \"serial_no_label\": \"SN\", \"due_amount_label\": \"Due Amount\", \"invoice_no_label\": \"Invoice No\", \"qr_code_business\": null, \"qr_code_subtotal\": null, \"show_letter_head\": \"No\", \"tax_label_arabic\": \"\", \"total_item_label\": \"Total Item\", \"due_receive_label\": \"Due Receive\", \"item_label_arabic\": \"\", \"letter_footer_gap\": \"100\", \"paid_amount_label\": \"Paid Amount\", \"qr_code_taxnumber\": null, \"show_product_code\": \"No\", \"given_amount_label\": \"Given Amount\", \"inv_numbering_type\": \"Sequential\", \"invoice_date_label\": \"Date\", \"price_label_arabic\": \"\", \"qr_code_invoice_no\": null, \"sales_person_label\": \"Sales Person\", \"show_business_name\": \"Yes\", \"show_product_image\": \"No\", \"total_label_arabic\": \"\", \"change_amount_label\": \"Change Amount\", \"charge_label_arabic\": \"\", \"inv_number_of_digit\": \"4\", \"invoice_heading_due\": \"Due\", \"item_discount_label\": \"Discount\", \"qr_code_invoice_url\": null, \"show_customer_email\": \"No\", \"show_payment_method\": \"Yes\", \"show_total_in_words\": \"Yes\", \"total_payable_label\": \"Total Payable\", \"business_name_arabic\": \"\", \"invoice_heading_paid\": \"Paid\", \"payment_method_label\": \"Payment Method\", \"show_warranty_period\": \"Yes\", \"advance_receive_label\": \"Advance Receive\", \"discount_label_arabic\": \"\", \"invoice_show_due_date\": \"Yes\", \"qr_code_customer_name\": null, \"qr_code_total_payable\": null, \"quantity_label_arabic\": \"\", \"rounding_label_arabic\": \"\", \"show_customer_address\": \"Yes\", \"show_guarantee_period\": \"Yes\", \"subtotal_label_arabic\": \"\", \"commission_agent_label\": \"Commission Agent\", \"delivery_partner_label\": \"Delivery Partner\", \"invoice_due_date_label\": \"Due Date\", \"invoice_format_or_size\": \"56mm\", \"invoice_heading_arabic\": \"\", \"previous_balance_label\": \"Previous Balance\", \"serial_no_label_arabic\": \"\", \"servicing_charge_label\": \"Servicing Charge\", \"due_amount_label_arabic\": \"\", \"invoice_no_label_arabic\": \"\", \"total_item_label_arabic\": \"\", \"due_receive_label_arabic\": \"\", \"paid_amount_label_arabic\": \"\", \"show_business_tax_number\": \"Yes\", \"business_tax_number_label\": \"Business Tax Number\", \"customer_tax_number_label\": \"Customer Tax Number\", \"given_amount_label_arabic\": \"\", \"invoice_date_label_arabic\": \"\", \"qr_code_invoice_date_time\": null, \"show_warranty_expiry_date\": \"Yes\", \"change_amount_label_arabic\": \"\", \"item_discount_label_arabic\": \"\", \"show_customer_phone_number\": \"Yes\", \"show_guarantee_expiry_date\": \"Yes\", \"total_payable_label_arabic\": \"\", \"payment_method_label_arabic\": \"\", \"advance_receive_label_arabic\": \"\", \"delivery_partner_label_arabic\": \"\", \"invoice_due_date_label_arabic\": \"\", \"previous_balance_label_arabic\": \"\", \"servicing_charge_label_arabic\": \"\", \"show_product_imei_serial_number\": \"No\"}','Yes','Yes','GST','07AABCU9603R1ZX',NULL,NULL,30,0.00,0.00,'No','No','000001',NULL,'Disable',NULL,'No',NULL,'Disable',NULL,'No',NULL,'Disable',NULL,NULL,'No',NULL,NULL,NULL,'Disable','No','Show','No');
/*!40000 ALTER TABLE `companies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `counters`
--

DROP TABLE IF EXISTS `counters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `counters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `printer_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `outlet_id` (`outlet_id`),
  KEY `printer_id` (`printer_id`),
  CONSTRAINT `counters_ibfk_1` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `counters_ibfk_2` FOREIGN KEY (`printer_id`) REFERENCES `printers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `counters`
--

LOCK TABLES `counters` WRITE;
/*!40000 ALTER TABLE `counters` DISABLE KEYS */;
INSERT INTO `counters` VALUES (1,'Counter 1',1,1,1,1,'Live','2026-07-17 12:17:52','2026-07-17 12:17:52');
/*!40000 ALTER TABLE `counters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_receives`
--

DROP TABLE IF EXISTS `customer_receives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_receives` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned DEFAULT NULL,
  `payment_method_id` bigint unsigned DEFAULT NULL,
  `amount` decimal(15,3) DEFAULT '0.000',
  `date` date DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `payment_method_id` (`payment_method_id`),
  CONSTRAINT `customer_receives_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_receives_ibfk_2` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_receives`
--

LOCK TABLES `customer_receives` WRITE;
/*!40000 ALTER TABLE `customer_receives` DISABLE KEYS */;
INSERT INTO `customer_receives` VALUES (1,4,1,500.000,'2026-07-14','Partial due payment for INV-0004',1,1,1,'Live','2026-07-17 12:34:53','2026-07-17 12:34:53',NULL);
/*!40000 ALTER TABLE `customer_receives` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_id` bigint unsigned DEFAULT NULL,
  `postal_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gst_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opening_balance` decimal(15,3) DEFAULT '0.000',
  `opening_balance_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `credit_limit` decimal(15,3) DEFAULT '0.000',
  `description` text COLLATE utf8mb4_unicode_ci,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `loyalty_point` decimal(15,2) DEFAULT '0.00',
  `dob` date DEFAULT NULL,
  `anniversary` date DEFAULT NULL,
  `is_installment_customer` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `discount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_anniversary` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_type` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `same_or_diff_state` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_type` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'B2C',
  PRIMARY KEY (`id`),
  KEY `state_id` (`state_id`),
  CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,'Walk-in Customer',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.000,'Debit',0.000,NULL,NULL,1,1,'Live','2026-07-17 12:26:11','2026-07-17 12:26:11',0.00,NULL,NULL,'No',NULL,NULL,NULL,NULL,NULL,'B2C'),(2,'Ramesh Sharma','ramesh.sharma@gmail.com','+91-9811001100','A-12, Laxmi Nagar','New Delhi',7,NULL,'India',NULL,500.000,'Debit',5000.000,NULL,NULL,1,1,'Live','2026-07-17 12:26:11','2026-07-17 12:26:11',0.00,'1985-03-15','2010-11-20','No',NULL,NULL,NULL,NULL,NULL,'B2C'),(3,'Sunita Devi','sunita.devi@gmail.com','+91-9822002200','B-45, Preet Vihar','New Delhi',7,NULL,'India',NULL,0.000,'Debit',3000.000,NULL,NULL,1,1,'Live','2026-07-17 12:26:11','2026-07-17 12:26:11',0.00,'1990-07-22',NULL,'No',NULL,NULL,NULL,NULL,NULL,'B2C'),(4,'Vikram Malhotra','vikram.m@gmail.com','+91-9833003300','C-8, Shakarpur','New Delhi',7,NULL,'India',NULL,1000.000,'Debit',8000.000,NULL,NULL,1,1,'Live','2026-07-17 12:26:11','2026-07-17 12:26:11',0.00,'1978-12-05','2005-02-14','No',NULL,NULL,NULL,NULL,NULL,'B2C'),(5,'Anita Kapoor','anita.kapoor@gmail.com','+91-9844004400','D-23, Patparganj','New Delhi',7,NULL,'India',NULL,0.000,'Debit',2000.000,NULL,NULL,1,1,'Live','2026-07-17 12:26:11','2026-07-17 12:26:11',0.00,'1992-05-18',NULL,'No',NULL,NULL,NULL,NULL,NULL,'B2C'),(6,'Suresh Gupta','suresh.gupta@gmail.com','+91-9855005500','E-67, Mayur Vihar','New Delhi',7,NULL,'India',NULL,2000.000,'Debit',10000.000,NULL,NULL,1,1,'Live','2026-07-17 12:26:11','2026-07-17 12:26:11',0.00,'1980-09-30','2008-06-12','No',NULL,NULL,NULL,NULL,NULL,'B2C'),(7,'Pooja Verma','pooja.verma@gmail.com','+91-9866006600','F-11, Ghazipur','New Delhi',7,NULL,'India',NULL,0.000,'Debit',1500.000,NULL,NULL,1,1,'Live','2026-07-17 12:26:11','2026-07-17 12:26:11',0.00,'1995-01-25',NULL,'No',NULL,NULL,NULL,NULL,NULL,'B2C'),(8,'Rajiv Khanna','rajiv.khanna@gmail.com','+91-9877007700','G-90, Geeta Colony','New Delhi',7,NULL,'India',NULL,500.000,'Credit',5000.000,NULL,NULL,1,1,'Live','2026-07-17 12:26:11','2026-07-17 12:26:11',0.00,'1975-08-10','2003-04-20','No',NULL,NULL,NULL,NULL,NULL,'B2C'),(9,'Meena Agarwal','meena.agarwal@gmail.com','+91-9888008800','H-34, Krishna Nagar','New Delhi',7,NULL,'India',NULL,0.000,'Debit',3000.000,NULL,NULL,1,1,'Live','2026-07-17 12:26:11','2026-07-17 12:26:11',0.00,'1988-11-14',NULL,'No',NULL,NULL,NULL,NULL,NULL,'B2C'),(10,'Deepak Jain','deepak.jain@gmail.com','+91-9899009900','I-56, Vivek Vihar','New Delhi',7,NULL,'India','07AAADJ1234D1ZX',0.000,'Debit',20000.000,NULL,NULL,1,1,'Live','2026-07-17 12:26:11','2026-07-17 12:26:11',0.00,'1982-04-08','2007-12-25','No',NULL,NULL,NULL,NULL,NULL,'B2B'),(11,'Kavita Mishra','kavita.mishra@gmail.com','+91-9800100200','J-78, Mandawali','New Delhi',7,NULL,'India',NULL,1500.000,'Debit',4000.000,NULL,NULL,1,1,'Live','2026-07-17 12:26:11','2026-07-17 12:26:11',0.00,'1993-06-30',NULL,'No',NULL,NULL,NULL,NULL,NULL,'B2C'),(12,'Mohan Lal Sahu','mohan.sahu@gmail.com','+91-9900200300','K-15, Loni Road','Ghaziabad',7,NULL,'India',NULL,0.000,'Debit',50000.000,NULL,NULL,1,1,'Live','2026-07-17 12:26:11','2026-07-17 12:26:11',0.00,NULL,NULL,'Yes',NULL,NULL,NULL,NULL,NULL,'B2C');
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `damage_details`
--

DROP TABLE IF EXISTS `damage_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `damage_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `damage_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `date` date DEFAULT NULL,
  `damage_quantity` decimal(15,3) DEFAULT '0.000',
  `last_purchase_price` decimal(15,3) DEFAULT '0.000',
  `loss_amount` decimal(15,3) DEFAULT '0.000',
  `total_amount` decimal(15,3) DEFAULT '0.000',
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `damage_id` (`damage_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `damage_details_ibfk_1` FOREIGN KEY (`damage_id`) REFERENCES `damages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `damage_details_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `damage_details`
--

LOCK TABLES `damage_details` WRITE;
/*!40000 ALTER TABLE `damage_details` DISABLE KEYS */;
INSERT INTO `damage_details` VALUES (1,1,4,'2026-07-15',50.000,18.000,18.000,900.000,1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11'),(2,2,3,'2026-07-18',5.000,130.000,150.000,750.000,1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11');
/*!40000 ALTER TABLE `damage_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `damages`
--

DROP TABLE IF EXISTS `damages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `damages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `total_loss` decimal(15,3) DEFAULT '0.000',
  `note` text COLLATE utf8mb4_unicode_ci,
  `employee_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `damage_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `damages_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `damages`
--

LOCK TABLES `damages` WRITE;
/*!40000 ALTER TABLE `damages` DISABLE KEYS */;
INSERT INTO `damages` VALUES (1,'DMG-0001','2026-07-15',900.000,'Salt packets damaged due to moisture',4,1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11','Expired'),(2,'DMG-0002','2026-07-18',750.000,'Oil bottles cracked during storage',4,1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11','Physical');
/*!40000 ALTER TABLE `damages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_partners`
--

DROP TABLE IF EXISTS `delivery_partners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_partners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `commission_percent` decimal(5,2) DEFAULT '0.00',
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_partners`
--

LOCK TABLES `delivery_partners` WRITE;
/*!40000 ALTER TABLE `delivery_partners` DISABLE KEYS */;
INSERT INTO `delivery_partners` VALUES (1,'Swiggy Instamart','+91-9000000001','Bangalore, Karnataka',8.00,1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(2,'Zepto','+91-9000000002','Mumbai, Maharashtra',7.50,1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(3,'Firoz','+91-9000000003','Delhi, India',9.00,1,1,'Live','2026-07-17 12:20:59','2026-07-18 10:23:39');
/*!40000 ALTER TABLE `delivery_partners` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `denominations`
--

DROP TABLE IF EXISTS `denominations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `denominations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` decimal(15,2) DEFAULT '0.00',
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `denominations`
--

LOCK TABLES `denominations` WRITE;
/*!40000 ALTER TABLE `denominations` DISABLE KEYS */;
INSERT INTO `denominations` VALUES (1,'2000 Note',2000.00,'Note',1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(2,'500 Note',500.00,'Note',1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(3,'200 Note',200.00,'Note',1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(4,'100 Note',100.00,'Note',1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(5,'50 Note',50.00,'Note',1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(6,'20 Note',20.00,'Note',1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(7,'10 Note',10.00,'Note',1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(8,'10 Coin',10.00,'Coin',1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(9,'5 Coin',5.00,'Coin',1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(10,'2 Coin',2.00,'Coin',1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(11,'1 Coin',1.00,'Coin',1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59');
/*!40000 ALTER TABLE `denominations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `deposit_withdraws`
--

DROP TABLE IF EXISTS `deposit_withdraws`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `deposit_withdraws` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `type` enum('Deposit','Withdraw') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method_id` bigint unsigned DEFAULT NULL,
  `amount` decimal(15,3) DEFAULT '0.000',
  `note` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_method_id` (`payment_method_id`),
  CONSTRAINT `deposit_withdraws_ibfk_1` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `deposit_withdraws`
--

LOCK TABLES `deposit_withdraws` WRITE;
/*!40000 ALTER TABLE `deposit_withdraws` DISABLE KEYS */;
INSERT INTO `deposit_withdraws` VALUES (1,'DW-0001','2026-07-01','Deposit',1,50000.000,'Opening cash deposit - counter',1,1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45'),(2,'DW-0002','2026-07-05','Deposit',2,20000.000,'Bank deposit from sales',1,1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45'),(3,'DW-0003','2026-07-15','Withdraw',1,5000.000,'Petty cash withdrawal',1,1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45');
/*!40000 ALTER TABLE `deposit_withdraws` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_advance_payments`
--

DROP TABLE IF EXISTS `employee_advance_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_advance_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT '0.00',
  `note` text COLLATE utf8mb4_unicode_ci,
  `payment_method_id` bigint unsigned DEFAULT NULL,
  `employee_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `payment_method_id` (`payment_method_id`),
  CONSTRAINT `employee_advance_payments_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_advance_payments_ibfk_2` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_advance_payments`
--

LOCK TABLES `employee_advance_payments` WRITE;
/*!40000 ALTER TABLE `employee_advance_payments` DISABLE KEYS */;
INSERT INTO `employee_advance_payments` VALUES (1,'ADV-0001','2026-07-20',3000.00,'Advance for medical emergency',1,3,1,1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45');
/*!40000 ALTER TABLE `employee_advance_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expense_categories`
--

DROP TABLE IF EXISTS `expense_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expense_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expense_categories`
--

LOCK TABLES `expense_categories` WRITE;
/*!40000 ALTER TABLE `expense_categories` DISABLE KEYS */;
INSERT INTO `expense_categories` VALUES (1,'Shop Rent','Monthly shop rent payment',1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(2,'Electricity','Electricity bill',1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(3,'Staff Salary','Employee salary expenses',1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(4,'Transport','Delivery and transport costs',1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(5,'Miscellaneous','Other miscellaneous expenses',1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59');
/*!40000 ALTER TABLE `expense_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expenses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `payment_method_id` bigint unsigned DEFAULT NULL,
  `amount` decimal(15,3) DEFAULT '0.000',
  `note` text COLLATE utf8mb4_unicode_ci,
  `attachment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `payment_method_id` (`payment_method_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_ibfk_3` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
INSERT INTO `expenses` VALUES (1,'EXP-0001','2026-07-01',1,2,15000.000,'Monthly shop rent - July 2026',NULL,NULL,1,1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45'),(2,'EXP-0002','2026-07-05',2,2,2800.000,'Electricity bill - June 2026',NULL,NULL,1,1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45'),(3,'EXP-0003','2026-07-10',4,1,800.000,'Transport for purchase delivery',NULL,NULL,1,1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45'),(4,'EXP-0004','2026-07-15',5,1,500.000,'Stationery and packaging material',NULL,NULL,1,1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45'),(5,'EXP-0005','2026-07-31',3,2,30000.000,'Staff salary - July 2026',NULL,NULL,1,1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45');
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fixed_asset_items`
--

DROP TABLE IF EXISTS `fixed_asset_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fixed_asset_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `quantity` decimal(15,3) DEFAULT '0.000',
  `unit_price` decimal(15,3) DEFAULT '0.000',
  `total` decimal(15,3) DEFAULT '0.000',
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fixed_asset_items`
--

LOCK TABLES `fixed_asset_items` WRITE;
/*!40000 ALTER TABLE `fixed_asset_items` DISABLE KEYS */;
INSERT INTO `fixed_asset_items` VALUES (1,'Electronic Weighing Scale','FA-001','Digital weighing scale 30 kg capacity',1.000,3500.000,3500.000,1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11','Equipment',NULL),(2,'CCTV Camera Set','FA-002','4 camera CCTV system',1.000,8000.000,8000.000,1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11','Security',NULL),(3,'Air Conditioner 1.5 Ton','FA-003','Split AC for shop',1.000,32000.000,32000.000,1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11','Appliance',NULL);
/*!40000 ALTER TABLE `fixed_asset_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fixed_asset_stock_in_details`
--

DROP TABLE IF EXISTS `fixed_asset_stock_in_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fixed_asset_stock_in_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `asset_stock_in_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `unit_price` decimal(15,3) DEFAULT '0.000',
  `total` decimal(15,3) DEFAULT '0.000',
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `quantity` decimal(15,3) DEFAULT '0.000',
  PRIMARY KEY (`id`),
  KEY `asset_stock_in_id` (`asset_stock_in_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `fixed_asset_stock_in_details_ibfk_1` FOREIGN KEY (`asset_stock_in_id`) REFERENCES `fixed_asset_stock_ins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fixed_asset_stock_in_details_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `fixed_asset_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fixed_asset_stock_in_details`
--

LOCK TABLES `fixed_asset_stock_in_details` WRITE;
/*!40000 ALTER TABLE `fixed_asset_stock_in_details` DISABLE KEYS */;
INSERT INTO `fixed_asset_stock_in_details` VALUES (1,1,1,3500.000,3500.000,1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11',1.000),(2,1,2,8000.000,8000.000,1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11',1.000),(3,1,3,32000.000,32000.000,1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11',1.000);
/*!40000 ALTER TABLE `fixed_asset_stock_in_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fixed_asset_stock_ins`
--

DROP TABLE IF EXISTS `fixed_asset_stock_ins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fixed_asset_stock_ins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `grand_total` decimal(15,3) DEFAULT '0.000',
  `note` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fixed_asset_stock_ins`
--

LOCK TABLES `fixed_asset_stock_ins` WRITE;
/*!40000 ALTER TABLE `fixed_asset_stock_ins` DISABLE KEYS */;
INSERT INTO `fixed_asset_stock_ins` VALUES (1,'FASI-0001','2026-07-01',43500.000,'Initial asset stock in',1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11');
/*!40000 ALTER TABLE `fixed_asset_stock_ins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fixed_asset_stock_out_details`
--

DROP TABLE IF EXISTS `fixed_asset_stock_out_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fixed_asset_stock_out_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `asset_stock_out_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `unit_price` decimal(15,3) DEFAULT '0.000',
  `total` decimal(15,3) DEFAULT '0.000',
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `quantity` decimal(15,3) DEFAULT '0.000',
  `reason` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `asset_stock_out_id` (`asset_stock_out_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `fixed_asset_stock_out_details_ibfk_1` FOREIGN KEY (`asset_stock_out_id`) REFERENCES `fixed_asset_stock_outs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fixed_asset_stock_out_details_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `fixed_asset_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fixed_asset_stock_out_details`
--

LOCK TABLES `fixed_asset_stock_out_details` WRITE;
/*!40000 ALTER TABLE `fixed_asset_stock_out_details` DISABLE KEYS */;
/*!40000 ALTER TABLE `fixed_asset_stock_out_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fixed_asset_stock_outs`
--

DROP TABLE IF EXISTS `fixed_asset_stock_outs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fixed_asset_stock_outs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `grand_total` decimal(15,3) DEFAULT '0.000',
  `note` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fixed_asset_stock_outs`
--

LOCK TABLES `fixed_asset_stock_outs` WRITE;
/*!40000 ALTER TABLE `fixed_asset_stock_outs` DISABLE KEYS */;
/*!40000 ALTER TABLE `fixed_asset_stock_outs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hold_combo_items`
--

DROP TABLE IF EXISTS `hold_combo_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hold_combo_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint unsigned DEFAULT NULL,
  `combo_sale_item_id` bigint unsigned DEFAULT NULL,
  `combo_item_id` bigint unsigned DEFAULT NULL,
  `combo_item_qty` decimal(15,3) DEFAULT '0.000',
  `combo_item_price` decimal(15,3) DEFAULT '0.000',
  `combo_item_seller_id` bigint unsigned DEFAULT NULL,
  `show_in_invoice` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'Yes',
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `combo_sale_item_id` (`combo_sale_item_id`),
  KEY `combo_item_id` (`combo_item_id`),
  CONSTRAINT `hold_combo_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `holds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hold_combo_items_ibfk_2` FOREIGN KEY (`combo_sale_item_id`) REFERENCES `hold_details` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hold_combo_items_ibfk_3` FOREIGN KEY (`combo_item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hold_combo_items`
--

LOCK TABLES `hold_combo_items` WRITE;
/*!40000 ALTER TABLE `hold_combo_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `hold_combo_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hold_details`
--

DROP TABLE IF EXISTS `hold_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hold_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `holds_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `qty` decimal(15,3) DEFAULT '0.000',
  `menu_price_without_discount` decimal(15,3) DEFAULT '0.000',
  `menu_price_with_discount` decimal(15,3) DEFAULT '0.000',
  `menu_unit_price` decimal(15,3) DEFAULT '0.000',
  `menu_vat_percentage` decimal(5,2) DEFAULT '0.00',
  `item_tax_amount` decimal(15,3) DEFAULT '0.000',
  `menu_discount_value` decimal(15,3) DEFAULT '0.000',
  `discount_amount` decimal(15,3) DEFAULT '0.000',
  `is_promo_item` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `promo_parent_id` bigint unsigned DEFAULT NULL,
  `item_seller_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `discount_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'fixed',
  PRIMARY KEY (`id`),
  KEY `holds_id` (`holds_id`),
  KEY `item_id` (`item_id`),
  KEY `promo_parent_id` (`promo_parent_id`),
  KEY `item_seller_id` (`item_seller_id`),
  CONSTRAINT `hold_details_ibfk_1` FOREIGN KEY (`holds_id`) REFERENCES `holds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hold_details_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hold_details_ibfk_3` FOREIGN KEY (`promo_parent_id`) REFERENCES `hold_details` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hold_details_ibfk_4` FOREIGN KEY (`item_seller_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hold_details`
--

LOCK TABLES `hold_details` WRITE;
/*!40000 ALTER TABLE `hold_details` DISABLE KEYS */;
/*!40000 ALTER TABLE `hold_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `holds`
--

DROP TABLE IF EXISTS `holds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `holds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_date` date DEFAULT NULL,
  `date_time` datetime DEFAULT NULL,
  `due_payment_date` date DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `employee_id` bigint unsigned DEFAULT NULL,
  `sub_total` decimal(15,3) DEFAULT '0.000',
  `paid_amount` decimal(15,3) DEFAULT '0.000',
  `due_amount` decimal(15,3) DEFAULT '0.000',
  `disc` decimal(15,3) DEFAULT '0.000',
  `disc_actual` decimal(15,3) DEFAULT '0.000',
  `vat` decimal(15,3) DEFAULT '0.000',
  `total_payable` decimal(15,3) DEFAULT '0.000',
  `total_item_discount_amount` decimal(15,3) DEFAULT '0.000',
  `sub_total_with_discount` decimal(15,3) DEFAULT '0.000',
  `sub_total_discount_amount` decimal(15,3) DEFAULT '0.000',
  `total_discount_amount` decimal(15,3) DEFAULT '0.000',
  `delivery_charge` decimal(15,3) DEFAULT '0.000',
  `sub_total_discount_value` decimal(15,3) DEFAULT '0.000',
  `delivery_partner_id` bigint unsigned DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `counter_id` bigint unsigned DEFAULT NULL,
  `sub_total_discount_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'fixed',
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `employee_id` (`employee_id`),
  KEY `delivery_partner_id` (`delivery_partner_id`),
  CONSTRAINT `holds_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `holds_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `holds_ibfk_3` FOREIGN KEY (`delivery_partner_id`) REFERENCES `delivery_partners` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `holds`
--

LOCK TABLES `holds` WRITE;
/*!40000 ALTER TABLE `holds` DISABLE KEYS */;
/*!40000 ALTER TABLE `holds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `income_categories`
--

DROP TABLE IF EXISTS `income_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `income_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `income_categories`
--

LOCK TABLES `income_categories` WRITE;
/*!40000 ALTER TABLE `income_categories` DISABLE KEYS */;
INSERT INTO `income_categories` VALUES (1,'Product Sales','Revenue from product sales',1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(2,'Late Fee','Late payment fees from customers',1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(3,'Other Income','Miscellaneous income',1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59');
/*!40000 ALTER TABLE `income_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incomes`
--

DROP TABLE IF EXISTS `incomes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `incomes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `payment_method_id` bigint unsigned DEFAULT NULL,
  `amount` decimal(15,3) DEFAULT '0.000',
  `note` text COLLATE utf8mb4_unicode_ci,
  `attachment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `payment_method_id` (`payment_method_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `incomes_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `income_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incomes_ibfk_2` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incomes_ibfk_3` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incomes`
--

LOCK TABLES `incomes` WRITE;
/*!40000 ALTER TABLE `incomes` DISABLE KEYS */;
INSERT INTO `incomes` VALUES (1,'INC-0001','2026-07-08',2,1,200.000,'Late payment fee from Vikram Malhotra',NULL,NULL,1,1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45'),(2,'INC-0002','2026-07-14',3,1,500.000,'Old packaging material sold',NULL,NULL,1,1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45'),(3,'INC-0003','2026-07-20',3,2,1200.000,'Scrap sale proceeds',NULL,NULL,1,1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45');
/*!40000 ALTER TABLE `incomes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `installment_sale_details`
--

DROP TABLE IF EXISTS `installment_sale_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `installment_sale_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `installment_sale_id` bigint unsigned DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `paid_date` date DEFAULT NULL,
  `amount` decimal(15,3) DEFAULT '0.000',
  `paid_amount` decimal(15,3) DEFAULT '0.000',
  `remaining_amount` decimal(15,3) DEFAULT '0.000',
  `paid_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Unpaid',
  `payment_method_id` bigint unsigned DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `installment_sale_id` (`installment_sale_id`),
  KEY `payment_method_id` (`payment_method_id`),
  CONSTRAINT `installment_sale_details_ibfk_1` FOREIGN KEY (`installment_sale_id`) REFERENCES `installment_sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `installment_sale_details_ibfk_2` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `installment_sale_details`
--

LOCK TABLES `installment_sale_details` WRITE;
/*!40000 ALTER TABLE `installment_sale_details` DISABLE KEYS */;
INSERT INTO `installment_sale_details` VALUES (1,1,'2026-08-16',NULL,141.317,0.000,141.317,'Unpaid',NULL,NULL,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11'),(2,1,'2026-09-16',NULL,141.317,0.000,141.317,'Unpaid',NULL,NULL,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11'),(3,1,'2026-10-16',NULL,141.316,0.000,141.316,'Unpaid',NULL,NULL,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11');
/*!40000 ALTER TABLE `installment_sale_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `installment_sale_payments`
--

DROP TABLE IF EXISTS `installment_sale_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `installment_sale_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `installment_sale_id` bigint unsigned DEFAULT NULL,
  `installment_sale_detail_id` bigint unsigned DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `amount` decimal(15,3) DEFAULT '0.000',
  `payment_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method_id` bigint unsigned DEFAULT NULL,
  `check_issue_date` date DEFAULT NULL,
  `check_expiry_date` date DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `installment_sale_id` (`installment_sale_id`),
  KEY `installment_sale_detail_id` (`installment_sale_detail_id`),
  KEY `payment_method_id` (`payment_method_id`),
  CONSTRAINT `installment_sale_payments_ibfk_1` FOREIGN KEY (`installment_sale_id`) REFERENCES `installment_sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `installment_sale_payments_ibfk_2` FOREIGN KEY (`installment_sale_detail_id`) REFERENCES `installment_sale_details` (`id`) ON DELETE SET NULL,
  CONSTRAINT `installment_sale_payments_ibfk_3` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `installment_sale_payments`
--

LOCK TABLES `installment_sale_payments` WRITE;
/*!40000 ALTER TABLE `installment_sale_payments` DISABLE KEYS */;
INSERT INTO `installment_sale_payments` VALUES (1,1,NULL,'2026-07-16',100.000,'Down_Payment',1,NULL,NULL,NULL,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11');
/*!40000 ALTER TABLE `installment_sale_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `installment_sales`
--

DROP TABLE IF EXISTS `installment_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `installment_sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `date` date DEFAULT NULL,
  `price` decimal(15,3) DEFAULT '0.000',
  `discount_amount` decimal(15,3) DEFAULT '0.000',
  `percentage_of_interest` decimal(5,2) DEFAULT '0.00',
  `interest_amount` decimal(15,3) DEFAULT '0.000',
  `shipping_other` decimal(15,3) DEFAULT '0.000',
  `total` decimal(15,3) DEFAULT '0.000',
  `down_payment` decimal(15,3) DEFAULT '0.000',
  `remaining` decimal(15,3) DEFAULT '0.000',
  `paid_amount` decimal(15,3) DEFAULT '0.000',
  `due_amount` decimal(15,3) DEFAULT '0.000',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `installment_count` int DEFAULT '0',
  `note` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `discount` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `installment_sales_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `installment_sales_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `installment_sales`
--

LOCK TABLES `installment_sales` WRITE;
/*!40000 ALTER TABLE `installment_sales` DISABLE KEYS */;
INSERT INTO `installment_sales` VALUES (1,'INST-0001',12,2,'2026-07-16',499.000,0.000,5.00,24.950,0.000,523.950,100.000,423.950,100.000,423.950,'Active',3,'India Gate Rice installment',1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11',NULL);
/*!40000 ALTER TABLE `installment_sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_hash_chain`
--

DROP TABLE IF EXISTS `invoice_hash_chain`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_hash_chain` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `previous_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zatca_invoice_id` bigint unsigned DEFAULT NULL,
  `chain_index` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_hash_chain`
--

LOCK TABLES `invoice_hash_chain` WRITE;
/*!40000 ALTER TABLE `invoice_hash_chain` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoice_hash_chain` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `item_categories`
--

DROP TABLE IF EXISTS `item_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `sort_id` int DEFAULT '0',
  `company_id` bigint unsigned DEFAULT '1',
  `user_id` bigint unsigned DEFAULT NULL,
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_categories`
--

LOCK TABLES `item_categories` WRITE;
/*!40000 ALTER TABLE `item_categories` DISABLE KEYS */;
INSERT INTO `item_categories` VALUES (1,'Atta & Flour','Wheat flour, atta, maida etc.',1,1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(2,'Rice & Dal','Rice, dal, pulses varieties',2,1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(3,'Oil & Ghee','Cooking oils and ghee',3,1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(4,'Spices & Masala','Spices, masalas, salt, sugar',4,1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(5,'Sugar & Salt','Sugar, salt, jaggery',5,1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(6,'Tea & Coffee','Tea leaves, coffee powder',6,1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(7,'Soap & Detergent','Cleaning and hygiene products',7,1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(8,'Biscuits & Snacks','Biscuits, namkeen, snacks',8,1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56');
/*!40000 ALTER TABLE `item_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `items`
--

DROP TABLE IF EXISTS `items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alternative_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `generic_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiry_date_maintain` tinyint(1) DEFAULT '0',
  `category_id` bigint unsigned DEFAULT NULL,
  `rack_id` bigint unsigned DEFAULT NULL,
  `brand_id` bigint unsigned DEFAULT NULL,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `alert_quantity` decimal(15,3) DEFAULT '0.000',
  `unit_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_unit_id` bigint unsigned DEFAULT NULL,
  `sale_unit_id` bigint unsigned DEFAULT NULL,
  `conversion_rate` decimal(15,3) DEFAULT '1.000',
  `purchase_price` decimal(15,3) DEFAULT '0.000',
  `last_three_purchase_avg` decimal(15,3) DEFAULT '0.000',
  `last_purchase_price` decimal(15,3) DEFAULT '0.000',
  `mrp_price` decimal(15,3) DEFAULT '0.000',
  `sale_price` decimal(15,3) DEFAULT '0.000',
  `profit_margin` decimal(15,3) DEFAULT '0.000',
  `whole_sale_price` decimal(15,3) DEFAULT '0.000',
  `description` text COLLATE utf8mb4_unicode_ci,
  `warranty` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warranty_date` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guarantee` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guarantee_date` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_information` json DEFAULT NULL,
  `tax_string` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `applicable_tax_id` bigint unsigned DEFAULT NULL,
  `hsn_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variation_details` json DEFAULT NULL,
  `enable_disable_status` tinyint(1) DEFAULT '1',
  `parent_id` bigint unsigned DEFAULT NULL,
  `loyalty_point` int DEFAULT '0',
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `stock_quantity` decimal(15,3) DEFAULT '0.000',
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `brand_id` (`brand_id`),
  KEY `rack_id` (`rack_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `purchase_unit_id` (`purchase_unit_id`),
  KEY `sale_unit_id` (`sale_unit_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `item_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `items_ibfk_2` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `items_ibfk_3` FOREIGN KEY (`rack_id`) REFERENCES `racks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `items_ibfk_4` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `items_ibfk_5` FOREIGN KEY (`purchase_unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `items_ibfk_6` FOREIGN KEY (`sale_unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `items_ibfk_7` FOREIGN KEY (`parent_id`) REFERENCES `items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `items`
--

LOCK TABLES `items` WRITE;
/*!40000 ALTER TABLE `items` DISABLE KEYS */;
INSERT INTO `items` VALUES (1,'Aashirvaad Aata 10 Kg','000001','Aashirvaad Wheat Flour','Wheat Flour','General_Product',0,1,1,1,1,5.000,'1',1,1,1.000,320.000,0.000,320.000,380.000,18.750,360.000,'Aashirvaad whole wheat chakki atta 10 kg',NULL,NULL,NULL,NULL,'aashirvaad_aata.webp','[{\"id\": \"4\", \"tax\": \"GST 5%\", \"tax_rate\": 5}]','GST 5%:','Exclusive',4,'1101',NULL,1,NULL,0,1,1,'Live','2026-07-17 12:25:28','2026-07-17 12:25:28',50.000),(2,'India Gate Basmati Rice 5 Kg','000002','Basmati Rice 5 Kg','Basmati Rice','General_Product',1,2,1,8,1,5.000,'1',1,1,1.000,420.000,0.000,420.000,499.000,18.810,475.000,'India Gate Classic Basmati Rice 5 kg',NULL,'day',NULL,'day','1784364168_6a5b3c8885a40.png','[{\"id\": \"4\", \"tax\": \"GST 5%\", \"tax_rate\": 5}]','GST 5%:','Exclusive',4,'1006',NULL,1,NULL,0,1,1,'Live','2026-07-17 12:25:28','2026-07-18 08:42:48',40.000),(3,'Fortune Sunflower Oil 1 Litre','000003','Sunflower Oil 1L','Refined Sunflower Oil','General_Product',1,3,2,3,2,10.000,'1',3,3,1.000,130.000,0.000,130.000,155.000,19.230,148.000,'Fortune refined sunflower oil 1 litre',NULL,'day',NULL,'day','1784364275_6a5b3cf33f329.png','[{\"id\": \"6\", \"tax\": \"GST 18%\", \"tax_rate\": 18}]','GST 18%:','Exclusive',6,'1512',NULL,1,NULL,0,1,1,'Live','2026-07-17 12:25:28','2026-07-18 08:44:35',60.000),(4,'Tata Salt 1 Kg','000004','Tata Namak 1 Kg','Iodised Salt','General_Product',1,5,3,2,3,20.000,'1',1,1,1.000,18.000,0.000,18.000,22.000,22.220,21.000,'Tata Salt iodised 1 kg',NULL,'day',NULL,'day','1784363838_6a5b3b3ec8f9f.png','[]','','Exclusive',NULL,'2501',NULL,1,NULL,0,1,1,'Live','2026-07-17 12:25:28','2026-07-18 08:37:18',120.000),(5,'MDH Garam Masala 100g','000005','MDH Masala 100g','Garam Masala','General_Product',1,4,3,4,3,10.000,'1',2,2,1.000,45.000,0.000,45.000,60.000,33.330,57.000,'MDH Garam Masala 100g pack',NULL,'day',NULL,'day','1784364375_6a5b3d57d8549.png','[{\"id\": \"6\", \"tax\": \"GST 18%\", \"tax_rate\": 18}]','GST 18%:','Exclusive',6,'0910',NULL,1,NULL,0,1,1,'Live','2026-07-17 12:25:28','2026-07-18 08:46:15',80.000);
/*!40000 ALTER TABLE `items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2026_07_18_105107_add_sale_no_to_sales_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_ibfk_1` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_roles`
--

LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
INSERT INTO `model_has_roles` VALUES (1,'App\\Models\\User',1),(2,'App\\Models\\User',2),(3,'App\\Models\\User',3),(4,'App\\Models\\User',4);
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `multiple_currencies`
--

DROP TABLE IF EXISTS `multiple_currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `multiple_currencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `symbol` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exchange_rate` decimal(15,4) DEFAULT '0.0000',
  `is_base` tinyint(1) DEFAULT '0',
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `multiple_currencies`
--

LOCK TABLES `multiple_currencies` WRITE;
/*!40000 ALTER TABLE `multiple_currencies` DISABLE KEYS */;
INSERT INTO `multiple_currencies` VALUES (1,'Indian Rupee','Rs.',1.0000,1,1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(2,'US Dollar','$',83.5000,0,1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59');
/*!40000 ALTER TABLE `multiple_currencies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `outlets`
--

DROP TABLE IF EXISTS `outlets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `outlets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `state_id` bigint unsigned DEFAULT NULL,
  `invoice_scheme_id` bigint unsigned DEFAULT NULL,
  `invoice_layout_id` bigint unsigned DEFAULT NULL,
  `sale_invoice_layout_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `outlet_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outlet_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  PRIMARY KEY (`id`),
  KEY `state_id` (`state_id`),
  CONSTRAINT `outlets_ibfk_1` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `outlets`
--

LOCK TABLES `outlets` WRITE;
/*!40000 ALTER TABLE `outlets` DISABLE KEYS */;
INSERT INTO `outlets` VALUES (1,'Rashan Ki Dukan - Main Branch','main@rashankidukan.com','+91-9876543210','Shop No. 5, Main Market, Laxmi Nagar, New Delhi - 110092',7,NULL,NULL,NULL,1,1,1,'Live','2026-07-17 11:53:54','2026-07-17 11:53:54','Rashan Ki Dukan - Main Branch','RKD-001','Active');
/*!40000 ALTER TABLE `outlets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_methods`
--

DROP TABLE IF EXISTS `payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_methods` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `configuration` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `account_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Enable',
  `is_deletable` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'Yes',
  `sort_id` int DEFAULT '0',
  `current_balance` decimal(15,3) DEFAULT '0.000',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_methods`
--

LOCK TABLES `payment_methods` WRITE;
/*!40000 ALTER TABLE `payment_methods` DISABLE KEYS */;
INSERT INTO `payment_methods` VALUES (1,'Cash','cash',NULL,1,1,1,'Live','2026-07-17 12:20:58','2026-07-17 12:20:58','Cash','Enable','No',1,0.000),(2,'Bank Transfer','bank',NULL,1,1,1,'Live','2026-07-17 12:20:58','2026-07-17 12:20:58','Bank_Account','Enable','No',2,0.000),(3,'UPI','upi',NULL,1,1,1,'Live','2026-07-17 12:20:58','2026-07-17 12:20:58','UPI','Enable','Yes',3,0.000),(4,'Credit Card','card',NULL,1,1,1,'Live','2026-07-17 12:20:58','2026-07-17 12:20:58','Credit_Card','Enable','Yes',4,0.000),(5,'Loyalty Point','loyalty',NULL,1,1,1,'Live','2026-07-17 12:20:58','2026-07-17 12:20:58','Loyalty Point','Enable','No',5,0.000);
/*!40000 ALTER TABLE `payment_methods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=283 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'accounting-account_balance','accounting','web','2026-07-17 10:33:00','2026-07-17 10:33:00'),(2,'accounting-account_statement','accounting','web','2026-07-17 10:33:00','2026-07-17 10:33:00'),(3,'accounting-balancesheet','accounting','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(4,'accounting-trial_balance','accounting','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(5,'accounting-transaction_history','accounting','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(6,'attendance-list','attendance','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(7,'attendance-create','attendance','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(8,'attendance-edit','attendance','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(9,'attendance-show','attendance','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(10,'attendance-destroy','attendance','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(11,'booking-list','booking','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(12,'booking-create','booking','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(13,'booking-edit','booking','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(14,'booking-show','booking','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(15,'booking-destroy','booking','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(16,'brand-list','brand','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(17,'brand-create','brand','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(18,'brand-edit','brand','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(19,'brand-show','brand','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(20,'brand-destroy','brand','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(21,'category-list','category','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(22,'category-create','category','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(23,'category-edit','category','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(24,'category-show','category','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(25,'category-destroy','category','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(26,'customer-list','customer','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(27,'customer-create','customer','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(28,'customer-edit','customer','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(29,'customer-show','customer','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(30,'customer-destroy','customer','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(31,'counter-list','counter','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(32,'counter-create','counter','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(33,'counter-edit','counter','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(34,'counter-show','counter','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(35,'counter-destroy','counter','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(36,'customer_receive-list','customer_receive','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(37,'customer_receive-create','customer_receive','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(38,'customer_receive-edit','customer_receive','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(39,'customer_receive-show','customer_receive','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(40,'customer_receive-destroy','customer_receive','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(41,'dashboard-dashboard','dashboard','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(42,'dashboard-userhome','dashboard','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(43,'damage-list','damage','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(44,'damage-create','damage','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(45,'damage-edit','damage','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(46,'damage-show','damage','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(47,'damage-destroy','damage','web','2026-07-17 10:33:01','2026-07-17 10:33:01'),(48,'delivery_partner-list','delivery_partner','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(49,'delivery_partner-create','delivery_partner','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(50,'delivery_partner-edit','delivery_partner','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(51,'delivery_partner-show','delivery_partner','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(52,'delivery_partner-destroy','delivery_partner','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(53,'denomination-list','denomination','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(54,'denomination-create','denomination','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(55,'denomination-edit','denomination','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(56,'denomination-show','denomination','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(57,'denomination-destroy','denomination','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(58,'deposit_withdraw-list','deposit_withdraw','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(59,'deposit_withdraw-create','deposit_withdraw','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(60,'deposit_withdraw-edit','deposit_withdraw','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(61,'deposit_withdraw-show','deposit_withdraw','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(62,'deposit_withdraw-destroy','deposit_withdraw','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(63,'expense-list','expense','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(64,'expense-create','expense','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(65,'expense-edit','expense','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(66,'expense-show','expense','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(67,'expense-destroy','expense','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(68,'expense_category-list','expense_category','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(69,'expense_category-create','expense_category','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(70,'expense_category-edit','expense_category','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(71,'expense_category-show','expense_category','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(72,'expense_category-destroy','expense_category','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(73,'fixed_asset_item-list','fixed_asset_item','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(74,'fixed_asset_item-create','fixed_asset_item','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(75,'fixed_asset_item-edit','fixed_asset_item','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(76,'fixed_asset_item-show','fixed_asset_item','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(77,'fixed_asset_item-destroy','fixed_asset_item','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(78,'fixed_asset_stock_in-list','fixed_asset_stock_in','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(79,'fixed_asset_stock_in-create','fixed_asset_stock_in','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(80,'fixed_asset_stock_in-edit','fixed_asset_stock_in','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(81,'fixed_asset_stock_in-show','fixed_asset_stock_in','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(82,'fixed_asset_stock_in-destroy','fixed_asset_stock_in','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(83,'fixed_asset_stock_out-list','fixed_asset_stock_out','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(84,'fixed_asset_stock_out-create','fixed_asset_stock_out','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(85,'fixed_asset_stock_out-edit','fixed_asset_stock_out','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(86,'fixed_asset_stock_out-show','fixed_asset_stock_out','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(87,'fixed_asset_stock_out-destroy','fixed_asset_stock_out','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(88,'income-list','income','web','2026-07-17 10:33:02','2026-07-17 10:33:02'),(89,'income-create','income','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(90,'income-edit','income','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(91,'income-show','income','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(92,'income-destroy','income','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(93,'income_category-list','income_category','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(94,'income_category-create','income_category','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(95,'income_category-edit','income_category','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(96,'income_category-show','income_category','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(97,'income_category-destroy','income_category','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(98,'installment_sale-list','installment_sale','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(99,'installment_sale-create','installment_sale','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(100,'installment_sale-edit','installment_sale','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(101,'installment_sale-show','installment_sale','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(102,'installment_sale-destroy','installment_sale','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(103,'item-list','item','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(104,'item-create','item','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(105,'item-edit','item','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(106,'item-show','item','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(107,'item-destroy','item','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(108,'item-import','item','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(109,'item_category-list','item_category','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(110,'item_category-create','item_category','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(111,'item_category-edit','item_category','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(112,'item_category-show','item_category','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(113,'item_category-destroy','item_category','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(114,'marketing-email','marketing','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(115,'marketing-sms','marketing','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(116,'marketing-whatsapp','marketing','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(117,'multiple_currency-list','multiple_currency','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(118,'multiple_currency-create','multiple_currency','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(119,'multiple_currency-edit','multiple_currency','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(120,'multiple_currency-show','multiple_currency','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(121,'multiple_currency-destroy','multiple_currency','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(122,'outlet-list','outlet','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(123,'outlet-create','outlet','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(124,'outlet-edit','outlet','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(125,'outlet-show','outlet','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(126,'outlet-destroy','outlet','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(127,'outlet-enter','outlet','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(128,'payment_method-list','payment_method','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(129,'payment_method-create','payment_method','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(130,'payment_method-edit','payment_method','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(131,'payment_method-show','payment_method','web','2026-07-17 10:33:03','2026-07-17 10:33:03'),(132,'payment_method-destroy','payment_method','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(133,'payment_method-sort','payment_method','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(134,'permission-list','permission','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(135,'permission-create','permission','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(136,'permission-edit','permission','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(137,'permission-show','permission','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(138,'permission-destroy','permission','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(139,'printer-list','printer','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(140,'printer-create','printer','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(141,'printer-edit','printer','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(142,'printer-show','printer','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(143,'printer-destroy','printer','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(144,'promotion-list','promotion','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(145,'promotion-create','promotion','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(146,'promotion-edit','promotion','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(147,'promotion-show','promotion','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(148,'promotion-destroy','promotion','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(149,'purchase-list','purchase','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(150,'purchase-create','purchase','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(151,'purchase-edit','purchase','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(152,'purchase-show','purchase','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(153,'purchase-destroy','purchase','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(154,'purchase_return-list','purchase_return','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(155,'purchase_return-create','purchase_return','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(156,'purchase_return-edit','purchase_return','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(157,'purchase_return-show','purchase_return','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(158,'purchase_return-destroy','purchase_return','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(159,'quotation-list','quotation','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(160,'quotation-create','quotation','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(161,'quotation-edit','quotation','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(162,'quotation-show','quotation','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(163,'quotation-destroy','quotation','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(164,'rack-list','rack','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(165,'rack-create','rack','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(166,'rack-edit','rack','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(167,'rack-show','rack','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(168,'rack-destroy','rack','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(169,'report-register_report','report','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(170,'report-z_report','report','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(171,'report-daily_summary_report','report','web','2026-07-17 10:33:04','2026-07-17 10:33:04'),(172,'report-sale_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(173,'report-due_sale_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(174,'report-final_invoice_due_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(175,'report-service_sale_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(176,'report-combo_service_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(177,'report-stock_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(178,'report-low_stock_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(179,'report-expire_soon_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(180,'report-employee_sale_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(181,'report-customer_receive_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(182,'report-attendance_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(183,'report-product_profit_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(184,'report-supplier_ledger_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(185,'report-supplier_balance_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(186,'report-customer_ledger_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(187,'report-customer_balance_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(188,'report-servicing_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(189,'report-product_sale_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(190,'report-tax_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(191,'report-detailed_sale_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(192,'report-profit_loss_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(193,'report-purchase_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(194,'report-expense_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(195,'report-income_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(196,'report-salary_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(197,'report-purchase_return_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(198,'report-sale_return_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(199,'report-damage_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(200,'report-installment_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(201,'report-installment_due_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(202,'report-item_tracking_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(203,'report-price_history_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(204,'report-cash_flow_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(205,'report-available_loyalty_point_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(206,'report-usage_loyalty_point_report','report','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(207,'role-list','role','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(208,'role-create','role','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(209,'role-edit','role','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(210,'role-show','role','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(211,'role-destroy','role','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(212,'salary-list','salary','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(213,'salary-create','salary','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(214,'salary-edit','salary','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(215,'salary-show','salary','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(216,'salary-destroy','salary','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(217,'employee_advance_payment-list','employee_advance_payment','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(218,'employee_advance_payment-create','employee_advance_payment','web','2026-07-17 10:33:05','2026-07-17 10:33:05'),(219,'employee_advance_payment-edit','employee_advance_payment','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(220,'employee_advance_payment-show','employee_advance_payment','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(221,'employee_advance_payment-destroy','employee_advance_payment','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(222,'sale-list','sale','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(223,'sale-create','sale','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(224,'sale-edit','sale','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(225,'sale-show','sale','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(226,'sale-destroy','sale','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(227,'sale-pos','sale','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(228,'stock-stock','stock','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(229,'stock-low_stock','stock','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(230,'sale_return-list','sale_return','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(231,'sale_return-create','sale_return','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(232,'sale_return-edit','sale_return','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(233,'sale_return-show','sale_return','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(234,'sale_return-destroy','sale_return','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(235,'servicing-list','servicing','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(236,'servicing-create','servicing','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(237,'servicing-edit','servicing','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(238,'servicing-show','servicing','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(239,'servicing-destroy','servicing','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(240,'setting-list','setting','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(241,'setting-create','setting','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(242,'setting-edit','setting','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(243,'setting-show','setting','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(244,'setting-destroy','setting','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(245,'supplier-list','supplier','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(246,'supplier-create','supplier','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(247,'supplier-edit','supplier','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(248,'supplier-show','supplier','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(249,'supplier-destroy','supplier','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(250,'supplier_payment-list','supplier_payment','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(251,'supplier_payment-create','supplier_payment','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(252,'supplier_payment-edit','supplier_payment','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(253,'supplier_payment-show','supplier_payment','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(254,'supplier_payment-destroy','supplier_payment','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(255,'transfer-list','transfer','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(256,'transfer-create','transfer','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(257,'transfer-edit','transfer','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(258,'transfer-show','transfer','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(259,'transfer-destroy','transfer','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(260,'unit-list','unit','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(261,'unit-create','unit','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(262,'unit-edit','unit','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(263,'unit-show','unit','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(264,'unit-destroy','unit','web','2026-07-17 10:33:06','2026-07-17 10:33:06'),(265,'user-list','user','web','2026-07-17 10:33:07','2026-07-17 10:33:07'),(266,'user-create','user','web','2026-07-17 10:33:07','2026-07-17 10:33:07'),(267,'user-edit','user','web','2026-07-17 10:33:07','2026-07-17 10:33:07'),(268,'user-show','user','web','2026-07-17 10:33:07','2026-07-17 10:33:07'),(269,'user-destroy','user','web','2026-07-17 10:33:07','2026-07-17 10:33:07'),(270,'security-uninstall','security','web','2026-07-17 10:33:07','2026-07-17 10:33:07'),(271,'security-update','security','web','2026-07-17 10:33:07','2026-07-17 10:33:07'),(272,'variation_attribute-list','variation_attribute','web','2026-07-17 10:33:07','2026-07-17 10:33:07'),(273,'variation_attribute-create','variation_attribute','web','2026-07-17 10:33:07','2026-07-17 10:33:07'),(274,'variation_attribute-edit','variation_attribute','web','2026-07-17 10:33:07','2026-07-17 10:33:07'),(275,'variation_attribute-show','variation_attribute','web','2026-07-17 10:33:07','2026-07-17 10:33:07'),(276,'variation_attribute-destroy','variation_attribute','web','2026-07-17 10:33:07','2026-07-17 10:33:07'),(277,'warranty-list','warranty','web','2026-07-17 10:33:07','2026-07-17 10:33:07'),(278,'warranty-create','warranty','web','2026-07-17 10:33:07','2026-07-17 10:33:07'),(279,'warranty-edit','warranty','web','2026-07-17 10:33:07','2026-07-17 10:33:07'),(280,'warranty-show','warranty','web','2026-07-17 10:33:07','2026-07-17 10:33:07'),(281,'warranty-destroy','warranty','web','2026-07-17 10:33:07','2026-07-17 10:33:07'),(282,'warranty-checking','warranty','web','2026-07-17 10:33:07','2026-07-17 10:33:07');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `printers`
--

DROP TABLE IF EXISTS `printers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `printers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `connection_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `port` int DEFAULT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `printers`
--

LOCK TABLES `printers` WRITE;
/*!40000 ALTER TABLE `printers` DISABLE KEYS */;
INSERT INTO `printers` VALUES (1,'Main Counter Printer','receipt','Network','192.168.1.100',9100,NULL,1,1,'Live','2026-07-17 12:17:52','2026-07-17 12:17:52');
/*!40000 ALTER TABLE `printers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promotions`
--

DROP TABLE IF EXISTS `promotions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promotions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_value` decimal(15,3) DEFAULT '0.000',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `applicable_items` json DEFAULT NULL,
  `applicable_categories` json DEFAULT NULL,
  `min_purchase_amount` decimal(15,3) DEFAULT '0.000',
  `max_discount_amount` decimal(15,3) DEFAULT '0.000',
  `discount` varchar(55) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `coupon_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promotions`
--

LOCK TABLES `promotions` WRITE;
/*!40000 ALTER TABLE `promotions` DISABLE KEYS */;
INSERT INTO `promotions` VALUES (1,'Monsoon Sale 10%','1','percentage',10.000,'2026-07-15','2026-07-31','Active',1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11',NULL,NULL,0.000,0.000,'10%',NULL),(2,'Weekend Flat 50 Off','1','flat',50.000,'2026-07-19','2026-07-20','Inactive',1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11',NULL,NULL,0.000,0.000,'50',NULL);
/*!40000 ALTER TABLE `promotions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_details`
--

DROP TABLE IF EXISTS `purchase_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `item_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiry_imei_serial` text COLLATE utf8mb4_unicode_ci,
  `unit_price` decimal(15,3) DEFAULT '0.000',
  `quantity_amount` decimal(15,3) DEFAULT '0.000',
  `total` decimal(15,3) DEFAULT '0.000',
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_id` (`purchase_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `purchase_details_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_details_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_details`
--

LOCK TABLES `purchase_details` WRITE;
/*!40000 ALTER TABLE `purchase_details` DISABLE KEYS */;
INSERT INTO `purchase_details` VALUES (1,1,1,'General_Product',NULL,320.000,30.000,9600.000,1,1,1,'Live','2026-07-17 12:33:45','2026-07-17 12:33:45'),(2,1,2,'General_Product',NULL,420.000,26.000,10920.000,1,1,1,'Live','2026-07-17 12:33:45','2026-07-17 12:33:45'),(3,2,3,'General_Product',NULL,130.000,70.000,9100.000,1,1,1,'Live','2026-07-17 12:33:45','2026-07-17 12:33:45'),(4,3,4,'General_Product',NULL,18.000,300.000,5400.000,1,1,1,'Live','2026-07-17 12:33:46','2026-07-17 12:33:46'),(5,3,5,'General_Product',NULL,45.000,200.000,9000.000,1,1,1,'Live','2026-07-17 12:33:46','2026-07-17 12:33:46'),(6,4,1,'General_Product',NULL,320.000,50.000,16000.000,1,1,1,'Live','2026-07-17 12:33:46','2026-07-17 12:33:46'),(7,5,3,'General_Product',NULL,130.000,65.000,8450.000,1,1,1,'Live','2026-07-17 12:33:46','2026-07-17 12:33:46');
/*!40000 ALTER TABLE `purchase_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_payments`
--

DROP TABLE IF EXISTS `purchase_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_id` bigint unsigned DEFAULT NULL,
  `payment_id` bigint unsigned DEFAULT NULL,
  `date` date DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT '0.00',
  `outlet_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_id` (`purchase_id`),
  KEY `payment_id` (`payment_id`),
  CONSTRAINT `purchase_payments_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_payments_ibfk_2` FOREIGN KEY (`payment_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_payments`
--

LOCK TABLES `purchase_payments` WRITE;
/*!40000 ALTER TABLE `purchase_payments` DISABLE KEYS */;
INSERT INTO `purchase_payments` VALUES (1,1,1,'2026-07-01',20400.00,1,1,1,'Live','2026-07-17 12:33:45','2026-07-17 12:33:45',NULL),(2,2,1,'2026-07-03',5000.00,1,1,1,'Live','2026-07-17 12:33:45','2026-07-17 12:33:45',NULL),(3,3,2,'2026-07-05',14400.00,1,1,1,'Live','2026-07-17 12:33:46','2026-07-17 12:33:46',NULL),(4,4,1,'2026-07-12',10000.00,1,1,1,'Live','2026-07-17 12:33:46','2026-07-17 12:33:46',NULL),(5,5,1,'2026-07-15',8450.00,1,1,1,'Live','2026-07-17 12:33:46','2026-07-17 12:33:46',NULL);
/*!40000 ALTER TABLE `purchase_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_return_details`
--

DROP TABLE IF EXISTS `purchase_return_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_return_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pur_return_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `item_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiry_imei_serial` text COLLATE utf8mb4_unicode_ci,
  `expiry_imei_serial_in` text COLLATE utf8mb4_unicode_ci,
  `return_note` text COLLATE utf8mb4_unicode_ci,
  `return_quantity_amount` decimal(15,3) DEFAULT '0.000',
  `unit_price` decimal(15,3) DEFAULT '0.000',
  `total` decimal(15,3) DEFAULT '0.000',
  `return_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pur_return_id` (`pur_return_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `purchase_return_details_ibfk_1` FOREIGN KEY (`pur_return_id`) REFERENCES `purchase_returns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_return_details_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_return_details`
--

LOCK TABLES `purchase_return_details` WRITE;
/*!40000 ALTER TABLE `purchase_return_details` DISABLE KEYS */;
INSERT INTO `purchase_return_details` VALUES (1,1,4,'General_Product',NULL,NULL,NULL,50.000,18.000,900.000,'Returned',1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11');
/*!40000 ALTER TABLE `purchase_return_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_returns`
--

DROP TABLE IF EXISTS `purchase_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_returns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pur_ref_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `date` date DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `return_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_return_amount` decimal(15,3) DEFAULT '0.000',
  `payment_method_id` bigint unsigned DEFAULT NULL,
  `payment_method_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `payment_method_id` (`payment_method_id`),
  CONSTRAINT `purchase_returns_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_returns_ibfk_2` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_returns`
--

LOCK TABLES `purchase_returns` WRITE;
/*!40000 ALTER TABLE `purchase_returns` DISABLE KEYS */;
INSERT INTO `purchase_returns` VALUES (1,'PRR-0001','PUR-0003',3,'2026-07-09','2026-07-05','Returned',900.000,1,'Cash',NULL,'50 kg salt damaged batch returned',1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11');
/*!40000 ALTER TABLE `purchase_returns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchases`
--

DROP TABLE IF EXISTS `purchases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `date` date DEFAULT NULL,
  `other` decimal(15,3) DEFAULT '0.000',
  `grand_total` decimal(15,3) DEFAULT '0.000',
  `paid` decimal(15,3) DEFAULT '0.000',
  `due_amount` decimal(15,3) DEFAULT '0.000',
  `note` text COLLATE utf8mb4_unicode_ci,
  `discount` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchases`
--

LOCK TABLES `purchases` WRITE;
/*!40000 ALTER TABLE `purchases` DISABLE KEYS */;
INSERT INTO `purchases` VALUES (1,'PUR-0001','INV-AG-001',1,'2026-07-01',0.000,20400.000,20400.000,0.000,'July batch - atta and rice','0',NULL,1,1,1,'Live','2026-07-17 12:33:45','2026-07-17 12:33:45','Received'),(2,'PUR-0002','INV-SH-011',2,'2026-07-03',0.000,9100.000,5000.000,4100.000,'Oil stock July','0',NULL,1,1,1,'Live','2026-07-17 12:33:45','2026-07-17 12:33:45','Pending'),(3,'PUR-0003','INV-GU-021',3,'2026-07-05',0.000,14400.000,14400.000,0.000,'Salt 300kg + Masala 200pkt','0',NULL,1,1,1,'Live','2026-07-17 12:33:45','2026-07-17 12:33:45','Received'),(4,'PUR-0004','INV-AG-002',1,'2026-07-12',200.000,16200.000,10000.000,6200.000,'Second batch atta + transport','0',NULL,1,1,1,'Live','2026-07-17 12:33:46','2026-07-17 12:33:46','Pending'),(5,'PUR-0005','INV-SH-012',2,'2026-07-15',0.000,8450.000,8450.000,0.000,'Oil restock mid-July','0',NULL,1,1,1,'Live','2026-07-17 12:33:46','2026-07-17 12:33:46','Received');
/*!40000 ALTER TABLE `purchases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pwa_settings`
--

DROP TABLE IF EXISTS `pwa_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pwa_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned DEFAULT '1',
  `app_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `short_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `theme_color` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `background_color` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pwa_settings`
--

LOCK TABLES `pwa_settings` WRITE;
/*!40000 ALTER TABLE `pwa_settings` DISABLE KEYS */;
INSERT INTO `pwa_settings` VALUES (1,1,'Rashan ki dukan','Rasanclub','#7367f0','#ffffff','icons/1','http://localhost:8080','2026-07-17 09:19:15','2026-07-18 09:05:20'),(2,1,'Rashan Ki Dukan','RKD','#2E7D32','#FFFFFF',NULL,'/','2026-07-17 12:40:11','2026-07-17 12:40:11');
/*!40000 ALTER TABLE `pwa_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotation_details`
--

DROP TABLE IF EXISTS `quotation_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quotation_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `unit_price` decimal(15,3) DEFAULT '0.000',
  `total` decimal(15,3) DEFAULT '0.000',
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quotation_id` (`quotation_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `quotation_details_ibfk_1` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quotation_details_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_details`
--

LOCK TABLES `quotation_details` WRITE;
/*!40000 ALTER TABLE `quotation_details` DISABLE KEYS */;
INSERT INTO `quotation_details` VALUES (1,1,1,380.000,3800.000,1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11'),(2,1,4,22.000,190.000,1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11');
/*!40000 ALTER TABLE `quotation_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotations`
--

DROP TABLE IF EXISTS `quotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quotations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `date` date DEFAULT NULL,
  `grand_total` decimal(15,3) DEFAULT '0.000',
  `note` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `discount` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `quotations_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotations`
--

LOCK TABLES `quotations` WRITE;
/*!40000 ALTER TABLE `quotations` DISABLE KEYS */;
INSERT INTO `quotations` VALUES (1,'QUO-0001',10,'2026-07-14',3990.000,'Bulk order quotation for Deepak Jain',1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11','0');
/*!40000 ALTER TABLE `quotations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `racks`
--

DROP TABLE IF EXISTS `racks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `racks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `company_id` bigint unsigned DEFAULT '1',
  `user_id` bigint unsigned DEFAULT NULL,
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `racks`
--

LOCK TABLES `racks` WRITE;
/*!40000 ALTER TABLE `racks` DISABLE KEYS */;
INSERT INTO `racks` VALUES (1,'Rack A - Atta/Rice','Front rack for staples',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(2,'Rack B - Oil/Ghee','Oil and ghee section',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(3,'Rack C - Spices','Masala and spice rack',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(4,'Rack D - Packaged','Packaged goods rack',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(5,'Store Room','Back store room',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56');
/*!40000 ALTER TABLE `racks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `registers`
--

DROP TABLE IF EXISTS `registers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `registers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `opening_balance` decimal(15,2) DEFAULT '0.00',
  `closing_balance` decimal(15,2) DEFAULT '0.00',
  `sale_paid_amount` decimal(15,2) DEFAULT '0.00',
  `refund_amount` decimal(15,2) DEFAULT '0.00',
  `customer_due_receive` decimal(15,2) DEFAULT '0.00',
  `total_purchase` decimal(15,2) DEFAULT '0.00',
  `total_downpayment` decimal(15,2) DEFAULT '0.00',
  `total_installmentcollection` decimal(15,2) DEFAULT '0.00',
  `total_servicing` decimal(15,2) DEFAULT '0.00',
  `total_purchase_return` decimal(15,2) DEFAULT '0.00',
  `total_due_payment` decimal(15,2) DEFAULT '0.00',
  `total_expense` decimal(15,2) DEFAULT '0.00',
  `register_status` tinyint(1) DEFAULT '1',
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `counter_id` bigint unsigned DEFAULT NULL,
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `counter_id` (`counter_id`),
  CONSTRAINT `registers_ibfk_1` FOREIGN KEY (`counter_id`) REFERENCES `counters` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `registers`
--

LOCK TABLES `registers` WRITE;
/*!40000 ALTER TABLE `registers` DISABLE KEYS */;
INSERT INTO `registers` VALUES (1,5000.00,10904.00,5904.00,0.00,500.00,0.00,0.00,0.00,0.00,0.00,0.00,800.00,2,1,1,1,1,'Live','2026-07-10 09:00:00','2026-07-10 20:00:00'),(2,5000.00,8000.00,3000.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,2,2,1,1,1,'Live','2026-07-11 09:00:00','2026-07-11 20:00:00'),(3,5000.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,1,1,1,1,1,'Live','2026-07-17 09:00:00','2026-07-17 09:00:00');
/*!40000 ALTER TABLE `registers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `role_has_permissions_ibfk_1` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
INSERT INTO `role_has_permissions` VALUES (1,1),(2,1),(3,1),(4,1),(5,1),(6,1),(7,1),(8,1),(9,1),(10,1),(11,1),(12,1),(13,1),(14,1),(15,1),(16,1),(17,1),(18,1),(19,1),(20,1),(21,1),(22,1),(23,1),(24,1),(25,1),(26,1),(27,1),(28,1),(29,1),(30,1),(31,1),(32,1),(33,1),(34,1),(35,1),(36,1),(37,1),(38,1),(39,1),(40,1),(41,1),(42,1),(43,1),(44,1),(45,1),(46,1),(47,1),(48,1),(49,1),(50,1),(51,1),(52,1),(53,1),(54,1),(55,1),(56,1),(57,1),(58,1),(59,1),(60,1),(61,1),(62,1),(63,1),(64,1),(65,1),(66,1),(67,1),(68,1),(69,1),(70,1),(71,1),(72,1),(73,1),(74,1),(75,1),(76,1),(77,1),(78,1),(79,1),(80,1),(81,1),(82,1),(83,1),(84,1),(85,1),(86,1),(87,1),(88,1),(89,1),(90,1),(91,1),(92,1),(93,1),(94,1),(95,1),(96,1),(97,1),(98,1),(99,1),(100,1),(101,1),(102,1),(103,1),(104,1),(105,1),(106,1),(107,1),(108,1),(109,1),(110,1),(111,1),(112,1),(113,1),(114,1),(115,1),(116,1),(117,1),(118,1),(119,1),(120,1),(121,1),(122,1),(123,1),(124,1),(125,1),(126,1),(127,1),(128,1),(129,1),(130,1),(131,1),(132,1),(133,1),(134,1),(135,1),(136,1),(137,1),(138,1),(139,1),(140,1),(141,1),(142,1),(143,1),(144,1),(145,1),(146,1),(147,1),(148,1),(149,1),(150,1),(151,1),(152,1),(153,1),(154,1),(155,1),(156,1),(157,1),(158,1),(159,1),(160,1),(161,1),(162,1),(163,1),(164,1),(165,1),(166,1),(167,1),(168,1),(169,1),(170,1),(171,1),(172,1),(173,1),(174,1),(175,1),(176,1),(177,1),(178,1),(179,1),(180,1),(181,1),(182,1),(183,1),(184,1),(185,1),(186,1),(187,1),(188,1),(189,1),(190,1),(191,1),(192,1),(193,1),(194,1),(195,1),(196,1),(197,1),(198,1),(199,1),(200,1),(201,1),(202,1),(203,1),(204,1),(205,1),(206,1),(207,1),(208,1),(209,1),(210,1),(211,1),(212,1),(213,1),(214,1),(215,1),(216,1),(217,1),(218,1),(219,1),(220,1),(221,1),(222,1),(223,1),(224,1),(225,1),(226,1),(227,1),(228,1),(229,1),(230,1),(231,1),(232,1),(233,1),(234,1),(235,1),(236,1),(237,1),(238,1),(239,1),(240,1),(241,1),(242,1),(243,1),(244,1),(245,1),(246,1),(247,1),(248,1),(249,1),(250,1),(251,1),(252,1),(253,1),(254,1),(255,1),(256,1),(257,1),(258,1),(259,1),(260,1),(261,1),(262,1),(263,1),(264,1),(265,1),(266,1),(267,1),(268,1),(269,1),(270,1),(271,1),(272,1),(273,1),(274,1),(275,1),(276,1),(277,1),(278,1),(279,1),(280,1),(281,1),(282,1),(6,2),(7,2),(8,2),(9,2),(10,2),(11,2),(12,2),(13,2),(14,2),(15,2),(16,2),(17,2),(18,2),(19,2),(20,2),(26,2),(27,2),(28,2),(29,2),(30,2),(36,2),(37,2),(38,2),(39,2),(40,2),(41,2),(42,2),(43,2),(44,2),(45,2),(46,2),(47,2),(48,2),(49,2),(50,2),(51,2),(52,2),(53,2),(54,2),(55,2),(56,2),(57,2),(58,2),(59,2),(60,2),(61,2),(62,2),(63,2),(64,2),(65,2),(66,2),(67,2),(68,2),(69,2),(70,2),(71,2),(72,2),(73,2),(74,2),(75,2),(76,2),(77,2),(78,2),(79,2),(80,2),(81,2),(82,2),(83,2),(84,2),(85,2),(86,2),(87,2),(88,2),(89,2),(90,2),(91,2),(92,2),(93,2),(94,2),(95,2),(96,2),(97,2),(98,2),(99,2),(100,2),(101,2),(102,2),(103,2),(104,2),(105,2),(106,2),(107,2),(108,2),(109,2),(110,2),(111,2),(112,2),(113,2),(144,2),(145,2),(146,2),(147,2),(148,2),(149,2),(150,2),(151,2),(152,2),(153,2),(154,2),(155,2),(156,2),(157,2),(158,2),(159,2),(160,2),(161,2),(162,2),(163,2),(164,2),(165,2),(166,2),(167,2),(168,2),(169,2),(170,2),(171,2),(172,2),(173,2),(175,2),(177,2),(178,2),(179,2),(180,2),(181,2),(182,2),(183,2),(184,2),(185,2),(186,2),(187,2),(188,2),(189,2),(190,2),(191,2),(192,2),(193,2),(194,2),(195,2),(196,2),(197,2),(198,2),(199,2),(200,2),(201,2),(202,2),(203,2),(204,2),(205,2),(206,2),(212,2),(213,2),(214,2),(215,2),(216,2),(217,2),(218,2),(219,2),(220,2),(221,2),(222,2),(223,2),(224,2),(225,2),(226,2),(227,2),(228,2),(229,2),(230,2),(231,2),(232,2),(233,2),(234,2),(235,2),(236,2),(237,2),(238,2),(239,2),(245,2),(246,2),(247,2),(248,2),(249,2),(250,2),(251,2),(252,2),(253,2),(254,2),(255,2),(256,2),(257,2),(258,2),(259,2),(260,2),(261,2),(262,2),(263,2),(264,2),(277,2),(278,2),(279,2),(280,2),(281,2),(282,2),(11,3),(12,3),(26,3),(27,3),(29,3),(31,3),(41,3),(42,3),(53,3),(128,3),(159,3),(160,3),(162,3),(222,3),(223,3),(225,3),(227,3),(228,3),(229,3),(230,3),(231,3),(233,3),(16,4),(17,4),(18,4),(19,4),(20,4),(41,4),(43,4),(44,4),(45,4),(46,4),(47,4),(73,4),(74,4),(75,4),(76,4),(77,4),(78,4),(79,4),(80,4),(81,4),(82,4),(83,4),(84,4),(85,4),(86,4),(87,4),(103,4),(104,4),(105,4),(106,4),(107,4),(108,4),(109,4),(110,4),(111,4),(112,4),(113,4),(149,4),(150,4),(151,4),(152,4),(153,4),(154,4),(155,4),(156,4),(157,4),(158,4),(164,4),(165,4),(166,4),(167,4),(168,4),(177,4),(178,4),(179,4),(184,4),(185,4),(193,4),(199,4),(202,4),(203,4),(228,4),(229,4),(245,4),(246,4),(247,4),(248,4),(249,4),(250,4),(251,4),(252,4),(253,4),(254,4),(255,4),(256,4),(257,4),(258,4),(259,4),(260,4),(261,4),(262,4),(263,4),(264,4),(272,4),(273,4),(274,4),(275,4),(276,4);
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Super Admin','web','2026-07-17 10:33:07','2026-07-17 10:33:07'),(2,'Manager','web','2026-07-17 12:17:52','2026-07-17 12:17:52'),(3,'Cashier','web','2026-07-17 12:17:52','2026-07-17 12:17:52'),(4,'Stock Manager','web','2026-07-17 12:17:52','2026-07-17 12:17:52');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salaries`
--

DROP TABLE IF EXISTS `salaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salaries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` int DEFAULT NULL,
  `month` int DEFAULT NULL,
  `generated_date` date DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT '0.00',
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salaries`
--

LOCK TABLES `salaries` WRITE;
/*!40000 ALTER TABLE `salaries` DISABLE KEYS */;
INSERT INTO `salaries` VALUES (1,'SAL-2026-07',2026,7,'2026-07-31',55000.00,1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45');
/*!40000 ALTER TABLE `salaries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salary_items`
--

DROP TABLE IF EXISTS `salary_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salary_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `salary_id` bigint unsigned DEFAULT NULL,
  `employee_id` bigint unsigned DEFAULT NULL,
  `salary_amount` decimal(15,2) DEFAULT '0.00',
  `overtime_rate` decimal(15,2) DEFAULT '0.00',
  `overtime_hour` decimal(5,2) DEFAULT '0.00',
  `additional_amount` decimal(15,2) DEFAULT '0.00',
  `deduction_amount` decimal(15,2) DEFAULT '0.00',
  `absent_day` int DEFAULT '0',
  `absent_day_amount` decimal(15,2) DEFAULT '0.00',
  `tips` decimal(15,2) DEFAULT '0.00',
  `advance_taken` decimal(15,2) DEFAULT '0.00',
  `net_salary` decimal(15,2) DEFAULT '0.00',
  `note` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `salary_id` (`salary_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `salary_items_ibfk_1` FOREIGN KEY (`salary_id`) REFERENCES `salaries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_items_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salary_items`
--

LOCK TABLES `salary_items` WRITE;
/*!40000 ALTER TABLE `salary_items` DISABLE KEYS */;
INSERT INTO `salary_items` VALUES (1,1,2,18000.00,0.00,0.00,500.00,0.00,0,0.00,0.00,0.00,18500.00,'July salary - Manager',1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45'),(2,1,3,12000.00,0.00,2.00,0.00,0.00,0,0.00,200.00,0.00,12200.00,'July salary - Cashier (2h OT)',1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45'),(3,1,4,15000.00,0.00,0.00,0.00,200.00,1,600.00,0.00,0.00,14200.00,'July salary - 1 day absent',1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45');
/*!40000 ALTER TABLE `salary_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salary_payments`
--

DROP TABLE IF EXISTS `salary_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salary_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `salary_id` bigint unsigned DEFAULT NULL,
  `payment_method_id` bigint unsigned DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT '0.00',
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `salary_id` (`salary_id`),
  KEY `payment_method_id` (`payment_method_id`),
  CONSTRAINT `salary_payments_ibfk_1` FOREIGN KEY (`salary_id`) REFERENCES `salaries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_payments_ibfk_2` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salary_payments`
--

LOCK TABLES `salary_payments` WRITE;
/*!40000 ALTER TABLE `salary_payments` DISABLE KEYS */;
INSERT INTO `salary_payments` VALUES (1,1,2,55000.00,1,1,'Live','2026-07-17 12:35:45','2026-07-17 12:35:45');
/*!40000 ALTER TABLE `salary_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_details`
--

DROP TABLE IF EXISTS `sale_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sales_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `qty` decimal(15,3) DEFAULT '0.000',
  `menu_price_without_discount` decimal(15,3) DEFAULT '0.000',
  `menu_price_with_discount` decimal(15,3) DEFAULT '0.000',
  `menu_unit_price` decimal(15,3) DEFAULT '0.000',
  `purchase_price` decimal(15,3) DEFAULT '0.000',
  `menu_vat_percentage` decimal(5,2) DEFAULT '0.00',
  `item_tax_amount` decimal(15,3) DEFAULT '0.000',
  `menu_discount_value` decimal(15,3) DEFAULT '0.000',
  `discount_amount` decimal(15,3) DEFAULT '0.000',
  `loyalty_point_earn` decimal(15,3) DEFAULT '0.000',
  `is_promo_item` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `promo_parent_id` bigint unsigned DEFAULT NULL,
  `item_seller_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `discount_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'fixed',
  PRIMARY KEY (`id`),
  KEY `sales_id` (`sales_id`),
  KEY `item_id` (`item_id`),
  KEY `promo_parent_id` (`promo_parent_id`),
  KEY `item_seller_id` (`item_seller_id`),
  CONSTRAINT `sale_details_ibfk_1` FOREIGN KEY (`sales_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sale_details_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sale_details_ibfk_3` FOREIGN KEY (`promo_parent_id`) REFERENCES `sale_details` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sale_details_ibfk_4` FOREIGN KEY (`item_seller_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_details`
--

LOCK TABLES `sale_details` WRITE;
/*!40000 ALTER TABLE `sale_details` DISABLE KEYS */;
INSERT INTO `sale_details` VALUES (1,1,1,2.000,380.000,380.000,380.000,320.000,0.00,0.000,0.000,0.000,0.000,'No',NULL,NULL,1,1,1,'Live','2026-07-17 12:34:53','2026-07-17 12:34:53','fixed'),(2,1,4,1.000,22.000,22.000,22.000,18.000,0.00,0.000,0.000,0.000,0.000,'No',NULL,NULL,1,1,1,'Live','2026-07-17 12:34:53','2026-07-17 12:34:53','fixed'),(3,2,2,1.000,499.000,499.000,499.000,420.000,0.00,0.000,0.000,0.000,0.000,'No',NULL,NULL,1,1,1,'Live','2026-07-17 12:34:53','2026-07-17 12:34:53','fixed'),(4,2,3,1.000,155.000,155.000,155.000,130.000,0.00,0.000,0.000,0.000,0.000,'No',NULL,NULL,1,1,1,'Live','2026-07-17 12:34:53','2026-07-17 12:34:53','fixed'),(5,3,5,1.000,60.000,60.000,60.000,45.000,0.00,0.000,0.000,0.000,0.000,'No',NULL,NULL,1,1,1,'Live','2026-07-17 12:34:53','2026-07-17 12:34:53','fixed'),(6,3,4,2.000,22.000,22.000,22.000,18.000,0.00,0.000,0.000,0.000,0.000,'No',NULL,NULL,1,1,1,'Live','2026-07-17 12:34:53','2026-07-17 12:34:53','fixed'),(7,4,2,1.000,499.000,499.000,499.000,420.000,0.00,0.000,0.000,0.000,0.000,'No',NULL,NULL,1,1,1,'Live','2026-07-17 12:34:53','2026-07-17 12:34:53','fixed'),(8,4,1,2.000,380.000,380.000,380.000,320.000,0.00,0.000,0.000,0.000,0.000,'No',NULL,NULL,1,1,1,'Live','2026-07-17 12:34:53','2026-07-17 12:34:53','fixed'),(9,5,1,2.000,380.000,380.000,380.000,320.000,0.00,0.000,0.000,0.000,0.000,'No',NULL,NULL,1,1,1,'Live','2026-07-17 12:34:53','2026-07-17 12:34:53','fixed'),(10,5,3,4.000,155.000,155.000,155.000,130.000,0.00,0.000,0.000,0.000,0.000,'No',NULL,NULL,1,1,1,'Live','2026-07-17 12:34:53','2026-07-17 12:34:53','fixed'),(11,5,5,5.000,60.000,60.000,60.000,45.000,0.00,0.000,0.000,0.000,0.000,'No',NULL,NULL,1,1,1,'Live','2026-07-17 12:34:53','2026-07-17 12:34:53','fixed'),(12,6,1,1.000,380.000,380.000,380.000,320.000,5.00,19.000,0.000,0.000,0.000,'No',NULL,NULL,1,1,1,'Live','2026-07-18 11:52:03','2026-07-18 11:52:03','fixed'),(13,7,2,1.000,499.000,499.000,499.000,420.000,5.00,24.950,0.000,0.000,0.000,'No',NULL,NULL,1,1,1,'Live','2026-07-18 11:52:09','2026-07-18 11:52:09','fixed'),(14,8,2,1.000,499.000,499.000,499.000,420.000,5.00,24.950,0.000,0.000,0.000,'No',NULL,NULL,1,1,1,'Live','2026-07-18 11:52:15','2026-07-18 11:52:15','fixed'),(15,9,1,1.000,380.000,380.000,380.000,320.000,5.00,19.000,0.000,0.000,0.000,'No',NULL,NULL,1,1,1,'Live','2026-07-18 11:55:37','2026-07-18 11:55:37','fixed'),(16,10,3,1.000,155.000,155.000,155.000,130.000,18.00,27.900,0.000,0.000,0.000,'No',NULL,NULL,1,1,1,'Live','2026-07-18 12:07:15','2026-07-18 12:07:15','fixed'),(17,11,4,1.000,22.000,22.000,22.000,18.000,0.00,0.000,0.000,0.000,0.000,'No',NULL,NULL,1,1,1,'Live','2026-07-18 12:17:27','2026-07-18 12:17:27','fixed');
/*!40000 ALTER TABLE `sale_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_payments`
--

DROP TABLE IF EXISTS `sale_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint unsigned DEFAULT NULL,
  `payment_id` bigint unsigned DEFAULT NULL,
  `date` date DEFAULT NULL,
  `amount` decimal(15,3) DEFAULT '0.000',
  `multi_currency` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `multi_currency_rate` decimal(15,4) DEFAULT '0.0000',
  `usage_point` decimal(15,3) DEFAULT '0.000',
  `note` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `payment_id` (`payment_id`),
  CONSTRAINT `sale_payments_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sale_payments_ibfk_2` FOREIGN KEY (`payment_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_payments`
--

LOCK TABLES `sale_payments` WRITE;
/*!40000 ALTER TABLE `sale_payments` DISABLE KEYS */;
INSERT INTO `sale_payments` VALUES (1,1,1,'2026-07-10',798.000,'No',0.0000,0.000,NULL,1,1,1,'Live','2026-07-17 12:34:53','2026-07-17 12:34:53',NULL),(2,2,3,'2026-07-10',654.000,'No',0.0000,0.000,NULL,1,1,1,'Live','2026-07-17 12:34:53','2026-07-17 12:34:53',NULL),(3,3,1,'2026-07-11',104.000,'No',0.0000,0.000,NULL,1,1,1,'Live','2026-07-17 12:34:53','2026-07-17 12:34:53',NULL),(4,4,1,'2026-07-12',500.000,'No',0.0000,0.000,NULL,1,1,1,'Live','2026-07-17 12:34:53','2026-07-17 12:34:53',NULL),(5,5,2,'2026-07-13',2090.000,'No',0.0000,0.000,NULL,1,1,1,'Live','2026-07-17 12:34:53','2026-07-17 12:34:53',NULL),(6,6,1,'2026-07-18',399.000,'No',0.0000,0.000,NULL,1,1,1,'Live','2026-07-18 11:52:03','2026-07-18 11:52:03',NULL),(7,7,1,'2026-07-18',523.950,'No',0.0000,0.000,NULL,1,1,1,'Live','2026-07-18 11:52:09','2026-07-18 11:52:09',NULL),(8,8,1,'2026-07-18',523.950,'No',0.0000,0.000,NULL,1,1,1,'Live','2026-07-18 11:52:15','2026-07-18 11:52:15',NULL),(9,9,1,'2026-07-18',399.000,'No',0.0000,0.000,NULL,1,1,1,'Live','2026-07-18 11:55:37','2026-07-18 11:55:37',NULL),(10,10,1,'2026-07-18',182.900,'No',0.0000,0.000,NULL,1,1,1,'Live','2026-07-18 12:07:15','2026-07-18 12:07:15',NULL),(11,11,1,'2026-07-18',22.000,'No',0.0000,0.000,NULL,1,1,1,'Live','2026-07-18 12:17:27','2026-07-18 12:17:27',NULL);
/*!40000 ALTER TABLE `sale_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_return_details`
--

DROP TABLE IF EXISTS `sale_return_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_return_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sale_return_id` bigint unsigned DEFAULT NULL,
  `sale_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `sale_quantity_amount` decimal(15,3) DEFAULT '0.000',
  `return_quantity_amount` decimal(15,3) DEFAULT '0.000',
  `unit_price_in_sale` decimal(15,3) DEFAULT '0.000',
  `unit_price_in_return` decimal(15,3) DEFAULT '0.000',
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_return_id` (`sale_return_id`),
  KEY `sale_id` (`sale_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `sale_return_details_ibfk_1` FOREIGN KEY (`sale_return_id`) REFERENCES `sale_returns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sale_return_details_ibfk_2` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sale_return_details_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_return_details`
--

LOCK TABLES `sale_return_details` WRITE;
/*!40000 ALTER TABLE `sale_return_details` DISABLE KEYS */;
INSERT INTO `sale_return_details` VALUES (1,1,3,5,1.000,1.000,60.000,60.000,1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11');
/*!40000 ALTER TABLE `sale_return_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_returns`
--

DROP TABLE IF EXISTS `sale_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_returns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_id` bigint unsigned DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `date` date DEFAULT NULL,
  `total_return_amount` decimal(15,3) DEFAULT '0.000',
  `paid` decimal(15,3) DEFAULT '0.000',
  `due` decimal(15,3) DEFAULT '0.000',
  `payment_method_id` bigint unsigned DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `customer_id` (`customer_id`),
  KEY `payment_method_id` (`payment_method_id`),
  CONSTRAINT `sale_returns_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sale_returns_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sale_returns_ibfk_3` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_returns`
--

LOCK TABLES `sale_returns` WRITE;
/*!40000 ALTER TABLE `sale_returns` DISABLE KEYS */;
INSERT INTO `sale_returns` VALUES (1,'SRT-0001',3,3,'2026-07-12',60.000,60.000,0.000,1,'MDH masala returned - quality issue',1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11');
/*!40000 ALTER TABLE `sale_returns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales`
--

DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_date` date DEFAULT NULL,
  `date_time` datetime DEFAULT NULL,
  `order_time` datetime DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `due_date_time` datetime DEFAULT NULL,
  `order_date_time` datetime DEFAULT NULL,
  `close_time` datetime DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `employee_id` bigint unsigned DEFAULT NULL,
  `sub_total` decimal(15,3) DEFAULT '0.000',
  `given_amount` decimal(15,3) DEFAULT '0.000',
  `paid_amount` decimal(15,3) DEFAULT '0.000',
  `change_amount` decimal(15,3) DEFAULT '0.000',
  `previous_due` decimal(15,3) DEFAULT '0.000',
  `due_amount` decimal(15,3) DEFAULT '0.000',
  `disc` decimal(15,3) DEFAULT '0.000',
  `disc_actual` decimal(15,3) DEFAULT '0.000',
  `vat` decimal(15,3) DEFAULT '0.000',
  `rounding` decimal(15,3) DEFAULT '0.000',
  `total_payable` decimal(15,3) DEFAULT '0.000',
  `total_item_discount_amount` decimal(15,3) DEFAULT '0.000',
  `sub_total_with_discount` decimal(15,3) DEFAULT '0.000',
  `sub_total_discount_amount` decimal(15,3) DEFAULT '0.000',
  `total_discount_amount` decimal(15,3) DEFAULT '0.000',
  `delivery_charge` decimal(15,3) DEFAULT '0.000',
  `sub_total_discount_value` decimal(15,3) DEFAULT '0.000',
  `grand_total` decimal(15,3) DEFAULT '0.000',
  `sale_vat_objects` json DEFAULT NULL,
  `delivery_partner_id` bigint unsigned DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `counter_id` bigint unsigned DEFAULT NULL,
  `table_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `booking_id` bigint unsigned DEFAULT NULL,
  `sub_total_discount_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'fixed',
  `delivery_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `employee_id` (`employee_id`),
  KEY `delivery_partner_id` (`delivery_partner_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_ibfk_3` FOREIGN KEY (`delivery_partner_id`) REFERENCES `delivery_partners` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales`
--

LOCK TABLES `sales` WRITE;
/*!40000 ALTER TABLE `sales` DISABLE KEYS */;
INSERT INTO `sales` VALUES (1,'INV-0001','SALE-2026-000001','2026-07-10','2026-07-10 10:15:00',NULL,NULL,NULL,NULL,NULL,1,1,798.000,800.000,798.000,2.000,0.000,0.000,0.000,0.000,0.000,0.000,798.000,0.000,798.000,0.000,0.000,0.000,0.000,798.000,NULL,NULL,NULL,1,1,1,'Live','2026-07-17 12:34:52','2026-07-18 10:52:34',NULL,NULL,NULL,'fixed',NULL),(2,'INV-0002','SALE-2026-000002','2026-07-10','2026-07-10 11:30:00',NULL,NULL,NULL,NULL,NULL,2,1,654.000,654.000,654.000,0.000,0.000,0.000,0.000,0.000,0.000,0.000,654.000,0.000,654.000,0.000,0.000,0.000,0.000,654.000,NULL,NULL,NULL,1,1,1,'Live','2026-07-17 12:34:53','2026-07-18 10:52:34',NULL,NULL,NULL,'fixed',NULL),(3,'INV-0003','SALE-2026-000003','2026-07-11','2026-07-11 09:45:00',NULL,NULL,NULL,NULL,NULL,3,2,104.000,110.000,104.000,6.000,0.000,0.000,0.000,0.000,0.000,0.000,104.000,0.000,104.000,0.000,0.000,0.000,0.000,104.000,NULL,NULL,NULL,1,1,1,'Live','2026-07-17 12:34:53','2026-07-18 10:52:34',NULL,NULL,NULL,'fixed',NULL),(4,'INV-0004','SALE-2026-000004','2026-07-12','2026-07-12 14:20:00',NULL,NULL,NULL,NULL,NULL,4,1,1258.000,500.000,500.000,0.000,0.000,758.000,0.000,0.000,0.000,0.000,1258.000,0.000,1258.000,0.000,0.000,0.000,0.000,1258.000,NULL,NULL,'Credit sale',1,1,1,'Live','2026-07-17 12:34:53','2026-07-18 10:52:34',NULL,NULL,NULL,'fixed',NULL),(5,'INV-0005','SALE-2026-000005','2026-07-13','2026-07-13 16:00:00',NULL,NULL,NULL,NULL,NULL,6,1,2090.000,2090.000,2090.000,0.000,0.000,0.000,0.000,0.000,0.000,0.000,2090.000,0.000,2090.000,0.000,0.000,0.000,0.000,2090.000,NULL,NULL,'Bulk order',1,1,1,'Live','2026-07-17 12:34:53','2026-07-18 10:52:34',NULL,NULL,NULL,'fixed',NULL),(6,NULL,'SALE-2026-000006','2026-07-18','2026-07-18 11:52:03','2026-07-18 11:52:03',NULL,NULL,NULL,NULL,1,1,380.000,399.000,399.000,0.000,0.000,0.000,0.000,0.000,19.000,0.000,399.000,0.000,380.000,0.000,0.000,0.000,0.000,399.000,'[{\"tax_field_id\": \"4\", \"tax_field_type\": \"GST 5%\", \"tax_field_amount\": \"19.00\", \"tax_field_percentage\": \"5\"}]',NULL,NULL,1,1,1,'Live','2026-07-18 11:52:03','2026-07-18 11:52:03',NULL,NULL,NULL,'fixed',NULL),(7,NULL,'SALE-2026-000007','2026-07-18','2026-07-18 11:52:09','2026-07-18 11:52:09',NULL,NULL,NULL,NULL,1,1,499.000,523.950,523.950,0.000,0.000,0.000,0.000,0.000,24.950,0.000,523.950,0.000,499.000,0.000,0.000,0.000,0.000,523.950,'[{\"tax_field_id\": \"4\", \"tax_field_type\": \"GST 5%\", \"tax_field_amount\": \"24.95\", \"tax_field_percentage\": \"5\"}]',NULL,NULL,1,1,1,'Live','2026-07-18 11:52:09','2026-07-18 11:52:09',NULL,NULL,NULL,'fixed',NULL),(8,NULL,'SALE-2026-000008','2026-07-18','2026-07-18 11:52:14','2026-07-18 11:52:14',NULL,NULL,NULL,NULL,1,1,499.000,523.950,523.950,0.000,0.000,0.000,0.000,0.000,24.950,0.000,523.950,0.000,499.000,0.000,0.000,0.000,0.000,523.950,'[{\"tax_field_id\": \"4\", \"tax_field_type\": \"GST 5%\", \"tax_field_amount\": \"24.95\", \"tax_field_percentage\": \"5\"}]',NULL,NULL,1,1,1,'Live','2026-07-18 11:52:14','2026-07-18 11:52:14',NULL,NULL,NULL,'fixed',NULL),(9,NULL,'SALE-2026-000009','2026-07-18','2026-07-18 11:55:37','2026-07-18 11:55:37',NULL,NULL,NULL,NULL,1,1,380.000,399.000,399.000,0.000,0.000,0.000,0.000,0.000,19.000,0.000,399.000,0.000,380.000,0.000,0.000,0.000,0.000,399.000,'[{\"tax_field_id\": \"4\", \"tax_field_type\": \"GST 5%\", \"tax_field_amount\": \"19.00\", \"tax_field_percentage\": \"5\"}]',NULL,NULL,1,1,1,'Live','2026-07-18 11:55:37','2026-07-18 11:55:37',NULL,NULL,NULL,'fixed',NULL),(10,NULL,'SALE-2026-000010','2026-07-18','2026-07-18 12:07:15','2026-07-18 12:07:15',NULL,NULL,NULL,NULL,1,1,155.000,182.900,182.900,0.000,0.000,0.000,0.000,0.000,27.900,0.000,182.900,0.000,155.000,0.000,0.000,0.000,0.000,182.900,'[{\"tax_field_id\": \"6\", \"tax_field_type\": \"GST 18%\", \"tax_field_amount\": \"27.90\", \"tax_field_percentage\": \"18\"}]',NULL,NULL,1,1,1,'Live','2026-07-18 12:07:15','2026-07-18 12:07:15',NULL,NULL,NULL,'fixed',NULL),(11,NULL,'SALE-2026-000011','2026-07-18','2026-07-18 12:17:27','2026-07-18 12:17:27',NULL,NULL,NULL,NULL,1,1,22.000,22.000,22.000,0.000,0.000,0.000,0.000,0.000,0.000,0.000,22.000,0.000,22.000,0.000,0.000,0.000,0.000,22.000,NULL,NULL,NULL,1,1,1,'Live','2026-07-18 12:17:27','2026-07-18 12:17:27',NULL,NULL,NULL,'fixed',NULL);
/*!40000 ALTER TABLE `sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `servicings`
--

DROP TABLE IF EXISTS `servicings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `servicings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `employee_id` bigint unsigned DEFAULT NULL,
  `date` date DEFAULT NULL,
  `receiving_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `servicing_charge` decimal(15,2) DEFAULT '0.00',
  `paid_amount` decimal(15,2) DEFAULT '0.00',
  `due_amount` decimal(15,2) DEFAULT '0.00',
  `payment_method_id` bigint unsigned DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `note` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `current_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `employee_id` (`employee_id`),
  KEY `payment_method_id` (`payment_method_id`),
  CONSTRAINT `servicings_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `servicings_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `servicings_ibfk_3` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `servicings`
--

LOCK TABLES `servicings` WRITE;
/*!40000 ALTER TABLE `servicings` DISABLE KEYS */;
INSERT INTO `servicings` VALUES (1,'SVC-0001',5,4,'2026-07-09','2026-07-09','2026-07-11',500.00,500.00,0.00,1,'Refrigerator servicing','Cooling issue fixed',1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11','Delivered');
/*!40000 ALTER TABLE `servicings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `set_opening_stocks`
--

DROP TABLE IF EXISTS `set_opening_stocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `set_opening_stocks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned DEFAULT NULL,
  `item_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_description` text COLLATE utf8mb4_unicode_ci,
  `stock_quantity` decimal(15,3) DEFAULT '0.000',
  `outlet_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `outlet_id` (`outlet_id`),
  CONSTRAINT `set_opening_stocks_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `set_opening_stocks_ibfk_2` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `set_opening_stocks`
--

LOCK TABLES `set_opening_stocks` WRITE;
/*!40000 ALTER TABLE `set_opening_stocks` DISABLE KEYS */;
INSERT INTO `set_opening_stocks` VALUES (1,1,'General_Product','Aashirvaad Aata 10 Kg',50.000,1,1,1,'2026-07-17 12:25:28','2026-07-17 12:25:28'),(2,2,'General_Product','India Gate Basmati Rice 5 Kg',40.000,1,1,1,'2026-07-17 12:25:28','2026-07-17 12:25:28'),(3,3,'General_Product','Fortune Sunflower Oil 1 Litre',60.000,1,1,1,'2026-07-17 12:25:28','2026-07-17 12:25:28'),(4,4,'General_Product','Tata Salt 1 Kg',120.000,1,1,1,'2026-07-17 12:25:28','2026-07-17 12:25:28'),(5,5,'General_Product','MDH Garam Masala 100g',80.000,1,1,1,'2026-07-17 12:25:28','2026-07-17 12:25:28');
/*!40000 ALTER TABLE `set_opening_stocks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `states`
--

DROP TABLE IF EXISTS `states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `states` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `state_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `states`
--

LOCK TABLES `states` WRITE;
/*!40000 ALTER TABLE `states` DISABLE KEYS */;
INSERT INTO `states` VALUES (1,'01','Jammu and Kashmir','Union Territory','2026-07-17 10:33:07','2026-07-17 10:33:07'),(2,'02','Himachal Pradesh','State','2026-07-17 10:33:07','2026-07-17 10:33:07'),(3,'03','Punjab','State','2026-07-17 10:33:07','2026-07-17 10:33:07'),(4,'04','Chandigarh','Union Territory','2026-07-17 10:33:07','2026-07-17 10:33:07'),(5,'05','Uttarakhand','State','2026-07-17 10:33:07','2026-07-17 10:33:07'),(6,'06','Haryana','State','2026-07-17 10:33:07','2026-07-17 10:33:07'),(7,'07','Delhi','Union Territory','2026-07-17 10:33:07','2026-07-17 10:33:07'),(8,'08','Rajasthan','State','2026-07-17 10:33:07','2026-07-17 10:33:07'),(9,'09','Uttar Pradesh','State','2026-07-17 10:33:07','2026-07-17 10:33:07'),(10,'10','Bihar','State','2026-07-17 10:33:07','2026-07-17 10:33:07'),(11,'11','Sikkim','State','2026-07-17 10:33:07','2026-07-17 10:33:07'),(12,'12','Arunachal Pradesh','State','2026-07-17 10:33:07','2026-07-17 10:33:07'),(13,'13','Nagaland','State','2026-07-17 10:33:07','2026-07-17 10:33:07'),(14,'14','Manipur','State','2026-07-17 10:33:07','2026-07-17 10:33:07'),(15,'15','Mizoram','State','2026-07-17 10:33:07','2026-07-17 10:33:07'),(16,'16','Tripura','State','2026-07-17 10:33:07','2026-07-17 10:33:07'),(17,'17','Meghalaya','State','2026-07-17 10:33:07','2026-07-17 10:33:07'),(18,'18','Assam','State','2026-07-17 10:33:07','2026-07-17 10:33:07'),(19,'19','West Bengal','State','2026-07-17 10:33:08','2026-07-17 10:33:08'),(20,'20','Jharkhand','State','2026-07-17 10:33:08','2026-07-17 10:33:08'),(21,'21','Odisha','State','2026-07-17 10:33:08','2026-07-17 10:33:08'),(22,'22','Chhattisgarh','State','2026-07-17 10:33:08','2026-07-17 10:33:08'),(23,'23','Madhya Pradesh','State','2026-07-17 10:33:08','2026-07-17 10:33:08'),(24,'24','Gujarat','State','2026-07-17 10:33:08','2026-07-17 10:33:08'),(25,'26','Dadra and Nagar Haveli and Daman and Diu','Union Territory','2026-07-17 10:33:08','2026-07-17 10:33:08'),(26,'27','Maharashtra','State','2026-07-17 10:33:08','2026-07-17 10:33:08'),(27,'28','Andhra Pradesh','State','2026-07-17 10:33:08','2026-07-17 10:33:08'),(28,'29','Karnataka','State','2026-07-17 10:33:08','2026-07-17 10:33:08'),(29,'30','Goa','State','2026-07-17 10:33:08','2026-07-17 10:33:08'),(30,'31','Lakshadweep','Union Territory','2026-07-17 10:33:08','2026-07-17 10:33:08'),(31,'32','Kerala','State','2026-07-17 10:33:08','2026-07-17 10:33:08'),(32,'33','Tamil Nadu','State','2026-07-17 10:33:08','2026-07-17 10:33:08'),(33,'34','Puducherry','Union Territory','2026-07-17 10:33:08','2026-07-17 10:33:08'),(34,'35','Andaman and Nicobar Islands','Union Territory','2026-07-17 10:33:08','2026-07-17 10:33:08'),(35,'36','Telangana','State','2026-07-17 10:33:08','2026-07-17 10:33:08'),(36,'37','Andhra Pradesh (New)','State','2026-07-17 10:33:08','2026-07-17 10:33:08'),(37,'38','Ladakh','Union Territory','2026-07-17 10:33:08','2026-07-17 10:33:08');
/*!40000 ALTER TABLE `states` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_payments`
--

DROP TABLE IF EXISTS `supplier_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supplier_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `payment_method_id` bigint unsigned DEFAULT NULL,
  `amount` decimal(15,3) DEFAULT '0.000',
  `date` date DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `payment_method_id` (`payment_method_id`),
  CONSTRAINT `supplier_payments_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `supplier_payments_ibfk_2` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_payments`
--

LOCK TABLES `supplier_payments` WRITE;
/*!40000 ALTER TABLE `supplier_payments` DISABLE KEYS */;
INSERT INTO `supplier_payments` VALUES (1,2,2,4100.000,'2026-07-10','Remaining oil payment via bank',1,1,'Live','2026-07-17 12:33:46','2026-07-17 12:33:46',NULL,1),(2,1,1,3000.000,'2026-07-16','Partial payment for PUR-0004',1,1,'Live','2026-07-17 12:33:46','2026-07-17 12:33:46',NULL,1);
/*!40000 ALTER TABLE `supplier_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gst_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method_id` bigint unsigned DEFAULT NULL,
  `opening_balance` decimal(15,3) DEFAULT '0.000',
  `opening_balance_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `credit_limit` decimal(15,3) DEFAULT '0.000',
  `description` text COLLATE utf8mb4_unicode_ci,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `vat_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_method_id` (`payment_method_id`),
  CONSTRAINT `suppliers_ibfk_1` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` VALUES (1,'Ramesh Agarwal','Agarwal Wholesale Traders','ramesh@agarwaltraders.com','+91-9811111111','15, Wholesale Market, Chandni Chowk','New Delhi','Delhi','110006','India','07AAACR0001R1ZX',NULL,50000.000,'Debit',200000.000,'Main wholesale supplier for grains and staples',NULL,1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56',NULL),(2,'Sunil Sharma','Sharma Oil Distributors','sunil@sharmaoil.com','+91-9822222222','8, Industrial Area, Patparganj','New Delhi','Delhi','110092','India','07AAACS0002S1ZX',NULL,25000.000,'Debit',100000.000,'Oil and ghee supplier',NULL,1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56',NULL),(3,'Mohan Gupta','Gupta Masala House','mohan@guptamasala.com','+91-9833333333','22, Khari Baoli, Sadar Bazar','New Delhi','Delhi','110006','India','07AAACG0003G1ZX',NULL,15000.000,'Debit',50000.000,'Spices and masala supplier',NULL,1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56',NULL),(4,'Deepak Verma','Verma General Stores','deepak@vermageneral.com','+91-9844444444','3, Nehru Place Market','New Delhi','Delhi','110019','India','07AAACV0004V1ZX',NULL,5000.000,'Credit',30000.000,'General items and packaged goods supplier',NULL,1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56',NULL),(5,'Anita Joshi','Joshi FMCG Distributors','anita@joshifmcg.com','+91-9855555555','11, Lawrence Road, Industrial Area','New Delhi','Delhi','110035','India','07AAACJ0005J1ZX',NULL,10000.000,'Debit',75000.000,'FMCG products - soap, detergent, biscuits',NULL,1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56',NULL);
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `taxs`
--

DROP TABLE IF EXISTS `taxs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `taxs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tax_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_rate` decimal(5,2) DEFAULT '0.00',
  `parent_tax_id` bigint unsigned DEFAULT NULL,
  `show_in_item_profile` tinyint(1) DEFAULT '0',
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_tax_id` (`parent_tax_id`),
  CONSTRAINT `taxs_ibfk_1` FOREIGN KEY (`parent_tax_id`) REFERENCES `taxs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `taxs`
--

LOCK TABLES `taxs` WRITE;
/*!40000 ALTER TABLE `taxs` DISABLE KEYS */;
INSERT INTO `taxs` VALUES (1,'CGST',9.00,6,1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(2,'SGST',9.00,6,1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(3,'IGST',18.00,NULL,1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(4,'GST 5%',5.00,NULL,1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(5,'GST 12%',12.00,NULL,1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59'),(6,'GST 18%',18.00,NULL,1,1,'Live','2026-07-17 12:20:59','2026-07-17 12:20:59');
/*!40000 ALTER TABLE `taxs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `time_zones`
--

DROP TABLE IF EXISTS `time_zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `time_zones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `country_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `time_zones`
--

LOCK TABLES `time_zones` WRITE;
/*!40000 ALTER TABLE `time_zones` DISABLE KEYS */;
/*!40000 ALTER TABLE `time_zones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transfer_details`
--

DROP TABLE IF EXISTS `transfer_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transfer_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `quantity` decimal(15,3) DEFAULT '0.000',
  `unit_price` decimal(15,3) DEFAULT '0.000',
  `total` decimal(15,3) DEFAULT '0.000',
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transfer_id` (`transfer_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `transfer_details_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transfer_details_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transfer_details`
--

LOCK TABLES `transfer_details` WRITE;
/*!40000 ALTER TABLE `transfer_details` DISABLE KEYS */;
INSERT INTO `transfer_details` VALUES (1,1,4,20.000,18.000,360.000,1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11');
/*!40000 ALTER TABLE `transfer_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transfers`
--

DROP TABLE IF EXISTS `transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transfers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `from_outlet_id` bigint unsigned DEFAULT NULL,
  `to_outlet_id` bigint unsigned DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  PRIMARY KEY (`id`),
  KEY `from_outlet_id` (`from_outlet_id`),
  KEY `to_outlet_id` (`to_outlet_id`),
  CONSTRAINT `transfers_ibfk_1` FOREIGN KEY (`from_outlet_id`) REFERENCES `outlets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transfers_ibfk_2` FOREIGN KEY (`to_outlet_id`) REFERENCES `outlets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transfers`
--

LOCK TABLES `transfers` WRITE;
/*!40000 ALTER TABLE `transfers` DISABLE KEYS */;
INSERT INTO `transfers` VALUES (1,'TRF-0001','2026-07-16',1,1,'Internal stock adjustment',1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11','Completed');
/*!40000 ALTER TABLE `transfers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `units`
--

DROP TABLE IF EXISTS `units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `unit_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `company_id` bigint unsigned DEFAULT '1',
  `user_id` bigint unsigned DEFAULT NULL,
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `units`
--

LOCK TABLES `units` WRITE;
/*!40000 ALTER TABLE `units` DISABLE KEYS */;
INSERT INTO `units` VALUES (1,'Kg','Kilogram',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(2,'Gram','Gram',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(3,'Litre','Litre',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(4,'Ml','Millilitre',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(5,'Piece','Piece / Unit',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(6,'Packet','Packet',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(7,'Box','Box',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(8,'Dozen','Dozen (12 pieces)',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(9,'Quintal','Quintal (100 Kg)',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(10,'Bag','Bag (50 Kg)',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56');
/*!40000 ALTER TABLE `units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `salary` decimal(15,2) DEFAULT '0.00',
  `commission` decimal(15,2) DEFAULT '0.00',
  `outlet_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `will_login` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'Yes',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_permission_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_amt` decimal(15,2) DEFAULT '0.00',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `two_factor_enabled` tinyint(1) DEFAULT '0',
  `session_timeout` int DEFAULT '0',
  `login_notifications` tinyint(1) DEFAULT '0',
  `question` text COLLATE utf8mb4_unicode_ci,
  `answer` text COLLATE utf8mb4_unicode_ci,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Akash Admin','admin@rashankidukan.com','$2y$12$UuoxIywtd4.bMWhrKKJdpOJFHjOLqBTmQ1bKF0V8mAR5dNAAiW/x6','1','+91-9876543210',25000.00,0.00,'1','Yes','Live',NULL,NULL,0.00,NULL,NULL,1,0,0,0,'What is the name of your first pet?','Mickey',NULL,'2026-07-17 10:32:59','2026-07-17 10:33:00','2026-07-17 10:37:25'),(2,'Rajesh Kumar','rajesh@rashankidukan.com','$2y$12$/KWvV36gvVdz/DDogPUx4.bBs3ccc.IZpz4Jsr4xOlwdhVSc8nk56','2','+91-9812345678',18000.00,0.00,'1','Yes','Live',NULL,NULL,0.00,NULL,NULL,1,0,0,0,NULL,NULL,NULL,NULL,'2026-07-17 12:17:52','2026-07-18 09:45:18'),(3,'Priya Singh','priya@rashankidukan.com','$2y$12$0M7LuXsLRHrj86wiZAnDke99JfeTRN234QSD5zU7py9zyT3RB6x6i','3','+91-9823456789',12000.00,1.50,'1','Yes','Live',NULL,NULL,0.00,NULL,NULL,1,0,0,0,NULL,NULL,NULL,NULL,'2026-07-17 12:17:52','2026-07-18 09:45:19'),(4,'Suresh Yadav','suresh@rashankidukan.com','$2y$12$EfRjUgrr3tfPNHy2T.z.cOWClPK4E.3HavJhHVHUo0M6sSgMrchq6','4','+91-9834567890',15000.00,0.00,'1','Yes','Live',NULL,NULL,0.00,NULL,NULL,1,0,0,0,NULL,NULL,NULL,NULL,'2026-07-17 12:17:52','2026-07-18 09:45:19');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `variations`
--

DROP TABLE IF EXISTS `variations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `variations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `variation_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variation_value` json DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `variations`
--

LOCK TABLES `variations` WRITE;
/*!40000 ALTER TABLE `variations` DISABLE KEYS */;
INSERT INTO `variations` VALUES (1,'Pack Size','[\"1 Kg\", \"2 Kg\", \"5 Kg\", \"10 Kg\", \"25 Kg\", \"50 Kg\"]',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(2,'Volume','[\"250 ml\", \"500 ml\", \"1 Litre\", \"2 Litre\", \"5 Litre\"]',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56'),(3,'Weight','[\"100 g\", \"200 g\", \"250 g\", \"500 g\", \"1 Kg\", \"2 Kg\"]',1,1,'Live','2026-07-17 12:21:56','2026-07-17 12:21:56');
/*!40000 ALTER TABLE `variations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `view_stock_detail` (replaces VIEW for hosting without CREATE VIEW privilege)
--

DROP TABLE IF EXISTS `view_stock_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `view_stock_detail` (
  `item_id` bigint unsigned DEFAULT NULL,
  `type` bigint NOT NULL DEFAULT '0',
  `stock_quantity` decimal(15,3) DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `view_stock_detail`
--

LOCK TABLES `view_stock_detail` WRITE;
/*!40000 ALTER TABLE `view_stock_detail` DISABLE KEYS */;
INSERT INTO `view_stock_detail` VALUES (1,1,30.000,1,1,'Live'),(2,1,26.000,1,1,'Live'),(3,1,70.000,1,1,'Live'),(4,1,300.000,1,1,'Live'),(5,1,200.000,1,1,'Live'),(1,1,50.000,1,1,'Live'),(3,1,65.000,1,1,'Live'),(1,2,2.000,1,1,'Live'),(4,2,1.000,1,1,'Live'),(2,2,1.000,1,1,'Live'),(3,2,1.000,1,1,'Live'),(5,2,1.000,1,1,'Live'),(4,2,2.000,1,1,'Live'),(2,2,1.000,1,1,'Live'),(1,2,2.000,1,1,'Live'),(1,2,2.000,1,1,'Live'),(3,2,4.000,1,1,'Live'),(5,2,5.000,1,1,'Live'),(1,2,1.000,1,1,'Live'),(2,2,1.000,1,1,'Live'),(2,2,1.000,1,1,'Live'),(1,2,1.000,1,1,'Live'),(3,2,1.000,1,1,'Live'),(4,2,1.000,1,1,'Live');
/*!40000 ALTER TABLE `view_stock_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `warranties`
--

DROP TABLE IF EXISTS `warranties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warranties` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `technician_id` bigint unsigned DEFAULT NULL,
  `receiving_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `current_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `note` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT '1',
  `del_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `item_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serial_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `technician_id` (`technician_id`),
  CONSTRAINT `warranties_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `warranties_ibfk_2` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `warranties`
--

LOCK TABLES `warranties` WRITE;
/*!40000 ALTER TABLE `warranties` DISABLE KEYS */;
INSERT INTO `warranties` VALUES (1,'WRN-0001',2,4,'2026-07-08','2026-07-10','Delivered','Digital weighing scale repair','Calibration done',1,1,1,'Live','2026-07-17 12:40:11','2026-07-17 12:40:11','Weighing Scale','DS-500','WS20240012');
/*!40000 ALTER TABLE `warranties` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zatca_invoices`
--

DROP TABLE IF EXISTS `zatca_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `zatca_invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL,
  `invoice_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `previous_invoice_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_code` text COLLATE utf8mb4_unicode_ci,
  `zatca_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `zatca_error` text COLLATE utf8mb4_unicode_ci,
  `cleared_at` datetime DEFAULT NULL,
  `reported_at` datetime DEFAULT NULL,
  `failed_at` datetime DEFAULT NULL,
  `retry_count` int DEFAULT '0',
  `last_retry_at` datetime DEFAULT NULL,
  `ubl_xml` longtext COLLATE utf8mb4_unicode_ci,
  `signed_xml` longtext COLLATE utf8mb4_unicode_ci,
  `cryptographic_stamp` text COLLATE utf8mb4_unicode_ci,
  `packaging_authorized_serial_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_offline` tinyint(1) DEFAULT '0',
  `queued_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  CONSTRAINT `zatca_invoices_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zatca_invoices`
--

LOCK TABLES `zatca_invoices` WRITE;
/*!40000 ALTER TABLE `zatca_invoices` DISABLE KEYS */;
/*!40000 ALTER TABLE `zatca_invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zatca_requests`
--

DROP TABLE IF EXISTS `zatca_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `zatca_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `zatca_invoice_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  `request_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_payload` longtext COLLATE utf8mb4_unicode_ci,
  `request_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_method` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_headers` json DEFAULT NULL,
  `response_status_code` int DEFAULT NULL,
  `response_body` longtext COLLATE utf8mb4_unicode_ci,
  `response_headers` json DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `error_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_at` datetime DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `response_time_ms` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `zatca_invoice_id` (`zatca_invoice_id`),
  CONSTRAINT `zatca_requests_ibfk_1` FOREIGN KEY (`zatca_invoice_id`) REFERENCES `zatca_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zatca_requests`
--

LOCK TABLES `zatca_requests` WRITE;
/*!40000 ALTER TABLE `zatca_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `zatca_requests` ENABLE KEYS */;
UNLOCK TABLES;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-18 12:28:10
