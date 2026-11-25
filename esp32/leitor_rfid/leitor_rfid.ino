#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>
#include <SPI.h>
#include <MFRC522.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>

const char* WIFI_SSID  = "Wokwi-GUEST";
const char* WIFI_SENHA = "";

const char* URL_API = "https://SEU-ENDERECO.trycloudflare.com/IoT_SA/api/armario.php";

#define ARMARIO_ID 1        // código do armário (ver página Armários)
#define TRAVA_PIN  5        // relé/LED da fechadura
#define TEMPO_ABERTO 5000   // ms que a fechadura fica liberada

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

void abrirArmario() {
  digitalWrite(TRAVA_PIN, HIGH);
  delay(TEMPO_ABERTO);
  digitalWrite(TRAVA_PIN, LOW);
}

void consultarApi(String codigo) {
  WiFiClientSecure cliente;
  cliente.setInsecure();

  HTTPClient http;
  http.begin(cliente, String(URL_API) + "?codigo=" + codigo + "&armario=" + ARMARIO_ID);
  http.addHeader("ngrok-skip-browser-warning", "1");

  int status = http.GET();

  if (status != 200) {
    Serial.println("Erro na API: " + String(status) + " " + http.errorToString(status));
    mostrar("Cartao: " + codigo, "Erro no servidor");
    http.end();
    return;
  }

  String resposta = http.getString();
  http.end();
  Serial.println("Resposta: " + resposta);

  JsonDocument json;

  if (deserializeJson(json, resposta)) {
    mostrar("Cartao: " + codigo, "Resposta invalida");
    return;
  }

  bool abrir        = json["abrir"] | false;
  String nome       = json["nome"] | "";
  String ferramenta = json["ferramenta"] | "";
  String prazo      = json["prazo"] | "";
  String mensagem   = json["mensagem"] | "";

  // Só abre com autorização válida do sistema WEB
  if (abrir) {
    mostrar("LIBERADO - " + nome, ferramenta, "Devolver: " + prazo);
    abrirArmario();
  } else {
    mostrar("NEGADO", "Cartao: " + codigo, mensagem);
  }
}

void setup() {
  Serial.begin(115200);

  pinMode(TRAVA_PIN, OUTPUT);
  digitalWrite(TRAVA_PIN, LOW);

  Wire.begin(OLED_SDA, OLED_SCL);
  tela.begin(SSD1306_SWITCHCAPVCC, 0x3C);

  SPI.begin(RFID_SCK, RFID_MISO, RFID_MOSI, RFID_SS);
  rfid.PCD_Init();

  conectarWiFi();
  mostrar("Armario " + String(ARMARIO_ID), "Aproxime o cartao");
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
  mostrar("Armario " + String(ARMARIO_ID), "Aproxime o cartao");
}
