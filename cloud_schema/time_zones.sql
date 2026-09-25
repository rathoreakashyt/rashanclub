CREATE TABLE `time_zones` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `country_code` varchar(10) DEFAULT NULL,
  `zone_name` varchar(255) DEFAULT NULL,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `time_zones`
--

INSERT INTO `time_zones` (`id`, `name`, `country_code`, `zone_name`, `del_status`, `created_at`, `updated_at`) VALUES
(1, 'Asia/Kolkata', 'IN', 'Asia/Kolkata', 'Live', '2026-09-12 17:45:37', '2026-09-12 17:45:37');
