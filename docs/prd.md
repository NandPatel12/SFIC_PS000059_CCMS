# Product Requirements Document (PRD)

## 1. Product summary

Build a web-based IoT CCMS for Gandhidham Municipal Corporation to
monitor, control, and maintain public streetlighting assets. The
application will use HTML, CSS, JavaScript, PHP, and MySQL. Real-world
control requires compatible field controllers, gateways, communications,
and electrical commissioning.

## 2. Problem statement

Manual patrols and citizen complaints delay fault detection. Day-burning
and unmanaged full-brightness operation increase energy consumption.
Limited feeder- and luminaire-level telemetry makes faults and
electrical anomalies difficult to localize, extending repair times.

## 3. Goals

-   Centralize visibility across wards, feeders, panels, and luminaires.
-   Detect faults automatically and notify responsible teams.
-   Support approved astronomical switching and dimming schedules.
-   Reduce time from fault detection to repair.
-   Provide reliable energy, fault, and maintenance reporting.

## 4. Non-goals for the initial software MVP

-   Replacing electrical protection systems.
-   Proving electricity theft automatically.
-   Guaranteeing energy savings before a measured pilot.
-   Controlling hardware that does not support remote switching/dimming.
-   Replacing official utility billing or GIS systems.

## 5. User roles

  -----------------------------------------------------------------------
  Role                                Main permissions
  ----------------------------------- -----------------------------------
  Super Administrator                 Manage users, configuration, and
                                      all assets

  Electrical Engineer                 View telemetry, configure approved
                                      schedules, review faults

  Line Supervisor                     Monitor assigned areas, acknowledge
                                      and assign faults

  Maintenance Contractor              View assigned work orders and
                                      update repair status

  Read-only Auditor                   View reports and audit records
  -----------------------------------------------------------------------

Use least-privilege permissions. Contractors must not modify global
schedules or user permissions.

## 6. Functional requirements

### FR-01 Authentication

Users can sign in and sign out securely. Passwords are hashed. Sessions
expire and are protected.

### FR-02 Asset registry

Authorized users can create, update, view, and deactivate wards,
feeders, panels, and luminaires. Every child asset references its
parent. Asset IDs are unique.

### FR-03 Telemetry ingestion

The backend accepts authenticated device telemetry, validates schema and
timestamps, rejects unknown devices, deduplicates message IDs, and
stores readings.

### FR-04 Live monitoring

Users can filter assets by ward, feeder, panel, status, and fault. The
UI displays commanded state, reported state, brightness, voltage, and
last-seen time when supported by hardware.

### FR-05 Fault detection

The system evaluates configurable rules for: - Luminaire commanded ON
but reported OFF. - Luminaire ON outside its permitted schedule. -
Feeder phase failure. - Voltage/current outside approved limits. -
Device communication timeout. - Feeder energy anomaly requiring
investigation.

### FR-06 Alerts

The system creates an alert with severity, asset, rule, evidence,
timestamps, and status. Users can acknowledge, assign, and resolve
alerts with notes. SMS/portal notifications are sent according to
escalation configuration.

### FR-07 Lighting schedules

Authorized users can define astronomical switching and time-based
dimming profiles. Schedule changes are versioned and audited. Manual
overrides require a reason and expiry.

### FR-08 Device commands

Commands are queued and tracked through pending, acknowledged, failed,
or expired states. A command is not shown as successful until device
acknowledgement is received.

### FR-09 Work orders

Users can create or generate work orders, assign a team, set priority,
track status, and record resolution details.

### FR-10 Reports

Users can view energy, asset availability, fault frequency, repair
turnaround, and work-order reports by date, ward, and feeder. Energy
cost uses a configurable tariff and is labelled as an estimate when
appropriate.

### FR-11 Audit

Sensitive actions, including schedule changes, manual overrides, user
changes, and device commands, are recorded with actor, timestamp,
action, and target.

## 7. Core workflows

### Fault-to-repair

Telemetry received → rule evaluated → alert created → supervisor
notified → alert acknowledged → work order assigned → contractor repairs
→ evidence/notes entered → supervisor verifies → alert/work order
closed.

### Scheduled dimming

Schedule configured → authorized approval → command generated at
scheduled time → gateway/device receives command → acknowledgement
recorded → reported brightness verified. If acknowledgement fails,
retry/escalate and retain safe local behavior.

## 8. Data entities

-   `users`, `roles`, `user_roles`
-   `wards`
-   `feeders`
-   `panels`
-   `luminaires`
-   `device_credentials`
-   `telemetry_readings`
-   `device_latest_state`
-   `fault_rules`
-   `alerts`, `alert_events`
-   `lighting_schedules`, `schedule_exceptions`
-   `device_commands`, `command_events`
-   `work_orders`, `work_order_events`
-   `notifications`
-   `audit_logs`

Use foreign keys, indexes, created/updated timestamps, and
soft-deactivation where historical references must remain valid.

## 9. Non-functional requirements

-   Security: HTTPS, password hashing, RBAC, CSRF protection, prepared
    statements, validation, output escaping.
-   Reliability: deduplication, retry handling, safe device fallback,
    backups.
-   Performance: paginate large tables and index common filters.
-   Usability: responsive, accessible, clear stale/offline states.
-   Maintainability: layered PHP code, migrations, documented API
    contracts.
-   Observability: application logs, ingestion metrics, alert/command
    failure metrics.

## 10. Success metrics

Measure during the pilot: - Median and 90th percentile fault detection
delay. - Median time from alert to acknowledgement. - Median time from
alert to repair closure. - Percentage of assets reporting valid
telemetry. - Number of repeat faults by asset/feeder. - kWh per
operating hour and normalized energy use versus baseline. - Percentage
of scheduled commands acknowledged. - False-positive alert rate.

## 11. Acceptance criteria

-   Unauthorized users cannot access restricted screens or API actions.
-   Duplicate telemetry does not create duplicate readings/alerts.
-   Stale devices are clearly marked as stale/offline, not healthy.
-   A test lamp failure creates one active alert with traceable
    evidence.
-   Phase failure is detected from configured feeder input.
-   Dimming command remains pending until acknowledged.
-   Schedule edits and overrides are audited.
-   Work orders can be tracked through verified closure.
-   Reports identify missing data and distinguish measured from
    estimated values.

## 12. Risks and dependencies

-   Individual luminaire monitoring requires compatible controllers or
    suitable sensing hardware.
-   Dimming requires compatible LED drivers/controllers.
-   Network outages require local schedules and safe fallback.
-   Poor asset inventory or coordinates reduce fault localization
    accuracy.
-   SMS delivery depends on provider availability and
    regulatory/template setup.
-   Electrical thresholds and operating policies require approval by
    qualified engineers.
