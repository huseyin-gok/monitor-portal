# Grafana Anonymous Auth Açma

## Sorun
Grafana.ini dosyasında `[auth.anonymous]` bölümünde:
```ini
[auth.anonymous]
;enabled = false
```

`;` işareti yorum satırı demektir. Bu satır aktif değil ama varsayılan olarak anonymous auth kapalı.

## Çözüm

Grafana sunucusunda (`192.168.168.17`) Grafana.ini dosyasını düzenleyin:

```bash
sudo nano /etc/grafana/grafana.ini
```

`[auth.anonymous]` bölümünü bulun ve şu şekilde güncelleyin:

```ini
[auth.anonymous]
# iframe embedding için anonymous auth açık
enabled = true
org_role = Viewer
```

**ÖNEMLİ:** 
- `;` işaretini kaldırın (yorum satırı değil, aktif satır olmalı)
- `enabled = true` yapın
- `org_role = Viewer` ekleyin (sadece görüntüleme yetkisi)

## Ayrıca Kontrol Edin

`[security]` bölümünde de şunlar olmalı:

```ini
[security]
allow_embedding = true
allow_anonymous = true
anonymous_role = Viewer
cookie_secure = false
cookie_samesite = lax
```

## Grafana'yı Yeniden Başlatın

```bash
sudo systemctl restart grafana-server
```

## Test

1. Grafana.ini'yi güncelleyin
2. Grafana'yı yeniden başlatın
3. Dashboard'u yenileyin
4. Iframe'de Grafana grafiği görünmeli

## Log Kontrolü

Grafana loglarını kontrol edin:
```bash
sudo journalctl -u grafana-server -f
```

Artık `status=200` ve `userId=0` (anonymous) görmelisiniz, `status=302` (redirect) değil.

