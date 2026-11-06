# Development Phases and Milestones

## Phase 0 --- Discovery and validation

**Goal:** Remove hardware and operational ambiguity before
implementation.

Tasks: - Meet electrical engineers and maintenance teams. - Collect
ward, feeder, panel, and luminaire inventory. - Confirm
controller/driver dimming capability. - Confirm device protocols,
network coverage, and gateway requirements. - Agree fault thresholds,
escalation rules, retention, and SLAs. - Select pilot wards/feeders.

Exit criteria: approved scope, hardware interface specification, data
dictionary, and pilot plan.

## Phase 1 --- Web application foundation

**Goal:** Deliver secure application skeleton.

Tasks: - Create PHP project structure and environment configuration. -
Create MySQL schema and migrations. - Implement login/logout and
role-based permissions. - Build responsive navigation and dashboard
shell. - Add audit logging and API response conventions. - Seed demo
wards, feeders, panels, and luminaires.

Exit criteria: users can sign in and view role-appropriate demo asset
data.

## Phase 2 --- Asset registry and live monitoring

**Goal:** Manage the municipal asset hierarchy and display telemetry.

Tasks: - CRUD for wards, feeders, panels, and luminaires. - Define
device IDs and parent-child relationships. - Implement telemetry
ingestion endpoint. - Validate, deduplicate, and persist device
messages. - Build latest-state views and stale-data indicators. - Build
device simulator for development.

Exit criteria: simulator data appears in the correct asset detail and
dashboard views.

## Phase 3 --- Fault detection and alerts

**Goal:** Detect and manage operational issues.

Tasks: - Implement configurable rules for lamp failure, day-burning,
phase failure, voltage anomaly, and communication loss. - Prevent
duplicate active alerts. - Add severity, acknowledgement, assignment,
and resolution workflow. - Integrate SMS provider in sandbox/test
mode. - Add alert history and audit trail.

Exit criteria: test cases trigger expected alerts and alert actions are
auditable.

## Phase 4 --- Lighting schedules and commands

**Goal:** Manage scheduled switching and dimming safely.

Tasks: - Implement site coordinates and astronomical calculations. -
Configure schedule blocks and dimming levels. - Add approvals and manual
override with expiry. - Implement command queue, acknowledgement, retry,
and expiry. - Add local fallback requirements to device integration
specification.

Exit criteria: commands are traceable end-to-end in a
hardware-in-the-loop pilot; no cloud failure causes unsafe darkness.

## Phase 5 --- Work orders and reporting

**Goal:** Improve repair turnaround and quantify outcomes.

Tasks: - Generate work orders from alerts. - Assign contractor/team and
priority. - Track acknowledgement, dispatch, repair, and closure. -
Capture notes and repair evidence. - Add ward/feeder energy reports and
maintenance KPIs. - Add CSV export with permission checks.

Exit criteria: sample faults can be tracked from detection through
verified closure.

## Phase 6 --- Pilot, hardening, and rollout

**Goal:** Validate in real operating conditions.

Tasks: - Install and commission a limited pilot. - Verify meter accuracy
and telemetry quality. - Run day-burning, phase-failure,
communication-loss, and lamp-failure tests. - Conduct cybersecurity
review and restore test. - Train engineers, supervisors, and
contractors. - Review energy savings against a documented baseline. -
Expand ward by ward after sign-off.

Exit criteria: signed pilot acceptance, operating procedures, support
plan, and rollout approval.

## Suggested sequencing

Build and test the software simulator before field hardware is fully
available. Do not represent simulated telemetry as live municipal data.
Mark all demo devices and measurements clearly.
