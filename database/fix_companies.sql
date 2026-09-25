-- ═══════════════════════════════════════════════════════════════
--  fix_companies.sql — Add ALL missing columns to companies table
--  Safe to run multiple times (skips columns that already exist)
--  Run via phpMyAdmin → SQL tab → paste → Go
-- ═══════════════════════════════════════════════════════════════

SET @dbname = DATABASE();

-- ── STEP 1: Resize columns that are too small (String data right truncated fix) ──
-- currency_position varchar(10) → varchar(50)  (needs to hold "Before Amount" = 13 chars)
-- is_loyalty_enable varchar(5) → varchar(10)   (needs to hold "Disable" = 7 chars)

ALTER TABLE `companies`
    MODIFY COLUMN `currency_position` VARCHAR(50) DEFAULT NULL,
    MODIFY COLUMN `is_loyalty_enable` VARCHAR(10) DEFAULT 'Disable',
    MODIFY COLUMN `e_commerce_checker` VARCHAR(10) DEFAULT 'No',
    MODIFY COLUMN `allow_less_sale` VARCHAR(5) DEFAULT 'No',
    MODIFY COLUMN `is_rounding_enable` VARCHAR(5) DEFAULT 'No',
    MODIFY COLUMN `direct_cart` VARCHAR(5) DEFAULT 'No',
    MODIFY COLUMN `grocery_experience` VARCHAR(5) DEFAULT 'No',
    MODIFY COLUMN `generic_name_search_option` VARCHAR(5) DEFAULT 'No',
    MODIFY COLUMN `smtp_default_selected_in_pos` VARCHAR(5) DEFAULT 'No',
    MODIFY COLUMN `sms_default_selected_in_pos` VARCHAR(5) DEFAULT 'No',
    MODIFY COLUMN `whatsapp_default_selected_in_pos` VARCHAR(5) DEFAULT 'No',
    MODIFY COLUMN `whatsapp_invoice_enable_status` VARCHAR(10) DEFAULT '0',
    MODIFY COLUMN `sms_enable_status` VARCHAR(10) DEFAULT '0',
    MODIFY COLUMN `smtp_enable_status` VARCHAR(10) DEFAULT '0',
    MODIFY COLUMN `white_label_status` VARCHAR(10) DEFAULT 'off',
    MODIFY COLUMN `purchase_price_show_hide` VARCHAR(10) DEFAULT 'show',
    MODIFY COLUMN `inv_logo_is_show` VARCHAR(10) DEFAULT 'Yes',
    MODIFY COLUMN `product_code_start_from` INT DEFAULT 1;

-- ── STEP 2: Add ALL missing columns (safe — skips existing) ──
DROP PROCEDURE IF EXISTS add_col;
DELIMITER //
CREATE PROCEDURE add_col(IN p_table VARCHAR(64), IN p_col VARCHAR(64), IN p_def VARCHAR(500))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = p_table AND COLUMN_NAME = p_col
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_col, '` ', p_def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- ── companies: all columns needed by SettingService / BusinessSettingRequest ──

CALL add_col('companies', 'business_name',                "VARCHAR(255) DEFAULT NULL");
CALL add_col('companies', 'short_name',                   "VARCHAR(50) DEFAULT NULL");
CALL add_col('companies', 'website',                      "VARCHAR(255) DEFAULT NULL");
CALL add_col('companies', 'zone_name',                    "VARCHAR(100) DEFAULT 'Asia/Kolkata'");
CALL add_col('companies', 'default_customer',             "INT DEFAULT 1");
CALL add_col('companies', 'default_cursor_position',      "VARCHAR(50) DEFAULT NULL");
CALL add_col('companies', 'product_display',              "VARCHAR(50) DEFAULT NULL");
CALL add_col('companies', 'onscreen_keyboard_status',     "VARCHAR(10) DEFAULT 'off'");
CALL add_col('companies', 'default_payment',              "VARCHAR(50) DEFAULT NULL");
CALL add_col('companies', 'payment_settings',             "TEXT DEFAULT NULL");
CALL add_col('companies', 'invoice_configuration',        "TEXT DEFAULT NULL");
CALL add_col('companies', 'collect_tax',                  "VARCHAR(10) DEFAULT 'No'");
CALL add_col('companies', 'tax_title',                    "VARCHAR(50) DEFAULT NULL");
CALL add_col('companies', 'tax_registration_no',          "VARCHAR(50) DEFAULT NULL");
CALL add_col('companies', 'tax_is_gst',                   "VARCHAR(10) DEFAULT 'No'");
CALL add_col('companies', 'tax_registration_number',      "VARCHAR(255) DEFAULT NULL");
CALL add_col('companies', 'tax_setting',                  "TEXT DEFAULT NULL");
CALL add_col('companies', 'tax_string',                   "TEXT DEFAULT NULL");
CALL add_col('companies', 'white_label',                  "TEXT DEFAULT NULL");
CALL add_col('companies', 'thousands_separator',          "VARCHAR(5) DEFAULT ','");
CALL add_col('companies', 'decimals_separator',           "VARCHAR(5) DEFAULT '.'");
CALL add_col('companies', 'register_content',             "TEXT DEFAULT NULL");
CALL add_col('companies', 'smtp_type',                    "VARCHAR(50) DEFAULT NULL");
CALL add_col('companies', 'smtp_details',                 "TEXT DEFAULT NULL");
CALL add_col('companies', 'sms_service_provider',         "VARCHAR(100) DEFAULT NULL");
CALL add_col('companies', 'sms_details',                  "TEXT DEFAULT NULL");
CALL add_col('companies', 'whatsapp_provider',            "VARCHAR(100) DEFAULT NULL");
CALL add_col('companies', 'whatsapp_app_key',             "VARCHAR(255) DEFAULT NULL");
CALL add_col('companies', 'whatsapp_authkey',             "VARCHAR(255) DEFAULT NULL");
CALL add_col('companies', 'payment_api_setting',          "TEXT DEFAULT NULL");
CALL add_col('companies', 'zatca_configuration',          "TEXT DEFAULT NULL");
CALL add_col('companies', 'gst_api_key',                  "VARCHAR(255) DEFAULT NULL");
CALL add_col('companies', 'company_name',                 "VARCHAR(255) DEFAULT NULL");
CALL add_col('companies', 'company_email',                "VARCHAR(255) DEFAULT NULL");
CALL add_col('companies', 'busynotify_token',             "TEXT DEFAULT NULL");
CALL add_col('companies', 'busynotify_company_id',        "VARCHAR(50) DEFAULT NULL");
CALL add_col('companies', 'pos_total_payable_type',       "VARCHAR(50) DEFAULT 'grand_total'");
CALL add_col('companies', 'letter_head_gap',              "INT DEFAULT 0");
CALL add_col('companies', 'letter_footer_gap',            "INT DEFAULT 0");
CALL add_col('companies', 'invoice_footer',               "TEXT DEFAULT NULL");
CALL add_col('companies', 'term_conditions',              "TEXT DEFAULT NULL");
CALL add_col('companies', 'minimum_point_to_redeem',      "DECIMAL(15,2) DEFAULT 0.00");
CALL add_col('companies', 'loyalty_rate',                 "DECIMAL(5,2) DEFAULT 0.00");

-- Cleanup
DROP PROCEDURE IF EXISTS add_col;
