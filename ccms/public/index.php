<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Services/AuthService.php';
require_once __DIR__ . '/../app/Services/FaultService.php';
require_once __DIR__ . '/../app/Services/TelemetryService.php';
require_once __DIR__ . '/../app/Controllers/ApiController.php';

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
            <div class="nav-item" data-tab="work-orders">🔧 Maintenance Orders</div>
            <div class="nav-item" data-tab="simulator">🎮 Device Simulator</div>
            <div class="nav-item" data-tab="sms-logs">📱 SMS Sandbox Logs</div>
        </nav>
        <div class="sidebar-footer">
            <p>Phase 2 & 3 Enabled</p>
            <p>Gandhidham Smart City</p>
        </div>
    </aside>

    <!-- Main Application View -->
    <main class="main-content">
        <header class="topbar">
            <div style="display: flex; align-items: center; gap: 12px;">
                <span class="badge-demo">DEMO / SIMULATED ENVIRONMENT</span>
                <span id="current-time-display" style="font-size: 0.85rem; color: #64748b; font-weight: 500;"></span>
            </div>
            <div class="topbar-right">
                <button id="btn-quick-sim" class="btn-secondary" title="Send live simulated telemetry payload">⚡ Ingest Random Telemetry</button>
                <div class="user-profile" id="user-profile-info">Checking session...</div>
                <button id="btn-logout" class="btn-secondary">Sign Out</button>
            </div>
        </header>

        <div class="content-body">
            <!-- TAB: Dashboard -->
            <div class="tab-view" id="tab-dashboard">
                <div class="metrics-grid">
                    <div class="card">
                        <div class="card-title">Luminaires Active</div>
                        <div class="card-value" id="stat-luminaires">-- / --</div>
                        <div class="card-desc">Pilot Ward 3 & 7</div>
                    </div>
                    <div class="card">
                        <div class="card-title">Feeder Panels</div>
                        <div class="card-value" id="stat-feeders">--</div>
                        <div class="card-desc">Connected Smart RTUs</div>
                    </div>
                    <div class="card">
                        <div class="card-title">Active Faults</div>
                        <div class="card-value" id="stat-alerts" style="color: var(--danger);">--</div>
                        <div class="card-desc">Unresolved alerts</div>
                    </div>
                    <div class="card">
                        <div class="card-title">Open Work Orders</div>
                        <div class="card-value" id="stat-orders">--</div>
                        <div class="card-desc">Assigned to contractors</div>
                    </div>
                    <div class="card">
                        <div class="card-title">Current Load</div>
                        <div class="card-value" id="stat-kw">-- kW</div>
                        <div class="card-desc">Aggregated live power</div>
                    </div>
                    <div class="card">
                        <div class="card-title">Est. Today Energy</div>
                        <div class="card-value" id="stat-kwh">-- kWh</div>
                        <div class="card-desc">Based on operating hours</div>
                    </div>
                </div>

                <div class="table-wrapper">
                    <div class="table-header">
                        <h3>Feeder Panels Telemetry Snapshot</h3>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Feeder Code & Location</th>
                                <th>Ward</th>
                                <th>Reported State</th>
                                <th>Phase Voltage</th>
                                <th>Total Power</th>
                                <th>Last Seen</th>
                            </tr>
                        </thead>
                        <tbody id="dashboard-feeders-body">
                            <tr><td colspan="6" style="text-align: center; color: #94a3b8;">Loading telemetry...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Feeders -->
            <div class="tab-view" id="tab-feeders" style="display: none;">
                <div class="table-wrapper">
                    <div class="table-header">
                        <h3>Smart Feeder Panels Registry</h3>
                        <button class="btn-primary" onclick="app.showAddFeederModal()">+ Register Feeder</button>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Panel Name</th>
                                <th>Ward</th>
                                <th>Rated Capacity</th>
                                <th>Voltage</th>
                                <th>Power</th>
                                <th>State</th>
                                <th>Last Ping</th>
                            </tr>
                        </thead>
                        <tbody id="feeders-table-body">
                            <tr><td colspan="8" style="text-align: center;">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Luminaires -->
            <div class="tab-view" id="tab-luminaires" style="display: none;">
                <div class="table-wrapper">
                    <div class="table-header">
                        <h3>Streetlight Assets (Luminaires)</h3>
                        <button class="btn-primary" onclick="app.showAddLuminaireModal()">+ Register Luminaire</button>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Pole No</th>
                                <th>Parent Panel</th>
                                <th>Ward</th>
                                <th>Rating</th>
                                <th>Dimming Support</th>
                                <th>Status</th>
                                <th>Brightness</th>
                                <th>Last Telemetry</th>
                            </tr>
                        </thead>
                        <tbody id="luminaires-table-body">
                            <tr><td colspan="8" style="text-align: center;">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Alerts -->
            <div class="tab-view" id="tab-alerts" style="display: none;">
                <div class="table-wrapper">
                    <div class="table-header">
                        <h3>Active Faults & System Alerts</h3>
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

            <!-- TAB: Work Orders -->
            <div class="tab-view" id="tab-work-orders" style="display: none;">
                <div class="table-wrapper">
                    <div class="table-header">
                        <h3>Maintenance Work Orders</h3>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order Code</th>
                                <th>Repair Title</th>
                                <th>Priority</th>
                                <th>Assigned Contractor</th>
                                <th>SLA Deadline</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="work-orders-table-body">
                            <tr><td colspan="6" style="text-align: center;">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Simulator -->
            <div class="tab-view" id="tab-simulator" style="display: none;">
                <div class="card" style="max-width: 700px; margin-bottom: 24px;">
                    <h3 style="margin-bottom: 12px;">IoT Device Telemetry & Fault Simulator</h3>
                    <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 16px;">
                        Trigger simulated field telemetry payloads to verify automated fault detection rules (Phase loss, voltage surge, lamp failure, day-burning, deduplication).
                    </p>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <button class="btn-secondary" onclick="app.triggerSimulatorAction('normal')">
                            ✅ Send Normal Telemetry (FDR-W03-FP01: 230V, 5.2kW)
                        </button>
                        <button class="btn-secondary" style="border-color: #ef4444; color: #b91c1c;" onclick="app.triggerSimulatorAction('phase_failure')">
                            ⚠️ Trigger Feeder Phase Loss (R-phase 135V, phase_status.r = false)
                        </button>
                        <button class="btn-secondary" style="border-color: #ef4444; color: #b91c1c;" onclick="app.triggerSimulatorAction('voltage_surge')">
                            ⚡ Trigger Feeder Voltage Surge (>280V)
                        </button>
                        <button class="btn-secondary" style="border-color: #f59e0b; color: #b45309;" onclick="app.triggerSimulatorAction('lamp_failure')">
                            💡 Trigger Streetlight Lamp Failure (POLE-W03-001: 0W / OFF while Commanded ON)
                        </button>
                        <button class="btn-secondary" style="border-color: #3b82f6; color: #1d4ed8;" onclick="app.triggerSimulatorAction('dedup_test')">
                            🔄 Test Message Deduplication Rejection (Replay same message_id)
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAB: SMS Logs -->
            <div class="tab-view" id="tab-sms-logs" style="display: none;">
                <div class="table-wrapper">
                    <div class="table-header">
                        <h3>SMS Sandbox Notification Dispatch Logs</h3>
                        <button class="btn-secondary btn-sm" onclick="app.loadSmsLogs()">Refresh Logs</button>
                    </div>
                    <div style="padding: 16px; font-family: monospace; font-size: 0.85rem; background: #0f172a; color: #38bdf8; min-height: 200px; border-radius: 0 0 8px 8px; overflow-x: auto;" id="sms-log-content">
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

    <script src="/assets/js/app.js"></script>
</body>
</html>
