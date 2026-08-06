SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ============================================================
-- PART 2: Payment Methods, Tax, Denominations, Currencies
-- ============================================================

-- Payment Methods
INSERT INTO `payment_methods` (`name`, `account_type`, `type`, `status`, `is_deletable`, `sort_id`, `is_active`, `configuration`, `user_id`, `company_id`, `del_status`, `created_at`, `updated_at`) VALUES
('Cash',         'Cash',          'cash',   'Enable', 'No', 1, 1, NULL, 1, 1, 'Live', NOW(), NOW()),
('Bank Transfer','Bank_Account',  'bank',   'Enable', 'No', 2, 1, NULL, 1, 1, 'Live', NOW(), NOW()),
('UPI',          'UPI',           'upi',    'Enable', 'Yes',3, 1, NULL, 1, 1, 'Live', NOW(), NOW()),
('Credit Card',  'Credit_Card',   'card',   'Enable', 'Yes',4, 1, NULL, 1, 1, 'Live', NOW(), NOW()),
('Loyalty Point','Loyalty Point', 'loyalty','Enable', 'No', 5, 1, NULL, 1, 1, 'Live', NOW(), NOW());

-- Update company default payment and customer
UPDATE `companies` SET `default_payment` = 1, `default_customer` = 1 WHERE `id` = 1;

-- GST Sub taxes (CGST, SGST, IGST)
INSERT INTO `taxs` (`tax_name`, `tax_rate`, `parent_tax_id`, `show_in_item_profile`, `company_id`, `del_status`, `created_at`, `updated_at`) VALUES
('CGST',  9.00, NULL, 1, 1, 'Live', NOW(), NOW()),
('SGST',  9.00, NULL, 1, 1, 'Live', NOW(), NOW()),
('IGST', 18.00, NULL, 1, 1, 'Live', NOW(), NOW()),
('GST 5%', 5.00, NULL, 1, 1, 'Live', NOW(), NOW()),
('GST 12%',12.00, NULL, 1, 1, 'Live', NOW(), NOW());

-- GST parent tax (18%)
INSERT INTO `taxs` (`tax_name`, `tax_rate`, `parent_tax_id`, `show_in_item_profile`, `company_id`, `del_status`, `created_at`, `updated_at`) VALUES
('GST 18%', 18.00, NULL, 1, 1, 'Live', NOW(), NOW());

-- Link CGST & SGST under GST 18%
UPDATE `taxs` SET `parent_tax_id` = LAST_INSERT_ID() WHERE `tax_name` IN ('CGST','SGST') AND `company_id` = 1;

-- Denominations (Indian currency notes & coins)
INSERT INTO `denominations` (`name`, `value`, `type`, `user_id`, `company_id`, `del_status`, `created_at`, `updated_at`) VALUES
('2000 Note', 2000.00, 'Note', 1, 1, 'Live', NOW(), NOW()),
('500 Note',   500.00, 'Note', 1, 1, 'Live', NOW(), NOW()),
('200 Note',   200.00, 'Note', 1, 1, 'Live', NOW(), NOW()),
('100 Note',   100.00, 'Note', 1, 1, 'Live', NOW(), NOW()),
('50 Note',     50.00, 'Note', 1, 1, 'Live', NOW(), NOW()),
('20 Note',     20.00, 'Note', 1, 1, 'Live', NOW(), NOW()),
('10 Note',     10.00, 'Note', 1, 1, 'Live', NOW(), NOW()),
('10 Coin',     10.00, 'Coin', 1, 1, 'Live', NOW(), NOW()),
('5 Coin',       5.00, 'Coin', 1, 1, 'Live', NOW(), NOW()),
('2 Coin',       2.00, 'Coin', 1, 1, 'Live', NOW(), NOW()),
('1 Coin',       1.00, 'Coin', 1, 1, 'Live', NOW(), NOW());

-- Multiple Currencies (INR as base)
INSERT INTO `multiple_currencies` (`name`, `symbol`, `exchange_rate`, `is_base`, `user_id`, `company_id`, `del_status`, `created_at`, `updated_at`) VALUES
('Indian Rupee', 'Rs.', 1.0000, 1, 1, 1, 'Live', NOW(), NOW()),
('US Dollar',    '$',  83.5000, 0, 1, 1, 'Live', NOW(), NOW());

-- Delivery Partners
INSERT INTO `delivery_partners` (`name`, `phone`, `address`, `commission_percent`, `user_id`, `company_id`, `del_status`, `created_at`, `updated_at`) VALUES
('Swiggy Instamart', '+91-9000000001', 'Bangalore, Karnataka', 8.00, 1, 1, 'Live', NOW(), NOW()),
('Zepto',            '+91-9000000002', 'Mumbai, Maharashtra',  7.50, 1, 1, 'Live', NOW(), NOW()),
('Dunzo',            '+91-9000000003', 'Delhi, India',         9.00, 1, 1, 'Live', NOW(), NOW());

-- Expense Categories
INSERT INTO `expense_categories` (`name`, `description`, `user_id`, `company_id`, `del_status`, `created_at`, `updated_at`) VALUES
('Shop Rent',       'Monthly shop rent payment',      1, 1, 'Live', NOW(), NOW()),
('Electricity',     'Electricity bill',               1, 1, 'Live', NOW(), NOW()),
('Staff Salary',    'Employee salary expenses',       1, 1, 'Live', NOW(), NOW()),
('Transport',       'Delivery and transport costs',   1, 1, 'Live', NOW(), NOW()),
('Miscellaneous',   'Other miscellaneous expenses',   1, 1, 'Live', NOW(), NOW());

-- Income Categories
INSERT INTO `income_categories` (`name`, `description`, `user_id`, `company_id`, `del_status`, `created_at`, `updated_at`) VALUES
('Product Sales',   'Revenue from product sales',     1, 1, 'Live', NOW(), NOW()),
('Late Fee',        'Late payment fees from customers',1, 1, 'Live', NOW(), NOW()),
('Other Income',    'Miscellaneous income',           1, 1, 'Live', NOW(), NOW());

SELECT 'Part 2 Done - Payment Methods, Tax, Denominations' AS status;
SET FOREIGN_KEY_CHECKS = 1;
