<?php
$sql = "CREATE TABLE IF NOT EXISTS `installment_sales` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `installment_sale_details` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `installment_sale_payments` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `invoice_hash_chain` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` BIGINT UNSIGNED DEFAULT NULL,
    `outlet_id` BIGINT UNSIGNED DEFAULT NULL,
    `previous_hash` VARCHAR(255) DEFAULT NULL,
    `current_hash` VARCHAR(255) DEFAULT NULL,
    `zatca_invoice_id` BIGINT UNSIGNED DEFAULT NULL,
    `chain_index` INT DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `servicings` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `warranties` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `zatca_invoices` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `zatca_requests` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `registers` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

file_put_contents(__DIR__ . '/schema_part7.sql', $sql);
echo "Part 7 written: " . strlen($sql) . " bytes\n";