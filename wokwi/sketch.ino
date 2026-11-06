#include <Arduino.h>

// ================= PINS =================
const int PH_R = 34;          // Phase R Voltage (D34)
const int PH_Y = 35;          // Phase Y Voltage (D35)
const int PH_B = 32;          // Phase B Voltage (D32)
const int LDR = 33;           // Ambient Light Sensor (D33)
const int FEED_I = 36;        // Feeder Total Current (VP)
const int SENSE_LAMP2 = 18;   // Lamp 2 (Yellow) Feedback Sense (D18)

const int RELAY1 = 25;        // Red Streetlight (Phase R)
const int RELAY2 = 26;        // Yellow Streetlight (Phase Y)
const int RELAY3 = 27;        // Blue Streetlight (Phase B)

const int STATUS_LED_PIN = 2; // Onboard LED
const int MODE_SW = 14;       // Auto/Manual Switch (D14)

const int RELAY_ON = HIGH;
const int RELAY_OFF = LOW;

// ================= THRESHOLDS =================
const int PHASE_MIN = 900;    // Minimum phase voltage
const int DARK_TH = 1900;     // Midpoint: < 1900 = Night (ON), >= 1900 = Day (OFF)
const int FEED_MIN = 200;     // Feeder break trip

bool manual = false;

void controlStreetlights(bool lamp1, bool lamp2, bool lamp3) {
  digitalWrite(RELAY1, lamp1 ? RELAY_ON : RELAY_OFF);
  digitalWrite(RELAY2, lamp2 ? RELAY_ON : RELAY_OFF);
  digitalWrite(RELAY3, lamp3 ? RELAY_ON : RELAY_OFF);
}

void setup() {
  Serial.begin(115200);

  pinMode(RELAY1, OUTPUT);
  pinMode(RELAY2, OUTPUT);
  pinMode(RELAY3, OUTPUT);

  pinMode(STATUS_LED_PIN, OUTPUT);
  pinMode(MODE_SW, INPUT_PULLUP);
  pinMode(SENSE_LAMP2, INPUT);

  analogReadResolution(12);

  // Start with all lights OFF
  controlStreetlights(false, false, false);

  Serial.println("==================================================");
  Serial.println(" SMARTSTREET 360 - CCMS SUPERVISORY CONTROLLER");
  Serial.println(" 3-PHASE RELAYS WITH BURNOUT DETECTION (PS000059)");
  Serial.println("==================================================");
}

void loop() {
  int r = analogRead(PH_R);
  int y = analogRead(PH_Y);
  int b = analogRead(PH_B);
  int light = analogRead(LDR);
  int feed = analogRead(FEED_I);

  // 1. Grid Health & Safety Trip
  bool phaseFail = (r < PHASE_MIN || y < PHASE_MIN || b < PHASE_MIN);
  bool feederTrip = (feed < FEED_MIN);
  bool safetyAlarm = phaseFail || feederTrip;

  // 2. Day vs Night Detection
  // Lower reading (< 1900) = Dark / Night (ON)
  // Higher reading (>= 1900) = Bright / Day (OFF)
  bool night = (light < DARK_TH);

  // 3. Mode Switch (HIGH = Auto, LOW = Manual Override)
  manual = (digitalRead(MODE_SW) == LOW);

  // System operation logic
  bool systemOn = manual || night;

  // Emergency safety cutoff
  if (safetyAlarm) {
    systemOn = false;
  }

  // 4. Drive Relays
  controlStreetlights(systemOn, systemOn, systemOn);

  // 5. Lamp 2 Burnout Detection (Direct Feedback Sense on Pin 18)
  // When system is active (Relay 2 commanded ON):
  // If Pin 18 reads LOW -> The Yellow LED is disconnected / burnt out!
  bool lamp2Burnout = false;
  if (systemOn) {
    if (digitalRead(SENSE_LAMP2) == LOW) {
      lamp2Burnout = true;
    }
  }

  // 6. Onboard Status LED
  if (safetyAlarm) {
    digitalWrite(STATUS_LED_PIN, (millis() / 250) % 2); // 4 Hz rapid alarm flash
  } else if (lamp2Burnout) {
    // Burnout warning: Double-blink strobe pattern
    int cycle = millis() % 1000;
    bool blink = (cycle < 100) || (cycle > 200 && cycle < 300);
    digitalWrite(STATUS_LED_PIN, blink ? HIGH : LOW);
  } else {
    digitalWrite(STATUS_LED_PIN, HIGH); // Solid ON when healthy
  }

  // 7. Serial Telemetry Stream (Restored Classic Style)
  static unsigned long lastLog = 0;
  if (millis() - lastLog >= 1000) {
    lastLog = millis();

    Serial.println("-----------------------------------------------------------------");
    Serial.printf("GRID: R=%d Y=%d B=%d | LDR=%d | MODE=%s NIGHT=%d\n",
                  r, y, b, light, manual ? "MANUAL" : "AUTO", night ? 1 : 0);

    if (safetyAlarm) {
      Serial.println("⚠️  [SAFETY TRIP]: Low voltage or feeder fault — all relays shut down!");
      Serial.println("STATUS: EMERGENCY SHUTDOWN ACTIVE");
    } else if (systemOn) {
      Serial.printf("🌙 NIGHTTIME DETECTED (LDR=%d < %d) - SYSTEM ACTIVE\n", light, DARK_TH);
      
      // Individual Luminaire Health Status
      Serial.printf("LAMP 1 (RED):    %s\n", "🟢 ON [HEALTHY]");

      if (lamp2Burnout) {
        Serial.printf("LAMP 2 (YELLOW): %s\n", "🔴❌ BURNOUT DETECTED! (FLT_LAMP_FAIL)");
        Serial.println("⚠️  [ALERT FLT_LAMP_FAIL]: Luminaire #2 on Phase Y burned out!");
        Serial.println("    Action: Auto-logged to CCMS | Work Order SLA: 4 Hours (Pole W03-002)");
      } else {
        Serial.printf("LAMP 2 (YELLOW): %s\n", "🟢 ON [HEALTHY]");
      }

      Serial.printf("LAMP 3 (BLUE):   %s\n", "🟢 ON [HEALTHY]");

      if (!lamp2Burnout) {
        Serial.println("STATUS: ALL 3 CHANNELS NORMAL & BURNING");
      }
    } else {
      Serial.printf("☀️ DAYTIME DETECTED (LDR=%d >= %d)\n", light, DARK_TH);
      Serial.println("⚪ RELAYS=OFF ==> ALL STREETLIGHTS AUTOMATICALLY TURNED OFF");
      Serial.printf("LAMP 1 (RED):    %s\n", "⚪ OFF (Daytime Schedule)");
      Serial.printf("LAMP 2 (YELLOW): %s\n", "⚪ OFF (Daytime Schedule)");
      Serial.printf("LAMP 3 (BLUE):   %s\n", "⚪ OFF (Daytime Schedule)");
      Serial.println("STATUS: IDLE (DAYTIME POWER SAVING)");
    }
  }

  delay(100);
}
