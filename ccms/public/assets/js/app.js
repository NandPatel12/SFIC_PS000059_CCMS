// Gandhidham Municipal Streetlight CCMS - Fully Integrated Phases 1, 2, 3, 4 & 5
document.addEventListener('DOMContentLoaded', () => {
    window.app = new CCMSApp();
});

class CCMSApp {
    constructor() {
        this.currentUser = null;
        this.csrfToken = '';
        this.activeTab = 'dashboard';
        this.woFilter = 'all';
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
        document.querySelectorAll('.nav-item').forEach(el => {
            el.addEventListener('click', (e) => {
                const target = e.currentTarget.getAttribute('data-tab');
                if (target) {
                    this.switchTab(target);
                }
            });
        });

        const loginForm = document.getElementById('login-form');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => this.handleLogin(e));
        }

        const logoutBtn = document.getElementById('btn-logout');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => this.handleLogout());
        }

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
        const profileEl = document.getElementById('user-profile-info');
        if (profileEl && !this.currentUser) {
            profileEl.innerText = 'Not signed in';
        }
    }

    async handleLogin(e) {
        e.preventDefault();
        const username = document.getElementById('login-username').value.trim();
        const password = document.getElementById('login-password').value;
        const errorEl = document.getElementById('login-error');
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.innerText : 'Sign In to Console';

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerText = 'Signing in...';
        }
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
                errorEl.innerText = data.error || 'Invalid credentials';
            }
        } catch (err) {
            errorEl.innerText = 'Network connection error';
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerText = originalText;
            }
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
        } else if (this.activeTab === 'schedules') {
            await this.loadSchedules();
        } else if (this.activeTab === 'work-orders') {
            await this.loadWorkOrders();
        } else if (this.activeTab === 'reports') {
            await this.loadReports();
        } else if (this.activeTab === 'sms-logs') {
            await this.loadSmsLogs();
        }
    }

    // --- Tab: Dashboard ---
    async loadDashboard() {
        try {
            const res = await fetch('/api/dashboard/stats');
            const data = await res.json();
            if (data.success) {
                const s = data.stats;
                document.getElementById('stat-luminaires').innerText = `${s.active_luminaires} / ${s.total_luminaires}`;
                document.getElementById('stat-alerts').innerText = s.active_alerts;
                document.getElementById('stat-orders').innerText = s.open_work_orders;
                document.getElementById('stat-energy').innerText = `${s.estimated_today_kwh} kWh`;
                const cost = Math.round(s.estimated_today_kwh * 7.10);
                document.getElementById('stat-cost').innerText = `Est. Cost: ₹${cost.toLocaleString('en-IN')}`;
            }

            const fRes = await fetch('/api/feeders');
            const fData = await fRes.json();
            if (fData.success) {
                const tbody = document.getElementById('dashboard-feeders-table-body');
                tbody.innerHTML = fData.feeders.map(f => `
                    <tr>
                        <td><strong>${this.escape(f.feeder_code)}</strong><br><small style="color: #64748b;">${this.escape(f.name)}</small></td>
                        <td>${this.escape(f.ward_name || 'Ward')}</td>
                        <td><span class="status-pill status-${(f.reported_state || 'off').toLowerCase()}">${this.escape(f.reported_state || 'OFF')}</span></td>
                        <td>${f.voltage_v ? f.voltage_v + ' V' : '---'}</td>
                        <td>${f.power_kw ? f.power_kw + ' kW' : '---'}</td>
                        <td>${f.last_seen_at ? this.escape(f.last_seen_at) : '<span style="color: #94a3b8;">Never</span>'}</td>
                    </tr>
                `).join('');
            }
        } catch (err) {
            console.error('Error loading dashboard', err);
        }
    }

    // --- Tab: Feeders ---
    async loadFeeders() {
        const res = await fetch('/api/feeders');
        const data = await res.json();
        const tbody = document.getElementById('feeders-table-body');
        if (data.success && tbody) {
            tbody.innerHTML = data.feeders.map(f => `
                <tr>
                    <td><strong>${this.escape(f.feeder_code)}</strong></td>
                    <td>${this.escape(f.name)}</td>
                    <td>${this.escape(f.ward_name || 'Ward')}</td>
                    <td>${f.rated_capacity_kw} kW</td>
                    <td><span class="status-pill status-${(f.reported_state || 'off').toLowerCase()}">${this.escape(f.reported_state || 'OFF')}</span></td>
                    <td>${f.voltage_v ? f.voltage_v + ' V' : '---'}</td>
                    <td>${f.power_kw ? f.power_kw + ' kW' : '---'}</td>
                    <td><span class="status-pill status-${f.status}">${this.escape(f.status)}</span></td>
                    <td>
                        <button class="btn-secondary" style="padding: 3px 8px; font-size: 0.75rem;" onclick="app.quickOverrideFeeder(${f.id}, '${this.escape(f.feeder_code)}')">⚡ Override</button>
                    </td>
                </tr>
            `).join('');
        }
    }

    // --- Tab: Luminaires ---
    async loadLuminaires() {
        const res = await fetch('/api/luminaires');
        const data = await res.json();
        const tbody = document.getElementById('luminaires-table-body');
        if (data.success && tbody) {
            tbody.innerHTML = data.luminaires.map(l => `
                <tr>
                    <td><strong>${this.escape(l.pole_number)}</strong></td>
                    <td>${this.escape(l.feeder_code || '')} &bull; ${this.escape(l.panel_name || '')}</td>
                    <td>${this.escape(l.ward_name || 'Ward')}</td>
                    <td>${l.wattage}W ${this.escape(l.lamp_type)}</td>
                    <td><span class="status-pill status-${(l.commanded_state || 'off').toLowerCase()}">${this.escape(l.commanded_state || 'OFF')}</span></td>
                    <td><span class="status-pill status-${(l.reported_state || 'off').toLowerCase()}">${this.escape(l.reported_state || 'OFF')}</span></td>
                    <td>${l.brightness_pct !== null ? l.brightness_pct + '%' : '100%'}</td>
                    <td>${l.voltage_v ? l.voltage_v + ' V' : '---'}</td>
                    <td><span class="status-pill status-${l.status}">${this.escape(l.status)}</span></td>
                    <td>
                        <button class="btn-secondary" style="padding: 3px 8px; font-size: 0.75rem;" onclick="app.quickDimLuminaire(${l.id})">💡 Dim</button>
                    </td>
                </tr>
            `).join('');
        }
    }

    // --- Tab: Alerts ---
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
                        <div style="display: flex; gap: 6px;">
                            ${a.status === 'active' ? `<button class="btn-secondary" style="padding: 3px 8px; font-size: 0.75rem;" onclick="app.acknowledgeAlert(${a.id})">Acknowledge</button>` : ''}
                            ${a.status === 'active' || a.status === 'acknowledged' ? `<button class="btn-primary" style="padding: 3px 8px; font-size: 0.75rem;" onclick="app.createWorkOrderFromAlert(${a.id})">+ Work Order</button>` : ''}
                            ${a.status === 'acknowledged' ? `<button class="btn-secondary" style="padding: 3px 8px; font-size: 0.75rem;" onclick="app.resolveAlert(${a.id})">Resolve</button>` : ''}
                            ${a.status === 'resolved' ? `<span style="font-size: 0.75rem; color: #16a34a; font-weight: 600;">✓ Resolved</span>` : ''}
                        </div>
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
            alert('Network error');
        }
    }

    async resolveAlert(alertId) {
        const notes = prompt('Enter resolution notes / repair evidence summary:', 'Verified and resolved by line supervisor');
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

    // --- Tab: Lighting Schedules (Phase 4) ---
    async loadSchedules() {
        try {
            // 1. Astronomical Solar Position
            const astroRes = await fetch('/api/schedules/astronomical');
            const astroData = await astroRes.json();
            if (astroData.success && astroData.astronomical) {
                const a = astroData.astronomical;
                document.getElementById('astro-sunset').innerText = a.sunset + ' IST';
                document.getElementById('astro-sunrise').innerText = a.sunrise + ' IST';
                document.getElementById('astro-dusk').innerText = a.civil_twilight_end + ' IST';
                document.getElementById('astro-dawn').innerText = a.civil_twilight_begin + ' IST';
            }

            // 2. Active Schedules
            const schedRes = await fetch('/api/schedules');
            const schedData = await schedRes.json();
            const schedTbody = document.getElementById('schedules-table-body');
            if (schedData.success && schedTbody) {
                schedTbody.innerHTML = schedData.schedules.map(s => {
                    const scope = s.ward_name ? s.ward_name : 'Global (All Municipal Wards)';
                    const timing = s.is_astronomical ? `Astronomical (Sunset: ${s.sunset_offset_mins >= 0 ? '+' : ''}${s.sunset_offset_mins}m)` : `${s.fixed_on_time || '18:30'} — ${s.fixed_off_time || '06:00'}`;
                    const dimmingStr = s.dimming_profile && s.dimming_profile.length > 0 
                        ? s.dimming_profile.map(d => `${d.start}-${d.end} @ ${d.brightness_pct}%`).join(', ')
                        : '100% Constant';
                    return `
                        <tr>
                            <td><strong>${this.escape(s.name)}</strong></td>
                            <td>${this.escape(scope)}</td>
                            <td><span class="status-pill status-${s.is_astronomical ? 'active' : 'dispatched'}">${s.is_astronomical ? '☀️ Astronomical' : '⏰ Fixed Clock'}</span></td>
                            <td>${this.escape(timing)}</td>
                            <td><code>${this.escape(dimmingStr)}</code></td>
                            <td><span class="status-pill status-${s.status}">${this.escape(s.status)}</span></td>
                            <td>${this.escape(s.approver_name || 'Er. Rajesh Patel (EE)')}</td>
                        </tr>
                    `;
                }).join('');
            }

            // 3. Device Commands Queue
            const cmdRes = await fetch('/api/commands');
            const cmdData = await cmdRes.json();
            const cmdTbody = document.getElementById('commands-table-body');
            if (cmdData.success && cmdTbody) {
                cmdTbody.innerHTML = cmdData.commands.map(c => `
                    <tr>
                        <td><code>${this.escape(c.command_code)}</code></td>
                        <td>${this.escape(c.target_type.toUpperCase())} #${c.target_id}</td>
                        <td><strong>${this.escape(c.command_type)}</strong></td>
                        <td><small>${this.escape(c.reason || 'Automated scheduled command')}</small></td>
                        <td><span class="status-pill status-${c.status}">${this.escape(c.status)}</span></td>
                        <td>${this.escape(c.created_at)}</td>
                        <td><small>${this.escape(c.expires_at || '---')}</small></td>
                        <td>
                            ${c.status === 'dispatched' ? `<button class="btn-secondary" style="padding: 2px 7px; font-size: 0.72rem;" onclick="app.acknowledgeCommand(${c.id})">⚡ Simulate ACK</button>` : `<span style="font-size: 0.75rem; color: #16a34a;">✓ Acknowledged</span>`}
                        </td>
                    </tr>
                `).join('');
            }
        } catch (err) {
            console.error('Error loading schedules', err);
        }
    }

    toggleScheduleMode(val) {
        const fixedEl = document.getElementById('sched-fixed-times');
        if (fixedEl) fixedEl.style.display = val === 'fixed' ? 'flex' : 'none';
    }

    async handleCreateSchedule(e) {
        e.preventDefault();
        const isAstro = document.getElementById('sched-mode').value === 'astro';
        const dimStart = document.getElementById('sched-dim-start').value;
        const dimEnd = document.getElementById('sched-dim-end').value;
        const dimPct = parseInt(document.getElementById('sched-dim-pct').value);

        const payload = {
            name: document.getElementById('sched-name').value.trim(),
            ward_id: document.getElementById('sched-ward').value || null,
            is_astronomical: isAstro,
            fixed_on_time: isAstro ? null : document.getElementById('sched-fixed-on').value,
            fixed_off_time: isAstro ? null : document.getElementById('sched-fixed-off').value,
            dimming_profile: [{ start: dimStart, end: dimEnd, brightness_pct: dimPct }]
        };

        const res = await fetch('/api/schedules/create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            this.closeModal('create-schedule-modal');
            this.loadSchedules();
            alert('Lighting schedule created and approved for deployment!');
        } else {
            alert(data.error || 'Schedule creation failed');
        }
    }

    quickOverrideFeeder(feederId, code) {
        this.openModal('override-modal');
        const sel = document.getElementById('ovr-feeder');
        if (sel) sel.value = feederId;
    }

    quickDimLuminaire(lumId) {
        const pct = prompt('Enter dimming brightness percentage (10 to 100%):', '60');
        if (!pct) return;
        fetch('/api/commands/dispatch', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                target_type: 'luminaire',
                target_id: lumId,
                command_type: 'SET_DIMMING',
                parameters: { brightness_pct: parseInt(pct) },
                reason: `Manual dimming adjustment to ${pct}%`
            })
        }).then(r => r.json()).then(data => {
            if (data.success) {
                alert(`Dimming command dispatched! Code: ${data.command.command_code}`);
                this.loadLuminaires();
            }
        });
    }

    async handleManualOverride(e) {
        e.preventDefault();
        const payload = {
            target_type: 'feeder',
            target_id: parseInt(document.getElementById('ovr-feeder').value),
            state: document.getElementById('ovr-state').value,
            brightness_pct: parseInt(document.getElementById('ovr-bright').value),
            duration_mins: parseInt(document.getElementById('ovr-duration').value),
            reason: document.getElementById('ovr-reason').value.trim()
        };

        const res = await fetch('/api/commands/override', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            this.closeModal('override-modal');
            this.loadSchedules();
            this.loadDashboard();
            alert(`Manual override command dispatched! Auto-expires in ${payload.duration_mins} mins.`);
        } else {
            alert(data.error || 'Override failed');
        }
    }

    async acknowledgeCommand(cmdId) {
        const res = await fetch('/api/commands/acknowledge', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ command_id: cmdId })
        });
        const data = await res.json();
        if (data.success) {
            this.loadSchedules();
        }
    }

    // --- Tab: Work Orders (Phase 5) ---
    async loadWorkOrders() {
        const url = this.woFilter && this.woFilter !== 'all' ? `/api/work-orders?status=${this.woFilter}` : '/api/work-orders';
        const res = await fetch(url);
        const data = await res.json();
        const tbody = document.getElementById('work-orders-table-body');
        if (data.success && tbody) {
            if (!data.work_orders || data.work_orders.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; color: #64748b;">No work orders found in this filter.</td></tr>';
                return;
            }
            tbody.innerHTML = data.work_orders.map(wo => `
                <tr>
                    <td><code>${this.escape(wo.order_code)}</code></td>
                    <td><strong>${this.escape(wo.title)}</strong></td>
                    <td>${this.escape(wo.asset_type.toUpperCase())} #${wo.asset_id}</td>
                    <td><span class="prio-pill prio-${wo.priority}">${this.escape(wo.priority)}</span></td>
                    <td>${this.escape(wo.assigned_name || wo.assigned_to_name || 'Apex Electrical')}</td>
                    <td><small>${this.escape(wo.sla_deadline)}</small></td>
                    <td><small style="color: #475569;">${this.escape(wo.repair_evidence_notes || 'Pending field notes')}</small></td>
                    <td><span class="status-pill status-${wo.status}">${this.escape(wo.status.replace('_', ' '))}</span></td>
                    <td>
                        <div style="display: flex; gap: 6px;">
                            <button class="btn-primary" style="padding: 3px 8px; font-size: 0.75rem;" onclick="app.openEditWorkOrderModal(${wo.id}, '${this.escape(wo.order_code)}', '${wo.status}', '${this.escape(wo.repair_evidence_notes || '')}')">Update / Evidence</button>
                            <button class="btn-secondary" style="padding: 3px 8px; font-size: 0.75rem;" onclick="app.viewWorkOrderEvents(${wo.id}, '${this.escape(wo.order_code)}')">Timeline</button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }
    }

    filterWorkOrders(status) {
        this.woFilter = status;
        this.loadWorkOrders();
    }

    async createWorkOrderFromAlert(alertId) {
        const title = prompt('Enter repair task title or leave blank for default:');
        const res = await fetch('/api/work-orders/create-from-alert', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                alert_id: alertId,
                priority: 'high',
                title: title || undefined
            })
        });
        const data = await res.json();
        if (data.success) {
            alert(`Work order generated! Code: ${data.work_order.order_code}`);
            this.loadAlerts();
            this.loadDashboard();
            this.switchTab('work-orders');
        } else {
            alert(data.error || 'Failed to generate work order');
        }
    }

    openEditWorkOrderModal(id, code, status, evidence) {
        document.getElementById('edit-wo-id').value = id;
        document.getElementById('edit-wo-code').value = code;
        document.getElementById('edit-wo-status').value = status;
        document.getElementById('edit-wo-evidence').value = evidence || '';
        document.getElementById('edit-wo-notes').value = '';
        this.openModal('update-wo-modal');
    }

    async handleUpdateWorkOrder(e) {
        e.preventDefault();
        const id = parseInt(document.getElementById('edit-wo-id').value);
        const status = document.getElementById('edit-wo-status').value;
        const evidence = document.getElementById('edit-wo-evidence').value.trim();
        const notes = document.getElementById('edit-wo-notes').value.trim();

        const res = await fetch('/api/work-orders/update-status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                work_order_id: id,
                status: status,
                repair_evidence_notes: evidence,
                notes: notes || undefined
            })
        });
        const data = await res.json();
        if (data.success) {
            this.closeModal('update-wo-modal');
            this.loadWorkOrders();
            this.loadDashboard();
            alert('Work order updated successfully!');
        } else {
            alert(data.error || 'Update failed');
        }
    }

    async viewWorkOrderEvents(woId, code) {
        document.getElementById('wo-events-title').innerText = `Timeline: ${code}`;
        const container = document.getElementById('wo-events-list');
        container.innerHTML = 'Loading timeline...';
        this.openModal('wo-events-modal');

        const res = await fetch(`/api/work-orders/events?work_order_id=${woId}`);
        const data = await res.json();
        if (data.success && data.events) {
            if (data.events.length === 0) {
                container.innerHTML = '<p style="color: #64748b;">No timeline entries recorded yet.</p>';
                return;
            }
            container.innerHTML = data.events.map(ev => `
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div style="font-weight: 600; font-size: 0.85rem;">Status: <span class="status-pill status-${ev.to_status}">${this.escape(ev.to_status)}</span></div>
                    <div style="font-size: 0.8rem; color: #475569; margin: 2px 0;">${this.escape(ev.notes || 'Status updated')}</div>
                    <div style="font-size: 0.72rem; color: #94a3b8;">${this.escape(ev.created_at)} &bull; ${this.escape(ev.user_name || 'System Operator')}</div>
                </div>
            `).join('');
        }
    }

    // --- Tab: Energy & Reports (Phase 5) ---
    async loadReports() {
        try {
            const res = await fetch('/api/reports/energy');
            const data = await res.json();
            if (data.success && data.energy) {
                const s = data.energy.summary;
                document.getElementById('report-load-kw').innerText = `${s.current_total_load_kw} kW`;
                document.getElementById('report-today-kwh').innerText = `${s.estimated_today_kwh} kWh`;
                document.getElementById('report-today-cost').innerText = `₹ ${s.estimated_today_cost_inr.toLocaleString('en-IN')} daily`;
                document.getElementById('report-monthly-cost').innerText = `₹ ${s.estimated_monthly_cost_inr.toLocaleString('en-IN')}`;
                document.getElementById('report-savings-pct').innerText = `${s.energy_savings_pct} %`;

                const tbody = document.getElementById('reports-energy-table-body');
                if (tbody && data.energy.feeders) {
                    tbody.innerHTML = data.energy.feeders.map(f => `
                        <tr>
                            <td><strong>${this.escape(f.feeder_code)}</strong></td>
                            <td>${this.escape(f.feeder_name)}</td>
                            <td>${this.escape(f.ward_name || 'Ward')}</td>
                            <td>${f.rated_capacity_kw} kW</td>
                            <td>${f.current_kw} kW</td>
                            <td>${f.daily_kwh} kWh</td>
                            <td><strong>₹ ${f.daily_cost_inr.toLocaleString('en-IN')}</strong></td>
                            <td>${f.baseline_kwh} kWh</td>
                            <td><span style="color: #16a34a; font-weight: 600;">${f.savings_pct}%</span></td>
                        </tr>
                    `).join('');
                }
            }

            const kpiRes = await fetch('/api/reports/kpi');
            const kpiData = await kpiRes.json();
            if (kpiData.success && kpiData.kpis) {
                const k = kpiData.kpis;
                document.getElementById('kpi-mtta').innerText = `${k.mean_time_to_acknowledge_mins} mins`;
                document.getElementById('kpi-mttr').innerText = `${k.mean_time_to_repair_hours} hours`;
                document.getElementById('kpi-sla').innerText = `${k.sla_compliance_pct} %`;
                document.getElementById('kpi-resolved').innerText = `${k.resolved_alerts} / ${k.total_alerts}`;
            }
        } catch (err) {
            console.error('Error loading reports', err);
        }
    }

    exportEnergyCsv() {
        window.open('/api/reports/export/energy', '_blank');
    }

    exportWorkOrdersCsv() {
        window.open('/api/reports/export/work-orders', '_blank');
    }

    // --- Tab: SMS Logs ---
    async loadSmsLogs() {
        const res = await fetch('/api/sms-sandbox-logs');
        const data = await res.json();
        const el = document.getElementById('sms-log-container');
        if (data.success && el) {
            el.innerText = data.logs.join('') || 'No notification events logged yet.';
        }
    }

    openModal(modalId) {
        if (modalId === 'add-feeder-modal') this.populateFeederModal();
        if (modalId === 'add-luminaire-modal') this.populateLuminaireModal();
        if (modalId === 'override-modal') this.populateOverrideModal();
        if (modalId === 'create-schedule-modal') this.populateScheduleModal();
        const m = document.getElementById(modalId);
        if (m) m.style.display = 'flex';
    }

    closeModal(modalId) {
        const m = document.getElementById(modalId);
        if (m) m.style.display = 'none';
    }

    async populateFeederModal() {
        const res = await fetch('/api/wards');
        const data = await res.json();
        const sel = document.getElementById('feeder-ward-select');
        if (data.success && sel) {
            sel.innerHTML = data.wards.map(w => `<option value="${w.id}">${this.escape(w.name)} (${this.escape(w.ward_number)})</option>`).join('');
        }
    }

    async populateLuminaireModal() {
        const res = await fetch('/api/panels');
        const data = await res.json();
        const sel = document.getElementById('lum-panel-select');
        if (data.success && sel) {
            sel.innerHTML = data.panels.map(p => `<option value="${p.id}">${this.escape(p.name)} [${this.escape(p.feeder_code)}]</option>`).join('');
        }
    }

    async populateOverrideModal() {
        const res = await fetch('/api/feeders');
        const data = await res.json();
        const sel = document.getElementById('ovr-feeder');
        if (data.success && sel) {
            sel.innerHTML = data.feeders.map(f => `<option value="${f.id}">${this.escape(f.feeder_code)} - ${this.escape(f.name)}</option>`).join('');
        }
    }

    async populateScheduleModal() {
        const res = await fetch('/api/wards');
        const data = await res.json();
        const sel = document.getElementById('sched-ward');
        if (data.success && sel) {
            sel.innerHTML = '<option value="">All Municipal Wards (Global)</option>' + data.wards.map(w => `<option value="${w.id}">${this.escape(w.name)}</option>`).join('');
        }
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
            alert('New feeder registered!');
        } else {
            alert(data.error || 'Failed');
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
            alert('New luminaire registered!');
        } else {
            alert(data.error || 'Failed');
        }
    }

    // --- Simulator Actions ---
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
        } else if (actionType === 'phase_fail') {
            payload = {
                device_id: 'FDR-W03-FP01',
                message_id: msgId,
                v_r: 135.0, v_y: 230.0, v_b: 228.0,
                phase_status: { r: false, y: true, b: true },
                total_power_kw: 3.1,
                contactor_status: true
            };
        } else if (actionType === 'surge') {
            payload = {
                device_id: 'FDR-W03-FP01',
                message_id: msgId,
                v_r: 284.5, v_y: 282.0, v_b: 279.0,
                phase_status: { r: true, y: true, b: true },
                total_power_kw: 6.8,
                contactor_status: true
            };
        } else if (actionType === 'lamp_fail') {
            endpoint = '/api/telemetry/luminaire';
            payload = {
                device_id: 'POLE-W03-001',
                message_id: msgId,
                lamp_status: false,
                brightness_pct: 0,
                power_w: 0.0,
                input_voltage_v: 230.5
            };
        } else if (actionType === 'dedup') {
            payload = {
                device_id: 'FDR-W03-FP01',
                message_id: 'DUPLICATE-MSG-TEST',
                v_r: 230.0,
                contactor_status: true
            };
            await fetch('/api/telemetry/feeder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
        }

        const res = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        const out = document.getElementById('simulator-output');
        if (out) {
            out.innerText = `[${new Date().toLocaleTimeString()}] Sent ${endpoint}:\n` + JSON.stringify(data, null, 2);
        }
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
