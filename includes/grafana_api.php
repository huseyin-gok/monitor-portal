<?php
/**
 * Grafana API Sınıfı
 * Production-ready Grafana API wrapper with folder, dashboard, and permission management
 */
class GrafanaAPI {
    private $url;
    private $apiKey;
    private $orgId;
    
    public function __construct($url, $apiKey, $orgId = 1) {
        $this->url = rtrim($url, '/');
        $this->apiKey = $apiKey;
        $this->orgId = $orgId;
        
        // API key kontrolü (sadece uyarı, hata vermez)
        if (empty($this->apiKey)) {
            error_log("UYARI: GrafanaAPI oluşturuldu ancak API Key boş!");
        }
    }
    
    /**
     * Generic API request method
     */
    private function request($method, $endpoint, $data = null) {
        if (empty($this->apiKey)) {
            throw new Exception("Grafana API Key yapılandırılmamış! config.php dosyasında GRAFANA_API_KEY değerini ayarlayın.");
        }
        
        $ch = curl_init();
        $url = $this->url . '/api' . $endpoint;
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];
        
        // Organization ID header (bazı endpoint'ler için gerekli)
        if (strpos($endpoint, '/folders') !== false || strpos($endpoint, '/dashboards') !== false) {
            $headers[] = 'X-Grafana-Org-Id: ' . $this->orgId;
        }
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT' && $data) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } elseif ($method === 'GET' && $data) {
            $url .= '?' . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Grafana API curl hatası: $error");
        }
        
        if ($httpCode >= 400) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['message'] ?? $response;
            throw new Exception("Grafana API hatası: HTTP $httpCode - $errorMsg");
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Dashboard iframe URL'i oluştur (kiosk modu ile)
     * Not: Bu metod API çağrısı yapmaz, sadece URL oluşturur
     */
    public function getDashboardIframeUrl($dashboardUid, $options = []) {
        if (empty($dashboardUid)) {
            throw new Exception("Dashboard UID boş olamaz!");
        }
        
        $params = [
            'orgId' => $this->orgId,
            'from' => $options['from'] ?? 'now-1h',
            'to' => $options['to'] ?? 'now',
            'refresh' => $options['refresh'] ?? '30s'
        ];
        
        // Kiosk modu: Grafana 12+ için TV mode
        // Grafana 12'de anonymous kullanıcılar için kiosk modu çalışması için özel parametreler gerekebilir
        if (!isset($options['kiosk']) || $options['kiosk'] !== false) {
            // Grafana 12+ için kiosk=tv (TV mode - tüm UI gizlenir)
            $params['kiosk'] = 'tv';
        }
        
        if (isset($options['panelIds']) && !empty($options['panelIds'])) {
            $params['var-panelId'] = implode(',', $options['panelIds']);
        }
        
        // Public URL kullan (NGINX reverse proxy üzerinden)
        $publicUrl = defined('GRAFANA_PUBLIC_URL') ? GRAFANA_PUBLIC_URL : $this->url;
        
        // URL oluştur - trailing slash kontrolü
        $publicUrl = rtrim($publicUrl, '/');
        
        // Grafana 12+ için kiosk modu: /d/ endpoint'i kullan (tüm dashboard için)
        // /d-solo/ endpoint'i sadece tek panel için çalışır, tüm dashboard için /d/ kullanılmalı
        if (substr($publicUrl, -8) === '/grafana') {
            $url = $publicUrl . '/d/' . $dashboardUid;
        } else {
            $url = $publicUrl . '/d/' . $dashboardUid;
        }
        
        // Kiosk modu parametresi ekle
        if (!isset($options['kiosk']) || $options['kiosk'] !== false) {
            // Grafana 12'de kiosk=tv parametresi kullanılır
            $params['kiosk'] = 'tv';
        }
        
        // Query parametrelerini ekle
        $queryString = http_build_query($params);
        
        // Kiosk parametresinin eklenip eklenmediğini kontrol et
        if (strpos($queryString, 'kiosk=') === false && isset($params['kiosk'])) {
            $queryString .= ($queryString ? '&' : '') . 'kiosk=tv';
        }
        
        $url .= '?' . $queryString;
        
        return $url;
    }
    
    /**
     * Folder oluştur
     */
    public function createFolder($title, $uid = null) {
        if (!$uid) {
            $uid = 'folder-' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $title));
        }
        
        $data = [
            'uid' => $uid,
            'title' => $title
        ];
        
        try {
            $result = $this->request('POST', '/folders', $data);
            return $result;
        } catch (Exception $e) {
            // Folder zaten varsa, mevcut folder'ı getir
            if (strpos($e->getMessage(), '409') !== false || strpos($e->getMessage(), 'already exists') !== false) {
                return $this->getFolderByUid($uid);
            }
            throw $e;
        }
    }
    
    /**
     * Folder'ı UID ile getir
     */
    public function getFolderByUid($uid) {
        return $this->request('GET', "/folders/$uid");
    }
    
    /**
     * Tüm folder'ları listele
     */
    public function getFolders() {
        return $this->request('GET', '/folders');
    }
    
    /**
     * Dashboard'u UID ile getir
     */
    public function getDashboard($uid) {
        return $this->request('GET', "/dashboards/uid/$uid");
    }
    
    /**
     * Dashboard panel'lerini getir
     */
    public function getDashboardPanels($uid) {
        $dashboard = $this->getDashboard($uid);
        return $dashboard['dashboard']['panels'] ?? [];
    }
    
    /**
     * Panel iframe URL'i oluştur (Grafana share embed kodu formatında)
     * /d-solo/ endpoint'i kullanarak tek panel gösterir
     */
    public function getPanelIframeUrl($dashboardUid, $panelId, $options = []) {
        if (empty($dashboardUid) || empty($panelId)) {
            throw new Exception("Dashboard UID ve Panel ID boş olamaz!");
        }
        
        // from ve to parametreleri timestamp (milliseconds) veya string ('now-1h') olabilir
        $from = $options['from'] ?? 'now-1h';
        $to = $options['to'] ?? 'now';
        
        // Eğer timestamp ise (sayı), direkt kullan; değilse string olarak gönder
        $params = [
            'orgId' => $this->orgId,
            'from' => is_numeric($from) ? $from : $from,
            'to' => is_numeric($to) ? $to : $to,
            'refresh' => $options['refresh'] ?? '30s',
            'timezone' => $options['timezone'] ?? 'browser',
            'panelId' => $panelId,
            '__feature.dashboardSceneSolo' => 'true'
        ];
        
        // Time picker'ı göstermek için kiosk parametresini kaldırıyoruz
        // Kullanıcılar tarih/saat seçebilecek
        if (isset($options['showTimePicker']) && $options['showTimePicker'] === false) {
            $params['fullscreen'] = 'true';
            $params['kiosk'] = ''; // Kiosk modu (time picker gizli)
        }
        // showTimePicker true veya belirtilmemişse, time picker gösterilir
        
        // Public URL kullan
        $publicUrl = defined('GRAFANA_PUBLIC_URL') ? GRAFANA_PUBLIC_URL : $this->url;
        $publicUrl = rtrim($publicUrl, '/');
        
        // /d-solo/ endpoint'i kullan (tek panel için)
        if (substr($publicUrl, -8) === '/grafana') {
            $url = $publicUrl . '/d-solo/' . $dashboardUid;
        } else {
            $url = $publicUrl . '/d-solo/' . $dashboardUid;
        }
        
        // Query parametrelerini ekle
        $queryString = http_build_query($params);
        $url .= '?' . $queryString;
        
        return $url;
    }
    
    /**
     * Panel görüntüsü al (render API)
     */
    public function getPanelImage($dashboardUid, $panelId, $width = 800, $height = 400, $from = 'now-1h', $to = 'now') {
        // Grafana render API kullanarak panel görüntüsü al
        $params = [
            'dashboard' => $dashboardUid,
            'panelId' => $panelId,
            'width' => $width,
            'height' => $height,
            'from' => $from,
            'to' => $to
        ];
        
        $url = $this->url . '/render/d-solo/' . $dashboardUid . '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey
        ]);
        
        $image = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return base64_encode($image);
        }
        
        return null;
    }
    
    /**
     * Dashboard'ları listele
     */
    public function getDashboards($folderId = null) {
        $params = ['type' => 'dash-db'];
        if ($folderId) {
            $params['folderIds'] = $folderId;
        }
        return $this->request('GET', '/search', $params);
    }
    
    /**
     * Dashboard'u kopyala (yeni folder'a)
     */
    public function copyDashboard($sourceUid, $targetFolderId, $newTitle, $newUid = null) {
        // Kaynak dashboard'u getir
        $sourceDashboard = $this->getDashboard($sourceUid);
        $dashboard = $sourceDashboard['dashboard'];
        
        // Yeni UID oluştur (Grafana maksimum 40 karakter kabul eder)
        if (!$newUid) {
            // Title'dan UID oluştur (maksimum 40 karakter)
            $baseUid = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $newTitle));
            $baseUid = preg_replace('/-+/', '-', $baseUid); // Çoklu tire'leri tek tire yap
            $baseUid = trim($baseUid, '-'); // Başta ve sonda tire varsa temizle
            
            // Eğer çok uzunsa kısalt
            if (strlen($baseUid) > 30) {
                $baseUid = substr($baseUid, 0, 30);
                $baseUid = rtrim($baseUid, '-');
            }
            
            // Kısa bir hash ekle (6 karakter) - benzersizlik için
            $hash = substr(md5($newTitle . time()), 0, 6);
            $newUid = $baseUid . '-' . $hash;
            
            // Yine de 40 karakteri aşmamalı
            if (strlen($newUid) > 40) {
                $newUid = substr($newUid, 0, 40);
                $newUid = rtrim($newUid, '-');
            }
        }
        
        // UID uzunluk kontrolü (son kontrol)
        if (strlen($newUid) > 40) {
            throw new Exception("Dashboard UID çok uzun! Maksimum 40 karakter olmalı. UID: $newUid (" . strlen($newUid) . " karakter)");
        }
        
        // Dashboard metadata'sını temizle ve yeni değerlerle doldur
        $dashboard['id'] = null;
        $dashboard['uid'] = $newUid;
        $dashboard['title'] = $newTitle;
        $dashboard['version'] = 0;
        unset($dashboard['created']);
        unset($dashboard['createdBy']);
        unset($dashboard['updated']);
        unset($dashboard['updatedBy']);
        
        // Folder ID'yi ayarla
        $data = [
            'dashboard' => $dashboard,
            'folderId' => $targetFolderId,
            'overwrite' => false
        ];
        
        $result = $this->request('POST', '/dashboards/db', $data);
        return $result;
    }
    
    /**
     * Folder'ı ID ile getir
     */
    public function getFolderById($folderId) {
        // Grafana API'de folder'ı ID ile getirmek için /folders/id/:id endpoint'i kullanılır
        return $this->request('GET', "/folders/id/$folderId");
    }
    
    /**
     * Folder permission'ları ayarla
     * NOT: Grafana API folder ID veya UID kabul eder
     */
    public function setFolderPermissions($folderIdOrUid, $permissions) {
        // permissions formatı:
        // [
        //   {'role': 'Viewer', 'permission': 1},  // 1=View, 2=Edit, 4=Admin
        //   {'teamId': 1, 'permission': 1},
        //   {'userId': 1, 'permission': 1}
        // ]
        // Önce folder'ın var olup olmadığını kontrol et
        try {
            // Folder ID ise UID'ye çevir
            if (is_numeric($folderIdOrUid)) {
                $folder = $this->getFolderById($folderIdOrUid);
                $folderIdOrUid = $folder['uid'] ?? $folderIdOrUid;
            }
        } catch (Exception $e) {
            // Folder bulunamadı, ID olarak dene
        }
        
        return $this->request('POST', "/folders/$folderIdOrUid/permissions", ['items' => $permissions]);
    }
    
    /**
     * Viewer kullanıcı oluştur veya getir
     */
    public function getOrCreateViewerUser($username, $email, $password) {
        // Önce kullanıcıyı ara
        try {
            $users = $this->request('GET', '/org/users');
            foreach ($users as $user) {
                if ($user['login'] === $username || $user['email'] === $email) {
                    return $user;
                }
            }
        } catch (Exception $e) {
            // Kullanıcı bulunamadı, oluştur
        }
        
        // Kullanıcı yoksa oluştur
        $data = [
            'name' => $username,
            'email' => $email,
            'login' => $username,
            'password' => $password
        ];
        
        $result = $this->request('POST', '/admin/users', $data);
        
        // Viewer rolü ata
        $this->request('PATCH', '/org/users/' . $result['id'], [
            'role' => 'Viewer'
        ]);
        
        return $result;
    }
    
    /**
     * Kullanıcıya folder permission ver
     */
    public function grantFolderViewPermission($folderId, $userId) {
        $permissions = [
            ['userId' => $userId, 'permission' => 1] // 1 = View
        ];
        return $this->setFolderPermissions($folderId, $permissions);
    }
    
    /**
     * Anonymous kullanıcılar için folder'a Viewer permission ver
     * Bu sayede anonymous kullanıcılar sadece bu folder'ı görebilir
     */
    public function grantFolderViewPermissionToViewers($folderId) {
        $permissions = [
            ['role' => 'Viewer', 'permission' => 1] // 1 = View, Viewer rolü = anonymous kullanıcılar
        ];
        return $this->setFolderPermissions($folderId, $permissions);
    }
    
    /**
     * Dashboard snapshot URL (geriye dönük uyumluluk)
     */
    public function getDashboardSnapshot($dashboardUid, $panelIds = [], $width = 800, $height = 400) {
        return $this->getDashboardIframeUrl($dashboardUid, [
            'panelIds' => $panelIds
        ]);
    }
}
