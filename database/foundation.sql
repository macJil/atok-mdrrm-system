CREATE DATABASE IF NOT EXISTS atok_mdrrm_rebuild
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE atok_mdrrm_rebuild;
CREATE TABLE IF NOT EXISTS accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('staff','resident') NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    must_change BOOLEAN NOT NULL DEFAULT TRUE,
    session_version INT UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_key CHAR(64) PRIMARY KEY,
    failed_attempts TINYINT UNSIGNED NOT NULL,
    window_started_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX (updated_at)
) ENGINE=InnoDB;
