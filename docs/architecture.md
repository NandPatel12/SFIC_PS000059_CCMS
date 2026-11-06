# System Architecture --- Gandhidham Municipal Streetlight CCMS

## 1. Purpose

Design a web-based IoT Centralized Control and Monitoring System (CCMS)
for Gandhidham Municipal Corporation. The platform supervises smart
feeder panels and individual streetlights, detects faults, applies
scheduled lighting profiles, and supports maintenance operations.

This document describes the software architecture for an MVP built with
HTML, CSS, JavaScript, PHP, and MySQL. Field hardware and communications
are treated as external integrations.

## 2. Architecture overview

``` text
Streetlight Nodes / Smart Feeder Panels
        |
        | MQTT over TLS / HTTPS API / Modbus gateway
        v
IoT Gateway or Device Ingestion Service
        |
        v
PHP Backend (REST endpoints + business services)
        |
        +---- MySQL database
        |
        +---- Alert service (SMS / email / portal)
        |
        +---- Scheduler (astronomical clock + dimming rules)
        |
        v
Web Frontend (HTML + CSS + JavaScript)
        |
        +---- Operations dashboard
        +---- Feeder and luminaire management
        +---- Faults and maintenance
        +---- Reports and audit logs
```

The browser must never connect directly to field devices or the MySQL
database. All application data flows through authenticated PHP
endpoints.

## 3. Recommended application layers

### Frontend

-   HTML5 semantic pages.
-   CSS with responsive layout and reusable design tokens.
-   Vanilla JavaScript modules using `fetch()` for API calls.
-   Role-aware navigation and accessible forms.
-   Dashboard charts and map visualization may use a maintained
    chart/map library if approved.

### Backend

Organize PHP code into: - `Controllers`: validate HTTP requests and
return responses. - `Services`: business rules such as fault evaluation,
dimming schedules, and work-order transitions. - `Repositories`:
parameterized MySQL queries. - `Middleware`: authentication,
authorization, CSRF, and request logging. - `Integrations`: SMS
provider, MQTT broker/API gateway, and device protocol adapters.

Use PDO with prepared statements. Keep credentials outside the public
web root.

### Database

MySQL stores users, wards, feeders, luminaires, telemetry, alerts,
schedules, work orders, and audit records. Store timestamps in UTC and
render them in `Asia/Kolkata`.

### Device integration

Prefer MQTT over TLS for telemetry and commands where supported. HTTPS
ingestion is an acceptable MVP alternative. If the panel uses
Modbus/RS-485, use a gateway to translate field protocol messages into
the backend's documented device API. Do not expose Modbus directly to
the public internet.

## 4. Core modules

1.  Authentication and role-based access control.
2.  Ward, feeder, panel, and luminaire registry.
3.  Telemetry ingestion and latest-state service.
4.  Fault detection and alert management.
5.  Astronomical clock and dimming schedule management.
6.  Work orders and maintenance tracking.
7.  Reports, energy analytics, and audit trail.
8.  Device command queue and acknowledgement tracking.

## 5. Device data contract

Every message should include: - `device_id` - `message_id` (unique, for
deduplication) - `recorded_at` (ISO 8601 timestamp with timezone) -
`sequence_no` where available - `firmware_version` - `source` (panel,
gateway, or luminaire)

Example telemetry payload:

``` json
{
  "device_id": "FEEDER-GDM-001",
  "message_id": "a6f5c8e0-001",
  "recorded_at": "2026-11-06T00:15:00+05:30",
  "source": "feeder",
  "voltage_v": 228.4,
  "current_a": 18.2,
  "power_kw": 3.72,
  "power_factor": 0.91,
  "phase_status": {"r": true, "y": true, "b": true},
  "contactor_status": true
}
```

Example luminaire payload:

``` json
{
  "device_id": "LUM-GDM-000145",
  "message_id": "b18c-145-001",
  "recorded_at": "2026-11-06T00:15:00+05:30",
  "source": "luminaire",
  "lamp_status": true,
  "brightness_pct": 55,
  "input_voltage_v": 226.8,
  "power_w": 42.1,
  "communication_status": "online"
}
```

Validate payload schema, device ownership, timestamp, units, and
replay/deduplication before persistence.

## 6. Fault evaluation

Use configurable rules rather than hard-coded assumptions. Initial
candidate rules: - **Luminaire failure:** commanded ON but measured lamp
status OFF for a configurable number of consecutive readings. -
**Day-burning:** light remains ON outside the configured daylight/off
period, after allowing for schedule and override state. - **Phase
failure:** one or more feeder phase status values are false, or voltage
is outside configured limits. - **Abnormal voltage/current:** readings
exceed approved operating thresholds for a configured duration. -
**Communication loss:** no valid heartbeat within the configured
timeout. - **Possible energy anomaly:** measured feeder energy differs
materially from the expected connected-load profile. Treat as an
investigation indicator, not proof of theft.

Each fault needs severity, first/last occurrence, status, affected
asset, evidence, and acknowledgement history. Avoid duplicate open
alerts for the same asset and fault type.

## 7. Dimming and switching

-   Calculate sunrise and sunset using a vetted solar-position
    library/service for the configured site coordinates and date.
-   Keep the municipality-approved lighting policy configurable.
-   Example policy: full brightness at evening switch-on; reduce to
    50--60% from 00:00--05:00; restore before morning traffic if policy
    requires.
-   Store schedules with timezone, effective dates, exceptions, and
    version.
-   Commands must include a command ID, target, requested value, expiry,
    and reason.
-   Show pending, acknowledged, failed, and expired command states.
-   Provide manual override with user, reason, start/end time, and audit
    log.
-   Never turn public lighting off solely because a browser session or
    cloud connection fails. Define a safe local fallback at the panel.

## 8. Security and resilience

-   HTTPS everywhere; MQTT over TLS where used.
-   Password hashing with PHP `password_hash()` / `password_verify()`.
-   Role-based access control and least privilege.
-   CSRF protection for session-authenticated state-changing requests.
-   Input validation, output escaping, rate limiting, and prepared SQL
    statements.
-   Device credentials unique per device; rotate and revoke credentials.
-   Audit sensitive actions and device commands.
-   Backups, restore tests, monitoring, and documented retention.
-   Local panel schedules and safe defaults must continue during
    cloud/network outage.

## 9. Deployment topology

For development: PHP built-in server or Apache/Nginx, MySQL, and a local
test-device simulator.

For production: Linux server or managed hosting, Apache/Nginx + PHP-FPM,
MySQL with automated backups, TLS, firewall restrictions, monitoring,
and a separate worker/scheduler process. Do not run long-lived MQTT
subscriptions inside ordinary page requests.

## 10. Key non-functional targets

Set and validate targets during pilot: - Dashboard initial load: under 3
seconds on the target municipal network. - Telemetry-to-dashboard
freshness: target under 60 seconds for normal reporting intervals. -
Alert generation: target under 2 minutes after a rule is satisfied. -
Availability: define a service objective with the corporation and
vendor. - Data integrity: deduplicate messages and preserve audit
history. - Accessibility: keyboard-operable controls, labels, contrast,
and responsive layouts.

These are proposed engineering targets, not measured guarantees.
