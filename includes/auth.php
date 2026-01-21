<?php
/**
 * Güvenli Kimlik Doğrulama Sınıfı
 * Production-ready authentication with security features
 */
class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->startSession();
    }
    
    private function startSession() {
        $sessionStatus = session_status();
        
        // Session zaten başlatılmışsa
        if ($sessionStatus === PHP_SESSION_ACTIVE) {
            // Eğer session name doğruysa, hiçbir şey yapma
            if (session_name() === SESSION_NAME) {
                return;
            }
            
            // Session name farklıysa ama içinde user_id varsa, mevcut session'ı kullan
            // Bu durumda name'i değiştirmeyiz çünkü cookie zaten set edilmiş ve veri var
            if (isset($_SESSION['user_id'])) {
                // Mevcut session'ı kullan, name'i değiştirme
                // NOT: Bu durumda session name PHPSESSID olabilir ama sorun değil
                return;
            }
            
            // Session name farklıysa ve boşsa veya user_id yoksa, yeniden başlat
            session_write_close();
            $sessionStatus = PHP_SESSION_NONE;
        }
        
        // Session başlatılmamışsa, önce mevcut cookie'yi kontrol et
        if ($sessionStatus === PHP_SESSION_NONE) {
            // Önce default session name (PHPSESSID) ile session var mı kontrol et
            // Eğer cookie'de PHPSESSID varsa ve içinde user_id varsa, onu kullan
            if (isset($_COOKIE['PHPSESSID']) && !isset($_COOKIE[SESSION_NAME])) {
                // PHPSESSID cookie'si var, önce onu kontrol et
                session_name('PHPSESSID');
                session_start();
                
                // Eğer bu session'da user_id varsa, bu session'ı kullan
                if (isset($_SESSION['user_id'])) {
                    // Session name'i değiştirmeden devam et
                    // NOT: Bu durumda session name PHPSESSID olarak kalacak
                    return;
                }
                
                // user_id yoksa, session'ı kapat ve doğru name ile yeniden başlat
                session_write_close();
            }
            
            // Güvenli session ayarları
            ini_set('session.cookie_httponly', SESSION_COOKIE_HTTPONLY ? 1 : 0);
            ini_set('session.cookie_secure', SESSION_COOKIE_SECURE ? 1 : 0);
            ini_set('session.cookie_samesite', SESSION_COOKIE_SAMESITE);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_lifetime', SESSION_LIFETIME);
            ini_set('session.cookie_path', '/'); // Tüm path'lerde çalışsın
            
            session_name(SESSION_NAME);
            session_start();
            
            // Session fixation koruması (sadece yeni session'larda)
            if (!isset($_SESSION['initiated'])) {
                session_regenerate_id(true);
                $_SESSION['initiated'] = true;
            }
        }
    }
    
    /**
     * Giriş denemesi kontrolü (brute force koruması)
     */
    private function checkLoginAttempts($username) {
        $key = 'login_attempts_' . md5($username);
        $attempts = $_SESSION[$key] ?? 0;
        $lastAttempt = $_SESSION[$key . '_time'] ?? 0;
        
        // Kilitleme süresi dolmuş mu?
        if ($lastAttempt > 0 && (time() - $lastAttempt) > LOGIN_LOCKOUT_TIME) {
            unset($_SESSION[$key]);
            unset($_SESSION[$key . '_time']);
            return true;
        }
        
        // Maksimum deneme aşıldı mı?
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Giriş denemesini kaydet
     */
    private function recordLoginAttempt($username, $success) {
        $key = 'login_attempts_' . md5($username);
        
        if ($success) {
            unset($_SESSION[$key]);
            unset($_SESSION[$key . '_time']);
        } else {
            $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;
            $_SESSION[$key . '_time'] = time();
        }
    }
    
    /**
     * Kullanıcı girişi
     */
    public function login($username, $password) {
        // Brute force koruması
        if (!$this->checkLoginAttempts($username)) {
            return false;
        }
        
        // Kullanıcıyı veritabanından al (aktif ve customer bilgisiyle)
        $stmt = $this->db->prepare("
            SELECT u.*, c.name as customer_name, c.grafana_folder_id, c.grafana_dashboard_uid, c.active as customer_active
            FROM users u
            INNER JOIN customers c ON u.customer_id = c.id
            WHERE u.username = ? AND u.active = 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        // Kullanıcı bulunamadı veya pasif
        if (!$user) {
            $this->recordLoginAttempt($username, false);
            return false;
        }
        
        // Müşteri pasif mi?
        if (!$user['customer_active']) {
            $this->recordLoginAttempt($username, false);
            return false;
        }
        
        // Şifre kontrolü (password_hash ile oluşturulmuş hash)
        if (!password_verify($password, $user['password_hash'])) {
            $this->recordLoginAttempt($username, false);
            return false;
        }
        
        // Başarılı giriş - session oluştur
        session_regenerate_id(true); // Session fixation koruması
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['customer_id'] = $user['customer_id'];
        $_SESSION['customer_name'] = $user['customer_name'];
        $_SESSION['grafana_folder_id'] = $user['grafana_folder_id'];
        $_SESSION['grafana_dashboard_uid'] = $user['grafana_dashboard_uid'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Son giriş zamanını güncelle
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        $this->recordLoginAttempt($username, true);
        return true;
    }
    
    /**
     * Çıkış yap
     */
    public function logout() {
        // Session'ı temizle
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            
            // Cookie'yi sil
            if (isset($_COOKIE[session_name()])) {
                $cookieParams = session_get_cookie_params();
                setcookie(
                    session_name(), 
                    '', 
                    time() - 3600, 
                    $cookieParams['path'],
                    $cookieParams['domain'],
                    $cookieParams['secure'],
                    $cookieParams['httponly']
                );
            }
            
            // Session'ı yok et
            session_destroy();
        }
    }
    
    /**
     * Giriş yapılmış mı kontrol et
     */
    public function isLoggedIn() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Session timeout kontrolü
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
            $this->logout();
            return false;
        }
        
        // Son aktiviteyi güncelle
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Giriş zorunlu - değilse yönlendir
     */
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: index.php');
            exit;
        }
    }
    
    /**
     * Müşteri bilgilerini getir
     */
    public function getCustomerInfo() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $customerId = $_SESSION['customer_id'];
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE id = ? AND active = 1");
        $stmt->execute([$customerId]);
        return $stmt->fetch();
    }
    
    /**
     * Kullanıcı config (geriye dönük uyumluluk için)
     */
    public function getUserConfig() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $userId = $_SESSION['user_id'];
        
        // Zabbix config
        $stmt = $this->db->prepare("SELECT * FROM user_zabbix_config WHERE user_id = ?");
        $stmt->execute([$userId]);
        $zabbixConfig = $stmt->fetch();
        
        // Grafana config (customer'dan gelen dashboard_uid öncelikli)
        $grafanaConfig = [
            'dashboard_uid' => $_SESSION['grafana_dashboard_uid'] ?? null,
            'panel_ids' => null,
            'refresh_interval' => 30
        ];
        
        // Eğer user_grafana_config'de kayıt varsa onu kullan
        $stmt = $this->db->prepare("SELECT * FROM user_grafana_config WHERE user_id = ?");
        $stmt->execute([$userId]);
        $userGrafanaConfig = $stmt->fetch();
        if ($userGrafanaConfig) {
            $grafanaConfig = $userGrafanaConfig;
        }
        
        return [
            'zabbix' => $zabbixConfig,
            'grafana' => $grafanaConfig
        ];
    }
}
