SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ============================================================
-- PART 8: Sales Transactions
-- customer: 1=Walk-in, 2=Ramesh, 3=Sunita, 4=Vikram, 5=Anita, 6=Suresh
-- items: 1=Aata380, 2=Rice499, 3=Oil155, 4=Salt22, 5=Masala60
-- payment: 1=Cash, 2=Bank, 3=UPI
-- ============================================================

-- Sale 1: Walk-in customer - cash (Aata + Salt)
INSERT INTO `sales` (`invoice_no`,`sale_date`,`date_time`,`customer_id`,`employee_id`,`sub_total`,`given_amount`,`paid_amount`,`change_amount`,`previous_due`,`due_amount`,`disc`,`disc_actual`,`vat`,`rounding`,`total_payable`,`total_item_discount_amount`,`sub_total_with_discount`,`sub_total_discount_amount`,`total_discount_amount`,`delivery_charge`,`sub_total_discount_value`,`grand_total`,`note`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('INV-0001','2026-07-10','2026-07-10 10:15:00',1,1,798.000,800.000,798.000,2.000,0.000,0.000,0.000,0.000,0.000,0.000,798.000,0.000,798.000,0.000,0.000,0.000,0.000,798.000,NULL,1,1,1,'Live',NOW(),NOW());
INSERT INTO `sale_details` (`sales_id`,`item_id`,`qty`,`menu_price_without_discount`,`menu_price_with_discount`,`menu_unit_price`,`purchase_price`,`menu_vat_percentage`,`item_tax_amount`,`menu_discount_value`,`discount_amount`,`loyalty_point_earn`,`is_promo_item`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(1,1,2.000,380.000,380.000,380.000,320.000,0.00,0.000,0.000,0.000,0.000,'No',1,1,1,'Live',NOW(),NOW()),
(1,4,1.000,22.000,22.000,22.000,18.000,0.00,0.000,0.000,0.000,0.000,'No',1,1,1,'Live',NOW(),NOW());
INSERT INTO `sale_payments` (`sale_id`,`payment_id`,`date`,`amount`,`multi_currency`,`multi_currency_rate`,`usage_point`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(1,1,'2026-07-10',798.000,'No',0.0000,0.000,1,1,1,'Live',NOW(),NOW());

-- Sale 2: Ramesh - UPI (Rice + Oil)
INSERT INTO `sales` (`invoice_no`,`sale_date`,`date_time`,`customer_id`,`employee_id`,`sub_total`,`given_amount`,`paid_amount`,`change_amount`,`previous_due`,`due_amount`,`disc`,`disc_actual`,`vat`,`rounding`,`total_payable`,`total_item_discount_amount`,`sub_total_with_discount`,`sub_total_discount_amount`,`total_discount_amount`,`delivery_charge`,`sub_total_discount_value`,`grand_total`,`note`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('INV-0002','2026-07-10','2026-07-10 11:30:00',2,1,654.000,654.000,654.000,0.000,0.000,0.000,0.000,0.000,0.000,0.000,654.000,0.000,654.000,0.000,0.000,0.000,0.000,654.000,NULL,1,1,1,'Live',NOW(),NOW());
INSERT INTO `sale_details` (`sales_id`,`item_id`,`qty`,`menu_price_without_discount`,`menu_price_with_discount`,`menu_unit_price`,`purchase_price`,`menu_vat_percentage`,`item_tax_amount`,`menu_discount_value`,`discount_amount`,`loyalty_point_earn`,`is_promo_item`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(2,2,1.000,499.000,499.000,499.000,420.000,0.00,0.000,0.000,0.000,0.000,'No',1,1,1,'Live',NOW(),NOW()),
(2,3,1.000,155.000,155.000,155.000,130.000,0.00,0.000,0.000,0.000,0.000,'No',1,1,1,'Live',NOW(),NOW());
INSERT INTO `sale_payments` (`sale_id`,`payment_id`,`date`,`amount`,`multi_currency`,`multi_currency_rate`,`usage_point`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(2,3,'2026-07-10',654.000,'No',0.0000,0.000,1,1,1,'Live',NOW(),NOW());

-- Sale 3: Sunita - cash (Masala + Salt x2)
INSERT INTO `sales` (`invoice_no`,`sale_date`,`date_time`,`customer_id`,`employee_id`,`sub_total`,`given_amount`,`paid_amount`,`change_amount`,`previous_due`,`due_amount`,`disc`,`disc_actual`,`vat`,`rounding`,`total_payable`,`total_item_discount_amount`,`sub_total_with_discount`,`sub_total_discount_amount`,`total_discount_amount`,`delivery_charge`,`sub_total_discount_value`,`grand_total`,`note`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('INV-0003','2026-07-11','2026-07-11 09:45:00',3,2,104.000,110.000,104.000,6.000,0.000,0.000,0.000,0.000,0.000,0.000,104.000,0.000,104.000,0.000,0.000,0.000,0.000,104.000,NULL,1,1,1,'Live',NOW(),NOW());
INSERT INTO `sale_details` (`sales_id`,`item_id`,`qty`,`menu_price_without_discount`,`menu_price_with_discount`,`menu_unit_price`,`purchase_price`,`menu_vat_percentage`,`item_tax_amount`,`menu_discount_value`,`discount_amount`,`loyalty_point_earn`,`is_promo_item`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(3,5,1.000,60.000,60.000,60.000,45.000,0.00,0.000,0.000,0.000,0.000,'No',1,1,1,'Live',NOW(),NOW()),
(3,4,2.000,22.000,22.000,22.000,18.000,0.00,0.000,0.000,0.000,0.000,'No',1,1,1,'Live',NOW(),NOW());
INSERT INTO `sale_payments` (`sale_id`,`payment_id`,`date`,`amount`,`multi_currency`,`multi_currency_rate`,`usage_point`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(3,1,'2026-07-11',104.000,'No',0.0000,0.000,1,1,1,'Live',NOW(),NOW());

-- Sale 4: Vikram - due sale (Rice + Aata, partial payment)
INSERT INTO `sales` (`invoice_no`,`sale_date`,`date_time`,`customer_id`,`employee_id`,`sub_total`,`given_amount`,`paid_amount`,`change_amount`,`previous_due`,`due_amount`,`disc`,`disc_actual`,`vat`,`rounding`,`total_payable`,`total_item_discount_amount`,`sub_total_with_discount`,`sub_total_discount_amount`,`total_discount_amount`,`delivery_charge`,`sub_total_discount_value`,`grand_total`,`note`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('INV-0004','2026-07-12','2026-07-12 14:20:00',4,1,1258.000,500.000,500.000,0.000,0.000,758.000,0.000,0.000,0.000,0.000,1258.000,0.000,1258.000,0.000,0.000,0.000,0.000,1258.000,'Credit sale',1,1,1,'Live',NOW(),NOW());
INSERT INTO `sale_details` (`sales_id`,`item_id`,`qty`,`menu_price_without_discount`,`menu_price_with_discount`,`menu_unit_price`,`purchase_price`,`menu_vat_percentage`,`item_tax_amount`,`menu_discount_value`,`discount_amount`,`loyalty_point_earn`,`is_promo_item`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(4,2,1.000,499.000,499.000,499.000,420.000,0.00,0.000,0.000,0.000,0.000,'No',1,1,1,'Live',NOW(),NOW()),
(4,1,2.000,380.000,380.000,380.000,320.000,0.00,0.000,0.000,0.000,0.000,'No',1,1,1,'Live',NOW(),NOW());
INSERT INTO `sale_payments` (`sale_id`,`payment_id`,`date`,`amount`,`multi_currency`,`multi_currency_rate`,`usage_point`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(4,1,'2026-07-12',500.000,'No',0.0000,0.000,1,1,1,'Live',NOW(),NOW());

-- Sale 5: Suresh - Bank transfer (bulk purchase)
INSERT INTO `sales` (`invoice_no`,`sale_date`,`date_time`,`customer_id`,`employee_id`,`sub_total`,`given_amount`,`paid_amount`,`change_amount`,`previous_due`,`due_amount`,`disc`,`disc_actual`,`vat`,`rounding`,`total_payable`,`total_item_discount_amount`,`sub_total_with_discount`,`sub_total_discount_amount`,`total_discount_amount`,`delivery_charge`,`sub_total_discount_value`,`grand_total`,`note`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('INV-0005','2026-07-13','2026-07-13 16:00:00',6,1,2090.000,2090.000,2090.000,0.000,0.000,0.000,0.000,0.000,0.000,0.000,2090.000,0.000,2090.000,0.000,0.000,0.000,0.000,2090.000,'Bulk order',1,1,1,'Live',NOW(),NOW());
INSERT INTO `sale_details` (`sales_id`,`item_id`,`qty`,`menu_price_without_discount`,`menu_price_with_discount`,`menu_unit_price`,`purchase_price`,`menu_vat_percentage`,`item_tax_amount`,`menu_discount_value`,`discount_amount`,`loyalty_point_earn`,`is_promo_item`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(5,1,2.000,380.000,380.000,380.000,320.000,0.00,0.000,0.000,0.000,0.000,'No',1,1,1,'Live',NOW(),NOW()),
(5,3,4.000,155.000,155.000,155.000,130.000,0.00,0.000,0.000,0.000,0.000,'No',1,1,1,'Live',NOW(),NOW()),
(5,5,5.000,60.000,60.000,60.000,45.000,0.00,0.000,0.000,0.000,0.000,'No',1,1,1,'Live',NOW(),NOW());
INSERT INTO `sale_payments` (`sale_id`,`payment_id`,`date`,`amount`,`multi_currency`,`multi_currency_rate`,`usage_point`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(5,2,'2026-07-13',2090.000,'No',0.0000,0.000,1,1,1,'Live',NOW(),NOW());

-- Due collection from Vikram (customer receive)
INSERT INTO `customer_receives` (`customer_id`,`payment_method_id`,`amount`,`date`,`note`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(4,1,500.000,'2026-07-14','Partial due payment for INV-0004',1,1,1,'Live',NOW(),NOW());

SELECT 'Part 8 Done - 5 Sales + Customer Receive' AS status;
SET FOREIGN_KEY_CHECKS = 1;
