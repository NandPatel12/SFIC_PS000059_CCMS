#include <Arduino.h>

// ================= SENSOR INPUTS =================
const int PH_R = 34;
const int PH_Y = 35;
const int PH_B = 32;
const int LDR = 33;
const int FEED_I = 36;

// ================= RELAY OUTPUTS =================
const int RELAY1 = 25;
const int RELAY2 = 26;
const int RELAY3 = 27;

const int STATUS_LED_PIN = 2;
const int MODE_SW = 14;

// Wokwi default relay: LOW activates COM-to-NO
const int RELAY_ON = LOW;
const int RELAY_OFF = HIGH;

// ================= THRESHOLDS =================
const int PHASE_MIN = 900;
const int DARK_TH = 3000;
const int FEED_MIN = 200;

bool manual = false;

// ================= RELAY CONTROL =================
void controlStreetlights(bool lamp1,
                         bool lamp2,
                         bool lamp3) {

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

  analogReadResolution(12);

  // Keep all relays OFF during startup
  controlStreetlights(false, false, false);

  Serial.println("================================");
  Serial.println(" SMARTSTREET 360 - PS000059");
  Serial.println(" 3-CHANNEL RELAY SYSTEM");
  Serial.println("================================");
}

// ================= MAIN LOOP =================
void loop() {

  // Read sensors
  int r = analogRead(PH_R);
  int y = analogRead(PH_Y);
  int b = analogRead(PH_B);

  int light = analogRead(LDR);
  int feed = analogRead(FEED_I);

  // Detect phase failure
  bool phaseFail =
      (r < PHASE_MIN ||
       y < PHASE_MIN ||
       b < PHASE_MIN);

  // Detect day and night
  bool night = (light < DARK_TH);

  // Switch LOW = Manual ON
  // Switch HIGH = Automatic
  manual = (digitalRead(MODE_SW) == LOW);

  // Determine system operation
  bool systemOn = manual || night;

  // Safety shutdown
  bool feederFault = (feed < FEED_MIN);
  bool alarm = phaseFail || feederFault;

  if (alarm) {
    systemOn = false;
  }

  // Control three independent relays
  controlStreetlights(
      systemOn,
      systemOn,
      systemOn
  );

  // Fault indicator
  digitalWrite(
      STATUS_LED_PIN,
      alarm ? ((millis() / 250) % 2) : HIGH
  );

  // Serial monitoring
  static unsigned long lastTime = 0;

  if (millis() - lastTime >= 1000) {

    lastTime = millis();

    Serial.println("--------------------------------");

    Serial.printf(
      "PHASE R=%d Y=%d B=%d\n", r, y, b
    );

    Serial.printf(
      "LDR=%d FEED=%d\n", light, feed
    );

    Serial.printf(
      "MODE=%s NIGHT=%d\n",
      manual ? "MANUAL" : "AUTO",
      night
    );

    Serial.printf(
      "PHASE_FAULT=%d FEED_FAULT=%d\n",
      phaseFail, feederFault
    );

    Serial.printf(
      "RELAY1=%s RELAY2=%s RELAY3=%s\n",
      systemOn ? "ON" : "OFF",
      systemOn ? "ON" : "OFF",
      systemOn ? "ON" : "OFF"
    );

    Serial.printf(
      "SYSTEM=%s FAULT=%d\n",
      systemOn ? "ON" : "OFF",
      alarm
    );
  }

  delay(100);
}
