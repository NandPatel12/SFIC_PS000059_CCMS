# UI / UX Design Specification

## 1. Product experience

The CCMS is an operations console for electrical engineers, line
supervisors, and maintenance contractors. The interface should
prioritize situational awareness, fast fault triage, and safe control
actions.

## 2. Navigation

Primary navigation: - Overview - Live Monitoring - Wards - Feeders &
Panels - Streetlights - Faults & Alerts - Work Orders - Lighting
Schedules - Energy Reports - Users & Roles - Audit Log - Settings

Hide or disable actions the signed-in role is not authorized to perform.

## 3. Dashboard layout

Top bar: - Municipality/project identity - Current local date and time -
System connectivity indicator - Notification indicator - User menu

Summary cards: - Total registered luminaires - Online / offline
devices - Active critical / warning faults - Feeders with phase
anomalies - Open work orders - Today's estimated energy use

Main content: - Ward-wise asset/fault summary - Feeder status table -
Recent critical alerts - Energy trend chart - Work-order aging summary

Do not imply a light is healthy when telemetry is stale. Display
`Last seen` and a stale-data state.

## 4. Visual system

Suggested palette: - Navy / charcoal for navigation and headings. -
Neutral light background for work surfaces. - Green for
healthy/online. - Amber for warning/attention. - Red for
critical/fault. - Blue for informational or pending states.

Color must not be the only status indicator; include text and icons. Use
consistent spacing, readable typography, clear focus outlines, and
sufficient contrast.

## 5. Important screens

### Live Monitoring

Filters: ward, feeder, panel, status, fault type, and last-seen range.
Table columns: asset ID, location, commanded state, reported state,
brightness, voltage, last seen, active fault, actions.

### Asset details

Show hierarchy (ward → feeder → panel → luminaire), coordinates,
make/model, installation details, telemetry history, command history,
open alerts, and maintenance history.

### Faults & Alerts

Support filtering, severity, acknowledgement, assignment, resolution
notes, and duplicate grouping. Preserve event history; resolving an
alert must not delete it.

### Work Orders

Show priority, affected asset, fault link, assigned team, SLA target,
status, timestamps, and resolution evidence. Provide a mobile-friendly
field view.

### Lighting Schedules

Show site location, sunrise/sunset, schedule blocks, dimming percentage,
effective dates, exceptions, and approval state. Require confirmation
for broad-impact changes.

### Energy Reports

Provide date range, ward/feeder filters, kWh, estimated cost (with
configurable tariff), comparison period, and export. Label estimates and
identify missing telemetry.

## 6. Interaction rules

-   Destructive or city-wide actions require confirmation and a reason.
-   Show command status; never report success before device
    acknowledgement.
-   Disable duplicate submission while a request is processing.
-   Use clear empty, loading, error, offline, and stale-data states.
-   Preserve filters when returning from a detail page.
-   Use pagination or server-side filtering for large asset lists.
-   Confirm manual overrides and show expiry time prominently.

## 7. Responsive behavior

Desktop: full navigation, dashboard grid, dense data tables. Tablet:
collapsible navigation, fewer table columns. Mobile: compact navigation,
card-based asset and work-order views, large touch targets, and
offline-aware field workflows where feasible.

## 8. Accessibility

Use semantic headings, associated form labels, keyboard navigation,
visible focus, accessible validation messages, meaningful button labels,
and WCAG-aligned contrast. Charts must have a text/table equivalent.
