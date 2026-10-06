<?php
/**
 * Router para PHP built-in server
 * Bloquea acceso directo a archivos sensibles
 * En Apache este archivo se ignora (usa .htaccess)
 */

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Archivos/extensiones bloqueadas
$blocked_patterns = [
    '.json',
    '.htaccess', 
    '.htpasswd',
    'db.php',
    'config.php'
];

foreach ($blocked_patterns as $pattern) {
    if (stripos($path, $pattern) !== false) {
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1></body></html>';
        exit;
    }
}

// Dejar que el servidor PHP maneje el resto normalmente
return false;
