#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <SPI.h>
#include <MFRC522.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>

const char* WIFI_SSID  = "Wokwi-GUEST";
const char* WIFI_SENHA = "";

const char* URL_API = "http://192.168.0.10/IoT_SA/api/rfid.php";

#define RFID_SS   0
#define RFID_RST  4
#define RFID_SCK  1
#define RFID_MOSI 2
#define RFID_MISO 3

#define OLED_SDA 18
#define OLED_SCL 19

MFRC522 rfid(RFID_SS, RFID_RST);
Adafruit_SSD1306 tela(128, 64, &Wire, -1);

void mostrar(String linha1, String linha2 = "", String linha3 = "") {
  tela.clearDisplay();
  tela.setTextSize(1);
  tela.setTextColor(SSD1306_WHITE);
  tela.setCursor(0, 0);
  tela.println(linha1);
  tela.setCursor(0, 20);
  tela.println(linha2);
  tela.setCursor(0, 40);
  tela.println(linha3);
  tela.display();
}

void conectarWiFi() {
  mostrar("Conectando WiFi...");
  WiFi.begin(WIFI_SSID, WIFI_SENHA);

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println("\nWiFi conectado: " + WiFi.localIP().toString());
}

String lerCodigo() {
  String codigo = "";

  for (byte i = 0; i < rfid.uid.size; i++) {
    if (rfid.uid.uidByte[i] < 0x10) codigo += "0";
    codigo += String(rfid.uid.uidByte[i], HEX);
  }

  codigo.toUpperCase();
  return codigo;
}

void consultarApi(String codigo) {
  HTTPClient http;
  http.begin(String(URL_API) + "?codigo=" + codigo);

  int status = http.GET();

  if (status != 200) {
    Serial.println("Erro na API: " + String(status));
    mostrar("Cartao: " + codigo, "Erro no servidor");
    http.end();
    return;
  }

  String resposta = http.getString();
  http.end();
  Serial.println("Resposta: " + resposta);

  JsonDocument json;
  deserializeJson(json, resposta);

  bool autorizado  = json["autorizado"];
  String nome      = json["nome"] | "";
  String mensagem  = json["mensagem"] | "";

  if (autorizado) {
    mostrar("AUTORIZADO", nome, mensagem);
  } else {
    mostrar("NEGADO", "Cartao: " + codigo, mensagem);
  }
}

void setup() {
  Serial.begin(115200);

  Wire.begin(OLED_SDA, OLED_SCL);
  tela.begin(SSD1306_SWITCHCAPVCC, 0x3C);

  SPI.begin(RFID_SCK, RFID_MISO, RFID_MOSI, RFID_SS);
  rfid.PCD_Init();

  conectarWiFi();
  mostrar("Ferramentaria", "Aproxime o cartao");
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    conectarWiFi();
  }

  if (!rfid.PICC_IsNewCardPresent() || !rfid.PICC_ReadCardSerial()) {
    delay(50);
    return;
  }

  String codigo = lerCodigo();
  Serial.println("Cartao lido: " + codigo);
  mostrar("Cartao: " + codigo, "Consultando...");

  consultarApi(codigo);
  rfid.PICC_HaltA();

  delay(3000);
  mostrar("Ferramentaria", "Aproxime o cartao");
}
