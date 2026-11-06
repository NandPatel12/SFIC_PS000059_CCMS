# Phase 0 Discovery & Hardware Interface Specification
**Project:** Gandhidham Municipal Corporation Streetlight CCMS  
**Document Version:** 1.0.0 (Phase 0 Artifact)  
**Date:** 2026-11-06  
**Status:** Approved for Implementation

---

## 1. Municipal Inventory & Pilot Scope

### 1.1 Pilot Selection: Ward 3 & Ward 7
- **Ward 3 (Adipur - Commercial/Institutional Zone):**
  - **Feeders:** 2 Feeder Panels (`FDR-W03-FP01`, `FDR-W03-FP02`)
  - **Luminaires:** 180 LED Streetlights (70W - 120W) with 0-10V / DALI dimmable smart drivers.
  - **Pilot Characteristics:** Higher night traffic until 23:30; automated astronomical on/off with stepped dimming to 60% between 00:00 and 04:30.
- **Ward 7 (Gandhidham Port Corridor & Residential Axis):**
  - **Feeders:** 2 Feeder Panels (`FDR-W07-FP01`, `FDR-W07-FP02`)
  - **Luminaires:** 240 LED Streetlights (120W - 150W).
  - **Pilot Characteristics:** Heavy transport corridor; full brightness till 01:00, dimming to 70% 01:00-04:30.

---

## 2. Hardware Interface & Communications Specification

### 2.1 Communication Architecture
```
[Feeder Panel RTU / Energy Meter] (RS-485 Modbus RTU)
                  │
                  ▼
         [IoT Gateway / 4G Modem] 
                  │
                  ▼ HTTPS REST / MQTT over TLS (JSON Payload)
      [CCMS Telemetry Ingestion API]
```

### 2.2 Protocols & Telemetry Payloads
1. **Feeder Telemetry:**
   - Transport: HTTPS POST to `/api/telemetry/feeder` (Bearer token authentication per gateway).
   - Frequency: Every 60 seconds (nominal) or immediate on electrical trip/phase change.
   - Parameters: Phase voltages ($V_R, V_Y, V_B$), currents ($I_R, I_Y, I_B$), active power (kW), reactive power (kVAR), power factor, contactor status, cumulative kWh.
2. **Luminaire Telemetry:**
   - Transport: HTTPS POST to `/api/telemetry/luminaire` or batched via Feeder Gateway.
   - Parameters: Lamp status (ON/OFF), commanded brightness %, reported brightness %, voltage ($V$), power ($W$), burning hours, fault flags.

### 2.3 Local Fallback & Safe Operations
- **Network Outage Rule:** If the field gateway loses connection to CCMS server, it reverts to its on-board RTC and astronomical table stored in NVRAM.
- **Fail-Safe Lighting:** Luminaires must never turn off during nighttime due to backend disconnect or packet loss. Safe default: 100% illumination during scheduled active night hours.

---

## 3. Thresholds, Fault Rules & SLA Agreement

| Fault Code | Fault Condition | Trigger Window | Severity | Auto Work Order SLA |
|---|---|---|---|---|
| `FLT_PHASE_FAIL` | Any feeder phase voltage < 160V or phase lost | 2 consecutive readings | Critical | 2 hours |
| `FLT_DAY_BURNING` | Light reported ON during daytime (outside schedule + override) | 2 consecutive readings | Warning | 6 hours |
| `FLT_LAMP_FAIL` | Commanded ON (>0%) but reported OFF or power < 10W | 3 consecutive readings | Critical | 4 hours |
| `FLT_VOLT_SURGE` | Feeder voltage > 270V | Immediate (1 reading) | Critical | 1 hour |
| `FLT_COMM_TIMEOUT` | No heartbeat or telemetry from gateway | > 10 minutes | Warning | 4 hours |
| `FLT_ENERGY_ANOMALY` | Measured power exceeds connected capacity by > 20% | 3 consecutive readings | Warning | 8 hours (Inspection) |

---

## 4. Phase 0 Exit Sign-Off
- **Electrical Standards:** Adheres to IS 10322 / Bureau of Energy Efficiency (BEE) streetlighting guidelines.
- **Data Dictionary:** Normalized JSON schema mapped to MySQL relational tables.
- **Scope Baseline:** Delivered for implementation in Phase 1 Web Foundation.

