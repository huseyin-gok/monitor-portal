<?php
/**
 * Admin Panel - Müşteri ve Dashboard Yönetimi
 * Production-ready admin interface
 */
require_once 'config.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/grafana_api.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance()->getConnection();
$message = '';
$error = '';

// Basit admin kontrolü (production'da daha gelişmiş yetkilendirme kullanın)
// Örnek: admin kullanıcısı veya özel role kontrolü
$isAdmin = ($_SESSION['username'] === 'admin'); // Basit kontrol

if (!$isAdmin) {
    header('Location: dashboard.php');
    exit;
}

// Grafana API instance (opsiyonel - API Key yoksa null kalır)
$grafanaAPI = null;
if (!empty(GRAFANA_API_KEY)) {
    try {
        $grafanaAPI = new GrafanaAPI(GRAFANA_INTERNAL_URL, GRAFANA_API_KEY, GRAFANA_ORG_ID);
    } catch (Exception $e) {
        // Grafana API hatası olsa bile devam et (sadece uyarı)
        error_log("Grafana API bağlantı hatası: " . $e->getMessage());
    }
}

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_customer') {
        $customerName = trim($_POST['customer_name'] ?? '');
        $templateDashboardUid = trim($_POST['template_dashboard_uid'] ?? '');
        
        if (empty($customerName)) {
            $error = 'Müşteri adı boş olamaz!';
        } else {
            $folderId = null;
            $dashboardUid = null;
            
            // Grafana API Key varsa, Grafana işlemlerini yap
            if ($grafanaAPI && !empty(GRAFANA_API_KEY)) {
                try {
                    // 1. Grafana'da folder oluştur
                    $folderTitle = 'Customer: ' . $customerName;
                    $folderResult = $grafanaAPI->createFolder($folderTitle);
                    $folderId = $folderResult['id'];
                    
                    // 1.5. Folder'a Viewer permission ver (anonymous kullanıcılar için)
                    // Bu sayede anonymous kullanıcılar sadece bu folder'ı görebilir
                    try {
                        $grafanaAPI->grantFolderViewPermissionToViewers($folderId);
                    } catch (Exception $e) {
                        // Permission hatası olsa bile devam et
                        error_log("Folder permission hatası: " . $e->getMessage());
                    }
                    
                    // 2. Dashboard'u kopyala (eğer template belirtilmişse)
                    if (!empty($templateDashboardUid)) {
                        try {
                            $newDashboardTitle = $customerName . ' Dashboard';
                            $copyResult = $grafanaAPI->copyDashboard($templateDashboardUid, $folderId, $newDashboardTitle);
                            $dashboardUid = $copyResult['dashboard']['uid'] ?? null;
                            
                            if (!$dashboardUid) {
                                $error = 'Dashboard kopyalandı ancak UID alınamadı! Grafana yanıtını kontrol edin.';
                                error_log("Dashboard UID alınamadı. Grafana yanıtı: " . json_encode($copyResult));
                            }
                        } catch (Exception $e) {
                            $error = 'Dashboard kopyalama hatası: ' . $e->getMessage();
                            error_log("Dashboard kopyalama hatası: " . $e->getMessage());
                            // Folder oluşturuldu ama dashboard kopyalanamadı
                        }
                    }
                } catch (Exception $e) {
                    // Grafana hatası olsa bile müşteriyi oluştur
                    $error = 'Grafana işlemleri hatası: ' . $e->getMessage();
                    error_log("Grafana işlemleri hatası: " . $e->getMessage());
                }
            } else {
                // Grafana API Key yoksa, sadece veritabanına kaydet
                if (empty(GRAFANA_API_KEY)) {
                    $error = 'Grafana API Key yapılandırılmamış! config.php dosyasında GRAFANA_API_KEY değerini ayarlayın.';
                } else {
                    $error = 'Grafana API bağlantısı kurulamadı!';
                }
            }
            
            // 3. Veritabanına müşteri ekle (sadece kritik hata yoksa)
            if (empty($error) || strpos($error, 'Grafana API Key') === false) {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO customers (name, grafana_folder_id, grafana_dashboard_uid, active) 
                        VALUES (?, ?, ?, 1)
                    ");
                    $stmt->execute([$customerName, $folderId, $dashboardUid]);
                    $customerId = $db->lastInsertId();
                    
                    if ($folderId && $dashboardUid) {
                        $message = "Müşteri başarıyla oluşturuldu! Folder ID: $folderId, Dashboard UID: $dashboardUid";
                    } elseif ($folderId) {
                        $message = "Müşteri oluşturuldu! Folder ID: $folderId (Dashboard kopyalanamadı - template UID kontrol edin)";
                    } else {
                        $message = "Müşteri oluşturuldu! (Grafana işlemleri atlandı)";
                    }
                    
                    // Eğer hata varsa ama müşteri oluşturulduysa, hata mesajını temizle
                    if ($customerId && !empty($error) && strpos($error, 'Grafana') !== false) {
                        $message .= " - Not: " . $error;
                        $error = ''; // Hata mesajını temizle, bilgi mesajı olarak göster
                    }
                } catch (PDOException $e) {
                    $error = 'Veritabanı hatası: ' . $e->getMessage();
                    error_log("Customer database error: " . $e->getMessage());
                }
            }
        }
    }
    
    if ($action === 'create_user') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $customerId = intval($_POST['customer_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($username) || empty($password) || $customerId <= 0) {
            $error = 'Kullanıcı adı, şifre ve müşteri seçimi zorunludur!';
        } else {
            try {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("
                    INSERT INTO users (username, password_hash, customer_id, full_name, email, active) 
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$username, $passwordHash, $customerId, $fullName, $email]);
                $message = 'Kullanıcı başarıyla oluşturuldu!';
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'Bu kullanıcı adı zaten kullanılıyor!';
                } else {
                    $error = 'Kullanıcı oluşturma hatası: ' . $e->getMessage();
                }
            }
        }
    }
    
    if ($action === 'update_customer') {
        $customerId = intval($_POST['customer_id'] ?? 0);
        $customerName = trim($_POST['customer_name'] ?? '');
        $dashboardUid = trim($_POST['grafana_dashboard_uid'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;
        
        if ($customerId <= 0) {
            $error = 'Geçersiz müşteri ID!';
        } elseif (empty($customerName)) {
            $error = 'Müşteri adı boş olamaz!';
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE customers 
                    SET name = ?, grafana_dashboard_uid = ?, active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$customerName, $dashboardUid ?: null, $active, $customerId]);
                $message = 'Müşteri başarıyla güncellendi!';
            } catch (PDOException $e) {
                $error = 'Müşteri güncelleme hatası: ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'delete_customer') {
        $customerId = intval($_POST['customer_id'] ?? 0);
        
        if ($customerId <= 0) {
            $error = 'Geçersiz müşteri ID!';
        } else {
            try {
                // Önce bu müşteriye ait kullanıcı var mı kontrol et
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE customer_id = ?");
                $stmt->execute([$customerId]);
                $userCount = $stmt->fetch()['count'];
                
                if ($userCount > 0) {
                    $error = "Bu müşteriye ait $userCount kullanıcı var! Önce kullanıcıları silin veya başka müşteriye atayın.";
                } else {
                    // Grafana folder'ı silmeyi deneyebiliriz (opsiyonel)
                    // Şimdilik sadece veritabanından silelim
                    $stmt = $db->prepare("DELETE FROM customers WHERE id = ?");
                    $stmt->execute([$customerId]);
                    $message = 'Müşteri başarıyla silindi!';
                }
            } catch (PDOException $e) {
                $error = 'Müşteri silme hatası: ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'set_folder_permission') {
        $customerId = intval($_POST['customer_id'] ?? 0);
        $viewerUserId = intval($_POST['viewer_user_id'] ?? 0);
        
        if ($customerId <= 0 || $viewerUserId <= 0) {
            $error = 'Müşteri ve viewer kullanıcı seçimi zorunludur!';
        } else {
            try {
                // Müşteri bilgilerini al
                $stmt = $db->prepare("SELECT grafana_folder_id FROM customers WHERE id = ?");
                $stmt->execute([$customerId]);
                $customer = $stmt->fetch();
                
                if (!$customer || !$customer['grafana_folder_id']) {
                    $error = 'Müşteri folder ID bulunamadı!';
                } else {
                    $grafanaAPI->grantFolderViewPermission($customer['grafana_folder_id'], $viewerUserId);
                    $message = 'Folder permission başarıyla ayarlandı!';
                }
            } catch (Exception $e) {
                $error = 'Permission ayarlama hatası: ' . $e->getMessage();
            }
        }
    }
}

// Müşterileri listele
$stmt = $db->query("SELECT * FROM customers ORDER BY name");
$customers = $stmt->fetchAll();

// Kullanıcıları listele
$stmt = $db->query("
    SELECT u.*, c.name as customer_name 
    FROM users u 
    INNER JOIN customers c ON u.customer_id = c.id 
    ORDER BY u.username
");
$users = $stmt->fetchAll();

// Grafana dashboard'ları listele (template seçimi için)
$grafanaDashboards = [];
if ($grafanaAPI) {
    try {
        $grafanaDashboards = $grafanaAPI->getDashboards();
    } catch (Exception $e) {
        // Hata sessizce yok sayılır
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Monitor Sistemi</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .admin-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        .admin-section h2 {
            color: #667eea;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        .btn-small {
            padding: 5px 10px;
            font-size: 14px;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 25px;
            max-width: 600px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <h1>Admin Panel</h1>
        <div class="user-info">
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="logout.php" class="btn btn-secondary">Çıkış</a>
        </div>
    </div>
    
    <div class="admin-container">
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Müşteri Oluşturma -->
        <div class="admin-section">
            <h2>Yeni Müşteri Ekle</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_customer">
                
                <div class="form-group">
                    <label for="customer_name">Müşteri Adı *</label>
                    <input type="text" id="customer_name" name="customer_name" required>
                </div>
                
                <div class="form-group">
                    <label for="template_dashboard_uid">Template Dashboard UID (Opsiyonel)</label>
                    <input type="text" id="template_dashboard_uid" name="template_dashboard_uid" 
                           placeholder="Bu dashboard kopyalanacak">
                    <small>Mevcut dashboard'lar:</small>
                    <div style="max-height: 150px; overflow-y: auto; margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                        <?php foreach ($grafanaDashboards as $dashboard): ?>
                            <div style="padding: 5px;">
                                <strong><?php echo htmlspecialchars($dashboard['uid']); ?></strong> - 
                                <?php echo htmlspecialchars($dashboard['title']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Müşteri Oluştur</button>
            </form>
        </div>
        
        <!-- Kullanıcı Oluşturma -->
        <div class="admin-section">
            <h2>Yeni Kullanıcı Ekle</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_user">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Kullanıcı Adı *</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Şifre *</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="customer_id">Müşteri *</label>
                        <select id="customer_id" name="customer_id" required>
                            <option value="">Seçiniz...</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>">
                                    <?php echo htmlspecialchars($customer['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">E-posta</label>
                        <input type="email" id="email" name="email">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="full_name">Ad Soyad</label>
                    <input type="text" id="full_name" name="full_name">
                </div>
                
                <button type="submit" class="btn btn-primary">Kullanıcı Oluştur</button>
            </form>
        </div>
        
        <!-- Müşteriler Listesi -->
        <div class="admin-section">
            <h2>Müşteriler</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Ad</th>
                        <th>Grafana Folder ID</th>
                        <th>Dashboard UID</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?php echo $customer['id']; ?></td>
                            <td><?php echo htmlspecialchars($customer['name']); ?></td>
                            <td><?php echo $customer['grafana_folder_id'] ?? '<span style="color:orange;">Atanmamış</span>'; ?></td>
                            <td><?php echo !empty($customer['grafana_dashboard_uid']) ? htmlspecialchars($customer['grafana_dashboard_uid']) : '<span style="color:orange;">Atanmamış</span>'; ?></td>
                            <td><?php echo $customer['active'] ? '<span style="color:green;">Aktif</span>' : '<span style="color:red;">Pasif</span>'; ?></td>
                            <td>
                                <button onclick="editCustomer(<?php echo $customer['id']; ?>)" class="btn btn-secondary btn-small">Düzenle</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Bu müşteriyi silmek istediğinizden emin misiniz? Bu müşteriye ait tüm kullanıcılar da silinecektir!');">
                                    <input type="hidden" name="action" value="delete_customer">
                                    <input type="hidden" name="customer_id" value="<?php echo $customer['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-small">Sil</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Kullanıcılar Listesi -->
        <div class="admin-section">
            <h2>Kullanıcılar</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kullanıcı Adı</th>
                        <th>Ad Soyad</th>
                        <th>Müşteri</th>
                        <th>Son Giriş</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['customer_name']); ?></td>
                            <td><?php echo $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : '-'; ?></td>
                            <td><?php echo $user['active'] ? 'Aktif' : 'Pasif'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Müşteri Düzenleme Modal -->
    <div id="editCustomerModal" class="modal">
        <div class="modal-content">
            <h2>Müşteri Düzenle</h2>
            <form method="POST" id="editCustomerForm">
                <input type="hidden" name="action" value="update_customer">
                <input type="hidden" name="customer_id" id="edit_customer_id">
                
                <div class="form-group">
                    <label for="edit_customer_name">Müşteri Adı *</label>
                    <input type="text" id="edit_customer_name" name="customer_name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_grafana_dashboard_uid">Grafana Dashboard UID</label>
                    <input type="text" id="edit_grafana_dashboard_uid" name="grafana_dashboard_uid" 
                           placeholder="Örn: adq6luwavb75sf">
                    <small>Mevcut dashboard'lar:</small>
                    <div style="max-height: 150px; overflow-y: auto; margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                        <?php foreach ($grafanaDashboards as $dashboard): ?>
                            <div style="padding: 5px; cursor: pointer;" onclick="document.getElementById('edit_grafana_dashboard_uid').value='<?php echo htmlspecialchars($dashboard['uid']); ?>'">
                                <strong><?php echo htmlspecialchars($dashboard['uid']); ?></strong> - 
                                <?php echo htmlspecialchars($dashboard['title']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="active" id="edit_customer_active" value="1"> Aktif
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary">Kaydet</button>
                <button type="button" onclick="closeCustomerModal()" class="btn btn-secondary">İptal</button>
            </form>
        </div>
    </div>
    
    <script>
        const customers = <?php echo json_encode($customers); ?>;
        
        function editCustomer(customerId) {
            const customer = customers.find(c => c.id == customerId);
            if (!customer) return;
            
            document.getElementById('edit_customer_id').value = customer.id;
            document.getElementById('edit_customer_name').value = customer.name;
            document.getElementById('edit_grafana_dashboard_uid').value = customer.grafana_dashboard_uid || '';
            document.getElementById('edit_customer_active').checked = customer.active == 1;
            
            document.getElementById('editCustomerModal').style.display = 'block';
        }
        
        function closeCustomerModal() {
            document.getElementById('editCustomerModal').style.display = 'none';
        }
        
        // Modal dışına tıklanınca kapat
        window.onclick = function(event) {
            const modal = document.getElementById('editCustomerModal');
            if (event.target == modal) {
                closeCustomerModal();
            }
        }
    </script>
</body>
</html>
