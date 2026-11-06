# Project Memory and Domain Notes

## 1. Project identity

-   Project: IoT Centralized Control and Monitoring System (CCMS) for
    Public Street Lighting
-   Stakeholder: Gandhidham Municipal Corporation
-   Sector: Clean & Green Technology
-   Application stack: HTML, CSS, JavaScript, PHP, MySQL
-   Primary users: Electrical Engineers, Line Supervisors, Maintenance
    Contractors
-   Secondary beneficiaries: Residents, motorists, and commercial
    transport drivers

## 2. Problem context

Current operations depend heavily on manual night patrols and citizen
complaints. Key problems: 1. Non-functional fixtures are discovered
late. 2. Day-burning lights waste energy. 3. Full-brightness operation
through low-traffic midnight hours increases energy use. 4. Limited
feeder/luminaire telemetry makes phase imbalance, voltage fluctuation,
cable faults, and energy anomalies difficult to localize. 5. Manual
fault localization increases maintenance turnaround time.

## 3. Intended outcomes

-   Centralized visibility of feeder panels and luminaires.
-   Automated detection and alerting for lamp faults, day-burning, phase
    failure, abnormal voltage, and communication loss.
-   Scheduled astronomical switching and approved dimming profiles.
-   Faster fault assignment, repair, and closure.
-   Ward-wise and feeder-wise energy and maintenance reporting.

## 4. Important domain distinctions

-   **Feeder telemetry** describes electrical measurements at
    feeder/panel level.
-   **Luminaire telemetry** describes individual light status and, if
    supported by hardware, brightness and power.
-   **Commanded state** is what the system requested.
-   **Reported state** is what the device reports. These must be stored
    separately.
-   **Communication loss** is not the same as lamp failure.
-   **Possible theft / energy anomaly** is an investigation signal, not
    a definitive accusation.
-   **Astronomical schedule** uses geographic location and date to
    calculate sunrise/sunset.
-   **Dimming** is only possible when installed drivers/controllers
    support it.

## 5. Initial configurable assumptions

These values are placeholders for pilot configuration and must be
validated with electrical engineers and applicable standards: - Night
dimming example: 50--60% between 00:00 and 05:00. - Telemetry interval:
configurable by device class and network capacity. - Communication
timeout: configurable based on reporting interval. - Voltage/current
fault thresholds: approved by electrical engineering team. - Alert
escalation and repair SLA: set by corporation. - Tariff and energy-cost
model: supplied by finance/utility data.

## 6. Site configuration

Record and verify: - Municipality/site latitude and longitude. -
Timezone: `Asia/Kolkata`. - Ward boundaries and identifiers. -
Feeder/panel identifiers and electrical diagrams. - Luminaire
coordinates and parent feeder/panel. - Connected load, lamp type,
driver/controller capability. - Utility tariff and billing period. -
Approved switching and dimming policy.

## 7. Decisions log

  -----------------------------------------------------------------------
  Date                    Decision                Status
  ----------------------- ----------------------- -----------------------
  2026-11-06              Web application stack   Confirmed by project
                          is                      owner
                          HTML/CSS/JS/PHP/MySQL   

  2026-11-06              Use separate feeder and Recommended
                          luminaire telemetry     
                          concepts                

  2026-11-06              Use UTC in storage and  Recommended
                          Asia/Kolkata in UI      

  TBD                     Choose MQTT or HTTPS    Pending hardware/vendor
                          ingestion               review

  TBD                     Confirm hardware        Pending
                          protocol and dimming    
                          support                 

  TBD                     Confirm voltage         Pending corporation
                          thresholds, SLA, and    approval
                          dimming policy          
  -----------------------------------------------------------------------

## 8. Open questions

1.  Which smart feeder panel and luminaire controller hardware will be
    used?
2.  Does the field hardware support individual luminaire telemetry, or
    only feeder-level monitoring?
3.  Which communication network is available (4G, NB-IoT, LoRaWAN,
    Ethernet, or other)?
4.  Is an MQTT broker available, or should the MVP use HTTPS ingestion?
5.  Which SMS provider and sender-ID/template approvals are available?
6.  What are the approved dimming windows, minimum safe brightness, and
    override rules?
7.  What are the official ward/feeder/asset registers and GIS data
    sources?
