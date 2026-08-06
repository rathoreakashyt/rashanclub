SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ============================================================
-- PART 10: Damages, Transfers, Registers, Quotations,
--          Sale Returns, Purchase Returns, Warranties,
--          Servicings, Bookings, Promotions, PWA
-- ============================================================

-- Damages
INSERT INTO `damages` (`reference_no`,`damage_type`,`date`,`total_loss`,`note`,`employee_id`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('DMG-0001','Expired','2026-07-15',900.000,'Salt packets damaged due to moisture',4,1,1,1,'Live',NOW(),NOW()),
('DMG-0002','Physical','2026-07-18',750.000,'Oil bottles cracked during storage',4,1,1,1,'Live',NOW(),NOW());

INSERT INTO `damage_details` (`damage_id`,`item_id`,`date`,`damage_quantity`,`last_purchase_price`,`loss_amount`,`total_amount`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(1,4,'2026-07-15',50.000,18.000,18.000,900.000,1,1,1,'Live',NOW(),NOW()),
(2,3,'2026-07-18', 5.000,130.000,150.000,750.000,1,1,1,'Live',NOW(),NOW());

-- Sale Return
INSERT INTO `sale_returns` (`reference_no`,`sale_id`,`customer_id`,`date`,`total_return_amount`,`paid`,`due`,`payment_method_id`,`note`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('SRT-0001',3,3,'2026-07-12',60.000,60.000,0.000,1,'MDH masala returned - quality issue',1,1,1,'Live',NOW(),NOW());

INSERT INTO `sale_return_details` (`sale_return_id`,`sale_id`,`item_id`,`sale_quantity_amount`,`return_quantity_amount`,`unit_price_in_sale`,`unit_price_in_return`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(1,3,5,1.000,1.000,60.000,60.000,1,1,1,'Live',NOW(),NOW());

-- Purchase Return
INSERT INTO `purchase_returns` (`reference_no`,`pur_ref_no`,`supplier_id`,`date`,`purchase_date`,`return_status`,`total_return_amount`,`payment_method_id`,`payment_method_type`,`note`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('PRR-0001','PUR-0003',3,'2026-07-09','2026-07-05','Returned',900.000,1,'Cash','50 kg salt damaged batch returned',1,1,1,'Live',NOW(),NOW());

INSERT INTO `purchase_return_details` (`pur_return_id`,`item_id`,`item_type`,`return_quantity_amount`,`unit_price`,`total`,`return_status`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(1,4,'Single',50.000,18.000,900.000,'Returned',1,1,1,'Live',NOW(),NOW());

-- Quotation
INSERT INTO `quotations` (`reference_no`,`customer_id`,`date`,`grand_total`,`discount`,`note`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('QUO-0001',10,'2026-07-14',3990.000,'0','Bulk order quotation for Deepak Jain',1,1,1,'Live',NOW(),NOW());

INSERT INTO `quotation_details` (`quotation_id`,`item_id`,`unit_price`,`total`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(1,1,380.000,3800.000,1,1,1,'Live',NOW(),NOW()),
(1,4,22.000, 190.000,1,1,1,'Live',NOW(),NOW());

-- Register (POS cash drawer)
INSERT INTO `registers` (`opening_balance`,`closing_balance`,`sale_paid_amount`,`refund_amount`,`customer_due_receive`,`total_purchase`,`total_downpayment`,`total_installmentcollection`,`total_servicing`,`total_purchase_return`,`total_due_payment`,`total_expense`,`register_status`,`user_id`,`outlet_id`,`company_id`,`counter_id`,`del_status`,`created_at`,`updated_at`) VALUES
(5000.00,10904.00,5904.00,0.00,500.00,0.00,0.00,0.00,0.00,0.00,0.00,800.00,2,1,1,1,1,'Live','2026-07-10 09:00:00','2026-07-10 20:00:00'),
(5000.00, 8000.00,3000.00,0.00,  0.00,0.00,0.00,0.00,0.00,0.00,0.00,  0.00,2,2,1,1,1,'Live','2026-07-11 09:00:00','2026-07-11 20:00:00'),
(5000.00,    0.00,   0.00,0.00,  0.00,0.00,0.00,0.00,0.00,0.00,0.00,  0.00,1,1,1,1,1,'Live','2026-07-17 09:00:00','2026-07-17 09:00:00');

-- Warranty
INSERT INTO `warranties` (`reference_no`,`customer_id`,`item_name`,`item_model`,`serial_no`,`technician_id`,`receiving_date`,`delivery_date`,`current_status`,`description`,`note`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('WRN-0001',2,'Weighing Scale','DS-500','WS20240012',4,'2026-07-08','2026-07-10','Delivered','Digital weighing scale repair','Calibration done',1,1,1,'Live',NOW(),NOW());

-- Servicing
INSERT INTO `servicings` (`reference_no`,`customer_id`,`employee_id`,`date`,`receiving_date`,`delivery_date`,`servicing_charge`,`paid_amount`,`due_amount`,`payment_method_id`,`current_status`,`description`,`note`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('SVC-0001',5,4,'2026-07-09','2026-07-09','2026-07-11',500.00,500.00,0.00,1,'Delivered','Refrigerator servicing','Cooling issue fixed',1,1,1,'Live',NOW(),NOW());

-- Booking
INSERT INTO `bookings` (`customer_id`,`item_id`,`service_seller_id`,`start_date`,`end_date`,`status`,`note`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(7,NULL,4,'2026-07-20 10:00:00','2026-07-20 11:00:00','Confirmed','Home delivery booking',1,1,1,'Live',NOW(),NOW());

-- Promotion
INSERT INTO `promotions` (`name`,`type`,`discount_type`,`discount_value`,`discount`,`start_date`,`end_date`,`status`,`user_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('Monsoon Sale 10%','1','percentage',10.000,'10%','2026-07-15','2026-07-31','Active',1,1,'Live',NOW(),NOW()),
('Weekend Flat 50 Off','1','flat',50.000,'50','2026-07-19','2026-07-20','Inactive',1,1,'Live',NOW(),NOW());

-- Installment Sale for Mohan Lal
INSERT INTO `installment_sales` (`reference_no`,`customer_id`,`item_id`,`date`,`price`,`discount_amount`,`percentage_of_interest`,`interest_amount`,`shipping_other`,`total`,`down_payment`,`remaining`,`paid_amount`,`due_amount`,`status`,`installment_count`,`note`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('INST-0001',12,2,'2026-07-16',499.000,0.000,5.000,24.950,0.000,523.950,100.000,423.950,100.000,423.950,'Active',3,'India Gate Rice installment',1,1,1,'Live',NOW(),NOW());

INSERT INTO `installment_sale_details` (`installment_sale_id`,`payment_date`,`paid_date`,`amount`,`paid_amount`,`remaining_amount`,`paid_status`,`payment_method_id`,`user_id`,`del_status`,`created_at`,`updated_at`) VALUES
(1,'2026-08-16',NULL,141.317,0.000,141.317,'Unpaid',NULL,1,'Live',NOW(),NOW()),
(1,'2026-09-16',NULL,141.317,0.000,141.317,'Unpaid',NULL,1,'Live',NOW(),NOW()),
(1,'2026-10-16',NULL,141.316,0.000,141.316,'Unpaid',NULL,1,'Live',NOW(),NOW());

INSERT INTO `installment_sale_payments` (`installment_sale_id`,`installment_sale_detail_id`,`payment_date`,`amount`,`payment_type`,`payment_method_id`,`user_id`,`del_status`,`created_at`,`updated_at`) VALUES
(1,NULL,'2026-07-16',100.000,'Down_Payment',1,1,'Live',NOW(),NOW());

-- PWA Setting
INSERT INTO `pwa_settings` (`company_id`,`app_name`,`short_name`,`theme_color`,`background_color`,`logo`,`start_url`,`created_at`,`updated_at`) VALUES
(1,'Rashan Ki Dukan','RKD','#2E7D32','#FFFFFF',NULL,'/',NOW(),NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- Transfer (between outlets - just 1 outlet so self record)
INSERT INTO `transfers` (`reference_no`,`date`,`from_outlet_id`,`to_outlet_id`,`status`,`note`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('TRF-0001','2026-07-16',1,1,'Completed','Internal stock adjustment',1,1,1,'Live',NOW(),NOW());

INSERT INTO `transfer_details` (`transfer_id`,`item_id`,`quantity`,`unit_price`,`total`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(1,4,20.000,18.000,360.000,1,1,1,'Live',NOW(),NOW());

-- Fixed Asset
INSERT INTO `fixed_asset_items` (`name`,`code`,`category`,`description`,`quantity`,`unit_price`,`total`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('Electronic Weighing Scale','FA-001','Equipment','Digital weighing scale 30 kg capacity',1.000,3500.000,3500.000,1,1,1,'Live',NOW(),NOW()),
('CCTV Camera Set','FA-002','Security','4 camera CCTV system',1.000,8000.000,8000.000,1,1,1,'Live',NOW(),NOW()),
('Air Conditioner 1.5 Ton','FA-003','Appliance','Split AC for shop',1.000,32000.000,32000.000,1,1,1,'Live',NOW(),NOW());

INSERT INTO `fixed_asset_stock_ins` (`reference_no`,`date`,`grand_total`,`note`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('FASI-0001','2026-07-01',43500.000,'Initial asset stock in',1,1,1,'Live',NOW(),NOW());

INSERT INTO `fixed_asset_stock_in_details` (`asset_stock_in_id`,`item_id`,`quantity`,`unit_price`,`total`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(1,1,1.000,3500.000, 3500.000,1,1,1,'Live',NOW(),NOW()),
(1,2,1.000,8000.000, 8000.000,1,1,1,'Live',NOW(),NOW()),
(1,3,1.000,32000.000,32000.000,1,1,1,'Live',NOW(),NOW());

SELECT 'Part 10 Done - All Remaining Tables Populated' AS status;
SET FOREIGN_KEY_CHECKS = 1;
