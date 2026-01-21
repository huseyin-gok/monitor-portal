# Monitor Portal - Production Deployment Rehberi

## Mimari Açıklama

Bu sistem, müşterilere özel Grafana dashboard'larını güvenli bir şekilde sunmak için tasarlanmıştır.

### Bileşenler

1. **PHP Portal**: Müşteri authentication ve dashboard gösterimi
2. **Grafana**: Monitoring dashboard'ları (NGINX reverse proxy arkasında)
3. **NGINX**: Reverse proxy ve SSL termination
4. **MySQL**: Kullanıcı ve müşteri veritabanı

### Güvenlik Mimarisi

- Grafana direkt dışarı açılmaz, sadece NGINX üzerinden erişilebilir
- Her müşteri sadece kendi folder'ındaki dashboard'ları görebilir
- Viewer yetkisi ile sınırlı erişim
- HTTPS zorunlu
- Session güvenliği (httponly, secure, samesite)

## Kurulum Adımları

### 1. Veritabanı Kurulumu

```bash
mysql -u root -p < database.sql
```

Veya MySQL'e bağlanıp SQL dosyasını çalıştırın.

### 2. PHP Konfigürasyonu

`config.php` dosyasını düzenleyin:
- Veritabanı bilgileri
- Grafana API Key
- Domain ayarları

**ÖNEMLİ**: Production'da hassas bilgileri environment variable'lara taşıyın:

```php
define('DB_PASS', getenv('DB_PASSWORD'));
define('GRAFANA_API_KEY', getenv('GRAFANA_API_KEY'));
```

### 3. Grafana Konfigürasyonu

#### 3.1. Grafana.ini Ayarları

`grafana.ini` dosyasındaki ayarları Grafana config dosyanıza ekleyin:

```bash
sudo cp grafana.ini /etc/grafana/grafana.ini
# Veya mevcut config'e ayarları ekleyin
sudo systemctl restart grafana-server
```

#### 3.2. Grafana API Key Oluşturma

1. Grafana'ya admin olarak giriş yapın
2. Configuration > API Keys
3. "New API Key" butonuna tıklayın
4. Key Name: "Monitor Portal API"
5. Role: Admin
6. Key'i kopyalayın ve `config.php`'ye ekleyin

#### 3.3. Template Dashboard Hazırlama

1. Grafana'da örnek bir dashboard oluşturun
2. Bu dashboard'u template olarak kullanacaksınız
3. Dashboard UID'yi not edin (admin panelinde kullanılacak)

### 4. NGINX Konfigürasyonu

#### 4.1. Config Dosyasını Kopyalama

```bash
sudo cp nginx.conf /etc/nginx/sites-available/monitor
sudo ln -s /etc/nginx/sites-available/monitor /etc/nginx/sites-enabled/
```

#### 4.2. SSL Sertifikası (Let's Encrypt)

```bash
sudo certbot --nginx -d monitor.sirket.com
```

#### 4.3. NGINX Test ve Restart

```bash
sudo nginx -t
sudo systemctl reload nginx
```

### 5. PHP-FPM Ayarları

PHP 8.3 FPM kullanıldığını varsayıyoruz. Socket path'i kontrol edin:

```bash
php -v  # PHP 8.3 kontrolü
ls -la /var/run/php/php8.3-fpm.sock  # Socket kontrolü
```

Gerekirse `nginx.conf`'daki socket path'ini düzenleyin.

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

### 7. İlk Kullanıcı Oluşturma

Veritabanına direkt SQL ile admin kullanıcısı ekleyebilirsiniz:

```sql
-- Önce bir müşteri oluşturun
INSERT INTO customers (name, active) VALUES ('Admin Müşteri', 1);

-- Admin kullanıcısı oluşturun (şifre: admin123)
INSERT INTO users (username, password_hash, customer_id, full_name, email, active) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'Admin User', 'admin@example.com', 1);
```

**ÖNEMLİ**: İlk girişten sonra şifreyi değiştirin!

## Müşteri Ekleme İşlemi

### Admin Panel Üzerinden

1. `https://monitor.sirket.com/admin.php` adresine giriş yapın
2. "Yeni Müşteri Ekle" bölümünden müşteri ekleyin
3. Sistem otomatik olarak:
   - Grafana'da folder oluşturur
   - Template dashboard'u kopyalar (belirtilmişse)
   - Veritabanına kaydeder

### Manuel Ekleme

Eğer admin paneli kullanmıyorsanız, PHP script ile:

```php
<?php
require_once 'config.php';
require_once 'includes/database.php';
require_once 'includes/grafana_api.php';

$grafanaAPI = new GrafanaAPI(GRAFANA_INTERNAL_URL, GRAFANA_API_KEY, GRAFANA_ORG_ID);
$db = Database::getInstance()->getConnection();

// Folder oluştur
$folder = $grafanaAPI->createFolder('Customer: Yeni Müşteri');
$folderId = $folder['id'];

// Dashboard kopyala (opsiyonel)
$templateUid = 'template-dashboard-uid';
$dashboard = $grafanaAPI->copyDashboard($templateUid, $folderId, 'Yeni Müşteri Dashboard');
$dashboardUid = $dashboard['dashboard']['uid'];

// Veritabanına ekle
$stmt = $db->prepare("INSERT INTO customers (name, grafana_folder_id, grafana_dashboard_uid) VALUES (?, ?, ?)");
$stmt->execute(['Yeni Müşteri', $folderId, $dashboardUid]);
```

## Güvenlik Önlemleri

### 1. Token Sızıntısı Önleme

- Grafana API Key sadece backend'de kullanılır, frontend'e gönderilmez
- Session token'ları güvenli cookie'lerde saklanır
- HTTPS zorunlu (HTTP trafiği otomatik yönlendirilir)

### 2. Grafana Erişim Kontrolü

- Grafana direkt dışarı açılmaz (sadece localhost:3000)
- NGINX reverse proxy üzerinden erişim
- Folder bazlı yetkilendirme
- Viewer rolü ile sınırlı erişim

### 3. PHP Güvenlik

- Prepared statements (SQL injection koruması)
- Password hashing (bcrypt)
- CSRF koruması (session token)
- XSS koruması (htmlspecialchars)
- Brute force koruması (login attempt limiting)

### 4. NGINX Güvenlik Headers

- HSTS (Strict-Transport-Security)
- X-Content-Type-Options
- X-XSS-Protection
- Content-Security-Policy (Grafana iframe için özel)
- X-Frame-Options (SAMEORIGIN - Grafana iframe için)

## Sorun Giderme

### Grafana Dashboard Görünmüyor

1. Grafana loglarını kontrol edin: `sudo journalctl -u grafana-server -f`
2. NGINX loglarını kontrol edin: `sudo tail -f /var/log/nginx/monitor_error.log`
3. Browser console'da hata var mı kontrol edin
4. Grafana API Key geçerli mi kontrol edin

### 403 Forbidden Hatası

- Grafana `allow_embedding = true` ayarı kontrol edin
- NGINX CSP header'larını kontrol edin
- Grafana folder permission'larını kontrol edin

### Session Timeout

`config.php`'de `SESSION_LIFETIME` değerini artırın (saniye cinsinden).

### API Bağlantı Hatası

- Grafana internal URL'inin doğru olduğundan emin olun
- Firewall kurallarını kontrol edin
- Grafana servisinin çalıştığını kontrol edin: `sudo systemctl status grafana-server`

## Bakım

### Log Rotasyon

PHP error logları için logrotate ayarlayın:

```bash
sudo nano /etc/logrotate.d/monitor
```

```
/var/www/monitor/logs/*.log {
    daily
    missingok
    rotate 7
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
}
```

### Yedekleme

Düzenli veritabanı yedeklemesi yapın:

```bash
# Cron job ekleyin
0 2 * * * mysqldump -u monitor -p'PASSWORD' monitor > /backup/monitor_$(date +\%Y\%m\%d).sql
```

## Performans

- PHP OPcache aktif edin
- MySQL query cache kullanın
- NGINX cache ayarlarını optimize edin
- Grafana dashboard'larını optimize edin (gereksiz query'leri azaltın)

