-- =============================================================================
-- Stock Management System — Seed Data
-- Run AFTER schema.sql: php database/migrate.php install
--                  or: mysql -u root stock_manage < database/seeds.sql
-- =============================================================================

USE stock_manage;

-- -----------------------------------------------------------------------------
-- Default users
-- Password for both: password
-- -----------------------------------------------------------------------------
INSERT INTO users (username, password_hash, display_name, role, status)
VALUES (
    'admin',
    '$2y$10$0yaT.D..byZmH4H3ZliLUOtk04SCHKaLFQj/2G9n.d3FPmGFr3Psm',
    'Admin User',
    'admin',
    'active'
)
ON DUPLICATE KEY UPDATE
    password_hash = VALUES(password_hash),
    display_name  = VALUES(display_name),
    role          = VALUES(role),
    status        = VALUES(status);

INSERT INTO users (username, password_hash, display_name, role, status)
VALUES (
    'staff1',
    '$2y$10$0yaT.D..byZmH4H3ZliLUOtk04SCHKaLFQj/2G9n.d3FPmGFr3Psm',
    'Staff One',
    'staff',
    'active'
)
ON DUPLICATE KEY UPDATE
    password_hash = VALUES(password_hash),
    display_name  = VALUES(display_name),
    role          = VALUES(role),
    status        = VALUES(status);

-- -----------------------------------------------------------------------------
-- Item categories (required before items.category_id FK)
-- -----------------------------------------------------------------------------
INSERT INTO categories (name, sort_order, is_active)
VALUES
    ('Fruits', 1, 1),
    ('Gelato', 2, 1),
    ('Icecream', 3, 1)
ON DUPLICATE KEY UPDATE
    sort_order = VALUES(sort_order),
    is_active  = VALUES(is_active);

-- -----------------------------------------------------------------------------
-- Sample customers (phone + remark columns)
-- -----------------------------------------------------------------------------
INSERT INTO customers (customer_code, customer_name, contact_person, phone, address, remark, customer_type, created_by)
SELECT 'CUS-001', 'Downtown Retail Shop', 'U Aung Kyaw', '09123456789', 'Yangon', 'Regular retail buyer', 'Retail', u.id
FROM users u WHERE u.username = 'admin' LIMIT 1
ON DUPLICATE KEY UPDATE
    customer_name  = VALUES(customer_name),
    contact_person = VALUES(contact_person),
    phone          = VALUES(phone),
    address        = VALUES(address),
    remark         = VALUES(remark),
    customer_type  = VALUES(customer_type);

INSERT INTO customers (customer_code, customer_name, contact_person, phone, address, remark, customer_type, created_by)
SELECT 'CUS-002', 'Golden Wholesale Trading', 'Daw Hnin', '09987654321', 'Mandalay', 'Monthly bulk orders', 'Whole Sale', u.id
FROM users u WHERE u.username = 'admin' LIMIT 1
ON DUPLICATE KEY UPDATE
    customer_name  = VALUES(customer_name),
    contact_person = VALUES(contact_person),
    phone          = VALUES(phone),
    address        = VALUES(address),
    remark         = VALUES(remark),
    customer_type  = VALUES(customer_type);

-- -----------------------------------------------------------------------------
-- Sample items (category_id FK — not category ENUM)
-- -----------------------------------------------------------------------------
INSERT INTO items (item_no, item_name, unit, unit_price, category_id, remark, created_by)
SELECT 'ITM-F001', 'Fresh Mango', 'kg', 3500.00, c.id, 'Seasonal fruit', u.id
FROM categories c, users u
WHERE c.name = 'Fruits' AND u.username = 'admin'
LIMIT 1
ON DUPLICATE KEY UPDATE
    item_name   = VALUES(item_name),
    unit        = VALUES(unit),
    unit_price  = VALUES(unit_price),
    category_id = VALUES(category_id),
    remark      = VALUES(remark);

INSERT INTO items (item_no, item_name, unit, unit_price, category_id, remark, created_by)
SELECT 'ITM-F002', 'Strawberry', 'kg', 4200.00, c.id, 'Imported', u.id
FROM categories c, users u
WHERE c.name = 'Fruits' AND u.username = 'admin'
LIMIT 1
ON DUPLICATE KEY UPDATE
    item_name   = VALUES(item_name),
    unit        = VALUES(unit),
    unit_price  = VALUES(unit_price),
    category_id = VALUES(category_id),
    remark      = VALUES(remark);

INSERT INTO items (item_no, item_name, unit, unit_price, category_id, remark, created_by)
SELECT 'ITM-G001', 'Vanilla Gelato', 'tub', 8500.00, c.id, '2L tub', u.id
FROM categories c, users u
WHERE c.name = 'Gelato' AND u.username = 'admin'
LIMIT 1
ON DUPLICATE KEY UPDATE
    item_name   = VALUES(item_name),
    unit        = VALUES(unit),
    unit_price  = VALUES(unit_price),
    category_id = VALUES(category_id),
    remark      = VALUES(remark);

INSERT INTO items (item_no, item_name, unit, unit_price, category_id, remark, created_by)
SELECT 'ITM-I001', 'Chocolate Icecream', 'pcs', 1200.00, c.id, 'Retail pack', u.id
FROM categories c, users u
WHERE c.name = 'Icecream' AND u.username = 'admin'
LIMIT 1
ON DUPLICATE KEY UPDATE
    item_name   = VALUES(item_name),
    unit        = VALUES(unit),
    unit_price  = VALUES(unit_price),
    category_id = VALUES(category_id),
    remark      = VALUES(remark);

-- -----------------------------------------------------------------------------
-- Opening stock (approved Stock In — for demo balance on Balance page)
-- -----------------------------------------------------------------------------
INSERT INTO stock_in (
    item_id, mfd_date, expire_date, lot_no, qty, unit, worker_qty,
    in_charge_name, status, created_by, approved_by, approved_at
)
SELECT i.id, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 'LOT-OPEN-001', 100.00, i.unit, NULL,
       'Warehouse Team', 'approved', u.id, u.id, NOW()
FROM items i, users u
WHERE i.item_no = 'ITM-F001' AND u.username = 'admin'
  AND NOT EXISTS (SELECT 1 FROM stock_in si WHERE si.item_id = i.id AND si.lot_no = 'LOT-OPEN-001');

INSERT INTO stock_in (
    item_id, mfd_date, expire_date, lot_no, qty, unit, worker_qty,
    in_charge_name, status, created_by, approved_by, approved_at
)
SELECT i.id, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 MONTH), 'LOT-OPEN-002', 50.00, i.unit, NULL,
       'Warehouse Team', 'approved', u.id, u.id, NOW()
FROM items i, users u
WHERE i.item_no = 'ITM-G001' AND u.username = 'admin'
  AND NOT EXISTS (SELECT 1 FROM stock_in si WHERE si.item_id = i.id AND si.lot_no = 'LOT-OPEN-002');

INSERT INTO stock_in (
    item_id, mfd_date, expire_date, lot_no, qty, unit, worker_qty,
    in_charge_name, status, created_by, approved_by, approved_at
)
SELECT i.id, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 4 MONTH), 'LOT-OPEN-003', 80.00, i.unit, NULL,
       'Warehouse Team', 'approved', u.id, u.id, NOW()
FROM items i, users u
WHERE i.item_no = 'ITM-I001' AND u.username = 'admin'
  AND NOT EXISTS (SELECT 1 FROM stock_in si WHERE si.item_id = i.id AND si.lot_no = 'LOT-OPEN-003');
