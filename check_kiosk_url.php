<?php
/**
 * Kiosk URL Kontrolü
 * Dashboard URL'ini kontrol eder ve kiosk parametresini gösterir
 */
require_once 'config.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/grafana_api.php';

$auth = new Auth();
$auth->requireLogin();

$isAdmin = ($_SESSION['username'] === 'admin');
if (!$isAdmin) {
    die('Bu sayfaya sadece admin erişebilir!');
}

$db = Database::getInstance()->getConnection();
$customer = $auth->getCustomerInfo();

header('Content-Type: text/html; charset=UTF-8');
echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Kiosk URL Kontrolü</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}";
echo ".box{background:white;padding:20px;margin:10px 0;border-radius:5px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}";
echo "pre{background:#f5f5f5;padding:15px;border-radius:5px;overflow:auto;border:1px solid #e0e0e0;word-break:break-all;}";
echo ".success{color:#4caf50;}.error{color:#f44336;}.warning{color:#ff9800;}</style></head><body>";
echo "<h1>Kiosk URL Kontrolü</h1>";

if (!$customer || empty($customer['grafana_dashboard_uid'])) {
    echo "<div class='box'><p class='error'>Dashboard UID bulunamadı!</p></div>";
    exit;
}

$dashboardUid = $customer['grafana_dashboard_uid'];

if (empty(GRAFANA_API_KEY)) {
    echo "<div class='box'><p class='error'>Grafana API Key yapılandırılmamış!</p></div>";
    exit;
}

$grafanaAPI = new GrafanaAPI(GRAFANA_INTERNAL_URL, GRAFANA_API_KEY, GRAFANA_ORG_ID);

try {
    $url = $grafanaAPI->getDashboardIframeUrl($dashboardUid);
    
    echo "<div class='box'>";
    echo "<h2>Oluşturulan URL</h2>";
    echo "<pre>" . htmlspecialchars($url) . "</pre>";
    
    // URL'i parse et
    $parsedUrl = parse_url($url);
    $queryParams = [];
    if (isset($parsedUrl['query'])) {
        parse_str($parsedUrl['query'], $queryParams);
    }
    
    echo "<h3>URL Parametreleri</h3>";
    echo "<ul>";
    foreach ($queryParams as $key => $value) {
        $highlight = ($key === 'kiosk') ? ' style="background:#e3f2fd;padding:5px;border-radius:3px;"' : '';
        echo "<li{$highlight}><strong>" . htmlspecialchars($key) . ":</strong> " . htmlspecialchars($value) . "</li>";
    }
    echo "</ul>";
    
    // Kiosk parametresi kontrolü
    if (isset($queryParams['kiosk'])) {
        if ($queryParams['kiosk'] === 'tv') {
            echo "<p class='success'>✓ Kiosk parametresi doğru: kiosk=tv</p>";
        } else {
            echo "<p class='warning'>⚠ Kiosk parametresi var ama değeri 'tv' değil: " . htmlspecialchars($queryParams['kiosk']) . "</p>";
        }
    } else {
        echo "<p class='error'>✗ Kiosk parametresi URL'de yok!</p>";
    }
    
    // URL endpoint kontrolü
    if (strpos($url, '/d/') !== false) {
        echo "<p class='success'>✓ Dashboard endpoint doğru: /d/</p>";
    } elseif (strpos($url, '/d-solo/') !== false) {
        echo "<p class='warning'>⚠ d-solo endpoint kullanılıyor (tek panel için)</p>";
    } else {
        echo "<p class='error'>✗ Dashboard endpoint bulunamadı!</p>";
    }
    
    echo "</div>";
    
    echo "<div class='box'>";
    echo "<h2>Test</h2>";
    echo "<p><a href='" . htmlspecialchars($url) . "' target='_blank'>URL'i yeni sekmede aç</a></p>";
    echo "<p><small>Yeni sekmede açıldığında Grafana dashboard'unu kontrol edin:</small></p>";
    echo "<ul>";
    echo "<li>Menüler görünüyor mu? (Görünmemeli)</li>";
    echo "<li>Butonlar görünüyor mu? (Görünmemeli)</li>";
    echo "<li>Sadece grafikler görünüyor mu? (Evet olmalı)</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='box'>";
    echo "<h2>Grafana 12 Kiosk Modu Notları</h2>";
    echo "<ul>";
    echo "<li>Grafana 12.1.1'de kiosk modu için <code>kiosk=tv</code> parametresi kullanılmalı</li>";
    echo "<li>Eğer kiosk modu çalışmıyorsa, Grafana.ini'de <code>allow_embedding = true</code> olduğundan emin olun</li>";
    echo "<li>Grafana'yı yeniden başlatın: <code>sudo systemctl restart grafana-server</code></li>";
    echo "<li>Bazı Grafana versiyonlarında kiosk modu düzgün çalışmayabilir</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='box'><p class='error'>Hata: " . htmlspecialchars($e->getMessage()) . "</p></div>";
}

echo "<p><a href='dashboard.php'>Dashboard'a Dön</a> | <a href='admin.php'>Admin Panel</a></p>";
echo "</body></html>";
?>

