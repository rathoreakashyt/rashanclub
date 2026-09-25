CREATE TABLE `fixed_asset_items` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `code` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `quantity` decimal(15,3) DEFAULT 0.000,
  `unit_price` decimal(15,3) DEFAULT 0.000,
  `total` decimal(15,3) DEFAULT 0.000,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fixed_asset_stock_ins`
--

CREATE TABLE `fixed_asset_stock_ins` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `grand_total` decimal(15,3) DEFAULT 0.000,
  `note` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fixed_asset_stock_in_details`
--

CREATE TABLE `fixed_asset_stock_in_details` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `asset_stock_in_id` bigint(20) UNSIGNED DEFAULT NULL,
  `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `unit_price` decimal(15,3) DEFAULT 0.000,
  `total` decimal(15,3) DEFAULT 0.000,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fixed_asset_stock_outs`
--

CREATE TABLE `fixed_asset_stock_outs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `grand_total` decimal(15,3) DEFAULT 0.000,
  `note` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fixed_asset_stock_out_details`
--

CREATE TABLE `fixed_asset_stock_out_details` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `asset_stock_out_id` bigint(20) UNSIGNED DEFAULT NULL,
  `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `unit_price` decimal(15,3) DEFAULT 0.000,
  `total` decimal(15,3) DEFAULT 0.000,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `reason` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gst_cache`
--

CREATE TABLE `gst_cache` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `gstin` varchar(15) NOT NULL,
  `legal_name` varchar(255) DEFAULT NULL,
  `trade_name` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `holds`
--

CREATE TABLE `holds` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(255) DEFAULT NULL,
  `hold_no` varchar(255) DEFAULT NULL,
  `sale_date` date DEFAULT NULL,
  `date_time` datetime DEFAULT NULL,
  `due_payment_date` date DEFAULT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `employee_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sub_total` decimal(15,3) DEFAULT 0.000,
  `paid_amount` decimal(15,3) DEFAULT 0.000,
  `due_amount` decimal(15,3) DEFAULT 0.000,
  `disc` decimal(15,3) DEFAULT 0.000,
  `disc_actual` decimal(15,3) DEFAULT 0.000,
  `vat` decimal(15,3) DEFAULT 0.000,
  `total_payable` decimal(15,3) DEFAULT 0.000,
  `total_item_discount_amount` decimal(15,3) DEFAULT 0.000,
  `sub_total_with_discount` decimal(15,3) DEFAULT 0.000,
  `sub_total_discount_amount` decimal(15,3) DEFAULT 0.000,
  `total_discount_amount` decimal(15,3) DEFAULT 0.000,
  `delivery_charge` decimal(15,3) DEFAULT 0.000,
  `sub_total_discount_value` decimal(15,3) DEFAULT 0.000,
  `delivery_partner_id` bigint(20) UNSIGNED DEFAULT NULL,
  `note` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `counter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sub_total_discount_type` varchar(20) DEFAULT 'flat',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hold_combo_items`
--

CREATE TABLE `hold_combo_items` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) UNSIGNED DEFAULT NULL,
  `combo_sale_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `combo_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `combo_item_qty` decimal(15,3) DEFAULT 0.000,
  `combo_item_price` decimal(15,3) DEFAULT 0.000,
  `combo_item_seller_id` bigint(20) UNSIGNED DEFAULT NULL,
  `show_in_invoice` varchar(10) DEFAULT 'Yes',
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hold_details`
--

CREATE TABLE `hold_details` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `holds_id` bigint(20) UNSIGNED DEFAULT NULL,
  `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `qty` decimal(15,3) DEFAULT 0.000,
  `menu_price_without_discount` decimal(15,3) DEFAULT 0.000,
  `menu_price_with_discount` decimal(15,3) DEFAULT 0.000,
  `menu_unit_price` decimal(15,3) DEFAULT 0.000,
  `menu_vat_percentage` decimal(5,2) DEFAULT 0.00,
  `item_tax_amount` decimal(15,3) DEFAULT 0.000,
  `menu_discount_value` decimal(15,3) DEFAULT 0.000,
  `discount_amount` decimal(15,3) DEFAULT 0.000,
  `is_promo_item` varchar(10) DEFAULT 'No',
  `promo_parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `item_seller_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `discount_type` varchar(20) DEFAULT 'flat',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `idempotency_keys`
--

CREATE TABLE `idempotency_keys` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` varchar(64) NOT NULL,
  `device_id` varchar(100) DEFAULT '',
  `response_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_snapshot`)),
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `idempotency_keys`
--

INSERT INTO `idempotency_keys` (`id`, `key`, `device_id`, `response_snapshot`, `expires_at`, `created_at`) VALUES
(1, 'b5c5e08b2c33d800adebad38a51ce5d65cea088d216edf272d7c73f4afc4ea8c', 'dfe329f5-086a-43f2-b4c7-8ecdb0944a13', '{\"entity_type\":\"configs\",\"configs\":{\"categories\":[],\"brands\":[],\"units\":[],\"racks\":[],\"variations\":[],\"expense_categories\":[]},\"server_time\":\"2026-09-13 16:32:26\"}', '2026-09-14 14:32:26', '2026-09-13 11:02:26'),
(2, 'f5055bb5d0fee3fd94307e8286e14b6249f8c00c215056019081b4465fae9135', 'dfe329f5-086a-43f2-b4c7-8ecdb0944a13', '{\"entity_type\":\"customers\",\"customers\":[{\"local_id\":\"CUS20260913163159299\",\"local_code\":\"CUS20260913163159299\",\"server_id\":null,\"error\":\"SQLSTATE[22007]: Invalid datetime format: 1366 Incorrect integer value: \'No\' for column `rashankidukan_cloud`.`customers`.`is_installment_customer` at row 1 (Connection: mysql, SQL: insert into `customers` (`name`, `email`, `phone`, `address`, `city`, `postal_code`, `gst_number`, `opening_balance`, `opening_balance_type`, `credit_limit`, `customer_type`, `business_type`, `same_or_diff_state`, `loyalty_point`, `is_installment_customer`, `discount`, `date_of_birth`, `date_of_anniversary`, `work_address`, `guarantor_name`, `guarantor_mobile`, `updated_at`, `user_id`, `company_id`, `del_status`, `created_at`) values (akash, ?, 8888888888, ?, ?, ?, ?, 0, Dr, 0, B2C, B2C, ?, 0, No, ?, ?, ?, ?, ?, ?, 2026-09-13 16:32:26, 1, 1, Live, 2026-09-13 16:32:26))\"}],\"server_time\":\"2026-09-13 16:32:26\"}', '2026-09-14 14:32:26', '2026-09-13 11:02:26'),
(3, '87c55fd0304ad8559449d2420b85fc14db68679ef5a1053f960ebfcfc13da71a', 'dfe329f5-086a-43f2-b4c7-8ecdb0944a13', '{\"entity_type\":\"customers\",\"customers\":[{\"local_id\":\"CUS20260913163301371\",\"local_code\":\"CUS20260913163301371\",\"server_id\":null,\"error\":\"SQLSTATE[22007]: Invalid datetime format: 1366 Incorrect integer value: \'No\' for column `rashankidukan_cloud`.`customers`.`is_installment_customer` at row 1 (Connection: mysql, SQL: insert into `customers` (`name`, `email`, `phone`, `address`, `city`, `postal_code`, `gst_number`, `opening_balance`, `opening_balance_type`, `credit_limit`, `customer_type`, `business_type`, `same_or_diff_state`, `loyalty_point`, `is_installment_customer`, `discount`, `date_of_birth`, `date_of_anniversary`, `work_address`, `guarantor_name`, `guarantor_mobile`, `updated_at`, `user_id`, `company_id`, `del_status`, `created_at`) values (akash, ?, 8888888888, ?, ?, ?, ?, 0, Dr, 0, B2C, B2C, ?, 0, No, ?, ?, ?, ?, ?, ?, 2026-09-13 16:33:25, 1, 1, Live, 2026-09-13 16:33:25))\"}],\"server_time\":\"2026-09-13 16:33:25\"}', '2026-09-14 14:33:25', '2026-09-13 11:03:25');
