SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- Walk-in Customer (default)
INSERT INTO `customers` (`name`,`phone`,`email`,`address`,`city`,`state_id`,`country`,`gst_number`,`opening_balance`,`opening_balance_type`,`credit_limit`,`loyalty_point`,`is_installment_customer`,`business_type`,`user_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('Walk-in Customer',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.000,'Debit',0.000,0.00,'No','B2C',1,1,'Live',NOW(),NOW());

-- Update default customer
UPDATE `companies` SET `default_customer` = LAST_INSERT_ID() WHERE `id` = 1;

-- Regular customers
INSERT INTO `customers` (`name`,`phone`,`email`,`address`,`city`,`state_id`,`country`,`gst_number`,`opening_balance`,`opening_balance_type`,`credit_limit`,`loyalty_point`,`dob`,`anniversary`,`is_installment_customer`,`business_type`,`user_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('Ramesh Sharma',   '+91-9811001100','ramesh.sharma@gmail.com',  'A-12, Laxmi Nagar',     'New Delhi',7,'India',NULL,   500.000,'Debit', 5000.000, 0.00,'1985-03-15','2010-11-20','No','B2C',1,1,'Live',NOW(),NOW()),
('Sunita Devi',     '+91-9822002200','sunita.devi@gmail.com',    'B-45, Preet Vihar',     'New Delhi',7,'India',NULL,     0.000,'Debit', 3000.000, 0.00,'1990-07-22',NULL,        'No','B2C',1,1,'Live',NOW(),NOW()),
('Vikram Malhotra', '+91-9833003300','vikram.m@gmail.com',       'C-8, Shakarpur',        'New Delhi',7,'India',NULL,  1000.000,'Debit', 8000.000, 0.00,'1978-12-05','2005-02-14','No','B2C',1,1,'Live',NOW(),NOW()),
('Anita Kapoor',    '+91-9844004400','anita.kapoor@gmail.com',   'D-23, Patparganj',      'New Delhi',7,'India',NULL,     0.000,'Debit', 2000.000, 0.00,'1992-05-18',NULL,        'No','B2C',1,1,'Live',NOW(),NOW()),
('Suresh Gupta',    '+91-9855005500','suresh.gupta@gmail.com',   'E-67, Mayur Vihar',     'New Delhi',7,'India',NULL,  2000.000,'Debit',10000.000, 0.00,'1980-09-30','2008-06-12','No','B2C',1,1,'Live',NOW(),NOW()),
('Pooja Verma',     '+91-9866006600','pooja.verma@gmail.com',    'F-11, Ghazipur',        'New Delhi',7,'India',NULL,     0.000,'Debit', 1500.000, 0.00,'1995-01-25',NULL,        'No','B2C',1,1,'Live',NOW(),NOW()),
('Rajiv Khanna',    '+91-9877007700','rajiv.khanna@gmail.com',   'G-90, Geeta Colony',    'New Delhi',7,'India',NULL,   500.000,'Credit',5000.000, 0.00,'1975-08-10','2003-04-20','No','B2C',1,1,'Live',NOW(),NOW()),
('Meena Agarwal',   '+91-9888008800','meena.agarwal@gmail.com',  'H-34, Krishna Nagar',   'New Delhi',7,'India',NULL,     0.000,'Debit', 3000.000, 0.00,'1988-11-14',NULL,        'No','B2C',1,1,'Live',NOW(),NOW()),
('Deepak Jain',     '+91-9899009900','deepak.jain@gmail.com',    'I-56, Vivek Vihar',     'New Delhi',7,'India','07AAADJ1234D1ZX',0.000,'Debit',20000.000,0.00,'1982-04-08','2007-12-25','No','B2B',1,1,'Live',NOW(),NOW()),
('Kavita Mishra',   '+91-9800100200','kavita.mishra@gmail.com',  'J-78, Mandawali',       'New Delhi',7,'India',NULL,  1500.000,'Debit', 4000.000, 0.00,'1993-06-30',NULL,        'No','B2C',1,1,'Live',NOW(),NOW());

-- Installment customer
INSERT INTO `customers` (`name`,`phone`,`email`,`address`,`city`,`state_id`,`country`,`opening_balance`,`opening_balance_type`,`credit_limit`,`loyalty_point`,`is_installment_customer`,`business_type`,`user_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('Mohan Lal Sahu','+91-9900200300','mohan.sahu@gmail.com','K-15, Loni Road','Ghaziabad',7,'India',0.000,'Debit',50000.000,0.00,'Yes','B2C',1,1,'Live',NOW(),NOW());

SELECT 'Part 5 Done - Customers (Walk-in + 10 regular + 1 installment)' AS status;
SET FOREIGN_KEY_CHECKS = 1;
