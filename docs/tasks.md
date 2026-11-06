# Development Task Backlog

Use `[ ]` for pending, `[x]` for complete, and add an owner/target date
when the team is known.

## Epic 1 --- Project foundation

-   [ ] Create repository and agreed branch strategy.
-   [ ] Create PHP application directory structure.
-   [ ] Add `.env.example` and environment configuration loader.
-   [ ] Configure Apache/Nginx document root to `public/`.
-   [ ] Configure PDO database connection.
-   [ ] Add centralized error handling and structured logging.
-   [ ] Define API response and validation conventions.
-   [ ] Add database migration mechanism.

## Epic 2 --- Database and asset registry

-   [ ] Create tables for users, roles, wards, feeders, panels, and
    luminaires.
-   [ ] Add foreign keys, unique asset IDs, and indexes.
-   [ ] Seed demo wards/feeders/panels/luminaires.
-   [ ] Build ward CRUD screens and APIs.
-   [ ] Build feeder and panel CRUD screens and APIs.
-   [ ] Build luminaire CRUD screens and APIs.
-   [ ] Add asset hierarchy and search/filter support.
-   [ ] Add CSV import with validation and error report.

## Epic 3 --- Authentication and permissions

-   [ ] Implement login/logout.
-   [ ] Hash and verify passwords securely.
-   [ ] Implement session hardening and CSRF protection.
-   [ ] Implement role and permission tables.
-   [ ] Protect every page and API endpoint.
-   [ ] Add user administration and account deactivation.
-   [ ] Add audit events for security-sensitive actions.

## Epic 4 --- Telemetry

-   [ ] Agree device payload schema with hardware vendor.
-   [ ] Create device credential registry.
-   [ ] Implement authenticated telemetry ingestion endpoint.
-   [ ] Validate device identity, units, timestamps, and schema.
-   [ ] Implement message deduplication.
-   [ ] Persist telemetry readings.
-   [ ] Maintain latest device state.
-   [ ] Add stale/offline calculation.
-   [ ] Build feeder/luminaire telemetry screens.
-   [ ] Build local device simulator with DEMO labels.

## Epic 5 --- Faults and alerts

-   [ ] Create configurable fault-rule tables.
-   [ ] Implement luminaire commanded-ON/reported-OFF rule.
-   [ ] Implement day-burning rule with schedule/override awareness.
-   [ ] Implement phase-failure rule.
-   [ ] Implement voltage/current threshold rule.
-   [ ] Implement communication-timeout rule.
-   [ ] Implement energy anomaly investigation rule.
-   [ ] Prevent duplicate active alerts.
-   [ ] Build alert list, detail, acknowledge, assign, and resolve
    actions.
-   [ ] Add alert event history.
-   [ ] Add SMS provider adapter and test-mode integration.
-   [ ] Test alert false-positive and recovery behavior.

## Epic 6 --- Schedules and device commands

-   [ ] Store site coordinates and timezone.
-   [ ] Integrate and test astronomical sunrise/sunset calculation.
-   [ ] Build schedule CRUD and versioning.
-   [ ] Add schedule approval workflow.
-   [ ] Add dimming time blocks and exceptions.
-   [ ] Add manual override with mandatory reason and expiry.
-   [ ] Create command queue and command event tables.
-   [ ] Implement command dispatch adapter.
-   [ ] Implement acknowledgement, retry, failure, and expiry.
-   [ ] Verify local safe fallback during network outage.
-   [ ] Test with hardware-in-the-loop before production control.

## Epic 7 --- Work orders

-   [ ] Create work-order schema and status transitions.
-   [ ] Generate work orders from selected alerts.
-   [ ] Assign contractor/team and priority.
-   [ ] Build contractor work-order view.
-   [ ] Capture repair notes and evidence.
-   [ ] Add supervisor verification and closure.
-   [ ] Report response and repair turnaround times.

## Epic 8 --- Dashboard and reports

-   [ ] Build dashboard summary cards.
-   [ ] Build ward-wise asset/fault summary.
-   [ ] Build feeder status table.
-   [ ] Build recent alerts panel.
-   [ ] Build energy trend chart.
-   [ ] Add date, ward, and feeder report filters.
-   [ ] Add energy cost estimate using configured tariff.
-   [ ] Add CSV export with authorization.
-   [ ] Clearly mark missing and estimated data.

## Epic 9 --- Quality, security, and rollout

-   [ ] Add automated tests for core services and API endpoints.
-   [ ] Test role permissions and CSRF protections.
-   [ ] Test ingestion replay, duplicates, malformed messages, and clock
    drift.
-   [ ] Test network loss and device reconnect behavior.
-   [ ] Conduct security review.
-   [ ] Configure production TLS, backups, and monitoring.
-   [ ] Test database restore.
-   [ ] Prepare user training and operating procedures.
-   [ ] Run limited pilot and record baseline energy use.
-   [ ] Obtain pilot acceptance before ward-wide rollout.

## Definition of done

A task is complete only when: - The code is implemented and reviewed. -
Server-side validation and authorization are present. - Relevant tests
pass. - Error and empty states are handled. - Documentation is
updated. - Demo/live data boundaries are respected.
