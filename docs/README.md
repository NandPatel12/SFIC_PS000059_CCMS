# Gandhidham Municipal Streetlight CCMS

A web application concept for centralized monitoring, fault detection,
scheduled dimming, and maintenance management of public streetlights.

## Technology stack

-   Frontend: HTML5, CSS3, vanilla JavaScript
-   Backend: PHP 8+ (recommended)
-   Database: MySQL 8+ (recommended)
-   Device integration: MQTT over TLS or authenticated HTTPS through an
    IoT gateway
-   Timezone for display: `Asia/Kolkata`; store timestamps in UTC

## Documentation

-   `prd.md` --- product requirements and acceptance criteria
-   `architecture.md` --- application, device, and data architecture
-   `design.md` --- UI/UX and screen requirements
-   `phase.md` --- delivery phases and milestones
-   `tasks.md` --- actionable development backlog
-   `rules.md` --- engineering and operational rules
-   `memory.md` --- domain context, decisions, and open questions

## Suggested project structure

``` text
ccms/
├── app/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Repositories/
│   ├── Services/
│   └── Integrations/
├── config/
├── database/
│   ├── migrations/
│   └── seeds/
├── public/
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   └── images/
│   ├── index.php
│   └── .htaccess
├── routes/
├── storage/
│   ├── logs/
│   └── uploads/
├── tests/
├── scripts/
├── .env.example
└── README.md
```

Keep `storage/`, `.env`, database dumps, and application source outside
the publicly served directory wherever possible.

## Local setup outline

1.  Install PHP 8+, MySQL, and Apache/Nginx (XAMPP is acceptable for
    local learning).
2.  Create a MySQL database, for example `gandhidham_ccms`.
3.  Copy `.env.example` to `.env` and configure database credentials.
    Never commit real secrets.
4.  Run database migrations and seed clearly labelled demo records.
5.  Configure the web server document root to `public/`.
6.  Create an administrator account through a secure setup command or
    one-time installer that is removed after use.
7.  Start the PHP application and open it through the configured local
    host.
8.  Run the device simulator and verify telemetry, alert, and command
    flows.

## Development mode

Build a simulator first. Simulated feeders and luminaires must be
visibly labelled `DEMO` and must never be confused with live municipal
assets.

## Production prerequisites

Before connecting live streetlights: - Approve hardware, protocol,
network, and cybersecurity design. - Commission electrical panels and
controllers with qualified personnel. - Confirm local fallback behavior
during cloud/network outage. - Approve voltage/current thresholds and
dimming policies. - Test command acknowledgement and rollback/failure
behavior. - Configure backups, monitoring, access control, and incident
response.

## Important safety note

This web application is supervisory software. It is not a substitute for
electrical protection, safe isolation procedures, statutory compliance,
or qualified electrical engineering. Never issue live switching or
dimming commands until hardware-in-the-loop testing and municipal
approval are complete.
