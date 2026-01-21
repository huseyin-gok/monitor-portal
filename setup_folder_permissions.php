<?php
/**
 * Mevcut Müşteriler için Folder Permission Ayarlama
 * Bu script mevcut tüm müşterilerin Grafana folder'larına Viewer permission verir
 * Böylece anonymous kullanıcılar sadece kendi folder'larını görebilir
 */
require_once 'config.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/grafana_api.php';

// Sadece admin erişebilir
$auth = new Auth();
$auth->requireLogin();

if ($_SESSION['username'] !== 'admin') {
    die('Bu sayfaya sadece admin erişebilir!');
}

$db = Database::getInstance()->getConnection();

if (empty(GRAFANA_API_KEY)) {
    die('Grafana API Key yapılandırılmamış!');
}

$grafanaAPI = new GrafanaAPI(GRAFANA_INTERNAL_URL, GRAFANA_API_KEY, GRAFANA_ORG_ID);

header('Content-Type: text/html; charset=UTF-8');
echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Folder Permission Ayarlama</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}";
echo ".box{background:white;padding:20px;margin:10px 0;border-radius:5px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}";
echo ".success{color:#4caf50;}.error{color:#f44336;}.warning{color:#ff9800;}";
echo "pre{background:#f5f5f5;padding:15px;border-radius:5px;overflow:auto;border:1px solid #e0e0e0;}</style></head><body>";
echo "<h1>Folder Permission Ayarlama</h1>";

// Müşterileri listele
$stmt = $db->query("SELECT * FROM customers WHERE grafana_folder_id IS NOT NULL ORDER BY name");
$customers = $stmt->fetchAll();

if (empty($customers)) {
    echo "<div class='box'><p class='warning'>Folder ID'si olan müşteri bulunamadı!</p></div>";
    echo "<p><a href='admin.php'>Admin Panel'e Dön</a></p>";
    exit;
}

echo "<div class='box'><p>Toplam <strong>" . count($customers) . "</strong> müşteri bulundu.</p></div>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'setup_permissions') {
    echo "<div class='box'><h2>Permission Ayarlama Sonuçları</h2>";
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($customers as $customer) {
        $folderId = $customer['grafana_folder_id'];
        $customerName = $customer['name'];
        
        echo "<p><strong>" . htmlspecialchars($customerName) . "</strong> (Folder ID: $folderId): ";
        
        try {
            // Önce folder'ın var olup olmadığını kontrol et
            try {
                $folder = $grafanaAPI->getFolderById($folderId);
                $folderUid = $folder['uid'] ?? null;
                echo "<small>(UID: " . htmlspecialchars($folderUid ?? 'yok') . ")</small> ";
            } catch (Exception $e) {
                echo "<small class='warning'>(Folder kontrolü: " . htmlspecialchars($e->getMessage()) . ")</small> ";
            }
            
            // Folder permission'ı ayarla (ID veya UID ile)
            $grafanaAPI->grantFolderViewPermissionToViewers($folderId);
            echo "<span class='success'>✓ Başarılı</span></p>";
            $successCount++;
        } catch (Exception $e) {
            echo "<span class='error'>✗ Hata: " . htmlspecialchars($e->getMessage()) . "</span></p>";
            $errorCount++;
        }
    }
    
    echo "<hr>";
    echo "<p><strong>Özet:</strong></p>";
    echo "<p class='success'>Başarılı: $successCount</p>";
    echo "<p class='error'>Hata: $errorCount</p>";
    echo "</div>";
} else {
    echo "<div class='box'>";
    echo "<h2>Ayarlanacak Müşteriler</h2>";
    echo "<ul>";
    foreach ($customers as $customer) {
        echo "<li><strong>" . htmlspecialchars($customer['name']) . "</strong> (Folder ID: " . $customer['grafana_folder_id'] . ")</li>";
    }
    echo "</ul>";
    echo "<form method='POST'>";
    echo "<input type='hidden' name='action' value='setup_permissions'>";
    echo "<button type='submit' style='padding:10px 20px;background:#1976d2;color:white;border:none;border-radius:5px;cursor:pointer;'>Permission'ları Ayarla</button>";
    echo "</form>";
    echo "</div>";
}

echo "<p><a href='admin.php'>Admin Panel'e Dön</a></p>";
echo "</body></html>";
?>

