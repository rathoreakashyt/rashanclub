CREATE TABLE `variations` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `variation_name` varchar(255) DEFAULT NULL,
  `variation_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variation_value`)),
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sync_version` bigint(20) UNSIGNED DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `view_stock_detail`
--

CREATE TABLE `view_stock_detail` (
  `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` bigint(20) DEFAULT 0,
  `stock_quantity` decimal(15,3) DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT NULL,
  `del_status` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `wallet_id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `sale_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` varchar(20) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `balance_before` decimal(15,2) DEFAULT 0.00,
  `balance_after` decimal(15,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warranties`
--

CREATE TABLE `warranties` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `technician_id` bigint(20) UNSIGNED DEFAULT NULL,
  `receiving_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `current_status` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `note` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `item_model` varchar(255) DEFAULT NULL,
  `serial_no` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zatca_invoices`
--

CREATE TABLE `zatca_invoices` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `invoice_type` varchar(50) DEFAULT NULL,
  `uuid` varchar(255) DEFAULT NULL,
  `invoice_hash` varchar(255) DEFAULT NULL,
  `previous_invoice_hash` varchar(255) DEFAULT NULL,
  `qr_code` text DEFAULT NULL,
  `zatca_status` varchar(50) DEFAULT 'pending',
  `zatca_error` text DEFAULT NULL,
  `cleared_at` datetime DEFAULT NULL,
  `reported_at` datetime DEFAULT NULL,
  `failed_at` datetime DEFAULT NULL,
  `retry_count` int(11) DEFAULT 0,
  `last_retry_at` datetime DEFAULT NULL,
  `ubl_xml` longtext DEFAULT NULL,
  `signed_xml` longtext DEFAULT NULL,
  `cryptographic_stamp` text DEFAULT NULL,
  `packaging_authorized_serial_number` varchar(255) DEFAULT NULL,
  `is_offline` tinyint(1) DEFAULT 0,
  `queued_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zatca_requests`
--

CREATE TABLE `zatca_requests` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `zatca_invoice_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT NULL,
  `request_type` varchar(50) DEFAULT NULL,
  `request_payload` longtext DEFAULT NULL,
  `request_url` varchar(255) DEFAULT NULL,
  `request_method` varchar(10) DEFAULT NULL,
  `request_headers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_headers`)),
  `response_status_code` int(11) DEFAULT NULL,
  `response_body` longtext DEFAULT NULL,
  `response_headers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_headers`)),
  `status` varchar(50) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `error_code` varchar(50) DEFAULT NULL,
  `requested_at` datetime DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `response_time_ms` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendances`
--
ALTER TABLE `attendances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attendances_employee` (`employee_id`);
