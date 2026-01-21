<?php
require_once '../config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';
require_once '../includes/zabbix_api.php';

$auth = new Auth();
$auth->requireLogin();

if (!$_SESSION['zabbix_enabled']) {
    http_response_code(403);
    die('Zabbix erişimi kapalı');
}

$graphId = $_GET['graphid'] ?? null;
$width = $_GET['width'] ?? 800;
$height = $_GET['height'] ?? 200;
$period = $_GET['period'] ?? 3600; // 1 saat

if (!$graphId) {
    http_response_code(400);
    die('Graph ID gerekli');
}

try {
    $zabbixAPI = new ZabbixAPI(ZABBIX_URL, ZABBIX_USER, ZABBIX_PASS);
    $imageData = $zabbixAPI->getGraphImage($graphId, $width, $height, $period);
    
    if ($imageData) {
        header('Content-Type: image/png');
        echo base64_decode($imageData);
    } else {
        http_response_code(404);
        die('Grafik bulunamadı');
    }
} catch (Exception $e) {
    http_response_code(500);
    die('Hata: ' . $e->getMessage());
}
