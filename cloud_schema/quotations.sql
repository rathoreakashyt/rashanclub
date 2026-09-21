CREATE TABLE `quotations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference_no` varchar(255) DEFAULT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `date` date DEFAULT NULL,
  `grand_total` decimal(15,3) DEFAULT 0.000,
  `note` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quotation_details`
--

CREATE TABLE `quotation_details` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `quotation_id` bigint(20) UNSIGNED DEFAULT NULL,
  `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `unit_price` decimal(15,3) DEFAULT 0.000,
  `total` decimal(15,3) DEFAULT 0.000,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `racks`
--

CREATE TABLE `racks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sync_version` bigint(20) UNSIGNED DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registers`
--

CREATE TABLE `registers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `opening_balance_date_time` datetime DEFAULT NULL,
  `closing_balance_date_time` datetime DEFAULT NULL,
  `opening_details` text DEFAULT NULL,
  `payment_methods_sale` text DEFAULT NULL,
  `others_currency` text DEFAULT NULL,
  `closing_balance` decimal(15,2) DEFAULT 0.00,
  `sale_paid_amount` decimal(15,2) DEFAULT 0.00,
  `refund_amount` decimal(15,2) DEFAULT 0.00,
  `customer_due_receive` decimal(15,2) DEFAULT 0.00,
  `total_purchase` decimal(15,2) DEFAULT 0.00,
  `total_downpayment` decimal(15,2) DEFAULT 0.00,
  `total_installmentcollection` decimal(15,2) DEFAULT 0.00,
  `total_servicing` decimal(15,2) DEFAULT 0.00,
  `total_purchase_return` decimal(15,2) DEFAULT 0.00,
  `total_due_payment` decimal(15,2) DEFAULT 0.00,
  `total_expense` decimal(15,2) DEFAULT 0.00,
  `register_status` tinyint(1) DEFAULT 1,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `counter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
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
