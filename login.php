<?php
// login.php - DISEÑO MATRIX + SEGURIDAD BLINDADA + SESIONES EN TIEMPO REAL
define('SECURE_ACCESS', true);
require_once 'db.php'; 

// ============================================
// 🔐 VERIFICACIÓN DE FRASE SECRETA
// ============================================
$FRASES_SECRETAS = [
    'Max0702',                 // Tu frase secreta
    'acceso-' . date('Ymd'),          // Frase diaria automática
];

$frase_proporcionada = $_GET['key'] ?? '';
$acceso_permitido = false;

foreach ($FRASES_SECRETAS as $frase_valida) {
    if ($frase_proporcionada === $frase_valida) {
        $acceso_permitido = true;
        break;
    }
}

// Permitir POST siempre (para el formulario de login interno)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acceso_permitido = true;
}

// Si es GET sin frase válida → MOSTRAR PÁGINA FALSA
if (!$acceso_permitido && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Mostrar página falsa de error
    header("HTTP/1.0 404 Not Found");
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>404 Not Found</title>
        <style>
            body { font-family: Arial; text-align: center; padding: 50px; background: #f5f5f5; }
            .error-box { 
                background: white; 
                padding: 40px; 
                border-radius: 10px; 
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
                display: inline-block;
                margin-top: 100px;
            }
            h1 { color: #333; }
            p { color: #666; margin: 20px 0; }
            a { color: #0066cc; text-decoration: none; }
            a:hover { text-decoration: underline; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>404 - Página no encontrada</h1>
            <p>La página solicitada no existe en este servidor.</p>
            <p>Verifica la URL e intenta nuevamente.</p>
            <p><a href="/">Volver al inicio</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================
// SI TIENE ACCESO, INICIAR SESIÓN Y MOSTRAR LOGIN REAL
// ============================================
ob_start();
session_start();
error_reporting(0);

// NOMBRES DE ARCHIVO SEGUROS (Se guardan como .php)
$usersFile = 'storage_users';
$colorsFile = 'storage_colors';
$pendingFile = 'storage_pending';
$sessionsFile = 'storage_sessions';
$configFile = 'system_config';

// COLORES DEL SISTEMA (Para la interfaz visual)
$COLORES_SISTEMA = [
    '#FF0000', '#00FF00', '#0066FF', '#FFD700', '#FF00FF', 
    '#00FFFF', '#FF4500', '#9400D3', '#00FA9A', '#DC143C'
];

// REPARACIÓN AUTOMÁTICA (Si no hay usuarios, crea admin)
$users = loadData($usersFile);
if (empty($users)) {
    $users['Max0702'] = password_hash('Kailyn0702', PASSWORD_DEFAULT);
    saveData($usersFile, $users);
}

// RECONEXIÓN AUTOMÁTICA (Solo GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        header('Location: admin.php');
        exit;
    }
}

// PROCESAMIENTO AJAX (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // 1. LOGIN ADMIN
    if ($action === 'admin_login') {
        $pass = $_POST['password'] ?? '';
        $users = loadData($usersFile);
        
        if (isset($users['admin']) && password_verify($pass, $users['admin'])) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = 'admin';
            
            // REGISTRAR SESIÓN ADMIN (Para que aparezcas online)
            $sessions = loadData($sessionsFile);
            $sessions['admin|'.$_SERVER['REMOTE_ADDR']] = time();
            saveData($sessionsFile, $sessions);
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Contraseña incorrecta']);
        }
        exit;
    }

    // 2. VERIFICAR SI EL USUARIO EXISTE O TIENE PASS
    if ($action === 'check_user_state') {
        $username = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['username']);
        $users = loadData($usersFile);
        
        $exists = isset($users[$username]);
        $hasPass = ($exists && $users[$username] !== 'nopassword');
        
        echo json_encode([
            'success' => true,
            'exists' => $exists,
            'has_password' => $hasPass
        ]);
        exit;
    }

    // 3. LOGIN USUARIO CON CONTRASEÑA
    if ($action === 'user_password_login') {
        $username = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['username']);
        $pass = $_POST['password'];
        $users = loadData($usersFile);
        
        if (isset($users[$username]) && password_verify($pass, $users[$username])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            
            // ✅ REGISTRAR SESIÓN AL INSTANTE
            $sessions = loadData($sessionsFile);
            $sessions[$username.'|'.$_SERVER['REMOTE_ADDR']] = time();
            saveData($sessionsFile, $sessions);
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Contraseña incorrecta']);
        }
        exit;
    }

    // 4. SOLICITUD DE ACCESO (PENDING)
    if ($action === 'request_access') {
        $username = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['username']);
        $color = $_POST['color'] ?? '';
        
        if(strtolower($username) === 'admin') {
            echo json_encode(['success' => false, 'error' => 'Admin requiere contraseña']); exit;
        }

        $pending = loadData($pendingFile);
        $pending[$username] = [
            'username' => $username,
            'color' => $color,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'status' => 'pending',
            'timestamp' => time()
        ];
        saveData($pendingFile, $pending);
        echo json_encode(['success' => true]);
        exit;
    }

    // 5. POLLING DE ESTADO
    if ($action === 'check_status') {
        $username = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['username']);
        $ip = $_SERVER['REMOTE_ADDR'];
        
        $pending = loadData($pendingFile);
        $sessions = loadData($sessionsFile);
        
        $isApproved = (isset($pending[$username]) && $pending[$username]['status'] === 'approved');
        $isActive = ($username !== 'admin' && isset($sessions[$username.'|'.$ip]));

        if ($isApproved || $isActive) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            
            // REGISTRAR SESIÓN
            $sessions = loadData($sessionsFile); 
            $sessions[$username.'|'.$ip] = time();
            saveData($sessionsFile, $sessions);

            // Limpiar de pendientes
            if($isApproved) { 
                unset($pending[$username]); 
                saveData($pendingFile, $pending); 
            }
            
            echo json_encode(['success' => true, 'status' => 'approved']);
        } elseif (isset($pending[$username]) && $pending[$username]['status'] === 'rejected') {
            unset($pending[$username]); 
            saveData($pendingFile, $pending);
            echo json_encode(['success' => false, 'error' => 'Solicitud rechazada']);
        } else {
            echo json_encode(['success' => true, 'status' => 'pending']);
        }
        exit;
    }
}

// ============================================
// IMPORTANTE: SI LLEGA AQUÍ, MOSTRAR EL HTML DEL LOGIN
// ============================================
// ESTE ES TU CÓDIGO HTML ORIGINAL (COPIADO DE TU ARCHIVO)
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Seguro</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            background: url('matrix.gif') center/cover no-repeat fixed #000;
            font-family: 'Segoe UI', system-ui, sans-serif;
            display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; overflow: hidden;
        }
        body::before { content: ''; position: absolute; inset: 0; background: rgba(0, 0, 0, 0.7); z-index: 0; }
        
        .login-card {
            position: relative; z-index: 1;
            background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(20px);
            padding: 40px; border-radius: 16px; width: 100%; max-width: 360px;
            border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;
            box-shadow: 0 0 40px rgba(16, 185, 129, 0.15); transition: all 0.3s;
        }

        .logo-icon { font-size: 3rem; color: #10b981; margin-bottom: 15px; text-shadow: 0 0 15px rgba(16, 185, 129, 0.5); }
        h1 { color: #fff; margin: 0 0 25px 0; font-size: 1.4rem; text-transform: uppercase; letter-spacing: 3px; font-weight: 300; }

        .wrapper { position: relative; text-align: left; margin-bottom: 15px; }
        .form-control {
            width: 100%; padding: 12px 15px 12px 45px; background: rgba(15, 23, 42, 0.6);
            border: 1px solid #334155; border-radius: 8px; color: #fff;
            font-size: 1rem; outline: none; box-sizing: border-box; transition: 0.3s;
        }
        .form-control:focus { border-color: #10b981; box-shadow: 0 0 15px rgba(16, 185, 129, 0.2); background: rgba(15, 23, 42, 0.9); }
        .input-icon { position: absolute; left: 15px; top: 14px; color: #64748b; transition: 0.3s; }
        .form-control:focus + .input-icon { color: #10b981; }

        #passwordSection, #colorSection { display: none; }

        .color-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin: 20px 0; }
        .color-opt { 
            width: 34px; height: 34px; border-radius: 50%; cursor: pointer; 
            border: 2px solid transparent; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
            position: relative; box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        .color-opt:hover { transform: scale(1.15); z-index: 2; }
        .color-opt.selected { border-color: white; transform: scale(1.1); box-shadow: 0 0 15px white; }
        .color-blocked { opacity: 0.2; cursor: not-allowed !important; filter: grayscale(1); transform: none !important; }

        .btn-login {
            width: 100%; padding: 14px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
            color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; 
            font-size: 1rem; text-transform: uppercase; letter-spacing: 1px; transition: 0.3s;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4); }
        .btn-login:disabled { background: #475569; cursor: not-allowed; transform: none; box-shadow: none; }

        .loader-container { display: none; padding: 20px; animation: fadeIn 0.5s; }
        .spinner {
            width: 50px; height: 50px; border: 3px solid rgba(16, 185, 129, 0.1);
            border-top-color = #10b981; border-radius: 50%; animation: spin 1s linear infinite;
            margin: 0 auto 15px auto; box-shadow: 0 0 15px rgba(16, 185, 129, 0.2);
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .error-msg { 
            background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; 
            padding: 12px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 20px; display: none; 
            animation: fadeIn 0.3s;
        }
    </style>
</head>
<body>

    <div class="login-card" id="loginBox">
        <div class="logo-icon"><i class="fas fa-shield-alt"></i></div>
        <h1>Identificación</h1>
        
        <div class="error-msg" id="errorMsg"></div>

        <form id="loginForm">
            <div class="wrapper">
                <input type="text" id="username" class="form-control" placeholder="Usuario" required autocomplete="off">
                <i class="fas fa-user input-icon"></i>
            </div>

            <div id="passwordSection" class="wrapper">
                <input type="password" id="password" class="form-control" placeholder="Contraseña">
                <i class="fas fa-lock input-icon"></i>
            </div>

            <div id="colorSection">
                <p style="color:#94a3b8; font-size:0.85rem; margin-bottom:10px;">Selecciona tu distintivo:</p>
                <div class="color-grid">
                    <?php 
                    $colorsData = loadData($colorsFile);
                    $takenValues = array_values($colorsData); 
                    foreach($COLORES_SISTEMA as $c): 
                        $isTaken = in_array($c, $takenValues);
                        $blockedClass = $isTaken ? 'color-blocked' : '';
                        $onclick = $isTaken ? '' : "selectColor('$c', this)";
                    ?>
                        <div class="color-opt <?php echo $blockedClass; ?>" 
                             style="background:<?php echo $c; ?>" 
                             onclick="<?php echo $onclick; ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="selectedColor">
            </div>

            <button type="submit" id="btnAcceder" class="btn-login">Siguiente</button>
        </form>

        <div class="loader-container" id="loadingBox">
            <div class="spinner"></div>
            <div style="color:#10b981; font-weight:bold; letter-spacing:1px;">ESPERANDO AUTORIZACIÓN</div>
            <p style="color:#64748b; font-size:0.8rem; margin-top:10px;">El administrador debe aprobar tu acceso.</p>
        </div>
    </div>

<script>
    let mode = 'check'; // check, login, request
    let typingTimer;

    // Detectar fin de escritura para fluidez
    $('#username').on('input', function() {
        clearTimeout(typingTimer);
        const val = $(this).val().trim();
        
        // Reset visual
        $('#passwordSection').slideUp(200);
        $('#colorSection').slideUp(200);
        $('#btnAcceder').text('Siguiente');
        $('#errorMsg').slideUp();
        mode = 'check';
        
        if(val.length > 0) {
            typingTimer = setTimeout(() => checkUser(val), 500);
        }
    });

    function checkUser(user) {
        if(user.toLowerCase() === 'admin') {
            switchToPassword();
            return;
        }
        
        $.post('login.php', { action: 'check_user_state', username: user }, function(res) {
            if(res.has_password) {
                switchToPassword();
            } else if (!res.exists) {
                switchToColor(); // Usuario nuevo
            } else {
                // Usuario existe pero es nopassword -> Mostrar color (opcional) o solicitar directo
                switchToColor(); 
            }
        }, 'json');
    }

    function switchToPassword() {
        $('#passwordSection').slideDown(300);
        $('#colorSection').slideUp();
        $('#btnAcceder').text('Acceder');
        mode = 'login';
    }

    function switchToColor() {
        $('#passwordSection').slideUp();
        $('#colorSection').slideDown(300).css('display', 'grid');
        $('#btnAcceder').text('Solicitar Acceso');
        mode = 'request';
    }

    function selectColor(color, el) {
        $('.color-opt').removeClass('selected');
        $(el).addClass('selected');
        $('#selectedColor').val(color);
    }

    $('#loginForm').on('submit', function(e) {
        e.preventDefault();
        $('#errorMsg').hide();
        const user = $('#username').val().trim();
        
        if(mode === 'login') {
            const pass = $('#password').val();
            const action = (user.toLowerCase() === 'admin') ? 'admin_login' : 'user_password_login';
            
            const btn = $('#btnAcceder');
            btn.prop('disabled', true).text('Verificando...');
            
            $.post('login.php', { action: action, username: user, password: pass }, function(res) {
                if(res.success) {
                    $('.login-card').fadeOut();
                    setTimeout(() => window.location.href = 'admin.php', 300);
                } else {
                    $('#errorMsg').text(res.error).slideDown();
                    btn.prop('disabled', false).text('Acceder');
                }
            }, 'json');
        } 
        else if (mode === 'request') {
            const color = $('#selectedColor').val();
            if(!color && !$('#colorSection').is(':hidden')) { // Si es visible el color, es obligatorio
                $('#errorMsg').text('Debes seleccionar un color distintivo').slideDown(); return; 
            }
            
            $.post('login.php', { action: 'request_access', username: user, color: color }, function(res) {
                if(res.success) {
                    $('#loginForm').slideUp();
                    $('#loadingBox').fadeIn();
                    $('.logo-icon i').addClass('fa-spin'); // Efecto visual
                    startPolling(user);
                } else {
                    $('#errorMsg').text(res.error).slideDown();
                }
            }, 'json');
        }
        else {
            // Si le dan enter en 'check', forzar chequeo
            checkUser(user);
        }
    });

    function startPolling(user) {
        setInterval(() => {
            $.post('login.php', { action: 'check_status', username: user }, function(res) {
                if(res.success && res.status === 'approved') window.location.href = 'admin.php';
                if(res.success === false) location.reload();
            }, 'json');
        }, 2000);
    }
</script>
</body>
</html>
<?php
ob_end_flush();
?>