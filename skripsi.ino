#include <Wire.h>
#include <Adafruit_MPU6050.h>
#include <Adafruit_Sensor.h>
#include <TinyGPS++.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
#include <WiFi.h>
#include <HTTPClient.h>

// Masukkan Otak Kecerdasan Buatan Edge Impulse
#include <Klasifikasi_Jalan_MPU6050_inferencing.h> 

// ==========================================
// KONFIGURASI JARINGAN INTERNET & SERVER
// ==========================================
const char* ssid     = "1";      // Ganti dengan nama hotspot HP
const char* password = "11111111";      // Ganti dengan password hotspot HP
const char* serverName = "http://laporlubang.web.id:8081/insert.php";
// Konfigurasi Layar OLED
#define SCREEN_WIDTH 128
#define SCREEN_HEIGHT 64
#define OLED_RESET    -1
Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, OLED_RESET);

// Inisialisasi Sensor
Adafruit_MPU6050 mpu;
TinyGPSPlus gps;

#define RXD2 16
#define TXD2 17

#define INTERVAL_MS 200
unsigned long lastSampleTime = 0;
unsigned long lastPotholeSendTime = 0; // Cooldown untuk cegah spam database
unsigned long lastWiFiCheckTime = 0;   // Cooldown untuk cek koneksi terputus

float feature_buffer[EI_CLASSIFIER_DSP_INPUT_FRAME_SIZE];
int feature_ix = 0;

String statusJalan = "Mengoneksi...";

// Fungsi untuk mengirim data ke Web Server Laragon via HTTP POST
void kirimDataKeWeb(float lat, float lon, String kategori) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    
    // Mulai koneksi ke server target
    http.begin(serverName);
    
    // Atur header konten sebagai form data URL encoded
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    
    // Susun payload data POST sesuai variabel di insert.php
    String httpRequestData = "latitude=" + String(lat, 7) +
                             "&longitude=" + String(lon, 7) +
                             "&kategori=" + kategori;
                             
    // Kirim permintaan POST
    int httpResponseCode = http.POST(httpRequestData);
    
    Serial.print("HTTP Response code: ");
    Serial.println(httpResponseCode);
    
    // Tutup koneksi untuk menghemat daya baterai
    http.end();
  } else {
    Serial.println("Wi-Fi Terputus! Gagal mengirim data.");
  }
}

void setup() {
  Serial.begin(115200);

  // TAMBAHAN REVISI: Memperbesar RX Buffer GPS untuk mencegah GPS freeze saat HTTP POST
  Serial2.setRxBufferSize(2048); 
  
  // Setup GPS (Baudrate disesuaikan dengan modul Ublox NEO-6M, biasanya 9600)
  Serial2.begin(9600, SERIAL_8N1, RXD2, TXD2);

  // 1. INISIALISASI LAYAR OLED
  if(!display.begin(SSD1306_SWITCHCAPVCC, 0x3C)) {
    Serial.println(F("Gagal inisialisasi OLED"));
    for(;;);
  }
  display.clearDisplay();
  display.setTextSize(1);
  display.setTextColor(SSD1306_WHITE);
  display.setCursor(0, 10);
  display.println("Menghubungkan Wi-Fi...");
  display.display();

  // 2. MENGHUBUNGKAN KE INTERNET VIA WI-FI
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWi-Fi Terhubung!");
  
  display.clearDisplay();
  display.setCursor(0, 10);
  display.println("Wi-Fi Terhubung!");
  display.println("Sistem AI Siap...");
  display.display();
  delay(1500);

  // 3. INISIALISASI MPU6050
  if (!mpu.begin()) {
    display.clearDisplay();
    display.setCursor(0, 10);
    display.println("Error: MPU6050 Lepas!");
    display.display();
    while (1) { delay(10); }
  }
  mpu.setAccelerometerRange(MPU6050_RANGE_8_G);
  mpu.setFilterBandwidth(MPU6050_BAND_10_HZ);
}

void loop() {
  // === AUTO RECONNECT WI-FI ===
  // Cek apakah WiFi terputus, coba sambung ulang tiap 5 detik tanpa membuat lag
  if (WiFi.status() != WL_CONNECTED) {
    if (millis() - lastWiFiCheckTime >= 5000) {
      WiFi.disconnect();
      WiFi.begin(ssid, password);
      lastWiFiCheckTime = millis();
    }
  }

  // Baca data GPS secara kontinu
  while (Serial2.available() > 0) {
    gps.encode(Serial2.read());
  }

  // Pengambilan data sensor getaran (Setiap 200ms)
  if (millis() - lastSampleTime >= INTERVAL_MS) {
    lastSampleTime = millis();

    sensors_event_t a, g, temp;
    mpu.getEvent(&a, &g, &temp);

    float x = a.acceleration.x;
    float y = a.acceleration.y;
    float z = a.acceleration.z;
    float resultan = sqrt((x*x) + (y*y) + (z*z));

    feature_buffer[feature_ix++] = x;
    feature_buffer[feature_ix++] = y;
    feature_buffer[feature_ix++] = z;
    feature_buffer[feature_ix++] = resultan;

    // Jika buffer jendela waktu terisi penuh
    if (feature_ix >= EI_CLASSIFIER_DSP_INPUT_FRAME_SIZE) {
      
      signal_t features_signal;
      int err = numpy::signal_from_buffer(feature_buffer, EI_CLASSIFIER_DSP_INPUT_FRAME_SIZE, &features_signal);
      
      if (err == 0) {
        ei_impulse_result_t result = { 0 };
        EI_IMPULSE_ERROR res = run_classifier(&features_signal, &result, false);

        if (res == EI_IMPULSE_OK) {
          float max_val = 0;
          int best_idx = 0;
          for (uint16_t i = 0; i < EI_CLASSIFIER_LABEL_COUNT; i++) {
            if (result.classification[i].value > max_val) {
              max_val = result.classification[i].value;
              best_idx = i;
            }
          }

          // Kamus Menerjemahkan Label Angka Biner AI ke Teks
          String labelBawaan = String(result.classification[best_idx].label);
          String tebakanAI = "";
          
          if (labelBawaan == "0") {
            tebakanAI = "Mulus";
          } else if (labelBawaan == "1") {
            tebakanAI = "Lubang";
          } else if (labelBawaan == "2") {
            tebakanAI = "P. Tidur";
          }

          // === JARING PENGAMAN FISIKA BERLAPIS (ANTI-HALUSINASI AI) ===
          // 1. Filter Gravitasi Statis (Kendaraan Diam / Jalan Sangat Mulus)
          if (resultan >= 8.5 && resultan <= 11.5) {
              tebakanAI = "Mulus"; 
          }

          // 2. Penyaringan Ketat untuk Label "Lubang"
          // Lubang riil di jalan raya pasti menghasilkan guncangan ekstrem.
          // Jika AI menebak "Lubang" tapi resultan getarannya hanya guncangan kecil (di bawah 13.5 m/s^2),
          // maka itu dipastikan hanyalah aspal kasar atau getaran mesin. Paksa menjadi "Mulus".
          if (tebakanAI == "Lubang") {
              if (resultan > 11.5 && resultan < 13.5) {
                  tebakanAI = "Mulus";
              }
          }

          // 3. Penyaringan Ketat untuk Label "P. Tidur" (Polisi Tidur)
          // Polisi tidur umumnya dilewati dengan kecepatan lebih rendah dan guncangannya lebih konstan.
          // Jika AI menebak Polisi Tidur tapi getarannya terlalu radikal (di atas 16.0 m/s^2), 
          // kemungkinan besar itu adalah lubang yang dalam, bukan polisi tidur.
          if (tebakanAI == "P. Tidur") {
              if (resultan > 16.0) {
                  tebakanAI = "Lubang";
              }
          }
          // ============================================================

         statusJalan = tebakanAI;

          // === 1. FITUR LIVE STREAMING (Sinkronisasi 3 Sumbu + GPS) ===
          kirimDataLive(x, y, z, resultan, statusJalan, gps.location.lat(), gps.location.lng());
          // ==============================================================


          // === 2. FITUR DATABASE & PETA (Mode Tempur Lapangan Riil) =====
          // Kirim ke database HANYA jika jalan rusak DAN satelit sudah mengunci (Valid)
          if ((tebakanAI == "Lubang" || tebakanAI == "P. Tidur") && gps.location.isValid()) {
            
            // Jeda 1 detik (1000ms) menggunakan millis agar tidak membombardir database 
            // saat melindas lubang panjang, TAPI tidak membuat pembacaan GPS jadi macet/lag.
            if (millis() - lastPotholeSendTime >= 1000) {
                // Ambil koordinat asli dari modul GPS
                float current_lat = gps.location.lat();
                float current_lon = gps.location.lng();
                
                kirimDataKeWeb(current_lat, current_lon, tebakanAI);
                lastPotholeSendTime = millis(); 
            }
            
          } else if ((tebakanAI == "Lubang" || tebakanAI == "P. Tidur") && !gps.location.isValid()) {
            // Tampilkan peringatan di Serial Monitor jika alat mendeteksi lubang tapi GPS masih buta
            Serial.println("Deteksi berhasil, tapi sinyal GPS belum mengunci. Data tidak dikirim ke Peta.");
          }
        }
      }
      feature_ix = 0; 
    }

    // Refresh Tampilan Antarmuka OLED Diagnostik
    display.clearDisplay();
    display.setTextSize(1);
    display.setCursor(0, 0);
    if (gps.location.isValid()) {
      display.print("Satelit OK ("); display.print(gps.satellites.value()); display.println(")");
    } else {
      display.println("Mencari Satelit...");
    }

    display.setCursor(0, 15);
    display.setTextSize(2); 
    display.println(statusJalan); 
    
    display.setTextSize(1);
    display.setCursor(0, 38);
    display.print("Z:"); display.print(z, 1);
    display.print(" R:"); display.print(resultan, 1);

    display.setCursor(0, 52);
    display.print("L:");
    display.print(gps.location.isValid() ? String(gps.location.lat(), 5) : "-");

    display.display();
  }
}

void ei_printf(const char *format, ...) {
    static char print_buf[1024] = { 0 };
    va_list args;
    va_start(args, format);
    int r = vsnprintf(print_buf, sizeof(print_buf), format, args);
    va_end(args);
    if (r > 0) { Serial.print(print_buf); }
}

// Fungsi tambahan untuk menembakkan data live stream ke monitor.php
void kirimDataLive(float x_val, float y_val, float z_val, float res_val, String stat, float lat_val, float lng_val) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    String targetLiveUrl = "http://laporlubang.web.id:8081/live_ping.php"; 
    
    http.begin(targetLiveUrl);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    
    // Menyusun payload POST dengan menambahkan variabel x dan y
    String httpRequestData = "x=" + String(x_val, 2) +
                             "&y=" + String(y_val, 2) +
                             "&z=" + String(z_val, 2) + 
                             "&resultan=" + String(res_val, 2) + 
                             "&status=" + stat +
                             "&latitude=" + String(lat_val, 6) +
                             "&longitude=" + String(lng_val, 6);
                             
    int httpResponseCode = http.POST(httpRequestData);
    http.end();
  }
}