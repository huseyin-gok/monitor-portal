# Monitor Portal - Müşteri İzleme Sistemi

Production-ready, güvenli müşteri izleme portalı. Her müşteri sadece kendine ait Grafana dashboard'larını görür ve yönetir.

## 📋 İçindekiler

- [Özellikler](#-özellikler)
- [Mimari](#-mimari)
- [Gereksinimler](#-gereksinimler)
- [Kurulum](#-kurulum)
- [Konfigürasyon](#-konfigürasyon)
- [Kullanım](#-kullanım)
- [Güvenlik](#-güvenlik)
- [API Dokümantasyonu](#-api-dokümantasyonu)
- [Lisans](#-lisans)
- [Ekran Görüntüleri](#-ekrangoruntu)

## ✨ Özellikler

### 🔐 Güvenlik
- **Müşteri bazlı yetkilendirme**: Her müşteri sadece kendi dashboard'unu görür
- **Güvenli PHP authentication**: Brute force koruması, session güvenliği
- **Grafana folder bazlı izolasyon**: Her müşteri için ayrı Grafana folder'ı
- **HTTPS zorunlu**: Tüm trafik şifrelenir
- **SQL Injection koruması**: Prepared statements kullanımı
- **XSS koruması**: Tüm çıktılar temizlenir
- **CSRF koruması**: Session token'ları ile koruma

### 📊 Dashboard Özellikleri
- **Grafana entegrasyonu**: Panel bazlı dashboard gösterimi
- **Zaman aralığı seçimi**: Esnek zaman filtreleme (5 dakika - 30 gün)
- **Otomatik yenileme**: Yapılandırılabilir refresh interval
- **Responsive tasarım**: Mobil ve tablet uyumlu
- **Zabbix entegrasyonu**: Opsiyonel Zabbix grafik desteği

### 👥 Kullanıcı Yönetimi
- **Admin paneli**: Müşteri ve kullanıcı yönetimi
- **Kullanıcı yönetim sayfası**: Detaylı kullanıcı CRUD işlemleri
- **Müşteri yönetimi**: Grafana folder ve dashboard otomatik oluşturma
- **Template dashboard desteği**: Yeni müşteriler için otomatik dashboard kopyalama

## 🏗️ Mimari

```
Internet
   ↓
NGINX (SSL Termination)
   ↓
PHP Portal (Authentication & Authorization)
   ↓
MySQL (User & Customer Database)
   ↓
Grafana (Dashboard Rendering)
   ↓
Zabbix Datasource (Optional)
```

### Bileşenler

1. **PHP Portal** (`https://monitor.sirket.com`)
   - Kullanıcı authentication
   - Müşteri bazlı yetkilendirme
   - Dashboard gösterimi

2. **Grafana** (`http://localhost:3000` veya NGINX reverse proxy)
   - Monitoring dashboard'ları
   - Folder bazlı izolasyon
   - Anonymous kullanıcı desteği

3. **NGINX** (Opsiyonel)
   - SSL termination
   - Reverse proxy
   - Güvenlik header'ları

4. **MySQL**
   - Kullanıcı ve müşteri veritabanı
   - Session yönetimi

## 📦 Gereksinimler

### Sunucu Gereksinimleri
- **PHP**: 8.3 veya üzeri
- **MySQL/MariaDB**: 5.7 veya üzeri
- **Grafana**: 12.0 veya üzeri (önemli: Grafana 12+ için özel ayarlar gerekebilir)
- **NGINX**: 1.18+ (opsiyonel, reverse proxy için)
- **cURL**: PHP extension
- **PDO**: MySQL extension

### PHP Extension'ları
```bash
php -m | grep -E "(pdo_mysql|curl|json|session)"
```

### Grafana Gereksinimleri
- Grafana API Key (Service Account veya Admin API Key)
- Anonymous authentication aktif (opsiyonel)
- Folder permission yönetimi

## 🚀 Kurulum

### 1. Projeyi İndirin

```bash
git clone https://github.com/kullanici/monitor-portal.git
cd monitor-portal
```

### 2. Veritabanı Kurulumu

```bash
mysql -u root -p < database.sql
```

Veya MySQL'e bağlanıp SQL dosyasını çalıştırın:

```sql
-- database.sql dosyasını çalıştırın
source database.sql;
```

### 3. Konfigürasyon

`config.php` dosyasını düzenleyin:

```php
// Veritabanı ayarları
define('DB_HOST', 'localhost');
define('DB_NAME', 'monitor');
define('DB_USER', 'monitor');
define('DB_PASS', 'güvenli_şifre');

// Grafana ayarları
define('GRAFANA_INTERNAL_URL', 'http://localhost:3000');
define('GRAFANA_PUBLIC_URL', 'http://localhost:3000'); // veya NGINX reverse proxy URL'i
define('GRAFANA_API_KEY', 'glsa_...'); // Grafana Service Account API Key
define('GRAFANA_ORG_ID', 1);

// Zabbix ayarları (opsiyonel)
define('ZABBIX_URL', 'http://zabbix-server/zabbix/api_jsonrpc.php');
define('ZABBIX_USER', 'monitor');
define('ZABBIX_PASS', 'şifre');
```

**ÖNEMLİ**: Production ortamında hassas bilgileri environment variable'lara taşıyın:

```php
define('DB_PASS', getenv('DB_PASSWORD'));
define('GRAFANA_API_KEY', getenv('GRAFANA_API_KEY'));
```

### 4. Grafana Konfigürasyonu

#### Grafana API Key Oluşturma

1. Grafana'ya admin olarak giriş yapın
2. **Configuration > API Keys** (veya **Service Accounts**)
3. Yeni bir Service Account oluşturun veya API Key ekleyin
4. Role: **Admin** (folder ve dashboard oluşturma için gerekli)
5. Key'i kopyalayın ve `config.php`'ye ekleyin

#### Grafana.ini Ayarları

`grafana.ini` dosyasındaki ayarları Grafana config dosyanıza ekleyin:

```bash
sudo cp grafana.ini /etc/grafana/grafana.ini
# Veya mevcut config'e ayarları ekleyin
sudo systemctl restart grafana-server
```

**Önemli Ayarlar**:
- `allow_embedding = true` (iframe desteği için)
- `anonymous` mode (opsiyonel, her müşteri için ayrı kullanıcı oluşturmak istemiyorsanız)

Detaylı Grafana kurulum rehberi için `GRAFANA_KURULUM.md` dosyasına bakın.

### 5. NGINX Konfigürasyonu (Opsiyonel)

NGINX reverse proxy kullanmak istiyorsanız:

```bash
sudo cp nginx.conf /etc/nginx/sites-available/monitor
sudo ln -s /etc/nginx/sites-available/monitor /etc/nginx/sites-enabled/
```

SSL sertifikası ekleyin:

```bash
sudo certbot --nginx -d monitor.sirket.com
```

NGINX'i test edin ve yeniden başlatın:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

### 6. Dosya İzinleri

```bash
# Web dizini
sudo chown -R www-data:www-data /var/www/monitor
sudo chmod -R 755 /var/www/monitor

# Log dizini (varsa)
sudo mkdir -p /var/www/monitor/logs
sudo chown -R www-data:www-data /var/www/monitor/logs
sudo chmod -R 755 /var/www/monitor/logs
```

### 7. İlk Admin Kullanıcısı

Veritabanına direkt SQL ile admin kullanıcısı ekleyebilirsiniz:

```sql
-- Önce bir müşteri oluşturun
INSERT INTO customers (name, active) VALUES ('Admin Müşteri', 1);

-- Admin kullanıcısı oluşturun
-- Şifre: admin123 (değiştirmeyi unutmayın!)
INSERT INTO users (username, password_hash, customer_id, full_name, email, active) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'Admin User', 'admin@example.com', 1);
```

Veya `setup_admin.php` script'ini kullanın:

```bash
php setup_admin.php
```

**ÖNEMLİ**: İlk girişten sonra şifreyi mutlaka değiştirin!

## ⚙️ Konfigürasyon

### Session Ayarları

`config.php` dosyasında session ayarlarını özelleştirebilirsiniz:

```php
define('SESSION_LIFETIME', 3600); // 1 saat (saniye cinsinden)
define('SESSION_COOKIE_SECURE', true); // HTTPS için
define('SESSION_COOKIE_HTTPONLY', true); // XSS koruması
define('SESSION_COOKIE_SAMESITE', 'Strict'); // CSRF koruması
```

### Güvenlik Ayarları

```php
define('MAX_LOGIN_ATTEMPTS', 5); // Maksimum giriş denemesi
define('LOGIN_LOCKOUT_TIME', 900); // 15 dakika kilitleme (saniye)
```

### Environment Modu

```php
define('ENVIRONMENT', 'production'); // 'development' veya 'production'
```

Development modunda hata mesajları gösterilir, production modunda loglanır.

## 📖 Kullanım

### Admin Paneli

1. `https://monitor.sirket.com/admin.php` adresine giriş yapın
2. **Yeni Müşteri Ekle** bölümünden müşteri ekleyin
3. Sistem otomatik olarak:
   - Grafana'da folder oluşturur
   - Template dashboard'u kopyalar (belirtilmişse)
   - Veritabanına kaydeder

### Müşteri Dashboard'u

1. Müşteri kullanıcısı ile giriş yapın
2. Dashboard sayfasında müşteriye özel Grafana panelleri görüntülenir
3. Zaman aralığı seçerek grafikleri filtreleyebilirsiniz
4. Otomatik yenileme aktif (varsayılan: 30 saniye)

### Kullanıcı Yönetimi

1. `https://monitor.sirket.com/users.php` adresine giriş yapın
2. Yeni kullanıcı ekleyin, düzenleyin veya silin
3. Kullanıcı şifrelerini değiştirin

## 🔒 Güvenlik

### Uygulanan Güvenlik Önlemleri

1. **SQL Injection Koruması**
   - Tüm SQL sorguları prepared statements kullanır
   - PDO emulated prepares kapalı

2. **XSS Koruması**
   - Tüm kullanıcı girdileri `htmlspecialchars()` ile temizlenir
   - Output encoding uygulanır

3. **CSRF Koruması**
   - Session token'ları kullanılır
   - SameSite cookie ayarları

4. **Brute Force Koruması**
   - Maksimum giriş denemesi sınırı
   - IP bazlı kilitleme (session bazlı)

5. **Session Güvenliği**
   - Secure cookies (HTTPS)
   - HttpOnly cookies
   - Session fixation koruması
   - Session timeout

6. **Grafana Erişim Kontrolü**
   - Grafana direkt dışarı açılmaz
   - Folder bazlı yetkilendirme
   - Viewer rolü ile sınırlı erişim

### Production Güvenlik Checklist

- [ ] `config.php` dosyasını `.gitignore`'a ekleyin
- [ ] Hassas bilgileri environment variable'lara taşıyın
- [ ] HTTPS zorunlu yapın
- [ ] Grafana API Key'i düzenli olarak yenileyin
- [ ] Veritabanı yedeklemesi yapın
- [ ] Log dosyalarını düzenli olarak kontrol edin
- [ ] PHP error reporting'i production modunda kapalı tutun

## 📚 API Dokümantasyonu

### Grafana API Sınıfı

```php
require_once 'includes/grafana_api.php';

$api = new GrafanaAPI(GRAFANA_INTERNAL_URL, GRAFANA_API_KEY, GRAFANA_ORG_ID);

// Folder oluştur
$folder = $api->createFolder('Customer: Yeni Müşteri');

// Dashboard kopyala
$dashboard = $api->copyDashboard('template-uid', $folder['id'], 'Yeni Dashboard');

// Panel iframe URL'i oluştur
$panelUrl = $api->getPanelIframeUrl('dashboard-uid', 1, [
    'from' => 'now-1h',
    'to' => 'now',
    'refresh' => '30s'
]);

// Folder permission ata
$api->grantFolderViewPermissionToViewers($folder['id']);
```

### Zabbix API Sınıfı (Opsiyonel)

```php
require_once 'includes/zabbix_api.php';

$zabbix = new ZabbixAPI(ZABBIX_URL, ZABBIX_USER, ZABBIX_PASS);
$zabbix->login();

// Grafikleri getir
$graphs = $zabbix->getGraphs([123, 456]);

// Grafik görüntüsü al
$image = $zabbix->getGraphImage(123, 800, 200, 3600);
```

## 📁 Dosya Yapısı

```
monitor-portal/
├── config.php              # Ana konfigürasyon
├── index.php               # Login sayfası
├── dashboard.php           # Müşteri dashboard
├── admin.php               # Admin paneli
├── users.php               # Kullanıcı yönetimi
├── logout.php              # Logout
├── database.sql            # Veritabanı şeması
├── grafana.ini             # Grafana konfigürasyon örneği
├── nginx.conf              # NGINX konfigürasyon örneği
├── DEPLOYMENT.md           # Detaylı kurulum rehberi
├── includes/
│   ├── auth.php            # Authentication sınıfı
│   ├── database.php        # Database sınıfı
│   ├── grafana_api.php     # Grafana API wrapper
│   └── zabbix_api.php      # Zabbix API wrapper
├── api/
│   └── zabbix_graph.php    # Zabbix grafik API endpoint
└── assets/
    ├── css/
    │   └── style.css        # Stil dosyaları
    └── js/
        └── dashboard.js     # JavaScript dosyaları
```

## 🤝 Katkıda Bulunma

Katkılarınızı bekliyoruz! Lütfen:

1. Fork edin
2. Feature branch oluşturun (`git checkout -b feature/yeni-ozellik`)
3. Commit edin (`git commit -am 'Yeni özellik eklendi'`)
4. Push edin (`git push origin feature/yeni-ozellik`)
5. Pull Request oluşturun

## 📝 Lisans

Bu proje production kullanımı için hazırlanmıştır. Güvenlik ve performans için düzenli güncellemeler yapın.


## 🔗 İlgili Dokümantasyon

- [DEPLOYMENT.md](DEPLOYMENT.md) - Detaylı kurulum rehberi
- [GRAFANA_KURULUM.md](GRAFANA_KURULUM.md) - Grafana kurulum rehberi
- [GRAFANA_12_KIOSK_FIX.md](GRAFANA_12_KIOSK_FIX.md) - Grafana 12 kiosk modu düzeltmeleri
- [GRAFANA_ANONYMOUS_FIX.md](GRAFANA_ANONYMOUS_FIX.md) - Anonymous kullanıcı ayarları
- [GRAFANA_IFRAME_FIX.md](GRAFANA_IFRAME_FIX.md) - iframe entegrasyon düzeltmeleri

---

**Not**: Bu sistem production ortamında kullanılmak üzere tasarlanmıştır. Güvenlik ve performans için düzenli güncellemeler yapın ve log dosyalarını kontrol edin.


## 🔗 Ekran Görüntüleri

<img width="1739" height="834" alt="musteri-dashboard" src="https://github.com/user-attachments/assets/afeb93a4-3c9f-4219-a8bb-c7b37f3ce002" /> 
<img width="1437" height="866" alt="admin-panel-1" src="https://github.com/user-attachments/assets/5bcbc061-5ce6-4cf0-baf8-c59179d460d5" />
<img width="929" height="632" alt="login" src="https://github.com/user-attachments/assets/037f9fe7-3a04-4ab5-96d8-004b53310f44" />
<img width="928" height="548" alt="admin-panel2" src="https://github.com/user-attachments/assets/5a3f4a86-3960-408d-8e79-ccea93a2d94a" />

