SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- Purchase 1: Aata + Rice from Agarwal (fully paid)
INSERT INTO `purchases` (`reference_no`,`invoice_no`,`supplier_id`,`date`,`other`,`grand_total`,`paid`,`due_amount`,`status`,`note`,`discount`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('PUR-0001','INV-AG-001',1,'2026-07-01',0.000,20400.000,20400.000,0.000,'Received','July batch - atta and rice','0',1,1,1,'Live',NOW(),NOW());
INSERT INTO `purchase_details` (`purchase_id`,`item_id`,`item_type`,`unit_price`,`quantity_amount`,`total`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(1,1,'Single',320.000,30.000,9600.000,1,1,1,'Live',NOW(),NOW()),
(1,2,'Single',420.000,26.000,10920.000,1,1,1,'Live',NOW(),NOW());
INSERT INTO `purchase_payments` (`purchase_id`,`payment_id`,`date`,`amount`,`outlet_id`,`user_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(1,1,'2026-07-01',20400.000,1,1,1,'Live',NOW(),NOW());

-- Purchase 2: Oil from Sharma (partial payment)
INSERT INTO `purchases` (`reference_no`,`invoice_no`,`supplier_id`,`date`,`other`,`grand_total`,`paid`,`due_amount`,`status`,`note`,`discount`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('PUR-0002','INV-SH-011',2,'2026-07-03',0.000,9100.000,5000.000,4100.000,'Pending','Oil stock July','0',1,1,1,'Live',NOW(),NOW());
INSERT INTO `purchase_details` (`purchase_id`,`item_id`,`item_type`,`unit_price`,`quantity_amount`,`total`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(2,3,'Single',130.000,70.000,9100.000,1,1,1,'Live',NOW(),NOW());
INSERT INTO `purchase_payments` (`purchase_id`,`payment_id`,`date`,`amount`,`outlet_id`,`user_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(2,1,'2026-07-03',5000.000,1,1,1,'Live',NOW(),NOW());

-- Purchase 3: Salt + Masala from Gupta (fully paid)
INSERT INTO `purchases` (`reference_no`,`invoice_no`,`supplier_id`,`date`,`other`,`grand_total`,`paid`,`due_amount`,`status`,`note`,`discount`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('PUR-0003','INV-GU-021',3,'2026-07-05',0.000,14400.000,14400.000,0.000,'Received','Salt 300kg + Masala 200pkt','0',1,1,1,'Live',NOW(),NOW());
INSERT INTO `purchase_details` (`purchase_id`,`item_id`,`item_type`,`unit_price`,`quantity_amount`,`total`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(3,4,'Single',18.000,300.000,5400.000,1,1,1,'Live',NOW(),NOW()),
(3,5,'Single',45.000,200.000,9000.000,1,1,1,'Live',NOW(),NOW());
INSERT INTO `purchase_payments` (`purchase_id`,`payment_id`,`date`,`amount`,`outlet_id`,`user_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(3,2,'2026-07-05',14400.000,1,1,1,'Live',NOW(),NOW());

-- Purchase 4: Aata second batch (due pending)
INSERT INTO `purchases` (`reference_no`,`invoice_no`,`supplier_id`,`date`,`other`,`grand_total`,`paid`,`due_amount`,`status`,`note`,`discount`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('PUR-0004','INV-AG-002',1,'2026-07-12',200.000,16200.000,10000.000,6200.000,'Pending','Second batch atta + transport','0',1,1,1,'Live',NOW(),NOW());
INSERT INTO `purchase_details` (`purchase_id`,`item_id`,`item_type`,`unit_price`,`quantity_amount`,`total`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(4,1,'Single',320.000,50.000,16000.000,1,1,1,'Live',NOW(),NOW());
INSERT INTO `purchase_payments` (`purchase_id`,`payment_id`,`date`,`amount`,`outlet_id`,`user_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(4,1,'2026-07-12',10000.000,1,1,1,'Live',NOW(),NOW());

-- Purchase 5: Rice + Oil restock (fully paid)
INSERT INTO `purchases` (`reference_no`,`invoice_no`,`supplier_id`,`date`,`other`,`grand_total`,`paid`,`due_amount`,`status`,`note`,`discount`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('PUR-0005','INV-SH-012',2,'2026-07-15',0.000,8450.000,8450.000,0.000,'Received','Oil restock mid-July','0',1,1,1,'Live',NOW(),NOW());
INSERT INTO `purchase_details` (`purchase_id`,`item_id`,`item_type`,`unit_price`,`quantity_amount`,`total`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(5,3,'Single',130.000,65.000,8450.000,1,1,1,'Live',NOW(),NOW());
INSERT INTO `purchase_payments` (`purchase_id`,`payment_id`,`date`,`amount`,`outlet_id`,`user_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(5,1,'2026-07-15',8450.000,1,1,1,'Live',NOW(),NOW());

-- Direct supplier payments (extra payments to clear dues)
INSERT INTO `supplier_payments` (`supplier_id`,`payment_method_id`,`amount`,`date`,`note`,`outlet_id`,`user_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(2,2,4100.000,'2026-07-10','Remaining oil payment via bank',1,1,1,'Live',NOW(),NOW()),
(1,1,3000.000,'2026-07-16','Partial payment for PUR-0004',1,1,1,'Live',NOW(),NOW());

SELECT 'Part 7 Done - 5 Purchases + Payments' AS status;
SET FOREIGN_KEY_CHECKS = 1;
