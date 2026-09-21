CREATE TABLE `idempotency_keys` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(64) NOT NULL,
  `device_id` varchar(100) DEFAULT '',
  `response_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_snapshot`)),
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `idempotency_keys`
--

INSERT INTO `idempotency_keys` (`id`, `key`, `device_id`, `response_snapshot`, `expires_at`, `created_at`) VALUES
(1, 'b5c5e08b2c33d800adebad38a51ce5d65cea088d216edf272d7c73f4afc4ea8c', 'dfe329f5-086a-43f2-b4c7-8ecdb0944a13', '{\"entity_type\":\"configs\",\"configs\":{\"categories\":[],\"brands\":[],\"units\":[],\"racks\":[],\"variations\":[],\"expense_categories\":[]},\"server_time\":\"2026-09-13 16:32:26\"}', '2026-09-14 14:32:26', '2026-09-13 11:02:26'),
(2, 'f5055bb5d0fee3fd94307e8286e14b6249f8c00c215056019081b4465fae9135', 'dfe329f5-086a-43f2-b4c7-8ecdb0944a13', '{\"entity_type\":\"customers\",\"customers\":[{\"local_id\":\"CUS20260913163159299\",\"local_code\":\"CUS20260913163159299\",\"server_id\":null,\"error\":\"SQLSTATE[22007]: Invalid datetime format: 1366 Incorrect integer value: \'No\' for column `rashankidukan_cloud`.`customers`.`is_installment_customer` at row 1 (Connection: mysql, SQL: insert into `customers` (`name`, `email`, `phone`, `address`, `city`, `postal_code`, `gst_number`, `opening_balance`, `opening_balance_type`, `credit_limit`, `customer_type`, `business_type`, `same_or_diff_state`, `loyalty_point`, `is_installment_customer`, `discount`, `date_of_birth`, `date_of_anniversary`, `work_address`, `guarantor_name`, `guarantor_mobile`, `updated_at`, `user_id`, `company_id`, `del_status`, `created_at`) values (akash, ?, 8888888888, ?, ?, ?, ?, 0, Dr, 0, B2C, B2C, ?, 0, No, ?, ?, ?, ?, ?, ?, 2026-09-13 16:32:26, 1, 1, Live, 2026-09-13 16:32:26))\"}],\"server_time\":\"2026-09-13 16:32:26\"}', '2026-09-14 14:32:26', '2026-09-13 11:02:26'),
(3, '87c55fd0304ad8559449d2420b85fc14db68679ef5a1053f960ebfcfc13da71a', 'dfe329f5-086a-43f2-b4c7-8ecdb0944a13', '{\"entity_type\":\"customers\",\"customers\":[{\"local_id\":\"CUS20260913163301371\",\"local_code\":\"CUS20260913163301371\",\"server_id\":null,\"error\":\"SQLSTATE[22007]: Invalid datetime format: 1366 Incorrect integer value: \'No\' for column `rashankidukan_cloud`.`customers`.`is_installment_customer` at row 1 (Connection: mysql, SQL: insert into `customers` (`name`, `email`, `phone`, `address`, `city`, `postal_code`, `gst_number`, `opening_balance`, `opening_balance_type`, `credit_limit`, `customer_type`, `business_type`, `same_or_diff_state`, `loyalty_point`, `is_installment_customer`, `discount`, `date_of_birth`, `date_of_anniversary`, `work_address`, `guarantor_name`, `guarantor_mobile`, `updated_at`, `user_id`, `company_id`, `del_status`, `created_at`) values (akash, ?, 8888888888, ?, ?, ?, ?, 0, Dr, 0, B2C, B2C, ?, 0, No, ?, ?, ?, ?, ?, ?, 2026-09-13 16:33:25, 1, 1, Live, 2026-09-13 16:33:25))\"}],\"server_time\":\"2026-09-13 16:33:25\"}', '2026-09-14 14:33:25', '2026-09-13 11:03:25');
