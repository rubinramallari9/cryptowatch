CREATE DATABASE IF NOT EXISTS cryptowatch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE cryptowatch;

CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)        NOT NULL,
    email      VARCHAR(150) UNIQUE NOT NULL,
    password   VARCHAR(255)        NOT NULL,
    favorites  TEXT                DEFAULT NULL,
    created_at TIMESTAMP           DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
