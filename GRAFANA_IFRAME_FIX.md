# Grafana Iframe Embedding Sorunu Çözümü

## Sorun
Grafana loglarında `status=302` ve `userId=0` görünüyor. Bu, iframe'de kullanıcının giriş yapmadığını ve login sayfasına yönlendirildiğini gösteriyor.

## Çözüm 1: Anonymous Auth Aç (Önerilen)

Grafana sunucusunda (`192.168.168.17`) Grafana config dosyasını düzenleyin:

```bash
sudo nano /etc/grafana/grafana.ini
```

Şu ayarları bulun ve güncelleyin:

```ini
[security]
# iframe embedding için gerekli
allow_embedding = true

# Anonymous auth - iframe için açık olmalı
allow_anonymous = true

# Anonymous kullanıcı rolü
anonymous_role = Viewer

# Cookie güvenliği
cookie_secure = false  # HTTP kullanıyorsanız
cookie_samesite = lax  # iframe için lax olmalı (strict çalışmaz)

[server]
domain = monitor.sirket.com.tr
root_url = http://192.168.168.17:3000/
serve_from_sub_path = false  # NGINX yoksa false
```

**ÖNEMLİ:** Grafana'yı yeniden başlatın:
```bash
sudo systemctl restart grafana-server
```

## Çözüm 2: Folder Permission ile Güvenlik

Anonymous auth açtıktan sonra, folder bazlı yetkilendirme ile güvenliği sağlayın:

1. Grafana'da folder'a gidin
2. **Permissions** sekmesine tıklayın
3. **Add Permission** butonuna tıklayın
4. **Role: Viewer** seçin
5. Bu şekilde sadece Viewer rolündeki kullanıcılar (anonymous dahil) folder'ı görebilir

## Çözüm 3: Grafana API ile Permission Ata (Otomatik)

Admin panelinden müşteri oluştururken, folder permission'ı otomatik atanabilir (Grafana API Key gerekli).

## Test

1. Grafana.ini'yi güncelleyin
2. Grafana'yı yeniden başlatın
3. Dashboard'u yenileyin
4. Iframe'de Grafana grafiği görünmeli

## Güvenlik Notu

Anonymous auth açık olsa bile, folder bazlı yetkilendirme ile her müşteri sadece kendi folder'ını görebilir. Bu yeterli güvenlik sağlar.

