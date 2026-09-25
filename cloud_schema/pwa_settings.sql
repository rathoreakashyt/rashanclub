CREATE TABLE `pwa_settings` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `app_name` varchar(255) DEFAULT NULL,
  `short_name` varchar(255) DEFAULT NULL,
  `theme_color` varchar(50) DEFAULT NULL,
  `background_color` varchar(50) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `start_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pwa_settings`
--

INSERT INTO `pwa_settings` (`id`, `company_id`, `app_name`, `short_name`, `theme_color`, `background_color`, `logo`, `start_url`, `created_at`, `updated_at`) VALUES
(1, 1, 'RashanKidukan POS', 'POS', '#7367f0', '#ffffff', NULL, 'https://rashankidukanindia.com', '2026-09-12 14:57:20', '2026-09-12 14:57:20');
