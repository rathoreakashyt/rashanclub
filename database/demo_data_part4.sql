SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- 5 Rashan Products
INSERT INTO `items` (`name`,`code`,`alternative_name`,`generic_name`,`type`,`expiry_date_maintain`,`category_id`,`rack_id`,`brand_id`,`supplier_id`,`alert_quantity`,`stock_quantity`,`unit_type`,`purchase_unit_id`,`sale_unit_id`,`conversion_rate`,`purchase_price`,`last_purchase_price`,`mrp_price`,`sale_price`,`profit_margin`,`whole_sale_price`,`description`,`tax_information`,`tax_string`,`tax_type`,`applicable_tax_id`,`hsn_code`,`enable_disable_status`,`loyalty_point`,`user_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('Aashirvaad Aata 10 Kg','000001','Aashirvaad Wheat Flour','Wheat Flour','Single',0,1,1,1,1,5.000,50.000,'Single',1,1,1.000,320.000,320.000,0.000,380.000,18.75,360.000,'Aashirvaad whole wheat chakki atta 10 kg','[{"id":"4","tax":"GST 5%","tax_rate":5}]','GST 5%:','Exclusive',4,'1101',1,0,1,1,'Live',NOW(),NOW()),
('India Gate Basmati Rice 5 Kg','000002','Basmati Rice 5 Kg','Basmati Rice','Single',0,2,1,8,1,5.000,40.000,'Single',1,1,1.000,420.000,420.000,0.000,499.000,18.81,475.000,'India Gate Classic Basmati Rice 5 kg','[{"id":"4","tax":"GST 5%","tax_rate":5}]','GST 5%:','Exclusive',4,'1006',1,0,1,1,'Live',NOW(),NOW()),
('Fortune Sunflower Oil 1 Litre','000003','Sunflower Oil 1L','Refined Sunflower Oil','Single',0,3,2,3,2,10.000,60.000,'Single',3,3,1.000,130.000,130.000,0.000,155.000,19.23,148.000,'Fortune refined sunflower oil 1 litre','[{"id":"6","tax":"GST 18%","tax_rate":18}]','GST 18%:','Exclusive',6,'1512',1,0,1,1,'Live',NOW(),NOW()),
('Tata Salt 1 Kg','000004','Tata Namak 1 Kg','Iodised Salt','Single',0,5,3,2,3,20.000,120.000,'Single',1,1,1.000,18.000,18.000,0.000,22.000,22.22,21.000,'Tata Salt iodised 1 kg','[]','','None',NULL,'2501',1,0,1,1,'Live',NOW(),NOW()),
('MDH Garam Masala 100g','000005','MDH Masala 100g','Garam Masala','Single',0,4,3,4,3,10.000,80.000,'Single',2,2,1.000,45.000,45.000,0.000,60.000,33.33,57.000,'MDH Garam Masala 100g pack','[{"id":"6","tax":"GST 18%","tax_rate":18}]','GST 18%:','Exclusive',6,'0910',1,0,1,1,'Live',NOW(),NOW());

-- Opening Stock
INSERT INTO `set_opening_stocks` (`item_id`,`item_type`,`item_description`,`stock_quantity`,`outlet_id`,`user_id`,`company_id`,`created_at`,`updated_at`) VALUES
(1,'Single','Aashirvaad Aata 10 Kg',50.000,1,1,1,NOW(),NOW()),
(2,'Single','India Gate Basmati Rice 5 Kg',40.000,1,1,1,NOW(),NOW()),
(3,'Single','Fortune Sunflower Oil 1 Litre',60.000,1,1,1,NOW(),NOW()),
(4,'Single','Tata Salt 1 Kg',120.000,1,1,1,NOW(),NOW()),
(5,'Single','MDH Garam Masala 100g',80.000,1,1,1,NOW(),NOW());

SELECT 'Part 4 Done - 5 Products + Opening Stock' AS status;
SET FOREIGN_KEY_CHECKS = 1;
