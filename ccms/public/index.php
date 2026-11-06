<?php
$appDir = file_exists(__DIR__ . '/app/Database.php') ? __DIR__ . '/app' : __DIR__ . '/../app';
require_once $appDir . '/Database.php';
require_once $appDir . '/Services/AuthService.php';
require_once $appDir . '/Services/FaultService.php';
require_once $appDir . '/Services/TelemetryService.php';
require_once $appDir . '/Services/ScheduleService.php';
require_once $appDir . '/Services/WorkOrderService.php';
require_once $appDir . '/Services/ReportService.php';
require_once $appDir . '/Controllers/ApiController.php';

// Route API requests directly
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (str_starts_with($uri, '/api/')) {
    App\Controllers\ApiController::handle();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gandhidham Municipal Streetlight CCMS</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="brand-header">
            <div class="brand-title">
                <span>💡</span> Gandhidham CCMS
            </div>
            <div class="brand-subtitle">Municipal Streetlights</div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item active" data-tab="dashboard">📊 Operations Dashboard</div>
            <div class="nav-item" data-tab="feeders">⚡ Feeder Panels</div>
            <div class="nav-item" data-tab="luminaires">💡 Streetlights Registry</div>
            <div class="nav-item" data-tab="alerts">⚠️ Faults & Alerts</div>
            <div class="nav-item" data-tab="schedules">⏱️ Lighting Schedules</div>
            <div class="nav-item" data-tab="work-orders">🔧 Work Orders</div>
            <div class="nav-item" data-tab="reports">📈 Energy & Reports</div>
            <div class="nav-item" data-tab="simulator">🎮 Device Simulator</div>
            <div class="nav-item" data-tab="sms-logs">📱 SMS Sandbox Logs</div>
        </nav>
        <div class="sidebar-footer">
            <p><strong>Phases 1 — 5 Operational</strong></p>
            <p>Gandhidham Smart City</p>
        </div>
    </aside>

    <!-- Main Application View -->
    <main class="main-content">
        <header class="topbar">
            <div style="display: flex; align-items: center; gap: 12px;">
                <span class="badge badge-warning">DEMO / SIMULATED ENVIRONMENT</span>
                <span id="current-time-display" style="font-size: 0.85rem; color: #64748b; font-weight: 500;"></span>
            </div>
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="btn-primary" id="btn-quick-sim" style="font-size: 0.8rem; padding: 6px 12px;">⚡ Ingest Random Telemetry</button>
                <div class="user-profile" id="user-profile-info">Not signed in</div>
                <button class="btn-secondary" id="btn-logout" style="font-size: 0.8rem; padding: 6px 12px;">Sign Out</button>
            </div>
        </header>

        <div class="content-body">
            <!-- TAB: Dashboard -->
            <div class="tab-view" id="tab-dashboard">
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-title">Luminaires Active / Total</div>
                        <div class="metric-value" id="stat-luminaires">-- / --</div>
                        <div class="metric-sub">Pilot Ward 3 & 7</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-title">Active Faults</div>
                        <div class="metric-value danger" id="stat-alerts">--</div>
                        <div class="metric-sub">Unresolved alerts</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-title">Open Work Orders</div>
                        <div class="metric-value warning" id="stat-orders">--</div>
                        <div class="metric-sub">Assigned to contractors</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-title">Est. Today Energy</div>
                        <div class="metric-value success" id="stat-energy">-- kWh</div>
                        <div class="metric-sub" id="stat-cost">Tariff: ₹7.10/unit</div>
                    </div>
                </div>

                <div class="table-wrapper" style="margin-top: 24px;">
                    <div class="table-header">
                        <h3>Feeder Panels Overview</h3>
                        <button class="btn-secondary" onclick="app.switchTab('feeders')">View All Feeders →</button>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Feeder Code & Location</th>
                                <th>Ward</th>
                                <th>Contactor</th>
                                <th>Line Voltage</th>
                                <th>Total Power</th>
                                <th>Last Seen</th>
                            </tr>
                        </thead>
                        <tbody id="dashboard-feeders-table-body">
                            <tr><td colspan="6" style="text-align: center;">Loading telemetry...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Feeders -->
            <div class="tab-view" id="tab-feeders" style="display: none;">
                <div class="table-wrapper">
                    <div class="table-header">
                        <h3>Smart Feeder Panels Registry (Phase 2)</h3>
                        <button class="btn-primary" onclick="app.openModal('add-feeder-modal')">+ Register New Feeder</button>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Feeder Code</th>
                                <th>Name / Description</th>
                                <th>Ward</th>
                                <th>Rated Cap</th>
                                <th>Contactor</th>
                                <th>R-Phase (V)</th>
                                <th>Power (kW)</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="feeders-table-body">
                            <tr><td colspan="9" style="text-align: center;">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Luminaires -->
            <div class="tab-view" id="tab-luminaires" style="display: none;">
                <div class="table-wrapper">
                    <div class="table-header">
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <h3>Streetlight Poles Registry (Phase 2)</h3>
                            <select id="filter-lum-ward" class="form-control" style="width: 180px; padding: 4px 8px;" onchange="app.loadLuminaires()">
                                <option value="">All Wards</option>
                            </select>
                        </div>
                        <button class="btn-primary" onclick="app.openModal('add-luminaire-modal')">+ Add Pole Luminaire</button>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Pole Number</th>
                                <th>Feeder & Sub-Panel</th>
                                <th>Ward</th>
                                <th>Wattage</th>
                                <th>Command</th>
                                <th>State</th>
                                <th>Dim %</th>
                                <th>Line (V)</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="luminaires-table-body">
                            <tr><td colspan="10" style="text-align: center;">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Alerts -->
            <div class="tab-view" id="tab-alerts" style="display: none;">
                <div class="table-wrapper">
                    <div class="table-header">
                        <h3>Active Faults & System Alerts (Phase 3)</h3>
                        <span style="font-size: 0.85rem; color: #64748b;">Automated rule engine evaluations</span>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Severity</th>
                                <th>Fault Code</th>
                                <th>Summary / Evidence</th>
                                <th>Status</th>
                                <th>Timestamp</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="alerts-table-body">
                            <tr><td colspan="6" style="text-align: center;">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Lighting Schedules (Phase 4) -->
            <div class="tab-view" id="tab-schedules" style="display: none;">
                <!-- Astronomical Position Card -->
                <div class="card" style="margin-bottom: 24px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
                        <div>
                            <span class="badge badge-warning" style="margin-bottom: 8px;">Astronomical Clock & Solar Positioning</span>
                            <h2 style="font-size: 1.3rem; margin: 4px 0 8px;">Gandhidham Municipal Corporation (23.0753° N, 70.1337° E)</h2>
                            <p style="color: #94a3b8; font-size: 0.85rem;">Timezone: Asia/Kolkata (IST) | Standard Switching Automation Enabled</p>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button class="btn-primary" onclick="app.openModal('create-schedule-modal')">+ Create Schedule</button>
                            <button class="btn-secondary" style="background: rgba(255,255,255,0.1); color: #fff;" onclick="app.openModal('override-modal')">⚡ Manual Override</button>
                        </div>
                    </div>
                    <div class="metrics-grid" style="margin-top: 18px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                        <div style="background: rgba(255,255,255,0.06); padding: 12px 16px; border-radius: 8px;">
                            <div style="font-size: 0.75rem; color: #94a3b8;">TODAY SUNSET</div>
                            <div style="font-size: 1.25rem; font-weight: 700; color: #f59e0b;" id="astro-sunset">--:--</div>
                            <div style="font-size: 0.75rem; color: #64748b;">Recommended Switch ON</div>
                        </div>
                        <div style="background: rgba(255,255,255,0.06); padding: 12px 16px; border-radius: 8px;">
                            <div style="font-size: 0.75rem; color: #94a3b8;">TODAY SUNRISE</div>
                            <div style="font-size: 1.25rem; font-weight: 700; color: #38bdf8;" id="astro-sunrise">--:--</div>
                            <div style="font-size: 0.75rem; color: #64748b;">Recommended Switch OFF</div>
                        </div>
                        <div style="background: rgba(255,255,255,0.06); padding: 12px 16px; border-radius: 8px;">
                            <div style="font-size: 0.75rem; color: #94a3b8;">CIVIL TWILIGHT DUSK</div>
                            <div style="font-size: 1.25rem; font-weight: 700;" id="astro-dusk">--:--</div>
                            <div style="font-size: 0.75rem; color: #64748b;">Arterial Roads ON</div>
                        </div>
                        <div style="background: rgba(255,255,255,0.06); padding: 12px 16px; border-radius: 8px;">
                            <div style="font-size: 0.75rem; color: #94a3b8;">CIVIL TWILIGHT DAWN</div>
                            <div style="font-size: 1.25rem; font-weight: 700;" id="astro-dawn">--:--</div>
                            <div style="font-size: 0.75rem; color: #64748b;">Arterial Roads OFF</div>
                        </div>
                    </div>
                </div>

                <!-- Schedules Table -->
                <div class="table-wrapper" style="margin-bottom: 24px;">
                    <div class="table-header">
                        <h3>Configured Lighting Schedules</h3>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Schedule Name</th>
                                <th>Scope</th>
                                <th>Switching Method</th>
                                <th>On / Off Timing</th>
                                <th>Dimming Profile</th>
                                <th>Status</th>
                                <th>Approved By</th>
                            </tr>
                        </thead>
                        <tbody id="schedules-table-body">
                            <tr><td colspan="7" style="text-align: center;">Loading schedules...</td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Device Commands Queue Table -->
                <div class="table-wrapper">
                    <div class="table-header">
                        <h3>Device Command Queue & Acknowledgement Log (Phase 4)</h3>
                        <span style="font-size: 0.85rem; color: #64748b;">Traceable commands sent to 4G IoT Gateways</span>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Command Code</th>
                                <th>Target Asset</th>
                                <th>Type</th>
                                <th>Reason / Parameters</th>
                                <th>Status</th>
                                <th>Dispatched Time</th>
                                <th>Expires At</th>
                                <th>Gateway Action</th>
                            </tr>
                        </thead>
                        <tbody id="commands-table-body">
                            <tr><td colspan="8" style="text-align: center;">Loading command queue...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Work Orders (Phase 5) -->
            <div class="tab-view" id="tab-work-orders" style="display: none;">
                <div class="table-wrapper">
                    <div class="table-header">
                        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                            <h3>Maintenance Work Orders & Repair Verification (Phase 5)</h3>
                            <div style="display: flex; gap: 6px;">
                                <button class="btn-secondary" style="padding: 4px 10px; font-size: 0.75rem;" onclick="app.filterWorkOrders('all')">All</button>
                                <button class="btn-secondary" style="padding: 4px 10px; font-size: 0.75rem;" onclick="app.filterWorkOrders('assigned')">Assigned</button>
                                <button class="btn-secondary" style="padding: 4px 10px; font-size: 0.75rem;" onclick="app.filterWorkOrders('in_progress')">In Progress</button>
                                <button class="btn-secondary" style="padding: 4px 10px; font-size: 0.75rem;" onclick="app.filterWorkOrders('closed')">Closed</button>
                            </div>
                        </div>
                        <button class="btn-secondary" onclick="app.exportWorkOrdersCsv()">📥 Export Work Orders CSV</button>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order Code</th>
                                <th>Repair Title</th>
                                <th>Target Asset</th>
                                <th>Priority</th>
                                <th>Assigned Contractor</th>
                                <th>SLA Deadline</th>
                                <th>Repair Notes / Evidence</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="work-orders-table-body">
                            <tr><td colspan="9" style="text-align: center;">Loading work orders...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Energy & Reports (Phase 5) -->
            <div class="tab-view" id="tab-reports" style="display: none;">
                <div class="metrics-grid" style="margin-bottom: 24px;">
                    <div class="metric-card">
                        <div class="metric-title">Current Municipal Load</div>
                        <div class="metric-value" id="report-load-kw">-- kW</div>
                        <div class="metric-sub">Sum of active feeder panels</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-title">Today Estimated Energy</div>
                        <div class="metric-value" id="report-today-kwh">-- kWh</div>
                        <div class="metric-sub" id="report-today-cost">₹ -- estimated daily</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-title">Monthly Energy Bill (Est.)</div>
                        <div class="metric-value success" id="report-monthly-cost">₹ --</div>
                        <div class="metric-sub">Tariff: ₹7.10 per unit (kWh)</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-title">Dimming Energy Saved</div>
                        <div class="metric-value success" id="report-savings-pct">-- %</div>
                        <div class="metric-sub">Versus un-dimmed baseline</div>
                    </div>
                </div>

                <div class="table-wrapper" style="margin-bottom: 24px;">
                    <div class="table-header">
                        <div>
                            <h3>Feeder Energy Consumption & Cost Analysis</h3>
                            <span style="font-size: 0.85rem; color: #64748b;">Calculated with Gandhidham Commercial Public Lighting Tariff ₹7.10/unit</span>
                        </div>
                        <button class="btn-primary" onclick="app.exportEnergyCsv()">📥 Download Energy CSV Report</button>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Feeder Code</th>
                                <th>Feeder Name</th>
                                <th>Ward</th>
                                <th>Rated (kW)</th>
                                <th>Current Load</th>
                                <th>Est. Daily (kWh)</th>
                                <th>Daily Cost (INR)</th>
                                <th>Baseline (kWh)</th>
                                <th>Savings %</th>
                            </tr>
                        </thead>
                        <tbody id="reports-energy-table-body">
                            <tr><td colspan="9" style="text-align: center;">Loading energy report...</td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Maintenance KPIs Card -->
                <div class="card">
                    <h3 style="margin-bottom: 12px;">Maintenance & Service Level Agreement (SLA) KPIs</h3>
                    <div class="metrics-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                        <div>
                            <div style="font-size: 0.8rem; color: #64748b;">Mean Time to Acknowledge (MTTA)</div>
                            <div style="font-size: 1.3rem; font-weight: 700; color: #2563eb;" id="kpi-mtta">14.5 mins</div>
                            <div style="font-size: 0.75rem; color: #10b981;">Target: &lt; 30 mins</div>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; color: #64748b;">Mean Time to Repair (MTTR)</div>
                            <div style="font-size: 1.3rem; font-weight: 700; color: #2563eb;" id="kpi-mttr">3.2 hours</div>
                            <div style="font-size: 0.75rem; color: #10b981;">Target: &lt; 12 hours</div>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; color: #64748b;">SLA Compliance Rate</div>
                            <div style="font-size: 1.3rem; font-weight: 700; color: #10b981;" id="kpi-sla">92.5 %</div>
                            <div style="font-size: 0.75rem; color: #64748b;">Resolved within target deadline</div>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; color: #64748b;">Total Resolved Alerts</div>
                            <div style="font-size: 1.3rem; font-weight: 700;" id="kpi-resolved">--</div>
                            <div style="font-size: 0.75rem; color: #64748b;">Audited repair closures</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: Simulator -->
            <div class="tab-view" id="tab-simulator" style="display: none;">
                <div class="card" style="max-width: 750px; margin-bottom: 24px;">
                    <h3 style="margin-bottom: 12px;">IoT Device Telemetry & Fault Simulator</h3>
                    <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 16px;">
                        Trigger simulated field telemetry payloads to verify automated fault detection rules (Phase loss, voltage surge, lamp failure, day-burning, deduplication).
                    </p>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <button class="btn-secondary" onclick="app.triggerSimulatorAction('normal')">
                            ✅ Send Normal Telemetry (FDR-W03-FP01: 230V, 5.2kW)
                        </button>
                        <button class="btn-secondary" style="border-color: #ef4444; color: #ef4444;" onclick="app.triggerSimulatorAction('phase_fail')">
                            ⚠️ Simulate Feeder Phase Failure (R-Phase drop to 142V &lt; 160V)
                        </button>
                        <button class="btn-secondary" style="border-color: #f59e0b; color: #f59e0b;" onclick="app.triggerSimulatorAction('surge')">
                            ⚡ Simulate Voltage Surge (284V &gt; 270V threshold)
                        </button>
                        <button class="btn-secondary" style="border-color: #ef4444; color: #ef4444;" onclick="app.triggerSimulatorAction('lamp_fail')">
                            💡 Simulate Luminaire Failure (Pole POLE-W03-001 commanded ON but draws 0W)
                        </button>
                        <button class="btn-secondary" onclick="app.triggerSimulatorAction('dedup')">
                            🔄 Test Message Deduplication (Replay identical message_id)
                        </button>
                    </div>
                    <div id="simulator-output" style="margin-top: 16px; font-family: monospace; font-size: 0.8rem; background: #0f172a; color: #38bdf8; padding: 12px; border-radius: 6px; min-height: 50px;">
                        Simulator ready. Click an action above to trigger an IoT telemetry payload.
                    </div>
                </div>
            </div>

            <!-- TAB: SMS Logs -->
            <div class="tab-view" id="tab-sms-logs" style="display: none;">
                <div class="card">
                    <h3 style="margin-bottom: 12px;">SMS Dispatch Sandbox Logs</h3>
                    <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 16px;">
                        Real-time audit log of SMS alert dispatches triggered by critical faults (Phase failures, voltage anomalies, lamp dropouts).
                    </p>
                    <div id="sms-log-container" style="background: #0f172a; color: #a5f3fc; padding: 16px; border-radius: 8px; font-family: monospace; font-size: 0.82rem; height: 350px; overflow-y: auto;">
                        Loading notifications...
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Login Screen -->
    <div class="modal-overlay" id="login-modal" style="display: none;">
        <div class="modal-card">
            <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 8px;">Municipal CCMS Portal</h2>
            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 20px;">Sign in with your role-authorized credentials</p>
            <form id="login-form">
                <div class="form-group">
                    <label for="login-username">Username or Email</label>
                    <input type="text" id="login-username" class="form-control" value="admin" required>
                </div>
                <div class="form-group">
                    <label for="login-password">Password</label>
                    <input type="password" id="login-password" class="form-control" value="Admin@1234" required>
                </div>
                <div id="login-error" style="color: var(--danger); font-size: 0.85rem; margin-bottom: 12px; min-height: 1.2rem;"></div>
                <button type="submit" class="btn-primary" style="width: 100%; padding: 10px;">Sign In to Console</button>
            </form>
            <div style="margin-top: 16px; font-size: 0.75rem; color: #94a3b8; text-align: center;">
                Demo Credentials: <code>admin</code> / <code>Admin@1234</code><br>
                Engineer: <code>engineer_patel</code> | Contractor: <code>contractor_apex</code>
            </div>
        </div>
    </div>

    <!-- Modal: Add Feeder -->
    <div class="modal-overlay" id="add-feeder-modal" style="display: none;">
        <div class="modal-card">
            <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 12px;">Register New Feeder Panel</h3>
            <form id="add-feeder-form" onsubmit="app.handleCreateFeeder(event)">
                <div class="form-group">
                    <label>Ward</label>
                    <select id="feeder-ward-select" class="form-control" required></select>
                </div>
                <div class="form-group">
                    <label>Feeder Code</label>
                    <input type="text" id="feeder-code-input" class="form-control" placeholder="e.g. FDR-W03-FP03" required>
                </div>
                <div class="form-group">
                    <label>Panel Name / Location</label>
                    <input type="text" id="feeder-name-input" class="form-control" placeholder="e.g. Sector 4 Junction Panel" required>
                </div>
                <div class="form-group">
                    <label>Rated Capacity (kW)</label>
                    <input type="number" id="feeder-cap-input" class="form-control" value="15.0" step="0.5" required>
                </div>
                <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px;">
                    <button type="button" class="btn-secondary" onclick="app.closeModal('add-feeder-modal')">Cancel</button>
                    <button type="submit" class="btn-primary">Register Feeder</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Add Luminaire -->
    <div class="modal-overlay" id="add-luminaire-modal" style="display: none;">
        <div class="modal-card">
            <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 12px;">Register New Streetlight Pole</h3>
            <form id="add-luminaire-form" onsubmit="app.handleCreateLuminaire(event)">
                <div class="form-group">
                    <label>Parent Distribution Panel</label>
                    <select id="lum-panel-select" class="form-control" required></select>
                </div>
                <div class="form-group">
                    <label>Pole Number</label>
                    <input type="text" id="lum-pole-input" class="form-control" placeholder="e.g. POLE-W03-005" required>
                </div>
                <div class="form-group">
                    <label>Wattage (W)</label>
                    <input type="number" id="lum-watt-input" class="form-control" value="90" required>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" id="lum-dimmable-input" checked> Dimmable LED Driver (0-10V / DALI)</label>
                </div>
                <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px;">
                    <button type="button" class="btn-secondary" onclick="app.closeModal('add-luminaire-modal')">Cancel</button>
                    <button type="submit" class="btn-primary">Register Luminaire</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Create Schedule (Phase 4) -->
    <div class="modal-overlay" id="create-schedule-modal" style="display: none;">
        <div class="modal-card">
            <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 12px;">Create Lighting Schedule</h3>
            <form onsubmit="app.handleCreateSchedule(event)">
                <div class="form-group">
                    <label>Schedule Profile Name</label>
                    <input type="text" id="sched-name" class="form-control" placeholder="e.g. Adipur Arterial Night Dimming" required>
                </div>
                <div class="form-group">
                    <label>Assigned Ward</label>
                    <select id="sched-ward" class="form-control">
                        <option value="">All Municipal Wards (Global)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Switching Mode</label>
                    <select id="sched-mode" class="form-control" onchange="app.toggleScheduleMode(this.value)">
                        <option value="astro">Astronomical (Auto Sunset / Sunrise)</option>
                        <option value="fixed">Fixed Clock Times</option>
                    </select>
                </div>
                <div id="sched-fixed-times" style="display: none; gap: 12px;">
                    <div class="form-group" style="flex: 1;">
                        <label>ON Time</label>
                        <input type="time" id="sched-fixed-on" class="form-control" value="18:30">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>OFF Time</label>
                        <input type="time" id="sched-fixed-off" class="form-control" value="06:00">
                    </div>
                </div>
                <div class="form-group">
                    <label>Night Dimming Block</label>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <span style="font-size: 0.85rem; color: #64748b;">From</span>
                        <input type="time" id="sched-dim-start" class="form-control" value="23:00" style="width: 110px;">
                        <span style="font-size: 0.85rem; color: #64748b;">To</span>
                        <input type="time" id="sched-dim-end" class="form-control" value="05:00" style="width: 110px;">
                        <span style="font-size: 0.85rem; color: #64748b;">Brightness</span>
                        <select id="sched-dim-pct" class="form-control" style="width: 90px;">
                            <option value="80">80%</option>
                            <option value="65" selected>65%</option>
                            <option value="50">50%</option>
                            <option value="40">40%</option>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px;">
                    <button type="button" class="btn-secondary" onclick="app.closeModal('create-schedule-modal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save & Activate Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Manual Override (Phase 4) -->
    <div class="modal-overlay" id="override-modal" style="display: none;">
        <div class="modal-card">
            <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 12px;">Manual Lighting Override</h3>
            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 16px;">
                Temporary manual command with mandatory audit justification and auto-expiry.
            </p>
            <form onsubmit="app.handleManualOverride(event)">
                <div class="form-group">
                    <label>Target Feeder Panel</label>
                    <select id="ovr-feeder" class="form-control" required></select>
                </div>
                <div class="form-group">
                    <label>Commanded State</label>
                    <select id="ovr-state" class="form-control">
                        <option value="ON">Force Contactor ON (Lights Active)</option>
                        <option value="OFF">Force Contactor OFF (Lights Inactive)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Luminaire Brightness (%)</label>
                    <select id="ovr-bright" class="form-control">
                        <option value="100">100% (Full Intensity)</option>
                        <option value="80">80%</option>
                        <option value="60">60%</option>
                        <option value="0">0% (Off)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Override Duration (Auto-Expiry)</label>
                    <select id="ovr-duration" class="form-control">
                        <option value="15">15 Minutes</option>
                        <option value="30">30 Minutes</option>
                        <option value="60" selected>1 Hour</option>
                        <option value="120">2 Hours</option>
                        <option value="240">4 Hours</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mandatory Audit Reason</label>
                    <input type="text" id="ovr-reason" class="form-control" placeholder="e.g. Emergency road repair inspection / Festival illumination" required>
                </div>
                <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px;">
                    <button type="button" class="btn-secondary" onclick="app.closeModal('override-modal')">Cancel</button>
                    <button type="submit" class="btn-primary" style="background: #f59e0b;">Dispatch Override</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Update Work Order & Evidence (Phase 5) -->
    <div class="modal-overlay" id="update-wo-modal" style="display: none;">
        <div class="modal-card">
            <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 12px;">Update Work Order & Evidence</h3>
            <form onsubmit="app.handleUpdateWorkOrder(event)">
                <input type="hidden" id="edit-wo-id">
                <div class="form-group">
                    <label>Work Order Code</label>
                    <input type="text" id="edit-wo-code" class="form-control" readonly style="background: #f1f5f9;">
                </div>
                <div class="form-group">
                    <label>Update Status</label>
                    <select id="edit-wo-status" class="form-control">
                        <option value="assigned">Assigned</option>
                        <option value="in_progress">In Progress</option>
                        <option value="pending_verification">Pending Verification</option>
                        <option value="closed">Closed / Verified Repaired</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Repair Notes & Evidence Description</label>
                    <textarea id="edit-wo-evidence" class="form-control" rows="3" placeholder="e.g. Replaced faulty 90W LED driver. Line voltage measured at 230V. Lamp tested OK." required></textarea>
                </div>
                <div class="form-group">
                    <label>Status Transition Note</label>
                    <input type="text" id="edit-wo-notes" class="form-control" placeholder="e.g. Field contractor dispatched with replacement parts">
                </div>
                <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px;">
                    <button type="button" class="btn-secondary" onclick="app.closeModal('update-wo-modal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Work Order Timeline Events (Phase 5) -->
    <div class="modal-overlay" id="wo-events-modal" style="display: none;">
        <div class="modal-card" style="max-width: 550px;">
            <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 12px;" id="wo-events-title">Work Order Timeline</h3>
            <div id="wo-events-list" style="margin: 16px 0; max-height: 300px; overflow-y: auto;">
                Loading timeline...
            </div>
            <div style="display: flex; justify-content: flex-end;">
                <button type="button" class="btn-secondary" onclick="app.closeModal('wo-events-modal')">Close</button>
            </div>
        </div>
    </div>

    <script src="/assets/js/app.js"></script>
</body>
</html>
