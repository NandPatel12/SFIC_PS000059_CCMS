# SmartStreet 360 (PS000059) — ESP32 CCMS 3-Channel Relay Simulation

This directory contains the complete Wokwi hardware simulation for the **SmartStreet 360 / CCMS (Centralized Control & Monitoring System)** 3-Phase Feeder & Luminaire Controller with **individual lamp burnout detection** (`FLT_LAMP_FAIL`).

---

## 📋 Pinout & Component Mapping

| Component | Wokwi ID | ESP32 Pin | Function / Type | Normal Range / State |
|---|---|---|---|---|
| **Phase R Voltage** | `potR` | `esp:D34` (ADC1_CH6) | Analog Potentiometer | Normal: > 900 ADC (`PHASE_MIN`) |
| **Phase Y Voltage** | `potY` | `esp:D35` (ADC1_CH7) | Analog Potentiometer | Normal: > 900 ADC (`PHASE_MIN`) |
| **Phase B Voltage** | `potB` | `esp:D32` (ADC1_CH4) | Analog Potentiometer | Normal: > 900 ADC (`PHASE_MIN`) |
| **Ambient Light (LDR)** | `potLDR` | `esp:D33` (ADC1_CH5) | Analog Potentiometer | Night: < 3000 (`DARK_TH`), Day: ≥ 3000 |
| **Feeder Total Current**| `potFeed` | `esp:VP` (ADC1_CH0) | Analog Potentiometer | Normal: > 1600 ADC; < 200 = Trip |
| **Lamp 2 Sense Feedback**| `sw_burn:2`| `esp:D18` | Digital Input Feedback | HIGH = Lamp 2 glowing; LOW = Burned out |
| **Lamp 2 Burnout Switch**| `sw_burn` | Between Relay 2 & LED 2 | Slide Switch | Left (Pin 1) = Normal; Right (Pin 3) = Burnout |
| **Mode Switch** | `sw` | `esp:D14` | Slide Switch (INPUT_PULLUP) | HIGH = Auto Mode; LOW = Manual Override |
| **Status Alarm LED** | Onboard | `GPIO 2` | Digital Output | Solid ON = Normal; Double Blink = Burnout; 4 Hz = Trip |
| **Relay 1 (Phase R)** | `relay1` | `esp:D25` | Active-HIGH Relay Module | Controls Red Luminaire (`led1`) |
| **Relay 2 (Phase Y)** | `relay2` | `esp:D26` | Active-HIGH Relay Module | Controls Yellow Luminaire (`led2`) |
| **Relay 3 (Phase B)** | `relay3` | `esp:D27` | Active-HIGH Relay Module | Controls Blue Luminaire (`led3`) |

---

## ⚡ Burnout Detection Architecture

```text
Relay 2 (NO) ──► [ Burnout Switch: sw_burn ] ──┬──► Yellow LED (Lamp 2) ──► Resistor ──► GND
                                                │
                                                └──► ESP32 GPIO 18 (SENSE_LAMP2)
```

1. **Normal Operation:**
   - Switch closed: Current flows to Yellow LED (Lamp 2 illuminates).
   - ESP32 GPIO 18 senses `HIGH`.
   - Serial Monitor reports: `LAMP 2 (YELLOW): 🟢 HEALTHY [ON]`.
2. **Burnout Trigger (Flipping `sw_burn`):**
   - Circuit opens: Yellow LED goes **DARK** (simulating fixture/driver burnout).
   - ESP32 GPIO 18 drops to `LOW` (pulled down by the resistor).
   - ESP32 detects: Relay 2 commanded ON, but sense wire is `LOW`!
   - System triggers `FLT_LAMP_FAIL`:
     - Serial Monitor reports: `LAMP 2 (YELLOW): 🔴❌ BURNOUT DETECTED! (FLT_LAMP_FAIL)`
     - Status LED enters double-blink warning strobe.
     - Auto-dispatches 4-hour SLA municipal work order!
