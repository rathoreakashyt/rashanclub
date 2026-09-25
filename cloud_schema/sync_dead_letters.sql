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
