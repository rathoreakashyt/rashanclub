CREATE TABLE `salaries` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `month` int(11) DEFAULT NULL,
  `generated_date` date DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `salary_items`
--

CREATE TABLE `salary_items` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `salary_id` bigint(20) UNSIGNED DEFAULT NULL,
  `employee_id` bigint(20) UNSIGNED DEFAULT NULL,
  `salary_amount` decimal(15,2) DEFAULT 0.00,
  `overtime_rate` decimal(15,2) DEFAULT 0.00,
  `overtime_hour` decimal(5,2) DEFAULT 0.00,
  `additional_amount` decimal(15,2) DEFAULT 0.00,
  `deduction_amount` decimal(15,2) DEFAULT 0.00,
  `absent_day` int(11) DEFAULT 0,
  `absent_day_amount` decimal(15,2) DEFAULT 0.00,
  `tips` decimal(15,2) DEFAULT 0.00,
  `advance_taken` decimal(15,2) DEFAULT 0.00,
  `net_salary` decimal(15,2) DEFAULT 0.00,
  `note` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `salary_payments`
--

CREATE TABLE `salary_payments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `salary_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT 0.00,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(255) DEFAULT NULL,
  `sale_no` varchar(255) DEFAULT NULL,
  `total_items` int(11) DEFAULT NULL,
  `sale_date` date DEFAULT NULL,
  `date_time` datetime DEFAULT NULL,
  `order_time` datetime DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `due_date_time` datetime DEFAULT NULL,
  `order_date_time` datetime DEFAULT NULL,
  `close_time` datetime DEFAULT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `employee_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sub_total` decimal(15,3) DEFAULT 0.000,
  `given_amount` decimal(15,3) DEFAULT 0.000,
  `paid_amount` decimal(15,3) DEFAULT 0.000,
  `change_amount` decimal(15,3) DEFAULT 0.000,
  `previous_due` decimal(15,3) DEFAULT 0.000,
  `due_amount` decimal(15,3) DEFAULT 0.000,
  `disc` decimal(15,3) DEFAULT 0.000,
  `disc_actual` decimal(15,3) DEFAULT 0.000,
  `vat` decimal(15,3) DEFAULT 0.000,
  `rounding` decimal(15,3) DEFAULT 0.000,
  `total_payable` decimal(15,3) DEFAULT 0.000,
  `total_item_discount_amount` decimal(15,3) DEFAULT 0.000,
  `sub_total_with_discount` decimal(15,3) DEFAULT 0.000,
  `sub_total_discount_amount` decimal(15,3) DEFAULT 0.000,
  `total_discount_amount` decimal(15,3) DEFAULT 0.000,
  `delivery_charge` decimal(15,3) DEFAULT 0.000,
  `sub_total_discount_value` decimal(15,3) DEFAULT 0.000,
  `grand_total` decimal(15,3) DEFAULT 0.000,
  `online_yes_no` varchar(10) DEFAULT 'No',
  `loyalty_point_used` decimal(15,3) DEFAULT 0.000,
  `mrp_total` decimal(15,3) DEFAULT 0.000,
  `savings` decimal(15,3) DEFAULT 0.000,
  `sale_vat_objects` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sale_vat_objects`)),
  `zatca_phase1_qr_code` text DEFAULT NULL,
  `zatca_compliant` tinyint(1) DEFAULT 0,
  `zatca_invoice_id` bigint(20) UNSIGNED DEFAULT NULL,
  `delivery_partner_id` bigint(20) UNSIGNED DEFAULT NULL,
  `note` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `invoice_id` varchar(255) DEFAULT NULL,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `discount` decimal(15,2) DEFAULT 0.00,
  `tax` decimal(15,2) DEFAULT 0.00,
  `shipping` decimal(15,2) DEFAULT 0.00,
  `total` decimal(15,2) DEFAULT 0.00,
  `paid` decimal(15,2) DEFAULT 0.00,
  `due` decimal(15,2) DEFAULT 0.00,
  `payment_status` varchar(20) DEFAULT 'Paid',
  `sale_status` varchar(20) DEFAULT 'Complete',
  `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `discount_type` varchar(20) DEFAULT NULL,
  `round_off` decimal(15,2) DEFAULT 0.00,
  `loyalty_point` decimal(15,2) DEFAULT 0.00,
  `type` varchar(20) DEFAULT 'sale',
  `counter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `table_no` varchar(50) DEFAULT NULL,
  `booking_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sub_total_discount_type` varchar(20) DEFAULT 'flat',
  `delivery_status` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_details`
--

CREATE TABLE `sale_details` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sales_id` bigint(20) UNSIGNED DEFAULT NULL,
  `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `qty` decimal(15,3) DEFAULT 0.000,
  `menu_price_without_discount` decimal(15,3) DEFAULT 0.000,
  `menu_price_with_discount` decimal(15,3) DEFAULT 0.000,
  `menu_unit_price` decimal(15,3) DEFAULT 0.000,
  `purchase_price` decimal(15,3) DEFAULT 0.000,
  `menu_vat_percentage` decimal(5,2) DEFAULT 0.00,
  `item_tax_amount` decimal(15,3) DEFAULT 0.000,
  `menu_taxes` text DEFAULT NULL,
  `menu_discount_value` decimal(15,3) DEFAULT 0.000,
  `discount_amount` decimal(15,3) DEFAULT 0.000,
  `item_type` varchar(50) DEFAULT NULL,
  `expiry_imei_serial` text DEFAULT NULL,
  `loyalty_point_earn` decimal(15,3) DEFAULT 0.000,
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
-- Table structure for table `sale_payments`
--

CREATE TABLE `sale_payments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `date` date DEFAULT NULL,
  `amount` decimal(15,3) DEFAULT 0.000,
  `multi_currency` varchar(10) DEFAULT 'No',
  `multi_currency_rate` decimal(15,4) DEFAULT 0.0000,
  `usage_point` decimal(15,3) DEFAULT 0.000,
  `note` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `reference_no` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_returns`
--

CREATE TABLE `sale_returns` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) DEFAULT NULL,
  `sale_id` bigint(20) UNSIGNED DEFAULT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `date` date DEFAULT NULL,
  `total_return_amount` decimal(15,3) DEFAULT 0.000,
  `paid` decimal(15,3) DEFAULT 0.000,
  `due` decimal(15,3) DEFAULT 0.000,
  `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  `note` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `local_id` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_return_details`
--

CREATE TABLE `sale_return_details` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_return_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sale_id` bigint(20) UNSIGNED DEFAULT NULL,
  `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sale_quantity_amount` decimal(15,3) DEFAULT 0.000,
  `return_quantity_amount` decimal(15,3) DEFAULT 0.000,
  `unit_price_in_sale` decimal(15,3) DEFAULT 0.000,
  `unit_price_in_return` decimal(15,3) DEFAULT 0.000,
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
-- Table structure for table `servicings`
--

CREATE TABLE `servicings` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `employee_id` bigint(20) UNSIGNED DEFAULT NULL,
  `date` date DEFAULT NULL,
  `receiving_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `servicing_charge` decimal(15,2) DEFAULT 0.00,
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `due_amount` decimal(15,2) DEFAULT 0.00,
  `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `note` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `current_status` varchar(50) DEFAULT 'pending',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `set_opening_stocks`
--

CREATE TABLE `set_opening_stocks` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `item_type` varchar(50) DEFAULT NULL,
  `item_description` text DEFAULT NULL,
  `stock_quantity` decimal(15,3) DEFAULT 0.000,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `states`
--

CREATE TABLE `states` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `state_code` varchar(10) DEFAULT NULL,
  `state_name` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `gst_number` varchar(50) DEFAULT NULL,
  `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  `opening_balance` decimal(15,3) DEFAULT 0.000,
  `opening_balance_type` varchar(20) DEFAULT NULL,
  `credit_limit` decimal(15,3) DEFAULT 0.000,
  `description` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sync_version` bigint(20) UNSIGNED DEFAULT 1,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `tax_number` varchar(255) DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `vat_number` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplier_payments`
--

CREATE TABLE `supplier_payments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  `amount` decimal(15,3) DEFAULT 0.000,
  `date` date DEFAULT NULL,
  `note` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `reference_no` varchar(255) DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sync_conflicts`
--

CREATE TABLE `sync_conflicts` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `outlet_id` bigint(20) UNSIGNED NOT NULL,
  `device_id` varchar(100) DEFAULT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_key` varchar(255) NOT NULL,
  `server_id` bigint(20) UNSIGNED DEFAULT NULL,
  `incoming_json` longtext DEFAULT NULL,
  `existing_json` longtext DEFAULT NULL,
  `incoming_updated_at` datetime DEFAULT NULL,
  `existing_updated_at` datetime DEFAULT NULL,
  `resolution` varchar(20) DEFAULT 'server_won',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sync_dead_letters`
--

CREATE TABLE `sync_dead_letters` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `device_id` varchar(100) DEFAULT '',
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `entity_type` varchar(60) DEFAULT '',
  `local_id` varchar(100) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `error_message` text DEFAULT NULL,
  `retry_count` tinyint(3) UNSIGNED DEFAULT 0,
  `first_failed_at` timestamp NULL DEFAULT NULL,
  `last_failed_at` timestamp NULL DEFAULT NULL,
  `resolved` tinyint(1) DEFAULT 0,
  `resolved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sync_dead_letters`
--

INSERT INTO `sync_dead_letters` (`id`, `device_id`, `outlet_id`, `entity_type`, `local_id`, `payload`, `error_message`, `retry_count`, `first_failed_at`, `last_failed_at`, `resolved`, `resolved_at`) VALUES
(1, 'dfe329f5-086a-43f2-b4c7-8ecdb0944a13', 1, 'customers', 'CUS20260913163159299', '{\"local_code\":\"CUS20260913163159299\",\"name\":\"akash\",\"email\":null,\"phone\":\"8888888888\",\"address\":null,\"city\":null,\"postal_code\":null,\"gst_number\":null,\"opening_balance\":0,\"credit_limit\":0,\"customer_type\":\"B2C\",\"business_type\":\"B2C\",\"updated_at\":\"2026-09-13 11:01:59\",\"sync_version\":1}', 'SQLSTATE[22007]: Invalid datetime format: 1366 Incorrect integer value: \'No\' for column `rashankidukan_cloud`.`customers`.`is_installment_customer` at row 1 (Connection: mysql, SQL: insert into `customers` (`name`, `email`, `phone`, `address`, `city`, `postal_code`, `gst_number`, `opening_balance`, `opening_balance_type`, `credit_limit`, `customer_type`, `business_type`, `same_or_diff_state`, `loyalty_point`, `is_installment_customer`, `discount`, `date_of_birth`, `date_of_anniversary`, `work_address`, `guarantor_name`, `guarantor_mobile`, `updated_at`, `user_id`, `company_id`, `del_status`, `created_at`) values (akash, ?, 8888888888, ?, ?, ?, ?, 0, Dr, 0, B2C, B2C, ?, 0, No, ?, ?, ?, ?, ?, ?, 2026-09-13 16:32:26, 1, 1, Live, 2026-09-13 16:32:26))', 1, '2026-09-13 11:02:26', '2026-09-13 14:32:26', 0, NULL),
(2, 'dfe329f5-086a-43f2-b4c7-8ecdb0944a13', 1, 'customers', 'CUS20260913163301371', '{\"local_code\":\"CUS20260913163301371\",\"name\":\"akash\",\"email\":null,\"phone\":\"8888888888\",\"address\":null,\"city\":null,\"postal_code\":null,\"gst_number\":null,\"opening_balance\":0,\"credit_limit\":0,\"customer_type\":\"B2C\",\"business_type\":\"B2C\",\"updated_at\":\"2026-09-13 11:03:01\",\"sync_version\":1}', 'SQLSTATE[22007]: Invalid datetime format: 1366 Incorrect integer value: \'No\' for column `rashankidukan_cloud`.`customers`.`is_installment_customer` at row 1 (Connection: mysql, SQL: insert into `customers` (`name`, `email`, `phone`, `address`, `city`, `postal_code`, `gst_number`, `opening_balance`, `opening_balance_type`, `credit_limit`, `customer_type`, `business_type`, `same_or_diff_state`, `loyalty_point`, `is_installment_customer`, `discount`, `date_of_birth`, `date_of_anniversary`, `work_address`, `guarantor_name`, `guarantor_mobile`, `updated_at`, `user_id`, `company_id`, `del_status`, `created_at`) values (akash, ?, 8888888888, ?, ?, ?, ?, 0, Dr, 0, B2C, B2C, ?, 0, No, ?, ?, ?, ?, ?, ?, 2026-09-13 16:33:25, 1, 1, Live, 2026-09-13 16:33:25))', 1, '2026-09-13 11:03:25', '2026-09-13 14:33:25', 0, NULL);
