CREATE TABLE `attendances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference_no` varchar(255) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `employee_id` bigint(20) UNSIGNED DEFAULT NULL,
  `in_time` time DEFAULT NULL,
  `out_time` time DEFAULT NULL,
  `note` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `service_seller_id` bigint(20) UNSIGNED DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `service_note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
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
-- Table structure for table `business_club_members`
--

CREATE TABLE `business_club_members` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(20) NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `membership_amount` decimal(15,2) DEFAULT 0.00,
  `locked_balance` decimal(15,2) DEFAULT 0.00,
  `earned_balance` decimal(15,2) DEFAULT 0.00,
  `total_earned` decimal(15,2) DEFAULT 0.00,
  `total_redeemed` decimal(15,2) DEFAULT 0.00,
  `status` varchar(20) DEFAULT 'active',
  `joined_at` datetime DEFAULT NULL,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `business_club_settings`
--

CREATE TABLE `business_club_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `business_partner_name` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `profit_percentage` decimal(5,2) DEFAULT 50.00,
  `redemption_date` int(11) DEFAULT 1,
  `min_purchase_amount` decimal(15,2) DEFAULT 10000.00,
  `minimum_bill_amount` decimal(15,2) DEFAULT 0.00,
  `membership_amount` decimal(15,2) DEFAULT 10000.00,
  `profit_share_percentage` decimal(5,2) DEFAULT 50.00,
  `redemption_day` int(11) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `business_club_transactions`
--

CREATE TABLE `business_club_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `sale_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` varchar(30) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `balance_before` decimal(15,2) DEFAULT 0.00,
  `balance_after` decimal(15,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `combo_items`
--

CREATE TABLE `combo_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `combo_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` decimal(15,3) DEFAULT 0.000,
  `amount` decimal(15,3) DEFAULT 0.000,
  `total` decimal(15,3) DEFAULT 0.000,
  `show_in_invoice` tinyint(1) DEFAULT 1,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `combo_sales`
--

CREATE TABLE `combo_sales` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sale_id` bigint(20) UNSIGNED DEFAULT NULL,
  `combo_sale_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `combo_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `combo_item_qty` decimal(15,3) DEFAULT 0.000,
  `combo_item_price` decimal(15,3) DEFAULT 0.000,
  `combo_item_seller_id` bigint(20) UNSIGNED DEFAULT NULL,
  `show_in_invoice` varchar(10) DEFAULT 'Yes',
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT 1,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `white_label` text DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `currency` varchar(10) DEFAULT NULL,
  `currency_symbol` varchar(10) DEFAULT NULL,
  `timezone` varchar(50) DEFAULT NULL,
  `date_format` varchar(20) DEFAULT NULL,
  `time_format` varchar(20) DEFAULT NULL,
  `fy_start_month` int(11) DEFAULT 1,
  `accounting_method` varchar(50) DEFAULT 'fifo',
  `default_profit_percent` decimal(5,2) DEFAULT 0.00,
  `logo` varchar(255) DEFAULT NULL,
  `del_status` varchar(20) DEFAULT 'Live',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `business_name` varchar(255) DEFAULT NULL,
  `short_name` varchar(50) DEFAULT NULL,
  `currency_position` varchar(10) DEFAULT 'before',
  `precision` int(11) DEFAULT 2,
  `zone_name` varchar(50) DEFAULT 'Asia/Kolkata',
  `default_customer` int(11) DEFAULT 1,
  `default_cursor_position` varchar(50) DEFAULT 'before_input',
  `product_display` varchar(50) DEFAULT 'dropdown',
  `onscreen_keyboard_status` varchar(10) DEFAULT 'off',
  `default_payment` varchar(50) DEFAULT 'cash',
  `payment_settings` text DEFAULT NULL,
  `inv_logo_is_show` tinyint(1) DEFAULT 0,
  `invoice_logo` varchar(255) DEFAULT NULL,
  `invoice_configuration` text DEFAULT NULL,
  `collect_tax` varchar(10) DEFAULT 'No',
  `tax_title` varchar(50) DEFAULT NULL,
  `tax_registration_no` varchar(50) DEFAULT NULL,
  `tax_is_gst` varchar(5) DEFAULT 'No',
  `sms_enable_status` tinyint(1) DEFAULT 0,
  `smtp_enable_status` tinyint(1) DEFAULT 0,
  `e_commerce_checker` varchar(10) DEFAULT 'off',
  `white_label_status` varchar(10) DEFAULT 'off',
  `thousands_separator` varchar(5) DEFAULT ',',
  `decimals_separator` varchar(5) DEFAULT '.',
  `purchase_price_show_hide` varchar(10) DEFAULT 'show',
  `allow_less_sale` varchar(5) DEFAULT 'No',
  `is_rounding_enable` varchar(5) DEFAULT 'No',
  `direct_cart` varchar(5) DEFAULT 'No',
  `register_content` text DEFAULT NULL,
  `grocery_experience` varchar(5) DEFAULT 'No',
  `generic_name_search_option` varchar(5) DEFAULT 'No',
  `product_code_start_from` int(11) DEFAULT 1,
  `smtp_default_selected_in_pos` varchar(5) DEFAULT 'No',
  `sms_default_selected_in_pos` varchar(5) DEFAULT 'No',
  `whatsapp_default_selected_in_pos` varchar(5) DEFAULT 'No',
  `invoice_footer` text DEFAULT NULL,
  `term_conditions` text DEFAULT NULL,
  `installment_days` int(11) DEFAULT 3,
  `minimum_point_to_redeem` decimal(15,2) DEFAULT 0.00,
  `loyalty_rate` decimal(5,2) DEFAULT 0.00,
  `is_loyalty_enable` varchar(5) DEFAULT 'No',
  `website` varchar(255) DEFAULT NULL,
  `tax_registration_number` varchar(255) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `company_email` varchar(255) DEFAULT NULL,
  `busynotify_token` text DEFAULT NULL,
  `busynotify_company_id` varchar(50) DEFAULT NULL,
  `pos_total_payable_type` varchar(50) DEFAULT 'grand_total',
  `letter_head_gap` int(11) DEFAULT 0,
  `letter_footer_gap` int(11) DEFAULT 0,
  `tax_setting` varchar(50) DEFAULT NULL,
  `tax_string` text DEFAULT NULL,
  `smtp_type` varchar(50) DEFAULT NULL,
  `smtp_details` text DEFAULT NULL,
  `sms_service_provider` varchar(100) DEFAULT NULL,
  `sms_details` text DEFAULT NULL,
  `whatsapp_provider` varchar(100) DEFAULT NULL,
  `whatsapp_invoice_enable_status` tinyint(1) DEFAULT 0,
  `whatsapp_app_key` varchar(255) DEFAULT NULL,
  `whatsapp_authkey` varchar(255) DEFAULT NULL,
  `payment_api_setting` text DEFAULT NULL,
  `zatca_configuration` text DEFAULT NULL,
  `gst_api_key` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `white_label`, `name`, `email`, `phone`, `address`, `currency`, `currency_symbol`, `timezone`, `date_format`, `time_format`, `fy_start_month`, `accounting_method`, `default_profit_percent`, `logo`, `del_status`, `created_at`, `updated_at`, `business_name`, `short_name`, `currency_position`, `precision`, `zone_name`, `default_customer`, `default_cursor_position`, `product_display`, `onscreen_keyboard_status`, `default_payment`, `payment_settings`, `inv_logo_is_show`, `invoice_logo`, `invoice_configuration`, `collect_tax`, `tax_title`, `tax_registration_no`, `tax_is_gst`, `sms_enable_status`, `smtp_enable_status`, `e_commerce_checker`, `white_label_status`, `thousands_separator`, `decimals_separator`, `purchase_price_show_hide`, `allow_less_sale`, `is_rounding_enable`, `direct_cart`, `register_content`, `grocery_experience`, `generic_name_search_option`, `product_code_start_from`, `smtp_default_selected_in_pos`, `sms_default_selected_in_pos`, `whatsapp_default_selected_in_pos`, `invoice_footer`, `term_conditions`, `installment_days`, `minimum_point_to_redeem`, `loyalty_rate`, `is_loyalty_enable`, `website`, `tax_registration_number`, `company_name`, `company_email`, `busynotify_token`, `busynotify_company_id`, `pos_total_payable_type`, `letter_head_gap`, `letter_footer_gap`, `tax_setting`, `tax_string`, `smtp_type`, `smtp_details`, `sms_service_provider`, `sms_details`, `whatsapp_provider`, `whatsapp_invoice_enable_status`, `whatsapp_app_key`, `whatsapp_authkey`, `payment_api_setting`, `zatca_configuration`, `gst_api_key`) VALUES
(1, '{\"site_name\":\"Rashan Ki Dukan India Pvt Ltd\",\"site_footer\":\"Rashan Ki Dukan India Pvt Ltd\",\"site_title\":\"Rashan Ki Dukan India Pvt Ltd\",\"site_link\":\"https:\\/\\/rashankidukanindia.com\",\"site_logo\":\"site_logo_1789287077_6aa65aa55ebab.jpg\",\"site_favicon\":\"site_favicon_1789287077_6aa65aa55eca3.jpg\"}', 'Rashan Ki Dukan', 'admin@rashankidukan.com', '', NULL, 'INR', '₹', 'Asia/Kolkata', 'd/m/Y', NULL, 1, 'fifo', 0.00, NULL, 'Live', '2026-09-12 17:13:37', '2026-09-13 11:41:17', 'Rashan Ki Dukan', 'RKD', 'before', 2, 'Asia/Kolkata', 1, 'before_input', 'dropdown', 'off', 'cash', NULL, 0, NULL, NULL, 'No', NULL, NULL, 'No', 0, 0, 'off', 'off', ',', '.', 'show', 'No', 'No', 'No', NULL, 'No', 'No', 1, 'No', 'No', 'No', NULL, NULL, 3, 0.00, 0.00, 'No', NULL, NULL, NULL, NULL, NULL, NULL, 'grand_total', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL);
