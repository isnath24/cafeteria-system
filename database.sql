-- ============================================================
-- Cafeteria Pre-Order System — Database Schema
-- Run this script in phpMyAdmin or MySQL CLI
-- ============================================================

CREATE DATABASE IF NOT EXISTS cafeteria_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cafeteria_db;

-- ============================================================
-- 1. USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150)    NOT NULL,
    email       VARCHAR(150)    NOT NULL UNIQUE,
    password    VARCHAR(255)    NOT NULL,
    student_id  VARCHAR(50)     DEFAULT NULL,
    phone       VARCHAR(20)     DEFAULT NULL,
    role        ENUM('admin','student','kitchen') NOT NULL DEFAULT 'student',
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default accounts (password = "password" for all three)
INSERT INTO users (name, email, password, role) VALUES
('Admin User',    'admin@uwu.ac.lk',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Kitchen Staff', 'kitchen@uwu.ac.lk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kitchen'),
('John Student',  'student@uwu.ac.lk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student');

-- ============================================================
-- 2. FOOD ITEMS
-- ============================================================
CREATE TABLE IF NOT EXISTS food_items (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    food_name           VARCHAR(150)    NOT NULL,
    description         TEXT            DEFAULT NULL,
    price               DECIMAL(10,2)   NOT NULL,
    category            VARCHAR(100)    NOT NULL,
    image               VARCHAR(255)    DEFAULT 'default_food.jpg',
    availability_status ENUM('Available','Unavailable') NOT NULL DEFAULT 'Available',
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed food items (categories match current_meal_period())
INSERT INTO food_items (food_name, description, price, category, image, availability_status) VALUES
('Rice & Curry',   'Traditional rice with curry',           150.00, 'Lunch,Dinner',    'rice.jpg',           'Available'),
('Kottu',          'Spicy kottu roti',                      300.00, 'Lunch,Dinner',    'kottu.jpg',          'Available'),
('Fried Rice',     'Egg fried rice',                        290.00, 'Lunch,Dinner',    'fried-rice.jpg',     'Available'),
('String Hoppers', 'Soft string hoppers with coconut milk', 180.00, 'Breakfast',       'string-hoppers.jpg', 'Available'),
('Noodles',        'Stir-fried noodles',                    200.00, 'Dinner',          'noodles.jpg',        'Available'),
('Egg Curry',      'Boiled egg in spicy curry',             200.00, 'Breakfast,Lunch', 'egg-curry.jpg',      'Available');

-- ============================================================
-- 3. INVENTORY
-- ============================================================
CREATE TABLE IF NOT EXISTS inventory (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    food_item_id    INT             NOT NULL,
    quantity        INT             NOT NULL DEFAULT 0,
    low_stock_alert INT             NOT NULL DEFAULT 10,
    unit            VARCHAR(50)     NOT NULL DEFAULT 'portions',
    last_updated    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (food_item_id) REFERENCES food_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO inventory (food_item_id, quantity, low_stock_alert, unit) VALUES
(1, 120, 20, 'portions'),
(2,  60, 15, 'portions'),
(3,  45, 10, 'portions'),
(4,  30, 10, 'portions'),
(5,  25, 10, 'portions'),
(6,  80, 15, 'portions');

-- ============================================================
-- 4. PICKUP SLOTS ← NEW TABLE (was missing!)
-- ============================================================
CREATE TABLE IF NOT EXISTS pickup_slots (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    start_time   TIME NOT NULL,
    end_time     TIME NOT NULL,
    meal_period  ENUM('Breakfast','Lunch','Dinner') NOT NULL,
    max_orders   INT NOT NULL DEFAULT 15,
    is_active    TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Seed pickup slots (30-min windows)
INSERT INTO pickup_slots (start_time, end_time, meal_period, max_orders, is_active) VALUES
-- Breakfast: 06:00 – 12:00
('06:00:00', '06:30:00', 'Breakfast', 15, 1),
('06:30:00', '07:00:00', 'Breakfast', 15, 1),
('07:00:00', '07:30:00', 'Breakfast', 15, 1),
('07:30:00', '08:00:00', 'Breakfast', 15, 1),
('08:00:00', '08:30:00', 'Breakfast', 15, 1),
('08:30:00', '09:00:00', 'Breakfast', 15, 1),
('09:00:00', '09:30:00', 'Breakfast', 15, 1),
('09:30:00', '10:00:00', 'Breakfast', 15, 1),
('10:00:00', '10:30:00', 'Breakfast', 15, 1),
('10:30:00', '11:00:00', 'Breakfast', 15, 1),
('11:00:00', '11:30:00', 'Breakfast', 15, 1),
('11:30:00', '12:00:00', 'Breakfast', 15, 1),
-- Lunch: 12:00 – 18:00
('12:00:00', '12:30:00', 'Lunch', 15, 1),
('12:30:00', '13:00:00', 'Lunch', 15, 1),
('13:00:00', '13:30:00', 'Lunch', 15, 1),
('13:30:00', '14:00:00', 'Lunch', 15, 1),
('14:00:00', '14:30:00', 'Lunch', 15, 1),
('14:30:00', '15:00:00', 'Lunch', 15, 1),
('15:00:00', '15:30:00', 'Lunch', 15, 1),
('15:30:00', '16:00:00', 'Lunch', 15, 1),
('16:00:00', '16:30:00', 'Lunch', 15, 1),
('16:30:00', '17:00:00', 'Lunch', 15, 1),
('17:00:00', '17:30:00', 'Lunch', 15, 1),
('17:30:00', '18:00:00', 'Lunch', 15, 1),
-- Dinner: 18:00 – 21:00
('18:00:00', '18:30:00', 'Dinner', 15, 1),
('18:30:00', '19:00:00', 'Dinner', 15, 1),
('19:00:00', '19:30:00', 'Dinner', 15, 1),
('19:30:00', '20:00:00', 'Dinner', 15, 1),
('20:00:00', '20:30:00', 'Dinner', 15, 1),
('20:30:00', '21:00:00', 'Dinner', 15, 1);

-- ============================================================
-- 5. ORDERS (with pickup_slot_id column)
-- ============================================================
CREATE TABLE IF NOT EXISTS orders (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT             NOT NULL,
    pickup_slot_id  INT             DEFAULT NULL,        -- ← ADDED
    total_amount    DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    payment_method  ENUM('Cash','Card') NOT NULL DEFAULT 'Cash',
    order_status    ENUM('Pending','Processing','Ready','Completed') NOT NULL DEFAULT 'Pending',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE CASCADE,
    FOREIGN KEY (pickup_slot_id) REFERENCES pickup_slots(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Performance indexes for dashboard queries
CREATE INDEX idx_orders_created_at ON orders(created_at);
CREATE INDEX idx_orders_status     ON orders(order_status);

-- ============================================================
-- 6. ORDER ITEMS
-- ============================================================
CREATE TABLE IF NOT EXISTS order_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    order_id        INT             NOT NULL,
    food_item_id    INT             NOT NULL,
    quantity        INT             NOT NULL DEFAULT 1,
    unit_price      DECIMAL(10,2)   NOT NULL,
    subtotal        DECIMAL(10,2)   NOT NULL,
    FOREIGN KEY (order_id)     REFERENCES orders(id)     ON DELETE CASCADE,
    FOREIGN KEY (food_item_id) REFERENCES food_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 7. PAYMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    order_id          INT             NOT NULL,
    payment_method    ENUM('Cash','Card') NOT NULL DEFAULT 'Cash',
    amount            DECIMAL(10,2)   NOT NULL,
    payment_status    ENUM('Paid','Pending','Failed') NOT NULL DEFAULT 'Pending',
    payment_date      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    stripe_session_id VARCHAR(255)    DEFAULT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Unique index for Stripe idempotency (prevents duplicate payments)
CREATE UNIQUE INDEX idx_payments_stripe ON payments(stripe_session_id);

-- ============================================================
-- 8. QR CODES
-- ============================================================
CREATE TABLE IF NOT EXISTS qr_codes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    order_id      INT             NOT NULL,
    qr_token      VARCHAR(64)     NOT NULL,
    generated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    verified_at   DATETIME        DEFAULT NULL,
    verified_by   INT             DEFAULT NULL,
    UNIQUE KEY order_id (order_id),
    UNIQUE KEY qr_token (qr_token),
    FOREIGN KEY (order_id)    REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 9. NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT             NOT NULL,
    order_id    INT             DEFAULT NULL,
    message     TEXT            NOT NULL,
    is_read     TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Composite index for "get unread notifications" queries
CREATE INDEX idx_notif_user_read ON notifications(user_id, is_read);

-- ============================================================
-- AUTO_INCREMENT reset values
-- ============================================================
ALTER TABLE users         AUTO_INCREMENT = 4;
ALTER TABLE food_items    AUTO_INCREMENT = 7;
ALTER TABLE inventory     AUTO_INCREMENT = 7;
ALTER TABLE pickup_slots  AUTO_INCREMENT = 31;
ALTER TABLE orders        AUTO_INCREMENT = 1;
ALTER TABLE order_items   AUTO_INCREMENT = 1;
ALTER TABLE payments      AUTO_INCREMENT = 1;
ALTER TABLE qr_codes      AUTO_INCREMENT = 1;
ALTER TABLE notifications AUTO_INCREMENT = 1;

COMMIT;

-- ============================================================
-- ✅ Schema created successfully
-- ============================================================
-- 
-- Login credentials (all passwords = "password"):
--   Admin    → admin@uwu.ac.lk
--   Kitchen  → kitchen@uwu.ac.lk
--   Student  → student@uwu.ac.lk
-- 
-- ⚠️ CHANGE ALL PASSWORDS AFTER FIRST LOGIN
-- ============================================================