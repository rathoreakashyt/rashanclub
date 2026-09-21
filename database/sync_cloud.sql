-- ============================================================
-- 1. MISSING TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS `gst_cache` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `gstin` varchar(15) NOT NULL,
  `legal_name` varchar(255) DEFAULT NULL,
  `trade_name` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `local_id_map` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) NOT NULL,
  `local_id` varchar(100) NOT NULL,
  `local_code` varchar(100) DEFAULT NULL,
  `server_id` bigint(20) UNSIGNED NOT NULL,
  `device_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. COMPANIES — missing columns
-- ============================================================
ALTER TABLE `companies`
  ADD COLUMN `allow_less_sale` varchar(5) DEFAULT 'No',
  ADD COLUMN `company_email` varchar(255) DEFAULT NULL,
  ADD COLUMN `company_name` varchar(255) DEFAULT NULL,
  ADD COLUMN `decimals_separator` varchar(5) DEFAULT '.',
  ADD COLUMN `direct_cart` varchar(5) DEFAULT 'No',
  ADD COLUMN `generic_name_search_option` varchar(5) DEFAULT 'No',
  ADD COLUMN `grocery_experience` varchar(5) DEFAULT 'No',
  ADD COLUMN `gst_api_key` text DEFAULT NULL,
  ADD COLUMN `invoice_configuration` text DEFAULT NULL,
  ADD COLUMN `invoice_footer` text DEFAULT NULL,
  ADD COLUMN `is_loyalty_enable` varchar(5) DEFAULT 'No',
  ADD COLUMN `is_rounding_enable` varchar(5) DEFAULT 'No',
  ADD COLUMN `letter_footer_gap` int(11) DEFAULT 0,
  ADD COLUMN `letter_head_gap` int(11) DEFAULT 0,
  ADD COLUMN `onscreen_keyboard_status` varchar(10) DEFAULT 'off',
  ADD COLUMN `payment_api_setting` text DEFAULT NULL,
  ADD COLUMN `pos_total_payable_type` varchar(50) DEFAULT 'grand_total',
  ADD COLUMN `purchase_price_show_hide` varchar(10) DEFAULT 'show',
  ADD COLUMN `register_content` text DEFAULT NULL,
  ADD COLUMN `sms_default_selected_in_pos` varchar(5) DEFAULT 'No',
  ADD COLUMN `sms_details` text DEFAULT NULL,
  ADD COLUMN `sms_enable_status` tinyint(1) DEFAULT 0,
  ADD COLUMN `sms_service_provider` varchar(100) DEFAULT NULL,
  ADD COLUMN `smtp_default_selected_in_pos` varchar(5) DEFAULT 'No',
  ADD COLUMN `smtp_enable_status` tinyint(1) DEFAULT 0,
  ADD COLUMN `smtp_type` varchar(50) DEFAULT NULL,
  ADD COLUMN `tax_registration_number` varchar(255) DEFAULT NULL,
  ADD COLUMN `tax_setting` varchar(50) DEFAULT NULL,
  ADD COLUMN `tax_string` text DEFAULT NULL,
  ADD COLUMN `term_conditions` text DEFAULT NULL,
  ADD COLUMN `thousands_separator` varchar(5) DEFAULT ',',
  ADD COLUMN `website` varchar(255) DEFAULT NULL,
  ADD COLUMN `whatsapp_app_key` varchar(255) DEFAULT NULL,
  ADD COLUMN `whatsapp_authkey` varchar(255) DEFAULT NULL,
  ADD COLUMN `whatsapp_default_selected_in_pos` varchar(5) DEFAULT 'No',
  ADD COLUMN `whatsapp_invoice_enable_status` tinyint(1) DEFAULT 0,
  ADD COLUMN `whatsapp_provider` varchar(100) DEFAULT NULL,
  ADD COLUMN `white_label_status` varchar(10) DEFAULT 'off',
  ADD COLUMN `zatca_configuration` text DEFAULT NULL;

-- ============================================================
-- 3. SALES — missing columns
-- ============================================================
ALTER TABLE `sales`
  ADD COLUMN `booking_id` bigint(20) UNSIGNED DEFAULT NULL,
  ADD COLUMN `counter_id` bigint(20) UNSIGNED DEFAULT NULL,
  ADD COLUMN `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  ADD COLUMN `delivery_status` varchar(50) DEFAULT NULL,
  ADD COLUMN `discount` decimal(15,2) DEFAULT 0.00,
  ADD COLUMN `discount_type` varchar(20) DEFAULT NULL,
  ADD COLUMN `due` decimal(15,2) DEFAULT 0.00,
  ADD COLUMN `invoice_id` varchar(255) DEFAULT NULL,
  ADD COLUMN `loyalty_point` decimal(15,2) DEFAULT 0.00,
  ADD COLUMN `loyalty_point_used` decimal(15,3) DEFAULT 0.000,
  ADD COLUMN `paid` decimal(15,2) DEFAULT 0.00,
  ADD COLUMN `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  ADD COLUMN `payment_status` varchar(20) DEFAULT 'Paid',
  ADD COLUMN `reference` varchar(255) DEFAULT NULL,
  ADD COLUMN `round_off` decimal(15,2) DEFAULT 0.00,
  ADD COLUMN `sale_status` varchar(20) DEFAULT 'Complete',
  ADD COLUMN `shipping` decimal(15,2) DEFAULT 0.00,
  ADD COLUMN `sub_total_discount_type` varchar(20) DEFAULT 'flat',
  ADD COLUMN `subtotal` decimal(15,2) DEFAULT 0.00,
  ADD COLUMN `table_no` varchar(50) DEFAULT NULL,
  ADD COLUMN `tax` decimal(15,2) DEFAULT 0.00,
  ADD COLUMN `total` decimal(15,2) DEFAULT 0.00,
  ADD COLUMN `type` varchar(20) DEFAULT 'sale';

-- ============================================================
-- 4. CUSTOMERS — missing columns
-- ============================================================
ALTER TABLE `customers`
  ADD COLUMN `anniversary` date DEFAULT NULL,
  ADD COLUMN `business_type` varchar(50) DEFAULT NULL,
  ADD COLUMN `current_balance` decimal(15,2) DEFAULT 0.00,
  ADD COLUMN `customer_type` varchar(50) DEFAULT 'Regular',
  ADD COLUMN `date_of_anniversary` date DEFAULT NULL,
  ADD COLUMN `date_of_birth` date DEFAULT NULL,
  ADD COLUMN `discount` decimal(10,2) DEFAULT 0.00,
  ADD COLUMN `dob` date DEFAULT NULL,
  ADD COLUMN `dp_1` varchar(255) DEFAULT NULL,
  ADD COLUMN `dp_2` varchar(255) DEFAULT NULL,
  ADD COLUMN `is_installment_customer` tinyint(1) DEFAULT 0,
  ADD COLUMN `is_walk_in` tinyint(1) DEFAULT 1,
  ADD COLUMN `loyalty_point` decimal(15,2) DEFAULT 0.00,
  ADD COLUMN `same_or_diff_state` varchar(20) DEFAULT NULL,
  ADD COLUMN `shipping_address` text DEFAULT NULL,
  ADD COLUMN `tax_number` varchar(255) DEFAULT NULL;

-- ============================================================
-- 5. PROMOTIONS — missing columns
-- ============================================================
ALTER TABLE `promotions`
  ADD COLUMN `applicable_brands` text DEFAULT NULL,
  ADD COLUMN `applicable_days` varchar(100) DEFAULT NULL,
  ADD COLUMN `applicable_items` text DEFAULT NULL,
  ADD COLUMN `apply_on` varchar(50) DEFAULT 'all',
  ADD COLUMN `compound_discount` decimal(10,2) DEFAULT 0.00,
  ADD COLUMN `compound_markup` decimal(10,2) DEFAULT 0.00,
  ADD COLUMN `coupon_code` varchar(100) DEFAULT NULL,
  ADD COLUMN `description` text DEFAULT NULL,
  ADD COLUMN `discount` decimal(10,2) DEFAULT 0.00,
  ADD COLUMN `free_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  ADD COLUMN `free_qty` decimal(10,2) DEFAULT NULL,
  ADD COLUMN `get_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  ADD COLUMN `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  ADD COLUMN `markup_type` varchar(20) DEFAULT 'flat',
  ADD COLUMN `markup_value` decimal(10,2) DEFAULT 0.00,
  ADD COLUMN `max_usage` int(11) DEFAULT NULL,
  ADD COLUMN `min_purchase_amount` decimal(10,2) DEFAULT 0.00,
  ADD COLUMN `min_qty` decimal(10,2) DEFAULT NULL,
  ADD COLUMN `outlet_id` bigint(20) UNSIGNED DEFAULT NULL,
  ADD COLUMN `priority` int(11) DEFAULT 0,
  ADD COLUMN `qty` decimal(10,2) DEFAULT NULL,
  ADD COLUMN `special_price` decimal(10,2) DEFAULT NULL,
  ADD COLUMN `title` varchar(255) DEFAULT NULL,
  ADD COLUMN `usage_count` int(11) DEFAULT 0;

-- ============================================================
-- 6. ITEMS — missing columns
-- ============================================================
ALTER TABLE `items`
  ADD COLUMN `barcode` varchar(255) DEFAULT NULL,
  ADD COLUMN `cost_price` decimal(15,2) DEFAULT 0.00,
  ADD COLUMN `discount_rate` decimal(5,2) DEFAULT 0.00,
  ADD COLUMN `discount_type` varchar(20) DEFAULT NULL,
  ADD COLUMN `image` varchar(255) DEFAULT NULL,
  ADD COLUMN `is_imei` tinyint(1) DEFAULT 0,
  ADD COLUMN `item_code` varchar(255) DEFAULT NULL,
  ADD COLUMN `minimum_stock` decimal(15,2) DEFAULT 0.00,
  ADD COLUMN `opening_stock` decimal(15,2) DEFAULT 0.00,
  ADD COLUMN `price` decimal(15,2) DEFAULT 0.00,
  ADD COLUMN `serial_number_needed` tinyint(1) DEFAULT 0,
  ADD COLUMN `status` varchar(20) DEFAULT 'Enable',
  ADD COLUMN `stock_quantity` decimal(15,2) DEFAULT 0.00,
  ADD COLUMN `tax_rate` decimal(5,2) DEFAULT 0.00,
  ADD COLUMN `unit_id` bigint(20) UNSIGNED DEFAULT NULL;

-- ============================================================
-- 7. PURCHASES — missing columns
-- ============================================================
ALTER TABLE `purchases`
  ADD COLUMN `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  ADD COLUMN `discount_type` varchar(20) DEFAULT NULL,
  ADD COLUMN `due` decimal(15,2) DEFAULT 0.00,
  ADD COLUMN `due_date` date DEFAULT NULL,
  ADD COLUMN `invoice_id` varchar(255) DEFAULT NULL,
  ADD COLUMN `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  ADD COLUMN `payment_status` varchar(20) DEFAULT 'Paid',
  ADD COLUMN `purchase_date` date DEFAULT NULL,
  ADD COLUMN `purchase_status` varchar(20) DEFAULT 'Complete',
  ADD COLUMN `reference` varchar(255) DEFAULT NULL,
  ADD COLUMN `shipping` decimal(15,2) DEFAULT 0.00,
  ADD COLUMN `status` varchar(50) DEFAULT 'received',
  ADD COLUMN `subtotal` decimal(15,2) DEFAULT 0.00,
  ADD COLUMN `tax` decimal(15,2) DEFAULT 0.00,
  ADD COLUMN `total` decimal(15,2) DEFAULT 0.00;

-- ============================================================
-- 8. OTHER TABLES — missing columns
-- ============================================================

-- bookings
ALTER TABLE `bookings`
  ADD COLUMN `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  ADD COLUMN `service_note` text DEFAULT NULL;

-- counters
ALTER TABLE `counters`
  ADD COLUMN `status` varchar(20) DEFAULT 'Enable',
  ADD COLUMN `description` varchar(255) DEFAULT NULL;

-- customer_receives
ALTER TABLE `customer_receives`
  ADD COLUMN `reference_no` varchar(255) DEFAULT NULL;

-- damages
ALTER TABLE `damages`
  ADD COLUMN `damage_type` varchar(50) DEFAULT 'expired';

-- fixed_asset_items
ALTER TABLE `fixed_asset_items`
  ADD COLUMN `category` varchar(100) DEFAULT NULL,
  ADD COLUMN `purchase_date` date DEFAULT NULL;

-- fixed_asset_stock_in_details
ALTER TABLE `fixed_asset_stock_in_details`
  ADD COLUMN `quantity` decimal(10,2) DEFAULT 0.00;

-- fixed_asset_stock_out_details
ALTER TABLE `fixed_asset_stock_out_details`
  ADD COLUMN `quantity` decimal(10,2) DEFAULT 0.00,
  ADD COLUMN `reason` text DEFAULT NULL;

-- hold_details
ALTER TABLE `hold_details`
  ADD COLUMN `discount_type` varchar(20) DEFAULT 'flat';

-- holds
ALTER TABLE `holds`
  ADD COLUMN `counter_id` bigint(20) UNSIGNED DEFAULT NULL,
  ADD COLUMN `sub_total_discount_type` varchar(20) DEFAULT 'flat';

-- installment_sales
ALTER TABLE `installment_sales`
  ADD COLUMN `discount` decimal(10,2) DEFAULT 0.00,
  ADD COLUMN `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL;

-- item_categories
ALTER TABLE `item_categories`
  ADD COLUMN `parent_id` bigint(20) UNSIGNED DEFAULT NULL;

-- outlets
ALTER TABLE `outlets`
  ADD COLUMN `active_status` varchar(20) DEFAULT 'Active';

-- payment_methods
ALTER TABLE `payment_methods`
  ADD COLUMN `current_balance` decimal(15,2) DEFAULT 0.00;

-- purchase_payments
ALTER TABLE `purchase_payments`
  ADD COLUMN `reference_no` varchar(255) DEFAULT NULL;

-- quotations
ALTER TABLE `quotations`
  ADD COLUMN `discount` decimal(10,2) DEFAULT 0.00;

-- sale_details
ALTER TABLE `sale_details`
  ADD COLUMN `discount_type` varchar(20) DEFAULT 'flat';

-- sale_payments
ALTER TABLE `sale_payments`
  ADD COLUMN `reference_no` varchar(255) DEFAULT NULL;

-- sale_returns
ALTER TABLE `sale_returns`
  ADD COLUMN `local_id` varchar(100) DEFAULT NULL;

-- servicings
ALTER TABLE `servicings`
  ADD COLUMN `current_status` varchar(50) DEFAULT 'pending';

-- supplier_payments
ALTER TABLE `supplier_payments`
  ADD COLUMN `reference_no` varchar(255) DEFAULT NULL,
  ADD COLUMN `outlet_id` bigint(20) UNSIGNED DEFAULT NULL;

-- suppliers
ALTER TABLE `suppliers`
  ADD COLUMN `contact_person` varchar(255) DEFAULT NULL,
  ADD COLUMN `current_balance` decimal(15,2) DEFAULT 0.00,
  ADD COLUMN `tax_number` varchar(255) DEFAULT NULL,
  ADD COLUMN `vat_number` varchar(100) DEFAULT NULL;

-- transfers
ALTER TABLE `transfers`
  ADD COLUMN `status` varchar(50) DEFAULT 'pending';

-- units
ALTER TABLE `units`
  ADD COLUMN `base_unit_id` bigint(20) UNSIGNED DEFAULT NULL,
  ADD COLUMN `conversion_rate` decimal(10,4) DEFAULT 1.0000,
  ADD COLUMN `name` varchar(255) DEFAULT NULL,
  ADD COLUMN `short_name` varchar(255) DEFAULT NULL;

-- users
ALTER TABLE `users`
  ADD COLUMN `active_status` varchar(20) DEFAULT 'Active',
  ADD COLUMN `is_saas` varchar(20) DEFAULT NULL;

-- warranties
ALTER TABLE `warranties`
  ADD COLUMN `item_model` varchar(255) DEFAULT NULL,
  ADD COLUMN `item_name` varchar(255) DEFAULT NULL,
  ADD COLUMN `serial_no` varchar(255) DEFAULT NULL;
