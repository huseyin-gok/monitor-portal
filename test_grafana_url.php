<?php
/**
 * Grafana URL Test
 * Farklı endpoint'leri test eder
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
echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Grafana URL Test</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}";
echo ".box{background:white;padding:20px;margin:10px 0;border-radius:5px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}";
echo "pre{background:#f5f5f5;padding:15px;border-radius:5px;overflow:auto;border:1px solid #e0e0e0;word-break:break-all;}";
echo ".success{color:#4caf50;}.error{color:#f44336;}.warning{color:#ff9800;}";
echo "iframe{border:2px solid #ddd;margin-top:10px;width:100%;height:600px;}</style></head><body>";
echo "<h1>Grafana URL Test</h1>";

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

// Farklı URL'leri test et
$tests = [
    [
        'name' => 'Normal /d/ endpoint (kiosk=tv)',
        'url' => GRAFANA_PUBLIC_URL . '/d/' . $dashboardUid . '?orgId=' . GRAFANA_ORG_ID . '&kiosk=tv&from=now-1h&to=now&refresh=30s'
    ],
    [
        'name' => '/d-solo/ endpoint (tüm dashboard)',
        'url' => GRAFANA_PUBLIC_URL . '/d-solo/' . $dashboardUid . '?orgId=' . GRAFANA_ORG_ID . '&from=now-1h&to=now&refresh=30s&__feature.dashboardSceneSolo=true&theme=light&timezone=browser'
    ],
    [
        'name' => 'API ile oluşturulan URL',
        'url' => $grafanaAPI->getDashboardIframeUrl($dashboardUid)
    ]
];

foreach ($tests as $test) {
    echo "<div class='box'>";
    echo "<h2>" . htmlspecialchars($test['name']) . "</h2>";
    echo "<pre>" . htmlspecialchars($test['url']) . "</pre>";
    echo "<p><a href='" . htmlspecialchars($test['url']) . "' target='_blank'>Yeni sekmede aç</a></p>";
    echo "<iframe src='" . htmlspecialchars($test['url']) . "'></iframe>";
    echo "</div>";
}

echo "<p><a href='dashboard.php'>Dashboard'a Dön</a> | <a href='admin.php'>Admin Panel</a></p>";
echo "</body></html>";
?>

