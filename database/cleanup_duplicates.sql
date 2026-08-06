-- ============================================================================
-- RashanKiDukan Cloud (MySQL) — Duplicate Cleanup Script
-- ----------------------------------------------------------------------------
-- Ye script un duplicates ko soft-delete (del_status='Deleted') karta hai jo
-- purane sync bug (Id-only matching / retry-duplicate) ki wajah se server par
-- ban gaye the. Soft-delete = RECOVERABLE — koi row hard-delete nahi hoti.
--
-- PEHLE BACKUP LO (zaroori):
--   mysqldump -u root -p off_pos > off_pos_backup_$(date +%F).sql
--
-- FIR migration chalao (device_id column ke liye):
--   php artisan migrate
--
-- FIR ye script ek baar chalao (phpMyAdmin ya mysql CLI):
--   mysql -u root -p off_pos < database/cleanup_duplicates.sql
--
-- Note: naya sync system (device_id mapping + idempotent retry + natural-key
-- dedupe) laga de to ye duplicates dobara kabhi nahi banenge.
-- ============================================================================

-- ── 1) items: duplicate code (keep MIN(id) — usko 'Live' rakho) ─────────────
UPDATE items i
JOIN (
    SELECT code, company_id, MIN(id) AS keep_id
    FROM items
    WHERE del_status = 'Live' AND code IS NOT NULL AND code <> ''
    GROUP BY code, company_id
    HAVING COUNT(*) > 1
) d ON i.code = d.code AND i.company_id = d.company_id
SET i.del_status = 'Deleted', i.updated_at = NOW()
WHERE i.id <> d.keep_id;

-- ── 2) brands: duplicate name ───────────────────────────────────────────────
UPDATE brands b
JOIN (
    SELECT name, company_id, MIN(id) AS keep_id
    FROM brands
    WHERE del_status = 'Live' AND name IS NOT NULL AND name <> ''
    GROUP BY name, company_id
    HAVING COUNT(*) > 1
) d ON b.name = d.name AND b.company_id = d.company_id
SET b.del_status = 'Deleted', b.updated_at = NOW()
WHERE b.id <> d.keep_id;

-- ── 3) item_categories: duplicate name ──────────────────────────────────────
UPDATE item_categories c
JOIN (
    SELECT name, company_id, MIN(id) AS keep_id
    FROM item_categories
    WHERE del_status = 'Live' AND name IS NOT NULL AND name <> ''
    GROUP BY name, company_id
    HAVING COUNT(*) > 1
) d ON c.name = d.name AND c.company_id = d.company_id
SET c.del_status = 'Deleted', c.updated_at = NOW()
WHERE c.id <> d.keep_id;

-- ── 4) units: duplicate unit_name ───────────────────────────────────────────
UPDATE units u
JOIN (
    SELECT unit_name, company_id, MIN(id) AS keep_id
    FROM units
    WHERE del_status = 'Live' AND unit_name IS NOT NULL AND unit_name <> ''
    GROUP BY unit_name, company_id
    HAVING COUNT(*) > 1
) d ON u.unit_name = d.unit_name AND u.company_id = d.company_id
SET u.del_status = 'Deleted', u.updated_at = NOW()
WHERE u.id <> d.keep_id;

-- ── 5) customers: duplicate phone (same company) ────────────────────────────
UPDATE customers c
JOIN (
    SELECT phone, company_id, MIN(id) AS keep_id
    FROM customers
    WHERE del_status = 'Live' AND phone IS NOT NULL AND phone <> ''
    GROUP BY phone, company_id
    HAVING COUNT(*) > 1
) d ON c.phone = d.phone AND c.company_id = d.company_id
SET c.del_status = 'Deleted', c.updated_at = NOW()
WHERE c.id <> d.keep_id;

-- ── 6) sales: duplicate invoice_no (renumber bug se bache) ──────────────────
UPDATE sales s
JOIN (
    SELECT invoice_no, company_id, MIN(id) AS keep_id
    FROM sales
    WHERE del_status = 'Live' AND invoice_no IS NOT NULL AND invoice_no <> ''
    GROUP BY invoice_no, company_id
    HAVING COUNT(*) > 1
) d ON s.invoice_no = d.invoice_no AND s.company_id = d.company_id
SET s.del_status = 'Deleted', s.updated_at = NOW()
WHERE s.id <> d.keep_id;

-- ── 7) purchases / purchase_returns / supplier_payments / expenses:
--        duplicate reference_no (jahan column exist karta hai) ───────────────
UPDATE purchases p
JOIN (
    SELECT reference_no, company_id, MIN(id) AS keep_id
    FROM purchases
    WHERE del_status = 'Live' AND reference_no IS NOT NULL AND reference_no <> ''
    GROUP BY reference_no, company_id
    HAVING COUNT(*) > 1
) d ON p.reference_no = d.reference_no AND p.company_id = d.company_id
SET p.del_status = 'Deleted', p.updated_at = NOW()
WHERE p.id <> d.keep_id;

UPDATE purchase_returns pr
JOIN (
    SELECT reference_no, company_id, MIN(id) AS keep_id
    FROM purchase_returns
    WHERE del_status = 'Live' AND reference_no IS NOT NULL AND reference_no <> ''
    GROUP BY reference_no, company_id
    HAVING COUNT(*) > 1
) d ON pr.reference_no = d.reference_no AND pr.company_id = d.company_id
SET pr.del_status = 'Deleted', pr.updated_at = NOW()
WHERE pr.id <> d.keep_id;

UPDATE supplier_payments sp
JOIN (
    SELECT reference_no, company_id, MIN(id) AS keep_id
    FROM supplier_payments
    WHERE del_status = 'Live' AND reference_no IS NOT NULL AND reference_no <> ''
    GROUP BY reference_no, company_id
    HAVING COUNT(*) > 1
) d ON sp.reference_no = d.reference_no AND sp.company_id = d.company_id
SET sp.del_status = 'Deleted', sp.updated_at = NOW()
WHERE sp.id <> d.keep_id;

UPDATE expenses e
JOIN (
    SELECT reference_no, company_id, MIN(id) AS keep_id
    FROM expenses
    WHERE del_status = 'Live' AND reference_no IS NOT NULL AND reference_no <> ''
    GROUP BY reference_no, company_id
    HAVING COUNT(*) > 1
) d ON e.reference_no = d.reference_no AND e.company_id = d.company_id
SET e.del_status = 'Deleted', e.updated_at = NOW()
WHERE e.id <> d.keep_id;

-- ============================================================================
-- Verify (chala ke dekho — koi 'Live' duplicate bacha ho to):
--   SELECT code, company_id, COUNT(*) FROM items
--     WHERE del_status='Live' GROUP BY code, company_id HAVING COUNT(*) > 1;
-- ============================================================================
