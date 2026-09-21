CREATE TABLE `taxs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tax_name` varchar(255) DEFAULT NULL,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `parent_tax_id` bigint(20) UNSIGNED DEFAULT NULL,
  `show_in_item_profile` tinyint(1) DEFAULT 0,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `time_zones`
--

CREATE TABLE `time_zones` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `country_code` varchar(10) DEFAULT NULL,
  `zone_name` varchar(255) DEFAULT NULL,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `time_zones`
--

INSERT INTO `time_zones` (`id`, `name`, `country_code`, `zone_name`, `del_status`, `created_at`, `updated_at`) VALUES
(1, 'Asia/Kolkata', 'IN', 'Asia/Kolkata', 'Live', '2026-09-12 17:45:37', '2026-09-12 17:45:37');
