-- ============================================================
-- rashankidukan MySQL Init Script
-- Runs automatically when MySQL container first starts
-- ============================================================

-- Create the database (installer may also create it, this is fallback)
CREATE DATABASE IF NOT EXISTS `off_pos`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Grant full privileges to pos_user (installer needs CREATE TABLE, etc.)
GRANT ALL PRIVILEGES ON `off_pos`.* TO 'pos_user'@'%';

-- Also allow pos_user to create new databases (in case user enters different DB name during install)
GRANT CREATE ON *.* TO 'pos_user'@'%';

-- Root user accessible from any host (for debugging)
ALTER USER 'root'@'%' IDENTIFIED WITH mysql_native_password BY 'rootpassword';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;

FLUSH PRIVILEGES;
