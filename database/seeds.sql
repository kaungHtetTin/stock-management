-- =============================================================================
-- Stock Management System — Seed Data (Phase 0)
-- Run AFTER schema.sql: mysql -u root -p stock_manage < database/seeds.sql
-- =============================================================================

USE stock_manage;

-- -----------------------------------------------------------------------------
-- Default admin user
-- Username : admin
-- Password : password
-- (Change password after first login in production)
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

-- -----------------------------------------------------------------------------
-- Default staff user (for testing Phase 1+)
-- Username : staff1
-- Password : password
-- -----------------------------------------------------------------------------
INSERT INTO users (username, password_hash, display_name, role, status)
VALUES (
    'staff1',
    '$2y$10$0yaT.D..byZmH4H3ZliLUOtk04SCHKaLFQj/2G9n.d3FPmGFr3Psm',
    'Staff-1',
    'staff',
    'active'
)
ON DUPLICATE KEY UPDATE
    password_hash = VALUES(password_hash),
    display_name  = VALUES(display_name),
    role          = VALUES(role),
    status        = VALUES(status);

-- -----------------------------------------------------------------------------
-- Default item categories
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO categories (name, sort_order) VALUES
    ('Fruits', 1),
    ('Gelato', 2),
    ('Icecream', 3);
