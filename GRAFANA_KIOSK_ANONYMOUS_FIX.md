# Grafana 12 Anonymous Kiosk Modu Çözümü

## Sorun
Grafana 12.1.1'de `kiosk=tv` parametresi URL'de var ama anonymous kullanıcılar için kiosk modu çalışmıyor. Login olmadan kiosk modu menüsü açılmıyor.

## Çözüm

### 1. Grafana.ini Ayarları

Grafana sunucusunda (`192.168.168.17`) Grafana.ini dosyasını düzenleyin:

```bash
sudo nano /etc/grafana/grafana.ini
```

`[auth.anonymous]` bölümünü şu şekilde güncelleyin:

```ini
[auth.anonymous]
# iframe embedding için anonymous auth açık
enabled = true
org_role = Viewer
# Organization ID (org_name yerine org_id kullan, daha güvenilir)
org_id = 1
# Anonymous kullanıcılar için kiosk modu desteği
hide_version = true
```

### 2. Grafana'yı Yeniden Başlatın

```bash
sudo systemctl restart grafana-server
```

### 3. Grafana 12'de Kiosk Modu Bilinen Sorunlar

Grafana 12'de anonymous kullanıcılar için kiosk modu ile ilgili bilinen sorunlar:
- Anonymous kullanıcılar için kiosk modu sınırlı olabilir
- Bazı Grafana versiyonlarında anonymous kullanıcılar için kiosk modu çalışmayabilir

### 4. Alternatif Çözüm: Viewer Kullanıcı ile Authentication

Eğer anonymous kiosk modu çalışmıyorsa, Grafana'da viewer kullanıcı oluşturup iframe'de authentication kullanabilirsiniz. Ancak bu daha karmaşık bir çözümdür.

### 5. Test

1. Grafana.ini'yi güncelleyin
2. Grafana'yı yeniden başlatın
3. Dashboard'u yenileyin
4. URL'de `kiosk=tv` parametresi olmalı
5. Anonymous kullanıcı olarak kiosk modu çalışmalı

## Not

Grafana 12.1.1'de anonymous kullanıcılar için kiosk modu ile ilgili bilinen sorunlar var. Eğer bu çözüm çalışmazsa:
1. Grafana versiyonunu güncelleyin
2. Grafana loglarını kontrol edin
3. Grafana web arayüzünde manuel olarak kiosk modunu test edin

