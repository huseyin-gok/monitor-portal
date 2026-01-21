<?php
// Veritabanı Bağlantı Sınıfı (MySQL)
class Database {
    private static $instance = null;
    private $db;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->db = new PDO($dsn, DB_USER, DB_PASS, $options);
            $this->createTables();
        } catch (PDOException $e) {
            die("Veritabanı bağlantı hatası: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->db;
    }
    
    private function createTables() {
        // Customers tablosu
        $sql = "
        CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL COMMENT 'Müşteri adı',
            grafana_folder_id INT DEFAULT NULL COMMENT 'Grafana folder ID',
            grafana_dashboard_uid VARCHAR(50) DEFAULT NULL COMMENT 'Grafana dashboard UID',
            grafana_viewer_user_id INT DEFAULT NULL COMMENT 'Grafana viewer kullanıcı ID',
            active TINYINT(1) DEFAULT 1 COMMENT 'Aktif/Pasif',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_grafana_folder (grafana_folder_id),
            INDEX idx_grafana_dashboard (grafana_dashboard_uid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $this->db->exec($sql);
        
        // Users tablosu
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            password_hash VARCHAR(255) NOT NULL COMMENT 'PHP password_hash() ile oluşturulmuş hash',
            customer_id INT NOT NULL COMMENT 'Hangi müşteriye ait',
            full_name VARCHAR(100),
            email VARCHAR(100),
            active TINYINT(1) DEFAULT 1,
            last_login DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_username (username),
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
            INDEX idx_customer (customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $this->db->exec($sql);
        
        // User Zabbix Config tablosu (opsiyonel, geriye dönük uyumluluk)
        $sql = "
        CREATE TABLE IF NOT EXISTS user_zabbix_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            graph_ids TEXT COMMENT 'JSON array: [\"123\", \"456\"]',
            refresh_interval INT DEFAULT 30 COMMENT 'saniye',
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $this->db->exec($sql);
        
        // User Grafana Config tablosu (opsiyonel, geriye dönük uyumluluk)
        $sql = "
        CREATE TABLE IF NOT EXISTS user_grafana_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            dashboard_uid VARCHAR(50),
            panel_ids TEXT COMMENT 'JSON array: [\"1\", \"2\"]',
            refresh_interval INT DEFAULT 30 COMMENT 'saniye',
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $this->db->exec($sql);
    }
}
