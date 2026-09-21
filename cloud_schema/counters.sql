CREATE TABLE `counters` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `printer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sync_version` bigint(20) UNSIGNED DEFAULT 1,
  `status` varchar(20) DEFAULT 'Enable',
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `counters`
--

INSERT INTO `counters` (`id`, `name`, `outlet_id`, `printer_id`, `user_id`, `company_id`, `del_status`, `created_at`, `updated_at`, `sync_version`, `status`, `description`) VALUES
(1, 'Counter 1', 1, NULL, 1, 1, 'Live', '2026-09-12 17:38:56', '2026-09-12 17:38:56', 1, 'Enable', NULL);
