#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <SPI.h>
#include <MFRC522.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
#include <WiFiClientSecure.h>


const char* WIFI_SSID  = "Wokwi-GUEST";
const char* WIFI_SENHA = "";


const char* URL_API = "https://immediately-exposure-samba-thee.trycloudflare.com/IoT_SA/api/armario.php";


#define ARMARIO_ID 1        
#define TRAVA_PIN  5        
#define TEMPO_ABERTO 5000   


#define RFID_SS   0
#define RFID_RST  4
#define RFID_SCK  1
#define RFID_MOSI 2
#define RFID_MISO 3


#define OLED_SDA 18
#define OLED_SCL 19


MFRC522 rfid(RFID_SS, RFID_RST);
Adafruit_SSD1306 tela(128, 64, &Wire, -1);


bool armarioAberto = false;
String cartaoAberto = "";
String nomeAberto = "";


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
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_SENHA, 6);

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


void fecharArmario() {
  digitalWrite(TRAVA_PIN, LOW);
  armarioAberto = false;
  cartaoAberto = "";
}

void mostrarEspera() {
  if (armarioAberto) {
    mostrar("ABERTO", nomeAberto, "Passe o mesmo cartao");
  } else {
    mostrar("Armario " + String(ARMARIO_ID), "Aproxime o cartao");
  }
}

void consultarApi(String codigo) {
  String url = String(URL_API) + "?codigo=" + codigo + "&armario=" + ARMARIO_ID;
  String resposta = "";
  int status = 0;

 
  for (int tentativa = 1; tentativa <= 3; tentativa++) {
    WiFiClientSecure cliente;
    cliente.setInsecure();

    HTTPClient http;
    http.setConnectTimeout(10000);
    http.setTimeout(10000);
    http.begin(cliente, url);
    http.addHeader("ngrok-skip-browser-warning", "1");

    status = http.GET();

    if (status == 200) {
      resposta = http.getString();
      http.end();
      break;
    }

    Serial.println("Erro na API (tentativa " + String(tentativa) + "): " + String(status) + " " + HTTPClient::errorToString(status));
    if (status > 0) {
      Serial.println(http.getString().substring(0, 300));   // mostra o corpo do erro
    }
    http.end();

    if (status != -1) break;

    mostrar("Cartao: " + codigo, "Tentando de novo", String(tentativa) + "/3");
    delay(1500);
  }

  if (status != 200) {
    mostrar("Cartao: " + codigo, "Erro no servidor", "Codigo " + String(status));
    return;
  }

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


  
  if (abrir) {
    mostrar("LIBERADO - " + nome, ferramenta, "Devolver: " + prazo);
    digitalWrite(TRAVA_PIN, HIGH);
    armarioAberto = true;
    cartaoAberto = codigo;
    nomeAberto = nome;
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

  
  if (armarioAberto) {
    if (codigo == cartaoAberto) {
      fecharArmario();
      mostrar("FECHADO", nomeAberto, "Ate logo!");
    } else {
      mostrar("Armario em uso", nomeAberto, "Use o mesmo cartao");
    }
    rfid.PICC_HaltA();
    delay(3000);
    mostrarEspera();
    return;
  }

  mostrar("Cartao: " + codigo, "Consultando...");


  consultarApi(codigo);
  rfid.PICC_HaltA();


  delay(3000);
  mostrarEspera();
}