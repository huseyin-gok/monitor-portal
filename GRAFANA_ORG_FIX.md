# Grafana Organization Hatası Çözümü

## Sorun
Grafana loglarında şu hata görünüyor:
```
failed to get org by name: Main Org.
[org.notFound] failed to get org by name: Main Org.
```

Grafana anonymous auth açılmış ama "Main Org." adında bir organization bulamıyor.

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
# Organization ID (org_name yerine org_id kullan, daha güvenilir)
org_id = 1
```

**ÖNEMLİ:** 
- `org_name = "Main Org."` yerine `org_id = 1` kullanın
- Organization ID'yi Grafana web arayüzünden veya API'den öğrenebilirsiniz
- Config.php'de `GRAFANA_ORG_ID = 1` varsa, aynı ID'yi kullanın

## Organization ID'yi Bulma

Grafana API ile organization ID'yi bulabilirsiniz:

```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
     http://192.168.168.17:3000/api/orgs
```

Veya Grafana web arayüzünde:
1. Settings > Preferences
2. Organization ID görünecektir

## Grafana'yı Yeniden Başlatın

```bash
sudo systemctl restart grafana-server
```

## Test

1. Grafana.ini'yi güncelleyin (`org_id = 1` ekleyin)
2. Grafana'yı yeniden başlatın
3. Dashboard'u yenileyin
4. Loglarda artık `status=200` görmelisiniz, `status=302` değil

## Log Kontrolü

Grafana loglarını kontrol edin:
```bash
sudo journalctl -u grafana-server -f
```

Artık şu hatayı görmemelisiniz:
- `failed to get org by name: Main Org.`
- `[org.notFound]`

Bunun yerine:
- `status=200` (başarılı)
- `userId=0` (anonymous user)
- `orgId=1` (doğru organization)

