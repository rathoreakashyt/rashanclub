CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `salary` decimal(15,2) DEFAULT 0.00,
  `commission` decimal(15,2) DEFAULT 0.00,
  `outlet_id` varchar(255) DEFAULT NULL,
  `will_login` varchar(10) DEFAULT 'Yes',
  `del_status` varchar(20) DEFAULT 'Live',
  `photo` varchar(255) DEFAULT NULL,
  `discount_permission_code` varchar(50) DEFAULT NULL,
  `discount_amt` decimal(15,2) DEFAULT 0.00,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `session_timeout` int(11) DEFAULT 0,
  `login_notifications` tinyint(1) DEFAULT 0,
  `question` text DEFAULT NULL,
  `answer` text DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_saas` varchar(20) DEFAULT NULL,
  `active_status` varchar(20) DEFAULT 'Active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `phone`, `salary`, `commission`, `outlet_id`, `will_login`, `del_status`, `photo`, `discount_permission_code`, `discount_amt`, `start_date`, `end_date`, `company_id`, `two_factor_enabled`, `session_timeout`, `login_notifications`, `question`, `answer`, `remember_token`, `email_verified_at`, `created_at`, `updated_at`, `is_saas`, `active_status`) VALUES
(1, 'Super Admin', 'admin@rashankidukan.com', '$2y$12$wvT.hUM0BagbvySROggp5OB5Yo4AwOCFxnzz.5divTIvaHB1LkwcC', '1', NULL, 0.00, 0.00, NULL, 'Yes', 'Live', NULL, NULL, 0.00, NULL, NULL, 1, 0, 0, 0, 'Pet name?', 'Mickey', NULL, '2026-09-12 17:01:46', '2026-09-12 17:01:46', '2026-09-12 17:01:46', NULL, 'Active');
