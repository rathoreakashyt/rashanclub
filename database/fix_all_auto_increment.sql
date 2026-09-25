-- ═══════════════════════════════════════════════════════════════
--  FIX: Add PRIMARY KEY + AUTO_INCREMENT to all tables needing it
--  Run this ONE TIME on production phpMyAdmin
--
--  This script is SAFE:
--   • Skips tables that already have a PRIMARY KEY
--   • Skips columns that already AUTO_INCREMENT
--   • Only touches `id` columns of type bigint / int
--   • Adds PRIMARY KEY BEFORE applying AUTO_INCREMENT (fixes #1075)
-- ═══════════════════════════════════════════════════════════════

DROP PROCEDURE IF EXISTS rkd_fix_keys;

DELIMITER $$
CREATE PROCEDURE rkd_fix_keys()
BEGIN
    -- Step 1: Fix tables where `id` has no PRIMARY KEY at all
    --         → add PRIMARY KEY (`id`) + AUTO_INCREMENT
    BLOCK1: BEGIN
        DECLARE done1 INT DEFAULT FALSE;
        DECLARE tbl VARCHAR(64);
        DECLARE cur1 CURSOR FOR
            SELECT c.TABLE_NAME
            FROM information_schema.COLUMNS c
            LEFT JOIN information_schema.TABLE_CONSTRAINTS tc
                ON tc.TABLE_SCHEMA = c.TABLE_SCHEMA
               AND tc.TABLE_NAME = c.TABLE_NAME
               AND tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
            WHERE c.TABLE_SCHEMA = DATABASE()
              AND c.COLUMN_NAME = 'id'
              AND c.DATA_TYPE IN ('bigint','int')
              AND tc.CONSTRAINT_NAME IS NULL;
        DECLARE CONTINUE HANDLER FOR NOT FOUND SET done1 = TRUE;

        OPEN cur1;
        fix1: LOOP
            FETCH cur1 INTO tbl;
            IF done1 THEN LEAVE fix1; END IF;

            SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD PRIMARY KEY (`id`)');
            PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

            SET @s = CONCAT('ALTER TABLE `', tbl, '` MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT');
            PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
        END LOOP;
        CLOSE cur1;
    END BLOCK1;

    -- Step 2: Fix tables where `id` IS the PRIMARY KEY but missing AUTO_INCREMENT
    BLOCK2: BEGIN
        DECLARE done2 INT DEFAULT FALSE;
        DECLARE tbl VARCHAR(64);
        DECLARE cur2 CURSOR FOR
            SELECT c.TABLE_NAME
            FROM information_schema.COLUMNS c
            WHERE c.TABLE_SCHEMA = DATABASE()
              AND c.COLUMN_NAME = 'id'
              AND c.COLUMN_KEY = 'PRI'
              AND c.DATA_TYPE IN ('bigint','int')
              AND c.EXTRA NOT LIKE '%auto_increment%';
        DECLARE CONTINUE HANDLER FOR NOT FOUND SET done2 = TRUE;

        OPEN cur2;
        fix2: LOOP
            FETCH cur2 INTO tbl;
            IF done2 THEN LEAVE fix2; END IF;

            SET @s = CONCAT('ALTER TABLE `', tbl, '` MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT');
            PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
        END LOOP;
        CLOSE cur2;
    END BLOCK2;
END$$
DELIMITER ;

CALL rkd_fix_keys();
DROP PROCEDURE IF EXISTS rkd_fix_keys;

-- ═══════════════════════════════════════════════════════════════
--  All done. Test your login / "Save & Sync" again.
-- ═══════════════════════════════════════════════════════════════