<?php
session_start();

// Archivo de sesiones activas (el mismo que usas en los otros archivos)
$sessionsFile = 'active_sessions.json';
$ip = $_SERVER['REMOTE_ADDR'];

// 1. ELIMINAR EL "PERMISO DE RECONEXIÓN" DEL JSON
if (file_exists($sessionsFile)) {
    $sessions = json_decode(file_get_contents($sessionsFile), true) ?: [];
    $modified = false;

    // Buscamos cualquier sesión asociada a esta IP y la borramos
    foreach ($sessions as $key => $data) {
        // Verificamos si la IP coincide
        if (isset($data['ip']) && $data['ip'] === $ip) {
            unset($sessions[$key]);
            $modified = true;
        }
    }

    // Si borramos algo, guardamos el archivo actualizado
    if ($modified) {
        file_put_contents($sessionsFile, json_encode($sessions, JSON_PRETTY_PRINT));
    }
}

// 2. DESTRUIR LA SESIÓN DE PHP
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// 3. REDIRIGIR AL LOGIN
// Ahora login.php no encontrará la sesión en el JSON y mostrará el formulario.
header("Location: login.php");
exit;
?>