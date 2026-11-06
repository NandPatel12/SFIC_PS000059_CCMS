-- InfinityFree Ready Import for database: if0_43371701_ccms
-- Generated for Gandhidham Streetlight CCMS (Phases 1-5)

-- Gandhidham Municipal Corporation Streetlight CCMS
-- Database Schema Definition (MySQL 8.0+ / MariaDB)




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


-- ========================================================
-- PHASE 4 & PHASE 5 TABLES
-- ========================================================

-- 13. Lighting Schedules (Astronomical & Time-block Dimming Profiles)
CREATE TABLE IF NOT EXISTS `lighting_schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `ward_id` INT DEFAULT NULL,
    `feeder_id` INT DEFAULT NULL,
    `is_astronomical` TINYINT(1) NOT NULL DEFAULT 1,
    `sunset_offset_mins` INT NOT NULL DEFAULT 0,
    `sunrise_offset_mins` INT NOT NULL DEFAULT 0,
    `fixed_on_time` TIME DEFAULT NULL,
    `fixed_off_time` TIME DEFAULT NULL,
    `dimming_profile_json` JSON DEFAULT NULL,
    `status` ENUM('active', 'draft', 'archived') NOT NULL DEFAULT 'active',
    `approved_by` INT DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`ward_id`) REFERENCES `wards` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`feeder_id`) REFERENCES `feeders` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 14. Device Commands Queue (Switching, Dimming & Manual Overrides)
CREATE TABLE IF NOT EXISTS `device_commands` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `command_code` VARCHAR(50) NOT NULL UNIQUE,
    `target_type` ENUM('feeder', 'luminaire', 'ward') NOT NULL,
    `target_id` INT NOT NULL,
    `command_type` ENUM('SWITCH_ON', 'SWITCH_OFF', 'SET_DIMMING', 'MANUAL_OVERRIDE', 'REBOOT') NOT NULL,
    `parameters_json` JSON DEFAULT NULL,
    `reason` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('pending', 'dispatched', 'acknowledged', 'failed', 'expired') NOT NULL DEFAULT 'pending',
    `issued_by` INT DEFAULT NULL,
    `expires_at` DATETIME DEFAULT NULL,
    `acknowledged_at` DATETIME DEFAULT NULL,
    `response_payload_json` JSON DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_cmd_target` (`target_type`, `target_id`, `status`),
    INDEX `idx_cmd_status` (`status`),
    FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 15. Work Order Events & Audit Timeline
CREATE TABLE IF NOT EXISTS `work_order_events` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `work_order_id` INT NOT NULL,
    `user_id` INT DEFAULT NULL,
    `from_status` VARCHAR(50) NOT NULL,
    `to_status` VARCHAR(50) NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 16. Municipal System Settings (Tariff Rates & Astronomical Coordinates)
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(50) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ================= SEED DATA ================

-- Gandhidham CCMS Demo Seeds


-- Roles
INSERT INTO `roles` (`id`, `slug`, `name`, `description`) VALUES
(1, 'super_admin', 'Super Administrator', 'Full system management and configuration control'),
(2, 'electrical_engineer', 'Electrical Engineer', 'Telemetry monitoring, schedule management, and fault triage'),
(3, 'line_supervisor', 'Line Supervisor', 'Monitors municipal wards, acknowledges alerts, and assigns work orders'),
(4, 'maintenance_contractor', 'Maintenance Contractor', 'Field contractor who executes repairs and updates work orders'),
(5, 'auditor', 'Read-only Auditor', 'Inspects compliance logs, energy metrics, and audit trails')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- Default Users (Password: Admin@1234, Engineer@1234, Supervisor@1234, Contractor@1234)
-- Hashes generated via password_hash('...', PASSWORD_BCRYPT)
INSERT INTO `users` (`id`, `role_id`, `username`, `email`, `password_hash`, `full_name`, `phone`, `status`) VALUES
(1, 1, 'admin', 'admin@gandhidham.gov.in', '$2y$10$Dgh/EzTU2UXUq6aOMaMapOLGLywCu.KBEOOuj5q1yM5dpinIZUXYq', 'Chief Municipal Admin', '+91 98250 11001', 'active'),
(2, 2, 'engineer_patel', 'ee.patel@gandhidham.gov.in', '$2y$10$Dgh/EzTU2UXUq6aOMaMapOLGLywCu.KBEOOuj5q1yM5dpinIZUXYq', 'Er. Rajesh Patel (EE)', '+91 98250 11002', 'active'),
(3, 3, 'sup_sharma', 'sup.sharma@gandhidham.gov.in', '$2y$10$Dgh/EzTU2UXUq6aOMaMapOLGLywCu.KBEOOuj5q1yM5dpinIZUXYq', 'Virendra Sharma (Supervisor)', '+91 98250 11003', 'active'),
(4, 4, 'contractor_apex', 'repairs@apexelectricals.com', '$2y$10$Dgh/EzTU2UXUq6aOMaMapOLGLywCu.KBEOOuj5q1yM5dpinIZUXYq', 'Apex Electrical Maintenance', '+91 98250 11004', 'active')
ON DUPLICATE KEY UPDATE `full_name`=VALUES(`full_name`);

-- Demo Wards (Gandhidham Corporation Pilot Wards)
INSERT INTO `wards` (`id`, `ward_number`, `name`, `zone`, `description`) VALUES
(1, 'WARD-03', 'Adipur Commercial & Institutional', 'South Zone', 'Adipur central market, colleges, and arterial highway access'),
(2, 'WARD-07', 'Gandhidham Port & Transport Corridor', 'East Zone', 'Heavy cargo freight route, railway overpass, and residential sectors')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- Demo Feeders
INSERT INTO `feeders` (`id`, `ward_id`, `feeder_code`, `name`, `location_description`, `latitude`, `longitude`, `rated_capacity_kw`) VALUES
(1, 1, 'FDR-W03-FP01', 'Adipur Main Road Feeder Panel 01', 'Near Adipur Circle, Opp Post Office', 23.0768000, 70.1034000, 18.50),
(2, 1, 'FDR-W03-FP02', 'College Road Feeder Panel 02', 'Polytechnic College Junction', 23.0812000, 70.0988000, 15.00),
(3, 2, 'FDR-W07-FP01', 'Port Bypass Corridor Feeder 01', 'Highway Milestone 14, Gandhidham Bypass', 23.0645000, 70.1342000, 22.00),
(4, 2, 'FDR-W07-FP02', 'Sector 8 Residential Feeder 02', 'Rotary Community Center Corner', 23.0592000, 70.1290000, 12.50)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- Demo Panels
INSERT INTO `panels` (`id`, `feeder_id`, `panel_code`, `name`, `latitude`, `longitude`) VALUES
(1, 1, 'PNL-W03-01A', 'Adipur Circle West Distribution Panel', 23.0769000, 70.1032000),
(2, 1, 'PNL-W03-01B', 'Adipur Circle East Distribution Panel', 23.0767000, 70.1036000),
(3, 3, 'PNL-W07-01A', 'Bypass North Express Distribution Panel', 23.0648000, 70.1340000)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- Demo Luminaires
INSERT INTO `luminaires` (`id`, `panel_id`, `pole_number`, `wattage`, `lamp_type`, `dimmable`, `latitude`, `longitude`, `status`) VALUES
(1, 1, 'POLE-W03-001', 90, 'LED', 1, 23.0770100, 70.1031500, 'active'),
(2, 1, 'POLE-W03-002', 90, 'LED', 1, 23.0771200, 70.1031900, 'active'),
(3, 1, 'POLE-W03-003', 90, 'LED', 1, 23.0772300, 70.1032300, 'faulty'),
(4, 2, 'POLE-W03-004', 120, 'LED', 1, 23.0766500, 70.1037500, 'active'),
(5, 3, 'POLE-W07-001', 150, 'LED', 1, 23.0649000, 70.1341000, 'active'),
(6, 3, 'POLE-W07-002', 150, 'LED', 1, 23.0650200, 70.1342500, 'active')
ON DUPLICATE KEY UPDATE `wattage`=VALUES(`wattage`);

-- Latest States
INSERT INTO `device_latest_state` (`asset_type`, `asset_id`, `commanded_state`, `reported_state`, `brightness_pct`, `voltage_v`, `current_a`, `power_kw`, `power_factor`, `is_online`, `last_seen_at`) VALUES
('feeder', 1, 'ON', 'ON', 100, 231.40, 26.80, 6.120, 0.94, 1, NOW()),
('feeder', 2, 'ON', 'ON', 100, 229.80, 21.10, 4.820, 0.92, 1, NOW()),
('feeder', 3, 'ON', 'ON', 100, 218.50, 38.40, 8.250, 0.90, 1, NOW()),
('feeder', 4, 'OFF', 'OFF', 0, 0.00, 0.00, 0.000, 0.00, 1, NOW()),
('luminaire', 1, 'ON', 'ON', 100, 231.20, 0.39, 0.090, 0.95, 1, NOW()),
('luminaire', 2, 'ON', 'ON', 100, 230.90, 0.39, 0.089, 0.94, 1, NOW()),
('luminaire', 3, 'ON', 'OFF', 0, 231.00, 0.00, 0.000, 0.00, 1, NOW())
ON DUPLICATE KEY UPDATE `last_seen_at`=NOW();

-- Seed Sample Alert for Faulty Lamp
INSERT INTO `alerts` (`id`, `asset_type`, `asset_id`, `fault_code`, `severity`, `summary`, `evidence_json`, `status`, `created_at`) VALUES
(1, 'luminaire', 3, 'FLT_LAMP_FAIL', 'critical', 'Luminaire POLE-W03-003 commanded ON but reported 0W consumption', '{"commanded":"ON","reported_power_w":0.0,"voltage":231.0}', 'active', NOW())
ON DUPLICATE KEY UPDATE `summary`=VALUES(`summary`);

-- Seed Work Order for that Alert
INSERT INTO `work_orders` (`id`, `order_code`, `alert_id`, `asset_type`, `asset_id`, `title`, `priority`, `assigned_to`, `sla_deadline`, `status`) VALUES
(1, 'WO-202611-001', 1, 'luminaire', 3, 'Replace faulty LED driver on Pole W03-003', 'high', 4, DATE_ADD(NOW(), INTERVAL 4 HOUR), 'assigned')
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`);


-- Phase 4: Lighting Schedules
INSERT INTO `lighting_schedules` (`id`, `name`, `ward_id`, `feeder_id`, `is_astronomical`, `sunset_offset_mins`, `sunrise_offset_mins`, `fixed_on_time`, `fixed_off_time`, `dimming_profile_json`, `status`, `approved_by`, `approved_at`, `created_at`) VALUES
(1, 'Gandhidham Standard Astronomical Profile', NULL, NULL, 1, 0, 0, NULL, NULL, '[{"start":"23:00","end":"05:00","brightness_pct":65}]', 'active', 2, NOW(), NOW()),
(2, 'Ward 7 Port Freight Corridor (High Intensity)', 2, 3, 0, 0, 0, '18:30:00', '06:15:00', '[{"start":"01:00","end":"04:30","brightness_pct":80}]', 'active', 2, NOW(), NOW()),
(3, 'Ward 3 Adipur Market Energy Saver', 1, 1, 1, 15, -15, NULL, NULL, '[{"start":"22:30","end":"05:30","brightness_pct":50}]', 'active', 2, NOW(), NOW())
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- Phase 4: Device Commands Queue
INSERT INTO `device_commands` (`id`, `command_code`, `target_type`, `target_id`, `command_type`, `parameters_json`, `reason`, `status`, `issued_by`, `expires_at`, `acknowledged_at`, `created_at`) VALUES
(1, 'CMD-202611-001', 'feeder', 1, 'SWITCH_ON', '{"contactor":true}', 'Scheduled astronomical turn-on', 'acknowledged', 2, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW(), NOW()),
(2, 'CMD-202611-002', 'luminaire', 1, 'SET_DIMMING', '{"brightness_pct":65}', 'Night-time energy saving dimming block', 'acknowledged', 2, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW(), NOW()),
(3, 'CMD-202611-003', 'feeder', 2, 'MANUAL_OVERRIDE', '{"state":"ON","duration_mins":60}', 'Underground cable joint test inspection', 'dispatched', 2, DATE_ADD(NOW(), INTERVAL 1 HOUR), NULL, NOW())
ON DUPLICATE KEY UPDATE `command_code`=VALUES(`command_code`);

-- Phase 5: Work Order Timeline Events
INSERT INTO `work_order_events` (`id`, `work_order_id`, `user_id`, `from_status`, `to_status`, `notes`, `created_at`) VALUES
(1, 1, 3, 'open', 'assigned', 'Assigned to Apex Electrical Maintenance with 4h SLA target', NOW())
ON DUPLICATE KEY UPDATE `notes`=VALUES(`notes`);

-- Phase 5: Municipal Settings & Energy Tariffs
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `description`) VALUES
(1, 'tariff_inr_per_kwh', '7.10', 'Commercial municipal public lighting tariff in INR per unit (kWh)'),
(2, 'gandhidham_latitude', '23.0753', 'Gandhidham Municipal Corporation Latitude'),
(3, 'gandhidham_longitude', '70.1337', 'Gandhidham Municipal Corporation Longitude'),
(4, 'timezone', 'Asia/Kolkata', 'System operating timezone'),
(5, 'baseline_feeder_kwh_daily', '140.0', 'Baseline un-dimmed daily energy consumption per feeder in kWh')
ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`);
