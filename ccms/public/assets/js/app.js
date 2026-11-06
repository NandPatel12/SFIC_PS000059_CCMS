// Gandhidham CCMS Client Application (Phase 1, 2 & 3 Integrated)
document.addEventListener('DOMContentLoaded', () => {
    window.app = new CCMSApp();
});

class CCMSApp {
    constructor() {
        this.currentUser = null;
        this.csrfToken = '';
        this.activeTab = 'dashboard';
        this.init();
    }

    async init() {
        this.bindEvents();
        await this.checkAuth();
        this.renderCurrentTime();
        setInterval(() => this.renderCurrentTime(), 1000);
    }

    renderCurrentTime() {
        const timeEl = document.getElementById('current-time-display');
        if (timeEl) {
            const now = new Date();
            timeEl.innerText = now.toLocaleString('en-IN', { timeZone: 'Asia/Kolkata' }) + ' IST';
        }
    }

    bindEvents() {
        // Navigation clicks
        document.querySelectorAll('.nav-item').forEach(el => {
            el.addEventListener('click', (e) => {
                const target = e.currentTarget.getAttribute('data-tab');
                if (target) {
                    this.switchTab(target);
                }
            });
        });

        // Login form
        const loginForm = document.getElementById('login-form');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => this.handleLogin(e));
        }

        // Logout
        const logoutBtn = document.getElementById('btn-logout');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => this.handleLogout());
        }

        // Quick demo simulator trigger
        const simBtn = document.getElementById('btn-quick-sim');
        if (simBtn) {
            simBtn.addEventListener('click', () => this.simulateTelemetry());
        }
    }

    async checkAuth() {
        try {
            const res = await fetch('/api/me');
            if (res.ok) {
                const data = await res.json();
                this.currentUser = data.user;
                this.csrfToken = data.csrf_token;
                this.updateAuthUI();
                this.loadActiveTab();
            } else {
                this.showLoginModal();
            }
        } catch (err) {
            console.error('Auth verification failed', err);
            this.showLoginModal();
        }
    }

    updateAuthUI() {
        const modal = document.getElementById('login-modal');
        if (modal) modal.style.display = 'none';

        const profileEl = document.getElementById('user-profile-info');
        if (profileEl && this.currentUser) {
            profileEl.innerHTML = `<strong>${this.escape(this.currentUser.full_name)}</strong> (${this.escape(this.currentUser.role_name)})`;
        }
    }

    showLoginModal() {
        const modal = document.getElementById('login-modal');
        if (modal) modal.style.display = 'flex';
    }

    async handleLogin(e) {
        e.preventDefault();
        const username = document.getElementById('login-username').value;
        const password = document.getElementById('login-password').value;
        const errorEl = document.getElementById('login-error');
        errorEl.innerText = '';

        try {
            const res = await fetch('/api/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });
            const data = await res.json();

            if (res.ok && data.success) {
                this.currentUser = data.user;
                this.csrfToken = data.csrf_token;
                this.updateAuthUI();
                this.loadActiveTab();
            } else {
                errorEl.innerText = data.error || 'Authentication failed';
            }
        } catch (err) {
            errorEl.innerText = 'Network error while logging in';
        }
    }

    async handleLogout() {
        try {
            await fetch('/api/logout', { method: 'POST' });
        } finally {
            this.currentUser = null;
            location.reload();
        }
    }

    switchTab(tabId) {
        this.activeTab = tabId;
        document.querySelectorAll('.nav-item').forEach(el => {
            el.classList.toggle('active', el.getAttribute('data-tab') === tabId);
        });
        document.querySelectorAll('.tab-view').forEach(view => {
            view.style.display = view.id === `tab-${tabId}` ? 'block' : 'none';
        });
        this.loadActiveTab();
    }

    async loadActiveTab() {
        if (!this.currentUser) return;
        if (this.activeTab === 'dashboard') {
            await this.loadDashboard();
        } else if (this.activeTab === 'feeders') {
            await this.loadFeeders();
        } else if (this.activeTab === 'luminaires') {
            await this.loadLuminaires();
        } else if (this.activeTab === 'alerts') {
            await this.loadAlerts();
        } else if (this.activeTab === 'work-orders') {
            await this.loadWorkOrders();
        } else if (this.activeTab === 'sms-logs') {
            await this.loadSmsLogs();
        }
    }

    async loadDashboard() {
        try {
            const res = await fetch('/api/dashboard/stats');
            const data = await res.json();
            if (data.success) {
                const s = data.stats;
                document.getElementById('stat-luminaires').innerText = `${s.active_luminaires} / ${s.total_luminaires}`;
                document.getElementById('stat-feeders').innerText = s.total_feeders;
                document.getElementById('stat-alerts').innerText = s.active_alerts;
                document.getElementById('stat-orders').innerText = s.open_work_orders;
                document.getElementById('stat-kw').innerText = `${s.current_load_kw} kW`;
                document.getElementById('stat-kwh').innerText = `~${s.estimated_today_kwh} kWh`;
            }

            const fRes = await fetch('/api/feeders');
            const fData = await fRes.json();
            if (fData.success) {
                const tbody = document.getElementById('dashboard-feeders-body');
                tbody.innerHTML = fData.feeders.map(f => `
                    <tr>
                        <td><strong>${this.escape(f.feeder_code)}</strong><br><small class="text-muted">${this.escape(f.name)}</small></td>
                        <td>${this.escape(f.ward_name)}</td>
                        <td><span class="status-pill status-${(f.reported_state || 'off').toLowerCase()}">${this.escape(f.reported_state || 'OFF')}</span></td>
                        <td>${f.voltage_v ? f.voltage_v + ' V' : '---'}</td>
                        <td>${f.power_kw ? f.power_kw + ' kW' : '---'}</td>
                        <td>${f.last_seen_at ? this.escape(f.last_seen_at) : '<span class="text-muted">Never</span>'}</td>
                    </tr>
                `).join('');
            }
        } catch (err) {
            console.error('Error loading dashboard', err);
        }
    }

    async loadFeeders() {
        const res = await fetch('/api/feeders');
        const data = await res.json();
        const tbody = document.getElementById('feeders-table-body');
        if (data.success && tbody) {
            tbody.innerHTML = data.feeders.map(f => `
                <tr>
                    <td><strong>${this.escape(f.feeder_code)}</strong></td>
                    <td>${this.escape(f.name)}</td>
                    <td>${this.escape(f.ward_name)}</td>
                    <td>${f.rated_capacity_kw} kW</td>
                    <td>${f.voltage_v ? f.voltage_v + ' V' : '---'}</td>
                    <td>${f.power_kw ? f.power_kw + ' kW' : '---'}</td>
                    <td><span class="status-pill status-${(f.reported_state || 'off').toLowerCase()}">${this.escape(f.reported_state || 'OFF')}</span></td>
                    <td>${f.last_seen_at || '<span class="text-muted">Offline</span>'}</td>
                </tr>
            `).join('');
        }
    }

    async loadLuminaires() {
        const res = await fetch('/api/luminaires');
        const data = await res.json();
        const tbody = document.getElementById('luminaires-table-body');
        if (data.success && tbody) {
            tbody.innerHTML = data.luminaires.map(l => `
                <tr>
                    <td><strong>${this.escape(l.pole_number)}</strong></td>
                    <td>${this.escape(l.panel_name)}</td>
                    <td>${this.escape(l.ward_name)}</td>
                    <td>${l.wattage}W ${this.escape(l.lamp_type)}</td>
                    <td>${l.dimmable ? 'Yes (0-10V)' : 'No'}</td>
                    <td><span class="status-pill status-${l.status}">${this.escape(l.status)}</span></td>
                    <td>${l.brightness_pct !== null ? l.brightness_pct + '%' : '---'}</td>
                    <td>${l.last_seen_at || '<span class="text-muted">Offline</span>'}</td>
                </tr>
            `).join('');
        }
    }

    async loadAlerts() {
        const res = await fetch('/api/alerts');
        const data = await res.json();
        const tbody = document.getElementById('alerts-table-body');
        if (data.success && tbody) {
            tbody.innerHTML = data.alerts.map(a => `
                <tr>
                    <td><span class="status-pill status-${a.severity}">${this.escape(a.severity.toUpperCase())}</span></td>
                    <td><code>${this.escape(a.fault_code)}</code></td>
                    <td>${this.escape(a.summary)}</td>
                    <td><span class="status-pill status-${a.status}">${this.escape(a.status)}</span></td>
                    <td>${this.escape(a.created_at)}</td>
                    <td>
                        ${a.status === 'active' ? `<button class="btn-secondary btn-sm" onclick="app.acknowledgeAlert(${a.id})">Acknowledge</button>` : ''}
                        ${a.status === 'acknowledged' ? `<button class="btn-primary btn-sm" onclick="app.resolveAlert(${a.id})">Resolve</button>` : ''}
                        ${a.status === 'resolved' ? `<small class="text-muted">Resolved</small>` : ''}
                    </td>
                </tr>
            `).join('');
        }
    }

    async acknowledgeAlert(alertId) {
        try {
            const res = await fetch('/api/alerts/acknowledge', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ alert_id: alertId })
            });
            const data = await res.json();
            if (data.success) {
                this.loadAlerts();
                this.loadDashboard();
            } else {
                alert(data.error || 'Failed to acknowledge alert');
            }
        } catch (e) {
            alert('Network failure');
        }
    }

    async resolveAlert(alertId) {
        const notes = prompt('Enter resolution notes / repair verification:', 'Fixed by field maintenance team');
        if (!notes) return;
        try {
            const res = await fetch('/api/alerts/resolve', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ alert_id: alertId, notes })
            });
            const data = await res.json();
            if (data.success) {
                this.loadAlerts();
                this.loadDashboard();
            }
        } catch (e) {
            alert('Resolution failed');
        }
    }

    async loadWorkOrders() {
        const res = await fetch('/api/work-orders');
        const data = await res.json();
        const tbody = document.getElementById('work-orders-table-body');
        if (data.success && tbody) {
            tbody.innerHTML = data.work_orders.map(wo => `
                <tr>
                    <td><strong>${this.escape(wo.order_code)}</strong></td>
                    <td>${this.escape(wo.title)}</td>
                    <td><span class="status-pill status-${wo.priority}">${this.escape(wo.priority)}</span></td>
                    <td>${this.escape(wo.assigned_to_name || 'Unassigned')}</td>
                    <td>${this.escape(wo.sla_deadline)}</td>
                    <td><span class="status-pill status-${wo.status}">${this.escape(wo.status)}</span></td>
                </tr>
            `).join('');
        }
    }

    async loadSmsLogs() {
        const res = await fetch('/api/sms-sandbox-logs');
        const data = await res.json();
        const el = document.getElementById('sms-log-content');
        if (data.success && el) {
            el.innerText = data.logs.join('') || 'No notification events logged yet.';
        }
    }

    async showAddFeederModal() {
        const res = await fetch('/api/wards');
        const data = await res.json();
        const select = document.getElementById('feeder-ward-select');
        select.innerHTML = data.wards.map(w => `<option value="${w.id}">${this.escape(w.name)} (${this.escape(w.ward_number)})</option>`).join('');
        document.getElementById('add-feeder-modal').style.display = 'flex';
    }

    async showAddLuminaireModal() {
        const res = await fetch('/api/panels');
        const data = await res.json();
        const select = document.getElementById('lum-panel-select');
        select.innerHTML = data.panels.map(p => `<option value="${p.id}">${this.escape(p.name)} [${this.escape(p.feeder_code)}]</option>`).join('');
        document.getElementById('add-luminaire-modal').style.display = 'flex';
    }

    closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    async handleCreateFeeder(e) {
        e.preventDefault();
        const payload = {
            ward_id: document.getElementById('feeder-ward-select').value,
            feeder_code: document.getElementById('feeder-code-input').value.trim(),
            name: document.getElementById('feeder-name-input').value.trim(),
            rated_capacity_kw: parseFloat(document.getElementById('feeder-cap-input').value)
        };
        const res = await fetch('/api/assets/feeder/create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            this.closeModal('add-feeder-modal');
            this.loadFeeders();
            this.loadDashboard();
            alert('New smart feeder panel registered!');
        } else {
            alert(data.error || 'Registration failed');
        }
    }

    async handleCreateLuminaire(e) {
        e.preventDefault();
        const payload = {
            panel_id: document.getElementById('lum-panel-select').value,
            pole_number: document.getElementById('lum-pole-input').value.trim(),
            wattage: parseInt(document.getElementById('lum-watt-input').value),
            dimmable: document.getElementById('lum-dimmable-input').checked
        };
        const res = await fetch('/api/assets/luminaire/create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            this.closeModal('add-luminaire-modal');
            this.loadLuminaires();
            this.loadDashboard();
            alert('New streetlight luminaire registered!');
        } else {
            alert(data.error || 'Registration failed');
        }
    }

    async triggerSimulatorAction(actionType) {
        const msgId = 'SIM-' + Date.now();
        let endpoint = '/api/telemetry/feeder';
        let payload = {};

        if (actionType === 'normal') {
            payload = {
                device_id: 'FDR-W03-FP01',
                message_id: msgId,
                v_r: 231.2, v_y: 230.8, v_b: 229.5,
                phase_status: { r: true, y: true, b: true },
                total_power_kw: 5.2,
                contactor_status: true
            };
        } else if (actionType === 'phase_failure') {
            payload = {
                device_id: 'FDR-W03-FP01',
                message_id: msgId,
                v_r: 135.0, v_y: 230.0, v_b: 228.0,
                phase_status: { r: false, y: true, b: true },
                total_power_kw: 3.1,
                contactor_status: true
            };
        } else if (actionType === 'voltage_surge') {
            payload = {
                device_id: 'FDR-W03-FP01',
                message_id: msgId,
                v_r: 284.5, v_y: 282.0, v_b: 279.0,
                phase_status: { r: true, y: true, b: true },
                total_power_kw: 6.8,
                contactor_status: true
            };
        } else if (actionType === 'lamp_failure') {
            endpoint = '/api/telemetry/luminaire';
            payload = {
                device_id: 'POLE-W03-001',
                message_id: msgId,
                lamp_status: false,
                brightness_pct: 0,
                power_w: 0.0,
                input_voltage_v: 230.5
            };
        } else if (actionType === 'dedup_test') {
            payload = {
                device_id: 'FDR-W03-FP01',
                message_id: 'DUPLICATE-MSG-TEST',
                v_r: 230.0,
                contactor_status: true
            };
            // Send first
            await fetch('/api/telemetry/feeder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            // Replay same
        }

        const res = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        alert(`Simulator Response: ${JSON.stringify(data, null, 2)}`);
        this.loadDashboard();
        this.loadAlerts();
    }

    async simulateTelemetry() {
        await this.triggerSimulatorAction('normal');
    }

    escape(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[m]));
    }
}
