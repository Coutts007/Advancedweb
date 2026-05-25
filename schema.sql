-- ============================================================
-- A-Commerce Platform — Database Schema
-- Run this script in phpMyAdmin or MySQL CLI:
--   mysql -u root -p < schema.sql
-- ============================================================

-- 1. Create & select database
CREATE DATABASE IF NOT EXISTS acommerce
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE acommerce;

-- ============================================================
-- 2. USERS table
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id         INT           NOT NULL AUTO_INCREMENT,
    name       VARCHAR(120)  NOT NULL,
    email      VARCHAR(180)  NOT NULL,
    password   VARCHAR(255)  NOT NULL,            -- bcrypt hash
    created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. PRODUCTS table
-- ============================================================
CREATE TABLE IF NOT EXISTS products (
    id          INT             NOT NULL AUTO_INCREMENT,
    title       VARCHAR(200)    NOT NULL,
    description TEXT            NOT NULL,
    price       DECIMAL(10, 2)  NOT NULL,
    image_url   VARCHAR(500)    NOT NULL,
    stock       INT             NOT NULL DEFAULT 0,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT chk_price  CHECK (price  >= 0),
    CONSTRAINT chk_stock  CHECK (stock  >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. Seed — 6 realistic mock products
--    Images sourced from picsum.photos (deterministic, stable IDs)
-- ============================================================
INSERT INTO products (title, description, price, image_url, stock) VALUES

('Sony WH-1000XM5 Headphones',
 'Industry-leading noise cancellation with 30-hour battery life. Crystal-clear call quality with eight microphones. Ultra-comfortable over-ear design perfect for travel and work-from-home.',
 349.99,
 'https://media.s-bol.com/gMLJEBpA8NpZ/w0kkgoX/779x1200.jpg',
 42),

('Apple iPad Pro 11" (M4)',
 'Supercharged by the Apple M4 chip with an Ultra Retina XDR OLED display. Incredibly thin at just 5.1mm. Supports Apple Pencil Pro and Magic Keyboard for versatile productivity.',
 999.00,
 'https://assets.swappie.com/cdn-cgi/image/width=600,height=600,dpr=2,fit=contain,format=auto/swappie-ipad-pro-3-2021-11-space-gray.png?v=1b7fbf0d',
 18),

('Nike Air Max 270 Sneakers',
 'Max Air heel unit delivers exceptional comfort all day long. Lightweight mesh upper keeps your feet cool. Available in a range of bold colorways that complement any outfit.',
 134.95,
 'https://encrypted-tbn0.gstatic.com/shopping?q=tbn:ANd9GcTGcFhhzubj5eDVQvFvea6UtPWsfV1EISCibYGgPlpBPtU3lxvvt9MpDu8B6pVJaddTsgfddCpZ3neKMemblCHe_gYHUIjKlzb1AZumk1o8IAoJ3aVHwI9WJffkYp9xRcuM&usqp=CAc',
 75),

('Dyson V15 Detect Cordless Vacuum',
 'Laser dust detection reveals invisible dust on hard floors. Powerful suction adapts automatically across all floor types. Up to 60-minute run time on a single charge.',
 749.99,
 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcT13goaAsv5w3n2EOsUV-BoblBPMh2TfRKse3gp5pztlg&s',
 9),

('Kindle Paperwhite Signature Edition',
 '6.8-inch 300 ppi glare-free display with adjustable warm light. Auto-adjusting front light for reading any time of day. Weeks of battery life and 32 GB of onboard storage.',
 189.99,
 'https://encrypted-tbn1.gstatic.com/shopping?q=tbn:ANd9GcQtzX1Q9qh6WpVbggzedhcyRJ2SPMbMn9XUkuApELFMHns4JJPEco3O7UduXflRBt-TRjhNaHJ-dvdZ_vUzUTWEYSz8_apHijedsWKMvtA0vbjBM_jxO3fh5vSITP9uKV27&usqp=CAc',
 55),

('Instant Pot Duo 7-in-1 Electric Pressure Cooker',
 'Replaces 7 kitchen appliances: pressure cooker, slow cooker, rice cooker, steamer, sauté pan, yogurt maker, and warmer. Cook up to 70% faster than traditional methods.',
 89.95,
 'https://encrypted-tbn0.gstatic.com/shopping?q=tbn:ANd9GcSWyHjDT7W9qDud3Nd3O0wj7nYor8gezFqdFIKrE5Wl_ZRkYnOHqOqrf6jvlJh3D5ppU6-NcFk-XDFOZuIvVWc6ByOw3Etw5--SAv2rL_zQrrT9IygoBE_PnMXiTzw9mfVCgS9oGQ&usqp=CAc',
 31),

('Samsung 27" Odyssey G5 Gaming Monitor',
 '165 Hz refresh rate and 1ms response time for blur-free competitive gaming. WQHD 1440p resolution curved VA panel. AMD FreeSync Premium eliminates screen tearing.',
 299.00,
 'https://encrypted-tbn2.gstatic.com/shopping?q=tbn:ANd9GcTlHiJwDA9b8Ov_vV4GnSXXxCbgy7ZIWteh742MMS6X9BzFhV6U0kmhXpKeI6cDxunL-EDlix6-7x9asrwM-hZ44gRYyHRP1unrQA&usqp=CAc',
 22),

('LEGO Technic Bugatti Bolide Set',
 '905 authentic pieces engineered in partnership with Bugatti. Features working 8-speed gearbox, aerodynamic body panels, and a collectible nameplate. For builders aged 18+.',
 59.99,
 'https://encrypted-tbn2.gstatic.com/shopping?q=tbn:ANd9GcRmWVJLHhOJvKvgfhfbzpuwv-tmASWNrF2CsAcw2I-mRDfs4f8TLzW9SNZLEwqVW3bW_GfjrEC9Udc9s4Jo9KxE42wY-1RM4Q5UdCzdoecFULCx8Sa6iPPfemtSvGJeE0CIJDaH0Q&usqp=CAc',
 14);
