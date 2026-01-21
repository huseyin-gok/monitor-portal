<?php
/**
 * Kiosk URL Test
 * Kiosk modu URL'ini test eder
 */
require_once 'config.php';
require_once 'includes/grafana_api.php';

header('Content-Type: text/html; charset=UTF-8');
echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Kiosk URL Test</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}";
echo ".box{background:white;padding:20px;margin:10px 0;border-radius:5px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}";
echo "pre{background:#f5f5f5;padding:15px;border-radius:5px;overflow:auto;border:1px solid #e0e0e0;}</style></head><body>";
echo "<h1>Kiosk URL Test</h1>";

if (empty(GRAFANA_API_KEY)) {
    echo "<div class='box'><p style='color:red;'>Grafana API Key yapılandırılmamış!</p></div>";
    exit;
}

$grafanaAPI = new GrafanaAPI(GRAFANA_INTERNAL_URL, GRAFANA_API_KEY, GRAFANA_ORG_ID);
$testUid = 'adqync10m8e80d'; // Test dashboard UID

echo "<div class='box'>";
echo "<h2>Test Dashboard UID: $testUid</h2>";

// Farklı kiosk parametreleri test et
$tests = [
    ['kiosk' => 'tv', 'name' => 'TV Mode (tv)'],
    ['kiosk' => '1', 'name' => 'Kiosk Mode (1)'],
    ['kiosk' => true, 'name' => 'Kiosk Mode (true)'],
    ['kiosk' => '', 'name' => 'Kiosk Mode (empty)'],
];

foreach ($tests as $test) {
    $options = ['kiosk' => $test['kiosk']];
    try {
        $url = $grafanaAPI->getDashboardIframeUrl($testUid, $options);
        echo "<h3>" . htmlspecialchars($test['name']) . "</h3>";
        echo "<pre>" . htmlspecialchars($url) . "</pre>";
        
        // URL'de kiosk parametresini kontrol et
        if (strpos($url, 'kiosk=') !== false) {
            preg_match('/kiosk=([^&]*)/', $url, $matches);
            $kioskValue = $matches[1] ?? 'boş';
            echo "<p><strong>Kiosk değeri:</strong> " . htmlspecialchars($kioskValue) . "</p>";
        } else {
            echo "<p style='color:red;'><strong>Kiosk parametresi URL'de yok!</strong></p>";
        }
        echo "<hr>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>Hata: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "</div>";

echo "<p><a href='dashboard.php'>Dashboard'a Dön</a></p>";
echo "</body></html>";
?>

