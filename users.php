<?php
/**
 * Kullanıcı Yönetim Sayfası
 * Kullanıcı oluşturma, düzenleme ve listeleme
 */
require_once 'config.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

// Basit admin kontrolü
$isAdmin = ($_SESSION['username'] === 'admin');

if (!$isAdmin) {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$message = '';
$error = '';

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_user') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $customerId = intval($_POST['customer_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        // Validasyon
        if (empty($username) || empty($password)) {
            $error = 'Kullanıcı adı ve şifre zorunludur!';
        } elseif ($password !== $passwordConfirm) {
            $error = 'Şifreler eşleşmiyor!';
        } elseif (strlen($password) < 6) {
            $error = 'Şifre en az 6 karakter olmalıdır!';
        } elseif ($customerId <= 0) {
            $error = 'Müşteri seçimi zorunludur!';
        } else {
            try {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("
                    INSERT INTO users (username, password_hash, customer_id, full_name, email, active) 
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$username, $passwordHash, $customerId, $fullName, $email]);
                $message = 'Kullanıcı başarıyla oluşturuldu!';
                
                // Formu temizle
                $_POST = [];
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'Bu kullanıcı adı zaten kullanılıyor!';
                } else {
                    $error = 'Kullanıcı oluşturma hatası: ' . $e->getMessage();
                }
            }
        }
    }
    
    if ($action === 'update_user') {
        $userId = intval($_POST['user_id'] ?? 0);
        $customerId = intval($_POST['customer_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;
        
        if ($userId <= 0) {
            $error = 'Geçersiz kullanıcı ID!';
        } elseif ($customerId <= 0) {
            $error = 'Müşteri seçimi zorunludur!';
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET customer_id = ?, full_name = ?, email = ?, active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$customerId, $fullName, $email, $active, $userId]);
                $message = 'Kullanıcı başarıyla güncellendi!';
            } catch (PDOException $e) {
                $error = 'Kullanıcı güncelleme hatası: ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'change_password') {
        $userId = intval($_POST['user_id'] ?? 0);
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        
        if ($userId <= 0) {
            $error = 'Geçersiz kullanıcı ID!';
        } elseif (empty($password)) {
            $error = 'Şifre zorunludur!';
        } elseif ($password !== $passwordConfirm) {
            $error = 'Şifreler eşleşmiyor!';
        } elseif (strlen($password) < 6) {
            $error = 'Şifre en az 6 karakter olmalıdır!';
        } else {
            try {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$passwordHash, $userId]);
                $message = 'Şifre başarıyla değiştirildi!';
            } catch (PDOException $e) {
                $error = 'Şifre değiştirme hatası: ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'delete_user') {
        $userId = intval($_POST['user_id'] ?? 0);
        
        if ($userId <= 0) {
            $error = 'Geçersiz kullanıcı ID!';
        } elseif ($userId == $_SESSION['user_id']) {
            $error = 'Kendi kullanıcınızı silemezsiniz!';
        } else {
            try {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $message = 'Kullanıcı başarıyla silindi!';
            } catch (PDOException $e) {
                $error = 'Kullanıcı silme hatası: ' . $e->getMessage();
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
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - Monitor Sistemi</title>
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
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-small {
            padding: 5px 10px;
            font-size: 14px;
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
        <h1>Kullanıcı Yönetimi</h1>
        <div class="user-info">
            <a href="admin.php" class="btn btn-secondary">Admin Panel</a>
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
        
        <!-- Yeni Kullanıcı Ekleme -->
        <div class="admin-section">
            <h2>Yeni Kullanıcı Ekle</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_user">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Kullanıcı Adı *</label>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="customer_id">Müşteri *</label>
                        <select id="customer_id" name="customer_id" required>
                            <option value="">Seçiniz...</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>" 
                                        <?php echo (isset($_POST['customer_id']) && $_POST['customer_id'] == $customer['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($customer['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Şifre *</label>
                        <input type="password" id="password" name="password" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="password_confirm">Şifre Tekrar *</label>
                        <input type="password" id="password_confirm" name="password_confirm" required minlength="6">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Ad Soyad</label>
                        <input type="text" id="full_name" name="full_name" 
                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">E-posta</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Kullanıcı Oluştur</button>
            </form>
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
                        <th>E-posta</th>
                        <th>Son Giriş</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                            <td><?php echo $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : '-'; ?></td>
                            <td><?php echo $user['active'] ? '<span style="color:green">Aktif</span>' : '<span style="color:red">Pasif</span>'; ?></td>
                            <td>
                                <button onclick="editUser(<?php echo $user['id']; ?>)" class="btn btn-secondary btn-small">Düzenle</button>
                                <button onclick="changePassword(<?php echo $user['id']; ?>)" class="btn btn-secondary btn-small">Şifre Değiştir</button>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Bu kullanıcıyı silmek istediğinizden emin misiniz?');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-small">Sil</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Düzenleme Modal (basit) -->
    <div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
        <div style="background:white; margin:50px auto; padding:20px; max-width:600px; border-radius:10px;">
            <h2>Kullanıcı Düzenle</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-group">
                    <label>Müşteri *</label>
                    <select name="customer_id" id="edit_customer_id" required>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>"><?php echo htmlspecialchars($customer['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Ad Soyad</label>
                    <input type="text" name="full_name" id="edit_full_name">
                </div>
                
                <div class="form-group">
                    <label>E-posta</label>
                    <input type="email" name="email" id="edit_email">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="active" id="edit_active" value="1"> Aktif
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary">Kaydet</button>
                <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="btn btn-secondary">İptal</button>
            </form>
        </div>
    </div>
    
    <!-- Şifre Değiştirme Modal -->
    <div id="passwordModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
        <div style="background:white; margin:50px auto; padding:20px; max-width:600px; border-radius:10px;">
            <h2>Şifre Değiştir</h2>
            <form method="POST" id="passwordForm">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="user_id" id="password_user_id">
                
                <div class="form-group">
                    <label>Yeni Şifre *</label>
                    <input type="password" name="password" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label>Şifre Tekrar *</label>
                    <input type="password" name="password_confirm" required minlength="6">
                </div>
                
                <button type="submit" class="btn btn-primary">Şifreyi Değiştir</button>
                <button type="button" onclick="document.getElementById('passwordModal').style.display='none'" class="btn btn-secondary">İptal</button>
            </form>
        </div>
    </div>
    
    <script>
        const users = <?php echo json_encode($users); ?>;
        
        function editUser(userId) {
            const user = users.find(u => u.id == userId);
            if (!user) return;
            
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_customer_id').value = user.customer_id;
            document.getElementById('edit_full_name').value = user.full_name || '';
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_active').checked = user.active == 1;
            
            document.getElementById('editModal').style.display = 'block';
        }
        
        function changePassword(userId) {
            document.getElementById('password_user_id').value = userId;
            document.getElementById('passwordModal').style.display = 'block';
        }
    </script>
</body>
</html>

