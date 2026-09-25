CREATE TABLE `sync_local_mappings` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(100) NOT NULL,
  `local_id` bigint(20) NOT NULL,
  `server_id` bigint(20) NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `outlet_id` bigint(20) UNSIGNED NOT NULL,
  `device_id` varchar(64) DEFAULT '',
  `local_ref` varchar(100) DEFAULT '',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sync_local_mappings`
--

INSERT INTO `sync_local_mappings` (`id`, `entity_type`, `local_id`, `server_id`, `company_id`, `outlet_id`, `device_id`, `local_ref`, `created_at`, `updated_at`) VALUES
(1, 'installed_modules', 1, 1, 1, 1, 'dfe329f5-086a-43f2-b4c7-8ecdb0944a13', '', NULL, '2026-09-13 12:59:14');
