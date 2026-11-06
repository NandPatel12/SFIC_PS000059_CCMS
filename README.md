# Gandhidham Municipal Corporation Streetlight CCMS
**Centralized Control and Monitoring System (CCMS) for Public Street Lighting**

A web-based IoT supervisory management platform built for **Gandhidham Municipal Corporation** to monitor smart feeder panels and individual streetlights, detect electrical faults in real time, automate maintenance work orders, and supervise scheduled switching and dimming.

---

## 🌟 Implemented Phases

| Phase | Title | Status | Scope & Features |
|---|---|---|---|
| **Phase 0** | Discovery & Hardware Specification | ✅ Completed | Ward 3 & 7 pilot baseline, RS-485 Modbus to 4G IoT gateway architecture, safe local failback logic, and fault trigger thresholds. |
| **Phase 1** | Web Application Foundation | ✅ Completed | MySQL relational schema, layered PHP backend structure, responsive HTML5/CSS3/Vanilla JS UI, session management, and audit logging. |
| **Phase 2** | Asset Registry & Live Telemetry | ✅ Completed | Full CRUD for Wards, Feeders, Panels, and Luminaires; authenticated telemetry ingestion; message deduplication (`message_id`); latest device state caching. |
| **Phase 3** | Fault Detection & Alerts | ✅ Completed | Automated rule engine (Phase Failure, Voltage Surge, Lamp Dropout, Day-Burning, Overload); duplicate alert suppression; supervisor acknowledge/resolve workflow; SMS sandbox dispatcher. |
| **Phase 4** | Schedules & Device Commands | ⏳ Next | Astronomical switching calculations (`Asia/Kolkata`) and scheduled dimming profiles. |
| **Phase 5** | Work Orders & Energy Reporting | ⏳ Next | Auto-dispatching work orders, repair evidence tracking, and tariff-based energy cost calculations. |

---

## 🛠️ Technology Stack

- **Frontend:** Semantic HTML5, CSS3 (responsive grid, accessible palette), Vanilla JavaScript (ES6+ `fetch()` modules)
- **Backend:** PHP 8+ (MVC structure, RESTful API endpoints, PDO prepared statements)
- **Database:** MySQL 8.0+ / MariaDB (relational design, indexing, foreign key constraints; SQLite bridge available for local zero-dependency testing)
- **IoT Protocol Interface:** JSON over authenticated HTTPS REST / MQTT over TLS
- **Timezone:** Timestamps stored in UTC, rendered in `Asia/Kolkata` (IST)

---

## 📁 Project Architecture & Directory Structure

```text
ps000059/
├── docs/                               # Engineering documentation & PRD specifications
│   ├── README.md                       # Documentation overview
│   ├── prd.md                          # Product requirements document
│   ├── architecture.md                 # System & integration architecture
│   ├── design.md                       # UI/UX design tokens & layout guidelines
│   ├── phase.md                        # Delivery phases & milestone roadmap
│   ├── tasks.md                        # Development backlog
│   ├── rules.md                        # Operational, security & coding standards
│   ├── memory.md                       # Domain notes & decision register
│   └── phase-0-discovery-spec.md       # Approved Phase 0 hardware & pilot spec
├── ccms/                               # Core application codebase
│   ├── app/
│   │   ├── Controllers/
│   │   │   └── ApiController.php       # REST API endpoints & request handlers
│   │   ├── Services/
│   │   │   ├── AuthService.php         # RBAC, CSRF validation, audit logging
│   │   │   ├── FaultService.php        # Real-time rule evaluation & alert engine
│   │   │   └── TelemetryService.php    # Ingestion, schema validation & deduplication
│   │   └── Database.php                # PDO database connection manager
│   ├── database/
│   │   ├── schema.sql                  # MySQL database schema definition (12 tables)
│   │   └── seeds/
│   │       └── demo_data.sql           # Seed records for pilot wards, feeders & lamps
│   ├── public/
│   │   ├── assets/
│   │   │   ├── css/style.css           # Design tokens, metrics grid & status pills
│   │   │   └── js/app.js               # Client state, live clock & async handlers
│   │   └── index.php                   # Single-page operations console & shell
│   ├── scripts/
│   │   ├── dev_server.py               # Built-in local development server & IoT gateway
│   │   └── init_db.py                  # Database initialization & migration utility
│   ├── storage/
│   │   ├── database.sqlite             # Local database file
│   │   └── logs/
│   │       └── sms_sandbox.log         # Sandbox dispatch logs for SMS alerts
│   └── .env.example                    # Environment configuration template
└── README.md
```

---

## ⚡ Quick Start & Local Execution

### 1. Initialize the Database
Ensure python3 is installed. To generate the local database with all schemas and demo seeds:
```bash
python3 ccms/scripts/init_db.py
```

### 2. Start the Local Server
Run the local server and IoT gateway from the project root:
```bash
python3 ccms/scripts/dev_server.py
```
The application will be live at: **[http://localhost:8000](http://localhost:8000)** (or `http://127.0.0.1:8000`).

---

## 🔐 Demo Credentials

The platform includes role-based access control (RBAC). Use any of the pre-configured accounts below:

| Role | Username | Password | Permissions |
|---|---|---|---|
| **Super Admin** | `admin` | `Admin@1234` | Full system control, asset registry, configuration |
| **Electrical Engineer** | `engineer_patel` | `Admin@1234` | Telemetry inspection, fault evaluation, schedules |
| **Line Supervisor** | `sup_sharma` | `Admin@1234` | Area monitoring, alert acknowledgement & resolution |
| **Contractor** | `contractor_apex` | `Admin@1234` | Assigned repair work orders |

---

## 📡 IoT Telemetry & Fault Detection API

The system provides dedicated REST endpoints for IoT gateways and luminaire nodes:

### 1. Feeder Telemetry Ingestion
- **Endpoint:** `POST /api/telemetry/feeder`
- **Sample Payload:**
```json
{
  "device_id": "FDR-W03-FP01",
  "message_id": "MSG-98234-01",
  "v_r": 230.5,
  "v_y": 231.0,
  "v_b": 229.8,
  "phase_status": { "r": true, "y": true, "b": true },
  "total_power_kw": 5.4,
  "power_factor": 0.94,
  "cumulative_kwh": 1420.5,
  "contactor_status": true
}
```

### 2. Luminaire Telemetry Ingestion
- **Endpoint:** `POST /api/telemetry/luminaire`
- **Sample Payload:**
```json
{
  "device_id": "POLE-W03-001",
  "message_id": "LUM-MSG-102",
  "lamp_status": true,
  "brightness_pct": 100,
  "input_voltage_v": 230.2,
  "power_w": 90.0
}
```

### 3. Automated Fault Detection Rules
- **`FLT_PHASE_FAIL` (Critical):** Triggered when any feeder phase voltage drops below 160V or phase contact is lost.
- **`FLT_VOLT_SURGE` (Critical):** Triggered when line voltage exceeds 270V.
- **`FLT_LAMP_FAIL` (Critical):** Triggered when a luminaire is commanded ON but reports 0W consumption.
- **`FLT_DAY_BURNING` (Warning):** Triggered when a luminaire draws load while commanded OFF during daytime.
- **`FLT_ENERGY_ANOMALY` (Warning):** Triggered when measured feeder load exceeds rated capacity by > 20%.

---

## 🎮 Interactive Device Simulator

Open **`http://localhost:8000`** in your browser and switch to the **Device Simulator** tab in the sidebar navigation to test:
- Normal operating telemetry ingest.
- Simulated phase loss and undervoltage.
- Feeder voltage surge (>280V).
- Streetlight LED driver dropout (lamp failure).
- Replay deduplication rejection (`duplicate_ignored`).
- Live SMS sandbox alert logs.
