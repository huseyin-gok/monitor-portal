# GitHub'a Yükleme Rehberi

## Adım 1: Git Kurulumu Kontrolü

Git'in yüklü olup olmadığını kontrol edin:

```bash
git --version
```

Eğer yüklü değilse: https://git-scm.com/download/win adresinden indirin ve kurun.

## Adım 2: GitHub'da Repository Oluşturma

1. https://github.com adresine gidin ve giriş yapın
2. Sağ üstteki **"+"** butonuna tıklayın
3. **"New repository"** seçeneğini seçin
4. Repository bilgilerini doldurun:
   - **Repository name**: `monitor-portal` (veya istediğiniz isim)
   - **Description**: "Müşteri İzleme Portalı - Grafana Dashboard Yönetim Sistemi"
   - **Public** veya **Private** seçin
   - ⚠️ **"Initialize this repository with a README"** seçeneğini İŞARETLEMEYİN
5. **"Create repository"** butonuna tıklayın

## Adım 3: Projeyi Git Repository'sine Dönüştürme

PowerShell veya Command Prompt'u açın ve proje klasörüne gidin:

```bash
cd C:\Users\huseyin\Documents\monitor2
```

### 3.1. Git Repository'sini Başlatın

```bash
git init
```

### 3.2. Tüm Dosyaları Ekleyin

```bash
git add .
```

### 3.3. İlk Commit'i Yapın

```bash
git commit -m "İlk commit: Monitor Portal projesi"
```

## Adım 4: GitHub Repository'sine Bağlama

GitHub'da oluşturduğunuz repository sayfasında, **"Quick setup"** bölümünden URL'i kopyalayın.

Örnek:
- HTTPS: `https://github.com/kullanici-adi/monitor-portal.git`
- SSH: `git@github.com:kullanici-adi/monitor-portal.git`

### 4.1. Remote Repository Ekleme

```bash
git remote add origin https://github.com/KULLANICI-ADI/REPOSITORY-ADI.git
```

**ÖNEMLİ**: Yukarıdaki URL'i kendi repository URL'inizle değiştirin!

### 4.2. Remote Repository'yi Kontrol Edin

```bash
git remote -v
```

Bu komut remote repository URL'ini göstermelidir.

## Adım 5: GitHub'a Yükleme (Push)

### 5.1. Ana Branch'i Oluşturun (Eğer yoksa)

```bash
git branch -M main
```

### 5.2. GitHub'a Push Edin

```bash
git push -u origin main
```

GitHub kullanıcı adı ve şifre (veya Personal Access Token) isteyecektir.

**Not**: Eğer 2FA (İki Faktörlü Doğrulama) aktifse, şifre yerine **Personal Access Token** kullanmanız gerekir.

## Adım 6: Personal Access Token Oluşturma (Gerekirse)

Eğer şifre çalışmazsa:

1. GitHub > Settings > Developer settings > Personal access tokens > Tokens (classic)
2. **"Generate new token"** > **"Generate new token (classic)"**
3. Token bilgilerini doldurun:
   - **Note**: "Monitor Portal Push"
   - **Expiration**: İstediğiniz süre
   - **Scopes**: `repo` seçeneğini işaretleyin
4. **"Generate token"** tıklayın
5. Token'ı kopyalayın (bir daha gösterilmeyecek!)
6. Push yaparken şifre yerine bu token'ı kullanın

## Adım 7: Kontrol

GitHub repository sayfanızı yenileyin. Tüm dosyaların yüklendiğini görmelisiniz.

## Sonraki Güncellemeler İçin

Projede değişiklik yaptıktan sonra:

```bash
# Değişiklikleri ekle
git add .

# Commit yap
git commit -m "Değişiklik açıklaması"

# GitHub'a yükle
git push
```

## Önemli Notlar

⚠️ **Güvenlik**: 
- `config.php` dosyası `.gitignore`'da olduğu için GitHub'a yüklenmeyecek
- `config.php.example` dosyası örnek olarak yüklenecek
- Production'da hassas bilgileri environment variable'lara taşıyın

✅ **İyi Pratikler**:
- Her commit'te anlamlı mesajlar yazın
- Düzenli olarak push yapın
- Branch kullanarak yeni özellikler geliştirin

## Sorun Giderme

### "fatal: not a git repository" hatası
```bash
git init
```

### "remote origin already exists" hatası
```bash
git remote remove origin
git remote add origin https://github.com/KULLANICI-ADI/REPOSITORY-ADI.git
```

### "Authentication failed" hatası
- Personal Access Token kullanın (şifre yerine)
- Token'ın `repo` yetkisi olduğundan emin olun

### "Permission denied" hatası
- Repository'nin size ait olduğundan emin olun
- SSH kullanıyorsanız SSH key'lerinizi kontrol edin
