<?php
/**
 * Admin Kullanıcı Kurulum Scripti
 * Bu dosyayı bir kez çalıştırın, sonra silin veya koruyun
 */
require_once 'config.php';
require_once 'includes/database.php';

$db = Database::getInstance()->getConnection();

echo "<h2>Admin Kullanıcı Kurulumu</h2>";

// Müşteri var mı kontrol et
$stmt = $db->query("SELECT COUNT(*) as count FROM customers");
$customerCount = $stmt->fetch()['count'];

if ($customerCount == 0) {
    // Örnek müşteri oluştur
    $stmt = $db->prepare("INSERT INTO customers (name, active) VALUES (?, 1)");
    $stmt->execute(['Admin Müşteri']);
    $customerId = $db->lastInsertId();
    echo "<p>✓ Örnek müşteri oluşturuldu (ID: $customerId)</p>";
} else {
    $stmt = $db->query("SELECT id FROM customers LIMIT 1");
    $customerId = $stmt->fetch()['id'];
    echo "<p>✓ Mevcut müşteri kullanılıyor (ID: $customerId)</p>";
}

// Admin kullanıcısı var mı kontrol et
$stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute(['admin']);
$existingUser = $stmt->fetch();

$username = 'admin';
$password = 'admin'; // Şifreyi buradan değiştirebilirsiniz
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

if ($existingUser) {
    // Mevcut kullanıcıyı güncelle
    $stmt = $db->prepare("UPDATE users SET password_hash = ?, customer_id = ?, active = 1 WHERE username = ?");
    $stmt->execute([$passwordHash, $customerId, $username]);
    echo "<p>✓ Admin kullanıcısı güncellendi</p>";
    echo "<p><strong>Kullanıcı Adı:</strong> admin</p>";
    echo "<p><strong>Şifre:</strong> $password</p>";
} else {
    // Yeni kullanıcı oluştur
    $stmt = $db->prepare("INSERT INTO users (username, password_hash, customer_id, full_name, email, active) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->execute([$username, $passwordHash, $customerId, 'Admin User', 'admin@example.com']);
    echo "<p>✓ Admin kullanıcısı oluşturuldu</p>";
    echo "<p><strong>Kullanıcı Adı:</strong> admin</p>";
    echo "<p><strong>Şifre:</strong> $password</p>";
}

echo "<hr>";
echo "<p><strong>ÖNEMLİ:</strong> Bu script'i production'da silin veya koruyun!</p>";
echo "<p><a href='index.php'>Giriş Sayfasına Git</a></p>";
?>

