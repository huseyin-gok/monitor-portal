# Grafana Kurulum ve Kullanım Rehberi

## 1. Grafana API Key Kontrolü

`config.php` dosyasında `GRAFANA_API_KEY` değerinin doldurulduğundan emin olun:

```php
define('GRAFANA_API_KEY', 'glsa_xxxxxxxxxxxxx'); // Grafana API Key'iniz
```

## 2. Grafana'da Template Dashboard Oluşturma

### Adım 1: Grafana'ya Giriş Yapın
```
http://localhost:3000
```
veya
```
https://monitor.sirket.com.tr/grafana
```

### Adım 2: Örnek Dashboard Oluşturun
1. Grafana'da **"+"** butonuna tıklayın
2. **"Dashboard"** seçin
3. Birkaç panel ekleyin (örnek: CPU, Memory, Network grafikleri)
4. Dashboard'u kaydedin
5. Dashboard ayarlarına gidin (⚙️ ikonu)
6. **Dashboard UID**'yi not edin (örnek: `my-dashboard-uid`)

### Adım 3: Dashboard'u Template Olarak Kullanın
- Bu dashboard'u template olarak kullanacaksınız
- Her müşteri için bu dashboard kopyalanacak

## 3. Müşteri Oluşturma (Admin Panel)

### Adım 1: Admin Paneline Giriş
```
http://monitor.sirket.com.tr/admin.php
```
veya Dashboard'dan **"Admin Panel"** butonuna tıklayın

### Adım 2: Yeni Müşteri Ekle
1. **"Yeni Müşteri Ekle"** bölümüne gidin
2. **Müşteri Adı** girin (örnek: "ABC Şirketi")
3. **Template Dashboard UID** girin (yukarıda not ettiğiniz UID)
4. **"Müşteri Oluştur"** butonuna tıklayın

### Ne Olur?
- ✅ Grafana'da folder oluşturulur: `Customer: ABC Şirketi`
- ✅ Template dashboard kopyalanır ve folder'a eklenir
- ✅ Veritabanına müşteri kaydedilir
- ✅ Folder ID ve Dashboard UID kaydedilir

## 4. Kullanıcı Oluşturma

### Adım 1: Kullanıcı Yönetim Sayfasına Gidin
```
http://monitor.sirket.com.tr/users.php
```
veya Dashboard'dan **"Kullanıcı Yönetimi"** butonuna tıklayın

### Adım 2: Yeni Kullanıcı Ekle
1. **"Yeni Kullanıcı Ekle"** bölümüne gidin
2. Formu doldurun:
   - **Kullanıcı Adı**: `musteri1`
   - **Müşteri**: Oluşturduğunuz müşteriyi seçin
   - **Şifre**: Güvenli bir şifre (min 6 karakter)
   - **Şifre Tekrar**: Aynı şifreyi tekrar girin
   - **Ad Soyad**: (Opsiyonel)
   - **E-posta**: (Opsiyonel)
3. **"Kullanıcı Oluştur"** butonuna tıklayın

## 5. Test Etme

### Adım 1: Yeni Kullanıcı ile Giriş Yapın
1. Çıkış yapın (eğer admin ile giriş yaptıysanız)
2. Yeni oluşturduğunuz kullanıcı ile giriş yapın:
   ```
   http://monitor.sirket.com.tr/index.php
   ```

### Adım 2: Dashboard'u Kontrol Edin
- Kullanıcı sadece kendi müşterisine ait dashboard'u görmeli
- Grafana dashboard iframe içinde görünmeli
- Kiosk modu aktif olmalı (tüm UI gizli)

## 6. Sorun Giderme

### Grafana Folder Oluşturulmadı
- Grafana API Key'in doğru olduğundan emin olun
- Grafana'nın çalıştığından emin olun (`http://localhost:3000`)
- Grafana loglarını kontrol edin

### Dashboard Görünmüyor
- Müşterinin `grafana_dashboard_uid` değerinin dolu olduğundan emin olun
- Grafana'da dashboard'un folder içinde olduğunu kontrol edin
- NGINX reverse proxy ayarlarını kontrol edin

### Kullanıcı Dashboard Göremiyor
- Kullanıcının `customer_id` değerinin doğru olduğundan emin olun
- Müşterinin `active = 1` olduğundan emin olun
- Kullanıcının `active = 1` olduğundan emin olun

## 7. İleri Seviye: Folder Permission Ayarlama

Eğer Grafana'da folder bazlı yetkilendirme yapmak istiyorsanız:

1. Grafana'da folder'a gidin
2. **Permissions** sekmesine tıklayın
3. Viewer rolü ekleyin veya özel kullanıcı ekleyin

Veya admin panelinden **"Set Folder Permission"** formunu kullanın (Grafana API Key gerekli).

## 8. Örnek Senaryo

1. **Template Dashboard Oluştur**: Grafana'da "Template Dashboard" adında bir dashboard oluştur (UID: `template-dashboard`)
2. **Müşteri Oluştur**: Admin panelinden "ABC Şirketi" müşterisini oluştur, template UID'yi gir
3. **Kullanıcı Oluştur**: "abc_user" kullanıcısını oluştur, "ABC Şirketi" müşterisine ata
4. **Test Et**: "abc_user" ile giriş yap, sadece ABC Şirketi dashboard'unu gör

## Notlar

- Her müşteri için ayrı Grafana folder'ı oluşturulur
- Her müşteri için template dashboard kopyalanır
- Kullanıcılar sadece kendi müşterilerinin dashboard'unu görür
- Grafana API Key Admin rolü ile oluşturulmalıdır

