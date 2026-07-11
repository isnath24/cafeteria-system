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
    role        ENUM('admin','student') NOT NULL DEFAULT 'student',
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default admin account  (password: Admin@1234)
INSERT INTO users (name, email, password, role) VALUES
('Admin User', 'admin@uwu.ac.lk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- NOTE: The hash above equals "password" for quick testing.
-- Change it after setup: password_hash('Admin@1234', PASSWORD_DEFAULT)

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

-- Seed food items
INSERT INTO food_items (food_name, description, price, category, image, availability_status) VALUES
('Rice & Curry',     'Traditional rice with curry',          150.00, 'Main Course', 'rice.jpg',           'Available'),
('Kottu',            'Spicy kottu roti',                     300.00, 'Main Course', 'kottu.jpg',          'Available'),
('Fried Rice',       'Egg fried rice',                       290.00, 'Main Course', 'fried-rice.jpg',     'Available'),
('String Hoppers',   'Soft string hoppers with coconut milk',180.00, 'Breakfast',   'string-hoppers.jpg', 'Available'),
('Noodles',          'Stir-fried noodles',                   200.00, 'Dinner',      'noodles.jpg',        'Available'),
('Egg Curry',        'Boiled egg in spicy curry',            200.00, 'Main Course', 'egg-curry.jpg',      'Available');

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

-- Seed inventory (matching food items 1–6)
INSERT INTO inventory (food_item_id, quantity, low_stock_alert, unit) VALUES
(1, 120, 20, 'portions'),
(2,  60, 15, 'portions'),
(3,  45, 10, 'portions'),
(4,  30, 10, 'portions'),
(5,  25, 10, 'portions'),
(6,  80, 15, 'portions');

-- ============================================================
-- 4. ORDERS
-- ============================================================
CREATE TABLE IF NOT EXISTS orders (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT             NOT NULL,
    total_amount    DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    payment_method  ENUM('Cash','Card') NOT NULL DEFAULT 'Cash',
    order_status    ENUM('Pending','Processing','Ready','Completed') NOT NULL DEFAULT 'Pending',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 5. ORDER ITEMS
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
-- 6. PAYMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    order_id          INT             NOT NULL,
    payment_method    ENUM('Cash','Card') NOT NULL DEFAULT 'Cash',
    amount            DECIMAL(10,2)   NOT NULL,
    payment_status    ENUM('Paid','Pending','Failed') NOT NULL DEFAULT 'Pending',
    payment_date      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    stripe_session_id VARCHAR(255)    DEFAULT NULL,  -- Stripe Checkout Session ID (card payments)
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;
