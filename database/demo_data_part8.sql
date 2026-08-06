SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- Expenses (category: 1=Rent, 2=Electricity, 3=Salary, 4=Transport, 5=Misc)
-- payment_method: 1=Cash, 2=Bank
INSERT INTO `expenses` (`reference_no`,`date`,`category_id`,`payment_method_id`,`amount`,`note`,`employee_id`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('EXP-0001','2026-07-01',1,2,15000.000,'Monthly shop rent - July 2026',NULL,1,1,1,'Live',NOW(),NOW()),
('EXP-0002','2026-07-05',2,2, 2800.000,'Electricity bill - June 2026',NULL,1,1,1,'Live',NOW(),NOW()),
('EXP-0003','2026-07-10',4,1,  800.000,'Transport for purchase delivery',NULL,1,1,1,'Live',NOW(),NOW()),
('EXP-0004','2026-07-15',5,1,  500.000,'Stationery and packaging material',NULL,1,1,1,'Live',NOW(),NOW()),
('EXP-0005','2026-07-31',3,2,30000.000,'Staff salary - July 2026',NULL,1,1,1,'Live',NOW(),NOW());

-- Incomes
-- (category: 1=Product Sales already through sales, 2=Late Fee, 3=Other)
INSERT INTO `incomes` (`reference_no`,`date`,`category_id`,`payment_method_id`,`amount`,`note`,`employee_id`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('INC-0001','2026-07-08', 2,1,  200.000,'Late payment fee from Vikram Malhotra',NULL,1,1,1,'Live',NOW(),NOW()),
('INC-0002','2026-07-14', 3,1,  500.000,'Old packaging material sold',NULL,1,1,1,'Live',NOW(),NOW()),
('INC-0003','2026-07-20', 3,2, 1200.000,'Scrap sale proceeds',NULL,1,1,1,'Live',NOW(),NOW());

-- Deposit/Withdraw from payment accounts
INSERT INTO `deposit_withdraws` (`reference_no`,`date`,`type`,`payment_method_id`,`amount`,`note`,`user_id`,`outlet_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('DW-0001','2026-07-01','Deposit',1,50000.000,'Opening cash deposit - counter',1,1,1,'Live',NOW(),NOW()),
('DW-0002','2026-07-05','Deposit',2,20000.000,'Bank deposit from sales',1,1,1,'Live',NOW(),NOW()),
('DW-0003','2026-07-15','Withdraw',1, 5000.000,'Petty cash withdrawal',1,1,1,'Live',NOW(),NOW());

-- Attendance for employees
INSERT INTO `attendances` (`reference_no`,`date`,`employee_id`,`in_time`,`out_time`,`note`,`user_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('ATT-0001','2026-07-10',2,'09:00:00','18:00:00','Regular day',1,1,'Live',NOW(),NOW()),
('ATT-0002','2026-07-10',3,'09:30:00','18:00:00','Regular day',1,1,'Live',NOW(),NOW()),
('ATT-0003','2026-07-10',4,'09:00:00','17:30:00','Regular day',1,1,'Live',NOW(),NOW()),
('ATT-0004','2026-07-11',2,'09:00:00','18:00:00','Regular day',1,1,'Live',NOW(),NOW()),
('ATT-0005','2026-07-11',3,'09:15:00','18:00:00','Regular day',1,1,'Live',NOW(),NOW()),
('ATT-0006','2026-07-12',2,'09:00:00','18:00:00','Regular day',1,1,'Live',NOW(),NOW()),
('ATT-0007','2026-07-12',4,'09:00:00','17:00:00','Left early',1,1,'Live',NOW(),NOW()),
('ATT-0008','2026-07-13',2,'09:00:00','18:00:00','Regular day',1,1,'Live',NOW(),NOW()),
('ATT-0009','2026-07-13',3,'09:30:00','18:30:00','Overtime',1,1,'Live',NOW(),NOW()),
('ATT-0010','2026-07-14',4,'09:00:00','18:00:00','Regular day',1,1,'Live',NOW(),NOW());

-- Salary (July 2026)
INSERT INTO `salaries` (`reference_no`,`year`,`month`,`generated_date`,`total_amount`,`user_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('SAL-2026-07',2026,7,'2026-07-31',55000.000,1,1,'Live',NOW(),NOW());

INSERT INTO `salary_items` (`salary_id`,`employee_id`,`salary_amount`,`overtime_rate`,`overtime_hour`,`additional_amount`,`deduction_amount`,`absent_day`,`absent_day_amount`,`tips`,`advance_taken`,`net_salary`,`note`,`user_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(1,2,18000.000,0.000,0.000,500.000,0.000,0,0.000,0.000,0.000,18500.000,'July salary - Manager',1,1,'Live',NOW(),NOW()),
(1,3,12000.000,0.000,2.000,0.000,0.000,0,0.000,200.000,0.000,12200.000,'July salary - Cashier (2h OT)',1,1,'Live',NOW(),NOW()),
(1,4,15000.000,0.000,0.000,0.000,200.000,1,600.000,0.000,0.000,14200.000,'July salary - 1 day absent',1,1,'Live',NOW(),NOW());

INSERT INTO `salary_payments` (`salary_id`,`payment_method_id`,`amount`,`user_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
(1,2,55000.000,1,1,'Live',NOW(),NOW());

-- Employee advance payment
INSERT INTO `employee_advance_payments` (`reference_no`,`date`,`amount`,`note`,`payment_method_id`,`employee_id`,`outlet_id`,`user_id`,`company_id`,`del_status`,`created_at`,`updated_at`) VALUES
('ADV-0001','2026-07-20',3000.000,'Advance for medical emergency',1,3,1,1,1,'Live',NOW(),NOW());

SELECT 'Part 9 Done - Expenses, Incomes, Attendance, Salary, Accounting' AS status;
SET FOREIGN_KEY_CHECKS = 1;
