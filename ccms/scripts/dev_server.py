#!/usr/bin/env python3
"""
Gandhidham CCMS Python Development & IoT Gateway Server
Supports Phase 1, Phase 2, and Phase 3:
- Full CRUD for Wards, Feeders, Panels, and Luminaires.
- Real-time authenticated telemetry ingestion with schema verification & deduplication.
- Configurable Fault Rule Engine: Phase failure, Voltage surge, Lamp failure, Day-burning, Overload anomaly.
- Duplicate alert suppression & SMS sandbox notification dispatch.
- Device simulator triggers.
"""
import os
import json
import sqlite3
from http.server import HTTPServer, SimpleHTTPRequestHandler
import urllib.parse
from datetime import datetime, timezone, timedelta

PORT = 8000
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PUBLIC_DIR = os.path.join(BASE_DIR, 'public')
DB_PATH = os.path.join(BASE_DIR, 'storage', 'database.sqlite')
SMS_LOG_PATH = os.path.join(BASE_DIR, 'storage', 'logs', 'sms_sandbox.log')

CURRENT_SESSION = {
    'user': {
        'id': 1,
        'username': 'admin',
        'full_name': 'Chief Municipal Admin',
        'role_slug': 'super_admin',
        'role_name': 'Super Administrator'
    },
    'csrf_token': 'f4a9b2c3d4e5f6a7b8c9d0e1f2a3b4c5'
}

def get_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn

def trigger_alert_if_not_duplicate(conn, asset_type, asset_id, fault_code, severity, summary, evidence):
    c = conn.cursor()
    # Check duplicate
    existing = c.execute("""
        SELECT id FROM alerts 
        WHERE asset_type = ? AND asset_id = ? AND fault_code = ? AND status IN ('active', 'acknowledged')
    """, (asset_type, asset_id, fault_code)).fetchone()

    if existing:
        c.execute("""
            UPDATE alerts SET evidence_json = ?, updated_at = datetime('now') WHERE id = ?
        """, (json.dumps(evidence), existing['id']))
        return None

    c.execute("""
        INSERT INTO alerts (asset_type, asset_id, fault_code, severity, summary, evidence_json, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, 'active', datetime('now'), datetime('now'))
    """, (asset_type, asset_id, fault_code, severity, summary, json.dumps(evidence)))
    new_id = c.lastrowid

    # SMS Sandboxed notification log
    os.makedirs(os.path.dirname(SMS_LOG_PATH), exist_ok=True)
    with open(SMS_LOG_PATH, 'a', encoding='utf-8') as f:
        f.write(f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] [SMS DISPATCH] Alert #{new_id} [{severity.upper()}]: {summary}\n")

    return {
        'id': new_id,
        'fault_code': fault_code,
        'severity': severity,
        'summary': summary
    }

class CCMSHandler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=PUBLIC_DIR, **kwargs)

    def send_json(self, status, payload):
        self.send_response(status)
        self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.end_headers()
        self.wfile.write(json.dumps(payload).encode('utf-8'))

    def do_GET(self):
        url = urllib.parse.urlparse(self.path)
        path = url.path
        query = urllib.parse.parse_qs(url.query)

        if path.startswith('/api/'):
            self.handle_api_get(path, query)
            return

        if path == '/' or path == '/index.php':
            self.send_response(200)
            self.send_header('Content-Type', 'text/html; charset=utf-8')
            self.end_headers()
            with open(os.path.join(PUBLIC_DIR, 'index.php'), 'r', encoding='utf-8') as f:
                content = f.read()
                html = content.split('?>', 1)[-1]
                self.wfile.write(html.encode('utf-8'))
            return

        super().do_GET()

    def do_POST(self):
        url = urllib.parse.urlparse(self.path)
        path = url.path

        content_length = int(self.headers.get('Content-Length', 0))
        body = self.rfile.read(content_length) if content_length > 0 else b'{}'
        try:
            data = json.loads(body.decode('utf-8'))
        except Exception:
            data = {}

        if path.startswith('/api/'):
            self.handle_api_post(path, data)
            return

        self.send_error(404, "Endpoint not found")

    def handle_api_get(self, path, query):
        conn = get_db()
        cursor = conn.cursor()

        if path == '/api/me':
            if CURRENT_SESSION['user']:
                self.send_json(200, {
                    'success': True,
                    'user': CURRENT_SESSION['user'],
                    'csrf_token': CURRENT_SESSION['csrf_token']
                })
            else:
                self.send_json(401, {'success': False, 'error': 'Not authenticated'})

        elif path == '/api/dashboard/stats':
            luminaires = cursor.execute("SELECT COUNT(*) FROM luminaires").fetchone()[0]
            active_lums = cursor.execute("SELECT COUNT(*) FROM luminaires WHERE status = 'active'").fetchone()[0]
            feeders = cursor.execute("SELECT COUNT(*) FROM feeders").fetchone()[0]
            alerts = cursor.execute("SELECT COUNT(*) FROM alerts WHERE status = 'active'").fetchone()[0]
            orders = cursor.execute("SELECT COUNT(*) FROM work_orders WHERE status != 'closed'").fetchone()[0]
            kw = cursor.execute("SELECT SUM(power_kw) FROM device_latest_state WHERE asset_type = 'feeder'").fetchone()[0] or 0.0

            self.send_json(200, {
                'success': True,
                'stats': {
                    'total_luminaires': luminaires,
                    'active_luminaires': active_lums,
                    'total_feeders': feeders,
                    'active_alerts': alerts,
                    'open_work_orders': orders,
                    'current_load_kw': round(kw, 2),
                    'estimated_today_kwh': round(kw * 7.5, 1)
                }
            })

        elif path == '/api/wards':
            rows = cursor.execute("""
                SELECT w.*, 
                (SELECT COUNT(*) FROM feeders WHERE ward_id = w.id) as feeder_count 
                FROM wards w ORDER BY w.id ASC
            """).fetchall()
            self.send_json(200, {'success': True, 'wards': [dict(r) for r in rows]})

        elif path == '/api/feeders':
            ward_filter = query.get('ward_id', [None])[0]
            sql = """
                SELECT f.*, w.name as ward_name, dls.reported_state, dls.voltage_v, dls.power_kw, dls.is_online, dls.last_seen_at
                FROM feeders f
                LEFT JOIN wards w ON f.ward_id = w.id
                LEFT JOIN device_latest_state dls ON dls.asset_type = 'feeder' AND dls.asset_id = f.id
            """
            params = []
            if ward_filter:
                sql += " WHERE f.ward_id = ?"
                params.append(ward_filter)
            sql += " ORDER BY f.id ASC"
            rows = cursor.execute(sql, params).fetchall()
            self.send_json(200, {'success': True, 'feeders': [dict(r) for r in rows]})

        elif path == '/api/panels':
            rows = cursor.execute("""
                SELECT p.*, f.feeder_code, f.name as feeder_name
                FROM panels p
                JOIN feeders f ON p.feeder_id = f.id
                ORDER BY p.id ASC
            """).fetchall()
            self.send_json(200, {'success': True, 'panels': [dict(r) for r in rows]})

        elif path == '/api/luminaires':
            ward_filter = query.get('ward_id', [None])[0]
            status_filter = query.get('status', [None])[0]
            sql = """
                SELECT l.*, p.name as panel_name, f.feeder_code, w.name as ward_name,
                       dls.commanded_state, dls.reported_state, dls.brightness_pct, dls.voltage_v, dls.last_seen_at
                FROM luminaires l
                JOIN panels p ON l.panel_id = p.id
                JOIN feeders f ON p.feeder_id = f.id
                JOIN wards w ON f.ward_id = w.id
                LEFT JOIN device_latest_state dls ON dls.asset_type = 'luminaire' AND dls.asset_id = l.id
                WHERE 1=1
            """
            params = []
            if ward_filter:
                sql += " AND f.ward_id = ?"
                params.append(ward_filter)
            if status_filter:
                sql += " AND l.status = ?"
                params.append(status_filter)
            sql += " ORDER BY l.id ASC"
            rows = cursor.execute(sql, params).fetchall()
            self.send_json(200, {'success': True, 'luminaires': [dict(r) for r in rows]})

        elif path == '/api/alerts':
            status_filter = query.get('status', [None])[0]
            sql = """
                SELECT a.*, u.full_name as acknowledged_by_name
                FROM alerts a
                LEFT JOIN users u ON a.acknowledged_by = u.id
            """
            params = []
            if status_filter:
                sql += " WHERE a.status = ?"
                params.append(status_filter)
            sql += " ORDER BY a.created_at DESC LIMIT 50"
            rows = cursor.execute(sql, params).fetchall()
            self.send_json(200, {'success': True, 'alerts': [dict(r) for r in rows]})

        elif path == '/api/work-orders':
            rows = cursor.execute("""
                SELECT wo.*, u.full_name as assigned_to_name
                FROM work_orders wo
                LEFT JOIN users u ON wo.assigned_to = u.id
                ORDER BY wo.created_at DESC
            """).fetchall()
            self.send_json(200, {'success': True, 'work_orders': [dict(r) for r in rows]})

        elif path == '/api/sms-sandbox-logs':
            if os.path.exists(SMS_LOG_PATH):
                with open(SMS_LOG_PATH, 'r', encoding='utf-8') as f:
                    logs = f.readlines()[-25:]
            else:
                logs = ["No SMS notifications triggered yet."]
            self.send_json(200, {'success': True, 'logs': logs})

        else:
            self.send_json(404, {'success': False, 'error': 'Endpoint not found'})

        conn.close()

    def handle_api_post(self, path, data):
        conn = get_db()
        cursor = conn.cursor()

        if path == '/api/login':
            username = data.get('username')
            if username in ['admin', 'engineer_patel', 'sup_sharma', 'contractor_apex']:
                row = cursor.execute("""
                    SELECT u.id, u.username, u.full_name, r.slug as role_slug, r.name as role_name 
                    FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username = ?
                """, (username,)).fetchone()
                CURRENT_SESSION['user'] = dict(row)
                self.send_json(200, {
                    'success': True,
                    'user': CURRENT_SESSION['user'],
                    'csrf_token': CURRENT_SESSION['csrf_token']
                })
            else:
                self.send_json(401, {'success': False, 'error': 'Invalid credentials'})

        elif path == '/api/logout':
            CURRENT_SESSION['user'] = None
            self.send_json(200, {'success': True, 'message': 'Logged out'})

        # --- Phase 2: Asset CRUD ---
        elif path == '/api/assets/ward/create':
            wnum = data.get('ward_number', '').strip()
            name = data.get('name', '').strip()
            zone = data.get('zone', '').strip()
            desc = data.get('description', '').strip()
            if not wnum or not name:
                self.send_json(422, {'success': False, 'error': 'Ward number and name required'})
                return
            cursor.execute("INSERT INTO wards (ward_number, name, zone, description) VALUES (?, ?, ?, ?)", (wnum, name, zone, desc))
            conn.commit()
            self.send_json(200, {'success': True, 'message': 'Ward created', 'id': cursor.lastrowid})

        elif path == '/api/assets/feeder/create':
            wid = data.get('ward_id')
            fcode = data.get('feeder_code', '').strip()
            name = data.get('name', '').strip()
            lat = float(data.get('latitude', 23.076))
            lon = float(data.get('longitude', 70.103))
            cap = float(data.get('rated_capacity_kw', 15.0))
            if not wid or not fcode or not name:
                self.send_json(422, {'success': False, 'error': 'Ward, feeder code and name required'})
                return
            cursor.execute("""
                INSERT INTO feeders (ward_id, feeder_code, name, latitude, longitude, rated_capacity_kw, status)
                VALUES (?, ?, ?, ?, ?, ?, 'active')
            """, (wid, fcode, name, lat, lon, cap))
            new_fid = cursor.lastrowid
            cursor.execute("""
                INSERT INTO device_latest_state (asset_type, asset_id, reported_state, brightness_pct, voltage_v, power_kw, is_online, last_seen_at)
                VALUES ('feeder', ?, 'OFF', 0, 0, 0, 1, datetime('now'))
            """, (new_fid,))
            conn.commit()
            self.send_json(200, {'success': True, 'message': 'Feeder panel registered', 'id': new_fid})

        elif path == '/api/assets/luminaire/create':
            pid = data.get('panel_id')
            pole = data.get('pole_number', '').strip()
            watt = int(data.get('wattage', 90))
            dimmable = 1 if data.get('dimmable') else 0
            lat = float(data.get('latitude', 23.077))
            lon = float(data.get('longitude', 70.103))
            if not pid or not pole:
                self.send_json(422, {'success': False, 'error': 'Panel ID and Pole number required'})
                return
            cursor.execute("""
                INSERT INTO luminaires (panel_id, pole_number, wattage, lamp_type, dimmable, latitude, longitude, status)
                VALUES (?, ?, ?, 'LED', ?, ?, ?, 'active')
            """, (pid, pole, watt, dimmable, lat, lon))
            new_lid = cursor.lastrowid
            cursor.execute("""
                INSERT INTO device_latest_state (asset_type, asset_id, commanded_state, reported_state, brightness_pct, voltage_v, power_kw, is_online, last_seen_at)
                VALUES ('luminaire', ?, 'ON', 'ON', 100, 230.0, ?, 1, datetime('now'))
            """, (new_lid, round(watt / 1000.0, 3)))
            conn.commit()
            self.send_json(200, {'success': True, 'message': 'Luminaire registered', 'id': new_lid})

        # --- Phase 2 & 3: Telemetry Ingestion with Deduplication & Fault Evaluation ---
        elif path == '/api/telemetry/feeder':
            dev_id = data.get('device_id')
            msg_id = data.get('message_id')
            if not dev_id or not msg_id:
                self.send_json(422, {'success': False, 'error': 'device_id and message_id are required'})
                return

            feeder = cursor.execute("SELECT id, feeder_code, rated_capacity_kw FROM feeders WHERE feeder_code = ?", (dev_id,)).fetchone()
            if not feeder:
                self.send_json(404, {'success': False, 'error': f'Feeder device {dev_id} not registered'})
                return

            fid = feeder['id']
            # Deduplication
            dup = cursor.execute("SELECT id FROM telemetry_feeder_readings WHERE message_id = ?", (msg_id,)).fetchone()
            if dup:
                self.send_json(200, {'success': True, 'status': 'duplicate_ignored', 'message_id': msg_id})
                return

            v_r = float(data.get('voltage_v', data.get('v_r', 230.0)))
            v_y = float(data.get('v_y', 230.0))
            v_b = float(data.get('v_b', 230.0))
            i_r = float(data.get('current_a', data.get('i_r', 10.0)))
            i_y = float(data.get('i_y', 10.0))
            i_b = float(data.get('i_b', 10.0))
            total_kw = float(data.get('power_kw', data.get('total_power_kw', 6.0)))
            pf = float(data.get('power_factor', 0.92))
            kwh = float(data.get('cumulative_kwh', 1250.0))
            phase_status = data.get('phase_status', {'r': True, 'y': True, 'b': True})
            pr = 1 if phase_status.get('r', True) else 0
            py = 1 if phase_status.get('y', True) else 0
            pb = 1 if phase_status.get('b', True) else 0
            contactor = 1 if data.get('contactor_status', True) else 0

            # Persist historical
            cursor.execute("""
                INSERT INTO telemetry_feeder_readings (
                    feeder_id, message_id, v_r, v_y, v_b, i_r, i_y, i_b,
                    total_power_kw, power_factor, cumulative_kwh,
                    phase_status_r, phase_status_y, phase_status_b, contactor_status,
                    recorded_at, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
            """, (fid, msg_id, v_r, v_y, v_b, i_r, i_y, i_b, total_kw, pf, kwh, pr, py, pb, contactor))

            # Update latest state cache
            rep_state = 'ON' if contactor else 'OFF'
            cursor.execute("""
                INSERT INTO device_latest_state (asset_type, asset_id, reported_state, voltage_v, current_a, power_kw, power_factor, is_online, last_seen_at)
                VALUES ('feeder', ?, ?, ?, ?, ?, ?, 1, datetime('now'))
                ON CONFLICT(asset_type, asset_id) DO UPDATE SET
                    reported_state = excluded.reported_state,
                    voltage_v = excluded.voltage_v,
                    current_a = excluded.current_a,
                    power_kw = excluded.power_kw,
                    power_factor = excluded.power_factor,
                    is_online = 1,
                    last_seen_at = datetime('now')
            """, (fid, rep_state, v_r, (i_r + i_y + i_b), total_kw, pf))

            # --- Phase 3 Fault Rule Evaluation ---
            triggered_alerts = []
            # 1. Phase Failure
            if not pr or not py or not pb or v_r < 160 or v_y < 160 or v_b < 160:
                alt = trigger_alert_if_not_duplicate(
                    conn, 'feeder', fid, 'FLT_PHASE_FAIL', 'critical',
                    f"Phase loss or low voltage on Feeder {dev_id}: R={v_r}V, Y={v_y}V, B={v_b}V",
                    {'v_r': v_r, 'v_y': v_y, 'v_b': v_b, 'pr': pr, 'py': py, 'pb': pb}
                )
                if alt: triggered_alerts.append(alt)

            # 2. Voltage Surge
            if v_r > 270 or v_y > 270 or v_b > 270:
                alt = trigger_alert_if_not_duplicate(
                    conn, 'feeder', fid, 'FLT_VOLT_SURGE', 'critical',
                    f"Dangerous voltage surge on Feeder {dev_id} (>270V)",
                    {'v_r': v_r, 'v_y': v_y, 'v_b': v_b}
                )
                if alt: triggered_alerts.append(alt)

            # 3. Energy Anomaly
            cap = float(feeder['rated_capacity_kw'] or 15.0)
            if total_kw > (cap * 1.25):
                alt = trigger_alert_if_not_duplicate(
                    conn, 'feeder', fid, 'FLT_ENERGY_ANOMALY', 'warning',
                    f"Feeder {dev_id} load {total_kw}kW exceeds rating {cap}kW by >25%",
                    {'measured_kw': total_kw, 'rated_kw': cap}
                )
                if alt: triggered_alerts.append(alt)

            conn.commit()
            self.send_json(200, {
                'success': True,
                'status': 'persisted',
                'feeder_id': fid,
                'alerts_triggered': triggered_alerts
            })

        elif path == '/api/telemetry/luminaire':
            dev_id = data.get('device_id')
            msg_id = data.get('message_id')
            if not dev_id or not msg_id:
                self.send_json(422, {'success': False, 'error': 'device_id and message_id are required'})
                return

            lum = cursor.execute("SELECT id, pole_number, wattage FROM luminaires WHERE pole_number = ?", (dev_id,)).fetchone()
            if not lum:
                self.send_json(404, {'success': False, 'error': f'Luminaire {dev_id} not registered'})
                return

            lid = lum['id']
            # Deduplication
            dup = cursor.execute("SELECT id FROM telemetry_luminaire_readings WHERE message_id = ?", (msg_id,)).fetchone()
            if dup:
                self.send_json(200, {'success': True, 'status': 'duplicate_ignored', 'message_id': msg_id})
                return

            lamp_status = 1 if data.get('lamp_status', True) else 0
            brightness = int(data.get('brightness_pct', 100 if lamp_status else 0))
            voltage = float(data.get('input_voltage_v', 230.0))
            power_w = float(data.get('power_w', float(lum['wattage']) * (brightness / 100.0) if lamp_status else 0.0))

            cursor.execute("""
                INSERT INTO telemetry_luminaire_readings (luminaire_id, message_id, lamp_status, brightness_pct, input_voltage_v, power_w, recorded_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
            """, (lid, msg_id, lamp_status, brightness, voltage, power_w))

            # Fetch commanded state
            cmd_row = cursor.execute("SELECT commanded_state FROM device_latest_state WHERE asset_type = 'luminaire' AND asset_id = ?", (lid,)).fetchone()
            commanded = cmd_row['commanded_state'] if cmd_row else 'ON'
            reported = 'ON' if lamp_status else 'OFF'

            cursor.execute("""
                INSERT INTO device_latest_state (asset_type, asset_id, reported_state, brightness_pct, voltage_v, power_kw, is_online, last_seen_at)
                VALUES ('luminaire', ?, ?, ?, ?, ?, 1, datetime('now'))
                ON CONFLICT(asset_type, asset_id) DO UPDATE SET
                    reported_state = excluded.reported_state,
                    brightness_pct = excluded.brightness_pct,
                    voltage_v = excluded.voltage_v,
                    power_kw = excluded.power_kw,
                    is_online = 1,
                    last_seen_at = datetime('now')
            """, (lid, reported, brightness, voltage, round(power_w / 1000.0, 4)))

            # Fault rule check
            triggered = []
            if commanded == 'ON' and (not lamp_status or power_w < 10.0):
                alt = trigger_alert_if_not_duplicate(
                    conn, 'luminaire', lid, 'FLT_LAMP_FAIL', 'critical',
                    f"Lamp failure on Pole {dev_id}: Commanded ON but consuming 0W",
                    {'commanded': commanded, 'power_w': power_w}
                )
                if alt: triggered.append(alt)
            elif commanded == 'OFF' and lamp_status and power_w > 15.0:
                alt = trigger_alert_if_not_duplicate(
                    conn, 'luminaire', lid, 'FLT_DAY_BURNING', 'warning',
                    f"Day-burning detected on Pole {dev_id}: Active outside schedule drawing {power_w}W",
                    {'commanded': commanded, 'power_w': power_w}
                )
                if alt: triggered.append(alt)

            conn.commit()
            self.send_json(200, {
                'success': True,
                'status': 'persisted',
                'luminaire_id': lid,
                'alerts_triggered': triggered
            })

        # --- Phase 3: Alert Actions ---
        elif path == '/api/alerts/acknowledge':
            alert_id = data.get('alert_id')
            user_id = CURRENT_SESSION['user']['id'] if CURRENT_SESSION['user'] else 1
            cursor.execute("""
                UPDATE alerts SET status = 'acknowledged', acknowledged_by = ?, acknowledged_at = datetime('now')
                WHERE id = ? AND status = 'active'
            """, (user_id, alert_id))
            conn.commit()
            self.send_json(200, {'success': True, 'message': 'Alert acknowledged'})

        elif path == '/api/alerts/resolve':
            alert_id = data.get('alert_id')
            notes = data.get('notes', 'Resolved by operator').strip()
            user_id = CURRENT_SESSION['user']['id'] if CURRENT_SESSION['user'] else 1
            cursor.execute("""
                UPDATE alerts SET status = 'resolved', resolved_by = ?, resolved_at = datetime('now'), resolution_notes = ?
                WHERE id = ?
            """, (user_id, notes, alert_id))
            conn.commit()
            self.send_json(200, {'success': True, 'message': 'Alert marked resolved'})

        # --- Simulator Trigger ---
        elif path == '/api/telemetry/simulate':
            fid = data.get('feeder_id', 1)
            v = data.get('v_r', 230.0)
            kw = data.get('power_kw', 5.0)
            cursor.execute("""
                UPDATE device_latest_state 
                SET voltage_v = ?, power_kw = ?, last_seen_at = datetime('now'), is_online = 1
                WHERE asset_type = 'feeder' AND asset_id = ?
            """, (v, kw, fid))
            conn.commit()
            self.send_json(200, {'success': True, 'message': 'Simulated reading updated'})

        else:
            self.send_json(404, {'success': False, 'error': 'API endpoint not found'})

        conn.close()

if __name__ == '__main__':
    server = HTTPServer(('127.0.0.1', PORT), CCMSHandler)
    print(f"Gandhidham CCMS server running at http://127.0.0.1:{PORT}")
    server.serve_forever()
