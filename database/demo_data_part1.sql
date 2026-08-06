SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ============================================================
-- PART 1: Company, Outlet, Users, Roles
-- ============================================================

UPDATE `companies` SET
  `name` = 'Rashan Ki Dukan',
  `business_name` = 'Rashan Ki Dukan',
  `short_name` = 'RKD',
  `email` = 'admin@rashankidukan.com',
  `phone` = '+91-9876543210',
  `address` = 'Shop No. 5, Main Market, Laxmi Nagar, New Delhi - 110092',
  `currency` = 'INR',
  `currency_symbol` = 'Rs.',
  `currency_position` = 'Before Amount',
  `timezone` = 'Asia/Kolkata',
  `zone_name` = 'Asia/Kolkata',
  `date_format` = 'd/m/Y',
  `time_format` = 'h:i A',
  `fy_start_month` = 4,
  `accounting_method` = 'fifo',
  `precision` = 2,
  `thousands_separator` = ',',
  `decimals_separator` = '.',
  `default_cursor_position` = 'Barcode Box',
  `product_display` = 'Image View',
  `collect_tax` = 'Yes',
  `tax_is_gst` = 'Yes',
  `tax_title` = 'GST',
  `tax_registration_no` = '07AABCU9603R1ZX',
  `installment_days` = 30,
  `product_code_start_from` = '000001',
  `e_commerce_checker` = 'No',
  `allow_less_sale` = 'No',
  `direct_cart` = 'No',
  `grocery_experience` = 'Yes',
  `is_loyalty_enable` = 'No',
  `minimum_point_to_redeem` = 0,
  `loyalty_rate` = 0,
  `inv_logo_is_show` = 'Yes',
  `invoice_footer` = 'Thank you for shopping at Rashan Ki Dukan!',
  `del_status` = 'Live',
  `white_label_status` = 'Disable',
  `smtp_enable_status` = 'Disable',
  `sms_enable_status` = 'Disable',
  `whatsapp_invoice_enable_status` = 'Disable',
  `is_rounding_enable` = 'No',
  `purchase_price_show_hide` = 'Show'
WHERE `id` = 1;

-- Update outlet
UPDATE `outlets` SET
  `name` = 'Rashan Ki Dukan - Main Branch',
  `outlet_name` = 'Rashan Ki Dukan - Main Branch',
  `outlet_code` = 'RKD-001',
  `email` = 'main@rashankidukan.com',
  `phone` = '+91-9876543210',
  `address` = 'Shop No. 5, Main Market, Laxmi Nagar, New Delhi - 110092',
  `state_id` = 7,
  `is_active` = 1,
  `active_status` = 'Active',
  `user_id` = 1,
  `company_id` = 1,
  `del_status` = 'Live'
WHERE `id` = 1;

-- Update Super Admin user
UPDATE `users` SET
  `name` = 'Akash Admin',
  `email` = 'admin@rashankidukan.com',
  `phone` = '+91-9876543210',
  `salary` = 25000.00,
  `commission` = 0.00,
  `outlet_id` = '1',
  `will_login` = 'Yes',
  `del_status` = 'Live',
  `company_id` = 1
WHERE `id` = 1;

-- Add more roles
INSERT INTO `roles` (`name`, `guard_name`, `created_at`, `updated_at`) VALUES
('Manager', 'web', NOW(), NOW()),
('Cashier', 'web', NOW(), NOW()),
('Stock Manager', 'web', NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- Add staff users
INSERT INTO `users` (`name`, `email`, `password`, `role`, `phone`, `salary`, `commission`, `outlet_id`, `will_login`, `del_status`, `company_id`, `created_at`, `updated_at`) VALUES
('Rajesh Kumar', 'rajesh@rashankidukan.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2', '+91-9812345678', 18000.00, 0.00, '1', 'Yes', 'Live', 1, NOW(), NOW()),
('Priya Singh', 'priya@rashankidukan.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '3', '+91-9823456789', 12000.00, 1.50, '1', 'Yes', 'Live', 1, NOW(), NOW()),
('Suresh Yadav', 'suresh@rashankidukan.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '4', '+91-9834567890', 15000.00, 0.00, '1', 'Yes', 'Live', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- Assign Super Admin role to user 1
INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
(1, 'App\\Models\\User', 1)
ON DUPLICATE KEY UPDATE `role_id` = 1;

-- Add a printer
INSERT INTO `printers` (`name`, `type`, `connection_type`, `ip_address`, `port`, `user_id`, `company_id`, `del_status`, `created_at`, `updated_at`) VALUES
('Main Counter Printer', 'receipt', 'Network', '192.168.1.100', 9100, 1, 1, 'Live', NOW(), NOW());

-- Add a counter
INSERT INTO `counters` (`name`, `outlet_id`, `printer_id`, `user_id`, `company_id`, `del_status`, `created_at`, `updated_at`) VALUES
('Counter 1', 1, 1, 1, 1, 'Live', NOW(), NOW());

SELECT 'Part 1 Done - Company, Outlet, Users, Roles' AS status;
SET FOREIGN_KEY_CHECKS = 1;
