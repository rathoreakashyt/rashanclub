CREATE TABLE `installment_sales` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference_no` varchar(255) DEFAULT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `date` date DEFAULT NULL,
  `price` decimal(15,3) DEFAULT 0.000,
  `discount_amount` decimal(15,3) DEFAULT 0.000,
  `percentage_of_interest` decimal(5,2) DEFAULT 0.00,
  `interest_amount` decimal(15,3) DEFAULT 0.000,
  `shipping_other` decimal(15,3) DEFAULT 0.000,
  `total` decimal(15,3) DEFAULT 0.000,
  `down_payment` decimal(15,3) DEFAULT 0.000,
  `remaining` decimal(15,3) DEFAULT 0.000,
  `paid_amount` decimal(15,3) DEFAULT 0.000,
  `due_amount` decimal(15,3) DEFAULT 0.000,
  `status` varchar(50) DEFAULT 'Active',
  `installment_count` int(11) DEFAULT 0,
  `note` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `installment_sale_details`
--

CREATE TABLE `installment_sale_details` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `installment_sale_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `paid_date` date DEFAULT NULL,
  `amount` decimal(15,3) DEFAULT 0.000,
  `paid_amount` decimal(15,3) DEFAULT 0.000,
  `remaining_amount` decimal(15,3) DEFAULT 0.000,
  `paid_status` varchar(50) DEFAULT 'Unpaid',
  `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  `note` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `installment_sale_payments`
--

CREATE TABLE `installment_sale_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `installment_sale_id` bigint(20) UNSIGNED DEFAULT NULL,
  `installment_sale_detail_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `amount` decimal(15,3) DEFAULT 0.000,
  `payment_type` varchar(50) DEFAULT NULL,
  `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  `check_issue_date` date DEFAULT NULL,
  `check_expiry_date` date DEFAULT NULL,
  `note` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_hash_chain`
--

CREATE TABLE `invoice_hash_chain` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `previous_hash` varchar(255) DEFAULT NULL,
  `current_hash` varchar(255) DEFAULT NULL,
  `zatca_invoice_id` bigint(20) UNSIGNED DEFAULT NULL,
  `chain_index` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `busy_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `code` varchar(255) DEFAULT NULL,
  `alternative_name` varchar(255) DEFAULT NULL,
  `generic_name` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `expiry_date_maintain` tinyint(1) DEFAULT 0,
  `category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `rack_id` bigint(20) UNSIGNED DEFAULT NULL,
  `brand_id` bigint(20) UNSIGNED DEFAULT NULL,
  `supplier_id` bigint(20) UNSIGNED DEFAULT NULL,
  `alert_quantity` decimal(15,3) DEFAULT 0.000,
  `unit_type` varchar(50) DEFAULT NULL,
  `purchase_unit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sale_unit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `conversion_rate` decimal(15,3) DEFAULT 1.000,
  `purchase_price` decimal(15,3) DEFAULT 0.000,
  `last_three_purchase_avg` decimal(15,3) DEFAULT 0.000,
  `last_purchase_price` decimal(15,3) DEFAULT 0.000,
  `mrp_price` decimal(15,3) DEFAULT 0.000,
  `sale_price` decimal(15,3) DEFAULT 0.000,
  `profit_margin` decimal(15,3) DEFAULT 0.000,
  `whole_sale_price` decimal(15,3) DEFAULT 0.000,
  `description` text DEFAULT NULL,
  `warranty` varchar(50) DEFAULT NULL,
  `warranty_date` date DEFAULT NULL,
  `guarantee` varchar(50) DEFAULT NULL,
  `guarantee_date` date DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `tax_information` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tax_information`)),
  `tax_string` varchar(255) DEFAULT NULL,
  `tax_type` varchar(50) DEFAULT NULL,
  `applicable_tax_id` bigint(20) UNSIGNED DEFAULT NULL,
  `hsn_code` varchar(50) DEFAULT NULL,
  `variation_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variation_details`)),
  `enable_disable_status` tinyint(1) DEFAULT 1,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `loyalty_point` int(11) DEFAULT 0,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sync_version` bigint(20) UNSIGNED DEFAULT 1,
  `item_code` varchar(255) DEFAULT NULL,
  `unit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `price` decimal(15,2) DEFAULT 0.00,
  `cost_price` decimal(15,2) DEFAULT 0.00,
  `stock_quantity` decimal(15,2) DEFAULT 0.00,
  `opening_stock` decimal(15,2) DEFAULT 0.00,
  `minimum_stock` decimal(15,2) DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `discount_type` varchar(20) DEFAULT NULL,
  `discount_rate` decimal(5,2) DEFAULT 0.00,
  `status` varchar(20) DEFAULT 'Enable',
  `is_imei` tinyint(1) DEFAULT 0,
  `barcode` varchar(255) DEFAULT NULL,
  `serial_number_needed` tinyint(1) DEFAULT 0,
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_categories`
--

CREATE TABLE `item_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `sort_id` int(11) DEFAULT 0,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sync_version` bigint(20) UNSIGNED DEFAULT 1,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `local_id_map`
--

CREATE TABLE `local_id_map` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `local_id` varchar(100) NOT NULL,
  `local_code` varchar(100) DEFAULT NULL,
  `server_id` bigint(20) UNSIGNED NOT NULL,
  `device_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `marketing_logs`
--

CREATE TABLE `marketing_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `channel` varchar(20) NOT NULL,
  `to` varchar(255) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `error` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `model_has_permissions`
--

CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `model_has_roles`
--

CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `model_has_roles`
--

INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
(1, 'App\\Models\\User', 1),
(1, 'AppModelsUser', 1);
