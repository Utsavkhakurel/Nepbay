-- Nepbay - Online Auction Platform
-- Import with:  /opt/lampp/bin/mysql -u root < database/schema.sql

CREATE DATABASE IF NOT EXISTS nepbay CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nepbay;

-- One table for both normal users (who can buy and sell) and admins
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,          -- from password_hash(), never plain text
    phone         VARCHAR(20)  NULL,
    address       VARCHAR(255) NULL,
    role          ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    status        ENUM('active', 'blocked') NOT NULL DEFAULT 'active',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default admin account. Password: admin123  (change it after first login)
INSERT IGNORE INTO users (name, email, password_hash, role)
VALUES ('Administrator', 'admin@nepbay.com',
        '$2y$10$b790paF49Qd2YtckUf3.6unWx2fyb2aqEo8CdER/iGRZAmouqSaHy', 'admin');

-- Product categories, managed by the admin
CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO categories (name, description) VALUES
    ('Electronics',       'Phones, laptops, cameras and accessories'),
    ('Vehicles',          'Bikes, scooters, cars and parts'),
    ('Fashion',           'Clothing, shoes, bags and watches'),
    ('Home & Furniture',  'Furniture, kitchen items and decor'),
    ('Books',             'Textbooks, novels and magazines'),
    ('Sports & Fitness',  'Sports equipment and gym gear'),
    ('Collectibles',      'Coins, stamps, antiques and art'),
    ('Others',            'Anything that does not fit above');

-- The item being sold
CREATE TABLE IF NOT EXISTS products (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seller_id   INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    title       VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id)   REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

-- Photos of a product (the first one, sort_order 0, is the cover photo)
CREATE TABLE IF NOT EXISTS product_images (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    file_name  VARCHAR(100) NOT NULL,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- One auction per product
CREATE TABLE IF NOT EXISTS auctions (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id     INT UNSIGNED NOT NULL UNIQUE,
    starting_price DECIMAL(12,2) NOT NULL,
    current_bid    DECIMAL(12,2) NULL,              -- NULL until someone bids
    start_time     DATETIME NOT NULL,
    end_time       DATETIME NOT NULL,
    status         ENUM('active', 'ended', 'removed') NOT NULL DEFAULT 'active',
    winner_id      INT UNSIGNED NULL,               -- set when the auction ends
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (winner_id)  REFERENCES users(id),
    INDEX (status, end_time)
) ENGINE=InnoDB;
