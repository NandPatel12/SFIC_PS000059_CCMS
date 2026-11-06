# Engineering, Data, Security, and Operational Rules

## 1. General development rules

1.  Use PHP for server-side logic; never put database credentials in
    JavaScript.
2.  The browser must call backend APIs; it must not connect directly to
    MySQL or field devices.
3.  Keep controllers thin and business logic in services.
4.  Use PDO prepared statements for every database query containing
    input.
5.  Validate all inputs server-side even when the browser also validates
    them.
6.  Escape untrusted content before rendering HTML.
7.  Use consistent JSON API response formats and appropriate HTTP status
    codes.
8.  Keep configuration and secrets outside the public web root.
9.  Use database migrations; do not make undocumented manual schema
    changes.
10. Mark demo/simulated data clearly.

## 2. Security rules

-   Hash passwords using `password_hash()` and verify using
    `password_verify()`.
-   Regenerate session IDs after authentication and protect session
    cookies.
-   Apply role-based authorization on every protected page and API
    endpoint.
-   Use CSRF tokens for state-changing requests using cookie-based
    sessions.
-   Enforce HTTPS in production.
-   Apply rate limits to login and device ingestion endpoints.
-   Use unique device credentials; support revocation and rotation.
-   Restrict device ingestion to registered devices and expected payload
    schemas.
-   Do not expose stack traces, SQL errors, secrets, or internal paths
    to users.
-   Record sensitive actions in an append-only audit trail.

## 3. Data rules

-   Store timestamps in UTC; display in `Asia/Kolkata`.
-   Use consistent units: volts, amperes, watts/kilowatts, kWh, and
    brightness percent.
-   Store commanded and reported states separately.
-   Include device ID, message ID, and recorded timestamp in telemetry.
-   Deduplicate using a unique message ID or device/sequence key.
-   Retain historical telemetry according to an approved retention
    policy.
-   Do not overwrite historical alert or command events.
-   Represent missing readings as missing/null, not zero.
-   Mark stale telemetry explicitly.

## 4. Fault rules

-   Fault thresholds and consecutive-reading counts must be
    configurable.
-   Do not infer a luminaire failure from communication loss alone.
-   Compare day-burning against the active schedule and override state.
-   Treat energy imbalance as a potential anomaly requiring
    investigation, not proof of theft.
-   Group repeated events into one active alert where appropriate.
-   Preserve evidence and timestamps used to create each alert.
-   Define alert severity and escalation with the corporation.

## 5. Device command rules

-   Every command has a unique ID, target, requested value, actor/system
    source, reason, creation time, expiry, and status.
-   A command is successful only after device/gateway acknowledgement.
-   Retry only according to a bounded retry policy.
-   Expired or failed commands must be visible to operators.
-   Require confirmation and reason for broad-impact commands.
-   Manual overrides must have an expiry and audit record.
-   Local controller behavior must remain safe when cloud connectivity
    is unavailable.
-   Do not allow a browser refresh or timeout to silently duplicate a
    command.

## 6. Lighting policy rules

-   Astronomical calculations must use configured site coordinates and
    `Asia/Kolkata`.
-   Dimming percentages and time windows are configurable and approved
    by the corporation.
-   The 50--60% midnight profile is an example only, not an
    automatically approved setting.
-   Validate minimum illumination and road-safety requirements before
    deployment.
-   Support holidays, events, weather/emergency overrides, and schedule
    exceptions where required.
-   Keep a version history of published schedules.

## 7. UI rules

-   Display last-seen time for device status.
-   Distinguish healthy, warning, critical, offline, stale, pending, and
    failed.
-   Never rely on color alone.
-   Confirm destructive or broad-impact actions.
-   Disable repeat submits while a request is in progress.
-   Provide loading, empty, validation, and error states.
-   Enforce permissions in the backend, not merely by hiding buttons.

## 8. Code quality rules

-   Use clear names and consistent formatting.
-   Keep functions focused and avoid duplicated business rules.
-   Add tests for authentication, permissions, ingestion, deduplication,
    fault rules, and command state transitions.
-   Log errors with correlation IDs while excluding secrets and
    unnecessary personal data.
-   Review dependencies and update them through a controlled process.
-   Document API contracts and environment variables.

## 9. Operational rules

-   Demo mode must not reach live device endpoints.
-   Live control requires approval, commissioning, and
    hardware-in-the-loop tests.
-   Maintain backups and test restoration.
-   Establish incident response and access revocation procedures.
-   Maintain an asset inventory and device firmware register.
