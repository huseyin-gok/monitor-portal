-- MySQL Veritabanı Tabloları
-- Veritabanı: monitor
-- Kullanıcı: monitor
-- Bu SQL dosyasını MySQL/MariaDB'de çalıştırın

-- Veritabanı oluştur (eğer yoksa)
CREATE DATABASE IF NOT EXISTS monitor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Veritabanını kullan
USE monitor;

-- Müşteriler Tablosu
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

-- Kullanıcılar Tablosu
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

-- Zabbix Dashboard Ayarları (opsiyonel, geriye dönük uyumluluk için)
CREATE TABLE IF NOT EXISTS user_zabbix_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    graph_ids TEXT COMMENT 'JSON array: ["123", "456"]',
    refresh_interval INT DEFAULT 30 COMMENT 'saniye',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grafana Dashboard Ayarları (opsiyonel, geriye dönük uyumluluk için)
CREATE TABLE IF NOT EXISTS user_grafana_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    dashboard_uid VARCHAR(50),
    panel_ids TEXT COMMENT 'JSON array: ["1", "2"]',
    refresh_interval INT DEFAULT 30 COMMENT 'saniye',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Örnek müşteri
INSERT INTO customers (name, active) VALUES ('Örnek Müşteri', 1);

-- Örnek admin kullanıcı (şifre: admin)
-- Not: customer_id = 1 (yukarıdaki örnek müşteri)
-- Şifre hash'i: password_hash('admin', PASSWORD_DEFAULT)
INSERT INTO users (username, password_hash, customer_id, full_name, email, active) 
VALUES ('admin', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 1, 'Admin User', 'admin@example.com', 1);

-- Not: Şifre hash'i PHP password_hash() ile oluşturulmalı
-- Örnek: password_hash('admin', PASSWORD_DEFAULT)
-- Veya setup_admin.php script'ini kullanarak admin kullanıcısı oluşturabilirsiniz
