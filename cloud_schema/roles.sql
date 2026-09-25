CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'web', '2026-09-12 17:19:32', '2026-09-12 17:19:32'),
(2, 'Super Admin', 'web', '2026-09-12 17:24:26', '2026-09-12 17:24:26'),
(3, 'Super Admin', 'web', '2026-09-12 17:57:13', '2026-09-12 17:57:13'),
(4, 'Super Admin', 'web', '2026-09-12 18:20:42', '2026-09-12 18:20:42'),
(5, 'Super Admin', 'web', '2026-09-12 18:24:14', '2026-09-12 18:24:14'),
(6, 'Super Admin', 'web', '2026-09-13 02:08:17', '2026-09-13 02:08:17');
