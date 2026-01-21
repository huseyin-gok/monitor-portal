-- Kullanıcı ve Müşteri Kontrol SQL'i
-- Bu SQL'i çalıştırarak kullanıcının customer_id'sini kontrol edin

USE monitor;

-- Admin kullanıcısının bilgilerini göster
SELECT 
    u.id,
    u.username,
    u.customer_id,
    u.active as user_active,
    c.id as customer_exists,
    c.name as customer_name,
    c.active as customer_active,
    c.grafana_folder_id,
    c.grafana_dashboard_uid
FROM users u
LEFT JOIN customers c ON u.customer_id = c.id
WHERE u.username = 'admin';

-- Eğer customer_id NULL veya yanlışsa, düzelt
-- Önce müşteri var mı kontrol et
SELECT id, name FROM customers WHERE id = 1;

-- Eğer müşteri yoksa oluştur
INSERT IGNORE INTO customers (id, name, active) VALUES (1, 'Admin Müşteri', 1);

-- Kullanıcının customer_id'sini güncelle
UPDATE users SET customer_id = 1 WHERE username = 'admin' AND (customer_id IS NULL OR customer_id = 0);

-- Tekrar kontrol
SELECT 
    u.id,
    u.username,
    u.customer_id,
    c.name as customer_name
FROM users u
LEFT JOIN customers c ON u.customer_id = c.id
WHERE u.username = 'admin';

