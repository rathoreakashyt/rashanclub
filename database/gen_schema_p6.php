<?php
$sql = "CREATE TABLE IF NOT EXISTS `combo_sales` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `sale_returns` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `sale_return_details` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `customer_receives` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `holds` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `hold_details` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `hold_combo_items` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `bookings` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `promotions` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `quotations` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `quotation_details` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

file_put_contents(__DIR__ . '/schema_part6.sql', $sql);
echo "Part 6 written: " . strlen($sql) . " bytes\n";