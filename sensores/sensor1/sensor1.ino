#include <Wire.h>
#include <AM2315C.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <esp_system.h> // Biblioteca para usar esp_restart()

// Credenciales WiFi
const char* ssid = "MX-wireless2.0"; // Reemplaza con el nombre de tu red WiFi
const char* password = ""; // Reemplaza con la contraseña de tu red WiFi

// URL del servidor backend
const char* serverUrl = "http://10.232.118.150/atomon/receive_data.php"; // Cambia la IP a la de tu servidor local

// ID del sensor (modifica este valor para cada sensor cuando uses varios)
const int sensor_id = 5;

// Instancia del sensor AM2315C
AM2315C am2315c;

// Variable para medir el tiempo
unsigned long startMillis;
const unsigned long resetInterval = 25 * 60 * 1000; // 25 minutos en milisegundos

void setup() {
  Serial.begin(115200);

  // Inicia el temporizador
  startMillis = millis();

  // Conexión al WiFi
  Serial.print("Conectando a WiFi");
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(1000);
    Serial.print(".");
  }
  Serial.println("\nConectado a WiFi");

  // Inicializar I2C con los pines definidos
  Wire.begin(17, 15); // SDA: GPIO 17, SCL: GPIO 15

  // Inicializar el sensor
  if (!am2315c.begin()) {
    Serial.println("No se encontró el sensor AM2315C. Verifica la conexión.");
    while (1);
  }
}

void loop() {
  // Comprobar si han pasado 25 minutos para reiniciar
  if (millis() - startMillis >= resetInterval) {
    Serial.println("25 minutos transcurridos. Reiniciando...");
    esp_restart();
  }

  // Leer temperatura y humedad
  if (am2315c.read() == AM2315C_OK) {
    float temperature = am2315c.getTemperature();
    float humidity = am2315c.getHumidity();
    Serial.print("Sensor ID: ");
    Serial.println(sensor_id);
    Serial.print("Temperatura: ");
    Serial.print(temperature);
    Serial.println(" °C");
    Serial.print("Humedad: ");
    Serial.print(humidity);
    Serial.println(" %");

    // Enviar datos al backend
    if (WiFi.status() == WL_CONNECTED) {
      HTTPClient http;
      http.begin(serverUrl);
      http.addHeader("Content-Type", "application/json");

      // Crear el cuerpo del JSON con los datos
      String jsonData = "{\"sensor_id\":" + String(sensor_id) +
                        ",\"temperature\":" + String(temperature) +
                        ",\"humidity\":" + String(humidity) + "}";

      // Enviar el POST request
      int httpResponseCode = http.POST(jsonData);

      // Verificar la respuesta del servidor
      if (httpResponseCode > 0) {
        Serial.println("Datos enviados correctamente");
        Serial.println(http.getString());
      } else {
        Serial.print("Error al enviar datos: ");
        Serial.println(httpResponseCode);
      }
      http.end();
    } else {
      Serial.println("WiFi desconectado. Intentando reconectar...");
      WiFi.reconnect();
    }
  } else {
    Serial.println("Error al leer el sensor.");
  }

  // Esperar 2 minutos antes de la próxima lectura
  delay(120000);
}
