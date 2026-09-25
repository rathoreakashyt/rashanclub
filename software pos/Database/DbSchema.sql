-- RashanKiDukan - Busy Clone Database Schema
-- Based on Busy 21 / Busy Magic architecture analysis
-- SQLite (modern replacement for MS Access Jet)

-- ==========================
-- COMPANY CONFIGURATION
-- ==========================
CREATE TABLE IF NOT EXISTS Company (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    Name TEXT NOT NULL,
    Address1 TEXT,
    Address2 TEXT,
    City TEXT,
    State TEXT,
    PinCode TEXT,
    GSTIN TEXT,
    PAN TEXT,
    Phone TEXT,
    Email TEXT,
    FinancialYearStart TEXT,
    FinancialYearEnd TEXT,
    CurrencySymbol TEXT DEFAULT '₹',
    IsActive INTEGER DEFAULT 1
);

-- ==========================
-- USERS & AUTHENTICATION
-- ==========================
CREATE TABLE IF NOT EXISTS Users (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    Username TEXT NOT NULL UNIQUE,
    PasswordHash TEXT NOT NULL,
    FullName TEXT,
    Role TEXT DEFAULT 'Operator',
    IsActive INTEGER DEFAULT 1,
    LastLogin DATETIME,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    CompanyId INTEGER REFERENCES Company(Id)
);

-- ==========================
-- MASTER1 (Busy jaisa - All Masters in One Table)
-- Party = Customer/Supplier, Item = Product, Account = Ledger
-- ==========================
CREATE TABLE IF NOT EXISTS Master1 (
    Code TEXT PRIMARY KEY,
    Name TEXT NOT NULL,
    AliasName TEXT,
    PrintName TEXT,
    ParentGroup TEXT,
    MasterType TEXT NOT NULL,
    PartyType TEXT,
    Description TEXT,
    GSTIN TEXT,
    PAN TEXT,
    Phone TEXT,
    Mobile TEXT,
    Email TEXT,
    Address1 TEXT,
    Address2 TEXT,
    City TEXT,
    State TEXT,
    PinCode TEXT,
    Station TEXT,
    OpeningBalance REAL DEFAULT 0,
    BalanceDate TEXT,
    CreditLimit REAL DEFAULT 0,
    TaxRate REAL DEFAULT 0,
    TaxCategory TEXT DEFAULT 'GST 0%',
    HSNCode TEXT,
    Unit TEXT,
    MainUnit TEXT,
    MRP REAL DEFAULT 0,
    MinSalePrice REAL DEFAULT 0,
    PurchaseRate REAL DEFAULT 0,
    SaleRate REAL DEFAULT 0,
    MinStock REAL DEFAULT 0,
    MaxStock REAL DEFAULT 0,
    CurrentStock REAL DEFAULT 0,
    Godown TEXT,
    Address TEXT,
    Country TEXT,
    WhatsApp TEXT,
    DealerType TEXT,
    FilingFreq TEXT,
    Transport TEXT,
    Distance TEXT,
    Aadhaar TEXT,
    TIN TEXT,
    Ward TEXT,
    CST TEXT,
    LST TEXT,
    IECode TEXT,
    SelfVal REAL DEFAULT 0,
    OpeningStock REAL DEFAULT 0,
    OpeningValue REAL DEFAULT 0,
    ItemType TEXT,
    Category TEXT,
    Brand TEXT,
    CategoryId INTEGER,
    BrandId INTEGER,
    SupplierId INTEGER,
    LoyaltyPoint INTEGER DEFAULT 0,
    UnitType TEXT,
    SaleUnitId INTEGER,
    PurchaseUnitId INTEGER,
    ConversionRate REAL DEFAULT 1,
    WholeSalePrice REAL DEFAULT 0,
    ProfitMargin REAL DEFAULT 0,
    AlertQty REAL DEFAULT 0,
    Warranty TEXT,
    WarrantyDate TEXT,
    Guarantee TEXT,
    GuaranteeDate TEXT,
    TaxType TEXT DEFAULT 'Exclusive',
    IsActive INTEGER DEFAULT 1,
    DelStatus TEXT,
    BusinessType TEXT,
    SameOrDiffState TEXT,
    DateOfBirth TEXT,
    DateOfAnniversary TEXT,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ==========================
-- MASTER ADDRESS INFO (Busy jaisa)
-- ==========================
CREATE TABLE IF NOT EXISTS MasterAddressInfo (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    MasterCode TEXT NOT NULL REFERENCES Master1(Code),
    AddressType TEXT DEFAULT 'Primary',
    Address1 TEXT,
    Address2 TEXT,
    City TEXT,
    State TEXT,
    PinCode TEXT,
    Country TEXT DEFAULT 'India',
    IsDefault INTEGER DEFAULT 0
);

-- ==========================
-- MASTER SUPPORT (Busy jaisa - Extra fields)
-- ==========================
CREATE TABLE IF NOT EXISTS MasterSupport (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    MasterCode TEXT NOT NULL REFERENCES Master1(Code),
    FieldName TEXT NOT NULL,
    FieldValue TEXT
);

-- ==========================
-- VOUCHER / TRANSACTION HEADER (Busy jaisa Tran1)
-- ==========================
CREATE TABLE IF NOT EXISTS Tran1 (
    VchCode TEXT PRIMARY KEY,
    VchType TEXT NOT NULL,
    VchNo TEXT NOT NULL,
    VchDate TEXT NOT NULL,
    VchSeriesCode TEXT,
    MasterCode1 TEXT REFERENCES Master1(Code),
    MasterCode2 TEXT REFERENCES Master1(Code),
    Narration TEXT,
    Amount REAL DEFAULT 0,
    IsCancelled INTEGER DEFAULT 0,
    CreatedBy INTEGER REFERENCES Users(Id),
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ==========================
-- VOUCHER LINE ITEMS (Busy jaisa Tran2)
-- ==========================
CREATE TABLE IF NOT EXISTS Tran2 (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    VchCode TEXT NOT NULL REFERENCES Tran1(VchCode),
    SrNo INTEGER NOT NULL,
    MasterCode1 TEXT REFERENCES Master1(Code),
    Description TEXT,
    Quantity REAL DEFAULT 0,
    Unit TEXT,
    Rate REAL DEFAULT 0,
    Amount REAL DEFAULT 0,
    DiscountPercent REAL DEFAULT 0,
    DiscountAmount REAL DEFAULT 0,
    MarkupPercent REAL DEFAULT 0,
    MarkupAmount REAL DEFAULT 0,
    TaxableAmount REAL DEFAULT 0,
    BatchNo TEXT,
    ExpiryDate TEXT,
    Godown TEXT
);

-- ==========================
-- GST / TAX CALCULATION (Busy jaisa VchGSTSumItemWise)
-- ==========================
CREATE TABLE IF NOT EXISTS VchGSTSumItemWise (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    VchCode TEXT NOT NULL REFERENCES Tran1(VchCode),
    SrNo INTEGER NOT NULL,
    HSNCode TEXT,
    TaxRate REAL DEFAULT 0,
    TaxableAmount REAL DEFAULT 0,
    CGST REAL DEFAULT 0,
    SGST REAL DEFAULT 0,
    IGST REAL DEFAULT 0,
    UTGST REAL DEFAULT 0,
    CESS REAL DEFAULT 0,
    TotalTax REAL DEFAULT 0
);

-- ==========================
-- VOUCHER OTHER INFO (Busy jaisa - E-Invoice/E-Way Bill)
-- ==========================
CREATE TABLE IF NOT EXISTS VchOtherInfo (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    VchCode TEXT NOT NULL REFERENCES Tran1(VchCode),
    EInvoiceRequired INTEGER DEFAULT 0,
    EWayBillRequired INTEGER DEFAULT 0,
    EWayBillNo TEXT,
    EWayBillDate TEXT,
    EWayBillExpiry TEXT,
    IRN TEXT,
    AckNo TEXT,
    AckDate TEXT,
    SignedQRCode TEXT,
    PlaceOfSupply TEXT,
    TransportMode TEXT,
    VehicleNo TEXT,
    Distance INTEGER DEFAULT 0
);

-- ==========================
-- GST RETURN INFO
-- ==========================
CREATE TABLE IF NOT EXISTS GSTR1Info (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    GSTIN TEXT,
    ReturnPeriod TEXT,
    FilingDate TEXT,
    Status TEXT,
    JsonData TEXT,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS GSTR3BInfo (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    GSTIN TEXT,
    ReturnPeriod TEXT,
    FilingDate TEXT,
    Status TEXT,
    JsonData TEXT,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ==========================
-- BILLING SETTINGS
-- ==========================
CREATE TABLE IF NOT EXISTS BillingDet (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    VchCode TEXT REFERENCES Tran1(VchCode),
    BillToName TEXT,
    BillToAddress TEXT,
    BillToGSTIN TEXT,
    ShipToName TEXT,
    ShipToAddress TEXT,
    ShipToGSTIN TEXT,
    TransportName TEXT,
    TransportDocNo TEXT,
    TransportDate TEXT
);

-- ==========================
-- AUDIT LOG
-- ==========================
CREATE TABLE IF NOT EXISTS AuditLog (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    UserId INTEGER REFERENCES Users(Id),
    Action TEXT,
    TableName TEXT,
    RecordCode TEXT,
    OldValue TEXT,
    NewValue TEXT,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ==========================
-- APP SETTINGS (server URL, token, last sync time)
-- ==========================
CREATE TABLE IF NOT EXISTS AppSettings (
    Key TEXT PRIMARY KEY,
    Value TEXT
);

-- ==========================
-- SYNC LOG (two-way sync history)
-- ==========================
CREATE TABLE IF NOT EXISTS SyncLog (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    Direction TEXT NOT NULL,
    EntityType TEXT NOT NULL,
    EntityKey TEXT,
    Status TEXT NOT NULL,
    Message TEXT,
    SyncedAt DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ==========================
-- SERVER MASTER LOOKUPS (mirror Laravel tables: units, brands, item_categories, racks, variations)
-- ==========================
CREATE TABLE IF NOT EXISTS Units (
    Id INTEGER PRIMARY KEY,
    UnitName TEXT NOT NULL,
    Description TEXT,
    ServerId INTEGER DEFAULT 0,
    SyncStatus TEXT DEFAULT 'Synced'
);

CREATE TABLE IF NOT EXISTS Brands (
    Id INTEGER PRIMARY KEY,
    Name TEXT NOT NULL,
    Description TEXT,
    ServerId INTEGER DEFAULT 0,
    SyncStatus TEXT DEFAULT 'Synced'
);

CREATE TABLE IF NOT EXISTS ItemCategories (
    Id INTEGER PRIMARY KEY,
    Name TEXT NOT NULL,
    Description TEXT,
    ServerId INTEGER DEFAULT 0,
    SyncStatus TEXT DEFAULT 'Synced',
    del_status TEXT DEFAULT 'Live'
);

CREATE TABLE IF NOT EXISTS Suppliers (
    Id INTEGER PRIMARY KEY,
    Name TEXT NOT NULL,
    ServerId INTEGER DEFAULT 0,
    SyncStatus TEXT DEFAULT 'Synced'
);

CREATE TABLE IF NOT EXISTS Racks (
    Id INTEGER PRIMARY KEY,
    Name TEXT NOT NULL,
    Description TEXT,
    ServerId INTEGER DEFAULT 0,
    SyncStatus TEXT DEFAULT 'Synced'
);

CREATE TABLE IF NOT EXISTS Variations (
    Id INTEGER PRIMARY KEY,
    VariationName TEXT NOT NULL,
    VariationValue TEXT,
    ServerId INTEGER DEFAULT 0,
    SyncStatus TEXT DEFAULT 'Synced'
);

-- ==========================
-- INDICES (Performance)
-- ==========================
CREATE INDEX IF NOT EXISTS idx_tran1_date ON Tran1(VchDate);
CREATE INDEX IF NOT EXISTS idx_tran1_type ON Tran1(VchType);
CREATE INDEX IF NOT EXISTS idx_tran1_master1 ON Tran1(MasterCode1);
CREATE INDEX IF NOT EXISTS idx_tran2_vch ON Tran2(VchCode);
CREATE INDEX IF NOT EXISTS idx_master1_type ON Master1(MasterType);
CREATE INDEX IF NOT EXISTS idx_master1_group ON Master1(ParentGroup);
CREATE INDEX IF NOT EXISTS idx_master1_gstin ON Master1(GSTIN);

-- ═══ PERFORMANCE: NOCASE compound indexes for POS item/customer search ═══
-- COLLATE NOCASE indexes work with LIKE — lower() wala index SCAN nahi kar sakta
CREATE INDEX IF NOT EXISTS idx_items_name_nocase ON items(name COLLATE NOCASE);
CREATE INDEX IF NOT EXISTS idx_items_code_nocase ON items(code COLLATE NOCASE);
CREATE INDEX IF NOT EXISTS idx_items_search_compound ON items(del_status, parent_id, name COLLATE NOCASE);
CREATE INDEX IF NOT EXISTS idx_customers_phone_nocase ON customers(phone COLLATE NOCASE, del_status);
CREATE INDEX IF NOT EXISTS idx_customer_wallets_cid ON customer_wallets(customer_id);

-- ==========================
-- DEFAULT DATA
-- ==========================
-- Default admin user is created by DatabaseService.Initialize() with proper BCrypt hash
-- Auto-generated SQLite mirror of Laravel MySQL schema (off_pos)
-- Types: varchar/text/date/datetime/timestamp -> TEXT, decimal/double -> REAL, int/bigint -> INTEGER, enum -> TEXT
CREATE TABLE IF NOT EXISTS "attendances" ("id" INTEGER, "reference_no" TEXT, "date" TEXT, "employee_id" INTEGER, "in_time" TEXT, "out_time" TEXT, "note" TEXT, "user_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "bookings" ("id" INTEGER, "customer_id" INTEGER, "service_seller_id" INTEGER, "start_date" TEXT, "end_date" TEXT, "status" TEXT, "note" TEXT, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "item_id" INTEGER, "service_note" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "brands" ("id" INTEGER, "name" TEXT, "description" TEXT, "company_id" INTEGER, "user_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "business_club_settings" ("id" INTEGER, "company_name" TEXT, "business_partner_name" TEXT, "address" TEXT, "phone" TEXT, "email" TEXT, "logo" TEXT, "profit_percentage" REAL, "redemption_date" INTEGER, "min_purchase_amount" REAL, "minimum_bill_amount" REAL, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "combo_items" ("id" INTEGER, "combo_item_id" INTEGER, "item_id" INTEGER, "quantity" REAL, "amount" REAL, "total" REAL, "show_in_invoice" INTEGER, "user_id" INTEGER, "company_id" INTEGER, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "combo_sales" ("id" INTEGER, "sale_id" INTEGER, "combo_sale_item_id" INTEGER, "combo_item_id" INTEGER, "combo_item_qty" REAL, "combo_item_price" REAL, "combo_item_seller_id" INTEGER, "show_in_invoice" TEXT, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "companies" ("id" INTEGER, "name" TEXT, "email" TEXT, "phone" TEXT, "address" TEXT, "currency" TEXT, "currency_symbol" TEXT, "timezone" TEXT, "date_format" TEXT, "time_format" TEXT, "fy_start_month" INTEGER, "accounting_method" TEXT, "default_profit_percent" REAL, "logo" TEXT, "del_status" TEXT, "white_label" TEXT, "created_at" TEXT, "updated_at" TEXT, "business_name" TEXT, "short_name" TEXT, "zone_name" TEXT, "website" TEXT, "currency_position" TEXT, "precision" INTEGER, "thousands_separator" TEXT, "decimals_separator" TEXT, "default_customer" INTEGER, "default_payment" INTEGER, "default_cursor_position" TEXT, "product_display" TEXT, "onscreen_keyboard_status" TEXT, "allow_less_sale" TEXT, "direct_cart" TEXT, "grocery_experience" TEXT, "pos_total_payable_type" TEXT, "register_content" TEXT, "inv_logo_is_show" TEXT, "invoice_logo" TEXT, "invoice_footer" TEXT, "term_conditions" TEXT, "letter_head_gap" INTEGER, "letter_footer_gap" INTEGER, "invoice_configuration" TEXT, "collect_tax" TEXT, "tax_is_gst" TEXT, "tax_title" TEXT, "tax_registration_no" TEXT, "tax_setting" TEXT, "tax_string" TEXT, "installment_days" INTEGER, "minimum_point_to_redeem" REAL, "loyalty_rate" REAL, "is_loyalty_enable" TEXT, "e_commerce_checker" TEXT, "product_code_start_from" TEXT, "smtp_type" TEXT, "smtp_enable_status" TEXT, "smtp_details" TEXT, "smtp_default_selected_in_pos" TEXT, "sms_service_provider" TEXT, "sms_enable_status" TEXT, "sms_details" TEXT, "sms_default_selected_in_pos" TEXT, "whatsapp_provider" TEXT, "whatsapp_invoice_enable_status" TEXT, "whatsapp_app_key" TEXT, "whatsapp_authkey" TEXT, "whatsapp_account_sid" TEXT, "whatsapp_auth_token" TEXT, "whatsapp_from_number" TEXT, "whatsapp_default_selected_in_pos" TEXT, "payment_api_setting" TEXT, "payment_settings" TEXT, "zatca_configuration" TEXT, "white_label_status" TEXT, "is_rounding_enable" TEXT, "purchase_price_show_hide" TEXT, "generic_name_search_option" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "counters" ("id" INTEGER, "name" TEXT, "outlet_id" INTEGER, "printer_id" INTEGER, "user_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "customer_receives" ("id" INTEGER, "customer_id" INTEGER, "payment_method_id" INTEGER, "amount" REAL, "date" TEXT, "note" TEXT, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "reference_no" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "customer_wallets" ("id" INTEGER, "customer_id" INTEGER, "company_id" INTEGER, "total_earned" REAL, "balance" REAL, "total_redeemed" REAL, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "customers" ("id" INTEGER, "name" TEXT, "email" TEXT, "phone" TEXT, "address" TEXT, "city" TEXT, "state_id" INTEGER, "postal_code" TEXT, "country" TEXT, "gst_number" TEXT, "opening_balance" REAL, "opening_balance_type" TEXT, "credit_limit" REAL, "description" TEXT, "photo" TEXT, "user_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "loyalty_point" REAL, "dob" TEXT, "anniversary" TEXT, "is_installment_customer" TEXT, "discount" TEXT, "date_of_birth" TEXT, "date_of_anniversary" TEXT, "customer_type" TEXT, "same_or_diff_state" TEXT, "business_type" TEXT, "work_address" TEXT, "guarantor_name" TEXT, "guarantor_mobile" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "damage_details" ("id" INTEGER, "damage_id" INTEGER, "item_id" INTEGER, "date" TEXT, "damage_quantity" REAL, "last_purchase_price" REAL, "loss_amount" REAL, "total_amount" REAL, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "damages" ("id" INTEGER, "reference_no" TEXT, "date" TEXT, "total_loss" REAL, "note" TEXT, "employee_id" INTEGER, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "damage_type" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "delivery_partners" ("id" INTEGER, "name" TEXT, "phone" TEXT, "address" TEXT, "commission_percent" REAL, "user_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "denominations" ("id" INTEGER, "name" TEXT, "value" REAL, "type" TEXT, "user_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "deposit_withdraws" ("id" INTEGER, "reference_no" TEXT, "date" TEXT, "type" TEXT, "payment_method_id" INTEGER, "amount" REAL, "note" TEXT, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "employee_advance_payments" ("id" INTEGER, "reference_no" TEXT, "date" TEXT, "amount" REAL, "note" TEXT, "payment_method_id" INTEGER, "employee_id" INTEGER, "outlet_id" INTEGER, "user_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "expense_categories" ("id" INTEGER, "name" TEXT, "description" TEXT, "user_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "expenses" ("id" INTEGER, "reference_no" TEXT, "date" TEXT, "category_id" INTEGER, "payment_method_id" INTEGER, "amount" REAL, "note" TEXT, "attachment" TEXT, "employee_id" INTEGER, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "fixed_asset_items" ("id" INTEGER, "name" TEXT, "code" TEXT, "description" TEXT, "quantity" REAL, "unit_price" REAL, "total" REAL, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "category" TEXT, "purchase_date" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "fixed_asset_stock_in_details" ("id" INTEGER, "asset_stock_in_id" INTEGER, "item_id" INTEGER, "unit_price" REAL, "total" REAL, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "quantity" REAL, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "fixed_asset_stock_ins" ("id" INTEGER, "reference_no" TEXT, "date" TEXT, "grand_total" REAL, "note" TEXT, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "fixed_asset_stock_out_details" ("id" INTEGER, "asset_stock_out_id" INTEGER, "item_id" INTEGER, "unit_price" REAL, "total" REAL, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "quantity" REAL, "reason" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "fixed_asset_stock_outs" ("id" INTEGER, "reference_no" TEXT, "date" TEXT, "grand_total" REAL, "note" TEXT, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "hold_combo_items" ("id" INTEGER, "sale_id" INTEGER, "combo_sale_item_id" INTEGER, "combo_item_id" INTEGER, "combo_item_qty" REAL, "combo_item_price" REAL, "combo_item_seller_id" INTEGER, "show_in_invoice" TEXT, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "hold_details" ("id" INTEGER, "holds_id" INTEGER, "item_id" INTEGER, "qty" REAL, "menu_price_without_discount" REAL, "menu_price_with_discount" REAL, "menu_unit_price" REAL, "menu_vat_percentage" REAL, "item_tax_amount" REAL, "menu_discount_value" REAL, "discount_amount" REAL, "is_promo_item" TEXT, "promo_parent_id" INTEGER, "item_seller_id" INTEGER, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "discount_type" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "holds" ("id" INTEGER, "invoice_no" TEXT, "sale_date" TEXT, "date_time" TEXT, "due_payment_date" TEXT, "customer_id" INTEGER, "employee_id" INTEGER, "sub_total" REAL, "paid_amount" REAL, "due_amount" REAL, "disc" REAL, "disc_actual" REAL, "vat" REAL, "total_payable" REAL, "total_item_discount_amount" REAL, "sub_total_with_discount" REAL, "sub_total_discount_amount" REAL, "total_discount_amount" REAL, "delivery_charge" REAL, "sub_total_discount_value" REAL, "delivery_partner_id" INTEGER, "note" TEXT, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "counter_id" INTEGER, "sub_total_discount_type" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "income_categories" ("id" INTEGER, "name" TEXT, "description" TEXT, "user_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "incomes" ("id" INTEGER, "reference_no" TEXT, "date" TEXT, "category_id" INTEGER, "payment_method_id" INTEGER, "amount" REAL, "note" TEXT, "attachment" TEXT, "employee_id" INTEGER, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "installment_sale_details" ("id" INTEGER, "installment_sale_id" INTEGER, "payment_date" TEXT, "paid_date" TEXT, "amount" REAL, "paid_amount" REAL, "remaining_amount" REAL, "paid_status" TEXT, "payment_method_id" INTEGER, "note" TEXT, "user_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "installment_sale_payments" ("id" INTEGER, "installment_sale_id" INTEGER, "installment_sale_detail_id" INTEGER, "payment_date" TEXT, "amount" REAL, "payment_type" TEXT, "payment_method_id" INTEGER, "check_issue_date" TEXT, "check_expiry_date" TEXT, "note" TEXT, "user_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "installment_sales" ("id" INTEGER, "reference_no" TEXT, "customer_id" INTEGER, "item_id" INTEGER, "date" TEXT, "price" REAL, "discount_amount" REAL, "percentage_of_interest" REAL, "interest_amount" REAL, "shipping_other" REAL, "total" REAL, "down_payment" REAL, "payment_method_id" INTEGER, "remaining" REAL, "paid_amount" REAL, "due_amount" REAL, "status" TEXT, "installment_count" INTEGER, "note" TEXT, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "discount" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "invoice_hash_chain" ("id" INTEGER, "company_id" INTEGER, "outlet_id" INTEGER, "previous_hash" TEXT, "current_hash" TEXT, "zatca_invoice_id" INTEGER, "chain_index" INTEGER, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "item_categories" ("id" INTEGER, "name" TEXT, "description" TEXT, "sort_id" INTEGER, "company_id" INTEGER, "user_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "items" ("id" INTEGER, "name" TEXT, "code" TEXT, "alternative_name" TEXT, "generic_name" TEXT, "type" TEXT, "expiry_date_maintain" INTEGER, "category_id" INTEGER, "rack_id" INTEGER, "brand_id" INTEGER, "supplier_id" INTEGER, "alert_quantity" REAL, "unit_type" TEXT, "purchase_unit_id" INTEGER, "sale_unit_id" INTEGER, "conversion_rate" REAL, "purchase_price" REAL, "last_three_purchase_avg" REAL, "last_purchase_price" REAL, "sale_price" REAL, "profit_margin" REAL, "whole_sale_price" REAL, "mrp_price" REAL, "description" TEXT, "warranty" TEXT, "warranty_date" TEXT, "guarantee" TEXT, "guarantee_date" TEXT, "photo" TEXT, "tax_information" TEXT, "tax_string" TEXT, "tax_type" TEXT, "applicable_tax_id" INTEGER, "hsn_code" TEXT, "variation_details" TEXT, "enable_disable_status" INTEGER, "parent_id" INTEGER, "loyalty_point" INTEGER, "user_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "stock_quantity" REAL, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "model_has_permissions" ("permission_id" INTEGER, "model_type" TEXT, "model_id" INTEGER, PRIMARY KEY ("permission_id", "model_id", "model_type"));
CREATE TABLE IF NOT EXISTS "model_has_roles" ("role_id" INTEGER, "model_type" TEXT, "model_id" INTEGER, PRIMARY KEY ("role_id", "model_id", "model_type"));
CREATE TABLE IF NOT EXISTS "multiple_currencies" ("id" INTEGER, "name" TEXT, "symbol" TEXT, "exchange_rate" REAL, "is_base" INTEGER, "user_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "outlets" ("id" INTEGER, "name" TEXT, "email" TEXT, "phone" TEXT, "address" TEXT, "state_id" INTEGER, "invoice_scheme_id" INTEGER, "invoice_layout_id" INTEGER, "sale_invoice_layout_id" INTEGER, "is_active" INTEGER, "user_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "outlet_name" TEXT, "outlet_code" TEXT, "active_status" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "password_reset_tokens" ("email" TEXT, "token" TEXT, "created_at" TEXT, PRIMARY KEY ("email"));
CREATE TABLE IF NOT EXISTS "payment_methods" ("id" INTEGER, "name" TEXT, "type" TEXT, "configuration" TEXT, "is_active" INTEGER, "user_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "account_type" TEXT, "status" TEXT, "is_deletable" TEXT, "sort_id" INTEGER, "current_balance" REAL, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "permissions" ("id" INTEGER, "name" TEXT, "group_name" TEXT, "guard_name" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "personal_access_tokens" ("id" INTEGER, "tokenable_type" TEXT, "tokenable_id" INTEGER, "name" TEXT, "token" TEXT, "abilities" TEXT, "last_used_at" TEXT, "expires_at" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "price_list_items" ("id" INTEGER, "price_list_id" INTEGER, "item_id" INTEGER, "price" REAL, "user_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "price_lists" ("id" INTEGER, "name" TEXT, "description" TEXT, "customer_type" TEXT, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "printers" ("id" INTEGER, "name" TEXT, "type" TEXT, "connection_type" TEXT, "ip_address" TEXT, "port" INTEGER, "path" TEXT, "user_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "promotions" ("id" INTEGER, "name" TEXT, "title" TEXT, "type" TEXT, "scheme_basis" TEXT DEFAULT 'item', "discount_type" TEXT, "discount_value" REAL, "start_date" TEXT, "end_date" TEXT, "start_time" TEXT, "end_time" TEXT, "status" TEXT, "user_id" INTEGER, "company_id" INTEGER, "outlet_id" INTEGER, "item_id" INTEGER, "qty" INTEGER, "get_item_id" INTEGER, "get_qty" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "applicable_items" TEXT, "applicable_categories" TEXT, "applicable_customers" TEXT, "applicable_customer_types" TEXT, "min_purchase_amount" REAL, "max_discount_amount" REAL, "bill_level_discount" REAL, "bill_level_discount_type" TEXT, "discount" TEXT, "coupon_code" TEXT, "tier_percentages" TEXT DEFAULT '', PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "purchase_details" ("id" INTEGER, "purchase_id" INTEGER, "item_id" INTEGER, "item_type" TEXT, "expiry_imei_serial" TEXT, "unit_price" REAL, "quantity_amount" REAL, "total" REAL, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "purchase_payments" ("id" INTEGER, "purchase_id" INTEGER, "payment_id" INTEGER, "date" TEXT, "amount" REAL, "outlet_id" INTEGER, "user_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "reference_no" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "purchase_return_details" ("id" INTEGER, "pur_return_id" INTEGER, "item_id" INTEGER, "item_type" TEXT, "expiry_imei_serial" TEXT, "expiry_imei_serial_in" TEXT, "return_note" TEXT, "return_quantity_amount" REAL, "unit_price" REAL, "total" REAL, "return_status" TEXT, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "purchase_returns" ("id" INTEGER, "reference_no" TEXT, "pur_ref_no" TEXT, "supplier_id" INTEGER, "date" TEXT, "purchase_date" TEXT, "return_status" TEXT, "total_return_amount" REAL, "payment_method_id" INTEGER, "payment_method_type" TEXT, "account_type" TEXT, "note" TEXT, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "purchases" ("id" INTEGER, "reference_no" TEXT, "invoice_no" TEXT, "supplier_id" INTEGER, "date" TEXT, "other" REAL, "grand_total" REAL, "paid" REAL, "due_amount" REAL, "note" TEXT, "discount" TEXT, "attachment" TEXT, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "status" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "pwa_settings" ("id" INTEGER, "company_id" INTEGER, "app_name" TEXT, "short_name" TEXT, "theme_color" TEXT, "background_color" TEXT, "logo" TEXT, "start_url" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "quotation_details" ("id" INTEGER, "quotation_id" INTEGER, "item_id" INTEGER, "unit_price" REAL, "total" REAL, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "quotations" ("id" INTEGER, "reference_no" TEXT, "customer_id" INTEGER, "date" TEXT, "grand_total" REAL, "note" TEXT, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "discount" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "racks" ("id" INTEGER, "name" TEXT, "description" TEXT, "company_id" INTEGER, "user_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "registers" ("id" INTEGER, "opening_balance" REAL, "closing_balance" REAL, "sale_paid_amount" REAL, "refund_amount" REAL, "customer_due_receive" REAL, "total_purchase" REAL, "total_downpayment" REAL, "total_installmentcollection" REAL, "total_servicing" REAL, "total_purchase_return" REAL, "total_due_payment" REAL, "total_expense" REAL, "register_status" INTEGER, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "counter_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "role_has_permissions" ("permission_id" INTEGER, "role_id" INTEGER, PRIMARY KEY ("permission_id", "role_id"));
CREATE TABLE IF NOT EXISTS "roles" ("id" INTEGER, "name" TEXT, "guard_name" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "salaries" ("id" INTEGER, "reference_no" TEXT, "year" INTEGER, "month" INTEGER, "generated_date" TEXT, "total_amount" REAL, "user_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "salary_items" ("id" INTEGER, "salary_id" INTEGER, "employee_id" INTEGER, "salary_amount" REAL, "overtime_rate" REAL, "overtime_hour" REAL, "additional_amount" REAL, "deduction_amount" REAL, "absent_day" INTEGER, "absent_day_amount" REAL, "tips" REAL, "advance_taken" REAL, "net_salary" REAL, "note" TEXT, "user_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "salary_payments" ("id" INTEGER, "salary_id" INTEGER, "payment_method_id" INTEGER, "amount" REAL, "user_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "sale_details" ("id" INTEGER, "sales_id" INTEGER, "item_id" INTEGER, "qty" REAL, "menu_price_without_discount" REAL, "menu_price_with_discount" REAL, "menu_unit_price" REAL, "purchase_price" REAL, "menu_vat_percentage" REAL, "item_tax_amount" REAL, "menu_discount_value" REAL, "discount_amount" REAL, "loyalty_point_earn" REAL, "is_promo_item" TEXT, "promo_parent_id" INTEGER, "item_seller_id" INTEGER, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "discount_type" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "sale_payments" ("id" INTEGER, "sale_id" INTEGER, "payment_id" INTEGER, "date" TEXT, "amount" REAL, "multi_currency" TEXT, "multi_currency_rate" REAL, "usage_point" REAL, "note" TEXT, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "reference_no" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "sale_return_details" ("id" INTEGER, "sale_return_id" INTEGER, "sale_id" INTEGER, "item_id" INTEGER, "sale_quantity_amount" REAL, "return_quantity_amount" REAL, "unit_price_in_sale" REAL, "unit_price_in_return" REAL, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "sale_returns" ("id" INTEGER, "reference_no" TEXT, "sale_id" INTEGER, "customer_id" INTEGER, "date" TEXT, "total_return_amount" REAL, "paid" REAL, "due" REAL, "payment_method_id" INTEGER, "note" TEXT, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "sales" ("id" INTEGER, "invoice_no" TEXT, "sale_no" TEXT, "sale_date" TEXT, "date_time" TEXT, "order_time" TEXT, "due_date" TEXT, "due_date_time" TEXT, "order_date_time" TEXT, "close_time" TEXT, "customer_id" INTEGER, "employee_id" INTEGER, "sub_total" REAL, "given_amount" REAL, "paid_amount" REAL, "change_amount" REAL, "previous_due" REAL, "due_amount" REAL, "disc" REAL, "disc_actual" REAL, "vat" REAL, "rounding" REAL, "total_payable" REAL, "total_item_discount_amount" REAL, "sub_total_with_discount" REAL, "sub_total_discount_amount" REAL, "total_discount_amount" REAL, "delivery_charge" REAL, "sub_total_discount_value" REAL, "grand_total" REAL, "sale_vat_objects" TEXT, "delivery_partner_id" INTEGER, "note" TEXT, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "counter_id" INTEGER, "table_no" TEXT, "booking_id" INTEGER, "sub_total_discount_type" TEXT, "delivery_status" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "servicings" ("id" INTEGER, "reference_no" TEXT, "customer_id" INTEGER, "employee_id" INTEGER, "date" TEXT, "receiving_date" TEXT, "delivery_date" TEXT, "servicing_charge" REAL, "paid_amount" REAL, "due_amount" REAL, "payment_method_id" INTEGER, "description" TEXT, "note" TEXT, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "current_status" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "set_opening_stocks" ("id" INTEGER, "item_id" INTEGER, "item_type" TEXT, "item_description" TEXT, "stock_quantity" REAL, "outlet_id" INTEGER, "user_id" INTEGER, "company_id" INTEGER, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "states" ("id" INTEGER, "state_code" TEXT, "state_name" TEXT, "type" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "supplier_payments" ("id" INTEGER, "supplier_id" INTEGER, "payment_method_id" INTEGER, "amount" REAL, "date" TEXT, "note" TEXT, "user_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "reference_no" TEXT, "outlet_id" INTEGER, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "suppliers" ("id" INTEGER, "name" TEXT, "company_name" TEXT, "email" TEXT, "phone" TEXT, "address" TEXT, "city" TEXT, "state" TEXT, "postal_code" TEXT, "country" TEXT, "gst_number" TEXT, "payment_method_id" INTEGER, "opening_balance" REAL, "opening_balance_type" TEXT, "credit_limit" REAL, "description" TEXT, "photo" TEXT, "user_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "vat_number" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "taxs" ("id" INTEGER, "tax_name" TEXT, "tax_rate" REAL, "parent_tax_id" INTEGER, "show_in_item_profile" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "time_zones" ("id" INTEGER, "name" TEXT, "created_at" TEXT, "updated_at" TEXT, "country_code" TEXT, "zone_name" TEXT, "del_status" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "transfer_details" ("id" INTEGER, "transfer_id" INTEGER, "item_id" INTEGER, "quantity" REAL, "unit_price" REAL, "total" REAL, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "transfers" ("id" INTEGER, "reference_no" TEXT, "date" TEXT, "from_outlet_id" INTEGER, "to_outlet_id" INTEGER, "note" TEXT, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "status" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "units" ("id" INTEGER, "unit_name" TEXT, "description" TEXT, "company_id" INTEGER, "user_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "users" ("id" INTEGER, "name" TEXT, "email" TEXT, "password" TEXT, "role" TEXT, "phone" TEXT, "salary" REAL, "commission" REAL, "outlet_id" TEXT, "will_login" TEXT, "del_status" TEXT, "photo" TEXT, "discount_permission_code" TEXT, "discount_amt" REAL, "start_date" TEXT, "end_date" TEXT, "company_id" INTEGER, "two_factor_enabled" INTEGER, "session_timeout" INTEGER, "login_notifications" INTEGER, "question" TEXT, "answer" TEXT, "remember_token" TEXT, "email_verified_at" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "variations" ("id" INTEGER, "variation_name" TEXT, "variation_value" TEXT, "user_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "view_stock_detail" ("item_id" INTEGER, "type" INTEGER, "stock_quantity" REAL, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT);
CREATE TABLE IF NOT EXISTS "wallet_transactions" ("id" INTEGER, "wallet_id" INTEGER, "customer_id" INTEGER, "sale_id" INTEGER, "type" TEXT, "amount" REAL, "balance_before" REAL, "balance_after" REAL, "description" TEXT, "transaction_date" TEXT, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "warranties" ("id" INTEGER, "reference_no" TEXT, "customer_id" INTEGER, "technician_id" INTEGER, "receiving_date" TEXT, "delivery_date" TEXT, "current_status" TEXT, "description" TEXT, "note" TEXT, "user_id" INTEGER, "outlet_id" INTEGER, "company_id" INTEGER, "del_status" TEXT, "created_at" TEXT, "updated_at" TEXT, "item_name" TEXT, "item_model" TEXT, "serial_no" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "zatca_invoices" ("id" INTEGER, "sale_id" INTEGER, "company_id" INTEGER, "outlet_id" INTEGER, "invoice_type" TEXT, "uuid" TEXT, "invoice_hash" TEXT, "previous_invoice_hash" TEXT, "qr_code" TEXT, "zatca_status" TEXT, "zatca_error" TEXT, "cleared_at" TEXT, "reported_at" TEXT, "failed_at" TEXT, "retry_count" INTEGER, "last_retry_at" TEXT, "ubl_xml" TEXT, "signed_xml" TEXT, "cryptographic_stamp" TEXT, "packaging_authorized_serial_number" TEXT, "is_offline" INTEGER, "queued_at" TEXT, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "zatca_requests" ("id" INTEGER, "zatca_invoice_id" INTEGER, "company_id" INTEGER, "request_type" TEXT, "request_payload" TEXT, "request_url" TEXT, "request_method" TEXT, "request_headers" TEXT, "response_status_code" INTEGER, "response_body" TEXT, "response_headers" TEXT, "status" TEXT, "error_message" TEXT, "error_code" TEXT, "requested_at" TEXT, "responded_at" TEXT, "response_time_ms" INTEGER, "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "employees" ("id" INTEGER PRIMARY KEY, "name" TEXT, "email" TEXT, "password" TEXT, "role" TEXT, "phone" TEXT, "photo" TEXT, "salary" REAL, "commission" REAL, "outlet_id" TEXT, "will_login" TEXT, "start_date" TEXT, "end_date" TEXT, "email_verified_at" TEXT, "remember_token" TEXT, "question" TEXT, "answer" TEXT, "discount_permission_code" TEXT, "discount_amt" REAL, "login_notifications" INTEGER, "session_timeout" INTEGER, "two_factor_enabled" INTEGER, "del_status" TEXT, "company_id" INTEGER, "user_id" INTEGER, "created_at" TEXT, "updated_at" TEXT);

CREATE TABLE IF NOT EXISTS "business_club_members" ("id" INTEGER, "member_id" TEXT, "customer_id" INTEGER, "company_id" INTEGER, "membership_amount" REAL DEFAULT 0, "locked_balance" REAL DEFAULT 0, "earned_balance" REAL DEFAULT 0, "total_earned" REAL DEFAULT 0, "total_redeemed" REAL DEFAULT 0, "status" TEXT DEFAULT 'active', "joined_at" TEXT, "del_status" TEXT DEFAULT 'Live', "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));
CREATE TABLE IF NOT EXISTS "business_club_transactions" ("id" INTEGER, "member_id" TEXT, "customer_id" INTEGER, "sale_id" INTEGER, "type" TEXT, "amount" REAL DEFAULT 0, "balance_before" REAL DEFAULT 0, "balance_after" REAL DEFAULT 0, "description" TEXT, "transaction_date" TEXT, "company_id" INTEGER, "del_status" TEXT DEFAULT 'Live', "created_at" TEXT, "updated_at" TEXT, PRIMARY KEY ("id"));

CREATE TABLE IF NOT EXISTS "FeatureActivations" (
    "Id" INTEGER PRIMARY KEY AUTOINCREMENT,
    "FeatureKey" TEXT NOT NULL UNIQUE,
    "FeatureName" TEXT NOT NULL,
    "FeatureGroup" TEXT DEFAULT 'general',
    "IsActive" INTEGER DEFAULT 1,
    "UpdatedAt" TEXT
);
