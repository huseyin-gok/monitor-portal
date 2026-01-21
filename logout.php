<?php
/**
 * Logout Sayfası
 * Kullanıcı çıkış işlemi
 */
try {
    require_once 'config.php';
    require_once 'includes/database.php';
    require_once 'includes/auth.php';
    
    $auth = new Auth();
    $auth->logout();
    
    // Session'ı temizle
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
    }
    
    // Yönlendir
    header('Location: index.php');
    exit;
    
} catch (Exception $e) {
    // Hata olsa bile çıkış yapmaya çalış
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: index.php');
    exit;
} catch (Error $e) {
    // PHP hatası olsa bile çıkış yapmaya çalış
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: index.php');
    exit;
}
