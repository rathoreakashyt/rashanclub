SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ============================================================
-- PART 3: Suppliers, Categories, Brands, Units, Racks, Variations
-- ============================================================

-- Item Categories (Rashan items)
INSERT INTO `item_categories` (`name`, `description`, `sort_id`, `company_id`, `user_id`, `del_status`, `created_at`, `updated_at`) VALUES
('Atta & Flour',     'Wheat flour, atta, maida etc.',          1, 1, 1, 'Live', NOW(), NOW()),
('Rice & Dal',       'Rice, dal, pulses varieties',            2, 1, 1, 'Live', NOW(), NOW()),
('Oil & Ghee',       'Cooking oils and ghee',                  3, 1, 1, 'Live', NOW(), NOW()),
('Spices & Masala',  'Spices, masalas, salt, sugar',           4, 1, 1, 'Live', NOW(), NOW()),
('Sugar & Salt',     'Sugar, salt, jaggery',                   5, 1, 1, 'Live', NOW(), NOW()),
('Tea & Coffee',     'Tea leaves, coffee powder',              6, 1, 1, 'Live', NOW(), NOW()),
('Soap & Detergent', 'Cleaning and hygiene products',          7, 1, 1, 'Live', NOW(), NOW()),
('Biscuits & Snacks','Biscuits, namkeen, snacks',              8, 1, 1, 'Live', NOW(), NOW());

-- Brands
INSERT INTO `brands` (`name`, `description`, `company_id`, `user_id`, `del_status`, `created_at`, `updated_at`) VALUES
('Aashirvaad',   'ITC Aashirvaad brand products',      1, 1, 'Live', NOW(), NOW()),
('Tata',         'Tata consumer products',              1, 1, 'Live', NOW(), NOW()),
('Fortune',      'Adani Wilmar Fortune brand',          1, 1, 'Live', NOW(), NOW()),
('MDH',          'MDH spices and masalas',              1, 1, 'Live', NOW(), NOW()),
('Amul',         'Amul dairy products',                 1, 1, 'Live', NOW(), NOW()),
('Patanjali',    'Patanjali Ayurved products',          1, 1, 'Live', NOW(), NOW()),
('Parle',        'Parle biscuits and snacks',           1, 1, 'Live', NOW(), NOW()),
('India Gate',   'India Gate rice products',            1, 1, 'Live', NOW(), NOW()),
('Surf Excel',   'HUL Surf Excel detergents',          1, 1, 'Live', NOW(), NOW()),
('No Brand',     'Unbranded / local products',         1, 1, 'Live', NOW(), NOW());

-- Units
INSERT INTO `units` (`unit_name`, `description`, `company_id`, `user_id`, `del_status`, `created_at`, `updated_at`) VALUES
('Kg',      'Kilogram',            1, 1, 'Live', NOW(), NOW()),
('Gram',    'Gram',                1, 1, 'Live', NOW(), NOW()),
('Litre',   'Litre',               1, 1, 'Live', NOW(), NOW()),
('Ml',      'Millilitre',          1, 1, 'Live', NOW(), NOW()),
('Piece',   'Piece / Unit',        1, 1, 'Live', NOW(), NOW()),
('Packet',  'Packet',              1, 1, 'Live', NOW(), NOW()),
('Box',     'Box',                 1, 1, 'Live', NOW(), NOW()),
('Dozen',   'Dozen (12 pieces)',   1, 1, 'Live', NOW(), NOW()),
('Quintal', 'Quintal (100 Kg)',    1, 1, 'Live', NOW(), NOW()),
('Bag',     'Bag (50 Kg)',         1, 1, 'Live', NOW(), NOW());

-- Racks / Storage locations
INSERT INTO `racks` (`name`, `description`, `company_id`, `user_id`, `del_status`, `created_at`, `updated_at`) VALUES
('Rack A - Atta/Rice',    'Front rack for staples',        1, 1, 'Live', NOW(), NOW()),
('Rack B - Oil/Ghee',     'Oil and ghee section',          1, 1, 'Live', NOW(), NOW()),
('Rack C - Spices',       'Masala and spice rack',         1, 1, 'Live', NOW(), NOW()),
('Rack D - Packaged',     'Packaged goods rack',           1, 1, 'Live', NOW(), NOW()),
('Store Room',            'Back store room',               1, 1, 'Live', NOW(), NOW());

-- Variations (for different pack sizes)
INSERT INTO `variations` (`variation_name`, `variation_value`, `user_id`, `company_id`, `del_status`, `created_at`, `updated_at`) VALUES
('Pack Size', '["1 Kg","2 Kg","5 Kg","10 Kg","25 Kg","50 Kg"]',       1, 1, 'Live', NOW(), NOW()),
('Volume',    '["250 ml","500 ml","1 Litre","2 Litre","5 Litre"]',     1, 1, 'Live', NOW(), NOW()),
('Weight',    '["100 g","200 g","250 g","500 g","1 Kg","2 Kg"]',       1, 1, 'Live', NOW(), NOW());

-- Suppliers
INSERT INTO `suppliers` (`name`, `company_name`, `email`, `phone`, `address`, `city`, `state`, `postal_code`, `country`, `gst_number`, `opening_balance`, `opening_balance_type`, `credit_limit`, `description`, `user_id`, `company_id`, `del_status`, `created_at`, `updated_at`) VALUES
('Ramesh Agarwal',    'Agarwal Wholesale Traders',   'ramesh@agarwaltraders.com',  '+91-9811111111', '15, Wholesale Market, Chandni Chowk', 'New Delhi',   'Delhi',      '110006', 'India', '07AAACR0001R1ZX', 50000.000, 'Debit',  200000.000, 'Main wholesale supplier for grains and staples',  1, 1, 'Live', NOW(), NOW()),
('Sunil Sharma',      'Sharma Oil Distributors',     'sunil@sharmaoil.com',        '+91-9822222222', '8, Industrial Area, Patparganj',      'New Delhi',   'Delhi',      '110092', 'India', '07AAACS0002S1ZX', 25000.000, 'Debit',  100000.000, 'Oil and ghee supplier',                           1, 1, 'Live', NOW(), NOW()),
('Mohan Gupta',       'Gupta Masala House',          'mohan@guptamasala.com',      '+91-9833333333', '22, Khari Baoli, Sadar Bazar',        'New Delhi',   'Delhi',      '110006', 'India', '07AAACG0003G1ZX', 15000.000, 'Debit',   50000.000, 'Spices and masala supplier',                      1, 1, 'Live', NOW(), NOW()),
('Deepak Verma',      'Verma General Stores',        'deepak@vermageneral.com',    '+91-9844444444', '3, Nehru Place Market',               'New Delhi',   'Delhi',      '110019', 'India', '07AAACV0004V1ZX',  5000.000, 'Credit',  30000.000, 'General items and packaged goods supplier',       1, 1, 'Live', NOW(), NOW()),
('Anita Joshi',       'Joshi FMCG Distributors',     'anita@joshifmcg.com',        '+91-9855555555', '11, Lawrence Road, Industrial Area',  'New Delhi',   'Delhi',      '110035', 'India', '07AAACJ0005J1ZX', 10000.000, 'Debit',   75000.000, 'FMCG products - soap, detergent, biscuits',       1, 1, 'Live', NOW(), NOW());

SELECT 'Part 3 Done - Categories, Brands, Units, Racks, Variations, Suppliers' AS status;
SET FOREIGN_KEY_CHECKS = 1;
