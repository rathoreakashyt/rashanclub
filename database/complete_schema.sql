-- Rashan Ki Dukan - Complete Schema
SET FOREIGN_KEY_CHECKS = 0;
SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';

CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` VARCHAR(50) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `salary` DECIMAL(15,2) DEFAULT 0.00,
    `commission` DECIMAL(15,2) DEFAULT 0.00,
    `outlet_id` VARCHAR(255) DEFAULT NULL,
    `will_login` VARCHAR(10) DEFAULT 'Yes',
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `photo` VARCHAR(255) DEFAULT NULL,
    `discount_permission_code` VARCHAR(50) DEFAULT NULL,
    `discount_amt` DECIMAL(15,2) DEFAULT 0.00,
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `two_factor_enabled` TINYINT(1) DEFAULT 0,
    `session_timeout` INT DEFAULT 0,
    `login_notifications` TINYINT(1) DEFAULT 0,
    `question` TEXT DEFAULT NULL,
    `answer` TEXT DEFAULT NULL,
    `remember_token` VARCHAR(100) DEFAULT NULL,
    `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `email` VARCHAR(255) NOT NULL PRIMARY KEY,
    `token` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tokenable_type` VARCHAR(255) NOT NULL,
    `tokenable_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `abilities` TEXT DEFAULT NULL,
    `last_used_at` TIMESTAMP NULL DEFAULT NULL,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
    `id` VARCHAR(255) NOT NULL PRIMARY KEY,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `payload` LONGTEXT NOT NULL,
    `last_activity` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `migrations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `migration` VARCHAR(255) NOT NULL,
    `batch` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache` (
    `key` VARCHAR(255) NOT NULL PRIMARY KEY,
    `value` MEDIUMTEXT NOT NULL,
    `expiration` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache_locks` (
    `key` VARCHAR(255) NOT NULL PRIMARY KEY,
    `owner` VARCHAR(255) NOT NULL,
    `expiration` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_jobs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uuid` VARCHAR(255) NOT NULL UNIQUE,
    `connection` TEXT NOT NULL,
    `queue` TEXT NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `exception` LONGTEXT NOT NULL,
    `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `guard_name` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roles` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `guard_name` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `model_has_roles` (
    `role_id` BIGINT UNSIGNED NOT NULL,
    `model_type` VARCHAR(255) NOT NULL,
    `model_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `model_id`, `model_type`),
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `model_has_permissions` (
    `permission_id` BIGINT UNSIGNED NOT NULL,
    `model_type` VARCHAR(255) NOT NULL,
    `model_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`permission_id`, `model_id`, `model_type`),
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_has_permissions` (
    `permission_id` BIGINT UNSIGNED NOT NULL,
    `role_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`permission_id`, `role_id`),
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pwa_settings` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `app_name` VARCHAR(255) DEFAULT NULL,
    `short_name` VARCHAR(255) DEFAULT NULL,
    `theme_color` VARCHAR(50) DEFAULT NULL,
    `background_color` VARCHAR(50) DEFAULT NULL,
    `logo` VARCHAR(255) DEFAULT NULL,
    `start_url` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `time_zones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `companies` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `white_label` TEXT DEFAULT NULL,
    `name` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `currency` VARCHAR(10) DEFAULT NULL,
    `currency_symbol` VARCHAR(10) DEFAULT NULL,
    `timezone` VARCHAR(50) DEFAULT NULL,
    `date_format` VARCHAR(20) DEFAULT NULL,
    `time_format` VARCHAR(20) DEFAULT NULL,
    `fy_start_month` INT DEFAULT 1,
    `accounting_method` VARCHAR(50) DEFAULT 'fifo',
    `default_profit_percent` DECIMAL(5,2) DEFAULT 0.00,
    `logo` VARCHAR(255) DEFAULT NULL,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `business_name` VARCHAR(255) DEFAULT NULL,
    `short_name` VARCHAR(50) DEFAULT NULL,
    `currency_position` VARCHAR(50) DEFAULT NULL,
    `precision` INT DEFAULT 2,
    `zone_name` VARCHAR(100) DEFAULT 'Asia/Kolkata',
    `default_customer` INT DEFAULT 1,
    `default_cursor_position` VARCHAR(50) DEFAULT NULL,
    `product_display` VARCHAR(50) DEFAULT NULL,
    `onscreen_keyboard_status` VARCHAR(10) DEFAULT 'off',
    `default_payment` VARCHAR(50) DEFAULT NULL,
    `payment_settings` TEXT DEFAULT NULL,
    `inv_logo_is_show` VARCHAR(10) DEFAULT 'Yes',
    `invoice_logo` VARCHAR(255) DEFAULT NULL,
    `invoice_configuration` TEXT DEFAULT NULL,
    `collect_tax` VARCHAR(10) DEFAULT 'No',
    `tax_title` VARCHAR(50) DEFAULT NULL,
    `tax_registration_no` VARCHAR(50) DEFAULT NULL,
    `tax_is_gst` VARCHAR(10) DEFAULT 'No',
    `sms_enable_status` VARCHAR(10) DEFAULT '0',
    `smtp_enable_status` VARCHAR(10) DEFAULT '0',
    `e_commerce_checker` VARCHAR(10) DEFAULT 'No',
    `white_label_status` VARCHAR(10) DEFAULT 'off',
    `thousands_separator` VARCHAR(5) DEFAULT ',',
    `decimals_separator` VARCHAR(5) DEFAULT '.',
    `purchase_price_show_hide` VARCHAR(10) DEFAULT 'show',
    `allow_less_sale` VARCHAR(5) DEFAULT 'No',
    `is_rounding_enable` VARCHAR(5) DEFAULT 'No',
    `direct_cart` VARCHAR(5) DEFAULT 'No',
    `register_content` TEXT DEFAULT NULL,
    `grocery_experience` VARCHAR(5) DEFAULT 'No',
    `generic_name_search_option` VARCHAR(5) DEFAULT 'No',
    `product_code_start_from` INT DEFAULT 1,
    `smtp_default_selected_in_pos` VARCHAR(5) DEFAULT 'No',
    `sms_default_selected_in_pos` VARCHAR(5) DEFAULT 'No',
    `whatsapp_default_selected_in_pos` VARCHAR(5) DEFAULT 'No',
    `invoice_footer` TEXT DEFAULT NULL,
    `term_conditions` TEXT DEFAULT NULL,
    `installment_days` INT DEFAULT 3,
    `minimum_point_to_redeem` DECIMAL(15,2) DEFAULT 0.00,
    `loyalty_rate` DECIMAL(5,2) DEFAULT 0.00,
    `is_loyalty_enable` VARCHAR(10) DEFAULT 'Disable',
    `website` VARCHAR(255) DEFAULT NULL,
    `tax_registration_number` VARCHAR(255) DEFAULT NULL,
    `company_name` VARCHAR(255) DEFAULT NULL,
    `company_email` VARCHAR(255) DEFAULT NULL,
    `busynotify_token` TEXT DEFAULT NULL,
    `busynotify_company_id` VARCHAR(50) DEFAULT NULL,
    `pos_total_payable_type` VARCHAR(50) DEFAULT 'grand_total',
    `letter_head_gap` INT DEFAULT 0,
    `letter_footer_gap` INT DEFAULT 0,
    `tax_setting` TEXT DEFAULT NULL,
    `tax_string` TEXT DEFAULT NULL,
    `smtp_type` VARCHAR(50) DEFAULT NULL,
    `smtp_details` TEXT DEFAULT NULL,
    `sms_service_provider` VARCHAR(100) DEFAULT NULL,
    `sms_details` TEXT DEFAULT NULL,
    `whatsapp_provider` VARCHAR(100) DEFAULT NULL,
    `whatsapp_invoice_enable_status` VARCHAR(10) DEFAULT '0',
    `whatsapp_app_key` VARCHAR(255) DEFAULT NULL,
    `whatsapp_authkey` VARCHAR(255) DEFAULT NULL,
    `payment_api_setting` TEXT DEFAULT NULL,
    `zatca_configuration` TEXT DEFAULT NULL,
    `gst_api_key` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `states` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `state_code` VARCHAR(10) DEFAULT NULL,
    `state_name` VARCHAR(255) DEFAULT NULL,
    `type` VARCHAR(50) DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `outlets` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `state_id` BIGINT UNSIGNED DEFAULT NULL,
    `invoice_scheme_id` BIGINT UNSIGNED DEFAULT NULL,
    `invoice_layout_id` BIGINT UNSIGNED DEFAULT NULL,
    `sale_invoice_layout_id` BIGINT UNSIGNED DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`state_id`) REFERENCES `states`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `printers` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `type` VARCHAR(50) DEFAULT NULL,
    `connection_type` VARCHAR(50) DEFAULT NULL,
    `ip_address` VARCHAR(255) DEFAULT NULL,
    `port` INT DEFAULT NULL,
    `path` VARCHAR(255) DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `counters` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `printer_id` BIGINT UNSIGNED DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`outlet_id`) REFERENCES `outlets`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`printer_id`) REFERENCES `printers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delivery_partners` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `commission_percent` DECIMAL(5,2) DEFAULT 0.00,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `denominations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `value` DECIMAL(15,2) DEFAULT 0.00,
    `type` VARCHAR(50) DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `multiple_currencies` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `symbol` VARCHAR(10) DEFAULT NULL,
    `exchange_rate` DECIMAL(15,4) DEFAULT 0.0000,
    `is_base` TINYINT(1) DEFAULT 0,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `taxs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tax_name` VARCHAR(255) DEFAULT NULL,
    `tax_rate` DECIMAL(5,2) DEFAULT 0.00,
    `parent_tax_id` BIGINT UNSIGNED DEFAULT NULL,
    `show_in_item_profile` TINYINT(1) DEFAULT 0,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`parent_tax_id`) REFERENCES `taxs`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_methods` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `type` VARCHAR(50) DEFAULT NULL,
    `configuration` JSON DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expense_categories` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expenses` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference_no` VARCHAR(255) DEFAULT NULL,
    `date` DATE DEFAULT NULL,
    `category_id` BIGINT UNSIGNED DEFAULT NULL,
    `payment_method_id` BIGINT UNSIGNED DEFAULT NULL,
    `amount` DECIMAL(15,3) DEFAULT 0.000,
    `note` TEXT DEFAULT NULL,
    `attachment` VARCHAR(255) DEFAULT NULL,
    `employee_id` BIGINT UNSIGNED DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`employee_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `income_categories` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `incomes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference_no` VARCHAR(255) DEFAULT NULL,
    `date` DATE DEFAULT NULL,
    `category_id` BIGINT UNSIGNED DEFAULT NULL,
    `payment_method_id` BIGINT UNSIGNED DEFAULT NULL,
    `amount` DECIMAL(15,3) DEFAULT 0.000,
    `note` TEXT DEFAULT NULL,
    `attachment` VARCHAR(255) DEFAULT NULL,
    `employee_id` BIGINT UNSIGNED DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`category_id`) REFERENCES `income_categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`employee_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `deposit_withdraws` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference_no` VARCHAR(255) DEFAULT NULL,
    `date` DATE DEFAULT NULL,
    `type` ENUM('Deposit','Withdraw') DEFAULT NULL,
    `payment_method_id` BIGINT UNSIGNED DEFAULT NULL,
    `amount` DECIMAL(15,3) DEFAULT 0.000,
    `note` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendances` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference_no` VARCHAR(255) DEFAULT NULL,
    `date` DATE DEFAULT NULL,
    `employee_id` BIGINT UNSIGNED DEFAULT NULL,
    `in_time` TIME DEFAULT NULL,
    `out_time` TIME DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`employee_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_advance_payments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference_no` VARCHAR(255) DEFAULT NULL,
    `date` DATE DEFAULT NULL,
    `amount` DECIMAL(15,2) DEFAULT 0.00,
    `note` TEXT DEFAULT NULL,
    `payment_method_id` BIGINT UNSIGNED DEFAULT NULL,
    `employee_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`employee_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `salaries` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference_no` VARCHAR(255) DEFAULT NULL,
    `year` INT DEFAULT NULL,
    `month` INT DEFAULT NULL,
    `generated_date` DATE DEFAULT NULL,
    `total_amount` DECIMAL(15,2) DEFAULT 0.00,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `salary_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `salary_id` BIGINT UNSIGNED DEFAULT NULL,
    `employee_id` BIGINT UNSIGNED DEFAULT NULL,
    `salary_amount` DECIMAL(15,2) DEFAULT 0.00,
    `overtime_rate` DECIMAL(15,2) DEFAULT 0.00,
    `overtime_hour` DECIMAL(5,2) DEFAULT 0.00,
    `additional_amount` DECIMAL(15,2) DEFAULT 0.00,
    `deduction_amount` DECIMAL(15,2) DEFAULT 0.00,
    `absent_day` INT DEFAULT 0,
    `absent_day_amount` DECIMAL(15,2) DEFAULT 0.00,
    `tips` DECIMAL(15,2) DEFAULT 0.00,
    `advance_taken` DECIMAL(15,2) DEFAULT 0.00,
    `net_salary` DECIMAL(15,2) DEFAULT 0.00,
    `note` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`salary_id`) REFERENCES `salaries`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`employee_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `salary_payments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `salary_id` BIGINT UNSIGNED DEFAULT NULL,
    `payment_method_id` BIGINT UNSIGNED DEFAULT NULL,
    `amount` DECIMAL(15,2) DEFAULT 0.00,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`salary_id`) REFERENCES `salaries`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `company_name` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `city` VARCHAR(255) DEFAULT NULL,
    `state` VARCHAR(255) DEFAULT NULL,
    `postal_code` VARCHAR(20) DEFAULT NULL,
    `country` VARCHAR(100) DEFAULT NULL,
    `gst_number` VARCHAR(50) DEFAULT NULL,
    `payment_method_id` BIGINT UNSIGNED DEFAULT NULL,
    `opening_balance` DECIMAL(15,3) DEFAULT 0.000,
    `opening_balance_type` VARCHAR(20) DEFAULT NULL,
    `credit_limit` DECIMAL(15,3) DEFAULT 0.000,
    `description` TEXT DEFAULT NULL,
    `photo` VARCHAR(255) DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchases` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference_no` VARCHAR(255) DEFAULT NULL,
    `invoice_no` VARCHAR(255) DEFAULT NULL,
    `supplier_id` BIGINT UNSIGNED DEFAULT NULL,
    `date` DATE DEFAULT NULL,
    `other` DECIMAL(15,3) DEFAULT 0.000,
    `grand_total` DECIMAL(15,3) DEFAULT 0.000,
    `paid` DECIMAL(15,3) DEFAULT 0.000,
    `due_amount` DECIMAL(15,3) DEFAULT 0.000,
    `note` TEXT DEFAULT NULL,
    `discount` VARCHAR(50) DEFAULT NULL,
    `attachment` VARCHAR(255) DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_details` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `purchase_id` BIGINT UNSIGNED DEFAULT NULL,
    `item_id` BIGINT UNSIGNED DEFAULT NULL,
    `item_type` VARCHAR(50) DEFAULT NULL,
    `expiry_imei_serial` TEXT DEFAULT NULL,
    `unit_price` DECIMAL(15,3) DEFAULT 0.000,
    `quantity_amount` DECIMAL(15,3) DEFAULT 0.000,
    `total` DECIMAL(15,3) DEFAULT 0.000,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_payments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `purchase_id` BIGINT UNSIGNED DEFAULT NULL,
    `payment_id` BIGINT UNSIGNED DEFAULT NULL,
    `date` DATE DEFAULT NULL,
    `amount` DECIMAL(15,2) DEFAULT 0.00,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`payment_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_returns` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference_no` VARCHAR(255) DEFAULT NULL,
    `pur_ref_no` VARCHAR(255) DEFAULT NULL,
    `supplier_id` BIGINT UNSIGNED DEFAULT NULL,
    `date` DATE DEFAULT NULL,
    `purchase_date` DATE DEFAULT NULL,
    `return_status` VARCHAR(50) DEFAULT NULL,
    `total_return_amount` DECIMAL(15,3) DEFAULT 0.000,
    `payment_method_id` BIGINT UNSIGNED DEFAULT NULL,
    `payment_method_type` VARCHAR(50) DEFAULT NULL,
    `account_type` VARCHAR(50) DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_return_details` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `pur_return_id` BIGINT UNSIGNED DEFAULT NULL,
    `item_id` BIGINT UNSIGNED DEFAULT NULL,
    `item_type` VARCHAR(50) DEFAULT NULL,
    `expiry_imei_serial` TEXT DEFAULT NULL,
    `expiry_imei_serial_in` TEXT DEFAULT NULL,
    `return_note` TEXT DEFAULT NULL,
    `return_quantity_amount` DECIMAL(15,3) DEFAULT 0.000,
    `unit_price` DECIMAL(15,3) DEFAULT 0.000,
    `total` DECIMAL(15,3) DEFAULT 0.000,
    `return_status` VARCHAR(50) DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`pur_return_id`) REFERENCES `purchase_returns`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `supplier_payments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `supplier_id` BIGINT UNSIGNED DEFAULT NULL,
    `payment_method_id` BIGINT UNSIGNED DEFAULT NULL,
    `amount` DECIMAL(15,3) DEFAULT 0.000,
    `date` DATE DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `item_categories` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `sort_id` INT DEFAULT 0,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `brands` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `units` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `unit_name` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `variations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `variation_name` VARCHAR(255) DEFAULT NULL,
    `variation_value` JSON DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `racks` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `code` VARCHAR(255) DEFAULT NULL,
    `alternative_name` VARCHAR(255) DEFAULT NULL,
    `generic_name` VARCHAR(255) DEFAULT NULL,
    `type` VARCHAR(50) DEFAULT NULL,
    `expiry_date_maintain` TINYINT(1) DEFAULT 0,
    `category_id` BIGINT UNSIGNED DEFAULT NULL,
    `rack_id` BIGINT UNSIGNED DEFAULT NULL,
    `brand_id` BIGINT UNSIGNED DEFAULT NULL,
    `supplier_id` BIGINT UNSIGNED DEFAULT NULL,
    `alert_quantity` DECIMAL(15,3) DEFAULT 0.000,
    `unit_type` VARCHAR(50) DEFAULT NULL,
    `purchase_unit_id` BIGINT UNSIGNED DEFAULT NULL,
    `sale_unit_id` BIGINT UNSIGNED DEFAULT NULL,
    `conversion_rate` DECIMAL(15,3) DEFAULT 1.000,
    `purchase_price` DECIMAL(15,3) DEFAULT 0.000,
    `last_three_purchase_avg` DECIMAL(15,3) DEFAULT 0.000,
    `last_purchase_price` DECIMAL(15,3) DEFAULT 0.000,
    `mrp_price` DECIMAL(15,3) DEFAULT 0.000,
    `sale_price` DECIMAL(15,3) DEFAULT 0.000,
    `profit_margin` DECIMAL(15,3) DEFAULT 0.000,
    `whole_sale_price` DECIMAL(15,3) DEFAULT 0.000,
    `description` TEXT DEFAULT NULL,
    `warranty` VARCHAR(50) DEFAULT NULL,
    `warranty_date` DATE DEFAULT NULL,
    `guarantee` VARCHAR(50) DEFAULT NULL,
    `guarantee_date` DATE DEFAULT NULL,
    `photo` VARCHAR(255) DEFAULT NULL,
    `tax_information` JSON DEFAULT NULL,
    `tax_string` VARCHAR(255) DEFAULT NULL,
    `tax_type` VARCHAR(50) DEFAULT NULL,
    `applicable_tax_id` BIGINT UNSIGNED DEFAULT NULL,
    `hsn_code` VARCHAR(50) DEFAULT NULL,
    `variation_details` JSON DEFAULT NULL,
    `enable_disable_status` TINYINT(1) DEFAULT 1,
    `parent_id` BIGINT UNSIGNED DEFAULT NULL,
    `loyalty_point` INT DEFAULT 0,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`category_id`) REFERENCES `item_categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`brand_id`) REFERENCES `brands`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`rack_id`) REFERENCES `racks`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`purchase_unit_id`) REFERENCES `units`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`sale_unit_id`) REFERENCES `units`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`parent_id`) REFERENCES `items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `combo_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `combo_item_id` BIGINT UNSIGNED DEFAULT NULL,
    `item_id` BIGINT UNSIGNED DEFAULT NULL,
    `quantity` DECIMAL(15,3) DEFAULT 0.000,
    `amount` DECIMAL(15,3) DEFAULT 0.000,
    `total` DECIMAL(15,3) DEFAULT 0.000,
    `show_in_invoice` TINYINT(1) DEFAULT 1,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`combo_item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `set_opening_stocks` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `item_id` BIGINT UNSIGNED DEFAULT NULL,
    `item_type` VARCHAR(50) DEFAULT NULL,
    `item_description` TEXT DEFAULT NULL,
    `stock_quantity` DECIMAL(15,3) DEFAULT 0.000,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`outlet_id`) REFERENCES `outlets`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `damages` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference_no` VARCHAR(255) DEFAULT NULL,
    `date` DATE DEFAULT NULL,
    `total_loss` DECIMAL(15,3) DEFAULT 0.000,
    `note` TEXT DEFAULT NULL,
    `employee_id` BIGINT UNSIGNED DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`employee_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `damage_details` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `damage_id` BIGINT UNSIGNED DEFAULT NULL,
    `item_id` BIGINT UNSIGNED DEFAULT NULL,
    `date` DATE DEFAULT NULL,
    `damage_quantity` DECIMAL(15,3) DEFAULT 0.000,
    `last_purchase_price` DECIMAL(15,3) DEFAULT 0.000,
    `loss_amount` DECIMAL(15,3) DEFAULT 0.000,
    `total_amount` DECIMAL(15,3) DEFAULT 0.000,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`damage_id`) REFERENCES `damages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transfers` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference_no` VARCHAR(255) DEFAULT NULL,
    `date` DATE DEFAULT NULL,
    `from_outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `to_outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`from_outlet_id`) REFERENCES `outlets`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`to_outlet_id`) REFERENCES `outlets`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transfer_details` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `transfer_id` BIGINT UNSIGNED DEFAULT NULL,
    `item_id` BIGINT UNSIGNED DEFAULT NULL,
    `quantity` DECIMAL(15,3) DEFAULT 0.000,
    `unit_price` DECIMAL(15,3) DEFAULT 0.000,
    `total` DECIMAL(15,3) DEFAULT 0.000,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`transfer_id`) REFERENCES `transfers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fixed_asset_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `code` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `quantity` DECIMAL(15,3) DEFAULT 0.000,
    `unit_price` DECIMAL(15,3) DEFAULT 0.000,
    `total` DECIMAL(15,3) DEFAULT 0.000,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fixed_asset_stock_ins` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference_no` VARCHAR(255) DEFAULT NULL,
    `date` DATE DEFAULT NULL,
    `grand_total` DECIMAL(15,3) DEFAULT 0.000,
    `note` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fixed_asset_stock_in_details` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `asset_stock_in_id` BIGINT UNSIGNED DEFAULT NULL,
    `item_id` BIGINT UNSIGNED DEFAULT NULL,
    `unit_price` DECIMAL(15,3) DEFAULT 0.000,
    `total` DECIMAL(15,3) DEFAULT 0.000,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`asset_stock_in_id`) REFERENCES `fixed_asset_stock_ins`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `fixed_asset_items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fixed_asset_stock_outs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference_no` VARCHAR(255) DEFAULT NULL,
    `date` DATE DEFAULT NULL,
    `grand_total` DECIMAL(15,3) DEFAULT 0.000,
    `note` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fixed_asset_stock_out_details` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `asset_stock_out_id` BIGINT UNSIGNED DEFAULT NULL,
    `item_id` BIGINT UNSIGNED DEFAULT NULL,
    `unit_price` DECIMAL(15,3) DEFAULT 0.000,
    `total` DECIMAL(15,3) DEFAULT 0.000,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`asset_stock_out_id`) REFERENCES `fixed_asset_stock_outs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `fixed_asset_items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customers` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `city` VARCHAR(255) DEFAULT NULL,
    `state_id` BIGINT UNSIGNED DEFAULT NULL,
    `postal_code` VARCHAR(20) DEFAULT NULL,
    `country` VARCHAR(100) DEFAULT NULL,
    `gst_number` VARCHAR(50) DEFAULT NULL,
    `opening_balance` DECIMAL(15,3) DEFAULT 0.000,
    `opening_balance_type` VARCHAR(20) DEFAULT NULL,
    `credit_limit` DECIMAL(15,3) DEFAULT 0.000,
    `description` TEXT DEFAULT NULL,
    `photo` VARCHAR(255) DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`state_id`) REFERENCES `states`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sales` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_no` VARCHAR(255) DEFAULT NULL,
    `sale_date` DATE DEFAULT NULL,
    `date_time` DATETIME DEFAULT NULL,
    `order_time` DATETIME DEFAULT NULL,
    `due_date` DATE DEFAULT NULL,
    `due_date_time` DATETIME DEFAULT NULL,
    `order_date_time` DATETIME DEFAULT NULL,
    `close_time` DATETIME DEFAULT NULL,
    `customer_id` BIGINT UNSIGNED DEFAULT NULL,
    `employee_id` BIGINT UNSIGNED DEFAULT NULL,
    `sub_total` DECIMAL(15,3) DEFAULT 0.000,
    `given_amount` DECIMAL(15,3) DEFAULT 0.000,
    `paid_amount` DECIMAL(15,3) DEFAULT 0.000,
    `change_amount` DECIMAL(15,3) DEFAULT 0.000,
    `previous_due` DECIMAL(15,3) DEFAULT 0.000,
    `due_amount` DECIMAL(15,3) DEFAULT 0.000,
    `disc` DECIMAL(15,3) DEFAULT 0.000,
    `disc_actual` DECIMAL(15,3) DEFAULT 0.000,
    `vat` DECIMAL(15,3) DEFAULT 0.000,
    `rounding` DECIMAL(15,3) DEFAULT 0.000,
    `total_payable` DECIMAL(15,3) DEFAULT 0.000,
    `total_item_discount_amount` DECIMAL(15,3) DEFAULT 0.000,
    `sub_total_with_discount` DECIMAL(15,3) DEFAULT 0.000,
    `sub_total_discount_amount` DECIMAL(15,3) DEFAULT 0.000,
    `total_discount_amount` DECIMAL(15,3) DEFAULT 0.000,
    `delivery_charge` DECIMAL(15,3) DEFAULT 0.000,
    `sub_total_discount_value` DECIMAL(15,3) DEFAULT 0.000,
    `grand_total` DECIMAL(15,3) DEFAULT 0.000,
    `sale_vat_objects` JSON DEFAULT NULL,
    `delivery_partner_id` BIGINT UNSIGNED DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`employee_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`delivery_partner_id`) REFERENCES `delivery_partners`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sale_details` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sales_id` BIGINT UNSIGNED DEFAULT NULL,
    `item_id` BIGINT UNSIGNED DEFAULT NULL,
    `qty` DECIMAL(15,3) DEFAULT 0.000,
    `menu_price_without_discount` DECIMAL(15,3) DEFAULT 0.000,
    `menu_price_with_discount` DECIMAL(15,3) DEFAULT 0.000,
    `menu_unit_price` DECIMAL(15,3) DEFAULT 0.000,
    `purchase_price` DECIMAL(15,3) DEFAULT 0.000,
    `menu_vat_percentage` DECIMAL(5,2) DEFAULT 0.00,
    `item_tax_amount` DECIMAL(15,3) DEFAULT 0.000,
    `menu_discount_value` DECIMAL(15,3) DEFAULT 0.000,
    `discount_amount` DECIMAL(15,3) DEFAULT 0.000,
    `loyalty_point_earn` DECIMAL(15,3) DEFAULT 0.000,
    `is_promo_item` VARCHAR(10) DEFAULT 'No',
    `promo_parent_id` BIGINT UNSIGNED DEFAULT NULL,
    `item_seller_id` BIGINT UNSIGNED DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`sales_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`promo_parent_id`) REFERENCES `sale_details`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`item_seller_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sale_payments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sale_id` BIGINT UNSIGNED DEFAULT NULL,
    `payment_id` BIGINT UNSIGNED DEFAULT NULL,
    `date` DATE DEFAULT NULL,
    `amount` DECIMAL(15,3) DEFAULT 0.000,
    `multi_currency` VARCHAR(10) DEFAULT 'No',
    `multi_currency_rate` DECIMAL(15,4) DEFAULT 0.0000,
    `usage_point` DECIMAL(15,3) DEFAULT 0.000,
    `note` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`payment_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `combo_sales` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sale_id` BIGINT UNSIGNED DEFAULT NULL,
    `combo_sale_item_id` BIGINT UNSIGNED DEFAULT NULL,
    `combo_item_id` BIGINT UNSIGNED DEFAULT NULL,
    `combo_item_qty` DECIMAL(15,3) DEFAULT 0.000,
    `combo_item_price` DECIMAL(15,3) DEFAULT 0.000,
    `combo_item_seller_id` BIGINT UNSIGNED DEFAULT NULL,
    `show_in_invoice` VARCHAR(10) DEFAULT 'Yes',
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`combo_sale_item_id`) REFERENCES `sale_details`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`combo_item_id`) REFERENCES `items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sale_returns` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference_no` VARCHAR(255) DEFAULT NULL,
    `sale_id` BIGINT UNSIGNED DEFAULT NULL,
    `customer_id` BIGINT UNSIGNED DEFAULT NULL,
    `date` DATE DEFAULT NULL,
    `total_return_amount` DECIMAL(15,3) DEFAULT 0.000,
    `paid` DECIMAL(15,3) DEFAULT 0.000,
    `due` DECIMAL(15,3) DEFAULT 0.000,
    `payment_method_id` BIGINT UNSIGNED DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sale_return_details` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sale_return_id` BIGINT UNSIGNED DEFAULT NULL,
    `sale_id` BIGINT UNSIGNED DEFAULT NULL,
    `item_id` BIGINT UNSIGNED DEFAULT NULL,
    `sale_quantity_amount` DECIMAL(15,3) DEFAULT 0.000,
    `return_quantity_amount` DECIMAL(15,3) DEFAULT 0.000,
    `unit_price_in_sale` DECIMAL(15,3) DEFAULT 0.000,
    `unit_price_in_return` DECIMAL(15,3) DEFAULT 0.000,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`sale_return_id`) REFERENCES `sale_returns`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_receives` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `customer_id` BIGINT UNSIGNED DEFAULT NULL,
    `payment_method_id` BIGINT UNSIGNED DEFAULT NULL,
    `amount` DECIMAL(15,3) DEFAULT 0.000,
    `date` DATE DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `holds` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_no` VARCHAR(255) DEFAULT NULL,
    `sale_date` DATE DEFAULT NULL,
    `date_time` DATETIME DEFAULT NULL,
    `due_payment_date` DATE DEFAULT NULL,
    `customer_id` BIGINT UNSIGNED DEFAULT NULL,
    `employee_id` BIGINT UNSIGNED DEFAULT NULL,
    `sub_total` DECIMAL(15,3) DEFAULT 0.000,
    `paid_amount` DECIMAL(15,3) DEFAULT 0.000,
    `due_amount` DECIMAL(15,3) DEFAULT 0.000,
    `disc` DECIMAL(15,3) DEFAULT 0.000,
    `disc_actual` DECIMAL(15,3) DEFAULT 0.000,
    `vat` DECIMAL(15,3) DEFAULT 0.000,
    `total_payable` DECIMAL(15,3) DEFAULT 0.000,
    `total_item_discount_amount` DECIMAL(15,3) DEFAULT 0.000,
    `sub_total_with_discount` DECIMAL(15,3) DEFAULT 0.000,
    `sub_total_discount_amount` DECIMAL(15,3) DEFAULT 0.000,
    `total_discount_amount` DECIMAL(15,3) DEFAULT 0.000,
    `delivery_charge` DECIMAL(15,3) DEFAULT 0.000,
    `sub_total_discount_value` DECIMAL(15,3) DEFAULT 0.000,
    `delivery_partner_id` BIGINT UNSIGNED DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`employee_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`delivery_partner_id`) REFERENCES `delivery_partners`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hold_details` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `holds_id` BIGINT UNSIGNED DEFAULT NULL,
    `item_id` BIGINT UNSIGNED DEFAULT NULL,
    `qty` DECIMAL(15,3) DEFAULT 0.000,
    `menu_price_without_discount` DECIMAL(15,3) DEFAULT 0.000,
    `menu_price_with_discount` DECIMAL(15,3) DEFAULT 0.000,
    `menu_unit_price` DECIMAL(15,3) DEFAULT 0.000,
    `menu_vat_percentage` DECIMAL(5,2) DEFAULT 0.00,
    `item_tax_amount` DECIMAL(15,3) DEFAULT 0.000,
    `menu_discount_value` DECIMAL(15,3) DEFAULT 0.000,
    `discount_amount` DECIMAL(15,3) DEFAULT 0.000,
    `is_promo_item` VARCHAR(10) DEFAULT 'No',
    `promo_parent_id` BIGINT UNSIGNED DEFAULT NULL,
    `item_seller_id` BIGINT UNSIGNED DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`holds_id`) REFERENCES `holds`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`promo_parent_id`) REFERENCES `hold_details`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`item_seller_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hold_combo_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sale_id` BIGINT UNSIGNED DEFAULT NULL,
    `combo_sale_item_id` BIGINT UNSIGNED DEFAULT NULL,
    `combo_item_id` BIGINT UNSIGNED DEFAULT NULL,
    `combo_item_qty` DECIMAL(15,3) DEFAULT 0.000,
    `combo_item_price` DECIMAL(15,3) DEFAULT 0.000,
    `combo_item_seller_id` BIGINT UNSIGNED DEFAULT NULL,
    `show_in_invoice` VARCHAR(10) DEFAULT 'Yes',
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`sale_id`) REFERENCES `holds`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`combo_sale_item_id`) REFERENCES `hold_details`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`combo_item_id`) REFERENCES `items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bookings` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `customer_id` BIGINT UNSIGNED DEFAULT NULL,
    `service_seller_id` BIGINT UNSIGNED DEFAULT NULL,
    `start_date` DATETIME DEFAULT NULL,
    `end_date` DATETIME DEFAULT NULL,
    `status` VARCHAR(50) DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`service_seller_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `promotions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `type` VARCHAR(50) DEFAULT NULL,
    `discount_type` VARCHAR(50) DEFAULT NULL,
    `discount_value` DECIMAL(15,3) DEFAULT 0.000,
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `status` VARCHAR(50) DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quotations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference_no` VARCHAR(255) DEFAULT NULL,
    `customer_id` BIGINT UNSIGNED DEFAULT NULL,
    `date` DATE DEFAULT NULL,
    `grand_total` DECIMAL(15,3) DEFAULT 0.000,
    `note` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quotation_details` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `quotation_id` BIGINT UNSIGNED DEFAULT NULL,
    `item_id` BIGINT UNSIGNED DEFAULT NULL,
    `unit_price` DECIMAL(15,3) DEFAULT 0.000,
    `total` DECIMAL(15,3) DEFAULT 0.000,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`quotation_id`) REFERENCES `quotations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `installment_sales` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference_no` VARCHAR(255) DEFAULT NULL,
    `customer_id` BIGINT UNSIGNED DEFAULT NULL,
    `item_id` BIGINT UNSIGNED DEFAULT NULL,
    `date` DATE DEFAULT NULL,
    `price` DECIMAL(15,3) DEFAULT 0.000,
    `discount_amount` DECIMAL(15,3) DEFAULT 0.000,
    `percentage_of_interest` DECIMAL(5,2) DEFAULT 0.00,
    `interest_amount` DECIMAL(15,3) DEFAULT 0.000,
    `shipping_other` DECIMAL(15,3) DEFAULT 0.000,
    `total` DECIMAL(15,3) DEFAULT 0.000,
    `down_payment` DECIMAL(15,3) DEFAULT 0.000,
    `remaining` DECIMAL(15,3) DEFAULT 0.000,
    `paid_amount` DECIMAL(15,3) DEFAULT 0.000,
    `due_amount` DECIMAL(15,3) DEFAULT 0.000,
    `status` VARCHAR(50) DEFAULT 'Active',
    `installment_count` INT DEFAULT 0,
    `note` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `installment_sale_details` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `installment_sale_id` BIGINT UNSIGNED DEFAULT NULL,
    `payment_date` DATE DEFAULT NULL,
    `paid_date` DATE DEFAULT NULL,
    `amount` DECIMAL(15,3) DEFAULT 0.000,
    `paid_amount` DECIMAL(15,3) DEFAULT 0.000,
    `remaining_amount` DECIMAL(15,3) DEFAULT 0.000,
    `paid_status` VARCHAR(50) DEFAULT 'Unpaid',
    `payment_method_id` BIGINT UNSIGNED DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`installment_sale_id`) REFERENCES `installment_sales`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `installment_sale_payments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `installment_sale_id` BIGINT UNSIGNED DEFAULT NULL,
    `installment_sale_detail_id` BIGINT UNSIGNED DEFAULT NULL,
    `payment_date` DATE DEFAULT NULL,
    `amount` DECIMAL(15,3) DEFAULT 0.000,
    `payment_type` VARCHAR(50) DEFAULT NULL,
    `payment_method_id` BIGINT UNSIGNED DEFAULT NULL,
    `check_issue_date` DATE DEFAULT NULL,
    `check_expiry_date` DATE DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`installment_sale_id`) REFERENCES `installment_sales`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`installment_sale_detail_id`) REFERENCES `installment_sale_details`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice_hash_chain` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `previous_hash` VARCHAR(255) DEFAULT NULL,
    `current_hash` VARCHAR(255) DEFAULT NULL,
    `zatca_invoice_id` BIGINT UNSIGNED DEFAULT NULL,
    `chain_index` INT DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `servicings` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference_no` VARCHAR(255) DEFAULT NULL,
    `customer_id` BIGINT UNSIGNED DEFAULT NULL,
    `employee_id` BIGINT UNSIGNED DEFAULT NULL,
    `date` DATE DEFAULT NULL,
    `receiving_date` DATE DEFAULT NULL,
    `delivery_date` DATE DEFAULT NULL,
    `servicing_charge` DECIMAL(15,2) DEFAULT 0.00,
    `paid_amount` DECIMAL(15,2) DEFAULT 0.00,
    `due_amount` DECIMAL(15,2) DEFAULT 0.00,
    `payment_method_id` BIGINT UNSIGNED DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`employee_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `warranties` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference_no` VARCHAR(255) DEFAULT NULL,
    `customer_id` BIGINT UNSIGNED DEFAULT NULL,
    `technician_id` BIGINT UNSIGNED DEFAULT NULL,
    `receiving_date` DATE DEFAULT NULL,
    `delivery_date` DATE DEFAULT NULL,
    `current_status` VARCHAR(50) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`technician_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `zatca_invoices` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sale_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `invoice_type` VARCHAR(50) DEFAULT NULL,
    `uuid` VARCHAR(255) DEFAULT NULL,
    `invoice_hash` VARCHAR(255) DEFAULT NULL,
    `previous_invoice_hash` VARCHAR(255) DEFAULT NULL,
    `qr_code` TEXT DEFAULT NULL,
    `zatca_status` VARCHAR(50) DEFAULT 'pending',
    `zatca_error` TEXT DEFAULT NULL,
    `cleared_at` DATETIME DEFAULT NULL,
    `reported_at` DATETIME DEFAULT NULL,
    `failed_at` DATETIME DEFAULT NULL,
    `retry_count` INT DEFAULT 0,
    `last_retry_at` DATETIME DEFAULT NULL,
    `ubl_xml` LONGTEXT DEFAULT NULL,
    `signed_xml` LONGTEXT DEFAULT NULL,
    `cryptographic_stamp` TEXT DEFAULT NULL,
    `packaging_authorized_serial_number` VARCHAR(255) DEFAULT NULL,
    `is_offline` TINYINT(1) DEFAULT 0,
    `queued_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `zatca_requests` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `zatca_invoice_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT NULL,
    `request_type` VARCHAR(50) DEFAULT NULL,
    `request_payload` LONGTEXT DEFAULT NULL,
    `request_url` VARCHAR(255) DEFAULT NULL,
    `request_method` VARCHAR(10) DEFAULT NULL,
    `request_headers` JSON DEFAULT NULL,
    `response_status_code` INT DEFAULT NULL,
    `response_body` LONGTEXT DEFAULT NULL,
    `response_headers` JSON DEFAULT NULL,
    `status` VARCHAR(50) DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `error_code` VARCHAR(50) DEFAULT NULL,
    `requested_at` DATETIME DEFAULT NULL,
    `responded_at` DATETIME DEFAULT NULL,
    `response_time_ms` INT DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`zatca_invoice_id`) REFERENCES `zatca_invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `registers` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `opening_balance` DECIMAL(15,2) DEFAULT 0.00,
    `closing_balance` DECIMAL(15,2) DEFAULT 0.00,
    `sale_paid_amount` DECIMAL(15,2) DEFAULT 0.00,
    `refund_amount` DECIMAL(15,2) DEFAULT 0.00,
    `customer_due_receive` DECIMAL(15,2) DEFAULT 0.00,
    `total_purchase` DECIMAL(15,2) DEFAULT 0.00,
    `total_downpayment` DECIMAL(15,2) DEFAULT 0.00,
    `total_installmentcollection` DECIMAL(15,2) DEFAULT 0.00,
    `total_servicing` DECIMAL(15,2) DEFAULT 0.00,
    `total_purchase_return` DECIMAL(15,2) DEFAULT 0.00,
    `total_due_payment` DECIMAL(15,2) DEFAULT 0.00,
    `total_expense` DECIMAL(15,2) DEFAULT 0.00,
    `register_status` TINYINT(1) DEFAULT 1,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `counter_id` BIGINT UNSIGNED DEFAULT NULL,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`counter_id`) REFERENCES `counters`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
