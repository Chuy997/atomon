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

// Variables para medir el tiempo (usando millis para no bloquear)
unsigned long startMillis;
unsigned long lastReadMillis = 0; 
const unsigned long resetInterval = 25 * 60 * 1000; // 25 minutos en milisegundos
const unsigned long readInterval = 2 * 60 * 1000;   // 2 minutos en milisegundos

void setup() {
  Serial.begin(115200);

  // Inicia el temporizador de reinicio
  startMillis = millis();

  // Conexión al WiFi con timeout para evitar bucle infinito
  Serial.print("Conectando a WiFi");
  WiFi.begin(ssid, password);
  int wifiAttempts = 0;
  while (WiFi.status() != WL_CONNECTED && wifiAttempts < 20) {
    delay(500);
    Serial.print(".");
    wifiAttempts++;
  }
  
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("\nError: No se pudo conectar al WiFi en el inicio. Reiniciando...");
    esp_restart();
  }
  Serial.println("\nConectado a WiFi");

  // Inicializar I2C con los pines definidos
  Wire.begin(17, 15); // SDA: GPIO 17, SCL: GPIO 15

  // Inicializar el sensor
  if (!am2315c.begin()) {
    Serial.println("No se encontró el sensor AM2315C. Verifica la conexión.");
    // En lugar de while(1) que cuelga el micro, mejor reiniciar e intentar nuevamente
    delay(2000);
    esp_restart();
  }
}

void loop() {
  unsigned long currentMillis = millis();

  // 1. Comprobar si han pasado 25 minutos para reiniciar por salud
  if (currentMillis - startMillis >= resetInterval) {
    Serial.println("25 minutos transcurridos. Reiniciando...");
    esp_restart();
  }

  // 2. Ejecutar la lectura de los datos cada 2 minutos usando temporizador no bloqueante
  if (currentMillis - lastReadMillis >= readInterval || lastReadMillis == 0) {
    lastReadMillis = currentMillis; // Actualizar el tiempo de la última lectura

    // Antes de procesar, verificar si sigue conectado y reconectar con timeout
    if (WiFi.status() != WL_CONNECTED) {
      Serial.println("WiFi desconectado. Intentando reconectar...");
      WiFi.reconnect();
      unsigned long reconnectStart = millis();
      // Pequeña espera asíncrona de 5 segundos para darle oportunidad de conectarse
      while (WiFi.status() != WL_CONNECTED && (millis() - reconnectStart) < 5000) {
        delay(100);
      }
    }

    // Leer temperatura y humedad
    if (am2315c.read() == AM2315C_OK) {
      float temperature = am2315c.getTemperature();
      float humidity = am2315c.getHumidity();
      
      // Uso de printf para hacer el código más limpio y fácil de leer
      Serial.printf("Sensor ID: %d\n", sensor_id);
      Serial.printf("Temperatura: %.2f °C\n", temperature);
      Serial.printf("Humedad: %.2f %%\n", humidity);

      // Enviar datos al backend si hay conexión
      if (WiFi.status() == WL_CONNECTED) {
        HTTPClient http;
        http.begin(serverUrl);
        http.addHeader("Content-Type", "application/json");

        // Usar un buffer estático (snprintf) es más amigable con la memoria de C++ que usar "String" concatenado
        char jsonData[128];
        snprintf(jsonData, sizeof(jsonData), "{\"sensor_id\":%d,\"temperature\":%.2f,\"humidity\":%.2f}", sensor_id, temperature, humidity);

        // Enviar el POST request
        int httpResponseCode = http.POST(jsonData);

        // Verificar la respuesta del servidor
        if (httpResponseCode > 0) {
          Serial.println("Datos enviados correctamente");
          Serial.println(http.getString());
        } else {
          Serial.printf("Error al enviar datos HTTP: %d\n", httpResponseCode);
        }
        http.end();
      } else {
        Serial.println("No se enviaron los datos porque el WiFi no se logró conectar.");
      }
    } else {
      Serial.println("Error al leer el sensor AM2315C.");
    }
  }
}
