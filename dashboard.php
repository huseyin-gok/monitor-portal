<?php
/**
 * Müşteri Dashboard Sayfası
 * Her müşteri sadece kendi dashboard'unu görür
 */
try {
require_once 'config.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';
    
    // Zabbix ve Grafana API'leri opsiyonel olarak yükle
    $zabbixLoaded = false;
    $grafanaLoaded = false;
    
    try {
require_once 'includes/zabbix_api.php';
        $zabbixLoaded = true;
    } catch (Throwable $e) {
        error_log("Zabbix API yükleme hatası: " . $e->getMessage());
    }
    
    try {
require_once 'includes/grafana_api.php';
        $grafanaLoaded = true;
    } catch (Throwable $e) {
        error_log("Grafana API yükleme hatası: " . $e->getMessage());
    }

$auth = new Auth();
$auth->requireLogin();

    // Müşteri bilgilerini al
    $customer = $auth->getCustomerInfo();
    if (!$customer) {
        throw new Exception('Müşteri bilgisi bulunamadı! Kullanıcı ID: ' . ($_SESSION['user_id'] ?? 'yok') . ', Customer ID: ' . ($_SESSION['customer_id'] ?? 'yok'));
    }
} catch (Exception $e) {
    error_log("Dashboard hatası: " . $e->getMessage() . " - " . $e->getFile() . ":" . $e->getLine());
    die('Bir hata oluştu. Lütfen sistem yöneticisine başvurun.');
} catch (Error $e) {
    error_log("Dashboard PHP hatası: " . $e->getMessage() . " - " . $e->getFile() . ":" . $e->getLine());
    die('Bir hata oluştu. Lütfen sistem yöneticisine başvurun.');
}

$refreshInterval = 30; // Varsayılan refresh interval

// Grafana dashboard URL'i oluştur (customer bazlı)
$grafanaDashboardUrl = null;
$grafanaError = null;

// Grafana dashboard panelleri (customer bazlı)
// Eğer dashboard_uid yoksa, sadece bilgi mesajı göster
$dashboardUid = trim($customer['grafana_dashboard_uid'] ?? '');
$grafanaPanels = [];
$grafanaDashboardUrl = null; // Eski kod uyumluluğu için

if (!empty($dashboardUid)) {
    // Dashboard UID validasyonu - "dashboard.php" gibi yanlış değerleri filtrele
    if (strpos($dashboardUid, '.php') !== false || strpos($dashboardUid, '/') !== false) {
        $grafanaError = 'Geçersiz Dashboard UID! UID sadece alfanumerik karakterler ve tire içermelidir. Mevcut değer: ' . htmlspecialchars($dashboardUid);
        error_log("Geçersiz dashboard UID: $dashboardUid");
    } elseif (!$grafanaLoaded) {
        $grafanaError = 'Grafana API sınıfı yüklenemedi. Lütfen sistem yöneticisine başvurun.';
    } else {
        try {
            // API Key kontrolü
            if (empty(GRAFANA_API_KEY)) {
                $grafanaError = 'Grafana API Key yapılandırılmamış. config.php dosyasında GRAFANA_API_KEY değerini ayarlayın.';
            } else {
                $grafanaAPI = new GrafanaAPI(GRAFANA_INTERNAL_URL, GRAFANA_API_KEY, GRAFANA_ORG_ID);
                
                // Dashboard'daki panelleri al
                try {
                    $panels = $grafanaAPI->getDashboardPanels($dashboardUid);
                    
                    // Her panel için iframe URL'i oluştur
                    $grafanaPanels = [];
                    foreach ($panels as $panel) {
                        // Sadece görünür panelleri al (hidden panelleri atla)
                        if (isset($panel['gridPos']) && !empty($panel['id'])) {
                            $panelId = $panel['id'];
                            $panelTitle = $panel['title'] ?? 'Panel ' . $panelId;
                            
                            // Base URL oluştur (timestamp'ler JavaScript ile güncellenecek)
                            $baseUrl = $grafanaAPI->getPanelIframeUrl($dashboardUid, $panelId, [
                                'from' => time() * 1000 - (60 * 60 * 1000), // 1 saat önce (timestamp)
                                'to' => time() * 1000, // Şimdi (timestamp)
                                'refresh' => '30s',
                                'showTimePicker' => false // Time picker gizli (sayfa üstünde kontrol var)
                            ]);
                            
                            $grafanaPanels[] = [
                                'id' => $panelId,
                                'title' => $panelTitle,
                                'url' => $baseUrl
                            ];
                        }
                    }
                    
                    // User config'den refresh interval al (varsa)
                    $userConfig = $auth->getUserConfig();
                    if ($userConfig && isset($userConfig['grafana']['refresh_interval'])) {
                        $refreshInterval = $userConfig['grafana']['refresh_interval'];
                    }
                } catch (Exception $e) {
                    $grafanaError = 'Grafana panel hatası: ' . $e->getMessage();
                    error_log("Grafana panel hatası: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            $grafanaError = 'Grafana bağlantı hatası: ' . $e->getMessage();
            error_log("Grafana dashboard hatası: " . $e->getMessage());
        } catch (Error $e) {
            $grafanaError = 'Grafana PHP hatası: ' . $e->getMessage();
            error_log("Grafana dashboard PHP hatası: " . $e->getMessage());
        }
    }
} else {
    $grafanaError = 'Grafana dashboard yapılandırılmamış. Admin panelinden müşteriye dashboard atayın.';
}

// Zabbix grafikleri (opsiyonel, geriye dönük uyumluluk)
$zabbixGraphs = [];
$zabbixError = null;
if ($zabbixLoaded) {
    $userConfig = $auth->getUserConfig();
    if ($userConfig && isset($userConfig['zabbix']) && $userConfig['zabbix']) {
    try {
            $zabbixAPI = new ZabbixAPI(ZABBIX_URL, ZABBIX_USER, ZABBIX_PASS);
        $graphIds = json_decode($userConfig['zabbix']['graph_ids'] ?? '[]', true);
        if (!empty($graphIds)) {
            $zabbixGraphs = $zabbixAPI->getGraphs($graphIds);
            $refreshInterval = $userConfig['zabbix']['refresh_interval'] ?? 30;
        }
    } catch (Exception $e) {
        $zabbixError = $e->getMessage();
            error_log("Zabbix grafik hatası: " . $e->getMessage());
        } catch (Error $e) {
            $zabbixError = 'Zabbix PHP hatası: ' . $e->getMessage();
            error_log("Zabbix grafik PHP hatası: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Monitor Sistemi</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="dashboard-header">
        <h1 class="dashboard-title">Expresnet Monitör</h1>
        <div class="user-info">
            <span>Hoş geldiniz, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></span>
            <?php if ($_SESSION['username'] === 'admin'): ?>
                <a href="admin.php" class="btn btn-secondary">Admin Panel</a>
                <a href="users.php" class="btn btn-secondary">Kullanıcı Yönetimi</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-secondary">Çıkış</a>
        </div>
    </div>
    
    <div class="dashboard-container">
        <!-- Grafana Dashboard (Ana gösterim) -->
        <section class="dashboard-section">
            <h2>Monitoring Dashboard</h2>
            <?php if ($grafanaError): ?>
                <div class="alert alert-error">
                    <strong>Hata:</strong> <?php echo htmlspecialchars($grafanaError); ?>
                    <?php if ($_SESSION['username'] === 'admin'): ?>
                        <br><small><a href="admin.php">Admin Panel</a>'den müşteriyi düzenleyip doğru Dashboard UID atayın.</small>
                    <?php endif; ?>
                </div>
            <?php elseif (empty($grafanaPanels)): ?>
                <div class="alert alert-info">
                    Dashboard yapılandırılmamış veya panel bulunamadı. Lütfen yönetici ile iletişime geçin.
                </div>
            <?php else: ?>
                <!-- Time Picker Kontrolü -->
                <div class="time-picker-control">
                    <label for="timeRange">Zaman Aralığı:</label>
                    <select id="timeRange" class="time-range-select">
                        <option value="now-5m">Son 5 dakika</option>
                        <option value="now-15m">Son 15 dakika</option>
                        <option value="now-30m">Son 30 dakika</option>
                        <option value="now-1h" selected>Son 1 saat</option>
                        <option value="now-3h">Son 3 saat</option>
                        <option value="now-6h">Son 6 saat</option>
                        <option value="now-12h">Son 12 saat</option>
                        <option value="now-24h">Son 24 saat</option>
                        <option value="now-2d">Son 2 gün</option>
                        <option value="now-7d">Son 7 gün</option>
                        <option value="now-30d">Son 30 gün</option>
                    </select>
                    <button id="refreshBtn" class="btn-refresh">Yenile</button>
                </div>
                
                <div class="grafana-panels-grid">
                    <?php foreach ($grafanaPanels as $panel): ?>
                        <div class="grafana-panel-card">
                            <h3><?php echo htmlspecialchars($panel['title']); ?></h3>
                            <div class="grafana-panel-container">
                                <iframe 
                                    src="<?php echo htmlspecialchars($panel['url']); ?>" 
                                    class="grafana-panel-iframe"
                                    data-base-url="<?php echo htmlspecialchars($panel['url']); ?>"
                                    data-panel-id="<?php echo htmlspecialchars($panel['id']); ?>"
                                    frameborder="0"
                                    allow="fullscreen"
                                    title="<?php echo htmlspecialchars($panel['title']); ?>"
                                    loading="lazy"></iframe>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <style>
                    /* Grafana panelleri için grid layout - Alt alta (tek sütun) */
                    .grafana-panels-grid {
                        display: grid;
                        grid-template-columns: 1fr;
                        gap: 15px;
                    }
                    
                    .grafana-panel-card {
                        background: white;
                        border: 1px solid #e0e0e0;
                        border-radius: 4px;
                        overflow: hidden;
                    }
                    
                    .grafana-panel-card h3 {
                        margin: 0;
                        padding: 10px 15px;
                        font-size: 14px;
                        font-weight: 500;
                        color: #333;
                        border-bottom: 1px solid #e0e0e0;
                    }
                    
                    .grafana-panel-container {
                        width: 100%;
                        height: 500px;
                    }
                    
                    .grafana-panel-iframe {
                        width: 100%;
                        height: 100%;
                        border: none;
                    }
                    
                    /* Time Picker Kontrolü - Minimal Tasarım */
                    .time-picker-control {
                        margin-bottom: 15px;
                        display: flex;
                        align-items: center;
                        gap: 10px;
                    }
                    
                    .time-picker-control label {
                        font-size: 14px;
                        color: #666;
                    }
                    
                    .time-range-select {
                        padding: 6px 10px;
                        border: 1px solid #ddd;
                        border-radius: 4px;
                        font-size: 14px;
                        background: white;
                        cursor: pointer;
                        min-width: 150px;
                    }
                    
                    .time-range-select:focus {
                        outline: none;
                        border-color: #1976d2;
                    }
                    
                    .btn-refresh {
                        padding: 6px 15px;
                        background: #1976d2;
                        color: white;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                        font-size: 14px;
                    }
                    
                    .btn-refresh:hover {
                        background: #1565c0;
                    }
                    
                    /* Responsive */
                    @media (max-width: 768px) {
                        .grafana-panels-grid {
                            grid-template-columns: 1fr;
                        }
                        
                        .time-picker-control {
                            flex-wrap: wrap;
                        }
                    }
                </style>
                
                <script>
                    // Time picker değiştiğinde tüm iframe URL'lerini güncelle
                    document.getElementById('timeRange').addEventListener('change', function() {
                        updatePanelUrls();
                    });
                    
                    // Yenile butonu
                    document.getElementById('refreshBtn').addEventListener('click', function() {
                        updatePanelUrls();
                    });
                    
                    function updatePanelUrls() {
                        const timeRange = document.getElementById('timeRange').value;
                        const iframes = document.querySelectorAll('.grafana-panel-iframe');
                        
                        // Zaman aralığını hesapla
                        const now = Date.now();
                        let fromTime;
                        
                        if (timeRange.startsWith('now-')) {
                            const value = timeRange.substring(4);
                            const unit = value.slice(-1);
                            const amount = parseInt(value.slice(0, -1));
                            
                            let milliseconds = 0;
                            switch(unit) {
                                case 'm': milliseconds = amount * 60 * 1000; break;
                                case 'h': milliseconds = amount * 60 * 60 * 1000; break;
                                case 'd': milliseconds = amount * 24 * 60 * 60 * 1000; break;
                            }
                            
                            fromTime = now - milliseconds;
                        } else {
                            fromTime = now - (60 * 60 * 1000); // Varsayılan: 1 saat
                        }
                        
                        // Her iframe URL'ini güncelle
                        iframes.forEach(function(iframe) {
                            const baseUrl = iframe.getAttribute('data-base-url');
                            if (baseUrl) {
                                // URL'i parse et
                                const url = new URL(baseUrl);
                                
                                // from ve to parametrelerini güncelle
                                url.searchParams.set('from', fromTime.toString());
                                url.searchParams.set('to', now.toString());
                                
                                // Iframe src'yi güncelle
                                iframe.src = url.toString();
                            }
                        });
                    }
                </script>
            <?php endif; ?>
        </section>
        
        <!-- Zabbix Grafikleri (Opsiyonel, geriye dönük uyumluluk) -->
        <?php if (!empty($zabbixGraphs)): ?>
        <section class="dashboard-section">
            <h2>Zabbix Grafikleri</h2>
            <?php if ($zabbixError): ?>
                <div class="alert alert-error">Zabbix Hatası: <?php echo htmlspecialchars($zabbixError); ?></div>
            <?php else: ?>
                <div class="graphs-grid">
                    <?php foreach ($zabbixGraphs as $graph): ?>
                        <div class="graph-card">
                            <h3><?php echo htmlspecialchars($graph['name']); ?></h3>
                            <div class="graph-container">
                                <img src="api/zabbix_graph.php?graphid=<?php echo $graph['graphid']; ?>" 
                                     alt="<?php echo htmlspecialchars($graph['name']); ?>"
                                     class="graph-image"
                                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\'%3E%3Ctext%3EGrafik yüklenemedi%3C/text%3E%3C/svg%3E'">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>
    
    <footer class="dashboard-footer">
        <p>Expresnet Monitör</p>
    </footer>
    
    <style>
        /* Minimal Başlık Stili */
        .dashboard-header {
            padding: 10px 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .dashboard-title {
            font-size: 18px;
            font-weight: 400;
            color: #666;
            margin: 0;
            padding: 0;
        }
        
        /* Minimal Footer Stili */
        .dashboard-footer {
            margin-top: 30px;
            padding: 15px 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            background: #f8f9fa;
        }
        
        .dashboard-footer p {
            margin: 0;
            font-size: 14px;
            color: #666;
            font-weight: 400;
        }
    </style>
    
    <script src="assets/js/dashboard.js"></script>
</body>
</html>
