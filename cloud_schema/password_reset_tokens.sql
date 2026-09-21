CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `account_type` varchar(50) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `configuration` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`configuration`)),
  `is_active` tinyint(1) DEFAULT 1,
  `status` varchar(20) DEFAULT 'Enable',
  `is_deletable` varchar(5) DEFAULT 'Yes',
  `sort_id` int(11) DEFAULT 0,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sync_version` bigint(20) UNSIGNED DEFAULT 1,
  `current_balance` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `name`, `account_type`, `type`, `configuration`, `is_active`, `status`, `is_deletable`, `sort_id`, `user_id`, `company_id`, `del_status`, `created_at`, `updated_at`, `sync_version`, `current_balance`) VALUES
(1, 'Cash', 'Cash', NULL, NULL, 1, 'Enable', 'No', 1, NULL, 1, 'Live', '2026-09-12 17:45:37', '2026-09-12 17:45:37', 1, 0.00),
(2, 'Bank', 'Bank_Account', NULL, NULL, 1, 'Enable', 'No', 2, NULL, 1, 'Live', '2026-09-12 17:45:37', '2026-09-12 17:45:37', 1, 0.00),
(3, 'Paypal', 'Paypal', NULL, NULL, 1, 'Enable', 'No', 3, NULL, 1, 'Live', '2026-09-12 17:45:37', '2026-09-12 17:45:37', 1, 0.00),
(4, 'Stripe', 'Stripe', NULL, NULL, 1, 'Enable', 'No', 4, NULL, 1, 'Live', '2026-09-12 17:45:37', '2026-09-12 17:45:37', 1, 0.00),
(5, 'Loyalty Point', 'Loyalty Point', NULL, NULL, 1, 'Enable', 'No', 5, NULL, 1, 'Live', '2026-09-12 17:45:37', '2026-09-12 17:45:37', 1, 0.00);
