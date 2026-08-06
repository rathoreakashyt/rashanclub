-- ============================================================
-- Missing Columns Script - MySQL 8.0 Compatible
-- Uses stored procedure to check column existence before adding
-- ============================================================

DROP PROCEDURE IF EXISTS add_column_if_missing;

DELIMITER //
CREATE PROCEDURE add_column_if_missing(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = p_table
          AND COLUMN_NAME  = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('Added: ', p_table, '.', p_column) AS log;
    ELSE
        SELECT CONCAT('Exists: ', p_table, '.', p_column) AS log;
    END IF;
END//
DELIMITER ;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- companies table
-- ============================================================
CALL add_column_if_missing('companies','business_name','VARCHAR(255) DEFAULT NULL');
CALL add_column_if_missing('companies','short_name','VARCHAR(100) DEFAULT NULL');
CALL add_column_if_missing('companies','zone_name','VARCHAR(100) DEFAULT NULL');
CALL add_column_if_missing('companies','website','VARCHAR(255) DEFAULT NULL');
CALL add_column_if_missing('companies','currency_position','VARCHAR(50) DEFAULT \'Before Amount\'');
CALL add_column_if_missing('companies','precision','INT DEFAULT 2');
CALL add_column_if_missing('companies','thousands_separator','VARCHAR(10) DEFAULT \',\'');
CALL add_column_if_missing('companies','decimals_separator','VARCHAR(10) DEFAULT \'.\'');
CALL add_column_if_missing('companies','default_customer','BIGINT UNSIGNED DEFAULT NULL');
CALL add_column_if_missing('companies','default_payment','BIGINT UNSIGNED DEFAULT NULL');
CALL add_column_if_missing('companies','default_cursor_position','VARCHAR(100) DEFAULT \'Barcode Box\'');
CALL add_column_if_missing('companies','product_display','VARCHAR(50) DEFAULT \'Image View\'');
CALL add_column_if_missing('companies','onscreen_keyboard_status','VARCHAR(20) DEFAULT \'Disable\'');
CALL add_column_if_missing('companies','allow_less_sale','VARCHAR(20) DEFAULT \'No\'');
CALL add_column_if_missing('companies','direct_cart','VARCHAR(20) DEFAULT \'No\'');
CALL add_column_if_missing('companies','grocery_experience','VARCHAR(20) DEFAULT \'No\'');
CALL add_column_if_missing('companies','pos_total_payable_type','VARCHAR(50) DEFAULT NULL');
CALL add_column_if_missing('companies','register_content','JSON DEFAULT NULL');
CALL add_column_if_missing('companies','inv_logo_is_show','VARCHAR(10) DEFAULT \'Yes\'');
CALL add_column_if_missing('companies','invoice_logo','VARCHAR(255) DEFAULT NULL');
CALL add_column_if_missing('companies','invoice_footer','TEXT DEFAULT NULL');
CALL add_column_if_missing('companies','term_conditions','TEXT DEFAULT NULL');
CALL add_column_if_missing('companies','letter_head_gap','INT DEFAULT 200');
CALL add_column_if_missing('companies','letter_footer_gap','INT DEFAULT 100');
CALL add_column_if_missing('companies','invoice_configuration','JSON DEFAULT NULL');
CALL add_column_if_missing('companies','collect_tax','VARCHAR(10) DEFAULT \'No\'');
CALL add_column_if_missing('companies','tax_is_gst','VARCHAR(10) DEFAULT \'No\'');
CALL add_column_if_missing('companies','tax_title','VARCHAR(100) DEFAULT NULL');
CALL add_column_if_missing('companies','tax_registration_no','VARCHAR(100) DEFAULT NULL');
CALL add_column_if_missing('companies','tax_setting','JSON DEFAULT NULL');
CALL add_column_if_missing('companies','tax_string','TEXT DEFAULT NULL');
CALL add_column_if_missing('companies','installment_days','INT DEFAULT 3');
CALL add_column_if_missing('companies','minimum_point_to_redeem','DECIMAL(15,2) DEFAULT 0.00');
CALL add_column_if_missing('companies','loyalty_rate','DECIMAL(15,2) DEFAULT 0.00');
CALL add_column_if_missing('companies','is_loyalty_enable','VARCHAR(10) DEFAULT \'No\'');
CALL add_column_if_missing('companies','e_commerce_checker','VARCHAR(10) DEFAULT \'No\'');
CALL add_column_if_missing('companies','product_code_start_from','VARCHAR(50) DEFAULT \'000001\'');
CALL add_column_if_missing('companies','smtp_type','VARCHAR(50) DEFAULT NULL');
CALL add_column_if_missing('companies','smtp_enable_status','VARCHAR(20) DEFAULT \'Disable\'');
CALL add_column_if_missing('companies','smtp_details','JSON DEFAULT NULL');
CALL add_column_if_missing('companies','smtp_default_selected_in_pos','VARCHAR(20) DEFAULT \'No\'');
CALL add_column_if_missing('companies','sms_service_provider','VARCHAR(100) DEFAULT NULL');
CALL add_column_if_missing('companies','sms_enable_status','VARCHAR(20) DEFAULT \'Disable\'');
CALL add_column_if_missing('companies','sms_details','JSON DEFAULT NULL');
CALL add_column_if_missing('companies','sms_default_selected_in_pos','VARCHAR(20) DEFAULT \'No\'');
CALL add_column_if_missing('companies','whatsapp_provider','VARCHAR(100) DEFAULT NULL');
CALL add_column_if_missing('companies','whatsapp_invoice_enable_status','VARCHAR(20) DEFAULT \'Disable\'');
CALL add_column_if_missing('companies','whatsapp_app_key','VARCHAR(255) DEFAULT NULL');
CALL add_column_if_missing('companies','whatsapp_authkey','VARCHAR(255) DEFAULT NULL');
CALL add_column_if_missing('companies','whatsapp_default_selected_in_pos','VARCHAR(20) DEFAULT \'No\'');
CALL add_column_if_missing('companies','payment_api_setting','JSON DEFAULT NULL');
CALL add_column_if_missing('companies','payment_settings','JSON DEFAULT NULL');
CALL add_column_if_missing('companies','zatca_configuration','JSON DEFAULT NULL');
CALL add_column_if_missing('companies','white_label_status','VARCHAR(20) DEFAULT \'Disable\'');
CALL add_column_if_missing('companies','is_rounding_enable','VARCHAR(10) DEFAULT \'No\'');
CALL add_column_if_missing('companies','purchase_price_show_hide','VARCHAR(20) DEFAULT \'Show\'');
CALL add_column_if_missing('companies','generic_name_search_option','VARCHAR(20) DEFAULT \'No\'');

-- ============================================================
-- outlets table
-- ============================================================
CALL add_column_if_missing('outlets','outlet_name','VARCHAR(255) DEFAULT NULL');
CALL add_column_if_missing('outlets','outlet_code','VARCHAR(50) DEFAULT NULL');

-- ============================================================
-- time_zones table
-- ============================================================
CALL add_column_if_missing('time_zones','country_code','VARCHAR(10) DEFAULT NULL');
CALL add_column_if_missing('time_zones','zone_name','VARCHAR(100) DEFAULT NULL');
CALL add_column_if_missing('time_zones','del_status','VARCHAR(20) DEFAULT \'Live\'');

-- ============================================================
-- payment_methods table
-- ============================================================
CALL add_column_if_missing('payment_methods','account_type','VARCHAR(100) DEFAULT NULL');
CALL add_column_if_missing('payment_methods','status','VARCHAR(20) DEFAULT \'Enable\'');
CALL add_column_if_missing('payment_methods','is_deletable','VARCHAR(10) DEFAULT \'Yes\'');
CALL add_column_if_missing('payment_methods','sort_id','INT DEFAULT 0');

-- ============================================================
-- customers table
-- ============================================================
CALL add_column_if_missing('customers','loyalty_point','DECIMAL(15,2) DEFAULT 0.00');
CALL add_column_if_missing('customers','dob','DATE DEFAULT NULL');
CALL add_column_if_missing('customers','anniversary','DATE DEFAULT NULL');

-- ============================================================
-- suppliers table
-- ============================================================
CALL add_column_if_missing('suppliers','vat_number','VARCHAR(100) DEFAULT NULL');

-- ============================================================
-- items table
-- ============================================================
CALL add_column_if_missing('items','stock_quantity','DECIMAL(15,3) DEFAULT 0.000');

-- ============================================================
-- sales table
-- ============================================================
CALL add_column_if_missing('sales','counter_id','BIGINT UNSIGNED DEFAULT NULL');
CALL add_column_if_missing('sales','table_no','VARCHAR(100) DEFAULT NULL');
CALL add_column_if_missing('sales','booking_id','BIGINT UNSIGNED DEFAULT NULL');

-- ============================================================
-- holds table
-- ============================================================
CALL add_column_if_missing('holds','counter_id','BIGINT UNSIGNED DEFAULT NULL');

-- ============================================================
-- purchases table
-- ============================================================
CALL add_column_if_missing('purchases','status','VARCHAR(50) DEFAULT \'Pending\'');

-- ============================================================
-- bookings table
-- ============================================================
CALL add_column_if_missing('bookings','item_id','BIGINT UNSIGNED DEFAULT NULL');
CALL add_column_if_missing('bookings','service_note','TEXT DEFAULT NULL');

-- ============================================================
-- promotions table
-- ============================================================
CALL add_column_if_missing('promotions','applicable_items','JSON DEFAULT NULL');
CALL add_column_if_missing('promotions','applicable_categories','JSON DEFAULT NULL');
CALL add_column_if_missing('promotions','min_purchase_amount','DECIMAL(15,3) DEFAULT 0.000');
CALL add_column_if_missing('promotions','max_discount_amount','DECIMAL(15,3) DEFAULT 0.000');

-- ============================================================
-- servicings table
-- ============================================================
CALL add_column_if_missing('servicings','current_status','VARCHAR(50) DEFAULT NULL');

-- ============================================================
-- warranties table
-- ============================================================
CALL add_column_if_missing('warranties','item_name','VARCHAR(255) DEFAULT NULL');
CALL add_column_if_missing('warranties','item_model','VARCHAR(255) DEFAULT NULL');
CALL add_column_if_missing('warranties','serial_no','VARCHAR(255) DEFAULT NULL');

-- ============================================================
-- fixed_asset_stock_in_details
-- ============================================================
CALL add_column_if_missing('fixed_asset_stock_in_details','quantity','DECIMAL(15,3) DEFAULT 0.000');

-- ============================================================
-- fixed_asset_stock_out_details
-- ============================================================
CALL add_column_if_missing('fixed_asset_stock_out_details','quantity','DECIMAL(15,3) DEFAULT 0.000');
CALL add_column_if_missing('fixed_asset_stock_out_details','reason','TEXT DEFAULT NULL');

-- ============================================================
-- fixed_asset_items
-- ============================================================
CALL add_column_if_missing('fixed_asset_items','category','VARCHAR(255) DEFAULT NULL');
CALL add_column_if_missing('fixed_asset_items','purchase_date','DATE DEFAULT NULL');

-- ============================================================
-- damages
-- ============================================================
CALL add_column_if_missing('damages','damage_type','VARCHAR(50) DEFAULT NULL');

-- ============================================================
-- transfers
-- ============================================================
CALL add_column_if_missing('transfers','status','VARCHAR(50) DEFAULT \'Pending\'');

-- ============================================================
-- sale_payments
-- ============================================================
CALL add_column_if_missing('sale_payments','reference_no','VARCHAR(255) DEFAULT NULL');

-- ============================================================
-- purchase_payments
-- ============================================================
CALL add_column_if_missing('purchase_payments','reference_no','VARCHAR(255) DEFAULT NULL');

-- ============================================================
-- customer_receives
-- ============================================================
CALL add_column_if_missing('customer_receives','reference_no','VARCHAR(255) DEFAULT NULL');

-- ============================================================
-- supplier_payments
-- ============================================================
CALL add_column_if_missing('supplier_payments','reference_no','VARCHAR(255) DEFAULT NULL');
CALL add_column_if_missing('supplier_payments','outlet_id','BIGINT UNSIGNED DEFAULT NULL');

SET FOREIGN_KEY_CHECKS = 1;

DROP PROCEDURE IF EXISTS add_column_if_missing;

SELECT 'ALL MISSING COLUMNS ADDED SUCCESSFULLY!' AS final_result;
