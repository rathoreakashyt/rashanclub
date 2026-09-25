CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `personal_access_tokens`
--

INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 'App\\Models\\User', 1, 'wpf-pos', 'f40574e39d5af98a930d467204fcbfb3fb9cbd394daf0b5a8cdd8df2f48016e0', '[\"*\"]', '2026-09-13 14:03:50', NULL, '2026-09-13 12:59:13', '2026-09-13 14:03:50'),
(2, 'App\\Models\\User', 1, 'wpf-pos', 'df4c85efa1b6491df6fdeb4edc00ca0ce5e8c970f7bfc1f06655e4cb96d5ba3b', '[\"*\"]', '2026-09-13 14:34:07', NULL, '2026-09-13 14:04:09', '2026-09-13 14:34:07'),
(3, 'App\\Models\\User', 1, 'wpf-pos', 'c9fa13e68c798e21938ee3b77aa5b1161ad58b1d0c825e23eabb6a742921df8a', '[\"*\"]', NULL, NULL, '2026-09-13 14:34:09', '2026-09-13 14:34:09'),
(4, 'App\\Models\\User', 1, 'wpf-pos', '4112fed0a1b263ab9acfa37b5d1fd9850484090adb7713469e892a2e14f62984', '[\"*\"]', '2026-09-13 16:30:29', NULL, '2026-09-13 14:34:12', '2026-09-13 16:30:29'),
(5, 'App\\Models\\User', 1, 'wpf-pos', '0b2a25fdd4eec98ae8d16d3140591ee7d192d7ab4f2c4a0c84dcfe12bb320383', '[\"*\"]', '2026-09-16 08:10:33', NULL, '2026-09-16 08:09:59', '2026-09-16 08:10:33'),
(6, 'App\\Models\\User', 1, 'wpf-pos', '9818a7589d3b363f6985a0e8ea9fe3cb3a0c70b0464fae64a297470889c0d9dd', '[\"*\"]', '2026-09-16 10:15:40', NULL, '2026-09-16 08:11:23', '2026-09-16 10:15:40');
