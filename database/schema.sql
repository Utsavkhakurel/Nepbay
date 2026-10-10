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
