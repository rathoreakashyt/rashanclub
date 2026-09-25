CREATE TABLE `customers` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `busy_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `work_address` varchar(255) DEFAULT NULL,
  `guarantor_name` varchar(255) DEFAULT NULL,
  `guarantor_mobile` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state_id` bigint(20) UNSIGNED DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `gst_number` varchar(50) DEFAULT NULL,
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
  `tax_number` varchar(255) DEFAULT NULL,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `loyalty_point` decimal(15,2) DEFAULT 0.00,
  `shipping_address` text DEFAULT NULL,
  `dp_1` varchar(255) DEFAULT NULL,
  `dp_2` varchar(255) DEFAULT NULL,
  `is_walk_in` tinyint(1) DEFAULT 1,
  `dob` date DEFAULT NULL,
  `anniversary` date DEFAULT NULL,
  `is_installment_customer` tinyint(1) DEFAULT 0,
  `discount` decimal(10,2) DEFAULT 0.00,
  `date_of_birth` date DEFAULT NULL,
  `date_of_anniversary` date DEFAULT NULL,
  `customer_type` varchar(50) DEFAULT 'Regular',
  `same_or_diff_state` varchar(20) DEFAULT NULL,
  `business_type` varchar(50) DEFAULT NULL,
  `sync_version` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `busy_id`, `name`, `email`, `phone`, `address`, `work_address`, `guarantor_name`, `guarantor_mobile`, `city`, `state_id`, `postal_code`, `country`, `gst_number`, `opening_balance`, `opening_balance_type`, `credit_limit`, `description`, `photo`, `user_id`, `company_id`, `del_status`, `created_at`, `updated_at`, `tax_number`, `current_balance`, `loyalty_point`, `shipping_address`, `dp_1`, `dp_2`, `is_walk_in`, `dob`, `anniversary`, `is_installment_customer`, `discount`, `date_of_birth`, `date_of_anniversary`, `customer_type`, `same_or_diff_state`, `business_type`, `sync_version`) VALUES
(1, NULL, 'Walk-in Customer', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.000, NULL, 0.000, NULL, NULL, NULL, 1, 'Live', '2026-09-12 17:38:56', '2026-09-12 17:38:56', NULL, 0.00, 0.00, NULL, NULL, NULL, 1, NULL, NULL, 0, 0.00, NULL, NULL, 'Regular', NULL, NULL, 0);
