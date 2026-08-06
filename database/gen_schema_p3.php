<?php
$sql = "CREATE TABLE IF NOT EXISTS `employee_advance_payments` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `salaries` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `salary_items` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `salary_payments` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `suppliers` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `purchases` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `purchase_details` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `purchase_payments` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `purchase_returns` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `purchase_return_details` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `supplier_payments` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

file_put_contents(__DIR__ . '/schema_part3.sql', $sql);
echo "Part 3 written: " . strlen($sql) . " bytes\n";