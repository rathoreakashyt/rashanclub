CREATE TABLE `customer_receives` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  `amount` decimal(15,3) DEFAULT 0.000,
  `date` date DEFAULT NULL,
  `note` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `reference_no` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_wallets`
--

CREATE TABLE `customer_wallets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `total_earned` decimal(15,2) DEFAULT 0.00,
  `balance` decimal(15,2) DEFAULT 0.00,
  `total_redeemed` decimal(15,2) DEFAULT 0.00,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `damages`
--

CREATE TABLE `damages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference_no` varchar(255) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `total_loss` decimal(15,3) DEFAULT 0.000,
  `note` text DEFAULT NULL,
  `employee_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `damage_type` varchar(50) DEFAULT 'expired'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `damage_details`
--

CREATE TABLE `damage_details` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `damage_id` bigint(20) UNSIGNED DEFAULT NULL,
  `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `date` date DEFAULT NULL,
  `damage_quantity` decimal(15,3) DEFAULT 0.000,
  `last_purchase_price` decimal(15,3) DEFAULT 0.000,
  `loss_amount` decimal(15,3) DEFAULT 0.000,
  `total_amount` decimal(15,3) DEFAULT 0.000,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_partners`
--

CREATE TABLE `delivery_partners` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `commission_percent` decimal(5,2) DEFAULT 0.00,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `denominations`
--

CREATE TABLE `denominations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `value` decimal(15,2) DEFAULT 0.00,
  `type` varchar(50) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deposit_withdraws`
--

CREATE TABLE `deposit_withdraws` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference_no` varchar(255) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `type` enum('Deposit','Withdraw') DEFAULT NULL,
  `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  `amount` decimal(15,3) DEFAULT 0.000,
  `note` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_advance_payments`
--

CREATE TABLE `employee_advance_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference_no` varchar(255) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT 0.00,
  `note` text DEFAULT NULL,
  `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  `employee_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` bigint(20) UNSIGNED NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sync_version` bigint(20) UNSIGNED DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feature_activations`
--

CREATE TABLE `feature_activations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `feature_key` varchar(255) NOT NULL,
  `feature_name` varchar(255) NOT NULL,
  `group` varchar(255) DEFAULT 'general',
  `is_active` tinyint(1) DEFAULT 1,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `feature_activations`
--

INSERT INTO `feature_activations` (`id`, `feature_key`, `feature_name`, `group`, `is_active`, `company_id`, `created_at`, `updated_at`) VALUES
(1, 'home', 'Home', 'Main', 1, 1, '2026-09-12 17:38:56', '2026-09-12 17:38:56'),
(2, 'dashboard', 'Dashboard', 'Main', 1, 1, '2026-09-12 17:38:56', '2026-09-12 17:38:56'),
(3, 'booking', 'Booking', 'Main', 1, 1, '2026-09-12 17:38:56', '2026-09-12 17:38:56'),
(4, 'outlet', 'Outlet', 'Main', 1, 1, '2026-09-12 17:38:56', '2026-09-12 17:38:56'),
(5, 'items', 'Items', 'Item & Stock', 1, 1, '2026-09-12 17:38:56', '2026-09-12 17:38:56'),
(6, 'categories', 'Categories', 'Item & Stock', 1, 1, '2026-09-12 17:38:56', '2026-09-12 17:38:56'),
(7, 'brands', 'Brands', 'Item & Stock', 1, 1, '2026-09-12 17:38:56', '2026-09-12 17:38:56'),
(8, 'units', 'Units', 'Item & Stock', 1, 1, '2026-09-12 17:38:56', '2026-09-12 17:38:56'),
(9, 'stock', 'Stock', 'Item & Stock', 1, 1, '2026-09-12 17:38:56', '2026-09-12 17:38:56'),
(10, 'low_stock', 'Low Stock', 'Item & Stock', 1, 1, '2026-09-12 17:38:56', '2026-09-12 17:38:56'),
(11, 'pos', 'POS', 'Sale & Customer', 1, 1, '2026-09-12 17:38:56', '2026-09-12 17:38:56'),
(12, 'sales', 'Sales', 'Sale & Customer', 1, 1, '2026-09-12 17:38:56', '2026-09-12 17:38:56'),
(13, 'customers', 'Customers', 'Sale & Customer', 1, 1, '2026-09-12 17:38:56', '2026-09-12 17:38:56'),
(14, 'promotions', 'Promotions', 'Sale & Customer', 1, 1, '2026-09-12 17:38:56', '2026-09-12 17:38:56'),
(15, 'purchases', 'Purchases', 'Purchase & Supplier', 1, 1, '2026-09-12 17:38:56', '2026-09-12 17:38:56'),
(16, 'suppliers', 'Suppliers', 'Purchase & Supplier', 1, 1, '2026-09-12 17:38:56', '2026-09-12 17:38:56'),
(17, 'transfers', 'Transfers', 'Transfer & Damage', 1, 1, '2026-09-12 17:38:56', '2026-09-12 17:38:56'),
(18, 'damages', 'Damages', 'Transfer & Damage', 1, 1, '2026-09-12 17:38:56', '2026-09-12 17:38:56'),
(19, 'quotations', 'Quotations', 'Transfer & Damage', 1, 1, '2026-09-12 17:38:56', '2026-09-12 17:38:56'),
(20, 'dashboard_access', 'Dashboard Access', 'Report & Settings', 1, 1, '2026-09-12 17:38:56', '2026-09-12 17:38:56'),
(21, 'sale', 'Sale', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(22, 'sale_list', 'Sale List', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(23, 'sale_promotion_add', 'Add Promotion', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(24, 'sale_promotion_list', 'List Promotion', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(25, 'sale_delivery_add', 'Add Delivery Partner', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(26, 'sale_delivery_list', 'List Delivery Partner', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(27, 'sale_return', 'Sale Return', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(28, 'sale_return_add', 'Add Sale Return', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(29, 'sale_return_list', 'List Sale Return', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(30, 'installment_sale', 'Installment Sale', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(31, 'installment_add', 'Add Installment', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(32, 'installment_list', 'List Installment', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(33, 'installment_collection', 'Installment Collection', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(34, 'customer', 'Customer', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(35, 'customer_add', 'Add Customer', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(36, 'customer_list', 'List Customer', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(37, 'customer_installment_add', 'Add Installment Customer', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(38, 'customer_installment_list', 'List Installment Customer', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(39, 'customer_receive_add', 'Add Customer Receive', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(40, 'customer_receive_list', 'List Customer Receive', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(41, 'income', 'Income', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(42, 'income_add', 'Add Income', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(43, 'income_list', 'List Income', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(44, 'income_category_add', 'Add Income Category', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(45, 'income_category_list', 'List Income Category', 'sale', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(46, 'purchase', 'Purchase', 'purchase', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(47, 'purchase_add', 'Add Purchase', 'purchase', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(48, 'purchase_list', 'List Purchase', 'purchase', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(49, 'purchase_return', 'Purchase Return', 'purchase', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(50, 'purchase_return_add', 'Add Purchase Return', 'purchase', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(51, 'purchase_return_list', 'List Purchase Return', 'purchase', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(52, 'supplier', 'Supplier', 'purchase', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(53, 'supplier_add', 'Add Supplier', 'purchase', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(54, 'supplier_list', 'List Supplier', 'purchase', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(55, 'supplier_payment_add', 'Add Supplier Payment', 'purchase', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(56, 'supplier_payment_list', 'List Supplier Payment', 'purchase', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(57, 'expense', 'Expense', 'purchase', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(58, 'expense_add', 'Add Expense', 'purchase', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(59, 'expense_list', 'List Expense', 'purchase', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(60, 'expense_category_add', 'Add Expense Category', 'purchase', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(61, 'expense_category_list', 'List Expense Category', 'purchase', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(62, 'item', 'Item', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(63, 'item_add', 'Add Item', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(64, 'item_list', 'List Item', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(65, 'item_bulk_update', 'Bulk Item Update', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(66, 'item_bulk_import', 'Bulk Item Import', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(67, 'item_opening_import', 'Opening Stock Import', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(68, 'item_configuration', 'Item Configuration', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(69, 'ic_category_add', 'Add Category', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(70, 'ic_category_list', 'List Category', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(71, 'ic_brand_add', 'Add Brand', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(72, 'ic_brand_list', 'List Brand', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(73, 'ic_unit_add', 'Add Unit', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(74, 'ic_unit_list', 'List Unit', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(75, 'ic_rack_add', 'Add Rack', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(76, 'ic_rack_list', 'List Rack', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(77, 'ic_variation_add', 'Add Variation', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(78, 'ic_variation_list', 'List Variation', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(79, 'stock_view', 'View Stock', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(80, 'stock_low', 'Low Stock', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(81, 'transfer', 'Transfer', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(82, 'transfer_add', 'Add Transfer', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(83, 'transfer_list', 'List Transfer', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(84, 'damage', 'Damage', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(85, 'damage_add', 'Add Damage', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(86, 'damage_list', 'List Damage', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(87, 'quotation', 'Quotation', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(88, 'quotation_add', 'Add Quotation', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(89, 'quotation_list', 'List Quotation', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(90, 'fixed_asset', 'Fixed Asset', 'fixed_asset', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(91, 'fixed_asset_add', 'Add Fixed Asset', 'fixed_asset', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(92, 'fixed_asset_list', 'List Fixed Asset', 'fixed_asset', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(93, 'fixed_asset_stock_in_add', 'Add Fixed Asset Stock In', 'fixed_asset', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(94, 'fixed_asset_stock_in_list', 'List Fixed Asset Stock In', 'fixed_asset', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(95, 'fixed_asset_stock_out_add', 'Add Fixed Asset Stock Out', 'fixed_asset', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(96, 'fixed_asset_stock_out_list', 'List Fixed Asset Stock Out', 'fixed_asset', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(97, 'business_club', 'Business Club', 'marketing', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(98, 'business_club_dashboard', 'Business Club Dashboard', 'marketing', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(99, 'business_club_settings', 'Business Club Settings', 'marketing', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(100, 'business_club_wallets', 'Business Club Wallets', 'marketing', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(101, 'business_club_register', 'Business Club Register', 'marketing', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(102, 'price_list', 'Price Lists', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(103, 'price_list_add', 'Add Price List', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(104, 'price_list_list', 'List Price List', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(105, 'warranty_servicing', 'Warranty & Servicing', 'warranty', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(106, 'warranty_add', 'Add Warranty', 'warranty', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(107, 'warranty_list', 'List Warranty', 'warranty', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(108, 'warranty_checking', 'Warranty Checking', 'warranty', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(109, 'servicing_add', 'Add Servicing', 'warranty', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(110, 'servicing_list', 'List Servicing', 'warranty', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(111, 'accounting', 'Accounting', 'accounting', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(112, 'payment_account', 'Payment Account', 'accounting', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(113, 'payment_account_add', 'Add Payment Account', 'accounting', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(114, 'payment_account_list', 'List Payment Account', 'accounting', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(115, 'payment_account_sort', 'Sort Account', 'accounting', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(116, 'deposit_withdraw', 'Deposit/Withdraw', 'accounting', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(117, 'accounting_deposit_add', 'Add Deposit/Withdraw', 'accounting', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(118, 'accounting_deposit_list', 'List Deposit/Withdraw', 'accounting', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(119, 'accounting_balance', 'Account Balance', 'accounting', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(120, 'accounting_statement', 'Account Statement', 'accounting', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(121, 'accounting_balance_sheet', 'Balance Sheet', 'accounting', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(122, 'accounting_trial_balance', 'Trial Balance', 'accounting', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(123, 'accounting_transaction_history', 'Transaction History', 'accounting', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(124, 'marketing', 'Marketing', 'marketing', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(125, 'marketing_email', 'Email Marketing', 'marketing', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(126, 'marketing_sms', 'SMS Marketing', 'marketing', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(127, 'marketing_whatsapp', 'WhatsApp Marketing', 'marketing', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(128, 'hrm', 'Human Resource Management', 'hrm', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(129, 'role_permission', 'Role Permission', 'hrm', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(130, 'role_add', 'Add Role', 'hrm', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(131, 'role_list', 'List Role', 'hrm', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(132, 'employee', 'Employee', 'hrm', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(133, 'employee_add', 'Add Employee', 'hrm', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(134, 'employee_list', 'List Employee', 'hrm', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(135, 'employee_update_profile', 'Update Profile', 'hrm', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(136, 'attendance', 'Attendance', 'hrm', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(137, 'attendance_add', 'Add Attendance', 'hrm', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(138, 'attendance_list', 'List Attendance', 'hrm', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(139, 'salary', 'Salary/Payroll', 'hrm', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(140, 'salary_add', 'Add Salary', 'hrm', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(141, 'salary_list', 'List Salary', 'hrm', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(142, 'employee_advance', 'Employee Advance Payment', 'hrm', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(143, 'employee_advance_add', 'Add Employee Advance Payment', 'hrm', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(144, 'employee_advance_list', 'List Employee Advance Payment', 'hrm', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(145, 'report', 'Reports', 'report', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(146, 'report_all', 'All Reports', 'report', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(147, 'settings_all', 'All Settings', 'settings', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(148, 'feature_activation', 'Feature Activation', 'settings', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(149, 'denomination_add', 'Add Denomination', 'settings', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(150, 'denomination_list', 'List Denomination', 'settings', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(151, 'currency_add', 'Add Currency', 'settings', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(152, 'currency_list', 'List Currency', 'settings', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(153, 'printer_add', 'Add Printer', 'settings', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(154, 'printer_list', 'List Printer', 'settings', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(155, 'counter_add', 'Add Counter', 'settings', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(156, 'counter_list', 'List Counter', 'settings', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(157, 'module_add', 'Add Module', 'modules', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(158, 'module_list', 'List Modules', 'modules', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(159, 'gst', 'GST', 'accounting', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17'),
(160, 'inventory', 'Inventory', 'stock', 1, 1, '2026-09-13 02:08:17', '2026-09-13 02:08:17');
