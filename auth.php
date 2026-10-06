<?php
// auth.php - Protección avanzada
session_start();

function checkAuth() {
    // Verificar si está logueado
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        // Registrar el intento de acceso
        logUnauthorizedAccess();
        header('Location: login.php?error=unauthorized');
        exit;
    }
    
    // Verificar timeout de sesión (8 horas)
    $sessionTimeout = 8 * 60 * 60; // 8 horas en segundos
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > $sessionTimeout)) {
        session_destroy();
        header('Location: login.php?error=timeout');
        exit;
    }
    
    // Regenerar ID de sesión periódicamente
    if (!isset($_SESSION['last_regeneration']) || (time() - $_SESSION['last_regeneration'] > 1800)) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

function logUnauthorizedAccess() {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'request_uri' => $_SERVER['REQUEST_URI'],
        'method' => $_SERVER['REQUEST_METHOD']
    ];
    
    $logFile = 'security.log';
    file_put_contents($logFile, json_encode($logData) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Usar en cada página protegida: checkAuth();
?>