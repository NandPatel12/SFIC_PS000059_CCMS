-- Gandhidham CCMS Demo Seeds
USE `gandhidham_ccms`;

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
