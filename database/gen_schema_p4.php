<?php
$sql = "CREATE TABLE IF NOT EXISTS `item_categories` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `sort_id` INT DEFAULT 0,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `brands` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `units` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `unit_name` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `variations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `variation_name` VARCHAR(255) DEFAULT NULL,
    `variation_value` JSON DEFAULT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `racks` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `company_id` BIGINT UNSIGNED DEFAULT 1,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `del_status` VARCHAR(20) DEFAULT 'Live',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `items` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `combo_items` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `set_opening_stocks` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `damages` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `damage_details` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `transfers` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `transfer_details` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `fixed_asset_items` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

file_put_contents(__DIR__ . '/schema_part4.sql', $sql);
echo "Part 4 written: " . strlen($sql) . " bytes\n";