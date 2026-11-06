-- Gandhidham Municipal Corporation Streetlight CCMS
-- Database Schema Definition (MySQL 8.0+ / MariaDB)

CREATE DATABASE IF NOT EXISTS `gandhidham_ccms` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gandhidham_ccms`;

-- 1. Roles & Permissions
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Users
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(120) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    `last_login_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 3. Municipal Wards
CREATE TABLE IF NOT EXISTS `wards` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ward_number` VARCHAR(20) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `zone` VARCHAR(50) NOT NULL,
    `description` TEXT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 4. Feeder Panels
CREATE TABLE IF NOT EXISTS `feeders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ward_id` INT NOT NULL,
    `feeder_code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `location_description` VARCHAR(255),
    `latitude` DECIMAL(10, 7) NOT NULL,
    `longitude` DECIMAL(10, 7) NOT NULL,
    `rated_capacity_kw` DECIMAL(8, 2) NOT NULL DEFAULT 15.00,
    `api_token_hash` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'maintenance') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`ward_id`) REFERENCES `wards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. Sub-panels (Distribution Panels)
CREATE TABLE IF NOT EXISTS `panels` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `feeder_id` INT NOT NULL,
    `panel_code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `latitude` DECIMAL(10, 7) DEFAULT NULL,
    `longitude` DECIMAL(10, 7) DEFAULT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`feeder_id`) REFERENCES `feeders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. Streetlight Luminaires
CREATE TABLE IF NOT EXISTS `luminaires` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `panel_id` INT NOT NULL,
    `pole_number` VARCHAR(50) NOT NULL UNIQUE,
    `wattage` INT NOT NULL DEFAULT 90,
    `lamp_type` VARCHAR(50) NOT NULL DEFAULT 'LED',
    `dimmable` TINYINT(1) NOT NULL DEFAULT 1,
    `latitude` DECIMAL(10, 7) NOT NULL,
    `longitude` DECIMAL(10, 7) NOT NULL,
    `status` ENUM('active', 'inactive', 'faulty') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`panel_id`) REFERENCES `panels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. Device Latest State (Cache table for ultra-fast monitoring views)
CREATE TABLE IF NOT EXISTS `device_latest_state` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `asset_type` ENUM('feeder', 'luminaire') NOT NULL,
    `asset_id` INT NOT NULL,
    `commanded_state` VARCHAR(20) DEFAULT 'OFF',
    `reported_state` VARCHAR(20) DEFAULT 'OFF',
    `brightness_pct` INT DEFAULT 0,
    `voltage_v` DECIMAL(6, 2) DEFAULT NULL,
    `current_a` DECIMAL(6, 2) DEFAULT NULL,
    `power_kw` DECIMAL(8, 3) DEFAULT NULL,
    `power_factor` DECIMAL(4, 2) DEFAULT NULL,
    `is_online` TINYINT(1) NOT NULL DEFAULT 0,
    `last_seen_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_asset_lookup` (`asset_type`, `asset_id`),
    INDEX `idx_last_seen` (`last_seen_at`)
) ENGINE=InnoDB;

-- 8. Feeder Telemetry Readings (Historical Timeseries)
CREATE TABLE IF NOT EXISTS `telemetry_feeder_readings` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `feeder_id` INT NOT NULL,
    `message_id` VARCHAR(64) NOT NULL UNIQUE,
    `v_r` DECIMAL(6, 2) NOT NULL,
    `v_y` DECIMAL(6, 2) NOT NULL,
    `v_b` DECIMAL(6, 2) NOT NULL,
    `i_r` DECIMAL(6, 2) NOT NULL,
    `i_y` DECIMAL(6, 2) NOT NULL,
    `i_b` DECIMAL(6, 2) NOT NULL,
    `total_power_kw` DECIMAL(8, 3) NOT NULL,
    `power_factor` DECIMAL(4, 2) NOT NULL,
    `cumulative_kwh` DECIMAL(12, 2) NOT NULL,
    `phase_status_r` TINYINT(1) NOT NULL DEFAULT 1,
    `phase_status_y` TINYINT(1) NOT NULL DEFAULT 1,
    `phase_status_b` TINYINT(1) NOT NULL DEFAULT 1,
    `contactor_status` TINYINT(1) NOT NULL DEFAULT 0,
    `recorded_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`feeder_id`) REFERENCES `feeders` (`id`) ON DELETE CASCADE,
    INDEX `idx_feeder_time` (`feeder_id`, `recorded_at`)
) ENGINE=InnoDB;

-- 9. Luminaire Telemetry Readings (Historical Timeseries)
CREATE TABLE IF NOT EXISTS `telemetry_luminaire_readings` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `luminaire_id` INT NOT NULL,
    `message_id` VARCHAR(64) NOT NULL UNIQUE,
    `lamp_status` TINYINT(1) NOT NULL,
    `brightness_pct` INT NOT NULL DEFAULT 100,
    `input_voltage_v` DECIMAL(6, 2) NOT NULL,
    `power_w` DECIMAL(8, 2) NOT NULL,
    `recorded_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`luminaire_id`) REFERENCES `luminaires` (`id`) ON DELETE CASCADE,
    INDEX `idx_lum_time` (`luminaire_id`, `recorded_at`)
) ENGINE=InnoDB;

-- 10. Alerts & Faults
CREATE TABLE IF NOT EXISTS `alerts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `asset_type` ENUM('feeder', 'luminaire') NOT NULL,
    `asset_id` INT NOT NULL,
    `fault_code` VARCHAR(50) NOT NULL,
    `severity` ENUM('critical', 'warning', 'info') NOT NULL,
    `summary` VARCHAR(255) NOT NULL,
    `evidence_json` JSON DEFAULT NULL,
    `status` ENUM('active', 'acknowledged', 'resolved') NOT NULL DEFAULT 'active',
    `acknowledged_by` INT DEFAULT NULL,
    `acknowledged_at` DATETIME DEFAULT NULL,
    `resolved_by` INT DEFAULT NULL,
    `resolved_at` DATETIME DEFAULT NULL,
    `resolution_notes` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_alert_status` (`status`, `severity`),
    INDEX `idx_asset_alert` (`asset_type`, `asset_id`, `status`),
    FOREIGN KEY (`acknowledged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 11. Work Orders
CREATE TABLE IF NOT EXISTS `work_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_code` VARCHAR(50) NOT NULL UNIQUE,
    `alert_id` INT DEFAULT NULL,
    `asset_type` ENUM('feeder', 'luminaire') NOT NULL,
    `asset_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `priority` ENUM('low', 'medium', 'high', 'emergency') NOT NULL DEFAULT 'medium',
    `assigned_to` INT DEFAULT NULL,
    `sla_deadline` DATETIME NOT NULL,
    `status` ENUM('open', 'assigned', 'in_progress', 'pending_verification', 'closed') NOT NULL DEFAULT 'open',
    `repair_evidence_notes` TEXT DEFAULT NULL,
    `closed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`alert_id`) REFERENCES `alerts` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 12. Audit Logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `target_type` VARCHAR(50) NOT NULL,
    `target_id` VARCHAR(50) NOT NULL,
    `details_json` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_user` (`user_id`),
    INDEX `idx_audit_action` (`action`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

