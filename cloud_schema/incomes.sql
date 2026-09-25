CREATE TABLE `incomes` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  `amount` decimal(15,3) DEFAULT 0.000,
  `note` text DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `employee_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `income_categories`
--

CREATE TABLE `income_categories` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `installed_modules`
--

CREATE TABLE `installed_modules` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `version` varchar(255) DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `zip_path` varchar(255) DEFAULT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(255) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `installed_modules`
--

INSERT INTO `installed_modules` (`id`, `name`, `description`, `version`, `author`, `zip_path`, `is_enabled`, `company_id`, `del_status`, `created_at`, `updated_at`) VALUES
(1, 'Business Club', 'Loyalty program with wallet, cashback & membership tiers for customers', '1.0.0', 'RashanKiDukan', NULL, 1, 1, 'Live', '2026-09-13 12:59:14', '2026-09-13 12:59:14');
