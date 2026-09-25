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
