# Grafana X-Frame-Options Hatası Çözümü

## Sorun
Iframe'de Grafana gösterilirken şu hata alınıyor:
```
Refused to display 'http://192.168.168.17:3000/' in a frame because it set 'X-Frame-Options' to 'deny'.
```

Bu, Grafana'nın iframe embedding'i engellediğini gösteriyor.

## Çözüm

### 1. Grafana.ini'yi Kontrol Edin

Grafana sunucusunda (`192.168.168.17`) Grafana.ini dosyasını kontrol edin:

```bash
sudo nano /etc/grafana/grafana.ini
```

`[security]` bölümünde şunlar olmalı:

```ini
[security]
allow_embedding = true
allow_anonymous = true
anonymous_role = Viewer
cookie_secure = false
cookie_samesite = lax
```

### 2. Grafana'yı Yeniden Başlatın

**ÖNEMLİ:** Grafana.ini değişikliklerinin etkili olması için Grafana'yı mutlaka yeniden başlatın:

```bash
sudo systemctl restart grafana-server
```

### 3. Grafana Versiyonunu Kontrol Edin

Bazı Grafana versiyonlarında `allow_embedding = true` yeterli olmayabilir. Grafana versiyonunu kontrol edin:

```bash
grafana-server -v
```

### 4. Grafana Loglarını Kontrol Edin

Grafana loglarını kontrol edin ve `allow_embedding` ayarının yüklendiğini doğrulayın:

```bash
sudo journalctl -u grafana-server -f | grep -i embedding
```

### 5. Grafana Web Arayüzünden Kontrol

Grafana web arayüzünde:
1. Configuration > Settings > Security
2. "Allow embedding" seçeneğinin açık olduğunu kontrol edin

### 6. Alternatif: Grafana Proxy Header'ları

Eğer hala çalışmıyorsa, Grafana'nın önünde bir reverse proxy varsa (NGINX), proxy header'larını kontrol edin. Ama siz NGINX kullanmıyorsunuz, bu yüzden bu adım gerekli değil.

## Test

1. Grafana.ini'yi kontrol edin (`allow_embedding = true`)
2. Grafana'yı yeniden başlatın
3. Dashboard'u yenileyin
4. Browser console'da hata olmamalı
5. Iframe'de Grafana grafiği görünmeli

## Not

- `allow_embedding = true` ayarı Grafana'yı yeniden başlattıktan sonra etkili olur
- Eğer hala X-Frame-Options hatası alıyorsanız, Grafana versiyonunu güncelleyin veya Grafana loglarını kontrol edin
- Grafana'nın bazı eski versiyonlarında bu özellik düzgün çalışmayabilir

