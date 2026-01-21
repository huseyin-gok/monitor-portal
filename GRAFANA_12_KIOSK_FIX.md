# Grafana 12 Kiosk Modu Çözümü

## Sorun
Grafana 12.1.1'de `kiosk=tv` parametresi çalışmıyor, menüler ve butonlar görünmeye devam ediyor.

## Çözümler

### 1. URL Parametresi Kontrolü

Grafana 12'de kiosk modu için URL şu şekilde olmalı:
```
http://192.168.168.17:3000/d/DASHBOARD_UID?orgId=1&kiosk=tv&from=now-1h&to=now&refresh=30s
```

### 2. Grafana.ini Ayarları

Grafana.ini dosyasında şu ayarlar olmalı:
```ini
[security]
allow_embedding = true
allow_anonymous = true
anonymous_role = Viewer
```

### 3. Test

`test_kiosk_url.php` sayfasını açarak farklı kiosk parametrelerini test edin:
```
http://monitor.sirket.com.tr/test_kiosk_url.php
```

### 4. Alternatif: CSS ile UI Gizleme

Eğer URL parametresi çalışmıyorsa, iframe içine CSS enjekte ederek UI elementlerini gizleyebilirsiniz. Bu çözüm `dashboard.php` dosyasına eklenmiştir.

### 5. Grafana 12 Kiosk Modu Bilinen Sorunlar

Grafana 12'de kiosk modu ile ilgili bilinen sorunlar:
- Bazı UI elementleri (refresh, time picker) görünmeye devam edebilir
- Bu durumda CSS ile gizleme gerekebilir

## Test Adımları

1. Dashboard URL'ini kontrol edin - `kiosk=tv` parametresi var mı?
2. Grafana.ini ayarlarını kontrol edin
3. Grafana'yı yeniden başlatın: `sudo systemctl restart grafana-server`
4. Dashboard'u yenileyin
5. Eğer hala çalışmıyorsa, `test_kiosk_url.php` sayfasını kullanarak farklı parametreleri test edin

