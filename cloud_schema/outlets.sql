CREATE TABLE `outlets` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `outlet_name` varchar(255) DEFAULT NULL,
  `outlet_code` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `state_id` bigint(20) UNSIGNED DEFAULT NULL,
  `invoice_scheme_id` bigint(20) UNSIGNED DEFAULT NULL,
  `invoice_layout_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sale_invoice_layout_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `active_status` varchar(20) DEFAULT 'Active',
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `outlets`
--

INSERT INTO `outlets` (`id`, `name`, `outlet_name`, `outlet_code`, `email`, `phone`, `address`, `state_id`, `invoice_scheme_id`, `invoice_layout_id`, `sale_invoice_layout_id`, `is_active`, `active_status`, `user_id`, `company_id`, `del_status`, `created_at`, `updated_at`) VALUES
(1, 'Main Store', 'Main Store', '000001', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'Active', 1, 1, 'Live', '2026-09-12 17:21:23', '2026-09-12 17:21:23');
